<?php
// assets/app/admin/change_password.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db_connect.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current'] ?? '';
    $new = $_POST['new'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if ($new === '' || $confirm === '' || $current === '') {
        $error = 'All fields are required.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } else {
        $stmt = $pdo->prepare("SELECT password FROM admin_users WHERE id = :id LIMIT 1");
        $stmt->execute([':id'=>$_SESSION['admin_id']]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($current, $row['password'])) {
            $error = 'Current password incorrect.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $u = $pdo->prepare("UPDATE admin_users SET password = :p WHERE id = :id");
            $u->execute([':p'=>$hash,':id'=>$_SESSION['admin_id']]);
            $success = 'Password updated successfully.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Change Password - ClearMyRide Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="icon" href="../../images/favicon.png" type="image/png">
</head>
<body class="bg-light">
  <!-- Navigation -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
      <a class="navbar-brand fw-bold" href="dashboard.php">
        <i class="bi bi-gear-fill me-2"></i>ClearMyRide Admin
      </a>
      <div class="d-flex align-items-center">
        <span class="text-light me-3 d-none d-md-inline">Welcome, <strong><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'admin'); ?></strong></span>
        <div class="btn-group">
          <a class="btn btn-outline-light btn-sm" href="dashboard.php">
            <i class="bi bi-speedometer2 me-1"></i>Dashboard
          </a>
          <a class="btn btn-outline-light btn-sm" href="logout.php">
            <i class="bi bi-box-arrow-right me-1"></i>Logout
          </a>
        </div>
      </div>
    </div>
  </nav>

  <!-- Main Content -->
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-md-6 col-lg-5">
        <!-- Back Navigation -->
        <div class="mb-4">
          <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
          </a>
        </div>

        <!-- Password Card -->
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white border-0 py-4">
            <div class="text-center">
              <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 60px; height: 60px;">
                <i class="bi bi-shield-lock text-primary fs-4"></i>
              </div>
              <h2 class="h4 fw-bold text-dark mb-1">Change Password</h2>
              <p class="text-muted mb-0">Update your account security</p>
            </div>
          </div>
          
          <div class="card-body p-4">
            <!-- Alerts -->
            <?php if ($error): ?>
              <div class="alert alert-danger d-flex align-items-center" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <div><?php echo htmlspecialchars($error); ?></div>
              </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
              <div class="alert alert-success d-flex align-items-center" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <div><?php echo htmlspecialchars($success); ?></div>
              </div>
            <?php endif; ?>

            <!-- Password Form -->
            <form method="POST" class="needs-validation" novalidate>
              <div class="mb-4">
                <label for="current" class="form-label fw-semibold">Current Password</label>
                <div class="input-group">
                  <span class="input-group-text bg-light border-end-0">
                    <i class="bi bi-lock text-muted"></i>
                  </span>
                  <input type="password" 
                         name="current" 
                         id="current"
                         class="form-control border-start-0" 
                         placeholder="Enter current password" 
                         required>
                </div>
              </div>

              <div class="mb-4">
                <label for="new" class="form-label fw-semibold">New Password</label>
                <div class="input-group">
                  <span class="input-group-text bg-light border-end-0">
                    <i class="bi bi-key text-muted"></i>
                  </span>
                  <input type="password" 
                         name="new" 
                         id="new"
                         class="form-control border-start-0" 
                         placeholder="Enter new password" 
                         required>
                </div>
                <div class="form-text">Use a strong, unique password.</div>
              </div>

              <div class="mb-4">
                <label for="confirm" class="form-label fw-semibold">Confirm New Password</label>
                <div class="input-group">
                  <span class="input-group-text bg-light border-end-0">
                    <i class="bi bi-key-fill text-muted"></i>
                  </span>
                  <input type="password" 
                         name="confirm" 
                         id="confirm"
                         class="form-control border-start-0" 
                         placeholder="Confirm new password" 
                         required>
                </div>
              </div>

              <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-lg">
                  <i class="bi bi-shield-check me-2"></i>Update Password
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Security Tips -->
        <div class="card border-0 shadow-sm mt-4">
          <div class="card-body p-4">
            <h6 class="fw-bold mb-3">Security Tips</h6>
            <ul class="list-unstyled text-muted small mb-0">
              <li class="mb-2">
                <i class="bi bi-check-circle text-success me-2"></i>
                Use at least 8 characters with mixed case
              </li>
              <li class="mb-2">
                <i class="bi bi-check-circle text-success me-2"></i>
                Include numbers and special characters
              </li>
              <li class="mb-0">
                <i class="bi bi-check-circle text-success me-2"></i>
                Avoid using personal information
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
    // Basic form validation
    (function() {
      'use strict';
      const forms = document.querySelectorAll('.needs-validation');
      Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
          if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
          }
          form.classList.add('was-validated');
        }, false);
      });
    })();

    // Password confirmation validation
    document.addEventListener('DOMContentLoaded', function() {
      const newPassword = document.getElementById('new');
      const confirmPassword = document.getElementById('confirm');

      function validatePassword() {
        if (newPassword.value !== confirmPassword.value) {
          confirmPassword.setCustomValidity("Passwords don't match");
        } else {
          confirmPassword.setCustomValidity('');
        }
      }

      newPassword.addEventListener('change', validatePassword);
      confirmPassword.addEventListener('keyup', validatePassword);
    });
  </script>
</body>
</html>