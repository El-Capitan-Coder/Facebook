<?php
require_once __DIR__ . '/vendor/autoload.php';

use Facebook\Facebook;
use Dotenv\Dotenv;

session_start();

// Load .env variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Database connection
$conn = new mysqli('localhost', 'root', '', 'pc-builder');
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Initialize Facebook SDK
$fb = new Facebook([
    'app_id' => $_ENV['FB_APP_ID'],
    'app_secret' => $_ENV['FB_APP_SECRET'],
    'default_graph_version' => 'v18.0',
]);

$helper = $fb->getRedirectLoginHelper();

try {
    $accessToken = $helper->getAccessToken();

    if (!isset($accessToken)) {
        echo 'No access token received.';
        exit;
    }

    $_SESSION['facebook_access_token'] = (string) $accessToken;

    // Request user info
    $response = $fb->get('/me?fields=id,name,email', $accessToken);
    $user = $response->getGraphUser();

    $facebook_id = $user['id'];
    $name = $user['name'];
    $email = $user->getEmail(); // May return null if not shared

    if (!$email) {
        echo "Email not available. Please provide an email to continue.";
        exit;
    }

    // Check if user exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE facebook_id = ? OR email = ?");
    $stmt->bind_param("ss", $facebook_id, $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 0) {
        // Insert new user
        $stmt = $conn->prepare("INSERT INTO users (email, facebook_id, auth_provider, is_active, created_at) VALUES (?, ?, 'Facebook', 1, NOW())");
        $stmt->bind_param("ss", $email, $facebook_id);
        $stmt->execute();
    }

    // Start user session
    $_SESSION['user_email'] = $email;
    $_SESSION['auth_provider'] = 'Facebook';

    // Redirect to profile
    header("Location: profile-page-form.php");
    exit();

} catch (Facebook\Exceptions\FacebookResponseException $e) {
    echo 'Graph error: ' . $e->getMessage();
    exit;
} catch (Facebook\Exceptions\FacebookSDKException $e) {
    echo 'SDK error: ' . $e->getMessage();
    exit;
} catch (Exception $e) {
    echo 'General error: ' . $e->getMessage();
    exit;
}
?>
