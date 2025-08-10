<?php
require_once '../vendor/autoload.php'; // Load Google API client and other dependencies

use Google\Client;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use Google\Service\Gmail\Draft;

session_start(); // Continue the session if needed

// Email account configuration
//$fromEmail = 'inno.ocr@gmail.com'; // Gmail account to send from
//$destinationEmail = 'innoventory.tech@soluma.si'; // The recipient

$fromEmail = $_SESSION['preProcessFROMemailAddress'];
$destinationEmail = $_SESSION['preProcessDestinationEmailAddress'];
$toSend = $_SESSION['preProcessToSend'];


$directory = $_SESSION['preProcessDirectory'];
$logFile = $_SESSION['logFile'];


$pdfFiles = glob($directory . '/*.pdf'); // Get all PDF files

// Manually set the toSend parameter (0 = no email, 1 = draft email, 2 = send email)
//$toSend = 1; // Change this manually to 0, 1, or 2 based on the action

// Gmail API client setup
function getClient() {
    $client = new Client();
    $client->setApplicationName('Gmail API PHP Send');
    $client->setScopes(Gmail::GMAIL_SEND);
    $client->setAuthConfig(__DIR__ . '/oauth_credentials.json'); // Path to your OAuth credentials JSON file
    $client->setAccessType('offline');
    
    // Load previously authorized token from a file
    if (file_exists('token.json')) {
        $accessToken = json_decode(file_get_contents('token.json'), true);
        $client->setAccessToken($accessToken);
    }
    
    // Refresh the token if it's expired
    if ($client->isAccessTokenExpired()) {
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            file_put_contents('token.json', json_encode($client->getAccessToken()));
        }
    }

    return $client;
}

$client = getClient();
$service = new Gmail($client);

// Function to send an email or save it as a draft
function sendOrDraftEmail($service, $fromEmail, $toEmail, $subject, $body, $attachmentFile, $toSend,$logFile) {
    $message = new Message(); // Use the correct namespace

    // Build email headers and message
    $boundary = uniqid(rand(), true);
    $rawMessageString = "From: $fromEmail\r\n";
    $rawMessageString .= "To: $toEmail\r\n";
    $rawMessageString .= "Subject: $subject\r\n";
    $rawMessageString .= "MIME-Version: 1.0\r\n";
    $rawMessageString .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n\r\n";
    $rawMessageString .= "--$boundary\r\n";
    $rawMessageString .= "Content-Type: text/plain; charset=\"UTF-8\"\r\n\r\n";
    $rawMessageString .= "$body\r\n";
    $rawMessageString .= "--$boundary\r\n";

    // Attach the PDF file
    $fileData = base64_encode(file_get_contents($attachmentFile));
    $rawMessageString .= "Content-Type: application/pdf; name=\"" . basename($attachmentFile) . "\"\r\n";
    $rawMessageString .= "Content-Disposition: attachment; filename=\"" . basename($attachmentFile) . "\"\r\n";
    $rawMessageString .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $rawMessageString .= chunk_split($fileData) . "\r\n";
    $rawMessageString .= "--$boundary--";

    $rawMessage = strtr(base64_encode($rawMessageString), array('+' => '-', '/' => '_', '=' => ''));
    $message->setRaw($rawMessage);

    // Handle based on the toSend value
    if ($toSend == 1) {
        // Draft the email
        $draft = new Draft();
        $draft->setMessage($message);
        $service->users_drafts->create('me', $draft);
        $finalMessage = "Draft created for: $subject ";
        echo $finalMessage."<br>";
        file_put_contents($logFile, $finalMessage . date('Y-m-d H:i:s') . PHP_EOL, FILE_APPEND);
    } elseif ($toSend == 2) {
        // Send the email
        $service->users_messages->send('me', $message);
        echo "Email sent for: $subject<br>";
    } else {
        echo "Email skipped for: $subject<br>";
    }
}

// Process each PDF file and act according to toSend
foreach ($pdfFiles as $pdfFile) {
    $fileName = basename($pdfFile, '.pdf'); // Get the file name without extension
    $subject = $fileName; // Use the file name as the email subject
    $body = "Please find attached the document $fileName.";

    // Call the function to send or draft email
    sendOrDraftEmail($service, $fromEmail, $destinationEmail, $subject, $body, $pdfFile, $toSend,$logFile);
}

session_destroy();

echo "Process completed.";
?>
