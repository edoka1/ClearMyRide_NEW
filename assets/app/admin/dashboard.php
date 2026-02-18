<?php
// admin/dashboard.php
session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db_connect.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$adminName = htmlspecialchars($_SESSION['admin_username'] ?? 'Admin');

// ---------- CORE METRICS (using customer_requests.status) ----------
// Total requests per type
$totalVehicle = (int)$pdo->query("
    SELECT COUNT(*) FROM vehicle_requests vr
    JOIN customer_requests cr ON vr.customer_request_id = cr.id
")->fetchColumn();

$totalLicense  = (int)$pdo->query("
    SELECT COUNT(*) FROM license_requests lr
    JOIN customer_requests cr ON lr.customer_request_id = cr.id
")->fetchColumn();

// Pending (status = 'Pending')
$pendingVehicle = (int)$pdo->query("
    SELECT COUNT(*) FROM vehicle_requests vr
    JOIN customer_requests cr ON vr.customer_request_id = cr.id
    WHERE cr.status = 'Pending'
")->fetchColumn();

$pendingLicense = (int)$pdo->query("
    SELECT COUNT(*) FROM license_requests lr
    JOIN customer_requests cr ON lr.customer_request_id = cr.id
    WHERE cr.status = 'Pending'
")->fetchColumn();

$pendingReview = $pendingVehicle + $pendingLicense;

// Under Review
$underReviewVehicle = (int)$pdo->query("
    SELECT COUNT(*) FROM vehicle_requests vr
    JOIN customer_requests cr ON vr.customer_request_id = cr.id
    WHERE cr.status = 'Under Review'
")->fetchColumn();

$underReviewLicense = (int)$pdo->query("
    SELECT COUNT(*) FROM license_requests lr
    JOIN customer_requests cr ON lr.customer_request_id = cr.id
    WHERE cr.status = 'Under Review'
")->fetchColumn();

$underReviewTotal = $underReviewVehicle + $underReviewLicense;

// Awaiting Payment
$awaitingPaymentVehicle = (int)$pdo->query("
    SELECT COUNT(*) FROM vehicle_requests vr
    JOIN customer_requests cr ON vr.customer_request_id = cr.id
    WHERE cr.status = 'Awaiting Payment'
")->fetchColumn();

$awaitingPaymentLicense = (int)$pdo->query("
    SELECT COUNT(*) FROM license_requests lr
    JOIN customer_requests cr ON lr.customer_request_id = cr.id
    WHERE cr.status = 'Awaiting Payment'
")->fetchColumn();

$awaitingPaymentTotal = $awaitingPaymentVehicle + $awaitingPaymentLicense;

// Processing
$processingVehicle = (int)$pdo->query("
    SELECT COUNT(*) FROM vehicle_requests vr
    JOIN customer_requests cr ON vr.customer_request_id = cr.id
    WHERE cr.status = 'Processing'
")->fetchColumn();

$processingLicense = (int)$pdo->query("
    SELECT COUNT(*) FROM license_requests lr
    JOIN customer_requests cr ON lr.customer_request_id = cr.id
    WHERE cr.status = 'Processing'
")->fetchColumn();

$processingTotal = $processingVehicle + $processingLicense;

// Completed
$completedVehicle = (int)$pdo->query("
    SELECT COUNT(*) FROM vehicle_requests vr
    JOIN customer_requests cr ON vr.customer_request_id = cr.id
    WHERE cr.status = 'Completed'
")->fetchColumn();

$completedLicense = (int)$pdo->query("
    SELECT COUNT(*) FROM license_requests lr
    JOIN customer_requests cr ON lr.customer_request_id = cr.id
    WHERE cr.status = 'Completed'
")->fetchColumn();

$completedTotal = $completedVehicle + $completedLicense;

// Cancelled
$cancelledVehicle = (int)$pdo->query("
    SELECT COUNT(*) FROM vehicle_requests vr
    JOIN customer_requests cr ON vr.customer_request_id = cr.id
    WHERE cr.status = 'Cancelled'
")->fetchColumn();

$cancelledLicense = (int)$pdo->query("
    SELECT COUNT(*) FROM license_requests lr
    JOIN customer_requests cr ON lr.customer_request_id = cr.id
    WHERE cr.status = 'Cancelled'
")->fetchColumn();

$cancelledTotal = $cancelledVehicle + $cancelledLicense;

// Refund Requested
$refundRequestedVehicle = (int)$pdo->query("
    SELECT COUNT(*) FROM vehicle_requests vr
    JOIN customer_requests cr ON vr.customer_request_id = cr.id
    WHERE cr.status = 'Refund Requested'
")->fetchColumn();

$refundRequestedLicense = (int)$pdo->query("
    SELECT COUNT(*) FROM license_requests lr
    JOIN customer_requests cr ON lr.customer_request_id = cr.id
    WHERE cr.status = 'Refund Requested'
")->fetchColumn();

$refundRequestedTotal = $refundRequestedVehicle + $refundRequestedLicense;

// Refunded
$refundedVehicle = (int)$pdo->query("
    SELECT COUNT(*) FROM vehicle_requests vr
    JOIN customer_requests cr ON vr.customer_request_id = cr.id
    WHERE cr.status = 'Refunded'
")->fetchColumn();

$refundedLicense = (int)$pdo->query("
    SELECT COUNT(*) FROM license_requests lr
    JOIN customer_requests cr ON lr.customer_request_id = cr.id
    WHERE cr.status = 'Refunded'
")->fetchColumn();

$refundedTotal = $refundedVehicle + $refundedLicense;

// Payment proof status breakdown
$proofPending = (int)$pdo->query("SELECT COUNT(*) FROM payment_proofs WHERE status = 'pending'")->fetchColumn();
$proofConfirmed = (int)$pdo->query("SELECT COUNT(*) FROM payment_proofs WHERE status = 'confirmed'")->fetchColumn();
$proofRejected = (int)$pdo->query("SELECT COUNT(*) FROM payment_proofs WHERE status = 'rejected'")->fetchColumn();

// Invoice status breakdown
$invoicesPaid = (int)$pdo->query("SELECT COUNT(*) FROM invoices WHERE status = 'paid'")->fetchColumn();
$invoicesSent  = (int)$pdo->query("SELECT COUNT(*) FROM invoices WHERE status = 'sent'")->fetchColumn();
$invoicesDraft = (int)$pdo->query("SELECT COUNT(*) FROM invoices WHERE status = 'draft'")->fetchColumn();

// ---------- CHART 1: Requests by Status (from customer_requests) ----------
$statusData = [
    'Pending'           => 0,
    'Under Review'      => 0,
    'Awaiting Payment'  => 0,
    'Processing'        => 0,
    'Completed'         => 0,
    'Cancelled'         => 0,
    'Refund Requested'  => 0,
    'Refunded'          => 0
];
$stmt = $pdo->query("SELECT status, COUNT(*) as count FROM customer_requests GROUP BY status");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (isset($statusData[$row['status']])) $statusData[$row['status']] = (int)$row['count'];
}
$statusLabels = array_keys($statusData);
$statusCounts = array_values($statusData);

// 🎨 NEW: Semantic colors matching badge styles
$statusColors = [
    '#f59e0b', // Pending (amber)
    '#3b82f6', // Under Review (blue)
    '#f97316', // Awaiting Payment (orange)
    '#0a5cff', // Processing (bright blue)
    '#10b981', // Completed (green)
    '#ef4444', // Cancelled (red)
    '#f43f5e', // Refund Requested (pink-red)
    '#6b7280'  // Refunded (gray)
];

// ---------- CHART 2: Monthly Activity (last 6 months) ----------
$monthlyData = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthlyData[$month] = 0;
}
$stmt = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count
    FROM customer_requests
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY month ASC
");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (isset($monthlyData[$row['month']])) $monthlyData[$row['month']] = (int)$row['count'];
}
$months = array_keys($monthlyData);
$monthlyCounts = array_values($monthlyData);
$monthLabels = array_map(fn($m) => date('M', strtotime($m . '-01')), $months);

// ---------- CHART 3: Payment Proof Status ----------
$proofStatusLabels = ['Pending', 'Confirmed', 'Rejected'];
$proofStatusCounts = [$proofPending, $proofConfirmed, $proofRejected];

// ---------- CHART 4: Request Type Mix (Vehicle vs License) ----------
$typeMixLabels = ['Vehicle', 'License'];
$typeMixCounts = [$totalVehicle, $totalLicense];

// ---------- RECENT REQUESTS (8 latest) ----------
$recent = $pdo->query("
    (SELECT 
        'vehicle' as type,
        vr.id,
        cr.id as request_id,
        cr.status,
        vr.full_name,
        vr.plate as identifier,
        i.status as payment_status,
        cr.created_at
    FROM vehicle_requests vr
    JOIN customer_requests cr ON vr.customer_request_id = cr.id
    LEFT JOIN invoices i ON cr.invoice_id = i.id
    ORDER BY cr.created_at DESC LIMIT 5)
    UNION ALL
    (SELECT 
        'license' as type,
        lr.id,
        cr.id as request_id,
        cr.status,
        lr.full_name,
        lr.license_number as identifier,
        i.status as payment_status,
        cr.created_at
    FROM license_requests lr
    JOIN customer_requests cr ON lr.customer_request_id = cr.id
    LEFT JOIN invoices i ON cr.invoice_id = i.id
    ORDER BY cr.created_at DESC LIMIT 5)
    ORDER BY created_at DESC
    LIMIT 8
")->fetchAll(PDO::FETCH_ASSOC);

// ---------- PENDING PAYMENTS (proofs awaiting confirmation) ----------
$pendingPaymentsList = $pdo->query("
    SELECT 
        pp.id,
        pp.file_name,
        pp.uploaded_at,
        i.invoice_number,
        cr.request_type,
        cr.id as request_id
    FROM payment_proofs pp
    JOIN invoices i ON pp.invoice_id = i.id
    JOIN customer_requests cr ON i.request_id = cr.id
    WHERE pp.status = 'pending'
    ORDER BY pp.uploaded_at DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard – ClearMyRide</title>
    <!-- Inter font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS (grid only, no rounding) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        /* ----- GLOBAL: NO ROUNDED CORNERS, CLEAN TYPOGRAPHY ----- */
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

        /* ----- COMPACT, BLUE NAVIGATION ----- */
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

        /* ----- METRIC CARDS – SMALLER, CLEANER ----- */
        .metric-card {
            background: white;
            border: 1px solid #edf2f7;
            padding: 1.25rem 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            height: 100%;
            transition: box-shadow 0.2s;
        }
        .metric-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.02);
            border-color: #cbd5e1;
        }
        .metric-icon {
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            color: #0a5cff;
            font-size: 1.25rem;
        }
        .metric-content h4 {
            font-size: 1.35rem;
            font-weight: 700;
            margin-bottom: 0.1rem;
            color: #0f172a;
            line-height: 1.2;
        }
        .metric-content span {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            font-weight: 600;
        }

        /* ----- CARDS – SHARP, SUBTLE BORDERS ----- */
        .card {
            background: white;
            border: 1px solid #edf2f7;
            box-shadow: 0 1px 2px rgba(0,0,0,0.01);
        }
        .card-header {
            background: white;
            border-bottom: 1px solid #edf2f7;
            padding: 1rem 1.25rem;
            font-weight: 600;
            font-size: 0.9375rem;
            color: #1e293b;
            display: flex;
            align-items: center;
        }
        .card-header i {
            color: #0a5cff;
            margin-right: 0.5rem;
            font-size: 0.9375rem;
        }
        .card-body {
            padding: 1.25rem;
        }

        /* ----- CHART CONTAINERS – FIXED HEIGHT ----- */
        .chart-container {
            position: relative;
            height: 200px;
            width: 100%;
        }

        /* ----- TABLES – DENSE, SCANNABLE ----- */
        .table {
            margin-bottom: 0;
            font-size: 0.8125rem;
        }
        .table th {
            border-top: none;
            border-bottom: 1px solid #e9edf2;
            color: #475569;
            font-weight: 600;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding: 0.85rem 1.25rem;
            background: #fcfdfe;
        }
        .table td {
            padding: 0.85rem 1.25rem;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
            color: #1e293b;
        }
        .table tbody tr:hover {
            background: #fafbfc;
        }

        /* ----- BADGES – FLAT, NO ROUNDING ----- */
        .badge {
            padding: 0.35rem 0.65rem;
            font-weight: 600;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border: 1px solid transparent;
        }
        .badge.bg-warning {
            background: #fffbeb !important;
            color: #b45309;
            border-color: #fcd34d;
        }
        .badge.bg-info {
            background: #eff6ff !important;
            color: #1e40af;
            border-color: #bfdbfe;
        }
        .badge.bg-success {
            background: #f0fdf4 !important;
            color: #166534;
            border-color: #bbf7d0;
        }
        .badge.bg-secondary {
            background: #f1f5f9 !important;
            color: #334155;
            border-color: #e2e8f0;
        }
        .badge.bg-danger {
            background: #fef2f2 !important;
            color: #991b1b;
            border-color: #fecaca;
        }

        /* ----- BUTTONS – TINY, SHARP ----- */
        .btn {
            border-radius: 0;
            font-weight: 600;
            padding: 0.35rem 0.85rem;
            font-size: 0.75rem;
        }
        .btn-primary {
            background: #0a5cff;
            border: 1px solid #0a5cff;
            color: white;
        }
        .btn-primary:hover {
            background: #004ce5;
            border-color: #004ce5;
        }
        .btn-outline-primary {
            border: 1px solid #0a5cff;
            color: #0a5cff;
        }
        .btn-outline-primary:hover {
            background: #0a5cff;
            color: white;
        }
        .btn-outline-secondary {
            border: 1px solid #e2e8f0;
            color: #475569;
        }

        /* ----- ACTION TILES – COMPACT ----- */
        .action-tile {
            display: block;
            text-decoration: none;
            border: 1px solid #edf2f7;
            padding: 1.25rem 0.75rem;
            text-align: center;
            transition: all 0.15s;
            background: white;
        }
        .action-tile:hover {
            border-color: #0a5cff;
            box-shadow: 0 4px 10px rgba(10,92,255,0.06);
        }
        .action-tile i {
            font-size: 1.5rem;
            color: #0a5cff;
            margin-bottom: 0.5rem;
        }
        .action-tile h6 {
            font-weight: 600;
            margin-bottom: 0.15rem;
            color: #0f172a;
            font-size: 0.8125rem;
        }
        .action-tile p {
            font-size: 0.6875rem;
            color: #64748b;
            margin-bottom: 0;
        }

        /* ----- SIDEBAR CARDS ----- */
        .profile-icon {
            width: 64px;
            height: 64px;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #0a5cff;
            border: 1px solid #e2e8f0;
            margin: 0 auto 1rem;
        }

        /* ----- STATUS INDICATORS ----- */
        .status-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            background: #10b981;
            border: 1px solid #d1fae5;
            margin-right: 0.35rem;
        }
    </style>
</head>
<body>
    <!-- NAVIGATION -->
    <nav class="admin-nav">
        <div class="admin-container d-flex justify-content-between align-items-center w-100">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-car" style="color: white;"></i>
                <span>ClearMyRide Admin</span>
            </a>
            <div class="d-flex align-items-center gap-2">
                <span class="d-none d-md-inline me-1" style="font-size:0.8125rem; color:white;">
                    <i class="fas fa-user-circle me-1"></i> <?php echo $adminName; ?>
                </span>
                <a href="change_password.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-key me-1"></i> Password
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
                <h1 style="font-size: 1.5rem; font-weight: 600; color: #0f172a; margin-bottom: 0.1rem;">Dashboard</h1>
                <p style="font-size: 0.8125rem; color: #64748b; margin-bottom: 0;">Welcome back, <?php echo $adminName; ?> · <?php echo date('l, F j'); ?></p>
            </div>
        </div>

        <!-- METRIC CARDS – KEY STATUSES -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-icon"><i class="fas fa-car"></i></div>
                    <div class="metric-content">
                        <h4><?php echo $totalVehicle; ?></h4>
                        <span>Vehicle</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-icon"><i class="fas fa-id-card"></i></div>
                    <div class="metric-content">
                        <h4><?php echo $totalLicense; ?></h4>
                        <span>License</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-icon" style="color: #b45309;"><i class="fas fa-clock"></i></div>
                    <div class="metric-content">
                        <h4><?php echo $pendingReview; ?></h4>
                        <span>Pending</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-icon" style="color: #b45309;"><i class="fas fa-hourglass-half"></i></div>
                    <div class="metric-content">
                        <h4><?php echo $proofPending; ?></h4>
                        <span>Proofs</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECOND ROW – MORE STATUS METRICS -->
        <div class="row g-3 mb-4">
            <div class="col-md-2">
                <div class="metric-card">
                    <div class="metric-icon" style="color: #0369a1;"><i class="fas fa-eye"></i></div>
                    <div class="metric-content">
                        <h4><?php echo $underReviewTotal; ?></h4>
                        <span>Under Review</span>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="metric-card">
                    <div class="metric-icon" style="color: #854d0e;"><i class="fas fa-file-invoice"></i></div>
                    <div class="metric-content">
                        <h4><?php echo $awaitingPaymentTotal; ?></h4>
                        <span>Awaiting Payment</span>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="metric-card">
                    <div class="metric-icon" style="color: #1e40af;"><i class="fas fa-cog"></i></div>
                    <div class="metric-content">
                        <h4><?php echo $processingTotal; ?></h4>
                        <span>Processing</span>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="metric-card">
                    <div class="metric-icon" style="color: #166534;"><i class="fas fa-check-circle"></i></div>
                    <div class="metric-content">
                        <h4><?php echo $completedTotal; ?></h4>
                        <span>Completed</span>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="metric-card">
                    <div class="metric-icon" style="color: #991b1b;"><i class="fas fa-undo-alt"></i></div>
                    <div class="metric-content">
                        <h4><?php echo $refundRequestedTotal; ?></h4>
                        <span>Refund Req</span>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="metric-card">
                    <div class="metric-icon" style="color: #6b21a8;"><i class="fas fa-check-double"></i></div>
                    <div class="metric-content">
                        <h4><?php echo $refundedTotal; ?></h4>
                        <span>Refunded</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- CHARTS ROW – 4 CHARTS -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-header"><i class="fas fa-chart-pie"></i> Requests by Status</div>
                    <div class="card-body p-2">
                        <div class="chart-container" style="height: 170px;">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-header"><i class="fas fa-chart-line"></i> Monthly Activity</div>
                    <div class="card-body p-2">
                        <div class="chart-container" style="height: 170px;">
                            <canvas id="monthlyChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-header"><i class="fas fa-credit-card"></i> Payment Proofs</div>
                    <div class="card-body p-2">
                        <div class="chart-container" style="height: 170px;">
                            <canvas id="proofChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100">
                    <div class="card-header"><i class="fas fa-cubes"></i> Request Mix</div>
                    <div class="card-body p-2">
                        <div class="chart-container" style="height: 170px;">
                            <canvas id="typeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- QUICK ACTIONS – 4 TILES -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <a href="view_vehicle.php" class="action-tile">
                    <i class="fas fa-car"></i>
                    <h6>Vehicle Requests</h6>
                    <p>Manage registrations</p>
                </a>
            </div>
            <div class="col-md-3">
                <a href="view_license.php" class="action-tile">
                    <i class="fas fa-id-card"></i>
                    <h6>License Requests</h6>
                    <p>Driver's licenses</p>
                </a>
            </div>
            <div class="col-md-3">
                <a href="invoice_list.php" class="action-tile">
                    <i class="fas fa-file-invoice"></i>
                    <h6>Invoices</h6>
                    <p>Generate & manage</p>
                </a>
            </div>
            <div class="col-md-3">
                <a href="change_password.php" class="action-tile">
                    <i class="fas fa-shield-alt"></i>
                    <h6>Security</h6>
                    <p>Update password</p>
                </a>
            </div>
        </div>

        <!-- TWO‑COLUMN LAYOUT: RECENT REQUESTS + PENDING PAYMENTS / ADMIN PROFILE -->
        <div class="row g-4">
            <!-- LEFT: RECENT REQUESTS -->
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div><i class="fas fa-clock"></i> Recent Requests</div>
                        <div class="small">
                            <a href="view_vehicle.php" class="text-decoration-none me-2" style="color:#0a5cff;">Vehicle</a>
                            <a href="view_license.php" class="text-decoration-none" style="color:#0a5cff;">License</a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recent)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                                <p class="text-muted small">No requests yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Customer</th>
                                            <th>Type</th>
                                            <th>Identifier</th>
                                            <th>Status</th>
                                            <th>Payment</th>
                                            <th>Date</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent as $r): ?>
                                        <tr>
                                            <td class="fw-medium"><?php echo htmlspecialchars($r['full_name']); ?></td>
                                            <td>
                                                <span class="badge <?php echo $r['type'] == 'vehicle' ? 'bg-info' : 'bg-secondary'; ?>">
                                                    <i class="fas fa-<?php echo $r['type'] == 'vehicle' ? 'car' : 'id-card'; ?> me-1"></i>
                                                    <?php echo ucfirst($r['type']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($r['identifier']); ?></td>
                                            <td>
                                                <?php
                                                $status = $r['status'] ?? 'Pending';
                                                $badgeClass = match($status) {
                                                    'Pending' => 'bg-warning',
                                                    'Under Review' => 'bg-info',
                                                    'Awaiting Payment' => 'bg-warning',
                                                    'Processing' => 'bg-primary',
                                                    'Completed' => 'bg-success',
                                                    'Cancelled' => 'bg-danger',
                                                    'Refund Requested' => 'bg-danger',
                                                    'Refunded' => 'bg-secondary',
                                                    default => 'bg-secondary'
                                                };
                                                ?>
                                                <span class="badge <?php echo $badgeClass; ?>"><?php echo $status; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo ($r['payment_status'] ?? 'sent') == 'paid' ? 'bg-success' : 'bg-warning'; ?>">
                                                    <?php echo ($r['payment_status'] ?? 'sent') == 'paid' ? 'Paid' : 'Pending'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j', strtotime($r['created_at'])); ?></td>
                                            <td>
                                                <a href="<?php echo $r['type'] == 'vehicle' ? 'view_vehicle.php?id=' . $r['id'] : 'view_license.php?id=' . $r['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!-- RIGHT: PENDING PAYMENTS + ADMIN PROFILE -->
            <div class="col-lg-5">
                <!-- Pending Payments Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-hourglass-half"></i> Pending Payments
                        <?php if ($proofPending > 0): ?>
                            <span class="badge bg-warning ms-2"><?php echo $proofPending; ?> awaiting</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($pendingPaymentsList)): ?>
                            <p class="text-muted small p-3 mb-0">No payment proofs pending.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($pendingPaymentsList as $p): ?>
                                <div class="list-group-item px-3 py-2 d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-semibold small">Invoice <?php echo htmlspecialchars($p['invoice_number']); ?></div>
                                        <small class="text-muted"><?php echo ucfirst($p['request_type']); ?> #<?php echo $p['request_id']; ?> · <?php echo date('M j', strtotime($p['uploaded_at'])); ?></small>
                                    </div>
                                    <a href="view_<?php echo $p['request_type']; ?>.php?id=<?php echo $p['request_id']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-check"></i>
                                    </a>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Admin Profile + System Status -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-user-shield"></i> Admin & System
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="profile-icon" style="width:48px; height:48px; font-size:1.5rem;">
                                <i class="fas fa-user-cog"></i>
                            </div>
                            <div class="ms-3">
                                <h6 class="fw-semibold mb-0"><?php echo $adminName; ?></h6>
                                <small class="text-muted">Administrator</small>
                            </div>
                            <div class="ms-auto">
                                <a href="change_password.php" class="btn btn-sm btn-outline-secondary" title="Change password">
                                    <i class="fas fa-key"></i>
                                </a>
                                <a href="logout.php" class="btn btn-sm btn-outline-secondary" title="Logout">
                                    <i class="fas fa-sign-out-alt"></i>
                                </a>
                            </div>
                        </div>
                        <hr class="my-2" style="border-top:1px solid #edf2f7;">
                        <div class="d-flex justify-content-between align-items-center small">
                            <span class="text-muted">Database</span>
                            <span><span class="status-dot"></span> Online</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center small mt-2">
                            <span class="text-muted">Backup</span>
                            <span><?php echo date('M j, g:i A', strtotime('-1 day')); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center small mt-2">
                            <span class="text-muted">PHP</span>
                            <span><?php echo phpversion(); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- CHART INITIALIZATION -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Status Donut – with new semantic colors
            const ctx1 = document.getElementById('statusChart')?.getContext('2d');
            if (ctx1) {
                new Chart(ctx1, {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode($statusLabels); ?>,
                        datasets: [{
                            data: <?php echo json_encode($statusCounts); ?>,
                            // ✅ Now uses the color array defined in PHP
                            backgroundColor: <?php echo json_encode($statusColors); ?>,
                            borderWidth: 0
                        }]
                    },
                    options: {
                        cutout: '70%',
                        plugins: { legend: { display: false } },
                        maintainAspectRatio: false
                    }
                });
            }

            // Monthly Bar
            const ctx2 = document.getElementById('monthlyChart')?.getContext('2d');
            if (ctx2) {
                new Chart(ctx2, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($monthLabels); ?>,
                        datasets: [{
                            label: 'Requests',
                            data: <?php echo json_encode($monthlyCounts); ?>,
                            backgroundColor: '#0a5cff',
                            borderWidth: 0,
                            borderRadius: 0
                        }]
                    },
                    options: {
                        scales: {
                            y: { beginAtZero: true, grid: { color: '#e9edf2' }, border: { display: false }, ticks: { stepSize: 1, font: { size: 10 } } },
                            x: { grid: { display: false }, ticks: { font: { size: 10 } } }
                        },
                        plugins: { legend: { display: false } },
                        maintainAspectRatio: false
                    }
                });
            }

            // Payment Proof Status
            const ctx3 = document.getElementById('proofChart')?.getContext('2d');
            if (ctx3) {
                new Chart(ctx3, {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode($proofStatusLabels); ?>,
                        datasets: [{
                            data: <?php echo json_encode($proofStatusCounts); ?>,
                            backgroundColor: ['#f59e0b', '#10b981', '#ef4444'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        cutout: '70%',
                        plugins: { legend: { display: false } },
                        maintainAspectRatio: false
                    }
                });
            }

            // Request Type Mix
            const ctx4 = document.getElementById('typeChart')?.getContext('2d');
            if (ctx4) {
                new Chart(ctx4, {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode($typeMixLabels); ?>,
                        datasets: [{
                            data: <?php echo json_encode($typeMixCounts); ?>,
                            backgroundColor: ['#3b82f6', '#94a3b8'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        cutout: '70%',
                        plugins: { legend: { display: false } },
                        maintainAspectRatio: false
                    }
                });
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>