<?php
require '../vendor/autoload.php';

use Google\Service\Gmail as Google_Service_Gmail;

session_start();

// Check if access token is available
if (!isset($_SESSION['access_token']) && !file_exists('token.json')) {
    // Initialize the client
    $client = new Google_Client();
    $client->setApplicationName('Gmail API PHP Quickstart');
    $client->setAuthConfig('oauth_credentials.json');
    $client->setAccessType('offline');
    $client->setScopes(Google_Service_Gmail::GMAIL_READONLY);

    // Generate the auth URL with the state parameter
    $authUrl = $client->createAuthUrl();
    $authUrl .= '&state=' . urlencode('source=get_last_email_subject'); // Pass the source parameter
    header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
    exit();
}

// Load the access token
if (file_exists('token.json')) {
    $accessToken = json_decode(file_get_contents('token.json'), true);
} else {
    $accessToken = $_SESSION['access_token'];
}

// Initialize the client
$client = new Google_Client();
$client->setApplicationName('Gmail API PHP Quickstart');
$client->setAuthConfig('oauth_credentials.json');
$client->setAccessType('offline');
$client->setAccessToken($accessToken);

// Initialize the Gmail service
$service = new Google_Service_Gmail($client);

// Get the latest email
try {
    $results = $service->users_messages->listUsersMessages('me', ['maxResults' => 1]);
    if (count($results->getMessages()) > 0) {
        $messageId = $results->getMessages()[0]->getId();
        $message = $service->users_messages->get('me', $messageId);
        $headers = $message->getPayload()->getHeaders();

        foreach ($headers as $header) {
            if ($header->getName() == 'Subject') {
                echo 'Subject: ' . $header->getValue();
                break;
            }
        }

        //listLabels($service, 'me');
    } else {
        echo 'No messages found.';
    }
} catch (Exception $e) {
    echo 'An error occurred: ' . $e->getMessage();
}

/*
function listLabels($service, $userId) {
    try {
        $labelsResponse = $service->users_labels->listUsersLabels($userId);
        $labels = $labelsResponse->getLabels();
        if (count($labels) == 0) {
            print "No labels found.\n";
        } else {
            print "Labels:\n";
            foreach ($labels as $label) {
                printf("- %s (ID: %s)\n", $label->getName(), $label->getId());
            }
        }
    } catch (Exception $e) {
        echo 'An error occurred: ' . $e->getMessage();
    }
} */
?>
