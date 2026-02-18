<?php
// admin/view_vehicle.php
session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/status_functions.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// ---------- PAGINATION & FILTERS ----------
$perPage = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

// Stats counts – via customer_requests status
$totalCount = (int)$pdo->query("
    SELECT COUNT(*) FROM vehicle_requests vr
    JOIN customer_requests cr ON vr.customer_request_id = cr.id
")->fetchColumn();

$pendingCount = (int)$pdo->query("
    SELECT COUNT(*) FROM vehicle_requests vr
    JOIN customer_requests cr ON vr.customer_request_id = cr.id
    WHERE cr.status = 'Pending'
")->fetchColumn();

$processingCount = (int)$pdo->query("
    SELECT COUNT(*) FROM vehicle_requests vr
    JOIN customer_requests cr ON vr.customer_request_id = cr.id
    WHERE cr.status = 'Processing'
")->fetchColumn();

$completedCount = (int)$pdo->query("
    SELECT COUNT(*) FROM vehicle_requests vr
    JOIN customer_requests cr ON vr.customer_request_id = cr.id
    WHERE cr.status = 'Completed'
")->fetchColumn();

// Search & filter
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$filterStatus = isset($_GET['status']) ? trim($_GET['status']) : '';

$whereParts = [];
$execParams = [];

$baseQuery = "FROM vehicle_requests vr
              JOIN customer_requests cr ON vr.customer_request_id = cr.id
              LEFT JOIN users u ON cr.user_id = u.id";

if ($search !== '') {
    $cols = ['vr.full_name', 'vr.email', 'vr.phone', 'vr.plate', 'vr.vin', 'u.email', 'u.full_name'];
    $likeParts = [];
    foreach ($cols as $idx => $col) {
        $ph = "q{$idx}";
        $likeParts[] = "{$col} LIKE :{$ph}";
        $execParams[$ph] = '%' . $search . '%';
    }
    $whereParts[] = '(' . implode(' OR ', $likeParts) . ')';
}

if ($filterStatus !== '') {
    if ($filterStatus === 'pending') {
        $whereParts[] = "cr.status = 'Pending'";
    } elseif ($filterStatus === 'processing') {
        $whereParts[] = "cr.status = 'Processing'";
    } elseif ($filterStatus === 'completed') {
        $whereParts[] = "cr.status = 'Completed'";
    }
}

$whereSql = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

// Filtered count
$countSql = "SELECT COUNT(*) $baseQuery $whereSql";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($execParams);
$filteredCount = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($filteredCount / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

// Main query
$limit = (int)$perPage;
$off = (int)$offset;
$sql = "
    SELECT vr.*, cr.status as current_status, cr.id as customer_request_id
    $baseQuery
    $whereSql
    ORDER BY vr.id DESC
    LIMIT {$limit} OFFSET {$off}
";
$stmt = $pdo->prepare($sql);
$stmt->execute($execParams);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$startNumber = $offset + 1;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Requests – ClearMyRide Admin</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        /* ----- GLOBAL: ZERO ROUNDED CORNERS ----- */
        * {
            border-radius: 0 !important;
        }

        body {
            background: #f9fbfd;
            font-family: 'Inter', sans-serif;
            color: #1e293b;
            font-size: 0.9375rem;
            margin: 0;
            padding: 0;
        }

        .admin-nav {
            background: #0A57FF;
            padding: 0.5rem 0;
            box-shadow: 0 2px 6px rgba(10, 87, 255, 0.2);
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
            border: 1px solid rgba(255, 255, 255, 0.5);
            color: white;
            font-weight: 500;
            padding: 0.35rem 1rem;
            font-size: 0.8125rem;
            background: transparent;
        }

        .admin-nav .btn-outline-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: white;
        }

        .admin-nav .text-white {
            color: white !important;
        }

        .admin-container {
            max-width: 1440px;
            margin: 0 auto;
            padding: 1.5rem 1.5rem;
        }

        .stat-card {
            background: white;
            border: 1px solid #edf2f7;
            padding: 1.25rem 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            height: 100%;
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            color: #0A57FF;
            font-size: 1.25rem;
        }

        .stat-content h4 {
            font-size: 1.35rem;
            font-weight: 700;
            margin-bottom: 0.1rem;
            color: #0f172a;
        }

        .stat-content span {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            font-weight: 600;
        }

        .main-card {
            background: white;
            border: 1px solid #edf2f7;
            margin-top: 1.5rem;
        }

        .filter-bar {
            background: white;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #edf2f7;
        }

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

        .status-badge {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            border: 1px solid;
        }

        .status-Pending {
            background: #fffbeb;
            color: #b45309;
            border-color: #fcd34d;
        }

        .status-UnderReview {
            background: #e0f2fe;
            color: #0369a1;
            border-color: #bae6fd;
        }

        .status-AwaitingPayment {
            background: #fef9c3;
            color: #854d0e;
            border-color: #fde047;
        }

        .status-Processing {
            background: #eff6ff;
            color: #1e40af;
            border-color: #bfdbfe;
        }

        .status-Completed {
            background: #f0fdf4;
            color: #166534;
            border-color: #bbf7d0;
        }

        .status-Cancelled {
            background: #f1f5f9;
            color: #334155;
            border-color: #e2e8f0;
        }

        .status-RefundRequested {
            background: #fef2f2;
            color: #991b1b;
            border-color: #fecaca;
        }

        .status-Refunded {
            background: #f3e8ff;
            color: #6b21a8;
            border-color: #e9d5ff;
        }

        .btn {
            border-radius: 0;
            font-weight: 600;
            padding: 0.4rem 0.85rem;
            font-size: 0.75rem;
        }

        .btn-primary {
            background: #0A57FF;
            border: 1px solid #0A57FF;
            color: white;
        }

        .btn-primary:hover {
            background: #004ce5;
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

        .btn-success {
            background: #10b981;
            border: 1px solid #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #0f9e6e;
        }

        .btn-danger {
            background: #e53e3e;
            border: 1px solid #e53e3e;
            color: white;
        }

        .btn-danger:hover {
            background: #c53030;
        }

        .btn-warning {
            background: #dd6b20;
            border: 1px solid #dd6b20;
            color: white;
        }

        .btn-warning:hover {
            background: #b45309;
        }

        .btn-info {
            background: #0A57FF;
            border: 1px solid #0A57FF;
            color: white;
        }

        .btn-info:hover {
            background: #0845cc;
        }

        .dropdown-menu {
            border: 1px solid #edf2f7;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.02);
            padding: 0.5rem 0;
        }

        .dropdown-item {
            padding: 0.5rem 1rem;
            font-size: 0.8125rem;
        }

        .modal-content {
            border: 1px solid #edf2f7;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05);
        }

        .files-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 0.75rem;
        }

        .lightbox-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.9);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 12000;
        }

        .lightbox-backdrop.active {
            display: flex;
        }

        .lightbox-close {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.5);
            border: none;
            color: white;
            padding: 0.5rem 0.75rem;
        }

        .invalid-feedback {
            display: none;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.75rem;
            color: #dc3545;
        }

        .was-validated .form-control:invalid~.invalid-feedback {
            display: block;
        }

        /* ----- ENHANCED INVOICE CARD (admin) ----- */
        .invoice-card {
            background: white;
            border: 1px solid #e2e8f0;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .invoice-header {
            border-bottom: 2px solid #0A57FF;
            padding-bottom: 0.75rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .invoice-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #0A57FF;
        }

        .invoice-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px dashed #edf2f7;
        }

        .invoice-row:last-child {
            border-bottom: none;
            font-weight: 700;
        }

        .invoice-notes {
            background: #f8fafc;
            padding: 0.75rem;
            margin-top: 1rem;
            border-left: 4px solid #0A57FF;
            font-size: 0.85rem;
        }

        .invoice-due {
            font-size: 0.85rem;
            color: #64748b;
        }

        .invoice-due.overdue {
            color: #e53e3e;
            font-weight: 600;
        }

        .bank-details {
            background: #f8fafc;
            border-left: 4px solid #0A57FF;
            padding: 1rem;
            margin-top: 1rem;
            font-size: 0.85rem;
        }

        /* ----- ENHANCED REFUND CARD (admin) ----- */
        .refund-card {
            background: white;
            border: 1px solid #edf2f7;
            border-left: 4px solid;
            border-left-color: #0A57FF;
            padding: 1.25rem;
            margin-bottom: 1rem;
            transition: box-shadow 0.2s;
        }

        .refund-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
        }

        .refund-card.pending {
            border-left-color: #dd6b20;
        }

        .refund-card.approved {
            border-left-color: #0A57FF;
        }

        .refund-card.rejected {
            border-left-color: #e53e3e;
        }

        .refund-card.completed {
            border-left-color: #10b981;
        }

        .refund-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .refund-title {
            font-weight: 600;
            color: #1a202c;
        }

        .refund-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            font-size: 0.85rem;
            color: #64748b;
            margin-bottom: 0.75rem;
        }

        .refund-detail-row {
            display: flex;
            margin-bottom: 0.5rem;
        }

        .refund-detail-label {
            width: 120px;
            font-weight: 600;
            color: #4a5568;
            font-size: 0.85rem;
        }

        .refund-detail-value {
            flex: 1;
            color: #1a202c;
            font-size: 0.9rem;
        }

        .refund-proof-link {
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid #edf2f7;
        }

        .bank-details-form {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #edf2f7;
        }

        /* ----- MODAL STYLES ----- */
        .card-header.bg-white {
            background: white;
        }

        .border-2 {
            border-width: 2px !important;
        }

        .fs-6 {
            font-size: 0.95rem !important;
        }

        .bg-opacity-10 {
            --bs-bg-opacity: 0.1;
        }

        .hover-shadow:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.05);
            transition: box-shadow 0.2s ease;
        }
    </style>
</head>

<body>
    <!-- BLUE NAVBAR -->
    <nav class="admin-nav">
        <div class="admin-container d-flex justify-content-between align-items-center w-100">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-car"></i> <span>ClearMyRide Admin</span>
            </a>
            <div class="d-flex align-items-center gap-2">
                <span class="text-white d-none d-md-inline">
                    <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?>
                </span>
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-tachometer-alt me-1"></i> Dashboard</a>
                <a href="logout.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-sign-out-alt me-1"></i> Logout</a>
            </div>
        </div>
    </nav>

    <main class="admin-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 600; color: #0f172a;">
                    <i class="fas fa-car me-2" style="color: #0A57FF;"></i> Vehicle Registration Requests
                </h1>
                <p class="text-muted mb-0" style="font-size: 0.8125rem;">Manage and review vehicle renewal requests</p>
            </div>
            <div>
                <a href="view_license.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-id-card me-1"></i> License Requests</a>
            </div>
        </div>

        <!-- STATS CARDS -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-list-ul"></i></div>
                    <div class="stat-content">
                        <h4><?php echo $totalCount; ?></h4><span>Total</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon" style="color: #b45309;"><i class="fas fa-clock"></i></div>
                    <div class="stat-content">
                        <h4><?php echo $pendingCount; ?></h4><span>Pending</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon" style="color: #1e40af;"><i class="fas fa-cog"></i></div>
                    <div class="stat-content">
                        <h4><?php echo $processingCount; ?></h4><span>Processing</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon" style="color: #166534;"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-content">
                        <h4><?php echo $completedCount; ?></h4><span>Completed</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- MAIN CARD -->
        <div class="main-card">
            <div class="filter-bar">
                <form method="get" id="filterForm" class="row g-2">
                    <div class="col-md-6">
                        <div class="input-group">
                            <input type="text" name="q" class="form-control" placeholder="Search name, email, plate, VIN..."
                                value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-outline-secondary" type="submit"><i class="fas fa-search me-1"></i>Search</button>
                            <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-outline-secondary"><i class="fas fa-times"></i></a>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="">All statuses</option>
                            <option value="pending" <?php echo $filterStatus == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="processing" <?php echo $filterStatus == 'processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="completed" <?php echo $filterStatus == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                    <div class="col-md-3 text-end">
                        <a href="export_vehicle_xlsx.php?<?php echo http_build_query($_GET); ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-file-excel me-1"></i> Export to Excel
                        </a>
                    </div>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Customer</th>
                            <th>Contact</th>
                            <th>Vehicle</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $i => $r):
                            $rowNumber = $startNumber + $i;
                            $status = $r['current_status'] ?? 'Pending';
                            if (!in_array($status, ['Pending', 'Under Review', 'Awaiting Payment', 'Processing', 'Completed', 'Cancelled', 'Refund Requested', 'Refunded'])) {
                                $status = 'Pending';
                            }
                            // Fetch attachments via customer_request_id
                            $attStmt = $pdo->prepare("
                                SELECT id, file_name, mime_type, file_size 
                                FROM attachments 
                                WHERE parent_type = 'customer_request' AND parent_id = ?
                                ORDER BY id ASC
                            ");
                            $attStmt->execute([$r['customer_request_id']]);
                            $attachments = $attStmt->fetchAll(PDO::FETCH_ASSOC);

                            // Fetch cancellation reason if any
                            $cancelStmt = $pdo->prepare("SELECT cancellation_reason FROM customer_requests WHERE id = ?");
                            $cancelStmt->execute([$r['customer_request_id']]);
                            $cancellationReason = $cancelStmt->fetchColumn();

                            $recordData = $r;
                            $recordData['attachments'] = $attachments;
                            $recordData['status'] = $status;
                            $recordData['cancellation_reason'] = $cancellationReason;
                            $jsonData = json_encode($recordData, JSON_HEX_APOS | JSON_HEX_QUOT);
                        ?>
                            <tr data-record='<?php echo htmlspecialchars($jsonData, ENT_QUOTES, 'UTF-8'); ?>'
                                data-request-id="<?php echo $r['customer_request_id']; ?>"
                                data-type="vehicle"
                                data-status="<?php echo $status; ?>">
                                <td class="fw-medium text-muted"><?php echo $rowNumber; ?></td>
                                <td>
                                    <div class="fw-medium"><?php echo htmlspecialchars($r['full_name']); ?></div>
                                    <div class="text-muted small">DOB: <?php echo !empty($r['dob']) ? date('M j, Y', strtotime($r['dob'])) : '—'; ?></div>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($r['email']); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($r['phone']); ?></div>
                                </td>
                                <td>
                                    <div class="fw-medium"><?php echo htmlspecialchars($r['plate']); ?></div>
                                    <div class="text-muted small">VIN: <?php echo htmlspecialchars($r['vin']) ?: '—'; ?></div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo str_replace(' ', '', $status); ?>" data-status="<?php echo $status; ?>">
                                        <?php echo $status; ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <button class="btn btn-sm action-btn dropdown-toggle" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item view-btn" href="#"><i class="fas fa-eye me-2"></i>View Details</a></li>
                                            <!-- Dynamic actions will be injected via JS -->
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No vehicle requests found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <?php if ($totalPages > 1): ?>
                <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3">
                    <div class="text-muted small">Showing <?php echo count($rows); ?> of <?php echo $filteredCount; ?> results</div>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <?php
                            $query = $_GET;
                            unset($query['page']);
                            $base = $_SERVER['PHP_SELF'] . '?' . http_build_query($query) . '&page=';
                            ?>
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $base . ($page - 1); ?>"><i class="fas fa-chevron-left"></i></a>
                            </li>
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php echo $base . $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $base . ($page + 1); ?>"><i class="fas fa-chevron-right"></i></a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- ========== MODALS ========== -->
    <!-- DETAIL MODAL -->
    <div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content"></div>
        </div>
    </div>

    <!-- INVOICE GENERATION MODAL (with due date and notes) -->
    <div class="modal fade" id="invoiceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-file-invoice me-2"></i>Generate Invoice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="invoiceForm">
                        <input type="hidden" name="request_id" id="invoice_request_id">
                        <input type="hidden" name="request_type" id="invoice_request_type">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Service Fee ($)</label>
                            <input type="number" name="service_fee" class="form-control" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Government Fee ($)</label>
                            <input type="number" name="gov_fee" class="form-control" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Due Date</label>
                            <input type="date" name="due_date" class="form-control" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                            <small class="text-muted">Default 30 days from today</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Notes (optional)</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Additional invoice notes..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="submitInvoiceBtn">Generate Invoice</button>
                </div>
            </div>
        </div>
    </div>

    <!-- CONFIRM PAYMENT MODAL -->
    <div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-credit-card me-2"></i>Update Payment Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="paymentForm">
                        <input type="hidden" name="proof_id" id="payment_proof_id">
                        <input type="hidden" name="status" id="payment_status">
                        <div class="mb-3" id="rejection_notes_container">
                            <label class="form-label fw-semibold">Reason for rejection (optional)</label>
                            <textarea name="admin_notes" class="form-control" rows="2"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="submitPaymentBtn">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <!-- CANCEL REQUEST MODAL (with reason) -->
    <div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-times-circle text-danger me-2"></i>Cancel Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to cancel this request? This action cannot be undone.</p>
                    <div class="mb-3">
                        <label for="cancelReason" class="form-label fw-semibold">Reason for cancellation</label>
                        <textarea id="cancelReason" class="form-control" rows="2" placeholder="Enter reason..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-danger" id="confirmCancelBtn">Yes, Cancel Request</button>
                </div>
            </div>
        </div>
    </div>

    <!-- COMPLETE WITH REFUND MODAL (admin-initiated refund on completion) -->
    <div class="modal fade" id="completeRefundModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-check-circle text-success me-2"></i>Mark Completed</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>You are about to mark this request as <strong>Completed</strong>.</p>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="processRefundCheck">
                        <label class="form-check-label fw-semibold" for="processRefundCheck">
                            Process a partial refund
                        </label>
                        <small class="d-block text-muted">If checked, you can enter a refund amount and reason. Customer will be prompted to provide bank details.</small>
                    </div>
                    <div id="refundFields" style="display: none;">
                        <hr>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Refund Amount ($)</label>
                            <input type="number" id="refundAmount" class="form-control" step="0.01" min="0.01" placeholder="0.00">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Reason for refund</label>
                            <textarea id="refundReason" class="form-control" rows="2" placeholder="Explain why this refund is being issued..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmCompleteBtn">Complete Request</button>
                </div>
            </div>
        </div>
    </div>

    <!-- REFUND ACTION MODAL (approve/reject with proof) -->
    <div class="modal fade" id="refundActionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="refundActionTitle">Process Refund</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="refundActionForm" enctype="multipart/form-data">
                        <input type="hidden" name="refund_id" id="action_refund_id">
                        <input type="hidden" name="action" id="action_refund_action">
                        <div id="approveFields">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Refund Proof (PDF, JPG, PNG)</label>
                                <input type="file" name="refund_proof" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                <small class="text-muted">Required for approval. Max 10MB.</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Admin Note (optional)</label>
                                <textarea name="admin_note" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                        <div id="rejectFields" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Reason for rejection <span class="text-danger">*</span></label>
                                <textarea name="admin_note" class="form-control" rows="2" required></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmRefundActionBtn">Submit</button>
                </div>
            </div>
        </div>
    </div>

    <!-- LIGHTBOX -->
    <div id="lightbox" class="lightbox-backdrop">
        <div class="lightbox-content">
            <button class="lightbox-close" onclick="closeLightbox()"><i class="fas fa-times"></i></button>
            <img id="lightbox-img" class="lightbox-img" src="" alt="">
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // ----- GLOBAL CONSTANTS -----
    const statusUpdateUrl = 'status_update.php';
    const getInvoiceUrl = 'get_invoice.php';
    const generateInvoiceUrl = 'invoice_generate.php';
    const confirmPaymentUrl = 'payment_confirm.php';
    const getReceiptUrl = 'get_receipt.php';
    const requestDocumentUrl = 'request_document.php';
    const getDocumentRequestsUrl = 'get_document_requests.php';
    const refundActionUrl = 'refund_action.php';
    const getRefundsUrl = 'get_refunds.php';

    // ----- TOAST NOTIFICATION -----
    function showToast(title, message, type = 'info') {
        const toast = document.createElement('div');
        toast.style.position = 'fixed';
        toast.style.top = '20px';
        toast.style.right = '20px';
        toast.style.background = type === 'danger' ? '#ef4444' : type === 'success' ? '#10b981' : '#3b82f6';
        toast.style.color = 'white';
        toast.style.padding = '12px 20px';
        toast.style.border = 'none';
        toast.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
        toast.style.zIndex = '9999';
        toast.style.fontSize = '0.875rem';
        toast.style.minWidth = '250px';
        toast.style.borderRadius = '0';
        toast.innerHTML = `<strong>${title}</strong><br>${message}`;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 5000);
    }

    // ----- ALLOWED ACTIONS BASED ON STATUS -----
    function getAllowedActions(status) {
        const actions = [];
        switch (status) {
            case 'Pending':
                actions.push({ action: 'under_review', label: 'Move to Under Review', icon: 'fa-eye' });
                actions.push({ action: 'cancel', label: 'Cancel Request', icon: 'fa-ban' });
                break;
            case 'Under Review':
                actions.push({ action: 'open_invoice_modal', label: 'Generate Invoice', icon: 'fa-file-invoice' });
                actions.push({ action: 'cancel', label: 'Cancel Request', icon: 'fa-ban' });
                break;
            case 'Awaiting Payment':
                actions.push({ action: 'cancel', label: 'Cancel Request', icon: 'fa-ban' });
                break;
            case 'Processing':
                actions.push({ action: 'complete', label: 'Mark Completed', icon: 'fa-check-circle' });
                break;
            case 'Completed':
            case 'Refund Requested':
            case 'Cancelled':
            case 'Refunded':
                break;
        }
        return actions;
    }

    // ----- REFRESH DROPDOWN ACTIONS -----
    function refreshDropdown(tr) {
        const status = tr.dataset.status;
        const dropdownMenu = tr.querySelector('.dropdown-menu');
        if (!dropdownMenu) return;
        const viewItem = dropdownMenu.querySelector('.view-btn').closest('li');
        dropdownMenu.innerHTML = '';
        dropdownMenu.appendChild(viewItem.cloneNode(true));

        const actions = getAllowedActions(status);
        actions.forEach(a => {
            const li = document.createElement('li');
            li.innerHTML = `<a class="dropdown-item" href="#" data-action="${a.action}"><i class="fas ${a.icon} me-2"></i>${a.label}</a>`;
            dropdownMenu.appendChild(li);
        });
    }
    document.querySelectorAll('table tbody tr').forEach(refreshDropdown);

    // ----- STATUS UPDATE -----
    async function doActionForRow(tr, action, extraData = {}) {
        const requestId = tr.dataset.requestId;
        const type = tr.dataset.type;
        const formData = new URLSearchParams({
            id: requestId,
            type: type,
            action: action,
            note: extraData.note || ''
        });
        if (extraData.refund_amount) formData.append('refund_amount', extraData.refund_amount);
        if (extraData.refund_reason) formData.append('refund_reason', extraData.refund_reason);

        try {
            const resp = await fetch(statusUpdateUrl, { method: 'POST', body: formData });
            const res = await resp.json();
            if (res.success) {
                const newStatus = res.new_status;
                tr.dataset.status = newStatus;
                const badge = tr.querySelector('.status-badge');
                badge.className = `status-badge status-${newStatus.replace(/ /g, '')}`;
                badge.textContent = newStatus;
                badge.dataset.status = newStatus;
                refreshDropdown(tr);
                if (document.getElementById('detailModal')._currentRow === tr) {
                    updateModalStatusBar(newStatus);
                    const invoiceSection = document.getElementById('invoice-section');
                    if (invoiceSection) loadInvoiceData(tr.dataset.requestId, type, newStatus);
                }
                showToast('Success', res.message, 'success');
            } else {
                showToast('Error', res.message, 'danger');
            }
        } catch (e) {
            showToast('Network Error', e.message, 'danger');
        }
    }

    // ----- OPEN INVOICE MODAL -----
    function openInvoiceModal(requestId, requestType) {
        document.getElementById('invoice_request_id').value = requestId;
        document.getElementById('invoice_request_type').value = requestType;
        new bootstrap.Modal(document.getElementById('invoiceModal')).show();
    }

    // ----- CANCEL MODAL -----
    let pendingCancelRow = null;
    function openCancelModal(tr) {
        pendingCancelRow = tr;
        document.getElementById('cancelReason').value = '';
        new bootstrap.Modal(document.getElementById('cancelModal')).show();
    }

    document.getElementById('confirmCancelBtn').addEventListener('click', async function() {
        if (!pendingCancelRow) return;
        const btn = this;
        const originalHtml = btn.innerHTML;
        const reason = document.getElementById('cancelReason').value.trim();
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Cancelling...';
        try {
            await doActionForRow(pendingCancelRow, 'cancel', { note: reason });
            bootstrap.Modal.getInstance(document.getElementById('cancelModal')).hide();
            showToast('Success', 'Request cancelled.', 'success');
            setTimeout(() => location.reload(), 1000);
        } catch (error) {
            console.error('Cancel error:', error);
            showToast('Error', 'Failed to cancel request.', 'danger');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        } finally {
            pendingCancelRow = null;
        }
    });

    // ----- COMPLETE + REFUND MODAL -----
    let pendingCompleteRow = null;
    function openCompleteModal(tr) {
        pendingCompleteRow = tr;
        document.getElementById('processRefundCheck').checked = false;
        document.getElementById('refundFields').style.display = 'none';
        document.getElementById('refundAmount').value = '';
        document.getElementById('refundReason').value = '';
        new bootstrap.Modal(document.getElementById('completeRefundModal')).show();
    }

    document.getElementById('processRefundCheck').addEventListener('change', function(e) {
        document.getElementById('refundFields').style.display = e.target.checked ? 'block' : 'none';
    });

    document.getElementById('confirmCompleteBtn').addEventListener('click', async function() {
        if (!pendingCompleteRow) return;
        const btn = this;
        const originalHtml = btn.innerHTML;
        const processRefund = document.getElementById('processRefundCheck').checked;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Completing...';
        try {
            if (processRefund) {
                const amount = document.getElementById('refundAmount').value;
                const reason = document.getElementById('refundReason').value.trim();
                if (!amount || parseFloat(amount) <= 0) {
                    showToast('Validation', 'Please enter a valid refund amount.', 'warning');
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                    return;
                }
                if (!reason) {
                    showToast('Validation', 'Please enter a reason for the refund.', 'warning');
                    btn.disabled = false;
                    btn.innerHTML = originalHtml;
                    return;
                }
                await doActionForRow(pendingCompleteRow, 'complete_with_refund', {
                    refund_amount: amount,
                    refund_reason: reason
                });
            } else {
                await doActionForRow(pendingCompleteRow, 'complete', {});
            }
            bootstrap.Modal.getInstance(document.getElementById('completeRefundModal')).hide();
            showToast('Success', 'Request completed.', 'success');
            setTimeout(() => location.reload(), 1000);
        } catch (error) {
            console.error('Complete error:', error);
            showToast('Error', 'Failed to complete request.', 'danger');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        } finally {
            pendingCompleteRow = null;
        }
    });

    // ----- REFUND ACTION MODAL -----
    let pendingRefundId = null;
    let pendingAction = null;
    function openRefundActionModal(refundId, action) {
        pendingRefundId = refundId;
        pendingAction = action;
        document.getElementById('action_refund_id').value = refundId;
        document.getElementById('action_refund_action').value = action;
        document.getElementById('refundActionTitle').innerText = action === 'approve' ? 'Approve Refund' : 'Reject Refund';
        document.getElementById('approveFields').style.display = action === 'approve' ? 'block' : 'none';
        document.getElementById('rejectFields').style.display = action === 'reject' ? 'block' : 'none';
        if (action === 'approve') {
            document.querySelector('#refundActionForm input[name="refund_proof"]').value = '';
            document.querySelector('#refundActionForm textarea[name="admin_note"]').value = '';
        } else {
            document.querySelector('#refundActionForm textarea[name="admin_note"]').value = '';
        }
        new bootstrap.Modal(document.getElementById('refundActionModal')).show();
    }

    document.getElementById('confirmRefundActionBtn').addEventListener('click', async function() {
        if (!pendingRefundId || !pendingAction) return;
        const btn = this;
        const originalHtml = btn.innerHTML;
        const formData = new FormData(document.getElementById('refundActionForm'));
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Processing...';
        try {
            const resp = await fetch(refundActionUrl, { method: 'POST', body: formData });
            const res = await resp.json();
            if (res.success) {
                showToast('Success', res.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('refundActionModal')).hide();
                const modalRow = document.getElementById('detailModal')._currentRow;
                if (modalRow) {
                    const requestId = modalRow.dataset.requestId;
                    const requestType = modalRow.dataset.type;
                    await loadRefundData(requestId, requestType);
                }
            } else {
                showToast('Error', res.message, 'danger');
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        } catch (error) {
            console.error('Refund action error:', error);
            showToast('Error', 'Network error. Please try again.', 'danger');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        } finally {
            pendingRefundId = null;
            pendingAction = null;
        }
    });

    // ----- LIGHTBOX -----
    const lb = document.getElementById('lightbox');
    const lbImg = document.getElementById('lightbox-img');
    window.openLightbox = function(src) { lbImg.src = src; lb.classList.add('active'); document.body.style.overflow = 'hidden'; };
    window.closeLightbox = function() { lb.classList.remove('active'); lbImg.src = ''; document.body.style.overflow = ''; };
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && lb.classList.contains('active')) closeLightbox(); });
    lb.addEventListener('click', function(e) { if (e.target === lb) closeLightbox(); });

    // ----- EVENT DELEGATION -----
    document.addEventListener('click', function(e) {
        const viewBtn = e.target.closest('.view-btn');
        if (viewBtn) {
            e.preventDefault();
            const tr = viewBtn.closest('tr');
            if (tr) openDetailModalForRow(tr);
        }

        const actionItem = e.target.closest('.dropdown-item[data-action]');
        if (actionItem && !actionItem.classList.contains('view-btn')) {
            e.preventDefault();
            const tr = actionItem.closest('tr');
            const action = actionItem.dataset.action;
            if (action === 'open_invoice_modal') {
                openInvoiceModal(tr.dataset.requestId, tr.dataset.type);
            } else if (action === 'cancel') {
                openCancelModal(tr);
            } else if (action === 'complete') {
                openCompleteModal(tr);
            } else {
                doActionForRow(tr, action);
            }
        }
    });

    // ========== DETAIL MODAL ==========
    const detailModalEl = document.getElementById('detailModal');
    const detailModal = new bootstrap.Modal(detailModalEl);

    // ----- RENDER INVOICE SECTION (enhanced design) -----
    async function loadInvoiceData(requestId, type, currentStatus) {
        const container = document.getElementById('invoice-section');
        if (!container) return;
        try {
            const resp = await fetch(`${getInvoiceUrl}?request_id=${requestId}&type=${type}`);
            const data = await resp.json();
            renderInvoiceSection(container, data, requestId, type, currentStatus);
        } catch (e) {
            container.innerHTML = `<div class="alert alert-danger">Failed to load invoice data.</div>`;
        }
    }

    function renderInvoiceSection(container, data, requestId, type, currentStatus) {
        let html = '';
        if (data.invoice) {
            const inv = data.invoice;
            const breakdown = typeof inv.breakdown === 'string' ? JSON.parse(inv.breakdown) : inv.breakdown || { service_fee: 0, gov_fee: 0 };
            const dueDate = inv.due_date ? new Date(inv.due_date).toLocaleDateString() : 'Not set';
            const isOverdue = inv.due_date && new Date(inv.due_date) < new Date() && inv.status !== 'paid';

            html += `<div class="invoice-card">
                <div class="invoice-header">
                    <span class="invoice-title">Invoice #${escapeHtml(inv.invoice_number)}</span>
                    <span class="badge bg-${inv.status === 'paid' ? 'success' : isOverdue ? 'danger' : 'warning'} px-3 py-2">
                        <i class="fas fa-${inv.status === 'paid' ? 'check-circle' : isOverdue ? 'exclamation-triangle' : 'clock'} me-1"></i>
                        ${inv.status === 'paid' ? 'Paid' : isOverdue ? 'Overdue' : inv.status}
                    </span>
                </div>
                <div class="invoice-row">
                    <span>Service Fee</span>
                    <span class="fw-medium">$${Number(breakdown.service_fee).toFixed(2)}</span>
                </div>
                <div class="invoice-row">
                    <span>Government Fee</span>
                    <span class="fw-medium">$${Number(breakdown.gov_fee).toFixed(2)}</span>
                </div>
                <div class="invoice-row">
                    <span>Total</span>
                    <span class="fw-bold fs-6">$${Number(inv.amount_estimated).toFixed(2)}</span>
                </div>
                <div class="d-flex justify-content-between mt-2 small">
                    <span class="text-muted"><i class="fas fa-calendar me-1"></i> Issued: ${new Date(inv.created_at).toLocaleDateString()}</span>
                    <span class="${isOverdue ? 'invoice-due overdue' : 'invoice-due'}">
                        <i class="fas fa-calendar-alt me-1"></i> Due: ${dueDate}
                    </span>
                </div>`;

            // Invoice notes (from inv.notes or breakdown.notes)
            const notesText = inv.notes || (breakdown.notes || null);
            if (notesText) {
                html += `<div class="invoice-notes">
                            <div class="d-flex gap-2">
                                <div></div>
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <i class="fas fa-sticky-note" style="color: #0A57FF; font-size: 0.9rem;"></i>
                                        <span style="font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.3px; color: #0A57FF;">Invoice Note</span>
                                    </div>
                                    <p style="margin-bottom: 0; font-size: 0.85rem; color: #1a202c; line-height: 1.5;">
                                        ${escapeHtml(notesText)}
                                    </p>
                                </div>
                            </div>
                        </div>`;
            }

            // Company bank details (hard-coded for admin reference)
            html += `<div class="bank-details">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <div style="width: 28px; height: 28px; background: #ebf8ff; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-university" style="color: #0A57FF; font-size: 0.9rem;"></i>
                            </div>
                            <span style="font-weight: 700; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; color: #1a202c;">Payment Instructions</span>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-top: 0.5rem;">
                            <div>
                                <div style="font-size: 0.7rem; text-transform: uppercase; color: #64748b; letter-spacing: 0.3px; margin-bottom: 0.2rem;">Beneficiary</div>
                                <div style="font-weight: 600; font-size: 0.9rem; color: #1a202c;">ClearMyRide LLC</div>
                            </div>
                            <div>
                                <div style="font-size: 0.7rem; text-transform: uppercase; color: #64748b; letter-spacing: 0.3px; margin-bottom: 0.2rem;">Bank</div>
                                <div style="font-weight: 500; font-size: 0.9rem; color: #1a202c;">Bank of America</div>
                            </div>
                            <div>
                                <div style="font-size: 0.7rem; text-transform: uppercase; color: #64748b; letter-spacing: 0.3px; margin-bottom: 0.2rem;">Account #</div>
                                <div style="font-weight: 500; font-size: 0.9rem; color: #1a202c; font-family: 'Inter', monospace;">4830 3200 1928</div>
                            </div>
                            <div>
                                <div style="font-size: 0.7rem; text-transform: uppercase; color: #64748b; letter-spacing: 0.3px; margin-bottom: 0.2rem;">Routing #</div>
                                <div style="font-weight: 500; font-size: 0.9rem; color: #1a202c; font-family: 'Inter', monospace;">0260-0959-3</div>
                            </div>
                        </div>
                        <div style="margin-top: 0.75rem; padding-top: 0.5rem; border-top: 1px dashed #e2e8f0;">
                            <small style="color: #64748b; display: flex; align-items: center; gap: 0.25rem;">
                                <i class="fas fa-info-circle" style="color: #0A57FF;"></i> Include invoice number in transfer description.
                            </small>
                        </div>
                    </div>
                </div>`; // close invoice-card

            // Payment proofs
            html += `<div class="mt-3"><p class="fw-semibold mb-2">Payment Proofs</p>`;
            if (data.proofs && data.proofs.length) {
                data.proofs.forEach(p => {
                    let statusBadge = `<span class="badge bg-${p.status === 'confirmed' ? 'success' : p.status === 'rejected' ? 'danger' : 'warning'}">${p.status}</span>`;
                    html += `<div class="d-flex justify-content-between align-items-center mb-2 p-2" style="border:1px solid #edf2f7;">
                                <div>
                                    <i class="fas fa-file me-2"></i>${escapeHtml(p.file_name)}
                                    <small class="text-muted ms-2">${new Date(p.uploaded_at).toLocaleDateString()}</small>
                                    ${statusBadge}
                                </div>
                                <div>
                                    <a href="download_proof.php?id=${p.id}" class="btn btn-sm btn-outline-primary me-1" download>
                                        <i class="fas fa-download"></i>
                                    </a>
                                    ${p.status === 'pending' ? `
                                        <button class="btn btn-sm btn-outline-success confirm-payment" data-proof-id="${p.id}"><i class="fas fa-check"></i></button>
                                        <button class="btn btn-sm btn-outline-danger reject-payment" data-proof-id="${p.id}"><i class="fas fa-times"></i></button>
                                    ` : ''}
                                </div>
                            </div>`;
                });
            } else {
                html += `<p class="text-muted">No payment proofs uploaded yet.</p>`;
            }
            html += `</div>`;
        } else {
            if (currentStatus === 'Under Review') {
                html = `<div class="text-center py-3">
                            <p class="text-muted mb-3">No invoice generated for this request.</p>
                            <button class="btn btn-primary generate-invoice-btn" data-request-id="${requestId}" data-request-type="${type}">
                                <i class="fas fa-file-invoice me-2"></i>Generate Invoice
                            </button>
                        </div>`;
            } else {
                html = `<div class="text-center py-3">
                            <p class="text-muted mb-3">No invoice generated. Invoice can only be created when request is <strong>Under Review</strong>.</p>
                        </div>`;
            }
        }
        container.innerHTML = html;

        // Attach listeners
        container.querySelectorAll('.generate-invoice-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                document.getElementById('invoice_request_id').value = this.dataset.requestId;
                document.getElementById('invoice_request_type').value = this.dataset.requestType;
                new bootstrap.Modal(document.getElementById('invoiceModal')).show();
            });
        });
        container.querySelectorAll('.confirm-payment').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('payment_proof_id').value = this.dataset.proofId;
                document.getElementById('payment_status').value = 'confirmed';
                document.getElementById('rejection_notes_container').style.display = 'none';
                new bootstrap.Modal(document.getElementById('paymentModal')).show();
            });
        });
        container.querySelectorAll('.reject-payment').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('payment_proof_id').value = this.dataset.proofId;
                document.getElementById('payment_status').value = 'rejected';
                document.getElementById('rejection_notes_container').style.display = 'block';
                new bootstrap.Modal(document.getElementById('paymentModal')).show();
            });
        });
    }

    // ----- LOAD DOCUMENT REQUEST HISTORY -----
    async function loadDocumentRequestHistory(requestId, requestType) {
        const container = document.getElementById('doc-request-history');
        if (!container) return;
        try {
            const resp = await fetch(`${getDocumentRequestsUrl}?request_id=${requestId}&type=${requestType}`);
            const requests = await resp.json();
            if (requests && requests.length > 0) {
                let html = `<div class="list-group list-group-flush">`;
                requests.forEach(req => {
                    const hasAttachments = req.attachments && req.attachments.length > 0;
                    const displayStatus = hasAttachments ? 'fulfilled' : (req.status || 'pending');
                    const statusClass = displayStatus === 'fulfilled' ? 'success' : (displayStatus === 'pending' ? 'warning' : 'secondary');

                    html += `<div class="list-group-item px-4 py-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <p class="mb-1 fw-medium">${escapeHtml(req.message)}</p>
                                        <small class="text-muted">
                                            <i class="fas fa-user me-1"></i> ${escapeHtml(req.admin_name || 'Admin')} · 
                                            ${new Date(req.requested_at).toLocaleString()}
                                        </small>
                                    </div>
                                    <span class="badge bg-${statusClass} px-3 py-2">
                                        <i class="fas fa-${displayStatus === 'fulfilled' ? 'check-circle' : 'clock'} me-1"></i>
                                        ${displayStatus}
                                    </span>
                                </div>`;
                    if (hasAttachments) {
                        html += `<div class="mt-2 ps-3 border-start border-2 border-light">
                                    <small class="text-muted d-block mb-1"><i class="fas fa-paperclip me-1"></i>Attachments:</small>
                                    <div class="d-flex flex-wrap gap-2">`;
                        req.attachments.forEach(att => {
                            const fileName = escapeHtml(att.file_name);
                            const fileUrl = `../download.php?id=${att.id}&mode=download`;
                            html += `<a href="${fileUrl}" class="btn btn-sm btn-outline-secondary" download>
                                        <i class="fas fa-file me-1"></i>${fileName.length > 20 ? fileName.substr(0,20)+'…' : fileName}
                                    </a>`;
                        });
                        html += `</div></div>`;
                    }
                    html += `</div>`;
                });
                html += `</div>`;
                container.innerHTML = html;
            } else {
                container.innerHTML = `<p class="text-muted text-center py-4 mb-0">No previous document requests.</p>`;
            }
        } catch (e) {
            console.error('Failed to load document history:', e);
            container.innerHTML = `<p class="text-danger text-center py-4 mb-0">Failed to load history.</p>`;
        }
    }

    // ----- LOAD REFUND DATA -----
    async function loadRefundData(requestId, requestType) {
        const container = document.getElementById('refund-section');
        if (!container) return;
        try {
            const resp = await fetch(`${getRefundsUrl}?request_id=${requestId}`);
            const data = await resp.json();
            renderRefundSection(container, data, requestId);
        } catch (e) {
            container.innerHTML = `<p class="text-danger text-center py-4">Failed to load refund data.</p>`;
        }
    }

    function renderRefundSection(container, refunds, requestId) {
        if (!refunds || refunds.length === 0) {
            container.innerHTML = `<p class="text-muted text-center py-3">No refund requests found.</p>`;
            return;
        }
        let html = '';
        refunds.forEach(r => {
            const statusClass = r.status;
            const statusBadgeClass = {
                'pending': 'bg-warning',
                'approved': 'bg-info',
                'rejected': 'bg-danger',
                'completed': 'bg-success'
            } [r.status] || 'bg-secondary';
            const statusIcon = {
                'pending': 'clock',
                'approved': 'check-circle',
                'rejected': 'times-circle',
                'completed': 'check-double'
            } [r.status] || 'circle';
            const initiatedBy = r.initiated_by === 'admin' ? 'Admin-initiated' : 'Customer-requested';
            const initiatedBadgeClass = r.initiated_by === 'admin' ? 'bg-secondary' : 'bg-primary';

            html += `<div class="refund-card ${statusClass}" id="refund-${r.id}">
                        <div class="refund-header">
                            <div>
                                <span class="refund-title">
                                    <i class="fas fa-${statusIcon} me-2 text-${statusBadgeClass.replace('bg-','')}"></i>
                                    Refund Request #${r.id}
                                </span>
                                <span class="badge ${initiatedBadgeClass} ms-2">${initiatedBy}</span>
                            </div>
                            <span class="badge ${statusBadgeClass} px-3 py-2">
                                <i class="fas fa-${statusIcon} me-1"></i> ${r.status}
                            </span>
                        </div>

                        <div class="refund-meta">
                            <span><i class="fas fa-calendar me-1"></i> Requested: ${new Date(r.created_at).toLocaleDateString()}</span>
                            <span><i class="fas fa-dollar-sign me-1"></i> Amount: $${parseFloat(r.amount).toFixed(2)}</span>
                        </div>

                        <div class="refund-detail-row">
                            <span class="refund-detail-label">Reason</span>
                            <span class="refund-detail-value">${escapeHtml(r.reason || '—')}</span>
                        </div>

                        ${r.bank_details ? `
                            <div class="refund-detail-row">
                                <span class="refund-detail-label">Bank Account</span>
                                <span class="refund-detail-value">${escapeHtml(r.bank_details)}</span>
                            </div>
                        ` : `
                            <div class="refund-detail-row">
                                <span class="refund-detail-label">Bank Account</span>
                                <span class="refund-detail-value text-muted"><em>Awaiting submission</em></span>
                            </div>
                        `}

                        ${r.admin_note ? `
                            <div class="refund-detail-row">
                                <span class="refund-detail-label">Admin Note</span>
                                <span class="refund-detail-value">${escapeHtml(r.admin_note)}</span>
                            </div>
                        ` : ''}

                        ${r.refund_proof_file ? `
                            <div class="refund-proof-link">
                                <a href="../download.php?path=${encodeURIComponent(r.refund_proof_file)}" class="btn btn-sm btn-outline-success" download>
                                    <i class="fas fa-download me-1"></i> Download Refund Proof
                                </a>
                            </div>
                        ` : ''}

                        <!-- Action buttons for pending refunds -->
                        ${r.status === 'pending' ? `
                            ${r.initiated_by === 'customer' ? `
                                <hr class="my-3">
                                <div class="d-flex gap-2">
                                    <button class="btn btn-success btn-sm" onclick="openRefundActionModal(${r.id}, 'approve')">
                                        <i class="fas fa-check-circle me-1"></i> Approve
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="openRefundActionModal(${r.id}, 'reject')">
                                        <i class="fas fa-times-circle me-1"></i> Reject
                                    </button>
                                </div>
                            ` : ''}
                            ${r.initiated_by === 'admin' && r.bank_details ? `
                                <hr class="my-3">
                                <div class="d-flex gap-2">
                                    <button class="btn btn-success btn-sm" onclick="openRefundActionModal(${r.id}, 'approve')">
                                        <i class="fas fa-check-circle me-1"></i> Approve
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="openRefundActionModal(${r.id}, 'reject')">
                                        <i class="fas fa-times-circle me-1"></i> Reject
                                    </button>
                                </div>
                            ` : ''}
                            ${r.initiated_by === 'admin' && !r.bank_details ? `
                                <hr class="my-3">
                                <div class="alert alert-info mb-0 py-2">
                                    <i class="fas fa-info-circle me-1"></i> Awaiting bank details from customer.
                                </div>
                            ` : ''}
                        ` : ''}

                        ${r.status === 'approved' && !r.refund_confirmed_at ? `
                            <hr class="my-3">
                            <div class="alert alert-warning mb-0 py-2">
                                <i class="fas fa-clock me-1"></i> Awaiting customer confirmation.
                            </div>
                        ` : ''}
                    </div>`;
        });
        container.innerHTML = html;
    }

    // ----- OPEN DETAIL MODAL (VEHICLE VERSION) -----
    function openDetailModalForRow(tr) {
        const data = tr.dataset.record;
        if (!data) return;
        let obj;
        try {
            obj = JSON.parse(data);
        } catch (e) {
            return;
        }
        const status = tr.dataset.status || 'Pending';
        const customerRequestId = tr.dataset.requestId;
        const requestType = tr.dataset.type;

        const statusInfo = {
            'Pending': { icon: 'fa-clock', class: 'status-Pending' },
            'Under Review': { icon: 'fa-eye', class: 'status-UnderReview' },
            'Awaiting Payment': { icon: 'fa-file-invoice', class: 'status-AwaitingPayment' },
            'Processing': { icon: 'fa-cog', class: 'status-Processing' },
            'Completed': { icon: 'fa-check-circle', class: 'status-Completed' },
            'Cancelled': { icon: 'fa-ban', class: 'status-Cancelled' },
            'Refund Requested': { icon: 'fa-undo', class: 'status-RefundRequested' },
            'Refunded': { icon: 'fa-check', class: 'status-Refunded' }
        };
        const si = statusInfo[status] || statusInfo['Pending'];

        const actions = getAllowedActions(status);
        let actionButtonsHtml = '';
        actions.forEach(a => {
            if (a.action === 'open_invoice_modal') {
                actionButtonsHtml += `<button type="button" class="btn btn-outline-primary btn-sm me-1" data-action="open_invoice_modal"><i class="fas ${a.icon} me-1"></i>${a.label}</button>`;
            } else if (a.action === 'cancel') {
                actionButtonsHtml += `<button type="button" class="btn btn-outline-danger btn-sm me-1" data-action="cancel"><i class="fas ${a.icon} me-1"></i>${a.label}</button>`;
            } else if (a.action === 'complete') {
                actionButtonsHtml += `<button type="button" class="btn btn-outline-success btn-sm me-1" data-action="complete"><i class="fas ${a.icon} me-1"></i>${a.label}</button>`;
            } else {
                actionButtonsHtml += `<button type="button" class="btn btn-outline-primary btn-sm me-1" data-action="${a.action}"><i class="fas ${a.icon} me-1"></i>${a.label}</button>`;
            }
        });

        const modalHtml = `
            <div class="modal-header border-bottom-0 pb-0">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-primary bg-opacity-10 p-3">
                        <i class="fas fa-car fa-2x text-primary"></i>
                    </div>
                    <div>
                        <h4 class="modal-title fw-bold mb-1">Vehicle Request #${obj.id}</h4>
                        <span class="badge bg-primary bg-opacity-10 text-dark px-3 py-2">
                            <i class="fas fa-car me-1"></i> Vehicle
                        </span>
                        ${obj.cancellation_reason ? `
                            <span class="badge bg-danger ms-2" title="${escapeHtml(obj.cancellation_reason)}">
                                <i class="fas fa-info-circle me-1"></i> Cancelled
                            </span>
                        ` : ''}
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-0">
                <!-- Status Bar -->
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 p-3 bg-light border">
                    <div class="d-flex align-items-center gap-3">
                        <span class="status-badge ${si.class} fs-6 px-3 py-2" id="d-modal-status" data-status="${status}">
                            <i class="fas ${si.icon} me-1"></i>${status}
                        </span>
                        <span class="text-muted small">ID: #${obj.id}</span>
                    </div>
                    <div id="modal-action-buttons" class="d-flex gap-2 flex-wrap">
                        ${actionButtonsHtml}
                    </div>
                </div>

                <!-- Customer & Vehicle Info -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="card h-100 border">
                            <div class="card-header bg-white border-bottom d-flex align-items-center gap-2 py-3">
                                <i class="fas fa-user text-primary"></i>
                                <h6 class="fw-semibold mb-0">Customer Information</h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between border-bottom pb-2">
                                            <span class="text-muted small">Full Name</span>
                                            <span class="fw-medium">${escapeHtml(obj.full_name)}</span>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between border-bottom pb-2">
                                            <span class="text-muted small">Date of Birth</span>
                                            <span>${obj.dob ? new Date(obj.dob).toLocaleDateString() : '—'}</span>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between border-bottom pb-2">
                                            <span class="text-muted small">Email</span>
                                            <span class="text-primary">${escapeHtml(obj.email)}</span>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between pb-2">
                                            <span class="text-muted small">Phone</span>
                                            <span>${escapeHtml(obj.phone)}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100 border">
                            <div class="card-header bg-white border-bottom d-flex align-items-center gap-2 py-3">
                                <i class="fas fa-car text-primary"></i>
                                <h6 class="fw-semibold mb-0">Vehicle Details</h6>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between border-bottom pb-2">
                                            <span class="text-muted small">Plate</span>
                                            <span class="fw-bold text-primary">${escapeHtml(obj.plate)}</span>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between border-bottom pb-2">
                                            <span class="text-muted small">VIN</span>
                                            <span>${escapeHtml(obj.vin) || '—'}</span>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between border-bottom pb-2">
                                            <span class="text-muted small">Reg Expiry</span>
                                            <span>${obj.reg_exp ? new Date(obj.reg_exp).toLocaleDateString() : '—'}</span>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="d-flex justify-content-between pb-2">
                                            <span class="text-muted small">Renewal</span>
                                            <span>${escapeHtml(obj.renew_when) || '—'}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Customer Documents -->
                <div class="card border mb-4">
                    <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-3">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-paperclip text-primary"></i>
                            <h6 class="fw-semibold mb-0">Customer Documents</h6>
                        </div>
                        <span class="badge bg-secondary">${obj.attachments?.length || 0} files</span>
                    </div>
                    <div class="card-body">
                        <div id="d-files-container">${generateFilesGrid(obj.attachments || [])}</div>
                    </div>
                </div>

                <!-- Invoice & Payment Section -->
                <div class="card border mb-4">
                    <div class="card-header bg-white border-bottom d-flex align-items-center gap-2 py-3">
                        <i class="fas fa-file-invoice-dollar text-primary"></i>
                        <h6 class="fw-semibold mb-0">Invoice & Payment</h6>
                    </div>
                    <div class="card-body">
                        <div id="invoice-section" data-request-id="${customerRequestId}" data-request-type="${requestType}" data-status="${status}">
                            <div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div> Loading...</div>
                        </div>
                    </div>
                </div>

                <!-- Request Additional Documents -->
                <div class="card border mb-4">
                    <div class="card-header bg-white border-bottom d-flex align-items-center gap-2 py-3">
                        <i class="fas fa-file-upload text-primary"></i>
                        <h6 class="fw-semibold mb-0">Request Additional Documents</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <textarea id="doc-request-message" class="form-control" rows="2" placeholder="Describe what documents are needed..."></textarea>
                            <div class="invalid-feedback">Please enter a message.</div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <button class="btn btn-primary px-4" id="submit-doc-request-btn" 
                                    data-request-id="${customerRequestId}" 
                                    data-request-type="${requestType}">
                                <i class="fas fa-paper-plane me-1"></i>Send Request
                            </button>
                            <small class="text-muted">Customer will see this request in their dashboard.</small>
                        </div>
                    </div>
                </div>

                <!-- Document Request History -->
                <div class="card border mb-4">
                    <div class="card-header bg-white border-bottom d-flex align-items-center gap-2 py-3">
                        <i class="fas fa-history text-primary"></i>
                        <h6 class="fw-semibold mb-0">Document Request History</h6>
                    </div>
                    <div class="card-body p-0">
                        <div id="doc-request-history" data-request-id="${customerRequestId}">
                            <div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div> Loading...</div>
                        </div>
                    </div>
                </div>

                <!-- Refund Requests Section -->
                <div class="card border mb-4">
                    <div class="card-header bg-white border-bottom d-flex align-items-center gap-2 py-3">
                        <i class="fas fa-undo-alt text-primary"></i>
                        <h6 class="fw-semibold mb-0">Refund Requests</h6>
                    </div>
                    <div class="card-body p-3">
                        <div id="refund-section" data-request-id="${customerRequestId}">
                            <div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div> Loading...</div>
                        </div>
                    </div>
                </div>

                <!-- Receipt Section (if completed) -->
                <div id="receipt-section" data-request-id="${customerRequestId}" data-request-type="${requestType}" class="mt-4" style="${status === 'Completed' ? '' : 'display:none;'}"></div>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <small class="text-muted me-auto">Submitted: ${new Date(obj.created_at).toLocaleString()}</small>
                <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Close</button>
            </div>
        `;

        const modalContent = detailModalEl.querySelector('.modal-content');
        modalContent.innerHTML = modalHtml;
        detailModalEl._currentRow = tr;
        detailModal.show();

        // Attach modal action buttons
        document.querySelectorAll('#modal-action-buttons [data-action]').forEach(btn => {
            btn.addEventListener('click', function(e) {
                const action = this.dataset.action;
                if (action === 'open_invoice_modal') {
                    openInvoiceModal(customerRequestId, requestType);
                } else if (action === 'cancel') {
                    openCancelModal(tr);
                } else if (action === 'complete') {
                    openCompleteModal(tr);
                } else {
                    doActionForRow(tr, action);
                }
            });
        });

        // Document request submission
        const submitBtn = document.getElementById('submit-doc-request-btn');
        if (submitBtn) {
            submitBtn.addEventListener('click', async function(e) {
                e.preventDefault();
                const requestId = this.dataset.requestId;
                const requestType = this.dataset.requestType;
                const message = document.getElementById('doc-request-message').value.trim();
                if (!message) {
                    showToast('Validation', 'Please enter a message describing the documents needed.', 'warning');
                    return;
                }
                this.disabled = true;
                this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Sending...';
                try {
                    const formData = new FormData();
                    formData.append('request_id', requestId);
                    formData.append('request_type', requestType);
                    formData.append('message', message);
                    const resp = await fetch(requestDocumentUrl, { method: 'POST', body: formData });
                    const result = await resp.json();
                    if (result.success) {
                        showToast('Success', 'Document request sent.', 'success');
                        document.getElementById('doc-request-message').value = '';
                        loadDocumentRequestHistory(requestId, requestType);
                    } else {
                        showToast('Error', result.message, 'danger');
                    }
                } catch (err) {
                    showToast('Network Error', err.message, 'danger');
                } finally {
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Send Request';
                }
            });
        }

        // Load invoice, document history, refunds
        loadInvoiceData(customerRequestId, requestType, status);
        loadDocumentRequestHistory(customerRequestId, requestType);
        loadRefundData(customerRequestId, requestType);
        if (status === 'Completed') loadReceiptData(customerRequestId, requestType);
    }

    // Helper to update modal status bar
    function updateModalStatusBar(newStatus) {
        const statusSpan = document.getElementById('d-modal-status');
        if (!statusSpan) return;
        const statusInfo = {
            'Pending': { icon: 'fa-clock', class: 'status-Pending' },
            'Under Review': { icon: 'fa-eye', class: 'status-UnderReview' },
            'Awaiting Payment': { icon: 'fa-file-invoice', class: 'status-AwaitingPayment' },
            'Processing': { icon: 'fa-cog', class: 'status-Processing' },
            'Completed': { icon: 'fa-check-circle', class: 'status-Completed' },
            'Cancelled': { icon: 'fa-ban', class: 'status-Cancelled' },
            'Refund Requested': { icon: 'fa-undo', class: 'status-RefundRequested' },
            'Refunded': { icon: 'fa-check', class: 'status-Refunded' }
        };
        const si = statusInfo[newStatus] || statusInfo['Pending'];
        statusSpan.className = `status-badge ${si.class} fs-6 px-3 py-2`;
        statusSpan.dataset.status = newStatus;
        statusSpan.innerHTML = `<i class="fas ${si.icon} me-1"></i>${newStatus}`;

        const actions = getAllowedActions(newStatus);
        let actionButtonsHtml = '';
        actions.forEach(a => {
            if (a.action === 'open_invoice_modal') {
                actionButtonsHtml += `<button type="button" class="btn btn-outline-primary btn-sm me-1" data-action="open_invoice_modal"><i class="fas ${a.icon} me-1"></i>${a.label}</button>`;
            } else if (a.action === 'cancel') {
                actionButtonsHtml += `<button type="button" class="btn btn-outline-danger btn-sm me-1" data-action="cancel"><i class="fas ${a.icon} me-1"></i>${a.label}</button>`;
            } else if (a.action === 'complete') {
                actionButtonsHtml += `<button type="button" class="btn btn-outline-success btn-sm me-1" data-action="complete"><i class="fas ${a.icon} me-1"></i>${a.label}</button>`;
            } else {
                actionButtonsHtml += `<button type="button" class="btn btn-outline-primary btn-sm me-1" data-action="${a.action}"><i class="fas ${a.icon} me-1"></i>${a.label}</button>`;
            }
        });
        const actionContainer = document.getElementById('modal-action-buttons');
        if (actionContainer) actionContainer.innerHTML = actionButtonsHtml;
    }

    // ----- HELPER FUNCTIONS -----
    function escapeHtml(unsafe) {
        if (!unsafe) return '';
        return String(unsafe)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatFileSize(bytes) {
        if (!bytes) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB'];
        let i = 0;
        while (bytes >= 1024 && i < units.length - 1) { bytes /= 1024; i++; }
        return bytes.toFixed(1) + ' ' + units[i];
    }

    function generateFilesGrid(attachments) {
        if (!attachments || attachments.length === 0) {
            return `<div class="empty-state text-center py-4"><i class="fas fa-folder-open fa-2x text-muted mb-2"></i><p class="text-muted">No files attached</p></div>`;
        }
        let html = `<div class="files-grid">`;
        attachments.forEach(f => {
            const isImage = f.mime_type?.startsWith('image/');
            const dlUrl = `../download.php?id=${encodeURIComponent(f.id)}&mode=download`;
            const viewUrl = `../download.php?id=${encodeURIComponent(f.id)}&mode=view`;
            html += `<div class="file-card">
                        <div class="file-icon"><i class="fas ${isImage ? 'fa-image' : 'fa-file-alt'}"></i></div>
                        <div class="file-info">
                            <div class="file-name">${escapeHtml(f.file_name)}</div>
                            <div class="file-meta">${formatFileSize(f.file_size)}</div>
                        </div>
                        <div class="file-actions">
                            ${isImage ? `<button class="btn btn-sm btn-outline-primary preview-btn" data-src="${viewUrl}"><i class="fas fa-eye"></i></button>` : ''}
                            <a href="${dlUrl}" class="btn btn-sm btn-outline-primary" download><i class="fas fa-download"></i></a>
                        </div>
                    </div>`;
        });
        html += `</div>`;
        return html;
    }

    // ----- LOAD RECEIPT DATA -----
    async function loadReceiptData(requestId, type) {
        const container = document.getElementById('receipt-section');
        if (!container) return;
        try {
            const resp = await fetch(`${getReceiptUrl}?request_id=${requestId}&type=${type}`);
            const data = await resp.json();
            if (data.receipt) {
                container.innerHTML = `<div class="p-3" style="background:#f0fdf4; border:1px solid #bbf7d0;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="fw-semibold"><i class="fas fa-receipt me-2 text-success"></i>Receipt #${escapeHtml(data.receipt.receipt_number)}</span>
                            <span class="ms-2">Paid: $${Number(data.receipt.amount_paid).toFixed(2)}</span>
                            <span class="ms-2 text-muted">${new Date(data.receipt.paid_at).toLocaleDateString()}</span>
                        </div>
                        <a href="receipt_view.php?id=${data.receipt.id}" target="_blank" class="btn btn-sm btn-outline-success">
                            <i class="fas fa-print me-1"></i>View
                        </a>
                    </div>
                </div>`;
            }
        } catch (e) {}
    }

    // ----- INVOICE GENERATION -----
    document.getElementById('submitInvoiceBtn').addEventListener('click', async function() {
        const btn = this;
        const originalHtml = btn.innerHTML;
        const form = document.getElementById('invoiceForm');
        const formData = new FormData(form);
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Generating...';
        try {
            const resp = await fetch(generateInvoiceUrl, { method: 'POST', body: formData });
            const res = await resp.json();
            if (res.success) {
                const modal = bootstrap.Modal.getInstance(document.getElementById('invoiceModal'));
                if (modal) modal.hide();
                const requestId = document.getElementById('invoice_request_id').value;
                const requestType = document.getElementById('invoice_request_type').value;
                await loadInvoiceData(requestId, requestType, 'Awaiting Payment');
                const tr = document.getElementById('detailModal')._currentRow;
                if (tr && tr.dataset.requestId === requestId) {
                    tr.dataset.status = 'Awaiting Payment';
                    const badge = tr.querySelector('.status-badge');
                    if (badge) {
                        badge.className = 'status-badge status-AwaitingPayment';
                        badge.textContent = 'Awaiting Payment';
                        badge.dataset.status = 'Awaiting Payment';
                    }
                    refreshDropdown(tr);
                    updateModalStatusBar('Awaiting Payment');
                }
                showToast('Success', 'Invoice generated and status updated to Awaiting Payment.', 'success');
            } else {
                showToast('Error', res.message || 'Failed to generate invoice', 'danger');
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        } catch (error) {
            console.error('Invoice generation error:', error);
            showToast('Error', 'Network error. Please try again.', 'danger');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    });

    document.getElementById('invoiceModal').addEventListener('hidden.bs.modal', function() {
        const btn = document.getElementById('submitInvoiceBtn');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-file-invoice me-2"></i>Generate Invoice';
        }
    });

    // ----- PAYMENT CONFIRMATION -----
    document.getElementById('submitPaymentBtn').addEventListener('click', async function() {
        const btn = this;
        const originalHtml = btn.innerHTML;
        const form = document.getElementById('paymentForm');
        const formData = new FormData(form);
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Processing...';
        try {
            const resp = await fetch(confirmPaymentUrl, { method: 'POST', body: formData });
            const res = await resp.json();
            if (res.success) {
                const modal = bootstrap.Modal.getInstance(document.getElementById('paymentModal'));
                if (modal) modal.hide();
                const requestId = document.querySelector('#detailModal .modal-content [data-request-id]')?.dataset.requestId;
                if (requestId) {
                    await loadInvoiceData(requestId,
                        document.querySelector('#detailModal .modal-content [data-request-type]')?.dataset.requestType,
                        'Processing');
                    if (formData.get('status') === 'confirmed') {
                        const tr = document.getElementById('detailModal')._currentRow;
                        if (tr) {
                            tr.dataset.status = 'Processing';
                            const badge = tr.querySelector('.status-badge');
                            if (badge) {
                                badge.className = 'status-badge status-Processing';
                                badge.textContent = 'Processing';
                                badge.dataset.status = 'Processing';
                            }
                            refreshDropdown(tr);
                            updateModalStatusBar('Processing');
                        }
                    }
                }
                showToast('Success', 'Payment status updated.', 'success');
            } else {
                showToast('Error', res.message || 'Failed to update payment', 'danger');
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        } catch (error) {
            console.error('Payment confirmation error:', error);
            showToast('Error', 'Network error. Please try again.', 'danger');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        }
    });

    document.getElementById('paymentModal').addEventListener('hidden.bs.modal', function() {
        const btn = document.getElementById('submitPaymentBtn');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = 'Confirm';
        }
    });

    document.getElementById('refundActionModal').addEventListener('hidden.bs.modal', function() {
        const btn = document.getElementById('confirmRefundActionBtn');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = 'Submit';
        }
    });

    // ----- EXPORT STUB -----
    function exportData() {
        showToast('Info', 'Export feature coming soon.', 'info');
    }
</script>
</body>

</html>