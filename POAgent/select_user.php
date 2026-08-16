<?php
// Handles Screen 1 submission — validates against the fixed user list and stores generator_id in session.
session_start();
require_once __DIR__ . '/lib/ui_common.php';

$generatorId = $_POST['generator_id'] ?? '';
if (!in_array($generatorId, POAGENT_USERS, true)) {
    header('Location: index.php');
    exit;
}

$_SESSION['poagent_generator_id'] = $generatorId;
header('Location: main_menu.php');
exit;
