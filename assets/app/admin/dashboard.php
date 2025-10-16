<?php
// assets/app/admin/dashboard.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db_connect.php';
$username = $_SESSION['admin_username'] ?? 'admin';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard - ClearMyRide</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="icon" href="../../images/favicon.png" type="image/png">
</head>
<body class="bg-light">
  <!-- Navigation -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
      <a class="navbar-brand fw-bold" href="dashboard.php">
        <i class="bi bi-gear-fill me-2"></i>ClearMyRide Admin
      </a>
      <div class="d-flex align-items-center">
        <span class="text-light me-3 d-none d-md-inline">Welcome, <strong><?php echo htmlspecialchars($username); ?></strong></span>
        <div class="btn-group">
          <a class="btn btn-outline-light btn-sm" href="change_password.php">
            <i class="bi bi-key me-1"></i>Password
          </a>
          <a class="btn btn-outline-light btn-sm" href="logout.php">
            <i class="bi bi-box-arrow-right me-1"></i>Logout
          </a>
        </div>
      </div>
    </div>
  </nav>

  <!-- Main Content -->
  <div class="container py-4">
    <div class="row g-4">
      
      <!-- Main Dashboard Section -->
      <div class="col-lg-8">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
          <div>
            <h1 class="h3 fw-bold text-dark mb-1">Dashboard Overview</h1>
            <p class="text-muted mb-0">Monitor and manage renewal requests</p>
          </div>
          <div class="text-muted small">
            <i class="bi bi-calendar3 me-1"></i> <?php echo date('F j, Y'); ?>
          </div>
        </div>

        <!-- Stats Cards -->
        <div class="row g-3 mb-4">
          <?php
          // example stats
          $vCount = $pdo->query("SELECT COUNT(*) FROM vehicle_requests")->fetchColumn();
          $lCount = $pdo->query("SELECT COUNT(*) FROM license_requests")->fetchColumn();
          ?>
          <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
              <div class="card-body p-4">
                <div class="d-flex align-items-center">
                  <div class="bg-primary bg-opacity-10 p-3 rounded me-3">
                    <i class="bi bi-car-front-fill text-primary fs-4"></i>
                  </div>
                  <div>
                    <h3 class="fw-bold text-dark mb-0"><?php echo (int)$vCount; ?></h3>
                    <p class="text-muted mb-0">Vehicle Requests</p>
                  </div>
                </div>
                <div class="mt-3">
                  <a href="view_vehicle.php" class="btn btn-outline-primary btn-sm">
                    View All <i class="bi bi-arrow-right ms-1"></i>
                  </a>
                </div>
              </div>
            </div>
          </div>
          
          <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
              <div class="card-body p-4">
                <div class="d-flex align-items-center">
                  <div class="bg-success bg-opacity-10 p-3 rounded me-3">
                    <i class="bi bi-person-badge-fill text-success fs-4"></i>
                  </div>
                  <div>
                    <h3 class="fw-bold text-dark mb-0"><?php echo (int)$lCount; ?></h3>
                    <p class="text-muted mb-0">License Requests</p>
                  </div>
                </div>
                <div class="mt-3">
                  <a href="view_license.php" class="btn btn-outline-success btn-sm">
                    View All <i class="bi bi-arrow-right ms-1"></i>
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white border-0 py-3">
            <h5 class="fw-bold mb-0">Quick Actions</h5>
          </div>
          <div class="card-body p-4">
            <div class="row g-3">
              <div class="col-sm-6 col-md-4">
                <a href="view_vehicle.php" class="card text-decoration-none h-100 border">
                  <div class="card-body text-center p-4">
                    <i class="bi bi-car-front text-primary fs-2 mb-2"></i>
                    <h6 class="fw-bold">Vehicle Requests</h6>
                    <p class="text-muted small mb-0">Manage registration renewals</p>
                  </div>
                </a>
              </div>
              <div class="col-sm-6 col-md-4">
                <a href="view_license.php" class="card text-decoration-none h-100 border">
                  <div class="card-body text-center p-4">
                    <i class="bi bi-person-badge text-success fs-2 mb-2"></i>
                    <h6 class="fw-bold">License Requests</h6>
                    <p class="text-muted small mb-0">Handle license renewals</p>
                  </div>
                </a>
              </div>
              <div class="col-sm-6 col-md-4">
                <a href="change_password.php" class="card text-decoration-none h-100 border">
                  <div class="card-body text-center p-4">
                    <i class="bi bi-shield-lock text-warning fs-2 mb-2"></i>
                    <h6 class="fw-bold">Security</h6>
                    <p class="text-muted small mb-0">Update password & settings</p>
                  </div>
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Sidebar -->
      <div class="col-lg-4">
        <!-- Profile Card -->
        <div class="card border-0 shadow-sm mb-4">
          <div class="card-header bg-white border-0 py-3">
            <h5 class="fw-bold mb-0">Admin Profile</h5>
          </div>
          <div class="card-body p-4">
            <div class="text-center mb-4">
              <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                <i class="bi bi-person-fill text-primary fs-2"></i>
              </div>
              <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($username); ?></h5>
              <p class="text-muted mb-3">Administrator</p>
            </div>
            <div class="d-grid gap-2">
              <a class="btn btn-outline-primary" href="change_password.php">
                <i class="bi bi-key me-2"></i>Change Password
              </a>
              <a class="btn btn-outline-secondary" href="logout.php">
                <i class="bi bi-box-arrow-right me-2"></i>Logout
              </a>
            </div>
          </div>
        </div>

        <!-- System Status -->
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white border-0 py-3">
            <h5 class="fw-bold mb-0">System Status</h5>
          </div>
          <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <span class="text-muted">Database</span>
              <span class="badge bg-success">Online</span>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-3">
              <span class="text-muted">Last Backup</span>
              <span class="text-muted small"><?php echo date('M j, g:i A', strtotime('-1 day')); ?></span>
            </div>
            <div class="d-flex justify-content-between align-items-center">
              <span class="text-muted">Uptime</span>
              <span class="text-muted small">99.8%</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>