<?php
require '../vendor/autoload.php';
include '../functions/configFunctions.php';

use Google\Client;
use Google\Service\Gmail;
use Google\Service\Gmail\MessagePartBody;
use Google\Service\Gmail\ModifyMessageRequest;

session_start();

// Set the session parameters
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Access the shop and supplier name 
    $shop = isset($_POST['shop']) ? $_POST['shop'] : null;
    $supplier = isset($_POST['supplier']) ? $_POST['supplier'] : null;

    // Store the $shop and $supplier data in the session 
    $_SESSION['shop'] = $shop;
    $_SESSION['supplier'] = $supplier;
} else {
    // If the initiation was not a POST message
    $shop = "NoShop";
    $supplier = "General";
}

// Initialize Google Client
$client = new Google_Client();
$client->setApplicationName('Gmail API PHP Integration');
$client->setAuthConfig('oauth_credentials.json');
$client->setAccessType('offline');
$client->setScopes([
    Gmail::GMAIL_MODIFY, // Full access to the mail service
]);


if (file_exists('token.json')) {
    $accessToken = json_decode(file_get_contents('token.json'), true);
    $client->setAccessToken($accessToken);

    // Check if token is expired and refresh it
    if ($client->isAccessTokenExpired()) {
        if ($client->getRefreshToken()) {
            $refreshedAccessToken = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            file_put_contents('token.json', json_encode($client->getAccessToken()));
            $client->setAccessToken($refreshedAccessToken);
        } else {
            // Refresh token is not available
            $authUrl = $client->createAuthUrl();
            $authUrl .= '&state=' . urlencode('source=download_ocrfiles_from_emails'); // Specify feature2 for this process
            header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
            exit;
        }
    }
} else {
    // token.json does not exist, start the OAuth flow
    $authUrl = $client->createAuthUrl();
    $authUrl .= '&state=' . urlencode('source=download_ocrfiles_from_emails'); // Specify feature2 for this process
    header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
    exit;
}

// Get the parameters: email source, inbox source, label ID, JSON+pdf destination directory, files extention
$shopsjsonData = uploadJSONdata('../shops.json');
$shopData = getShopForm($shopsjsonData, $shop);
$targetLabelId = $shopData["destinationLabelID"];
$landingEmailDirectory = $shopData["landingEmailDirectory"];
$emailSourceAddress = $shopData["emailSourceAddress"];
$ocrFilesDestinationDirectory = $shopData["ocrFilesDestinationDirectory"];
$OCRPicFileExtention = $shopData["OCRPicFileExtention"];

// Initialize Gmail service
$service = new Gmail($client);

echo "Start to get email from the inbox <br>";

// Get emails from the inbox
try {
    $results = $service->users_messages->listUsersMessages('me', [
        'q' => 'from:' . $emailSourceAddress . ' has:attachment in:inbox'
    ]);

    $messages = $results->getMessages();
    if (empty($messages)) {
        echo 'No messages found.';
    } else {
        echo "Start to get the messages <br>";
        foreach ($messages as $message) {
            $msg = $service->users_messages->get('me', $message->getId(), ['format' => 'full']);
            echo "<p>Processing Message ID: {$message->getId()}</p>";
            $parts = ($msg->getPayload()->getParts()) ? $msg->getPayload()->getParts() : [];
            $attachmentIds = [];
            $foundPdf = false;
            $foundJson = false;

            foreach ($parts as $part) {
                $filename = $part->getFilename();
                if ($filename) {
                    echo "<p>Found file: {$filename}</p>";
                    $fileExtension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                    if ($fileExtension === $OCRPicFileExtention && !$foundPdf) {
                        $foundPdf = true;
                        $attachmentIds['pdf'] = $part->getBody()->getAttachmentId();
                    } elseif ($fileExtension === 'json' && !$foundJson) {
                        $foundJson = true;
                        $attachmentIds['json'] = $part->getBody()->getAttachmentId();
                    }
                }
            }

            echo "$OCRPicFileExtention found: " . ($foundPdf ? 'Yes' : 'No') . "<br>";
            echo "JSON found: " . ($foundJson ? 'Yes' : 'No') . "<br>";

            // If both PDF and JSON attachments are found
            if ($foundPdf && $foundJson) {
                echo "Has $OCRPicFileExtention and JSON attachments <br>";
                foreach ($attachmentIds as $type => $attachmentId) {
                    $attachment = $service->users_messages_attachments->get('me', $message->getId(), $attachmentId);
                    $data = base64_decode(str_replace(array('-', '_'), array('+', '/'), $attachment->getData()));
                    $baseFilename = pathinfo($filename, PATHINFO_FILENAME);
                    //distingush between JSON and pdf
                    if ($type == 'pdf') {
                        file_put_contents($ocrFilesDestinationDirectory . "/{$baseFilename}.pdf", $data);
                    } elseif ($type == 'json') {
                        file_put_contents($ocrFilesDestinationDirectory . "/{$baseFilename}.JSON", $data); 
                    } else {
                        die("Tried to match pair which are not .$OCRPicFileExtention and .JSON. Process terminated");
                    }
                    
                }

                // Move the email from Inbox to the designated directory
                $modifyRequest = new ModifyMessageRequest();
                $modifyRequest->setRemoveLabelIds([$landingEmailDirectory]);  // Remove from Inbox
                $modifyRequest->setAddLabelIds([$targetLabelId]);  // Add to new label

                try {
                    $service->users_messages->modify('me', $message->getId(), $modifyRequest);
                    echo "Message moved successfully to designated folder.";
                } catch (Exception $e) {
                    echo 'An error occurred: ' . $e->getMessage();
                }
            }
        }
    }
} catch (Exception $e) {
    echo 'An error occurred: ' . $e->getMessage();
}
?>
