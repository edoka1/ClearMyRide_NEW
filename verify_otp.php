<?php
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$email = $_GET['email'] ?? '';
if (empty($email)) {
    header('Location: forgot_password.php');
    exit;
}

require_once __DIR__ . '/assets/app/alerts.php';
require_once __DIR__ . '/assets/app/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - ClearMyRide</title>
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
                            <h1 class="h3 mb-2">Verify Your Email</h1>
                            <p class="text-muted">We sent a 6-digit code to <strong><?php echo htmlspecialchars($email); ?></strong></p>
                        </div>

                        <?php show_flash(); ?>

                        <form id="verifyOtpForm" method="POST" action="assets/app/verify_otp.php">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                            
                            <div class="mb-4">
                                <label for="otp" class="form-label">Verification Code</label>
                                <input type="text" class="form-control" id="otp" name="otp" required 
                                       placeholder="Enter 6-digit code" maxlength="6" pattern="\d{6}" inputmode="numeric">
                                <small class="form-text text-muted">Check your inbox (and spam) for the code.</small>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">Verify Code</button>
                            </div>
                        </form>

                        <div class="text-center mt-4">
                            <p class="text-muted mb-2">Didn't receive the code?</p>
                            <a href="forgot_password.php" class="text-primary">Request new code</a>
                        </div>
                        
                        <div class="text-center mt-4">
                            <a href="login.php" class="text-muted">Back to login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ===== SHOW USER‑FRIENDLY MESSAGE (same as register) =====
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

        // ===== OTP VERIFICATION HANDLER =====
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('verifyOtpForm');
            if (form) {
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();

                    const otp = document.getElementById('otp').value;
                    if (!/^\d{6}$/.test(otp)) {
                        showMessage('Please enter a valid 6-digit code.', 'error');
                        return;
                    }

                    const formData = new FormData(this);
                    const submitBtn = this.querySelector('button[type="submit"]');
                    const originalText = submitBtn.innerHTML;

                    try {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Verifying...';

                        const response = await fetch(BASE_URL + '/assets/app/verify_otp.php', {
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
                            showMessage(result.message || 'Invalid verification code.', 'error');
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        }
                    } catch (error) {
                        console.error('OTP verification error:', error);
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