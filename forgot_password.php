<?php
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/assets/app/alerts.php';
require_once __DIR__ . '/assets/app/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - ClearMyRide</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/auth.css?v=<?php echo time(); ?>">
    <script>
        const BASE_URL = '<?php echo rtrim(base_url(''), '/'); ?>';
    </script>
</head>
<body class="auth-page">
    <header class="nav" role="banner">
        <div class="container">
            <a class="brand" href="/">
                <img src="assets/images/CMR Transparent BG.png" alt="Clear My Ride logo" />
                <span class="brand-text">ClearMyRide</span>
            </a>
            <div class="nav-actions">
                <a href="login.php" class="btn-outline">Login</a>
                <a href="register.php" class="cta-primary">Sign Up</a>
            </div>
        </div>
    </header>

    <main class="auth-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <div class="auth-card">
                        <div class="auth-header text-center mb-4">
                            <h1 class="h3 mb-2">Reset Your Password</h1>
                            <p class="text-muted">Enter your email to receive a verification code</p>
                        </div>

                        <!-- PHP flash messages (non‑AJAX fallback) -->
                        <?php show_flash(); ?>

                        <form id="forgotPasswordForm" method="POST" action="assets/app/forgot_password.php">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            
                            <div class="mb-4">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" required 
                                       placeholder="you@example.com">
                                <small class="form-text text-muted">We'll send you a 6‑digit code to reset your password.</small>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">Send Verification Code</button>
                            </div>
                        </form>
                        
                        <div class="text-center mt-4">
                            <p class="text-muted mb-0">Remember your password? 
                                <a href="login.php" class="text-primary fw-semibold">Sign in here</a>
                            </p>
                        </div>
                        
                        <div class="text-center mt-4">
                            <a href="/" class="text-muted">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M19 12H5M12 19l-7-7 7-7"/>
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
        // ===== SHOW USER‑FRIENDLY MESSAGE (identical to register/login) =====
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

        // ===== FORGOT PASSWORD FORM HANDLER =====
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('forgotPasswordForm');
            if (form) {
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();

                    const email = document.getElementById('email').value.trim();
                    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                        showMessage('Please enter a valid email address.', 'error');
                        return;
                    }

                    const formData = new FormData(this);
                    const submitBtn = this.querySelector('button[type="submit"]');
                    const originalText = submitBtn.innerHTML;

                    try {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Sending...';

                        const response = await fetch(BASE_URL + '/assets/app/forgot_password.php', {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: formData
                        });

                        const result = await response.json();

                        if (result.success) {
                            showMessage(result.message, 'success');
                            this.reset();

                            if (result.redirect) {
                                setTimeout(() => {
                                    window.location.href = result.redirect;
                                }, 2000);
                            }
                        } else {
                            showMessage(result.message || 'Failed to send verification code.', 'error');
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        }
                    } catch (error) {
                        console.error('Forgot password error:', error);
                        showMessage('Network error. Please try again.', 'error');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }
                });
            }
        });
    </script>
</body>
</html>