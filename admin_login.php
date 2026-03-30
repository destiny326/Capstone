<?php
// ============================================================
// admin_login.php — Admin / Kitchen Staff Login (DB credentials)
// ============================================================
require_once 'config.php';
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Already logged in as admin?
if (!empty($_SESSION['logged_in']) && ($_SESSION['auth_type'] ?? '') === 'database') {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter your username and password.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            // Regenerate session ID on privilege escalation
            session_regenerate_id(true);

            $_SESSION['logged_in']        = true;
            $_SESSION['auth_type']        = 'database';
            $_SESSION['admin_id']         = $admin['id'];
            $_SESSION['admin_username']   = $admin['username'];
            $_SESSION['admin_name']       = $admin['full_name'];
            $_SESSION['admin_role']       = $admin['role'];
            $_SESSION['last_regenerated'] = time();

            header('Location: dashboard.php');
            exit;
        } else {
            // Timing-safe delay to slow brute-force
            usleep(300000);
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login – TAMCC Foodie</title>
<link rel="icon" href="assets/logo.png" type="image/png">
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="login-page">
    <div class="login-card">
        <img src="assets/logo.png" alt="TAMCC Foodie" class="login-logo">
        <h1>ADMIN</h1>
        <p>Marryshow's Mealhouse — Staff Portal</p>

        <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control" 
                       autocomplete="username" required
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" 
                       autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn btn-primary btn-full" style="margin-top:8px;">Sign In</button>
        </form>

        <div style="margin-top:24px;padding-top:24px;border-top:1px solid var(--border);">
            <a href="login.php" style="color:var(--muted);font-size:13px;">← Student/Staff Login</a>
        </div>
    </div>
</div>
</body>
</html>
