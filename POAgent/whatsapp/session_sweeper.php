<?php
/**
 * POAgent — idle-session sweeper (manual/dev runner).
 *
 * Run in its own terminal window while testing:
 *   php POAgent/whatsapp/session_sweeper.php
 * Stop with Ctrl+C.
 *
 * Deliberately NOT wired to a Windows-specific scheduler (Task Scheduler)
 * for now: this app is expected to move to a non-Windows server later, and
 * the timing logic doesn't need to know or care what wakes it up. This file
 * is just a runner - all the actual logic is
 * poagent_whatsapp_sweep_idle_sessions() in bot.php. Swapping this loop for
 * cron/systemd/a proper daemon later is a one-file change.
 *
 * Fine for single/low-volume test sessions (per explicit request - this is
 * not meant to survive as the production mechanism).
 */

require_once __DIR__ . '/bot.php';

$intervalSeconds = 15; // how often to re-check every open session

echo "[POAgent idle sweeper] started, checking every {$intervalSeconds}s. Ctrl+C to stop.\n";

while (true) {
    $result = poagent_whatsapp_sweep_idle_sessions();
    if ($result['reminders_sent'] > 0 || $result['sessions_closed'] > 0) {
        echo sprintf(
            "[%s] reminders_sent=%d sessions_closed=%d\n",
            date('H:i:s'),
            $result['reminders_sent'],
            $result['sessions_closed']
        );
    }
    sleep($intervalSeconds);
}
