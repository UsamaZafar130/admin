<?php
require_once __DIR__ . '/settings/config.php';

$pdo = null;
try {
    @include __DIR__ . '/includes/db_connection.php';
} catch (Exception $e) {
    // For testing purposes, allow login to work without database
    error_log('Login page - Database connection failed: ' . $e->getMessage());
}

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter username and password.';
    } else {
        // Check if database connection is working
        if (!$pdo) {
            $error = 'Database connection failed. Please contact administrator.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id, username, password_hash, role FROM users WHERE username = :username AND deleted_at IS NULL LIMIT 1");
                $stmt->execute(['username' => $username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['last_activity'] = time();

                    // Updated: Use correct redirect session variable for after-login redirection
                    $redirect = !empty($_SESSION['redirect_after_login']) ? $_SESSION['redirect_after_login'] : 'index.php';
                    unset($_SESSION['redirect_after_login']);
                    header('Location: ' . $redirect);
                    exit;
                } else {
                    $error = 'Invalid username or password.';
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
                error_log('Login database error: ' . $e->getMessage());
            }
        }
    }
}

$timeout = isset($_GET['timeout']);
$redirect_required = isset($_GET['redirect']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="spacelab">
<head>
    <meta charset="UTF-8">
    <title>Login &mdash; FrozoFun Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="/assets/img/favicon.png" type="image/png">
    <!-- Bootstrap 5 with Bootswatch theme -->
    <link id="bootstrap-theme" href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.2/dist/spacelab/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/bootstrap-overrides.css">
</head>
<body class="bg-primary-subtle">
<div class="container-fluid d-flex justify-content-center align-items-center min-vh-100">
  <div class="col-12 col-sm-8 col-md-6 col-lg-4">
    <div class="card shadow-lg border-0">
      <div class="card-body p-5">
        <div class="text-center mb-4">
          <img src="/assets/img/logo.png" alt="FrozoFun Logo" class="mb-3" style="height:80px;width:auto;">
          <h2 class="card-title text-primary mb-2">FrozoFun Admin</h2>
          <p class="text-muted">Sign in to your account</p>
        </div>
        
        <form method="post" action="login.php" autocomplete="off">
          <?php if ($timeout): ?>
              <div class="alert alert-warning">
                  <i class="fas fa-clock me-2"></i>Session expired due to inactivity. Please log in again.
              </div>
          <?php elseif ($redirect_required): ?>
              <div class="alert alert-info">
                  <i class="fas fa-sign-in-alt me-2"></i>Please log in to continue.
              </div>
          <?php endif; ?>
          <?php if ($error): ?>
              <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>
          
          <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <input id="username" type="text" name="username" class="form-control form-control-lg" required autofocus autocomplete="off">
          </div>
          
          <div class="mb-4">
            <label for="password" class="form-label">Password</label>
            <input id="password" type="password" name="password" class="form-control form-control-lg" required autocomplete="off">
          </div>
          
          <div class="d-grid">
            <button type="submit" class="btn btn-primary btn-lg btn-3d">
              <i class="fas fa-sign-in-alt me-2"></i> Login
            </button>
          </div>
        </form>
      </div>
      
      <div class="card-footer bg-transparent text-center">
        <small class="text-muted">&copy; <?= date('Y') ?> FrozoFun. All Rights Reserved</small>
      </div>
    </div>
  </div>
</div>
<!-- Include Bootstrap and theme manager -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/bootstrap-theme-manager.js"></script>
</body>
</html>