<?php
/**
 * Check which Gmail account is authenticated
 */

require '../vendor/autoload.php';

use Google\Client;
use Google\Service\Gmail;

echo "======================================================================\n";
echo " GMAIL ACCOUNT CHECKER\n";
echo "======================================================================\n\n";

// Check if oauth_credentials.json exists
if (!file_exists(__DIR__ . '/oauth_credentials.json')) {
    echo "❌ ERROR: oauth_credentials.json NOT found\n";
    echo "   This file should be in: " . __DIR__ . "/oauth_credentials.json\n";
    echo "   You need to download it from Google Cloud Console\n\n";
} else {
    echo "✓ oauth_credentials.json exists\n";

    // Read and display some info (without showing sensitive data)
    $creds = json_decode(file_get_contents(__DIR__ . '/oauth_credentials.json'), true);
    if (isset($creds['web']['client_id'])) {
        echo "  Client ID: " . substr($creds['web']['client_id'], 0, 20) . "...\n";
    } elseif (isset($creds['installed']['client_id'])) {
        echo "  Client ID: " . substr($creds['installed']['client_id'], 0, 20) . "...\n";
    }
}

echo "\n";

// Check if token.json exists
if (!file_exists(__DIR__ . '/token.json')) {
    echo "❌ ERROR: token.json NOT found\n";
    echo "   This file should be in: " . __DIR__ . "/token.json\n";
    echo "   You need to authenticate by visiting oauth2callback.php\n\n";
    echo "To authenticate:\n";
    echo "1. Open browser and go to: http://localhost/website/gmailAccess/oauth2callback.php\n";
    echo "2. Login with: inno.ocr@gmail.com\n";
    echo "3. Grant permissions\n";
    echo "4. Token will be saved automatically\n";
    exit;
} else {
    echo "✓ token.json exists\n\n";

    // Try to get the authenticated account
    try {
        $client = new Google_Client();
        $client->setAuthConfig(__DIR__ . '/oauth_credentials.json');
        $client->setAccessType('offline');
        $client->setScopes([Gmail::GMAIL_READONLY]);

        $accessToken = json_decode(file_get_contents(__DIR__ . '/token.json'), true);
        $client->setAccessToken($accessToken);

        // Check if token is expired
        if ($client->isAccessTokenExpired()) {
            echo "⚠️  Token is EXPIRED\n";

            if ($client->getRefreshToken()) {
                echo "   Attempting to refresh token...\n";
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                file_put_contents(__DIR__ . '/token.json', json_encode($client->getAccessToken()));
                echo "   ✓ Token refreshed successfully\n\n";
            } else {
                echo "   ❌ Cannot refresh - you need to re-authenticate\n";
                echo "   Visit: http://localhost/website/gmailAccess/oauth2callback.php\n";
                exit;
            }
        } else {
            echo "✓ Token is VALID\n\n";
        }

        // Get the authenticated email address
        $service = new Gmail($client);
        $profile = $service->users->getProfile('me');
        $authenticatedEmail = $profile->getEmailAddress();

        echo "======================================================================\n";
        echo " AUTHENTICATED ACCOUNT\n";
        echo "======================================================================\n";
        echo "Email: $authenticatedEmail\n";
        echo "======================================================================\n\n";

        // Check if it's the expected account
        if ($authenticatedEmail === 'inno.ocr@gmail.com') {
            echo "✅ SUCCESS: You are authenticated with the correct account!\n";
            echo "   You can now run download_emails_batch.php\n";
        } else {
            echo "⚠️  WARNING: You are authenticated with: $authenticatedEmail\n";
            echo "   But you want to use: inno.ocr@gmail.com\n\n";
            echo "To switch accounts:\n";
            echo "1. Delete token.json: " . __DIR__ . "/token.json\n";
            echo "2. Visit: http://localhost/website/gmailAccess/oauth2callback.php\n";
            echo "3. Login with: inno.ocr@gmail.com\n";
        }

    } catch (Exception $e) {
        echo "❌ ERROR: " . $e->getMessage() . "\n";
    }
}
