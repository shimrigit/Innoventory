<?php
// flow_mode.php — shared helpers for NP Harmonized "Flow" vs "Steps" mode.
//
// Flow (default): the process auto-advances from index.php's "המשך לאישור"
// click through every stage, without the user clicking each stage's own
// אישור button — EXCEPT the final review screen (stage_dcl.php), which
// always requires the manual "אישור – שמור וסיים תהליך" click.
//
// Steps (fallback): identical to the original click-through behavior — no
// auto-advance anywhere. Use if Flow mode misbehaves.
//
// Auto-advance only ever fires when the stage's own success condition is
// already met (the same condition that would otherwise leave its button
// enabled) — it never bypasses real validation, it just skips the click.

function npFlowMode(): string {
    $mode = $_POST['mode'] ?? $_GET['mode'] ?? 'flow';
    return $mode === 'steps' ? 'steps' : 'flow';
}

// Renders a small banner + a delayed auto-submit of the given form id.
// $delaySeconds mirrors the cross-stage filesystem settle time used by
// xlsx_retry.php's retry loader (3s), so the receiving stage's file is very
// likely fully synced by the time this fires.
function renderFlowAutoAdvance(string $mode, string $formId, int $delaySeconds = 3): void {
    if ($mode !== 'flow') return;
    ?>
    <div id="flowBanner" style="background:#e8f1f8;border:1px solid #2575a8;border-radius:8px;padding:14px 22px;margin:18px 0;font-size:19px;color:#2575a8;font-weight:bold;max-width:1100px;">
        🔄 מצב Flow – ממשיך אוטומטית לשלב הבא בעוד <span id="flowCountdown"><?= $delaySeconds ?></span> שניות...
        <span style="font-weight:normal;font-size:16px;color:#555">(ניתן ללחוץ על הכפתור כדי להמשיך מיד)</span>
    </div>
    <script>
    (function () {
        var seconds = <?= (int)$delaySeconds ?>;
        var el = document.getElementById('flowCountdown');
        var timer = setInterval(function () {
            seconds--;
            if (el) el.textContent = Math.max(seconds, 0);
            if (seconds <= 0) {
                clearInterval(timer);
                var f = document.getElementById(<?= json_encode($formId) ?>);
                if (f) f.submit();
            }
        }, 1000);
    })();
    </script>
    <?php
}

// A short "manual step required" note for stage_dcl.php, shown only in Flow
// mode so the user understands why the auto-advance stopped here.
function renderFlowStopNotice(string $mode): void {
    if ($mode !== 'flow') return;
    ?>
    <div style="background:#fff8e1;border:1px solid #ffe082;border-radius:8px;padding:14px 22px;margin-bottom:18px;font-size:19px;color:#7a5800;font-weight:bold;max-width:1100px;">
        🔄 מצב Flow – זהו שלב האימות הסופי. התהליך ימתין כאן; יש לבדוק ולערוך במידת הצורך, ואז ללחוץ "אישור – שמור וסיים תהליך".
    </div>
    <?php
}
