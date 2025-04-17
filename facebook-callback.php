<?php
require 'vendor/autoload.php';

use Facebook\Facebook;

session_start();

// Database connection
$conn = new mysqli('localhost', 'root', '', 'pc-builder');
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}


]);

$helper = $fb->getRedirectLoginHelper();

try {
    $accessToken = $helper->getAccessToken();
    if (!isset($accessToken)) {
        echo 'No access token received.';
        exit;
    }
    
    $_SESSION['facebook_access_token'] = (string) $accessToken;
    
    $response = $fb->get('/me?fields=id,name,email', $accessToken);
    $user = $response->getGraphUser();

    $facebook_id = $user['id'];
    $name = $user['name'];
    $email = isset($user['email']) ? $user['email'] : null; // Fallback if email is missing

    if (!$email) {
        echo "Email not available. Please provide an email to continue.";
        exit;
    }

    // Check if user already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE facebook_id = ? OR email = ?");
    $stmt->bind_param("ss", $facebook_id, $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 0) {
        // New user, insert into database
        $stmt = $conn->prepare("INSERT INTO users (email, facebook_id, auth_provider, is_active, created_at) VALUES (?, ?, 'Facebook', 1, NOW())");
        $stmt->bind_param("ss", $email, $facebook_id);
        $stmt->execute();
    }

    // Log the user in (session handling)
    $_SESSION['user_email'] = $email;
    $_SESSION['auth_provider'] = 'Facebook';

    header("Location: profile-page-form.php"); // Redirect after login
    exit();

} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
