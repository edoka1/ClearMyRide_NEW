<?php
// assets/app/admin/login.php
session_start();
require_once __DIR__ . '/../db_connect.php';

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $password = $_POST['password'] ?? '';

  if ($username === '' || $password === '') {
    $err = 'Please provide both username and password.';
  } else {
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = :u LIMIT 1");
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
      // login success
      $_SESSION['admin_id'] = $user['id'];
      $_SESSION['admin_username'] = $user['username'];
      header("Location: dashboard.php");
      exit;
    } else {
      $err = 'Invalid username or password.';
    }
  }
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login - ClearMyRide</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="icon" href="../../images/favicon.png" type="image/png">
  <style>
    body {
      background: linear-gradient(135deg, #0a57ff 0%, #3ba7ff 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-direction: column;
    }

    .login-container {
      max-width: 420px;
      width: 100%;
      padding: 20px;
    }

    .brand-text {
      font-weight: 700;
      letter-spacing: -0.5px;
    }

    .btn-outline-light:hover {
      background: rgba(255, 255, 255, 0.15);
      color: #fff;
    }
  </style>
</head>

<body>
  <div class="login-container">
    <!-- Login Card -->
    <div class="card border-0 shadow-lg">
      <div class="card-body p-5">
        <!-- Brand Header -->
        <div class="text-center mb-4">
          <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
            <i class="bi bi-shield-lock text-primary fs-2"></i>
          </div>
          <h1 class="h3 brand-text text-dark mb-2">ClearMyRide Admin</h1>
          <p class="text-muted">Sign in to access the dashboard</p>
        </div>

        <!-- Error Alert -->
        <?php if ($err): ?>
          <div class="alert alert-danger d-flex align-items-center" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <div><?php echo htmlspecialchars($err); ?></div>
          </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form method="POST" action="">
          <div class="mb-4">
            <label for="username" class="form-label fw-semibold">Username</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0">
                <i class="bi bi-person text-muted"></i>
              </span>
              <input type="text"
                name="username"
                id="username"
                class="form-control border-start-0"
                placeholder="Enter your username"
                required
                autofocus>
            </div>
          </div>

          <div class="mb-4">
            <label for="password" class="form-label fw-semibold">Password</label>
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0">
                <i class="bi bi-lock text-muted"></i>
              </span>
              <input type="password"
                name="password"
                id="password"
                class="form-control border-start-0"
                placeholder="Enter your password"
                required>
            </div>
          </div>

          <div class="d-grid">
            <button type="submit" class="btn btn-primary btn-lg">
              <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
            </button>
          </div>
        </form>

        <!-- Security Note -->
        <div class="text-center mt-4">
          <div class="border-top pt-3">
            <p class="text-muted small mb-0">
              <i class="bi bi-shield-check me-1"></i>Secure admin access
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Back to Website Button -->
    <div class="text-center mt-4">
      <a href="https://clearmyride.com/" class="btn btn-outline-light btn-sm px-4" rel="noopener noreferrer">
        <i class="bi bi-arrow-left me-1"></i>Back to Website
      </a>
    </div>

    <!-- Footer Note -->
    <div class="text-center mt-3">
      <p class="text-white-50 small mb-0">
        &copy; <?php echo date('Y'); ?> ClearMyRide. Administrative access only.
      </p>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>