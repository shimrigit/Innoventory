<?php
require '../vendor/autoload.php';

use Google\Service\Gmail as Google_Service_Gmail; 

session_start();

$client = new Google_Client();
$client->setApplicationName('Gmail API PHP Quickstart');
$client->setScopes(Google_Service_Gmail::GMAIL_MODIFY);
$client->setAuthConfig('oauth_credentials.json');
$client->setAccessType('offline');
$client->setRedirectUri('http://localhost/website/gmailAccess/oauth2callback.php');

if (!isset($_GET['code'])) {
    $authUrl = $client->createAuthUrl();
    header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
    exit();  
} else {
    $client->fetchAccessTokenWithAuthCode($_GET['code']);
    $_SESSION['access_token'] = $client->getAccessToken();
    file_put_contents('token.json', json_encode($client->getAccessToken()));

    // Retrieve and parse the state parameter
    if (isset($_GET['state'])) {
        parse_str($_GET['state'], $stateParams);
        $source = $stateParams['source'] ?? 'default';

        switch ($source) {
            case 'get_last_email_subject':
                header('Location: get_last_email_subject.php');
                break;
            case 'download_ocrfiles_from_emails': 
                header('Location: download_ocrfiles_from_emails.php');
                break;
            case 'generateNewProductsList': 
                header('Location: ../gmailAccess/generateNewProductsList.php');
                break;
            default:
                header('Location: default_page.php');
                break;
        }
    } else {
        header('Location: default_page.php'); // Fallback if no state is present
    }
    exit(); 
}
?>
