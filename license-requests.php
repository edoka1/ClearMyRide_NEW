<?php
// license-requests.php
session_start();
require_once __DIR__ . '/assets/app/Auth.php';
require_once __DIR__ . '/assets/app/db_connect.php';
require_once __DIR__ . '/assets/app/alerts.php';
require_once __DIR__ . '/assets/app/config.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: ' . base_url('index.php?redirect=license-requests.php'));
    exit;
}
$user = $auth->getCurrentUser();
$userId = $user['id'];

// ---------- PAGINATION, FILTERS, SORT ----------
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$payment = isset($_GET['payment']) ? trim($_GET['payment']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'asc' : 'desc';

$allowedSortColumns = ['id', 'license_number', 'created_at', 'status', 'payment_status'];
if (!in_array($sort, $allowedSortColumns)) $sort = 'created_at';

// ---------- BASE QUERY (USING FOREIGN KEY customer_request_id) ----------
$baseQuery = "FROM customer_requests cr
              INNER JOIN license_requests lr ON cr.id = lr.customer_request_id
              LEFT JOIN invoices i ON cr.invoice_id = i.id
              WHERE cr.user_id = :user_id AND cr.request_type = 'license'";
$params = [':user_id' => $userId];

// 🔧 FIX: Use distinct named placeholders for each search field
if ($search) {
    $baseQuery .= " AND (lr.license_number LIKE :search_license OR lr.full_name LIKE :search_name)";
    $params[':search_license'] = "%$search%";
    $params[':search_name']    = "%$search%";
}
if ($status) {
    $baseQuery .= " AND cr.status = :status";
    $params[':status'] = $status;
}
if ($payment) {
    $baseQuery .= " AND i.status = :payment";
    $params[':payment'] = $payment;
}

// ---------- COUNT TOTAL ----------
$countSql = "SELECT COUNT(*) $baseQuery";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $limit);

// ---------- SORTING ----------
switch ($sort) {
    case 'id': $orderClause = "ORDER BY cr.id $order"; break;
    case 'license_number': $orderClause = "ORDER BY lr.license_number $order"; break;
    case 'created_at': $orderClause = "ORDER BY cr.created_at $order"; break;
    case 'status': $orderClause = "ORDER BY cr.status $order"; break;
    case 'payment_status': $orderClause = "ORDER BY i.status $order"; break;
    default: $orderClause = "ORDER BY cr.created_at DESC";
}

// ---------- MAIN QUERY ----------
$sql = "
    SELECT cr.id, cr.status, cr.created_at, 
           lr.license_number, lr.full_name, lr.dob, lr.email, lr.phone,
           i.status as invoice_status
    $baseQuery
    $orderClause
    LIMIT :limit OFFSET :offset
";

// Add limit and offset to the parameters array
$params[':limit'] = (int)$limit;
$params[':offset'] = (int)$offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>License Requests – ClearMyRide</title>
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
        /* ----- SHARP, NO ROUNDED CORNERS – CLEAN & MODERN ----- */
        * { border-radius: 0 !important; }
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

        /* ----- STICKY SIDEBAR (exactly like profile) ----- */
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
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
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
        .sidebar-nav .nav-item i { width: 18px; color: #718096; }
        .sidebar-nav .nav-item:hover { background: #f7fafc; border-left-color: #0A57FF; }
        .sidebar-nav .nav-item.active { background: #ebf8ff; border-left-color: #0A57FF; color: #0A57FF; }
        .sidebar-nav .nav-item.active i { color: #0A57FF; }
        .sidebar-nav .nav-item.logout { color: #e53e3e; }
        .sidebar-nav .nav-item.logout i { color: #e53e3e; }
        .sidebar-divider { height: 1px; background: #edf2f7; margin: 1rem 0; }

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
        .page-header p { color: #718096; margin-bottom: 0; }

        /* ----- FILTER BAR ----- */
        .filter-bar {
            background: white;
            border: 1px solid #edf2f7;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }

        /* ----- CARDS ----- */
        .card {
            border: 1px solid #edf2f7;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            background: white;
        }
        .card-body { padding: 1.5rem; }

        /* ----- TABLES ----- */
        .table { margin-bottom: 0; }
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
        .table-hover tbody tr:hover { background: #f7fafc; }

        /* ----- BADGES (same as dashboard) ----- */
        .badge {
            padding: 0.5rem 0.75rem;
            font-weight: 500;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border: 1px solid transparent;
        }
        .badge.bg-secondary { background: #edf2f7 !important; color: #1a202c; border-color: #e2e8f0; }
        .badge.bg-info { background: #ebf8ff !important; color: #0A57FF; border-color: #90cdf4; }
        .badge.bg-warning { background: #fffaf0 !important; color: #dd6b20; border-color: #fbd38d; }
        .badge.bg-success { background: #f0fff4 !important; color: #276749; border-color: #9ae6b4; }
        .badge.bg-danger { background: #fff5f5 !important; color: #c53030; border-color: #feb2b2; }
        .badge.bg-primary { background: #ebf8ff !important; color: #0A57FF; border-color: #90cdf4; }

        /* ----- BUTTONS ----- */
        .btn {
            border-radius: 0;
            font-weight: 600;
            padding: 0.5rem 1.25rem;
            font-size: 0.875rem;
        }
        .btn-primary { background: #0A57FF; border: 1px solid #0A57FF; color: white; }
        .btn-primary:hover { background: #0845cc; border-color: #0845cc; }
        .btn-outline-primary { border: 1px solid #0A57FF; color: #0A57FF; }
        .btn-outline-primary:hover { background: #0A57FF; color: white; }
        .btn-outline-secondary { border: 1px solid #e0e5ec; color: #4a5568; }
        .btn-outline-secondary:hover { background: #edf2f7; border-color: #cbd5e0; }

        /* ----- PAGINATION ----- */
        .pagination .page-link {
            border-radius: 0;
            color: #4a5568;
            border-color: #edf2f7;
            padding: 0.5rem 1rem;
        }
        .pagination .page-item.active .page-link {
            background: #0A57FF;
            border-color: #0A57FF;
            color: white;
        }

        /* ----- SORT LINKS ----- */
        .sort-link {
            text-decoration: none;
            color: inherit;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .sort-link:hover { color: #0A57FF; }

        /* ----- FORMS ----- */
        .form-label {
            font-weight: 600;
            font-size: 0.875rem;
            color: #4a5568;
            margin-bottom: 0.375rem;
        }
        .form-control, .form-select {
            border-radius: 0;
            border: 1px solid #e0e5ec;
            padding: 0.5rem 0.75rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: #0A57FF;
            box-shadow: none;
        }
        .input-group-text {
            border-radius: 0;
            border: 1px solid #e0e5ec;
            background: white;
        }
    </style>
</head>
<body>
    <?php include 'partials/nav.php'; ?>

    <main class="dashboard-container py-0">
        <?php show_flash(); ?>

        <div class="row gx-4">
            <!-- ===== SIDEBAR (STICKY, EXACTLY LIKE PROFILE) ===== -->
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
                            <a href="<?php echo base_url('dashboard.php'); ?>" class="nav-item">
                                <i class="fas fa-chart-pie"></i> Dashboard
                            </a>
                             <a href="<?php echo base_url('new-request.php'); ?>" class="nav-item">
                                <i class="fas fa-plus-circle"></i> New Request
                            </a>
                            <a href="<?php echo base_url('vehicle-requests.php'); ?>" class="nav-item">
                                <i class="fas fa-car"></i> Vehicle Requests
                            </a>
                            <a href="<?php echo base_url('license-requests.php'); ?>" class="nav-item active">
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
                <!-- Support Widget -->
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
                        <h1><i class="fas fa-id-card me-3" style="color: #0A57FF;"></i> License Requests</h1>
                        <p class="text-muted mb-0">Manage your driver's license renewal and clearance requests</p>
                    </div>
                    <a href="<?php echo base_url('new-request.php'); ?>" class="btn btn-primary px-4 py-2 fw-semibold">
                        <i class="fas fa-plus me-2"></i>New Request
                    </a>
                </div>

                <!-- Filter Bar -->
                <div class="filter-bar">
                    <form method="get" class="d-flex flex-wrap align-items-end gap-3">
                        <div class="flex-grow-1" style="min-width:200px;">
                            <label class="form-label fw-semibold small text-uppercase mb-1">Search</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" name="search" class="form-control border-start-0 ps-0"
                                       placeholder="License number, name..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div style="min-width:150px;">
                            <label class="form-label fw-semibold small text-uppercase mb-1">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All statuses</option>
                                <option value="Pending" <?php echo $status == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="Under Review" <?php echo $status == 'Under Review' ? 'selected' : ''; ?>>Under Review</option>
                                <option value="Awaiting Payment" <?php echo $status == 'Awaiting Payment' ? 'selected' : ''; ?>>Awaiting Payment</option>
                                <option value="Processing" <?php echo $status == 'Processing' ? 'selected' : ''; ?>>Processing</option>
                                <option value="Completed" <?php echo $status == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="Cancelled" <?php echo $status == 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                <option value="Refund Requested" <?php echo $status == 'Refund Requested' ? 'selected' : ''; ?>>Refund Requested</option>
                                <option value="Refunded" <?php echo $status == 'Refunded' ? 'selected' : ''; ?>>Refunded</option>
                            </select>
                        </div>
                        <div style="min-width:150px;">
                            <label class="form-label fw-semibold small text-uppercase mb-1">Payment</label>
                            <select name="payment" class="form-select">
                                <option value="">All payments</option>
                                <option value="sent" <?php echo $payment == 'sent' ? 'selected' : ''; ?>>Pending</option>
                                <option value="paid" <?php echo $payment == 'paid' ? 'selected' : ''; ?>>Paid</option>
                            </select>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="fas fa-filter me-2"></i>Apply
                            </button>
                            <a href="<?php echo base_url('license-requests.php'); ?>" class="btn btn-outline-secondary px-4">
                                <i class="fas fa-times me-2"></i>Clear
                            </a>
                        </div>
                        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                        <input type="hidden" name="order" value="<?php echo htmlspecialchars($order); ?>">
                    </form>
                </div>

                <!-- Requests Table -->
                <?php if (empty($requests)): ?>
                    <div class="card text-center py-5">
                        <div class="card-body">
                            <i class="fas fa-id-card fa-3x text-muted mb-3"></i>
                            <p class="text-muted fs-5 mb-3">No license requests found.</p>
                            <a href="<?php echo base_url('new-request.php'); ?>" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Start a License Request
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 align-middle">
                                    <thead>
                                        <tr>
                                            <th>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'id', 'order' => ($sort == 'id' && $order == 'asc') ? 'desc' : 'asc'])); ?>" class="sort-link">
                                                    # <?php if ($sort == 'id'): ?><i class="fas fa-chevron-<?php echo $order == 'asc' ? 'up' : 'down'; ?> ms-1"></i><?php endif; ?>
                                                </a>
                                            </th>
                                            <th>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'license_number', 'order' => ($sort == 'license_number' && $order == 'asc') ? 'desc' : 'asc'])); ?>" class="sort-link">
                                                    License # <?php if ($sort == 'license_number'): ?><i class="fas fa-chevron-<?php echo $order == 'asc' ? 'up' : 'down'; ?> ms-1"></i><?php endif; ?>
                                                </a>
                                            </th>
                                            <th>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'created_at', 'order' => ($sort == 'created_at' && $order == 'asc') ? 'desc' : 'asc'])); ?>" class="sort-link">
                                                    Submitted <?php if ($sort == 'created_at'): ?><i class="fas fa-chevron-<?php echo $order == 'asc' ? 'up' : 'down'; ?> ms-1"></i><?php endif; ?>
                                                </a>
                                            </th>
                                            <th>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'status', 'order' => ($sort == 'status' && $order == 'asc') ? 'desc' : 'asc'])); ?>" class="sort-link">
                                                    Status <?php if ($sort == 'status'): ?><i class="fas fa-chevron-<?php echo $order == 'asc' ? 'up' : 'down'; ?> ms-1"></i><?php endif; ?>
                                                </a>
                                            </th>
                                            <th>
                                                <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'payment_status', 'order' => ($sort == 'payment_status' && $order == 'asc') ? 'desc' : 'asc'])); ?>" class="sort-link">
                                                    Payment <?php if ($sort == 'payment_status'): ?><i class="fas fa-chevron-<?php echo $order == 'asc' ? 'up' : 'down'; ?> ms-1"></i><?php endif; ?>
                                                </a>
                                            </th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $rowNumber = $offset + 1; ?>
                                        <?php foreach ($requests as $req): ?>
                                        <tr>
                                            <td class="fw-medium"><?php echo $rowNumber++; ?></td>
                                            <td><?php echo htmlspecialchars($req['license_number'] ?? 'N/A'); ?></td>
                                            <td class="text-muted"><?php echo date('M j, Y', strtotime($req['created_at'])); ?></td>
                                            <td>
                                                <?php
                                                $statusClass = match($req['status']) {
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
                                                $statusIcon = match($req['status']) {
                                                    'Pending' => 'clock',
                                                    'Under Review' => 'search',
                                                    'Awaiting Payment' => 'file-invoice',
                                                    'Processing' => 'cog',
                                                    'Completed' => 'check-circle',
                                                    'Cancelled' => 'times-circle',
                                                    'Refund Requested' => 'undo',
                                                    'Refunded' => 'check',
                                                    default => 'circle'
                                                };
                                                ?>
                                                <span class="badge <?php echo $statusClass; ?> px-3 py-2">
                                                    <i class="fas fa-<?php echo $statusIcon; ?> me-1"></i>
                                                    <?php echo $req['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php $paymentStatus = $req['invoice_status'] ?? 'sent'; ?>
                                                <span class="badge bg-<?php echo $paymentStatus == 'paid' ? 'success' : 'warning'; ?> px-3 py-2">
                                                    <i class="fas fa-<?php echo $paymentStatus == 'paid' ? 'check-circle' : 'hourglass-half'; ?> me-1"></i>
                                                    <?php echo $paymentStatus == 'paid' ? 'Paid' : 'Pending'; ?>
                                                </span>
                                            </td>
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
                        </div>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php
                            $query = $_GET;
                            unset($query['page']);
                            $base = base_url('license-requests.php?' . http_build_query($query) . '&page=');
                            ?>
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $base . ($page - 1); ?>">
                                    <i class="fas fa-chevron-left me-1"></i>Previous
                                </a>
                            </li>
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php echo $base . $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $base . ($page + 1); ?>">
                                    Next <i class="fas fa-chevron-right ms-1"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>