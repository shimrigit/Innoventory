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
 * What it does for now — and ONLY this: reply to any actionable user message
 * with the two-option menu, and append the exchange to
 * POAgent/whatsapp/logs/. No branching on the user's A/B answer yet; that
 * needs the conversation-state work noted at the bottom.
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

    $reply         = poagent_whatsapp_menu_text();
    $phoneNumberId = (string) ($event['business_phone_number_id'] ?? '');
    $sent          = WaClient::text($phoneNumberId, $from, $reply);

    // Record where this user now is, so a later slice can read their next
    // message as the A/B answer. Nothing consumes this yet.
    WaSessionStore::set($from, POAGENT_WA_APP_ID, 'awaiting_menu_choice', [
        'menu_sent_at' => date('c'),
        'last_msg_id'  => $event['message_id'] ?? '',
    ]);

    poagent_whatsapp_log([
        'ts'          => date('c'),
        'direction'   => 'menu_reply',
        'from'        => $from,
        'contact'     => $event['contact_name'] ?? '',
        'wa_id'       => $event['message_id'] ?? '',
        'in_type'     => $type,
        'in_text'     => (string) ($event['text'] ?? ''),
        'reply_sent'  => $sent,
        'reply_text'  => $reply,
    ]);
}

/** The A/B menu, Hebrew RTL (matches the rest of POAgent's UI language). */
function poagent_whatsapp_menu_text(): string
{
    return implode("\n", [
        'שלום 👋 הגעת ל-POAgent, מערכת ההזמנות ותעודות המשלוח.',
        '',
        'מה תרצה לעשות?',
        '',
        'A – הקם הזמנה חדשה',
        'B – סריקת תעודת משלוח',
        '',
        'השב/י באות A או B.',
    ]);
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
