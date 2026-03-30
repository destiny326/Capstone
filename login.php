<?php
// ============================================================
// login.php — Student/Staff Microsoft Login Page
// ============================================================
require_once 'config.php';
require_once 'auth.php';

// Already logged in?
if (!empty($_SESSION['logged_in']) && ($_SESSION['auth_type'] ?? '') === 'microsoft') {
    header('Location: index.php');
    exit;
}

$loginUrl = getMicrosoftLoginUrl();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In – TAMCC Foodie</title>
<link rel="icon" href="assets/logo.png" type="image/png">
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="login-page">
    <div class="login-card">
        <img src="assets/logo.png" alt="TAMCC Foodie" class="login-logo">
        <h1>TAMCC FOODIE</h1>
        <p>Marryshow's Mealhouse<br>Sign in with your TAMCC Microsoft account</p>

        <a href="<?= htmlspecialchars($loginUrl) ?>" class="ms-btn">
            <!-- Microsoft Icon SVG -->
            <svg width="20" height="20" viewBox="0 0 21 21" xmlns="http://www.w3.org/2000/svg">
                <rect x="1" y="1" width="9" height="9" fill="#f25022"/>
                <rect x="11" y="1" width="9" height="9" fill="#7fba00"/>
                <rect x="1" y="11" width="9" height="9" fill="#00a4ef"/>
                <rect x="11" y="11" width="9" height="9" fill="#ffb900"/>
            </svg>
            Sign in with Microsoft
        </a>

        <div style="margin-top:32px;padding-top:24px;border-top:1px solid var(--border);">
            <p style="font-size:12px;color:var(--muted);">
                Use your official TAMCC email address.<br>
                Only students and staff may access the ordering system.
            </p>
        </div>

        <div style="margin-top:16px;">
            <a href="admin_login.php" style="color:var(--muted);font-size:12px;">Admin / Kitchen Staff →</a>
        </div>
    </div>
</div>
</body>
</html>
