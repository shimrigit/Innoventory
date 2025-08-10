<?php

require '../vendor/autoload.php';

use Google\Client;
use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use Google\Service\Gmail\Draft;

// Function to add a draft email with processed  files or send an email
function addDraftEmailWithProcessedFiles($pathTofile_invoice_list, $pathTofile_new_products,$pathTofile_price_change, $emailBodyText, $destinationEmailAddress, $emailSubject, int $toSend)
{
    // Initialize the Google Client
    $client = new Google_Client();
    $client->setApplicationName('Gmail API PHP Quickstart');
    $client->setAuthConfig(__DIR__.'/oauth_credentials.json');
    $client->setAccessType('offline');
    $client->setScopes(Gmail::GMAIL_COMPOSE);

    // Load the access token from file
    if (file_exists(__DIR__.'/token.json')) {
        $accessToken = json_decode(file_get_contents(__DIR__.'/token.json'), true);
        $client->setAccessToken($accessToken);

        // Check if the token is expired and refresh if necessary
        if ($client->isAccessTokenExpired()) {
            if ($client->getRefreshToken()) {
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                file_put_contents(__DIR__.'/token.json', json_encode($client->getAccessToken()));
            } else {
                header('Location: '.__DIR__.'/oauth2callback.php?state=' . urlencode('source=generateNewProductsList'));
                exit();
            }
        }
    } else {
        // Start OAuth flow if no token is found
        header('Location: '.__DIR__.'/oauth2callback.php?state=' . urlencode('source=generateNewProductsList'));
        exit();
    }

    // Initialize the Gmail service
    $service = new Gmail($client);

    // Prepare the message
    $boundary = uniqid(rand(), true);
    $rawMessageString = "To: $destinationEmailAddress\r\n";
    $rawMessageString .= "Subject: $emailSubject\r\n";
    $rawMessageString .= "MIME-Version: 1.0\r\n";
    $rawMessageString .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n\r\n";
    $rawMessageString .= "--$boundary\r\n";
    $rawMessageString .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $rawMessageString .= "$emailBodyText\r\n";

    // Attach the invocie list file
    if (file_exists($pathTofile_invoice_list)) {
        $fileData = file_get_contents($pathTofile_invoice_list);
        $rawMessageString .= "--$boundary\r\n";
        $rawMessageString .= "Content-Type: application/octet-stream; name=\"" . basename($pathTofile_invoice_list) . "\"\r\n";
        $rawMessageString .= "Content-Disposition: attachment; filename=\"" . basename($pathTofile_invoice_list) . "\"\r\n";
        $rawMessageString .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $rawMessageString .= chunk_split(base64_encode($fileData)) . "\r\n";
    }

    // Attach the new products file
    if (file_exists($pathTofile_new_products)) {
        $fileData = file_get_contents($pathTofile_new_products);
        $rawMessageString .= "--$boundary\r\n";
        $rawMessageString .= "Content-Type: application/octet-stream; name=\"" . basename($pathTofile_new_products) . "\"\r\n";
        $rawMessageString .= "Content-Disposition: attachment; filename=\"" . basename($pathTofile_new_products) . "\"\r\n";
        $rawMessageString .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $rawMessageString .= chunk_split(base64_encode($fileData)) . "\r\n";
    }

    // Attach the new products file
    if (file_exists($pathTofile_price_change)) {
        $fileData = file_get_contents($pathTofile_price_change);
        $rawMessageString .= "--$boundary\r\n";
        $rawMessageString .= "Content-Type: application/octet-stream; name=\"" . basename($pathTofile_price_change) . "\"\r\n";
        $rawMessageString .= "Content-Disposition: attachment; filename=\"" . basename($pathTofile_price_change) . "\"\r\n";
        $rawMessageString .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $rawMessageString .= chunk_split(base64_encode($fileData)) . "\r\n";
    }

    $rawMessageString .= "--$boundary--";

    // Encode the message
    $encodedMessage = base64_encode($rawMessageString);
    $encodedMessage = str_replace(['+', '/', '='], ['-', '_', ''], $encodedMessage);

    // Create a message
    $message = new Message();
    $message->setRaw($encodedMessage);

    try {
        if ($toSend === 1) {
            // Create a draft message
            $draft = new Draft();
            $draft->setMessage($message);
            $createdDraft = $service->users_drafts->create('me', $draft);
            echo 'Email draft created with ID: ' . $createdDraft->getId() . "<br>";
        } elseif ($toSend === 2) {
            // Send the email
            $sentMessage = $service->users_messages->send('me', $message);
            echo 'Email sent with ID: ' . $sentMessage->getId() . "<br>";
        } else {
            echo 'Invalid value for $toSend. Use 1 for draft or 2 for sending the email.' . "<br>";
        }
    } catch (Exception $e) {
        echo 'An error occurred: ' . $e->getMessage();
    }
}

?>
