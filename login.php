<?php
session_start();
require_once __DIR__ . '/assets/app/config.php';

// Redirect if already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
    header('Location: dashboard.php');
    exit;
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/assets/app/alerts.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ClearMyRide</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/auth.css?v=<?php echo time(); ?>">
</head>

<body class="auth-page">
    <!-- Navigation -->
    <header class="nav" role="banner">
        <div class="container">
            <a class="brand" href="/">
                <img src="assets/images/CMR Transparent BG.png" alt="Clear My Ride logo" />
                <span class="brand-text">ClearMyRide</span>
            </a>
            <div class="nav-actions">
                <a href="register.php" class="cta-primary">Sign Up</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="auth-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <div class="auth-card">
                        <div class="auth-header text-center mb-4">
                            <h1 class="h3 mb-2">Welcome Back</h1>
                            <p class="text-muted">Sign in to your ClearMyRide account</p>
                        </div>

                        <?php show_flash(); ?>

                        <form id="loginForm" method="POST" action="assets/app/login.php">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" required
                                    placeholder="you@example.com">
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <div class="password-field">
                                    <input type="password" class="form-control" id="password" name="password" required
                                        placeholder="Enter your password">
                                    <button type="button" class="password-toggle" id="togglePassword" aria-label="Show password">
                                        <!-- Eye icon (visible when password is hidden) -->
                                        <svg class="eye-open" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                            <circle cx="12" cy="12" r="3" />
                                        </svg>
                                        <!-- Eye-slash icon (hidden by default) -->
                                        <svg class="eye-closed" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="display: none;">
                                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24" />
                                            <line x1="1" y1="1" x2="23" y2="23" />
                                        </svg>
                                    </button>
                                </div>
                                <div class="text-end mt-1">
                                    <a href="forgot_password.php" class="text-small">Forgot password?</a>
                                </div>
                            </div>

                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="remember" name="remember">
                                <label class="form-check-label" for="remember">Remember me</label>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">Sign In</button>
                            </div>
                        </form>

                        <div class="text-center mt-4">
                            <p class="text-muted mb-0">Don't have an account?
                                <a href="register.php" class="text-primary fw-semibold">Sign up here</a>
                            </p>
                        </div>

                       <div class="text-center mt-4">
    <a href="<?php echo base_url(); ?>" class="text-muted">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path d="M19 12H5M12 19l-7-7 7-7" />
        </svg>
        Back to home
    </a>
</div>
                    </div>
                </div>
            </div>
        </div>
    </main>

   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ===== SHOW USER-FRIENDLY MESSAGE =====
function showMessage(message, type = 'error', container = null) {
  const authCard = document.querySelector('.auth-card');
  let msgContainer = container || authCard?.querySelector('.message-container');
  if (!msgContainer && authCard) {
    msgContainer = document.createElement('div');
    msgContainer.className = 'message-container';
    authCard.prepend(msgContainer);
  }
  if (!msgContainer) return;

  msgContainer.innerHTML = '';

  const msg = document.createElement('div');
  msg.className = `message message-${type}`;

  let iconSvg = '';
  if (type === 'error') {
    iconSvg = '<svg class="message-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><circle cx="12" cy="16" r="1"/></svg>';
  } else if (type === 'success') {
    iconSvg = '<svg class="message-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="20 6 9 17 4 12"/></svg>';
  } else {
    iconSvg = '<svg class="message-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><circle cx="12" cy="8" r="1"/></svg>';
  }

  msg.innerHTML = `
    ${iconSvg}
    <div class="message-content">${message}</div>
    <button class="message-close" aria-label="Dismiss">✕</button>
  `;

  msgContainer.appendChild(msg);

  msg.querySelector('.message-close').addEventListener('click', () => {
    msg.remove();
    if (msgContainer.children.length === 0) msgContainer.remove();
  });

  setTimeout(() => {
    if (msg.parentNode) msg.remove();
  }, 6000);
}

// ===== LOGIN FORM HANDLER =====
document.addEventListener('DOMContentLoaded', function() {
  const loginForm = document.getElementById('loginForm');
  if (loginForm) {
    loginForm.addEventListener('submit', async function(e) {
      e.preventDefault();

      const formData = new FormData(this);
      const submitBtn = this.querySelector('button[type="submit"]');
      const originalText = submitBtn.innerHTML;

      try {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Signing in...';

        const response = await fetch('assets/app/login.php', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        if (result.success) {
          showMessage('Login successful! Redirecting...', 'success');
          setTimeout(() => {
            window.location.href = result.redirect || 'dashboard.php';
          }, 1000);
        } else {
          // User-friendly error message
          const errorMsg = result.message || 'Invalid email or password.';
          showMessage(errorMsg, 'error');
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalText;
        }
      } catch (error) {
        console.error('Login error:', error);
        showMessage('Unable to connect. Please check your internet.', 'error');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
      }
    });
  }

  // ===== PASSWORD VISIBILITY TOGGLE =====
  const togglePassword = document.getElementById('togglePassword');
  if (togglePassword) {
    const passwordInput = document.getElementById('password');
    const eyeOpen = togglePassword.querySelector('.eye-open');
    const eyeClosed = togglePassword.querySelector('.eye-closed');

    togglePassword.addEventListener('click', function() {
      const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
      passwordInput.setAttribute('type', type);
      if (type === 'text') {
        eyeOpen.style.display = 'none';
        eyeClosed.style.display = 'block';
        this.setAttribute('aria-label', 'Hide password');
      } else {
        eyeOpen.style.display = 'block';
        eyeClosed.style.display = 'none';
        this.setAttribute('aria-label', 'Show password');
      }
    });
  }
});
</script>
</body>

</html>