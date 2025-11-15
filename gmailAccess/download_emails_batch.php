<?php
/**
 * Batch Email Downloader for inno.ocr@gmail.com
 * Downloads emails that have BOTH PDF and JSON attachments
 * Processes in small batches for safety
 */

require '../vendor/autoload.php';

use Google\Client;
use Google\Service\Gmail;

// ============================================================================
// CONFIGURATION - EDIT THESE SETTINGS
// ============================================================================

$BATCH_SIZE = 100;  // Number of emails to process per run
$OUTPUT_DIR = 'C:\Users\Shimri-SAS\Dropbox (Personal)\PC\Documents\LS Consulting\Business\Retailomatics\inno-ocr-invoices';
$PROGRESS_FILE = 'download_progress_jpg.json';  // Tracks which emails were processed (separate file for JPG downloads)

// File type configuration
$FILE_TYPE_1 = 'jpg';  // First required file type (case-insensitive)
$FILE_TYPE_2 = 'json'; // Second required file type (case-insensitive)

// ============================================================================
// SETUP
// ============================================================================

// Create output directories
$attachmentsDir = $OUTPUT_DIR . '/attachments';
$metadataDir = $OUTPUT_DIR . '/metadata';

if (!is_dir($attachmentsDir)) {
    mkdir($attachmentsDir, 0777, true);
    echo "Created directory: $attachmentsDir\n";
}

if (!is_dir($metadataDir)) {
    mkdir($metadataDir, 0777, true);
    echo "Created directory: $metadataDir\n";
}

// Load progress tracking
$processedIds = [];
if (file_exists($PROGRESS_FILE)) {
    $progress = json_decode(file_get_contents($PROGRESS_FILE), true);
    $processedIds = $progress['processed_ids'] ?? [];
    echo "Loaded progress: " . count($processedIds) . " emails already processed\n";
} else {
    $progress = [
        'processed_ids' => [],
        'total_downloaded' => 0,
        'total_skipped' => 0,
        'last_run' => null
    ];
}

// ============================================================================
// GMAIL AUTHENTICATION
// ============================================================================

$client = new Google_Client();
$client->setApplicationName('Gmail Batch Downloader');
$client->setAuthConfig(__DIR__ . '/oauth_credentials.json');
$client->setAccessType('offline');
$client->setScopes([Gmail::GMAIL_READONLY]);

// Load token
if (file_exists(__DIR__ . '/token.json')) {
    $accessToken = json_decode(file_get_contents(__DIR__ . '/token.json'), true);
    $client->setAccessToken($accessToken);

    // Refresh if expired
    if ($client->isAccessTokenExpired()) {
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            file_put_contents(__DIR__ . '/token.json', json_encode($client->getAccessToken()));
        } else {
            die("ERROR: Token expired. Please re-authenticate via oauth2callback.php\n");
        }
    }
} else {
    die("ERROR: token.json not found. Please authenticate via oauth2callback.php\n");
}

$service = new Gmail($client);

// Check authenticated account
$profile = $service->users->getProfile('me');
echo "\nAuthenticated as: " . $profile->getEmailAddress() . "\n\n";

// ============================================================================
// DOWNLOAD EMAILS
// ============================================================================

echo "======================================================================\n";
echo " BATCH EMAIL DOWNLOADER\n";
echo " Processing up to $BATCH_SIZE emails this run\n";
echo "======================================================================\n\n";

// Get ALL messages with attachments (with pagination)
echo "Fetching message list from Gmail...\n";
$messages = [];
$pageToken = null;

do {
    $optParams = [
        'q' => 'has:attachment',
        'maxResults' => 500
    ];

    if ($pageToken) {
        $optParams['pageToken'] = $pageToken;
    }

    $results = $service->users_messages->listUsersMessages('me', $optParams);

    if ($results->getMessages()) {
        $messages = array_merge($messages, $results->getMessages());
        echo "  Fetched " . count($results->getMessages()) . " messages (total so far: " . count($messages) . ")\n";
    }

    $pageToken = $results->getNextPageToken();

} while ($pageToken);

if (empty($messages)) {
    echo "No messages with attachments found.\n";
    exit;
}

echo "\nFound " . count($messages) . " total messages with attachments\n";
echo "Already processed: " . count($processedIds) . " messages\n";
echo "Remaining: " . (count($messages) - count($processedIds)) . " messages\n";
echo "Starting batch processing (up to $BATCH_SIZE this run)...\n\n";

$processedThisRun = 0;
$downloadedThisRun = 0;
$skippedThisRun = 0;

foreach ($messages as $messageInfo) {
    $messageId = $messageInfo->getId();

    // Skip if already processed
    if (in_array($messageId, $processedIds)) {
        continue;
    }

    // Check batch limit
    if ($processedThisRun >= $BATCH_SIZE) {
        echo "\n✓ Batch limit reached ($BATCH_SIZE emails)\n";
        break;
    }

    $processedThisRun++;

    // Get full message
    $message = $service->users_messages->get('me', $messageId, ['format' => 'full']);

    echo "[$processedThisRun] Processing message ID: $messageId\n";

    // Find attachments
    $parts = $message->getPayload()->getParts() ?: [];

    $file1Attachment = null;
    $file2Attachment = null;
    $file1Filename = null;
    $file2Filename = null;

    foreach ($parts as $part) {
        $filename = $part->getFilename();
        if ($filename) {
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if ($ext === strtolower($FILE_TYPE_1) && !$file1Attachment) {
                $file1Attachment = $part->getBody()->getAttachmentId();
                $file1Filename = $filename;
            } elseif ($ext === strtolower($FILE_TYPE_2) && !$file2Attachment) {
                $file2Attachment = $part->getBody()->getAttachmentId();
                $file2Filename = $filename;
            }
        }
    }

    // Check if both required files exist
    if (!$file1Attachment || !$file2Attachment) {
        echo "  ✗ SKIP: Missing " . strtoupper($FILE_TYPE_1) . " or " . strtoupper($FILE_TYPE_2) .
             " (" . strtoupper($FILE_TYPE_1) . ": " . ($file1Attachment ? 'Yes' : 'No') .
             ", " . strtoupper($FILE_TYPE_2) . ": " . ($file2Attachment ? 'Yes' : 'No') . ")\n\n";
        $skippedThisRun++;
        $progress['total_skipped']++;
        $processedIds[] = $messageId;
        continue;
    }

    // Download first file type
    $attachment = $service->users_messages_attachments->get('me', $messageId, $file1Attachment);
    $file1Data = base64_decode(str_replace(['-', '_'], ['+', '/'], $attachment->getData()));
    file_put_contents($attachmentsDir . '/' . $file1Filename, $file1Data);

    // Download second file type
    $attachment = $service->users_messages_attachments->get('me', $messageId, $file2Attachment);
    $file2Data = base64_decode(str_replace(['-', '_'], ['+', '/'], $attachment->getData()));
    file_put_contents($attachmentsDir . '/' . $file2Filename, $file2Data);

    // Get email metadata
    $headers = $message->getPayload()->getHeaders();
    $subject = '';
    $from = '';
    $date = '';

    foreach ($headers as $header) {
        if ($header->getName() === 'Subject') {
            $subject = $header->getValue();
        } elseif ($header->getName() === 'From') {
            $from = $header->getValue();
        } elseif ($header->getName() === 'Date') {
            $date = $header->getValue();
        }
    }

    // Save metadata
    $metadata = [
        'message_id' => $messageId,
        'subject' => $subject,
        'from' => $from,
        'date' => $date,
        'file1_type' => strtoupper($FILE_TYPE_1),
        'file1_name' => $file1Filename,
        'file2_type' => strtoupper($FILE_TYPE_2),
        'file2_name' => $file2Filename,
        'downloaded_at' => date('Y-m-d H:i:s')
    ];

    file_put_contents($metadataDir . '/' . $messageId . '_metadata.json', json_encode($metadata, JSON_PRETTY_PRINT));

    echo "  ✓ DOWNLOADED: $file1Filename + $file2Filename\n\n";

    $downloadedThisRun++;
    $progress['total_downloaded']++;
    $processedIds[] = $messageId;
}

// Save progress
$progress['processed_ids'] = $processedIds;
$progress['last_run'] = date('Y-m-d H:i:s');
file_put_contents($PROGRESS_FILE, json_encode($progress, JSON_PRETTY_PRINT));

// Summary
echo "======================================================================\n";
echo " BATCH COMPLETE\n";
echo "======================================================================\n";
echo "This run:\n";
echo "  Processed: $processedThisRun\n";
echo "  Downloaded: $downloadedThisRun\n";
echo "  Skipped: $skippedThisRun\n\n";

echo "Total (all runs):\n";
echo "  Downloaded: " . $progress['total_downloaded'] . "\n";
echo "  Skipped: " . $progress['total_skipped'] . "\n";
echo "  Total processed: " . count($processedIds) . "\n";
echo "  Last run: " . $progress['last_run'] . "\n";
echo "======================================================================\n";

$remaining = count($messages) - count($processedIds);
if ($remaining > 0) {
    echo "\n💡 Run this script again to process the next batch ($remaining emails remaining)\n";
} else {
    echo "\n✅ All emails have been processed!\n";
}
