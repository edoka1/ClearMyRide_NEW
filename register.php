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
    <title>Sign Up - ClearMyRide</title>
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
                <a href="login.php" class="btn-outline">Login</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="auth-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-7">
                    <div class="auth-card">
                        <div class="auth-header text-center mb-4">
                            <h1 class="h3 mb-2">Create Your Account</h1>
                            <p class="text-muted">Join ClearMyRide to streamline your vehicle and license renewals</p>
                        </div>

                        <?php show_flash(); ?>

                        <form id="registerForm" method="POST" action="assets/app/register.php">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="full_name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" required
                                        placeholder="Jane A. Doe">
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" required
                                        placeholder="you@example.com">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone"
                                        placeholder="(410) 555-1234">
                                </div>
                                <div class="col-md-6">
                                    <label for="date_of_birth" class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="password" class="form-label">Password</label>
                                    <div class="password-field">
                                        <input type="password" class="form-control" id="password" name="password" required
                                            placeholder="Minimum 8 characters">
                                        <button type="button" class="password-toggle" data-target="password" aria-label="Show password">
                                            <svg class="eye-open" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                                <circle cx="12" cy="12" r="3" />
                                            </svg>
                                            <svg class="eye-closed" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="display: none;">
                                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24" />
                                                <line x1="1" y1="1" x2="23" y2="23" />
                                            </svg>
                                        </button>
                                    </div>
                                    <small class="form-text text-muted">Include letters and numbers</small>
                                </div>
                                <div class="col-md-6">
                                    <label for="confirm_password" class="form-label">Confirm Password</label>
                                    <div class="password-field">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required
                                            placeholder="Confirm your password">
                                        <button type="button" class="password-toggle" data-target="confirm_password" aria-label="Show password">
                                            <svg class="eye-open" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                                <circle cx="12" cy="12" r="3" />
                                            </svg>
                                            <svg class="eye-closed" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="display: none;">
                                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24" />
                                                <line x1="1" y1="1" x2="23" y2="23" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="terms.php" target="_blank">Terms of Service</a> and <a href="privacy_policy.php" target="_blank">Privacy Policy</a>
                                </label>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">Create Account</button>
                            </div>
                        </form>

                        <div class="text-center mt-4">
                            <p class="text-muted mb-0">Already have an account?
                                <a href="login.php" class="text-primary fw-semibold">Sign in here</a>
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
// ===== SHOW USER-FRIENDLY MESSAGE (same function as in login) =====
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

// ===== REGISTER FORM HANDLER =====
document.addEventListener('DOMContentLoaded', function() {
  const registerForm = document.getElementById('registerForm');
  if (registerForm) {
    registerForm.addEventListener('submit', async function(e) {
      e.preventDefault();

      // ===== USER-FRIENDLY VALIDATION =====
      const password = document.getElementById('password').value;
      const confirmPassword = document.getElementById('confirm_password').value;

      if (password !== confirmPassword) {
        showMessage('Passwords do not match. Please try again.', 'error');
        return;
      }

      if (password.length < 8) {
        showMessage('Password must be at least 8 characters.', 'error');
        return;
      }

      if (!/(?=.*[A-Za-z])(?=.*\d)/.test(password)) {
        showMessage('Password must include both letters and numbers.', 'error');
        return;
      }

      const formData = new FormData(this);
      const submitBtn = this.querySelector('button[type="submit"]');
      const originalText = submitBtn.innerHTML;

      try {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Creating account...';

        const response = await fetch('assets/app/register.php', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        if (result.success) {
          showMessage('Account created successfully! Redirecting...', 'success');
          setTimeout(() => {
            window.location.href = result.redirect || 'dashboard.php';
          }, 1000);
        } else {
          const errorMsg = result.message || 'Registration failed. Please check your details.';
          showMessage(errorMsg, 'error');
          submitBtn.disabled = false;
          submitBtn.innerHTML = originalText;
        }
      } catch (error) {
        console.error('Registration error:', error);
        showMessage('Network error – please try again later.', 'error');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
      }
    });
  }

  // ===== PASSWORD VISIBILITY TOGGLE (for all .password-toggle) =====
  document.querySelectorAll('.password-toggle').forEach(button => {
    button.addEventListener('click', function(e) {
      e.preventDefault();
      const targetId = this.dataset.target;
      const passwordInput = document.getElementById(targetId);
      const eyeOpen = this.querySelector('.eye-open');
      const eyeClosed = this.querySelector('.eye-closed');

      if (!passwordInput) return;

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
  });
});
</script>
</body>

</html>