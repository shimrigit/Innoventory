<?php
/**
 * POAgent — WhatsApp bot handler (pre-demo BENCHMARK).
 *
 * Registered in whatsapp_app/apps.json as the handler for the POAgent
 * business line (052-2649555 / 972522649555). WaRouter matches the number,
 * normalizes the Meta payload, and calls poagent_whatsapp_handle_event()
 * once per inbound message — this file never sees the raw webhook body and
 * does no number filtering of its own (the router already did that).
 *
 * Flow, for now (stub - the actual PO/DN logic behind the menu is a later slice):
 *   - No open session (first contact, or one that expired/ended) → send the
 *     menu as tappable WhatsApp reply buttons ("הזמנה חדשה" / "סריקת תעודה"),
 *     state = awaiting_menu_choice.
 *   - In awaiting_menu_choice, tapping "הזמנה חדשה" (or typing "A") →
 *     "מתחיל תהליך הקמת הזמנה" then immediately "הזמנה הושלמה", session ends.
 *   - In awaiting_menu_choice, tapping "סריקת תעודה" (or typing "B") →
 *     "מתחיל תהליך סריקת תעודה" then immediately "סריקת תעודה הושלמה", session
 *     ends.
 *   - In awaiting_menu_choice, anything else → a corrective nudge asking for
 *     A or B; session stays open so they can retry. (Typed A/B stays as a
 *     fallback alongside the buttons - free text always reaches WhatsApp
 *     bots regardless of how the menu rendered.)
 * Every exchange is appended to POAgent/whatsapp/logs/.
 *
 * Shared infra used:
 *   WaClient       (whatsapp_app/lib) — outbound send
 *   WaSessionStore (whatsapp_app/lib) — per-user state (wired, not yet acted on)
 */

require_once __DIR__ . '/../../whatsapp_app/lib/WaClient.php';
require_once __DIR__ . '/../../whatsapp_app/lib/WaSessionStore.php';

const POAGENT_WA_APP_ID  = 'poagent';
const POAGENT_WA_LOG_DIR  = __DIR__ . '/logs';

// Message types that count as "the user said something" → answer with the menu.
// Anything else (reactions, location, system, unsupported…) is logged and ignored.
const POAGENT_WA_ACTIONABLE_TYPES = ['text', 'interactive', 'button'];

// Idle-session timeout: no inbound message for this long → send one
// "still there?" reminder; no inbound message for this much longer again →
// close the session outright. Enforced by poagent_whatsapp_sweep_idle_sessions(),
// which needs something external calling it on a timer - see session_sweeper.php.
const POAGENT_WA_IDLE_REMINDER_AFTER_SECONDS = 300; // 5 minutes
const POAGENT_WA_IDLE_CLOSE_AFTER_SECONDS    = 60;  // +1 minute after the reminder

// Stable button ids for the menu's reply buttons - matched on tap regardless
// of the (translatable, editable) title text shown to the user.
const POAGENT_WA_BTN_PO_NEW  = 'po_new';
const POAGENT_WA_BTN_DN_SCAN = 'dn_scan';

/**
 * Registry handler. $event is WaRouter's normalized event — see
 * WaRouter::normalizeEvent() for the full shape. Must not throw (the router
 * catches, but keep it clean).
 */
function poagent_whatsapp_handle_event(array $event): void
{
    $from = (string) ($event['from'] ?? '');
    $type = (string) ($event['type'] ?? '');
    if ($from === '') {
        return;
    }

    if (!in_array($type, POAGENT_WA_ACTIONABLE_TYPES, true)) {
        poagent_whatsapp_log([
            'ts'      => date('c'),
            'event'   => 'ignored_type',
            'from'    => $from,
            'in_type' => $type,
        ]);
        return;
    }

    $phoneNumberId = (string) ($event['business_phone_number_id'] ?? '');
    $session       = $event['session'] ?? null;
    $replyId       = (string) ($event['reply_id'] ?? '');
    $choice        = strtoupper(trim((string) ($event['text'] ?? '')));
    $chosePo       = $replyId === POAGENT_WA_BTN_PO_NEW || $choice === 'A';
    $choseDn       = $replyId === POAGENT_WA_BTN_DN_SCAN || $choice === 'B';

    if (is_array($session) && ($session['state'] ?? '') === 'awaiting_menu_choice') {
        if ($chosePo) {
            poagent_whatsapp_run_stub_flow(
                $phoneNumberId, $from, $event,
                'po', 'מתחיל תהליך הקמת הזמנה', 'הזמנה הושלמה'
            );
            return;
        }
        if ($choseDn) {
            poagent_whatsapp_run_stub_flow(
                $phoneNumberId, $from, $event,
                'dn', 'מתחיל תהליך סריקת תעודה', 'סריקת תעודה הושלמה'
            );
            return;
        }

        // Anything else while a menu answer was expected - nudge, stay put.
        $reply = poagent_whatsapp_invalid_choice_text();
        $sent  = WaClient::text($phoneNumberId, $from, $reply);
        WaSessionStore::set($from, POAGENT_WA_APP_ID, 'awaiting_menu_choice', [
            'menu_sent_at'             => $session['data']['menu_sent_at'] ?? date('c'),
            'last_msg_id'              => $event['message_id'] ?? '',
            'business_phone_number_id' => $phoneNumberId,
            'last_inbound_at'          => date('c'),
            'reminder_sent_at'         => null,
        ]);
        poagent_whatsapp_log([
            'ts'         => date('c'),
            'direction'  => 'invalid_choice_reply',
            'from'       => $from,
            'contact'    => $event['contact_name'] ?? '',
            'in_type'    => $type,
            'in_text'    => (string) ($event['text'] ?? ''),
            'reply_sent' => $sent,
            'reply_text' => $reply,
        ]);
        return;
    }

    // No open session (first contact, or one that ended/expired) - greet + menu.
    $reply = poagent_whatsapp_menu_body_text();
    $sent  = WaClient::buttons($phoneNumberId, $from, $reply, poagent_whatsapp_menu_buttons());

    // last_inbound_at/reminder_sent_at feed the idle-timeout sweep - every
    // inbound message resets the idle clock and cancels any reminder already
    // sent, regardless of what state ends up storing.
    WaSessionStore::set($from, POAGENT_WA_APP_ID, 'awaiting_menu_choice', [
        'menu_sent_at'              => date('c'),
        'last_msg_id'               => $event['message_id'] ?? '',
        'business_phone_number_id'  => $phoneNumberId,
        'last_inbound_at'           => date('c'),
        'reminder_sent_at'          => null,
    ]);

    poagent_whatsapp_log([
        'ts'            => date('c'),
        'direction'     => 'menu_reply',
        'from'          => $from,
        'contact'       => $event['contact_name'] ?? '',
        'wa_id'         => $event['message_id'] ?? '',
        'in_type'       => $type,
        'in_text'       => (string) ($event['text'] ?? ''),
        'reply_sent'    => $sent,
        'reply_body'    => $reply,
        'reply_buttons' => array_column(poagent_whatsapp_menu_buttons(), 'title'),
    ]);
}

/**
 * Stub A/B flow: announce start, immediately announce completion, end the
 * session. Placeholder for the real PO ('po') / DN ('dn') logic to come.
 */
function poagent_whatsapp_run_stub_flow(
    string $phoneNumberId,
    string $from,
    array $event,
    string $flow,
    string $startText,
    string $doneText
): void {
    $sentStart = WaClient::text($phoneNumberId, $from, $startText);
    $sentDone  = WaClient::text($phoneNumberId, $from, $doneText);
    WaSessionStore::clear($from); // stub completes immediately - session ends per spec

    poagent_whatsapp_log([
        'ts'          => date('c'),
        'direction'   => 'stub_flow_completed',
        'flow'        => $flow, // 'po' | 'dn'
        'from'        => $from,
        'contact'     => $event['contact_name'] ?? '',
        'in_text'     => (string) ($event['text'] ?? ''),
        'start_sent'  => $sentStart,
        'done_sent'   => $sentDone,
    ]);
}

/**
 * Body text of the menu's interactive-buttons message, Hebrew RTL (matches
 * the rest of POAgent's UI language). No A/B instructions needed here - the
 * buttons themselves are the options; typed "A"/"B" still works as a fallback
 * (see poagent_whatsapp_invalid_choice_text()) but isn't advertised anymore.
 */
function poagent_whatsapp_menu_body_text(): string
{
    return implode("\n", [
        'שלום 👋 הגעת ל-POAgent, מערכת ההזמנות ותעודות המשלוח.',
        '',
        'מה תרצה לעשות?',
    ]);
}

/** The menu's two tappable reply buttons - id is matched on tap, title is what's shown. */
function poagent_whatsapp_menu_buttons(): array
{
    return [
        ['id' => POAGENT_WA_BTN_PO_NEW,  'title' => 'הזמנה חדשה'],
        ['id' => POAGENT_WA_BTN_DN_SCAN, 'title' => 'סריקת תעודה'],
    ];
}

/** Sent when a reply while awaiting_menu_choice isn't recognized as A or B. */
function poagent_whatsapp_invalid_choice_text(): string
{
    return 'עליך לשלוח A - להזמנה חדשה או B לסריקת תעודת משלוח';
}

/** First idle nudge - sent once, after POAGENT_WA_IDLE_REMINDER_AFTER_SECONDS of silence. */
function poagent_whatsapp_idle_reminder_text(): string
{
    return 'עדיין שם/ה? אם לא נמשיך את השיחה, היא תיסגר בעוד דקה.';
}

/** Sent once the grace window after the reminder also passes with no reply. */
function poagent_whatsapp_idle_closed_text(): string
{
    return 'השיחה הסתיימה. כדי להתחיל שיחה חדשה, פשוט שלח/י לנו הודעה.';
}

/**
 * Idle-timeout sweep: scan every open POAgent WhatsApp session (not just one
 * user's) and act on how long it's been quiet. There is no code path that
 * runs on its own without an inbound webhook hit, so something external has
 * to call this on a timer - see session_sweeper.php for the dev/test runner.
 * Logic lives here, independent of *how* it gets triggered, so the runner
 * can be swapped later (cron/systemd once this moves off Windows) without
 * touching this function.
 *
 * @return array{reminders_sent:int, sessions_closed:int}
 */
function poagent_whatsapp_sweep_idle_sessions(): array
{
    $remindersSent  = 0;
    $sessionsClosed = 0;

    foreach (WaSessionStore::allForApp(POAGENT_WA_APP_ID) as $session) {
        $waId           = (string) ($session['wa_id'] ?? '');
        $data           = $session['data'] ?? [];
        $phoneNumberId  = (string) ($data['business_phone_number_id'] ?? '');
        $lastInboundAt  = (string) ($data['last_inbound_at'] ?? $session['updated_at'] ?? '');
        $reminderSentAt = $data['reminder_sent_at'] ?? null;

        if ($waId === '' || $lastInboundAt === '') {
            continue; // malformed/pre-existing session, nothing to time against
        }

        if ($reminderSentAt) {
            $idleSinceReminder = time() - strtotime((string) $reminderSentAt);
            if ($idleSinceReminder >= POAGENT_WA_IDLE_CLOSE_AFTER_SECONDS) {
                $sent = WaClient::text($phoneNumberId, $waId, poagent_whatsapp_idle_closed_text());
                WaSessionStore::clear($waId);
                $sessionsClosed++;
                poagent_whatsapp_log([
                    'ts' => date('c'), 'event' => 'session_closed_idle', 'from' => $waId, 'reply_sent' => $sent,
                ]);
            }
            continue;
        }

        $idleSinceInbound = time() - strtotime($lastInboundAt);
        if ($idleSinceInbound >= POAGENT_WA_IDLE_REMINDER_AFTER_SECONDS) {
            $sent = WaClient::text($phoneNumberId, $waId, poagent_whatsapp_idle_reminder_text());
            WaSessionStore::patchData($waId, ['reminder_sent_at' => date('c')]);
            $remindersSent++;
            poagent_whatsapp_log([
                'ts' => date('c'), 'event' => 'idle_reminder_sent', 'from' => $waId, 'reply_sent' => $sent,
            ]);
        }
    }

    return ['reminders_sent' => $remindersSent, 'sessions_closed' => $sessionsClosed];
}

/** One JSON object per line, one file per day. */
function poagent_whatsapp_log(array $entry): void
{
    if (!is_dir(POAGENT_WA_LOG_DIR)) {
        @mkdir(POAGENT_WA_LOG_DIR, 0775, true);
    }
    @file_put_contents(
        POAGENT_WA_LOG_DIR . '/bot_' . date('Y-m-d') . '.log',
        json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
        FILE_APPEND | LOCK_EX
    );
}
