<?php
// ui_common.php — shared HTML shell + session helpers for POAgent screens.
// RTL Hebrew, card-style layout matching the existing convention elsewhere
// in this codebase (see NPharmonized/index.php).

// Fixed user list for Screen 1 (spec §4.1) — becomes `generator_id` for the session.
define('POAGENT_USERS', ['user1', 'user2', 'user3']);

function poagent_render_head(string $title, int $cardWidth = 690): void
{
    ?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title) ?></title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; display: flex; justify-content: center; align-items: flex-start; min-height: 100vh; margin: 0; padding: 40px 0; }
        .card { background: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,.15); padding: 50px 65px; width: <?= (int) $cardWidth ?>px; max-width: 92vw; box-sizing: border-box; }
        h2 { margin: 0 0 32px; color: #333; text-align: center; font-size: 32px; }
        label { display: block; margin-bottom: 9px; font-weight: bold; color: #555; font-size: 19px; }
        select, input[type="text"], input[type="number"] {
            width: 100%; padding: 13px; border: 1px solid #ccc; border-radius: 8px;
            font-size: 19px; background: #fafafa; margin-bottom: 22px; box-sizing: border-box;
        }
        button, .btn {
            display: block; width: 100%; padding: 16px; background: #2575a8; color: #fff; border: none;
            border-radius: 8px; font-size: 21px; cursor: pointer; margin-top: 6px; text-align: center;
            text-decoration: none; box-sizing: border-box;
        }
        button:hover, .btn:hover { background: #1a5c87; }
        .btn.secondary { background: #888; }
        .btn.secondary:hover { background: #666; }
        .btn.disabled { background: #ddd; cursor: not-allowed; color: #888; }
        .btn.disabled:hover { background: #ddd; }
        .switch-group { display: flex; gap: 15px; margin-bottom: 26px; }
        .switch-opt { flex: 1; }
        .switch-opt input[type="radio"] { display: none; }
        .switch-opt label { display: block; margin: 0; padding: 14px; text-align: center; border: 2px solid #ccc;
            border-radius: 8px; background: #fafafa; font-size: 18px; font-weight: bold; color: #555; cursor: pointer; }
        .switch-opt input[type="radio"]:checked + label { border-color: #2575a8; background: #e8f1f8; color: #2575a8; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: 17px; }
        th, td { border: 1px solid #ddd; padding: 9px 10px; text-align: right; }
        th { background: #f0f4f8; }
        input[type="number"].qty { width: 80px; margin: 0; padding: 8px; text-align: center; }
        .muted { color: #888; font-size: 15px; }
        .row-gap { margin-top: 14px; }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 14px; font-weight: bold; }
        .badge.open { background: #fff3cd; color: #856404; }
        .badge.prcv { background: #d1ecf1; color: #0c5460; }
        .badge.closed { background: #d4edda; color: #155724; }
        .badge.cancelled { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
<div class="card">
    <?php
}

function poagent_render_foot(): void
{
    ?>
</div>
</body>
</html>
    <?php
}

/** Redirects to Screen 1 if no user has been selected yet; otherwise returns generator_id. */
function poagent_require_generator(): string
{
    if (empty($_SESSION['poagent_generator_id'])) {
        header('Location: index.php');
        exit;
    }
    return $_SESSION['poagent_generator_id'];
}
