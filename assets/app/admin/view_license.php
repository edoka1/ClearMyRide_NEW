<?php
// assets/app/admin/view_license.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db_connect.php';

// pagination settings
$perPage = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

// --- top-level counts (Total / Pending(received) / Processing / Processed) ---
// treat 'received', NULL, '', 'pending', 'new' as pending/received
$totalCount = (int)$pdo->query("SELECT COUNT(*) FROM license_requests")->fetchColumn();
$receivedCount = (int)$pdo->query(
  "SELECT COUNT(*) FROM license_requests WHERE status = 'received' OR status IS NULL OR status = '' OR LOWER(status) IN ('pending','new')"
)->fetchColumn();
$processingCount = (int)$pdo->query(
  "SELECT COUNT(*) FROM license_requests WHERE LOWER(status) = 'processing'"
)->fetchColumn();
// processed in DB may be 'processed' or 'complete'
$processedCount = (int)$pdo->query(
  "SELECT COUNT(*) FROM license_requests WHERE LOWER(status) IN ('processed','complete')"
)->fetchColumn();

// --- read search and filter inputs (GET) ---
$search = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$filterStatus = isset($_GET['status']) ? trim((string)$_GET['status']) : '';

// normalize filterStatus: user-facing 'processed' maps to DB 'complete' or 'processed' - we'll handle in SQL
// allowed friendly statuses: received, processing, processed

// Build WHERE parts and parameters safely
$whereParts = [];
$execParams = [];

// search across multiple columns: full_name, email, phone, license_number
$search = (string)$search;
if ($search !== '') {
  $cols = ['full_name', 'email', 'phone', 'license_number'];
  $likeParts = [];
  foreach ($cols as $idx => $col) {
    $ph = "q{$idx}";
    $likeParts[] = "{$col} LIKE :{$ph}";
    $execParams[$ph] = '%' . $search . '%';
  }
  if (!empty($likeParts)) {
    $whereParts[] = '(' . implode(' OR ', $likeParts) . ')';
  }
}

// status filter
$filterStatus = (string)$filterStatus;
if ($filterStatus !== '') {
  if ($filterStatus === 'received') {
    $whereParts[] = "(status = 'received' OR status IS NULL OR status = '' OR LOWER(status) IN ('pending','new'))";
  } elseif ($filterStatus === 'processing') {
    $whereParts[] = "LOWER(status) = 'processing'";
  } elseif ($filterStatus === 'processed') {
    // handle both 'processed' and 'complete' in DB
    $whereParts[] = "LOWER(status) IN ('processed','complete')";
  }
}

// final WHERE SQL
$whereSql = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';

// filtered count for UI & pagination
$countSql = "SELECT COUNT(*) FROM license_requests {$whereSql}";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($execParams);
$filteredCount = (int)$countStmt->fetchColumn();

// pagination calculations
$totalPages = (int)max(1, ceil($filteredCount / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

// main select — use explicit int for LIMIT/OFFSET to avoid param issues
$limit = (int)$perPage;
$off = (int)$offset;
$sql = "SELECT * FROM license_requests {$whereSql} ORDER BY id DESC LIMIT {$limit} OFFSET {$off}";
$stmt = $pdo->prepare($sql);
$stmt->execute($execParams);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$startNumber = $offset + 1;
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>License Requests - ClearMyRide Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="icon" href="../../images/favicon.png" type="image/png">
  <style>
    :root {
      --primary: #2c3e50;
      --secondary: #7f8c8d;
      --light: #f8f9fa;
      --accent: #3498db;
      --success: #27ae60;
      --warning: #f39c12;
      --danger: #e74c3c;
      --border: #e1e8ed;
      --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
      --hover-shadow: 0 10px 15px rgba(0, 0, 0, 0.07);
    }

    body {
      background-color: #f5f7fa;
      font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
      color: #2c3e50;
      line-height: 1.5;
    }

    .navbar-brand {
      font-weight: 600;
    }

    /* Cards */
    .stat-card {
      background: white;
      border-radius: 12px;
      box-shadow: var(--card-shadow);
      padding: 1.5rem;
      transition: all 0.2s ease;
      border: none;
      height: 100%;
    }

    .stat-card:hover {
      transform: translateY(-3px);
      box-shadow: var(--hover-shadow);
    }

    .stat-icon {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
    }

    .stat-value {
      font-size: 1.75rem;
      font-weight: 600;
      margin-bottom: 0.25rem;
    }

    .stat-label {
      font-size: 0.875rem;
      color: var(--secondary);
    }

    /* Main content card */
    .main-card {
      background: white;
      border-radius: 16px;
      box-shadow: var(--card-shadow);
      border: none;
      overflow: hidden;
    }

    .card-header {
      background: white;
      border-bottom: 1px solid var(--border);
      padding: 1.5rem 1.5rem 0.75rem;
    }

    .card-title {
      font-weight: 600;
      color: var(--primary);
      margin-bottom: 0.25rem;
    }

    /* Table */
    .table {
      margin-bottom: 0;
    }

    .table thead th {
      border-bottom: 1px solid var(--border);
      font-weight: 500;
      font-size: 0.85rem;
      color: var(--secondary);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      padding: 1rem 1.5rem;
    }

    .table tbody td {
      padding: 1.25rem 1.5rem;
      vertical-align: middle;
      border-bottom: 1px solid var(--border);
    }

    .table tbody tr {
      transition: background-color 0.15s ease;
    }

    .table tbody tr:last-child td {
      border-bottom: none;
    }

    .table tbody tr:hover {
      background-color: rgba(52, 152, 219, 0.03);
    }

    /* Status badges */
    .status-badge {
      font-size: 0.75rem;
      font-weight: 500;
      padding: 0.375rem 0.75rem;
      border-radius: 20px;
      display: inline-block;
      min-width: 90px;
      text-align: center;
    }

    .status-received {
      background-color: rgba(243, 156, 18, 0.1);
      color: #d35400;
    }

    .status-processing {
      background-color: rgba(52, 152, 219, 0.1);
      color: #2980b9;
    }

    .status-complete {
      background-color: rgba(39, 174, 96, 0.1);
      color: #27ae60;
    }

    /* Buttons */
    .btn {
      border-radius: 8px;
      font-weight: 500;
      padding: 0.5rem 1rem;
    }

    .btn-outline-primary {
      border-color: var(--accent);
      color: var(--accent);
    }

    .btn-outline-primary:hover {
      background-color: var(--accent);
      border-color: var(--accent);
    }

    .btn-primary {
      background-color: var(--accent);
      border-color: var(--accent);
    }

    .btn-light {
      background-color: #f8f9fa;
      border-color: #e9ecef;
    }

    /* Form controls */
    .form-control,
    .form-select {
      border-radius: 8px;
      border: 1px solid var(--border);
      padding: 0.5rem 0.75rem;
    }

    .form-control:focus,
    .form-select:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.15);
    }

    /* Pagination */
    .pagination {
      margin-bottom: 0;
    }

    .page-link {
      border: none;
      color: var(--secondary);
      padding: 0.5rem 0.75rem;
      border-radius: 6px;
      margin: 0 2px;
    }

    .page-link:hover {
      background-color: rgba(52, 152, 219, 0.1);
      color: var(--accent);
    }

    .page-item.active .page-link {
      background-color: var(--accent);
      color: white;
    }

    .page-item.disabled .page-link {
      color: #bdc3c7;
      background-color: transparent;
    }

    /* Modal */
    .modal-content {
      border-radius: 16px;
      border: none;
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    }

    .modal-header {
      border-bottom: 1px solid var(--border);
      padding: 1.5rem;
    }

    .modal-body {
      padding: 1.5rem;
    }

    .modal-footer {
      border-top: 1px solid var(--border);
      padding: 1.25rem 1.5rem;
    }

    /* Thumbnails */
    .thumb {
      width: 80px;
      height: 56px;
      object-fit: cover;
      border-radius: 8px;
      border: 1px solid var(--border);
    }

    .thumb-btn {
      border: none;
      background: transparent;
      padding: 0;
    }

    .file-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 12px 0;
      border-bottom: 1px solid var(--border);
    }

    .file-row:last-child {
      border-bottom: 0;
    }

    /* Lightbox */
    .lightbox-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.9);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 12000;
      padding: 20px;
    }

    .lightbox-backdrop.active {
      display: flex;
    }

    .lightbox-content {
      max-width: 1200px;
      max-height: 92vh;
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
    }

    .lightbox-img {
      max-width: 100%;
      max-height: 100%;
      border-radius: 8px;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.6);
    }

    .lightbox-close {
      position: absolute;
      right: 6px;
      top: 6px;
      background: rgba(0, 0, 0, 0.4);
      border: 0;
      color: #fff;
      padding: 8px 10px;
      border-radius: 6px;
    }

    /* Utilities */
    .truncate-1 {
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      max-width: 240px;
      display: inline-block;
      vertical-align: middle;
    }

    .text-muted {
      color: #95a5a6 !important;
    }

    .border-subtle {
      border-color: var(--border) !important;
    }

    /* Dropdown */
    .dropdown-menu {
      border-radius: 8px;
      border: 1px solid var(--border);
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }

    .dropdown-item {
      padding: 0.5rem 1rem;
      font-size: 0.875rem;
    }

    .dropdown-item:hover {
      background-color: rgba(52, 152, 219, 0.08);
    }

    /* Action buttons in table */
    .action-btn {
      background: transparent;
      border: 1px solid var(--border);
      border-radius: 6px;
      padding: 0.375rem 0.75rem;
      font-size: 0.875rem;
      color: var(--secondary);
      transition: all 0.15s ease;
    }

    .action-btn:hover {
      background-color: rgba(52, 152, 219, 0.08);
      color: var(--accent);
      border-color: var(--accent);
    }

    /* Enhanced Modal Styles */
    .modal-content {
      border-radius: 16px;
      border: none;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }

    .modal-header {
      background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
      border-bottom: 1px solid #e9ecef;
    }

    .detail-card {
      background: white;
      border-radius: 12px;
      padding: 1.5rem;
      border: 1px solid #e9ecef;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .action-bar {
      background: linear-gradient(135deg, #f8f9fa 0%, #f1f3f4 100%);
      border: 1px solid #e9ecef;
    }

    .info-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 1.25rem;
    }

    .info-item {
      display: flex;
      flex-direction: column;
    }

    .info-label {
      font-size: 0.75rem;
      font-weight: 600;
      color: #6c757d;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-bottom: 0.375rem;
    }

    .info-value {
      font-size: 0.95rem;
      font-weight: 500;
      color: #2c3e50;
      padding: 0.5rem 0;
    }

    .info-value.highlight {
      background-color: rgba(52, 152, 219, 0.08);
      padding: 0.5rem 0.75rem;
      border-radius: 8px;
      border-left: 3px solid #3498db;
      font-weight: 600;
    }

    .status-display {
      display: inline-flex;
      align-items: center;
      padding: 0.5rem 1rem;
      border-radius: 8px;
      font-weight: 600;
      font-size: 0.875rem;
      border: 1px solid;
    }

    .status-pending {
      background-color: rgba(243, 156, 18, 0.1);
      color: #d35400;
      border-color: rgba(243, 156, 18, 0.2);
    }

    .status-processing {
      background-color: rgba(52, 152, 219, 0.1);
      color: #2980b9;
      border-color: rgba(52, 152, 219, 0.2);
    }

    .status-complete {
      background-color: rgba(39, 174, 96, 0.1);
      color: #27ae60;
      border-color: rgba(39, 174, 96, 0.2);
    }

    /* Files Grid */
    .files-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 1rem;
    }

    .file-card {
      display: flex;
      align-items: center;
      background: white;
      border: 1px solid #e9ecef;
      border-radius: 10px;
      padding: 1rem;
      transition: all 0.2s ease;
      gap: 1rem;
    }

    .file-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      border-color: #3498db;
    }

    .file-icon {
      width: 48px;
      height: 48px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.25rem;
    }

    .file-icon.image {
      background-color: rgba(52, 152, 219, 0.1);
      color: #3498db;
    }

    .file-icon.document {
      background-color: rgba(108, 117, 125, 0.1);
      color: #6c757d;
    }

    .file-info {
      flex: 1;
      min-width: 0;
    }

    .file-name {
      font-size: 0.9rem;
      font-weight: 600;
      color: #2c3e50;
      margin-bottom: 0.25rem;
      word-break: break-word;
    }

    .file-meta {
      font-size: 0.75rem;
      color: #6c757d;
    }

    .file-actions {
      display: flex;
      gap: 0.5rem;
    }

    .file-actions .btn {
      padding: 0.375rem 0.5rem;
      border-radius: 6px;
    }

    .empty-state {
      color: #6c757d;
    }

    .empty-state i {
      opacity: 0.5;
    }

    /* Issue badge */
    .issue-badge {
      font-size: 0.75rem;
      font-weight: 500;
      padding: 0.375rem 0.75rem;
      border-radius: 20px;
      display: inline-block;
      text-align: center;
    }

    .issue-warning {
      background-color: rgba(243, 156, 18, 0.1);
      color: #d35400;
    }

    .issue-success {
      background-color: rgba(39, 174, 96, 0.1);
      color: #27ae60;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
      .info-grid {
        grid-template-columns: 1fr;
      }

      .files-grid {
        grid-template-columns: 1fr;
      }

      .file-card {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
      }

      .file-actions {
        justify-content: center;
      }
    }
  </style>
</head>

<body>
  <!-- Minimal Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
      <a class="navbar-brand fw-semibold" href="dashboard.php">
        <i class="bi bi-car-front me-2"></i>ClearMyRide
      </a>
      <div class="d-flex align-items-center">
        <span class="text-light me-3 d-none d-md-inline">Welcome, <strong><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'admin'); ?></strong></span>
        <div class="btn-group">
          <a class="btn btn-light btn-sm" href="dashboard.php"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
          <a class="btn btn-outline-light btn-sm" href="logout.php"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
        </div>
      </div>
    </div>
  </nav>

  <div class="container py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h1 class="h3 fw-semibold text-dark mb-1">Driver's License Requests</h1>
        <p class="text-muted mb-0">Manage and review license renewal requests</p>
      </div>
      <div class="btn-group">
        <a href="view_vehicle.php" class="btn btn-light"><i class="bi bi-car-front me-1"></i>Vehicle Requests</a>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
      <div class="col-md-3">
        <div class="stat-card">
          <div class="d-flex align-items-center">
            <div class="stat-icon bg-light text-primary me-3">
              <i class="bi bi-list-ul"></i>
            </div>
            <div>
              <div class="stat-value"><?php echo (int)$totalCount; ?></div>
              <div class="stat-label">Total Requests</div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-3">
        <div class="stat-card">
          <div class="d-flex align-items-center">
            <div class="stat-icon bg-light text-warning me-3">
              <i class="bi bi-clock"></i>
            </div>
            <div>
              <div class="stat-value" id="card-pending"><?php echo (int)$receivedCount; ?></div>
              <div class="stat-label">Pending Review</div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-3">
        <div class="stat-card">
          <div class="d-flex align-items-center">
            <div class="stat-icon bg-light text-info me-3">
              <i class="bi bi-gear"></i>
            </div>
            <div>
              <div class="stat-value" id="card-processing"><?php echo (int)$processingCount; ?></div>
              <div class="stat-label">Processing</div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-3">
        <div class="stat-card">
          <div class="d-flex align-items-center">
            <div class="stat-icon bg-light text-success me-3">
              <i class="bi bi-check2-circle"></i>
            </div>
            <div>
              <div class="stat-value" id="card-processed"><?php echo (int)$processedCount; ?></div>
              <div class="stat-label">Processed</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content Card -->
    <div class="main-card">
      <!-- Card Header with Filters -->
      <div class="card-header">
        <div class="row align-items-center">
          <div class="col-md-6">
            <h5 class="card-title">Recent License Requests</h5>
          </div>
          <div class="col-md-6 text-md-end">
            <div class="small text-muted">
              Showing <strong id="filtered-count"><?php echo isset($filteredCount) ? (int)$filteredCount : '—'; ?></strong> results
            </div>
          </div>
        </div>

        <!-- Search and Status filter -->
        <form class="row g-2 mt-3" method="get" id="filterForm" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
          <div class="col-md-6">
            <div class="input-group">
              <input type="text" name="q" value="<?php echo htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES); ?>" class="form-control" placeholder="Search by name, email, phone, license number">
              <button class="btn btn-light border-subtle" type="submit"><i class="bi bi-search me-1"></i>Search</button>
              <a class="btn btn-light border-subtle" href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" title="Clear filters"><i class="bi bi-x-lg"></i></a>
            </div>
          </div>

          <div class="col-md-3">
            <select name="status" class="form-select" onchange="document.getElementById('filterForm').submit()">
              <?php
              $selStatus = $_GET['status'] ?? '';
              $statusOptions = [
                '' => 'All statuses',
                'received' => 'Pending',
                'processing' => 'Processing',
                'processed' => 'Processed'
              ];
              foreach ($statusOptions as $k => $label) {
                $sel = ($k === $selStatus) ? 'selected' : '';
                echo '<option value="' . htmlspecialchars($k) . '" ' . $sel . '>' . htmlspecialchars($label) . '</option>';
              }
              ?>
            </select>
          </div>
          <!-- add beside your other filter buttons -->
          <div class="col-md-3 text-md-end">
            <div class="d-flex justify-content-end gap-2">
              <button
                class="btn btn-outline-secondary"
                type="submit"
                form="filterForm"
                formaction="export_license_xlsx.php"
                formmethod="get"
                formtarget="_blank"
                title="Export current filtered data to XLSX">
                <i class="bi bi-download me-1"></i>Export
              </button>
            </div>
          </div>


        </form>
      </div>

      <!-- Table -->
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle">
            <thead>
              <tr>
                <th class="ps-4">#</th>
                <th>Driver</th>
                <th>Contact</th>
                <th>License</th>
                <th>Status</th>
                <th>Issues</th>
                <th>Submitted</th>
                <th class="text-end pe-4">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $i = 0;
              foreach ($rows as $r):
                $i++;
                $rowNumber = $startNumber + ($i - 1);

                // normalize status
                $statusRaw = strtolower(trim((string)($r['status'] ?? 'received')));
                if ($statusRaw === '' || !in_array($statusRaw, ['received', 'processing', 'complete'])) {
                  if (in_array(strtolower($r['status']), ['pending', 'new'])) $statusRaw = 'received';
                  elseif (in_array(strtolower($r['status']), ['processed', 'complete'])) $statusRaw = 'complete';
                  else $statusRaw = 'received';
                }

                // friendly labels shown to the admin
                $statusLabels = [
                  'received'   => 'Pending',
                  'processing' => 'Processing',
                  'complete'   => 'Complete'
                ];

                // use mapping, fallback to ucfirst()
                $statusLabel = $statusLabels[$statusRaw] ?? ucfirst($statusRaw);
                $statusClass = "status-{$statusRaw}";

                // fetch attachments
                $attStmt = $pdo->prepare("SELECT id, file_name, mime_type FROM attachments WHERE parent_type='license' AND parent_id=:id ORDER BY id ASC");
                $attStmt->execute([':id' => $r['id']]);
                $attachments = $attStmt->fetchAll(PDO::FETCH_ASSOC);

                // prepare payload
                $recordData = $r;
                $recordData['attachments'] = $attachments;
                $jsonData = json_encode($recordData, JSON_HEX_APOS | JSON_HEX_QUOT);
              ?>
                <tr data-record='<?php echo htmlspecialchars($jsonData, ENT_QUOTES, 'UTF-8'); ?>' data-id="<?php echo (int)$r['id']; ?>">
                  <td class="ps-4 fw-medium text-muted"><?php echo (int)$rowNumber; ?></td>

                  <td>
                    <div class="fw-medium"><?php echo htmlspecialchars($r['full_name']); ?></div>
                    <div class="small text-muted">
                      DOB:
                      <?php
                      if (!empty($r['dob'])) {
                        $dobTimestamp = strtotime($r['dob']);
                        if ($dobTimestamp) {
                          echo date('F j, Y', $dobTimestamp); 
                        } else {
                          echo htmlspecialchars($r['dob']); 
                        }
                      } else {
                        echo '—';
                      }
                      ?>
                    </div>
                  </td>


                  <td>
                    <div class="fw-medium"><?php echo htmlspecialchars($r['email']); ?></div>
                    <div class="small text-muted"><?php echo htmlspecialchars($r['phone']); ?></div>
                  </td>

                  <td>
                    <div class="fw-medium"><?php echo htmlspecialchars($r['license_number']); ?></div>
                    <div class="small text-muted">
                      <?php
                      if (!empty($r['license_exp'])) {
                        $d = new DateTime($r['license_exp']);
                        echo 'Expires: ' . $d->format('M j, Y');
                      } else echo 'No expiration';
                      ?>
                    </div>
                  </td>

                  <td>
                    <span class="status-badge <?php echo $statusClass; ?>" data-status="<?php echo $statusRaw; ?>"><?php echo $statusLabel; ?></span>
                  </td>

                  <td>
                    <?php if (!empty($r['has_issues'])): ?>
                      <span class="issue-badge issue-warning"><i class="bi bi-exclamation-triangle me-1"></i>Has Issues</span>
                    <?php else: ?>
                      <span class="issue-badge issue-success"><i class="bi bi-check-circle me-1"></i>No Issues</span>
                    <?php endif; ?>
                  </td>

                  <td>
                    <div class="text-muted small">
                      <?php
                      $dt = new DateTime($r['created_at']);
                      echo $dt->format('M j, Y');
                      ?>
                    </div>
                    <div class="text-muted small"><?php echo $dt->format('g:i A'); ?></div>
                  </td>

                  <td class="text-end pe-4">
                    <div class="btn-group">
                      <button class="btn btn-sm action-btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-three-dots"></i>
                      </button>
                      <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item view-btn" href="#" data-id="<?php echo (int)$r['id']; ?>"><i class="bi bi-eye me-2"></i>View Details</a></li>
                        <li><a class="dropdown-item mark-processing" href="#" data-id="<?php echo (int)$r['id']; ?>" data-action="process"><i class="bi bi-play-circle me-2"></i>Start Processing</a></li>
                        <li><a class="dropdown-item mark-processed" href="#" data-id="<?php echo (int)$r['id']; ?>" data-action="complete"><i class="bi bi-check-circle me-2"></i>Mark Complete</a></li>
                      </ul>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination -->
      <div class="card-footer bg-white border-0 py-3">
        <div class="d-flex justify-content-between align-items-center">
          <div class="text-muted small">Showing <?php echo count($rows); ?> of <?php echo $filteredCount; ?> results</div>
          <nav>
            <ul class="pagination pagination-sm mb-0">
              <?php
              function pageLink($p)
              {
                $query = $_GET;
                $query['page'] = $p;
                return htmlspecialchars($_SERVER['PHP_SELF'] . '?' . http_build_query($query));
              }

              $prev = max(1, $page - 1);
              $next = min($totalPages, $page + 1);
              if ($page > 1) {
                echo '<li class="page-item"><a class="page-link" href="' . pageLink(1) . '">&laquo;</a></li>';
                echo '<li class="page-item"><a class="page-link" href="' . pageLink($prev) . '">Prev</a></li>';
              } else {
                echo '<li class="page-item disabled"><span class="page-link">&laquo;</span></li>';
                echo '<li class="page-item disabled"><span class="page-link">Prev</span></li>';
              }
              $range = 3;
              $start = max(1, $page - $range);
              $end = min($totalPages, $page + $range);
              for ($p = $start; $p <= $end; $p++) {
                if ($p == $page) echo '<li class="page-item active"><span class="page-link">' . $p . '</span></li>';
                else echo '<li class="page-item"><a class="page-link" href="' . pageLink($p) . '">' . $p . '</a></li>';
              }
              if ($page < $totalPages) {
                echo '<li class="page-item"><a class="page-link" href="' . pageLink($next) . '">Next</a></li>';
                echo '<li class="page-item"><a class="page-link" href="' . pageLink($totalPages) . '">&raquo;</a></li>';
              } else {
                echo '<li class="page-item disabled"><span class="page-link">Next</span></li>';
                echo '<li class="page-item disabled"><span class="page-link">&raquo;</span></li>';
              }
              ?>
            </ul>
          </nav>
        </div>
      </div>
    </div> <!-- main-card -->
  </div> <!-- container -->

  <!-- Enhanced Details Modal -->
  <div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
      <div class="modal-content">
        <!-- Modal content will be dynamically generated by JavaScript -->
      </div>
    </div>
  </div>

  <!-- Lightbox overlay -->
  <div id="lightbox" class="lightbox-backdrop" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="lightbox-content">
      <button class="lightbox-close btn btn-sm" aria-label="Close">&times;</button>
      <img id="lightbox-img" class="lightbox-img" src="" alt="Preview">
    </div>
  </div>

  <!-- Bootstrap + JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    (function() {
      const detailModalEl = document.getElementById('detailModal');
      const detailModal = new bootstrap.Modal(detailModalEl);
      const lt = document.getElementById('lightbox');
      const ltImg = document.getElementById('lightbox-img');
      const ltClose = lt.querySelector('.lightbox-close');

      const cardPending = document.getElementById('card-pending');
      const cardProcessing = document.getElementById('card-processing');
      const cardProcessed = document.getElementById('card-processed');

      const updateUrl = 'update_license_status.php';

      // helper: adjust counts using canonical statuses (received/processing/complete)
      function adjustCounts(oldStatus, newStatus) {
        const dec = (el) => el && (el.textContent = Math.max(0, parseInt(el.textContent || '0', 10) - 1));
        const inc = (el) => el && (el.textContent = (parseInt(el.textContent || '0', 10) + 1));
        if (!oldStatus || oldStatus === '') oldStatus = 'received';

        if (oldStatus === 'received') dec(cardPending);
        if (oldStatus === 'processing') dec(cardProcessing);
        if (oldStatus === 'complete') dec(cardProcessed);

        if (newStatus === 'received') inc(cardPending);
        if (newStatus === 'processing') inc(cardProcessing);
        if (newStatus === 'complete') inc(cardProcessed);
      }

      // update badge UI (client-side) — use canonical statuses
      function setRowStatusLocal(row, status) {
        const badge = row.querySelector('.status-badge');
        if (!badge) return;
        const oldStatus = badge.dataset.status || 'received';
        badge.dataset.status = status;

        // Update badge text and class
        const statusLabels = {
          'received': 'Pending',
          'processing': 'Processing',
          'complete': 'Complete'
        };
        badge.textContent = statusLabels[status] || status.charAt(0).toUpperCase() + status.slice(1);

        // Remove all status classes and add the new one
        badge.classList.remove('status-received', 'status-processing', 'status-complete');
        badge.classList.add(`status-${status}`);

        adjustCounts(oldStatus, status);
      }

      // show/hide actions in the dropdown & modal depending on status
      function refreshRowActions(tr) {
        if (!tr) return;
        const status = (tr.querySelector('.status-badge') || {}).dataset?.status || 'received';
        const processBtn = tr.querySelector('.mark-processing');
        const completeBtn = tr.querySelector('.mark-processed');

        if (processBtn) processBtn.style.display = 'none';
        if (completeBtn) completeBtn.style.display = 'none';

        if (status === 'received') {
          if (processBtn) processBtn.style.display = '';
        } else if (status === 'processing') {
          if (completeBtn) completeBtn.style.display = '';
        }
      }

      document.querySelectorAll('table tbody tr').forEach(tr => refreshRowActions(tr));

      // persist to server
      async function persistStatus(id, action) {
        try {
          const body = new URLSearchParams({
            id: id,
            action: action
          });
          const resp = await fetch(updateUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: body
          });
          if (!resp.ok) {
            const text = await resp.text();
            throw new Error('HTTP ' + resp.status + ' — ' + text);
          }
          const data = await resp.json();
          return data;
        } catch (err) {
          console.error('persistStatus error', err);
          return {
            success: false,
            message: err.message || 'Network error'
          };
        }
      }

      // centralised handler that persists and then updates UI on success
      async function doActionForRow(tr, action) {
        const id = tr.getAttribute('data-id') || tr.querySelector('.view-btn')?.getAttribute('data-id');
        if (!id) return alert('Missing id for row');
        const processBtn = tr.querySelector('.mark-processing');
        const completeBtn = tr.querySelector('.mark-processed');
        if (action === 'process' && processBtn) processBtn.classList.add('disabled');
        if (action === 'complete' && completeBtn) completeBtn.classList.add('disabled');

        const res = await persistStatus(id, action);

        if (processBtn) processBtn.classList.remove('disabled');
        if (completeBtn) completeBtn.classList.remove('disabled');

        if (res.success) {
          const newStatus = (action === 'process') ? 'processing' : 'complete';
          setRowStatusLocal(tr, newStatus);
          refreshRowActions(tr);

          const btnGroup = tr.querySelector('.btn-group');
          if (btnGroup) {
            const dropdown = btnGroup.querySelector('.dropdown-toggle');
            try {
              const dd = bootstrap.Dropdown.getInstance(dropdown) || new bootstrap.Dropdown(dropdown);
              dd.hide();
            } catch (e) {}
          }

          if (detailModalEl._currentRow === tr) {
            updateModalStatus(newStatus);
          }
        } else {
          alert('Error: ' + (res.message || 'Could not update status'));
        }
      }

      // Update modal status display
      function updateModalStatus(status) {
        const statusDisplay = document.getElementById('d-modal-status');
        const statusLabels = {
          'received': {
            text: 'Pending Review',
            class: 'status-pending',
            icon: 'bi-clock'
          },
          'processing': {
            text: 'Processing',
            class: 'status-processing',
            icon: 'bi-gear'
          },
          'complete': {
            text: 'Complete',
            class: 'status-complete',
            icon: 'bi-check-circle'
          }
        };

        const statusInfo = statusLabels[status] || statusLabels['received'];

        // Update status display
        statusDisplay.className = `status-badge ${statusInfo.class}`;
        statusDisplay.innerHTML = `<i class="${statusInfo.icon} me-1"></i>${statusInfo.text}`;

        // Update action buttons visibility
        document.getElementById('modal-mark-processing').style.display = (status === 'received') ? '' : 'none';
        document.getElementById('modal-mark-processed').style.display = (status === 'processing') ? '' : 'none';
      }

      // Event delegation for actions
      document.addEventListener('click', function(e) {
        const el = e.target;
        if (el.closest('.view-btn')) {
          e.preventDefault();
          const tr = el.closest('tr');
          if (!tr) return;
          openDetailModalForRow(tr);
          return;
        }

        if (el.closest('.mark-processing')) {
          e.preventDefault();
          const tr = el.closest('tr');
          if (!tr) return;
          doActionForRow(tr, 'process');
          return;
        }

        if (el.closest('.mark-processed')) {
          e.preventDefault();
          const tr = el.closest('tr');
          if (!tr) return;
          doActionForRow(tr, 'complete');
          return;
        }
      });

      // Lightbox handlers
      function openLightbox(src, alt) {
        ltImg.src = src;
        ltImg.alt = alt || 'Image preview';
        lt.classList.add('active');
        lt.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
      }

      function closeLightbox() {
        lt.classList.remove('active');
        lt.setAttribute('aria-hidden', 'true');
        ltImg.src = '';
        document.body.style.overflow = '';
      }
      ltClose.addEventListener('click', closeLightbox);
      lt.addEventListener('click', function(e) {
        if (e.target === lt || e.target === ltImg) closeLightbox();
      });
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && lt.classList.contains('active')) closeLightbox();
      });

      // populate modal
      function openDetailModalForRow(tr) {
        const data = tr.getAttribute('data-record');
        if (!data) return;
        let obj;
        try {
          obj = JSON.parse(data);
        } catch (e) {
          console.error('Invalid JSON', e);
          return;
        }

        // Update modal structure with enhanced layout
        const status = tr.querySelector('.status-badge')?.dataset?.status || 'received';
        const statusLabels = {
          'received': {
            text: 'Pending Review',
            class: 'status-pending',
            icon: 'bi-clock'
          },
          'processing': {
            text: 'Processing',
            class: 'status-processing',
            icon: 'bi-gear'
          },
          'complete': {
            text: 'Complete',
            class: 'status-complete',
            icon: 'bi-check-circle'
          }
        };
        const statusInfo = statusLabels[status] || statusLabels['received'];

        // Create enhanced modal content
        const modalContent = `
        <div class="modal-header border-0 pb-0">
            <div class="d-flex justify-content-between align-items-start w-100">
                <div>
                    <h5 class="modal-title fw-semibold text-dark mb-1">
                        <i class="bi bi-person-badge me-2 text-primary"></i>
                        Driver's License Request
                    </h5>
                  <br>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
        </div>

        <div class="modal-body pt-0">
            <!-- Status & Actions Bar -->
            <div class="action-bar bg-light rounded p-3 mb-4">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center gap-2">
                          <span id="d-modal-status" class="status-badge ${statusInfo.class}" data-status="${status}">
                            <i class="${statusInfo.icon} me-1"></i>${statusInfo.text}
                          </span>
                        </div>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-primary btn-sm" id="modal-mark-processing">
                                <i class="bi bi-play-circle me-1"></i>Start Processing
                            </button>
                            <button type="button" class="btn btn-success btn-sm" id="modal-mark-processed">
                                <i class="bi bi-check-circle me-1"></i>Mark Complete
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Customer Information -->
                <div class="col-lg-6">
                    <div class="detail-card">
                        <div class="card-header bg-transparent border-bottom-0 px-0 pt-0">
                            <h6 class="fw-semibold mb-3">
                                <i class="bi bi-person-badge me-2 text-primary"></i>
                                Driver Information
                            </h6>
                        </div>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Full Name</span>
                                <span class="info-value" id="d-name">${obj.full_name || '—'}</span>
                            </div>
                            <div class="info-item">
  <span class="info-label">Date of Birth</span>
  <span class="info-value" id="d-dob">
    ${obj.dob
      ? new Date(obj.dob).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })
      : '—'}
  </span>
</div>
                            <div class="info-item">
                                <span class="info-label">Email Address</span>
                                <span class="info-value" id="d-email">${obj.email || '—'}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Phone Number</span>
                                <span class="info-value" id="d-phone">${obj.phone || '—'}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- License Information -->
                <div class="col-lg-6">
                    <div class="detail-card">
                        <div class="card-header bg-transparent border-bottom-0 px-0 pt-0">
                            <h6 class="fw-semibold mb-3">
                                <i class="bi bi-card-text me-2 text-primary"></i>
                                License Details
                            </h6>
                        </div>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">License Number</span>
                                <span class="info-value highlight" id="d-license">${obj.license_number || '—'}</span>
                            </div>
                           <div class="info-item">
  <span class="info-label">License Expiry</span>
  <span class="info-value" id="d-license-exp">
    ${obj.license_exp
      ? new Date(obj.license_exp).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })
      : '—'}
  </span>
</div>
                            <div class="info-item">
                                <span class="info-label">Issues Status</span>
                                <span class="info-value">
                                  ${obj.has_issues ? 
                                    '<span class="issue-badge issue-warning"><i class="bi bi-exclamation-triangle me-1"></i>Has Issues</span>' : 
                                    '<span class="issue-badge issue-success"><i class="bi bi-check-circle me-1"></i>No Issues</span>'}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Attached Files -->
            <div class="detail-card mt-4">
                <div class="card-header bg-transparent border-bottom-0 px-0 pt-0">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-semibold mb-0">
                            <i class="bi bi-paperclip me-2 text-primary"></i>
                            Attached Documents
                        </h6>
                        <span class="badge bg-light text-dark">${obj.attachments?.length || 0} files</span>
                    </div>
                </div>
                <div id="d-files-container">
                    ${generateFilesGrid(obj.attachments || [])}
                </div>
            </div>
        </div>

        <div class="modal-footer border-0 bg-light rounded-bottom">
            <div class="me-auto">
                <small class="text-muted">
                    <i class="bi bi-clock me-1"></i>
                    Submitted: ${obj.created_at ? new Date(obj.created_at).toLocaleString() : '—'}
                </small>
            </div>
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                <i class="bi bi-x-circle me-1"></i>Close
            </button>
        </div>
    `;

        // Update modal content
        const modalContentElement = document.querySelector('#detailModal .modal-content');
        modalContentElement.innerHTML = modalContent;

        // Re-attach event listeners
        document.getElementById('modal-mark-processing').addEventListener('click', function() {
          const tr = detailModalEl._currentRow;
          if (!tr) return;
          doActionForRow(tr, 'process');
        });

        document.getElementById('modal-mark-processed').addEventListener('click', function() {
          const tr = detailModalEl._currentRow;
          if (!tr) return;
          doActionForRow(tr, 'complete');
        });

        // Set initial button visibility
        document.getElementById('modal-mark-processing').style.display = status === 'received' ? '' : 'none';
        document.getElementById('modal-mark-processed').style.display = status === 'processing' ? '' : 'none';

        // Keep reference and show modal
        detailModalEl._currentRow = tr;
        detailModal.show();
      }

      // Helper function to generate files grid
      function generateFilesGrid(attachments) {
        if (!attachments.length) {
          return `
            <div class="empty-state text-center py-5">
                <i class="bi bi-folder-x display-4 text-muted mb-3"></i>
                <p class="text-muted mb-0">No files attached to this request</p>
            </div>
        `;
        }

        return `
        <div class="files-grid">
            ${attachments.map(f => {
                const mime = (f.mime_type || '').toLowerCase();
                const isImage = mime.startsWith('image/');
                const fileDlUrl = `../download.php?id=${encodeURIComponent(f.id)}&mode=download`;
                const fileViewUrl = `../download.php?id=${encodeURIComponent(f.id)}&mode=view`;
                
                return `
                    <div class="file-card">
                        <div class="file-icon ${isImage ? 'image' : 'document'}">
                            <i class="bi ${isImage ? 'bi-card-image' : 'bi-file-earmark-text'}"></i>
                        </div>
                        <div class="file-info">
                            <div class="file-name">${f.file_name || 'Untitled'}</div>
                            <div class="file-meta">${formatFileSize(f.file_size)} • ${f.mime_type || 'Unknown type'}</div>
                        </div>
                        <div class="file-actions">
                            ${isImage ? `
                                <button class="btn btn-sm btn-outline-primary preview-btn" 
                                        data-src="${fileViewUrl}" 
                                        title="Preview">
                                    <i class="bi bi-eye"></i>
                                </button>
                            ` : ''}
                            <a class="btn btn-sm btn-primary" 
                               href="${fileDlUrl}" 
                               download 
                               title="Download">
                                <i class="bi bi-download"></i>
                            </a>
                        </div>
                    </div>
                `;
            }).join('')}
        </div>
    `;
      }

      // Helper function to format file size
      function formatFileSize(bytes) {
        if (!bytes) return 'Unknown size';
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
      }

      // Single delegated event listener for preview buttons
      document.addEventListener('click', function(ev) {
        const previewBtn = ev.target.closest('.preview-btn');
        if (previewBtn) {
          ev.preventDefault();
          ev.stopPropagation();
          const src = previewBtn.getAttribute('data-src');
          if (src) openLightbox(src, previewBtn.getAttribute('title') || '');
        }
      });

      window.__openLightbox = function(src, alt) {
        openLightbox(src, alt);
      };

    })();
  </script>

</body>

</html>