<?php
session_start();

if (empty($_SESSION['reset_email_verified'])) {
    header('Location: forgot_password.php');
    exit;
}

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
    <title>Reset Password - ClearMyRide</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/auth.css?v=<?php echo time(); ?>">
    <style>
        .password-field {
            position: relative;
        }
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            padding: 0;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            transition: color 0.2s;
        }
        .password-toggle:hover {
            color: #0A57FF;
        }
    </style>
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
        </div>
    </header>

    <main class="auth-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <div class="auth-card">
                        <div class="auth-header text-center mb-4">
                            <h1 class="h3 mb-2">Set New Password</h1>
                            <p class="text-muted">Choose a strong password for your account</p>
                        </div>

                        <?php show_flash(); ?>

                        <form id="resetPasswordForm" method="POST" action="assets/app/reset_password.php">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">New Password</label>
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
                            
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
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
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">Reset Password</button>
                            </div>
                        </form>
                        
                        <div class="text-center mt-4">
                            <a href="login.php" class="text-primary">Back to login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ===== SHOW USER‑FRIENDLY MESSAGE (same as register/login) =====
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

        // ===== PASSWORD TOGGLE (identical to register) =====
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

        // ===== RESET PASSWORD FORM HANDLER =====
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('resetPasswordForm');
            if (form) {
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();

                    const password = document.getElementById('password').value;
                    const confirm = document.getElementById('confirm_password').value;

                    if (password !== confirm) {
                        showMessage('Passwords do not match.', 'error');
                        return;
                    }
                    if (password.length < 8) {
                        showMessage('Password must be at least 8 characters.', 'error');
                        return;
                    }
                    if (!/(?=.*[A-Za-z])(?=.*\d)/.test(password)) {
                        showMessage('Password must contain both letters and numbers.', 'error');
                        return;
                    }

                    const formData = new FormData(this);
                    const submitBtn = this.querySelector('button[type="submit"]');
                    const originalText = submitBtn.innerHTML;

                    try {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Resetting...';

                        const response = await fetch(BASE_URL + '/assets/app/reset_password.php', {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: formData
                        });

                        const result = await response.json();

                        if (result.success) {
                            showMessage(result.message, 'success');
                            if (result.redirect) {
                                setTimeout(() => {
                                    window.location.href = result.redirect;
                                }, 1500);
                            }
                        } else {
                            showMessage(result.message, 'error');
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        }
                    } catch (error) {
                        console.error('Reset password error:', error);
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