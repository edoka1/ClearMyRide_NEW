<?php
session_start();
require_once __DIR__ . '/assets/app/Auth.php';
require_once __DIR__ . '/assets/app/alerts.php';
require_once __DIR__ . '/assets/app/config.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: /?redirect=new-request');
    exit;
}
$user = $auth->getCurrentUser();

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Request – ClearMyRide</title>
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
        /* ----- SHARP, NO ROUNDED CORNERS ----- */
        * { border-radius: 0 !important; }
        
        body { 
            background: #f7f9fc; 
            font-family: 'Inter', sans-serif; 
            padding-top: 80px; /* Space for fixed nav */
            margin: 0;
        }

        /* ----- DASHBOARD LAYOUT ----- */
        .dashboard-container {
            max-width: 1440px;
            margin: 0 auto;
            padding: 0 24px;
            padding-top: 0 !important;
            position: relative;
        }

        /* ----- STICKY SIDEBAR ----- */
        .sidebar-sticky {
            position: sticky;
            top: 80px;
            height: calc(100vh - 80px);
            overflow-y: auto;
            align-self: flex-start;
            margin-bottom: 0 !important;
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

        /* ----- SIDEBAR ----- */
        .sidebar-card {
            border: none;
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }

        /* ----- PAGE HEADER ----- */
        .page-header {
            background: white;
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            display: flex;
            justify-content: space-between;
            align-items: center;
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

        /* ----- SERVICE TABS – FIXED POSITIONING ----- */
   /* ----- SERVICE TABS – FORCED LEFT ALIGNMENT, TIGHT SPACING ----- */
.service-tabs {
    background: white;
    border-bottom: 1px solid #edf2f7;
    padding: 0 1.5rem;
    margin-bottom: 2rem;
    margin-top: 0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    display: flex;
    gap: 0.25rem !important;      /* Tiny gap – exactly what you want */
    justify-content: flex-start;  /* Force left alignment */
    align-items: center;
    flex-wrap: nowrap;           /* Prevent wrapping */
    position: static !important;
    clear: both;
    z-index: 1;
}

/* Remove all Bootstrap default spacing on nav items */
.service-tabs .nav-item {
    margin: 0 !important;
    padding: 0 !important;
}

/* Style the tab buttons – no extra margins, snug fit */
.service-tabs .nav-link {
    padding: 1rem 0.5rem !important;  /* Reduced horizontal padding */
    margin: 0 !important;
    font-weight: 600;
    color: #4a5568;
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    border-radius: 0;
    transition: all 0.1s;
    display: flex;
    align-items: center;
    gap: 0.75rem !important;
    white-space: nowrap;    
         /* Keep text on one line */
}

.service-tabs {
    font-weight: 600;
    color: #4a5568;
    background: transparent;
    border-bottom: 1px solid #edf2f7;
    padding: 0 1.5rem;
    margin-bottom: 2rem;
    margin-top: 0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.02);
    display: flex;
    gap: 0.75rem !important;      /* Increased from 0.25rem – perfect little gap */
    justify-content: flex-start;
    align-items: center;
    flex-wrap: nowrap;
    position: static !important;
    clear: both;
    z-index: 1;
}

.service-tabs .nav-link i {
    color: #718096;
}

.service-tabs .nav-link:hover {
    color: #1a202c;
    border-bottom-color: #cbd5e0;
}

.service-tabs .nav-link.active {
    color: #0A57FF;
    background: transparent;
    border-bottom-color: #0A57FF;
}

.service-tabs .nav-link.active i {
    color: #0A57FF;
}

        /* ----- FORM CARD ----- */
        .form-card {
            background: white;
            border: none;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            clear: both;
        }
        .form-card .card-body {
            padding: 2rem;
        }

        /* ----- FORM GRID ----- */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        .full-width {
            grid-column: span 2;
        }

        /* ----- FORM CONTROLS ----- */
        .form-label {
            font-weight: 600;
            font-size: 0.875rem;
            color: #4a5568;
            margin-bottom: 0.375rem;
        }
        .form-label.required:after {
            content: " *";
            color: #e53e3e;
            font-weight: 600;
        }
        .form-control, .form-select {
            border-radius: 0;
            border: 1px solid #e0e5ec;
            padding: 0.625rem 0.875rem;
            font-size: 0.9375rem;
            transition: border-color 0.15s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: #0A57FF;
            box-shadow: none;
        }
        .form-text {
            font-size: 0.75rem;
            color: #718096;
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

        /* ----- CONSENT CHECKBOX ----- */
        .form-check-input {
            border-radius: 0;
            border: 1px solid #e0e5ec;
        }
        .form-check-input:checked {
            background-color: #0A57FF;
            border-color: #0A57FF;
        }

        /* ----- RESPONSIVE ----- */
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            .full-width {
                grid-column: span 1;
            }
        }
         .sidebar-nav .nav-item.active {
            background: #ebf8ff;
            border-left-color: #0A57FF;
            color: #0A57FF;
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
                            <a href="<?php echo base_url('new-request.php'); ?>" class="nav-item active">
                                <i class="fas fa-file"></i> New Requests
                            </a>
                            <a href="vehicle-requests.php" class="nav-item">
                                <i class="fas fa-car"></i> Vehicle Requests
                            </a>
                            <a href="license-requests.php" class="nav-item">
                                <i class="fas fa-id-card"></i> License Requests
                            </a>
                            <a href="profile.php" class="nav-item">
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
                    <div>
                        <h1><i class="fas fa-plus-circle me-3" style="color: #0A57FF;"></i> Start a New Request</h1>
                        <p class="text-muted mb-0">Choose the service you need and complete the form below</p>
                    </div>
                    <a href="dashboard.php" class="btn btn-outline-secondary px-4">
                        <i class="fas fa-arrow-left me-2"></i>Dashboard
                    </a>
                </div>

                <!-- SERVICE TABS – CLEARLY ABOVE FORM, NOT PART OF TOP NAV -->
                <ul class="nav nav-pills service-tabs" id="serviceTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tab-vehicle" data-bs-toggle="pill" data-bs-target="#panel-vehicle" type="button" role="tab" aria-controls="panel-vehicle" aria-selected="true">
                            <i class="fas fa-car"></i> Vehicle Registration
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-license" data-bs-toggle="pill" data-bs-target="#panel-license" type="button" role="tab" aria-controls="panel-license" aria-selected="false">
                            <i class="fas fa-id-card"></i> Driver's License
                        </button>
                    </li>
                </ul>

                <!-- Form Card -->
                <div class="form-card">
                    <div class="card-body">
                        <div class="tab-content" id="intakeTabsContent">
                            <!-- VEHICLE FORM -->
                            <div class="tab-pane fade show active" id="panel-vehicle" role="tabpanel" aria-labelledby="tab-vehicle">
                                <form id="vehicle-form" method="POST" action="assets/app/submit_vehicle.php" enctype="multipart/form-data" novalidate>
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="source" value="dashboard">
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label for="v-fullname" class="form-label required">Full Name</label>
                                            <input type="text" class="form-control" id="v-fullname" name="fullName" required placeholder="Jane A. Doe">
                                        </div>
                                        <div class="form-group">
                                            <label for="v-email" class="form-label required">Email Address</label>
                                            <input type="email" class="form-control" id="v-email" name="email" required placeholder="jane@example.com" value="<?php echo htmlspecialchars($user['email']); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label for="v-phone" class="form-label required">Phone Number</label>
                                            <input type="tel" class="form-control" id="v-phone" name="phone" required placeholder="(410) 555-1234">
                                        </div>
                                        <div class="form-group">
                                            <label for="v-dob" class="form-label required">Date of Birth</label>
                                            <input type="date" class="form-control" id="v-dob" name="dob" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="v-plate" class="form-label required">Maryland Plate</label>
                                            <input type="text" class="form-control" id="v-plate" name="plate" required placeholder="ABC-1234">
                                        </div>
                                        <div class="form-group">
                                            <label for="v-vin" class="form-label">VIN (optional)</label>
                                            <input type="text" class="form-control" id="v-vin" name="vin" placeholder="17-character VIN">
                                        </div>
                                        <div class="form-group">
                                            <label for="v-exp" class="form-label">Reg Expiration (optional)</label>
                                            <input type="date" class="form-control" id="v-exp" name="regExp">
                                        </div>
                                        <div class="form-group">
                                            <label for="v-renew" class="form-label required">Renewal due</label>
                                            <select class="form-select" id="v-renew" name="renewWhen" required>
                                                <option value="">Select</option>
                                                <option value="within-month">Within a month</option>
                                                <option value="1-3">1-3 months</option>
                                                <option value="3-6">3-6 months</option>
                                                <option value="6-plus">6+ months</option>
                                            </select>
                                        </div>
                                        <div class="form-group full-width">
                                            <label class="form-label required">Outstanding tickets / flags?</label>
                                            <div class="d-flex gap-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="v-hasIssues" id="v-has-yes" value="yes" required>
                                                    <label class="form-check-label" for="v-has-yes">Yes</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="v-hasIssues" id="v-has-no" value="no" required>
                                                    <label class="form-check-label" for="v-has-no">No</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group full-width">
                                            <label class="form-label">Upload citations / letters (optional)</label>
                                            <input type="file" name="vehicleFiles[]" multiple class="form-control" accept=".pdf,image/*">
                                            <div class="form-text">PDF, JPG, PNG up to 10MB each</div>
                                        </div>
                                        <div class="form-group">
                                            <label for="v-delivery" class="form-label required">Delivery method</label>
                                            <select class="form-select" id="v-delivery" name="delivery" required>
                                                <option value="">Select</option>
                                                <option value="email">Email</option>
                                                <option value="mail">Mail</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="v-ref" class="form-label required">How did you hear about us?</label>
                                            <select class="form-select" id="v-ref" name="referral" required>
                                                <option value="">Select</option>
                                                <option value="instagram">Instagram</option>
                                                <option value="tiktok">TikTok</option>
                                                <option value="facebook">Facebook</option>
                                                <option value="peer">Friend/Peer</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                        <div class="form-group full-width">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="v-consent" name="consent" required>
                                                <label class="form-check-label" for="v-consent">
                                                    I consent to ClearMyRide accessing my MVA records.
                                                </label>
                                            </div>
                                        </div>
                                        <div class="form-group full-width text-end">
                                            <button type="submit" class="btn btn-primary px-5">
                                                <i class="fas fa-paper-plane me-2"></i>Submit Request
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            <!-- LICENSE FORM -->
                            <div class="tab-pane fade" id="panel-license" role="tabpanel" aria-labelledby="tab-license">
                                <form id="license-form" method="POST" action="assets/app/submit_license.php" enctype="multipart/form-data" novalidate>
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                     <input type="hidden" name="source" value="dashboard">
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label for="l-fullname" class="form-label required">Full Name</label>
                                            <input type="text" class="form-control" id="l-fullname" name="fullName" required placeholder="Jane A. Doe">
                                        </div>
                                        <div class="form-group">
                                            <label for="l-email" class="form-label required">Email Address</label>
                                            <input type="email" class="form-control" id="l-email" name="email" required placeholder="jane@example.com" value="<?php echo htmlspecialchars($user['email']); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label for="l-phone" class="form-label required">Phone Number</label>
                                            <input type="tel" class="form-control" id="l-phone" name="phone" required placeholder="(410) 555-1234">
                                        </div>
                                        <div class="form-group">
                                            <label for="l-dob" class="form-label required">Date of Birth</label>
                                            <input type="date" class="form-control" id="l-dob" name="dob" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="l-number" class="form-label required">License Number</label>
                                            <input type="text" class="form-control" id="l-number" name="licenseNumber" required placeholder="D12345678">
                                        </div>
                                        <div class="form-group">
                                            <label for="l-exp" class="form-label">Expiration Date (optional)</label>
                                            <input type="date" class="form-control" id="l-exp" name="licenseExp">
                                        </div>
                                        <div class="form-group full-width">
                                            <label class="form-label required">Unpaid tickets or MVA holds?</label>
                                            <div class="d-flex gap-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="l-hasIssues" id="l-has-yes" value="yes" required>
                                                    <label class="form-check-label" for="l-has-yes">Yes</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="l-hasIssues" id="l-has-no" value="no" required>
                                                    <label class="form-check-label" for="l-has-no">No</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group full-width">
                                            <label class="form-label">Upload MVA letters (optional)</label>
                                            <input type="file" name="licenseFiles[]" multiple class="form-control" accept=".pdf,image/*">
                                            <div class="form-text">PDF, JPG, PNG up to 10MB each</div>
                                        </div>
                                        <div class="form-group full-width">
                                            <label for="l-sign" class="form-label required">Signature (type full name)</label>
                                            <input type="text" class="form-control" id="l-sign" name="signature" required placeholder="Your full name">
                                        </div>
                                        <div class="form-group full-width">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="l-consent" name="consent" required>
                                                <label class="form-check-label" for="l-consent">
                                                    I consent to ClearMyRide accessing my driver record.
                                                </label>
                                            </div>
                                        </div>
                                        <div class="form-group full-width text-end">
                                            <button type="submit" class="btn btn-primary px-5">
                                                <i class="fas fa-paper-plane me-2"></i>Submit Request
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- BOOTSTRAP TABS INITIALIZATION -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Manually activate each tab trigger to ensure Bootstrap picks them up
            var triggerTabList = [].slice.call(document.querySelectorAll('#serviceTabs button[data-bs-toggle="pill"]'));
            triggerTabList.forEach(function(triggerEl) {
                var tabTrigger = new bootstrap.Tab(triggerEl);
                triggerEl.addEventListener('click', function(event) {
                    event.preventDefault();
                    tabTrigger.show();
                });
            });

            // Handle URL hash for direct tab access
            if (window.location.hash === '#license' || window.location.hash === '#panel-license') {
                var licenseTab = document.getElementById('tab-license');
                if (licenseTab) {
                    var tab = new bootstrap.Tab(licenseTab);
                    tab.show();
                }
            }
        });
    </script>
</body>
</html>