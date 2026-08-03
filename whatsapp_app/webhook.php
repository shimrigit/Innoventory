<?php
// webhook.php — Meta WhatsApp webhook receiver

$VERIFY_TOKEN = "my_secret_verify_123"; // must match exactly what you enter in Meta's dashboard

// ── GET: Meta's verification handshake ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode      = $_GET['hub_mode']         ?? '';
    $token     = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge']    ?? '';

    if ($mode === 'subscribe' && $token === $VERIFY_TOKEN) {
        http_response_code(200);
        echo $challenge;
    } else {
        http_response_code(403);
        echo "Verification failed";
    }
    exit;
}

// ── POST: incoming message events ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = file_get_contents('php://input');
    $logLine = date('Y-m-d H:i:s') . " - " . $body . "\n\n";
    file_put_contents(__DIR__ . '/whatsapp_images/webhook_log.txt', $logLine, FILE_APPEND);
    http_response_code(200);
    echo "OK";
    exit;
}

http_response_code(405);
echo "Method not allowed";
