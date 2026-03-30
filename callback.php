<?php
// ============================================================
// callback.php — Microsoft OAuth2 Callback Handler
// ============================================================

require_once 'config.php';
require_once 'db.php';

session_start();

// --- CSRF state verification ---
if (!isset($_GET['state']) || $_GET['state'] !== ($_SESSION['oauth_state'] ?? '')) {
    die('Invalid OAuth state. Possible CSRF attack.');
}
unset($_SESSION['oauth_state']);

if (isset($_GET['error'])) {
    $err = htmlspecialchars($_GET['error_description'] ?? $_GET['error']);
    die("Microsoft login error: $err");
}

if (!isset($_GET['code'])) {
    die('No authorization code received.');
}

$code = $_GET['code'];

// --- Exchange code for access token ---
$tokenUrl = AZURE_AUTHORITY . '/oauth2/v2.0/token';
$postData = http_build_query([
    'client_id'     => AZURE_CLIENT_ID,
    'client_secret' => AZURE_CLIENT_SECRET,
    'code'          => $code,
    'redirect_uri'  => AZURE_REDIRECT_URI,
    'grant_type'    => 'authorization_code',
    'scope'         => AZURE_SCOPES,
]);

$ch = curl_init($tokenUrl);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $postData,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
]);
$response = curl_exec($ch);
curl_close($ch);

$tokenData = json_decode($response, true);

if (!isset($tokenData['access_token'])) {
    die('Failed to obtain access token from Microsoft.');
}

$accessToken = $tokenData['access_token'];
$expiresIn   = $tokenData['expires_in'] ?? 3600;
$expiresAt   = date('Y-m-d H:i:s', time() + $expiresIn);

// --- Fetch user profile from Microsoft Graph ---
$ch = curl_init('https://graph.microsoft.com/v1.0/me');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
]);
$profileResponse = curl_exec($ch);
curl_close($ch);

$profile = json_decode($profileResponse, true);

if (!isset($profile['id'])) {
    die('Failed to retrieve user profile from Microsoft.');
}

$microsoftId = $profile['id'];
$email       = $profile['mail'] ?? $profile['userPrincipalName'] ?? '';
$fullName    = $profile['displayName'] ?? 'Unknown User';

// --- Upsert user in database ---
$db = getDB();
$stmt = $db->prepare("
    INSERT INTO users (microsoft_id, email, full_name, access_token, token_expires_at)
    VALUES (:mid, :email, :name, :token, :expires)
    ON DUPLICATE KEY UPDATE
        email = VALUES(email),
        full_name = VALUES(full_name),
        access_token = VALUES(access_token),
        token_expires_at = VALUES(token_expires_at),
        last_login = CURRENT_TIMESTAMP
");
$stmt->execute([
    ':mid'     => $microsoftId,
    ':email'   => $email,
    ':name'    => $fullName,
    ':token'   => $accessToken,
    ':expires' => $expiresAt,
]);

// Fetch user row
$stmt = $db->prepare("SELECT * FROM users WHERE microsoft_id = ?");
$stmt->execute([$microsoftId]);
$user = $stmt->fetch();

// --- Set session ---
$_SESSION['user_id']   = $user['id'];
$_SESSION['user_name'] = $user['full_name'];
$_SESSION['user_email']= $user['email'];
$_SESSION['user_role'] = $user['role'];
$_SESSION['auth_type'] = 'microsoft';
$_SESSION['logged_in'] = true;

// --- Redirect to menu ---
header('Location: index.php');
exit;
