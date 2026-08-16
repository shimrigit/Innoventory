<?php
// Screen 1 — user select (spec §4.1). Choice becomes `generator_id` for the session.
session_start();
require_once __DIR__ . '/lib/ui_common.php';

poagent_render_head('POAgent – בחירת משתמש');
?>
<h2>הזמנות רכש ותעודות משלוח</h2>
<form action="select_user.php" method="post">
    <label>בחר משתמש</label>
    <div class="switch-group">
        <?php foreach (POAGENT_USERS as $i => $u): ?>
        <div class="switch-opt">
            <input type="radio" id="u<?= $i ?>" name="generator_id" value="<?= htmlspecialchars($u) ?>" <?= $i === 0 ? 'checked' : '' ?>>
            <label for="u<?= $i ?>"><?= htmlspecialchars($u) ?></label>
        </div>
        <?php endforeach; ?>
    </div>
    <button type="submit">המשך</button>
</form>
<?php poagent_render_foot(); ?>
