<?php
// webhook.php — Meta WhatsApp webhook receiver

$VERIFY_TOKEN = "my_secret_verify_123"; // must match exactly what you enter in Meta's dashboard

$IMAGES_DIR = __DIR__ . '/whatsapp_images';

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
// webhook.php only records the raw payload here. MessageFetching.php reads
// this log, assigns each message object a serial number, and downloads any
// image attachments — see MessageFetching.php for that logic.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = file_get_contents('php://input');
    file_put_contents($IMAGES_DIR . '/webhook_log.txt', date('Y-m-d H:i:s') . " - " . $body . "\n\n", FILE_APPEND);

    http_response_code(200);
    echo "OK";
    exit;
}

http_response_code(405);
echo "Method not allowed";