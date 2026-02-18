<?php
session_start();
require_once __DIR__ . '/assets/app/Auth.php';
require_once __DIR__ . '/assets/app/db_connect.php';
require_once __DIR__ . '/assets/app/alerts.php';
require_once __DIR__ . '/assets/app/config.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: /?redirect=profile');
    exit;
}
$user = $auth->getCurrentUser();
$userId = $user['id'];

// ---------- CSRF TOKEN ----------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ---------- HANDLE PROFILE UPDATE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Verify CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid security token.'];
        header('Location: profile.php');
        exit;
    }

    $data = [
        'full_name' => trim($_POST['full_name'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'date_of_birth' => $_POST['date_of_birth'] ?? null
    ];

    // Basic validation
    if (empty($data['full_name'])) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Full name is required.'];
    } else {
        $result = $auth->updateProfile($userId, $data);
        if ($result) {
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Profile updated successfully.'];
            // Refresh user data
            $user = $auth->getCurrentUser();
        } else {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Failed to update profile.'];
        }
    }
    header('Location: profile.php');
    exit;
}

// ---------- HANDLE PASSWORD CHANGE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    // Verify CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid security token.'];
        header('Location: profile.php');
        exit;
    }

    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_new_password'] ?? '';

    // Validation
    if (empty($current) || empty($new) || empty($confirm)) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'All password fields are required.'];
    } elseif ($new !== $confirm) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'New passwords do not match.'];
    } elseif (strlen($new) < 8) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Password must be at least 8 characters.'];
    } elseif (!preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Password must contain both letters and numbers.'];
    } else {
        $result = $auth->changePassword($userId, $current, $new);
       if ($result['success']) {
    $_SESSION['flash'] = [
        'type' => 'success',
        'message' => !empty($result['message']) ? $result['message'] : 'Password changed successfully.'
    ];
} else {
    $_SESSION['flash'] = [
        'type' => 'error',
        'message' => !empty($result['message']) ? $result['message'] : 'Failed to change password.'
    ];
}
    }
    header('Location: profile.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings – ClearMyRide</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <!-- Custom Styles -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        /* ----- SHARP, PROFESSIONAL, NO ROUNDED CORNERS ----- */
        * {
            border-radius: 0 !important;
        }

        body {
            background: #f7f9fc;
            font-family: 'Inter', sans-serif;
            padding-top: 80px;
            margin: 0;
        }

        /* ----- FIXED NAVIGATION ----- */
        .nav,
        header.nav,
        header[role="banner"] {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background: white;
            border-bottom: 1px solid #edf2f7;
            z-index: 1030;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
        }

        /* ----- DASHBOARD LAYOUT ----- */
        .dashboard-container {
            max-width: 1440px;
            margin: 0 auto;
            padding: 0 24px;
            padding-top: 0 !important;
        }

        /* ----- STICKY SIDEBAR – PERFECTLY FIXED ----- */
        .sidebar-sticky {
            position: sticky;
            top: 80px;
            height: calc(100vh - 80px);
            overflow-y: auto;
            align-self: flex-start;
            margin-bottom: 0 !important;
        }

        .sidebar-sticky .sidebar-card {
            margin-bottom: 0;
        }

        /* ----- BOOTSTRAP ROW FIXES ----- */
        .row {
            margin-left: 0;
            margin-right: 0;
            align-items: flex-start;
        }

        .row.gx-4 {
            margin-left: -0.5rem;
            margin-right: -0.5rem;
        }

        /* ----- SIDEBAR NAVIGATION ----- */
        .sidebar-card {
            border: none;
            background: white;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
        }

        .sidebar-nav .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0.75rem 1rem;
            color: #4a5568;
            text-decoration: none;
            border-left: 3px solid transparent;
            transition: all 0.1s;
            font-weight: 500;
        }

        .sidebar-nav .nav-item i {
            width: 18px;
            color: #718096;
        }

        .sidebar-nav .nav-item:hover {
            background: #f7fafc;
            border-left-color: #0A57FF;
        }

        .sidebar-nav .nav-item.active {
            background: #ebf8ff;
            border-left-color: #0A57FF;
            color: #0A57FF;
        }

        .sidebar-nav .nav-item.active i {
            color: #0A57FF;
        }

        .sidebar-nav .nav-item.logout {
            color: #e53e3e;
        }

        .sidebar-nav .nav-item.logout i {
            color: #e53e3e;
        }

        .sidebar-divider {
            height: 1px;
            background: #edf2f7;
            margin: 1rem 0;
        }

        /* ----- PROFILE SECTIONS ----- */
        .profile-card {
            background: white;
            border: none;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
            margin-bottom: 1.5rem;
        }

        .profile-card-header {
            border-bottom: 1px solid #edf2f7;
            padding: 1.25rem 1.5rem;
            background: white;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .profile-card-header i {
            color: #0A57FF;
            font-size: 1.25rem;
        }

        .profile-card-header h5 {
            margin-bottom: 0;
            font-weight: 600;
            color: #1a202c;
        }

        .profile-card-body {
            padding: 1.5rem;
        }

        /* ----- FORMS ----- */
        .form-label {
            font-weight: 600;
            font-size: 0.875rem;
            color: #4a5568;
            margin-bottom: 0.375rem;
        }

        .form-control,
        .form-select {
            border-radius: 0;
            border: 1px solid #e0e5ec;
            padding: 0.625rem 0.875rem;
            font-size: 0.9375rem;
            transition: border-color 0.15s ease;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #0A57FF;
            box-shadow: none;
        }

        .form-control:disabled {
            background: #f7fafc;
            color: #718096;
        }

        .text-muted.small {
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }

        /* ----- BUTTONS ----- */
        .btn {
            border-radius: 0;
            font-weight: 600;
            padding: 0.625rem 1.5rem;
            font-size: 0.875rem;
        }

        .btn-primary {
            background: #0A57FF;
            border: 1px solid #0A57FF;
        }

        .btn-primary:hover {
            background: #0845cc;
            border-color: #0845cc;
        }

        .btn-outline-primary {
            border: 1px solid #0A57FF;
            color: #0A57FF;
        }

        .btn-outline-primary:hover {
            background: #0A57FF;
            color: white;
        }

        /* ----- PAGE HEADER ----- */
        .page-header {
            background: white;
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
        }

        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 0.25rem;
        }

        .page-header p {
            color: #718096;
            margin-bottom: 0;
        }
    </style>
</head>

<body>
    <?php include 'partials/nav.php'; ?>

    <main class="dashboard-container py-0">
        <?php show_flash(); ?>

        <div class="row gx-4">
            <!-- ===== STICKY SIDEBAR ===== -->
            <div class="col-lg-3 sidebar-sticky">
                <div class="sidebar-card">
                    <div class="card-body p-4">
                        <div class="user-info text-center mb-4">
                            <div class="avatar mb-3">
                                <i class="fas fa-user-circle fa-4x" style="color: #0A57FF;"></i>
                            </div>
                            <h5 class="mb-1 fw-semibold"><?php echo htmlspecialchars($user['full_name']); ?></h5>
                            <p class="text-muted small"><?php echo htmlspecialchars($user['email']); ?></p>
                        </div>
                        <nav class="sidebar-nav">
                            <a href="dashboard.php" class="nav-item">
                                <i class="fas fa-chart-pie"></i> Dashboard
                            </a>
                             <a href="<?php echo base_url('new-request.php'); ?>" class="nav-item">
                                <i class="fas fa-plus-circle"></i> New Request
                            </a>
                            <a href="vehicle-requests.php" class="nav-item">
                                <i class="fas fa-car"></i> Vehicle Requests
                            </a>
                            <a href="license-requests.php" class="nav-item">
                                <i class="fas fa-id-card"></i> License Requests
                            </a>
                            <a href="profile.php" class="nav-item active">
                                <i class="fas fa-user-cog"></i> Profile
                            </a>
                            <div class="sidebar-divider"></div>
                            <a href="assets/app/logout.php" class="nav-item logout" onclick="return confirm('Logout?')">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </nav>
                    </div>
                </div>
                <!-- Support widget -->
                <div class="p-3 mt-3" style="background: white; border: 1px solid #edf2f7;">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-headset fs-4" style="color: #0A57FF;"></i>
                        <div class="ms-3">
                            <h6 class="mb-0 fw-semibold">Need help?</h6>
                            <small class="text-muted">support@clearmyride.com</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== MAIN CONTENT ===== -->
            <div class="col-lg-9">
                <!-- Page Header -->
                <div class="page-header">
                    <h1><i class="fas fa-user-cog me-3" style="color: #0A57FF;"></i> Profile Settings</h1>
                    <p class="text-muted mb-0">Manage your personal information and account security.</p>
                </div>

                <!-- Personal Information Card -->
                <div class="profile-card">
                    <div class="profile-card-header">
                        <i class="fas fa-user"></i>
                        <h5>Personal Information</h5>
                    </div>
                    <div class="profile-card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" name="full_name"
                                        value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" class="form-control"
                                        value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                                    <small class="text-muted small">Email cannot be changed.</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" name="phone"
                                        value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                        placeholder="(410) 555-1234">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control" name="date_of_birth"
                                        value="<?php echo htmlspecialchars($user['date_of_birth'] ?? ''); ?>">
                                </div>
                                <div class="col-12 text-end">
                                    <button type="submit" name="update_profile" class="btn btn-primary px-5">
                                        <i class="fas fa-save me-2"></i>Update Profile
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Change Password Card -->
                <div class="profile-card">
                    <div class="profile-card-header">
                        <i class="fas fa-lock"></i>
                        <h5>Change Password</h5>
                    </div>
                    <div class="profile-card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label">Current Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="current_password" required>
                                        <button class="btn btn-outline-secondary toggle-password" type="button">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6"></div>
                                <div class="col-md-6">
                                    <label class="form-label">New Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="new_password" required>
                                        <button class="btn btn-outline-secondary toggle-password" type="button">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted small">Minimum 8 characters, letters & numbers</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Confirm New Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" name="confirm_new_password" required>
                                        <button class="btn btn-outline-secondary toggle-password" type="button">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-12 text-end">
                                    <button type="submit" name="change_password" class="btn btn-primary px-5">
                                        <i class="fas fa-key me-2"></i>Change Password
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Account Information Card (optional) -->
                <div class="profile-card">
                    <div class="profile-card-header">
                        <i class="fas fa-info-circle"></i>
                        <h5>Account Information</h5>
                    </div>
                    <div class="profile-card-body">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label">Member Since</label>
                                <p class="form-control-static fw-medium">
                                    <?php echo date('F j, Y', strtotime($user['created_at'] ?? date('Y-m-d'))); ?>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Account Status</label>
                                <p class="form-control-static">
                                    <span class="badge bg-success px-3 py-2">
                                        <i class="fas fa-check-circle me-1"></i> Active
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Password visibility toggle
            const togglePasswordButtons = document.querySelectorAll('.toggle-password');

            togglePasswordButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const passwordField = this.closest('.input-group').querySelector('input');
                    const icon = this.querySelector('i');

                    if (passwordField.type === 'password') {
                        passwordField.type = 'text';
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        passwordField.type = 'password';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                });
            });
        });
    </script>
</body>

</html>