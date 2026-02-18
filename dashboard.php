<?php
// dashboard.php
session_start();
require_once __DIR__ . '/assets/app/Auth.php';
require_once __DIR__ . '/assets/app/db_connect.php';
require_once __DIR__ . '/assets/app/alerts.php';
require_once __DIR__ . '/assets/app/config.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: ' . base_url('index.php?redirect=dashboard'));
    exit;
}
$user = $auth->getCurrentUser();
$userId = $user['id'];

// ---------- STATS ----------
$stats = [
    'total'    => 0,
    'active'   => 0,
    'completed' => 0,
    'pending_payment' => 0
];
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN cr.status IN ('Pending','Under Review','Processing') THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN cr.status = 'Completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN i.status = 'sent' THEN 1 ELSE 0 END) as pending_payment
    FROM customer_requests cr
    LEFT JOIN invoices i ON cr.invoice_id = i.id
    WHERE cr.user_id = ?
");
$stmt->execute([$userId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    $stats = [
        'total'    => (int)$row['total'],
        'active'   => (int)$row['active'],
        'completed' => (int)$row['completed'],
        'pending_payment' => (int)$row['pending_payment']
    ];
}

// ---------- CHART DATA ----------
$statusData = [
    'Pending' => 0,
    'Under Review' => 0,
    'Awaiting Payment' => 0,
    'Processing' => 0,
    'Completed' => 0,
    'Cancelled' => 0,
    'Refund Requested' => 0,
    'Refunded' => 0
];
$stmt = $pdo->prepare("
    SELECT status, COUNT(*) as count
    FROM customer_requests
    WHERE user_id = ?
    GROUP BY status
");
$stmt->execute([$userId]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (array_key_exists($row['status'], $statusData)) {
        $statusData[$row['status']] = (int)$row['count'];
    }
}
$filteredStatusData = array_filter($statusData);
$statusLabels = array_keys($filteredStatusData);
$statusCounts = array_values($filteredStatusData);

// 🎨 SEMANTIC COLOR MAP (matches badge colors)
$statusColorMap = [
    'Pending'           => '#6b7280', // gray
    'Under Review'      => '#3b82f6', // blue
    'Awaiting Payment'  => '#f59e0b', // orange
    'Processing'        => '#0A57FF', // bright blue (primary)
    'Completed'         => '#10b981', // green
    'Cancelled'         => '#ef4444', // red
    'Refund Requested'  => '#ec4899', // pink
    'Refunded'          => '#8b5cf6'  // purple
];

// Build color array in the same order as $statusLabels
$statusColors = [];
foreach ($statusLabels as $status) {
    $statusColors[] = $statusColorMap[$status] ?? '#94a3b8'; // fallback gray
}

// Monthly activity (last 6 months)
$monthlyData = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthlyData[$month] = 0;
}
$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count
    FROM customer_requests
    WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY month ASC
");
$stmt->execute([$userId]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (isset($monthlyData[$row['month']])) {
        $monthlyData[$row['month']] = (int)$row['count'];
    }
}
$months = array_keys($monthlyData);
$monthlyCounts = array_values($monthlyData);
$monthLabels = array_map(function ($m) {
    return date('M', strtotime($m . '-01'));
}, $months);

// ---------- RECENT REQUESTS ----------
$stmt = $pdo->prepare("
    SELECT cr.*,
           vr.plate,
           lr.license_number,
           i.status as invoice_status
    FROM customer_requests cr
    LEFT JOIN vehicle_requests vr ON cr.id = vr.customer_request_id
    LEFT JOIN license_requests lr ON cr.id = lr.customer_request_id
    LEFT JOIN invoices i ON cr.invoice_id = i.id
    WHERE cr.user_id = ?
    ORDER BY cr.created_at DESC
    LIMIT 6
");
$stmt->execute([$userId]);
$recentRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard – ClearMyRide</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <!-- Custom Styles -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        /* ----- SHARP, NO ROUNDED CORNERS ----- */
        * {
            border-radius: 0 !important;
        }

        body {
            background: #f7f9fc;
            font-family: 'Inter', sans-serif;
            padding-top: 80px;
            margin: 0;
        }

        .dashboard-container {
            max-width: 1440px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* ----- FIXED NAVIGATION (your existing nav.php handles this) ----- */
        /* ----- SIDEBAR ----- */
        .sidebar-sticky {
            position: sticky;
            top: 80px;
            height: calc(100vh - 80px);
            overflow-y: auto;
            align-self: flex-start;
        }

        .sidebar-card {
            background: white;
            border: 1px solid #edf2f7;
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

        /* ----- WELCOME HEADER ----- */
        .welcome-header {
            background: white;
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .welcome-header h1 {
            font-size: 1.75rem;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 0.25rem;
        }

        .welcome-header p {
            color: #718096;
            margin-bottom: 0;
        }

        /* ----- STAT CARDS ----- */
        .stat-card {
            display: flex;
            align-items: center;
            padding: 1.5rem;
            background: white;
            border: 1px solid #edf2f7;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
            height: 100%;
        }

        .stat-icon {
            width: 52px;
            height: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f7fafc;
            color: #0A57FF;
            font-size: 1.5rem;
            margin-right: 1rem;
        }

        .stat-content h4 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: #1a202c;
        }

        .stat-content span {
            font-size: 0.75rem;
            color: #718096;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            font-weight: 600;
        }

        /* ----- CARDS ----- */
        .card {
            border: 1px solid #edf2f7;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
            background: white;
        }

        .card-header {
            background: white;
            border-bottom: 1px solid #edf2f7;
            padding: 1.25rem 1.5rem;
            font-weight: 600;
            color: #1a202c;
        }

        .card-header i {
            color: #0A57FF;
            margin-right: 0.5rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        .chart-container {
            position: relative;
            height: 200px;
            width: 100%;
        }

        /* ----- TABLES ----- */
        .table {
            margin-bottom: 0;
        }

        .table th {
            border-top: none;
            border-bottom: 1px solid #edf2f7;
            color: #4a5568;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 1rem 1.5rem;
            background: #fafcfc;
        }

        .table td {
            padding: 1rem 1.5rem;
            vertical-align: middle;
            border-bottom: 1px solid #f0f4f8;
            color: #2d3748;
        }

        .table-hover tbody tr:hover {
            background: #f7fafc;
        }

        /* ----- BADGES ----- */
        .badge {
            padding: 0.5rem 0.75rem;
            font-weight: 500;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border: 1px solid transparent;
        }

        .badge.bg-secondary {
            background: #edf2f7 !important;
            color: #1a202c;
            border-color: #e2e8f0;
        }

        .badge.bg-info {
            background: #ebf8ff !important;
            color: #0A57FF;
            border-color: #90cdf4;
        }

        .badge.bg-warning {
            background: #fffaf0 !important;
            color: #dd6b20;
            border-color: #fbd38d;
        }

        .badge.bg-success {
            background: #f0fff4 !important;
            color: #276749;
            border-color: #9ae6b4;
        }

        .badge.bg-danger {
            background: #fff5f5 !important;
            color: #c53030;
            border-color: #feb2b2;
        }

        .badge.bg-primary {
            background: #ebf8ff !important;
            color: #0A57FF;
            border-color: #90cdf4;
        }

        /* ----- BUTTONS ----- */
        .btn {
            border-radius: 0;
            font-weight: 600;
            padding: 0.5rem 1.25rem;
            font-size: 0.875rem;
        }

        .btn-primary {
            background: #0A57FF;
            border: 1px solid #0A57FF;
            color: white;
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

        .btn-outline-secondary {
            border: 1px solid #e0e5ec;
            color: #4a5568;
        }

        .btn-outline-secondary:hover {
            background: #edf2f7;
            border-color: #cbd5e0;
        }

        /* ----- STATUS GRID – CLEAN, COMPACT ----- */
        .status-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.85rem 1rem;
            margin-top: 0.5rem;
        }

        .status-item {
            display: flex;
            align-items: center;
            font-size: 0.8125rem;
        }

        .status-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            background: currentColor;
            border: 1px solid rgba(0, 0, 0, 0.05);
            margin-right: 0.5rem;
            flex-shrink: 0;
        }

        .status-name {
            color: #334155;
            font-weight: 500;
            letter-spacing: 0.01em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex: 1;
        }

        .status-count {
            margin-left: 0.5rem;
            padding: 0.2rem 0.5rem;
            font-size: 0.6875rem;
            font-weight: 600;
            border: 1px solid transparent;
            flex-shrink: 0;
        }

        /* Responsive: stack on mobile */
        @media (max-width: 768px) {
            .status-grid {
                grid-template-columns: 1fr;
                gap: 0.6rem;
                margin-top: 1.5rem;
            }

            .chart-container {
                margin-bottom: 1rem;
            }
        }

        /* ----- RESPONSIVE ----- */
        @media (max-width: 768px) {
            .dashboard-container {
                padding: 0 16px;
            }

            .welcome-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .stat-icon {
                width: 44px;
                height: 44px;
                font-size: 1.25rem;
            }

            .stat-content h4 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <?php include 'partials/nav.php'; ?>
    <main class="dashboard-container py-0">
        <?php show_flash(); ?>
        <div class="row gx-4">
            <!-- SIDEBAR -->
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
                            <a href="<?php echo base_url('dashboard.php'); ?>" class="nav-item active">
                                <i class="fas fa-chart-pie"></i> Dashboard
                            </a>
                            <a href="<?php echo base_url('new-request.php'); ?>" class="nav-item">
                                <i class="fas fa-plus-circle"></i> New Request
                            </a>
                            <a href="<?php echo base_url('vehicle-requests.php'); ?>" class="nav-item">
                                <i class="fas fa-car"></i> Vehicle Requests
                            </a>
                            <a href="<?php echo base_url('license-requests.php'); ?>" class="nav-item">
                                <i class="fas fa-id-card"></i> License Requests
                            </a>
                            <a href="<?php echo base_url('profile.php'); ?>" class="nav-item">
                                <i class="fas fa-user-cog"></i> Profile
                            </a>
                            <div class="sidebar-divider"></div>
                            <a href="<?php echo base_url('assets/app/logout.php'); ?>" class="nav-item logout" onclick="return confirm('Logout?')">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </nav>
                    </div>
                </div>
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

            <!-- MAIN CONTENT -->
            <div class="col-lg-9">
                <!-- Welcome Header -->
                <div class="welcome-header">
                    <div>
                        <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', $user['full_name'])[0]); ?> 👋</h1>
                        <p>Track your requests, upload documents, and manage payments.</p>
                    </div>
                    <a href="<?php echo base_url('new-request.php'); ?>" class="btn btn-primary px-4 py-2 fw-semibold">
                        <i class="fas fa-plus me-2"></i>New Request
                    </a>
                </div>

                <!-- STATS CARDS -->
                <div class="row g-4 mb-5">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="fas fa-file-alt fa-xl"></i></div>
                            <div class="stat-content">
                                <h4><?php echo $stats['total']; ?></h4><span>Total Requests</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="fas fa-spinner fa-xl"></i></div>
                            <div class="stat-content">
                                <h4><?php echo $stats['active']; ?></h4><span>In Progress</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="fas fa-check-circle fa-xl"></i></div>
                            <div class="stat-content">
                                <h4><?php echo $stats['completed']; ?></h4><span>Completed</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="fas fa-dollar-sign fa-xl"></i></div>
                            <div class="stat-content">
                                <h4><?php echo $stats['pending_payment']; ?></h4><span>Needs Payment</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CHARTS -->
                <div class="row g-4 mb-5">
                    <!--  REQUESTS BY STATUS – CHART ONLY -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <i class="fas fa-chart-pie"></i> Requests by Status
                            </div>
                            <div class="card-body d-flex align-items-center justify-content-center">
                                <div class="chart-container" style="height: 200px; width: 200px; margin: 0 auto;">
                                    <canvas id="statusChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Monthly Activity Card (unchanged) -->
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header"><i class="fas fa-chart-line"></i> Monthly Activity</div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="monthlyChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RECENT REQUESTS -->
                <div class="row mb-5">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div><i class="fas fa-clock"></i> Recent Requests</div>
                                <div>
                                    <a href="<?php echo base_url('vehicle-requests.php'); ?>" class="small text-primary me-3"><i class="fas fa-car me-1"></i>Vehicle</a>
                                    <a href="<?php echo base_url('license-requests.php'); ?>" class="small text-primary me-3"><i class="fas fa-id-card me-1"></i>License</a>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($recentRequests)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">You haven't submitted any requests yet.</p>
                                        <a href="<?php echo base_url('new-request.php'); ?>" class="btn btn-primary mt-2">
                                            <i class="fas fa-plus me-2"></i>Start your first request
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0 align-middle">
                                            <thead>
                                                <tr>
                                                    <th>Type</th>
                                                    <th>Identifier</th>
                                                    <th>Status</th>
                                                    <th>Payment</th>
                                                    <th>Date</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recentRequests as $req): ?>
                                                    <tr>
                                                        <td>
                                                            <span class="badge bg-<?php echo $req['request_type'] == 'vehicle' ? 'info' : 'secondary'; ?>">
                                                                <i class="fas fa-<?php echo $req['request_type'] == 'vehicle' ? 'car' : 'id-card'; ?> me-1"></i>
                                                                <?php echo ucfirst($req['request_type']); ?>
                                                            </span>
                                                        </td>
                                                        <td class="fw-medium">
                                                            <?php echo htmlspecialchars($req['request_type'] == 'vehicle' ? ($req['plate'] ?? 'N/A') : ($req['license_number'] ?? 'N/A')); ?>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $statusClass = match ($req['status']) {
                                                                'Pending' => 'bg-secondary',
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
                                                            <span class="badge <?php echo $statusClass; ?>">
                                                                <?php echo $req['status']; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php $paymentStatus = $req['invoice_status'] ?? 'sent'; ?>
                                                            <span class="badge bg-<?php echo $paymentStatus == 'paid' ? 'success' : 'warning'; ?>">
                                                                <i class="fas fa-<?php echo $paymentStatus == 'paid' ? 'check-circle' : 'hourglass-half'; ?> me-1"></i>
                                                                <?php echo $paymentStatus == 'paid' ? 'Paid' : 'Pending'; ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-muted"><?php echo date('M j, Y', strtotime($req['created_at'])); ?></td>
                                                        <td>
                                                            <a href="<?php echo base_url('request-details.php?id=' . $req['id']); ?>" class="btn btn-sm btn-outline-primary px-3">
                                                                <i class="fas fa-eye me-1"></i> View
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
                </div>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 🎨 STATUS CHART – SEMANTIC COLORS
            const statusCtx = document.getElementById('statusChart')?.getContext('2d');
            if (statusCtx && <?php echo !empty($filteredStatusData) ? 'true' : 'false'; ?>) {
                new Chart(statusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode($statusLabels); ?>,
                        datasets: [{
                            data: <?php echo json_encode($statusCounts); ?>,
                            backgroundColor: <?php echo json_encode($statusColors); ?>,
                            borderWidth: 0
                        }]
                    },
                    options: {
                        cutout: '70%',
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        maintainAspectRatio: false
                    }
                });
            }

            // MONTHLY CHART (unchanged)
            const monthlyCtx = document.getElementById('monthlyChart')?.getContext('2d');
            if (monthlyCtx) {
                new Chart(monthlyCtx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($monthLabels); ?>,
                        datasets: [{
                            label: 'Requests',
                            data: <?php echo json_encode($monthlyCounts); ?>,
                            backgroundColor: '#0A57FF',
                            borderWidth: 0,
                            borderRadius: 0
                        }]
                    },
                    options: {
                        scales: {
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: '#edf2f7'
                                },
                                border: {
                                    display: false
                                },
                                ticks: {
                                    stepSize: 1
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        maintainAspectRatio: false
                    }
                });
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>