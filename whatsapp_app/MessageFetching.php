<?php
// MessageFetching.php — subprocess of the NP (WhatsApp) process.
//
// Parses whatsapp_images/webhook_log.txt (where webhook.php logs every raw
// Meta webhook POST body), flattens every message object found across all
// logged payloads into one ordered list, assigns each a serial number in
// arrival order (1, 2, 3, ...), and downloads any image attachments while
// the Meta-hosted media URL is still valid (Meta only keeps it live for a
// few minutes).
//
// Image files are saved to whatsapp_images/ as:
//   ddmmyy-hhmmss X Y.ext
//     ddmmyy-hhmmss = the message's timestamp (UTC)
//     X             = serial number of the message among all logged objects
//     Y             = the image's caption, sanitized for the filesystem
//   e.g. "050826-080931 2 13.9.jpeg"
//
// messages.jsonl is no longer produced — webhook_log.txt is the sole record.
//
// Run manually: php MessageFetching.php   (or open in a browser)

$IMAGES_DIR  = __DIR__ . '/whatsapp_images';
$LOG_FILE    = $IMAGES_DIR . '/webhook_log.txt';
$CONFIG_FILE = __DIR__ . '/config.json';

// When run in a browser, PHP's output is treated as HTML: a bare "\n" in an
// echo doesn't start a new line and the default font isn't enlarged. Wrap the
// report in a <pre> block (one real line per echoed line) at double size.
// Plain CLI runs (php MessageFetching.php) are left as ordinary text.
$isWeb = PHP_SAPI !== 'cli';
if ($isWeb) {
    echo "<!DOCTYPE html><html><head><meta charset=\"UTF-8\"><title>MessageFetching</title></head><body>";
    echo "<pre style=\"font-size:2em; line-height:1.4;\">";
}

if (!is_file($LOG_FILE)) {
    if ($isWeb) {
        echo "No webhook_log.txt found at {$LOG_FILE}\n</pre></body></html>";
    } else {
        fwrite(STDERR, "No webhook_log.txt found at {$LOG_FILE}\n");
    }
    exit(1);
}

$config      = is_file($CONFIG_FILE) ? json_decode(file_get_contents($CONFIG_FILE), true) : [];
$accessToken = $config['meta_key'] ?? '';

$objects = extractObjects($LOG_FILE);

$serial     = 0;
$downloaded = 0;
$skipped    = 0;
$failed     = 0;

foreach ($objects as $message) {
    $serial++;

    if (($message['type'] ?? '') !== 'image' || empty($message['image'])) {
        continue;
    }

    $filename = buildImageFilename($message, $serial);
    $destPath = $IMAGES_DIR . '/' . $filename;

    if (is_file($destPath)) {
        $skipped++;
        continue;
    }

    if (downloadImage($message['image'], $accessToken, $destPath)) {
        $downloaded++;
        echo "Saved #{$serial}: {$filename}\n";
    } else {
        $failed++;
        echo "FAILED #{$serial}: could not download image (id=" . ($message['image']['id'] ?? '?') . ")\n";
    }
}

echo "Done. " . count($objects) . " message object(s) in log, {$downloaded} image(s) downloaded, "
    . "{$skipped} already present, {$failed} failed.\n";

if ($isWeb) {
    echo "</pre></body></html>";
}

/**
 * Read webhook_log.txt and return a flat, arrival-ordered list of every
 * message object found across all logged webhook payloads.
 */
function extractObjects(string $logFile): array {
    $messages = [];

    $lines = file($logFile, FILE_IGNORE_NEW_LINES) ?: [];
    foreach ($lines as $line) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} - (\{.*\})\s*$/', $line, $m)) {
            continue; // blank separator line or unrecognized content
        }

        $payload = json_decode($m[1], true);
        if (!is_array($payload)) {
            continue;
        }

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                foreach ($change['value']['messages'] ?? [] as $message) {
                    $messages[] = $message;
                }
            }
        }
    }

    return $messages;
}

function buildImageFilename(array $message, int $serial): string {
    $timestamp = (int)($message['timestamp'] ?? time());
    $stamp     = gmdate('dmy-His', $timestamp);

    $caption = sanitizeForFilename($message['image']['caption'] ?? '');

    $ext = strtolower(explode('/', $message['image']['mime_type'] ?? 'image/jpeg')[1] ?? 'jpeg');
    $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?: 'jpeg';

    $name = trim("{$stamp} {$serial} {$caption}");
    return $name . '.' . $ext;
}

function sanitizeForFilename(string $text): string {
    $text = preg_replace('/[\\\\\/:*?"<>|]/', '_', $text);
    $text = trim($text);
    return mb_substr($text, 0, 120);
}

function downloadImage(array $image, string $accessToken, string $destPath): bool {
    $url = $image['url'] ?? null;
    if (!$url && !empty($image['id'])) {
        $url = fetchMediaUrl($image['id'], $accessToken);
    }
    if (!$url) {
        return false;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$accessToken}"],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $data   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($data === false || $status !== 200) {
        return false;
    }

    file_put_contents($destPath, $data);
    return true;
}

function fetchMediaUrl(string $mediaId, string $accessToken): ?string {
    $ch = curl_init("https://graph.facebook.com/v21.0/{$mediaId}");
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$accessToken}"],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($response, true);
    return $json['url'] ?? null;
}
