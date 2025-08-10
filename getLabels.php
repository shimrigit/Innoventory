<?php
require 'vendor/autoload.php';

use Google\Client;
use Google\Service\Gmail;

// Load previously authorized token from a file, or create a new one if it doesn't exist
function getClient()
{
    $client = new Client();
    $client->setApplicationName('Gmail API PHP Quickstart');
    $client->setScopes(Gmail::GMAIL_READONLY);
    $client->setAuthConfig('gmailAccess/oauth_credentials.json');
    $client->setAccessType('offline');
    $client->setPrompt('select_account consent');

    // Load the token from the file if it exists
    $tokenPath = 'gmailAccess/token.json';
    if (file_exists($tokenPath)) {
        $accessToken = json_decode(file_get_contents($tokenPath), true);
        $client->setAccessToken($accessToken);
    }

    // Refresh the token if it's expired
    if ($client->isAccessTokenExpired()) {
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        } else {
            // If the authorization code is in the URL, use it
            if (isset($_GET['code'])) {
                $authCode = $_GET['code'];
                // Exchange authorization code for an access token
                $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
                $client->setAccessToken($accessToken);

                // Save the token to a file for future use
                if (!file_exists(dirname($tokenPath))) {
                    mkdir(dirname($tokenPath), 0700, true);
                }
                file_put_contents($tokenPath, json_encode($client->getAccessToken()));

                // Redirect back to the script (without the 'code' in URL)
                header('Location: ' . filter_var($_SERVER['PHP_SELF'], FILTER_SANITIZE_URL));
                exit;
            } else {
                // If no code, redirect the user to the authorization URL
                $authUrl = $client->createAuthUrl();
                header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
                exit;
            }
        }
    }
    return $client;
}

// Get the API client and Gmail service
$client = getClient();
$service = new Gmail($client);

// Request the list of labels
$labelsResponse = $service->users_labels->listUsersLabels('me');
$labels = $labelsResponse->getLabels();

if (count($labels) == 0) {
    print "No labels found.\n";
} else {
    print "Labels:\n";
    foreach ($labels as $label) {
        printf("Label Name: %s - Label ID: %s\n", $label->getName(), $label->getId());
    }
}
?>
