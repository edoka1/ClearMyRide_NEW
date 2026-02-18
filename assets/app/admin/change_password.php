<?php
// assets/app/admin/change_password.php
session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db_connect.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$adminName = htmlspecialchars($_SESSION['admin_username'] ?? 'Admin');
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
        $stmt->execute([':id' => $_SESSION['admin_id']]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($current, $row['password'])) {
            $error = 'Current password incorrect.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $u = $pdo->prepare("UPDATE admin_users SET password = :p WHERE id = :id");
            $u->execute([':p' => $hash, ':id' => $_SESSION['admin_id']]);
            $success = 'Password updated successfully.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password – ClearMyRide Admin</title>
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS (grid only, no rounding) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        /* ----- GLOBAL: ZERO ROUNDED CORNERS, INTER FONT ----- */
        * { border-radius: 0 !important; }
        body {
            background: #f9fbfd;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: #1e293b;
            font-size: 0.9375rem;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }

        /* ----- BLUE NAVBAR (identical to dashboard) ----- */
        .admin-nav {
            background: #0A57FF;
            border-bottom: none;
            padding: 0.5rem 0;
            box-shadow: 0 2px 6px rgba(10,87,255,0.2);
        }
        .admin-nav .navbar-brand {
            font-weight: 700;
            color: white;
            font-size: 1.15rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .admin-nav .navbar-brand i {
            color: white;
        }
        .admin-nav .btn-outline-secondary {
            border: 1px solid rgba(255,255,255,0.5);
            color: white;
            font-weight: 500;
            padding: 0.35rem 1rem;
            font-size: 0.8125rem;
            background: transparent;
        }
        .admin-nav .btn-outline-secondary:hover {
            background: rgba(255,255,255,0.15);
            border-color: white;
        }
        .admin-nav .text-white {
            color: rgba(255,255,255,0.85) !important;
        }

        /* ----- MAIN CONTAINER – TIGHTER SPACING ----- */
        .admin-container {
            max-width: 1440px;
            margin: 0 auto;
            padding: 1.5rem 1.5rem;
        }

        /* ----- CARD – SHARP, SUBTLE BORDER ----- */
        .card {
            background: white;
            border: 1px solid #edf2f7;
            box-shadow: 0 1px 2px rgba(0,0,0,0.01);
        }
        .card-header {
            background: white;
            border-bottom: 1px solid #edf2f7;
            padding: 1.25rem 1.5rem;
            font-weight: 600;
            font-size: 0.9375rem;
            color: #1e293b;
        }
        .card-body {
            padding: 1.5rem;
        }

        /* ----- BUTTONS – SHARP, CLEAN ----- */
        .btn {
            border-radius: 0;
            font-weight: 600;
            padding: 0.5rem 1.25rem;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-primary {
            background: #0A57FF;
            border: 1px solid #0A57FF;
            color: white;
        }
        .btn-primary:hover {
            background: #004ce5;
            border-color: #004ce5;
        }
        .btn-outline-primary {
            border: 1px solid #0A57FF;
            color: #0A57FF;
        }
        .btn-outline-primary:hover {
            background: #0A57FF;
            color: white;
        }
        .btn-outline-secondary {
            border: 1px solid #e2e8f0;
            color: #475569;
        }
        .btn-outline-secondary:hover {
            background: #f1f5f9;
            border-color: #cbd5e0;
        }

        /* ----- FORMS – NO ROUNDING ----- */
        .form-label {
            font-weight: 600;
            font-size: 0.875rem;
            color: #4a5568;
            margin-bottom: 0.375rem;
        }
        .form-control {
            border-radius: 0;
            border: 1px solid #e0e5ec;
            padding: 0.5rem 0.75rem;
            height: 42px;
            font-size: 0.875rem;
        }
        .form-control:focus {
            border-color: #0A57FF;
            box-shadow: none;
        }
        .input-group-text {
            border-radius: 0;
            border: 1px solid #e0e5ec;
            background: white;
        }

        /* ----- ALERTS – FLAT, NO ROUNDING ----- */
        .alert {
            border-radius: 0;
            border: none;
            border-left: 4px solid;
            padding: 1rem;
            font-size: 0.875rem;
        }
        .alert-success {
            background: #f0fdf4;
            border-left-color: #10b981;
            color: #166534;
        }
        .alert-danger {
            background: #fff5f5;
            border-left-color: #e53e3e;
            color: #c53030;
        }

        /* ----- SECURITY TIPS – CLEAN LIST ----- */
        .security-tip {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0;
            color: #4a5568;
            font-size: 0.8125rem;
        }
        .security-tip i {
            color: #10b981;
            width: 16px;
        }
    </style>
</head>
<body>
    <!-- BLUE NAVBAR (exactly like dashboard) -->
    <nav class="admin-nav">
        <div class="admin-container d-flex justify-content-between align-items-center w-100">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-car"></i>
                <span>ClearMyRide Admin</span>
            </a>
            <div class="d-flex align-items-center gap-2">
                <span class="d-none d-md-inline me-1" style="font-size:0.8125rem; color:white;">
                    <i class="fas fa-user-circle me-1"></i> <?php echo $adminName; ?>
                </span>
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                </a>
                <a href="logout.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-sign-out-alt me-1"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <main class="admin-container">
        <!-- PAGE TITLE – MINIMAL -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 600; color: #0f172a; margin-bottom: 0.1rem;">
                    <i class="fas fa-key me-2" style="color: #0A57FF;"></i> Change Password
                </h1>
                <p style="font-size: 0.8125rem; color: #64748b; margin-bottom: 0;">
                    Update your account security credentials
                </p>
            </div>
            <a href="dashboard.php" class="btn btn-outline-secondary px-4">
                <i class="fas fa-arrow-left me-2"></i> Dashboard
            </a>
        </div>

        <!-- CENTERED CARD -->
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-shield-alt me-2" style="color: #0A57FF;"></i> 
                        Password Settings
                    </div>
                    <div class="card-body">
                        <!-- SUCCESS / ERROR MESSAGES -->
                        <?php if ($error): ?>
                            <div class="alert alert-danger d-flex align-items-center" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <div><?php echo htmlspecialchars($error); ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success d-flex align-items-center" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <div><?php echo htmlspecialchars($success); ?></div>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="needs-validation" novalidate>
                            <!-- CURRENT PASSWORD -->
                            <div class="mb-4">
                                <label for="current" class="form-label fw-semibold">Current Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="fas fa-lock text-muted"></i>
                                    </span>
                                    <input type="password" 
                                           name="current" 
                                           id="current"
                                           class="form-control border-start-0" 
                                           placeholder="Enter current password" 
                                           required>
                                </div>
                            </div>

                            <!-- NEW PASSWORD -->
                            <div class="mb-4">
                                <label for="new" class="form-label fw-semibold">New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="fas fa-key text-muted"></i>
                                    </span>
                                    <input type="password" 
                                           name="new" 
                                           id="new"
                                           class="form-control border-start-0" 
                                           placeholder="Enter new password" 
                                           required>
                                </div>
                                <div class="form-text text-muted mt-1 small">
                                    <i class="fas fa-info-circle me-1" style="color: #0A57FF;"></i> 
                                    Use at least 8 characters with mixed case, numbers, and symbols.
                                </div>
                            </div>

                            <!-- CONFIRM PASSWORD -->
                            <div class="mb-4">
                                <label for="confirm" class="form-label fw-semibold">Confirm New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="fas fa-key-fill text-muted"></i>
                                    </span>
                                    <input type="password" 
                                           name="confirm" 
                                           id="confirm"
                                           class="form-control border-start-0" 
                                           placeholder="Confirm new password" 
                                           required>
                                </div>
                            </div>

                            <!-- SUBMIT BUTTON -->
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-shield-check me-2"></i> Update Password
                                </button>
                            </div>
                        </form>

                        <!-- SECURITY TIPS (styled as clean list) -->
                        <hr class="my-4" style="border-top:1px solid #edf2f7;">
                        <h6 class="fw-semibold mb-3" style="color: #1a202c;">
                            <i class="fas fa-shield-alt me-2" style="color: #0A57FF;"></i> 
                            Security Tips
                        </h6>
                        <div class="security-tip">
                            <i class="fas fa-check-circle"></i>
                            <span>Use at least 8 characters – the longer, the stronger.</span>
                        </div>
                        <div class="security-tip">
                            <i class="fas fa-check-circle"></i>
                            <span>Mix uppercase, lowercase, numbers, and symbols.</span>
                        </div>
                        <div class="security-tip">
                            <i class="fas fa-check-circle"></i>
                            <span>Avoid using personal information or common words.</span>
                        </div>
                        <div class="security-tip">
                            <i class="fas fa-check-circle"></i>
                            <span>Never share your password with anyone.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS (for validation styling) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Form validation and password matching (inline) -->
    <script>
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

            // Password confirmation match validation
            const newPassword = document.getElementById('new');
            const confirmPassword = document.getElementById('confirm');

            function validatePasswordMatch() {
                if (newPassword.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity("Passwords don't match");
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }

            newPassword.addEventListener('change', validatePasswordMatch);
            confirmPassword.addEventListener('keyup', validatePasswordMatch);
        })();
    </script>
</body>
</html>