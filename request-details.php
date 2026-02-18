<?php
// request-details.php
session_start();
require_once __DIR__ . '/assets/app/Auth.php';
require_once __DIR__ . '/assets/app/db_connect.php';
require_once __DIR__ . '/assets/app/alerts.php';
require_once __DIR__ . '/assets/app/config.php';
require_once __DIR__ . '/assets/app/customer_functions.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: ' . base_url('index.php?redirect=' . urlencode($_SERVER['REQUEST_URI'])));
    exit;
}
$user = $auth->getCurrentUser();
$userId = $user['id'];

$requestId = (int)($_GET['id'] ?? 0);
if (!$requestId) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid request.'];
    header('Location: ' . base_url('dashboard.php'));
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ---------- HANDLE POST ACTIONS ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid security token.'];
        header('Location: ' . base_url("request-details.php?id=$requestId"));
        exit;
    }

    $action = $_POST['action'];
    $note = trim($_POST['note'] ?? '');

    if ($action === 'cancel') {
        // Store cancellation reason in customer_requests
        if (!empty($note)) {
            $stmt = $pdo->prepare("UPDATE customer_requests SET cancellation_reason = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$note, $requestId, $userId]);
        }
        $result = customerTransitionStatus($pdo, $requestId, 'Cancelled', $userId, $note);
    } elseif ($action === 'refund_request') {
        $bankDetails = trim($_POST['bank_details'] ?? '');
        $reason = trim($_POST['reason'] ?? '');
        if (empty($bankDetails)) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Bank account details are required.'];
            header('Location: ' . base_url("request-details.php?id=$requestId"));
            exit;
        }
        if (empty($reason)) {
            $reason = 'No reason provided';
        }

        // Fetch the invoice amount
        $stmt = $pdo->prepare("
            SELECT i.amount_estimated
            FROM customer_requests cr
            LEFT JOIN invoices i ON cr.invoice_id = i.id
            WHERE cr.id = ? AND cr.user_id = ?
        ");
        $stmt->execute([$requestId, $userId]);
        $amount = $stmt->fetchColumn();
        if (!$amount || $amount <= 0) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Unable to determine refund amount.'];
            header('Location: ' . base_url("request-details.php?id=$requestId"));
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO refund_requests 
                (request_id, customer_id, initiated_by, amount, reason, bank_details, status)
            VALUES (?, ?, 'customer', ?, ?, ?, 'pending')
        ");
        $stmt->execute([$requestId, $userId, $amount, $reason, $bankDetails]);

        $result = customerTransitionStatus($pdo, $requestId, 'Refund Requested', $userId, 'Refund requested by customer');
    } elseif ($action === 'submit_refund_bank_details') {
        // Admin-initiated refund: customer submits bank details
        $refundId = (int)($_POST['refund_id'] ?? 0);
        $bankDetails = trim($_POST['bank_details'] ?? '');
        if (!$refundId) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid refund request.'];
            header('Location: ' . base_url("request-details.php?id=$requestId"));
            exit;
        }
        if (empty($bankDetails)) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Bank account details are required.'];
            header('Location: ' . base_url("request-details.php?id=$requestId"));
            exit;
        }

        // Verify the refund belongs to this request and user, is pending, and has no bank details yet
        $stmt = $pdo->prepare("
            SELECT id FROM refund_requests
            WHERE id = ? AND request_id = ? AND customer_id = ? 
                AND status = 'pending' AND (bank_details IS NULL OR bank_details = '')
                AND initiated_by = 'admin'
        ");
        $stmt->execute([$refundId, $requestId, $userId]);
        if (!$stmt->fetch()) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Refund request not found or already has bank details.'];
            header('Location: ' . base_url("request-details.php?id=$requestId"));
            exit;
        }

        $pdo->prepare("UPDATE refund_requests SET bank_details = ? WHERE id = ?")
            ->execute([$bankDetails, $refundId]);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Bank details submitted successfully.'];
        header('Location: ' . base_url("request-details.php?id=$requestId"));
        exit;
    } elseif ($action === 'confirm_refund') {
        $refundId = (int)($_POST['refund_id'] ?? 0);
        if (!$refundId) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid refund request.'];
            header('Location: ' . base_url("request-details.php?id=$requestId"));
            exit;
        }
        $stmt = $pdo->prepare("
            SELECT id FROM refund_requests
            WHERE id = ? AND request_id = ? AND customer_id = ? AND status = 'approved'
        ");
        $stmt->execute([$refundId, $requestId, $userId]);
        if (!$stmt->fetch()) {
            $_SESSION['flash'] = ['type' => 'error', 'message' => 'Refund request not found or cannot be confirmed.'];
            header('Location: ' . base_url("request-details.php?id=$requestId"));
            exit;
        }

        // Update refund status to completed
        $pdo->prepare("UPDATE refund_requests SET status = 'completed', refund_confirmed_at = NOW() WHERE id = ?")->execute([$refundId]);

        // Update customer request status to Refunded
        $pdo->prepare("UPDATE customer_requests SET status = 'Refunded' WHERE id = ?")->execute([$requestId]);

        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Refund confirmed. Thank you.'];
        header('Location: ' . base_url("request-details.php?id=$requestId"));
        exit;
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'Invalid action.'];
        header('Location: ' . base_url("request-details.php?id=$requestId"));
        exit;
    }

    $_SESSION['flash'] = $result['success']
        ? ['type' => 'success', 'message' => $result['message']]
        : ['type' => 'error', 'message' => $result['message']];
    header('Location: ' . base_url("request-details.php?id=$requestId"));
    exit;
}

// ---------- FETCH REQUEST ----------
$stmt = $pdo->prepare("
    SELECT cr.*, 
           vr.plate, vr.vin, vr.reg_exp, vr.delivery_method, vr.full_name as vr_fullname,
           lr.license_number, lr.license_exp, lr.full_name as lr_fullname,
           i.id as invoice_id, i.invoice_number, i.amount_estimated, i.breakdown, 
           i.status as invoice_status, i.created_at as invoice_created_at, i.due_date, i.notes as invoice_notes
    FROM customer_requests cr
    LEFT JOIN vehicle_requests vr ON cr.id = vr.customer_request_id
    LEFT JOIN license_requests lr ON cr.id = lr.customer_request_id
    LEFT JOIN invoices i ON cr.invoice_id = i.id
    WHERE cr.id = ? AND cr.user_id = ?
");
$stmt->execute([$requestId, $userId]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$request) {
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'Request not found.'];
    header('Location: ' . base_url('dashboard.php'));
    exit;
}

// ---------- FETCH ADMIN DOCUMENT REQUESTS ----------
$stmt = $pdo->prepare("
    SELECT 
        adr.*,
        au.username as admin_name,
        (
            SELECT JSON_ARRAYAGG(
                JSON_OBJECT(
                    'id', ad.id,
                    'file_name', ad.file_name,
                    'file_path', ad.file_path,
                    'description', ad.description,
                    'uploaded_at', ad.uploaded_at
                )
            )
            FROM additional_documents ad
            WHERE ad.document_request_id = adr.id
        ) as attachments_json
    FROM additional_document_requests adr
    LEFT JOIN admin_users au ON adr.admin_id = au.id
    WHERE adr.request_id = ?
    ORDER BY adr.requested_at DESC
");
$stmt->execute([$requestId]);
$documentRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($documentRequests as &$dr) {
    $dr['attachments'] = json_decode($dr['attachments_json'], true) ?: [];
    unset($dr['attachments_json']);
}

$pendingDocRequests = array_filter($documentRequests, fn($req) => $req['status'] === 'pending');
$fulfilledDocRequests = array_filter($documentRequests, fn($req) => $req['status'] === 'fulfilled');

// ---------- FETCH ORPHAN DOCUMENTS ----------
$stmt = $pdo->prepare("
    SELECT * FROM additional_documents 
    WHERE request_id = ? AND document_request_id IS NULL
    ORDER BY uploaded_at DESC
");
$stmt->execute([$requestId]);
$orphanDocs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------- FETCH PAYMENT PROOFS ----------
$paymentProofs = [];
if ($request['invoice_id']) {
    $stmt = $pdo->prepare("
        SELECT * FROM payment_proofs 
        WHERE invoice_id = ?
        ORDER BY uploaded_at DESC
    ");
    $stmt->execute([$request['invoice_id']]);
    $paymentProofs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ---------- FETCH REFUND REQUESTS ----------
$stmt = $pdo->prepare("
    SELECT * FROM refund_requests 
    WHERE request_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$requestId]);
$refundRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---------- DETERMINE CUSTOMER PERMISSIONS ----------
$canCancel = in_array($request['status'], ['Pending', 'Under Review', 'Awaiting Payment'])
    && $request['invoice_status'] !== 'paid';
$canRequestRefund = $request['status'] === 'Processing' && $request['invoice_status'] === 'paid';
$canUploadPaymentProof = $request['status'] === 'Awaiting Payment' && $request['invoice_id'];
$canUploadDocument = $request['status'] !== 'Completed';

// Check if there is an approved refund waiting for confirmation
$pendingRefundConfirmation = array_filter($refundRequests, fn($r) => $r['status'] === 'approved' && empty($r['refund_confirmed_at']));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request #<?php echo $requestId; ?> – ClearMyRide</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
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

        .page-header {
            background: white;
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
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

        .detail-card {
            background: white;
            border: 1px solid #edf2f7;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
            margin-bottom: 1.5rem;
        }

        .detail-card-header {
            background: white;
            border-bottom: 1px solid #edf2f7;
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .detail-card-header i {
            color: #0A57FF;
            font-size: 1.25rem;
        }

        .detail-card-header h5 {
            margin-bottom: 0;
            font-weight: 600;
            color: #1a202c;
        }

        .detail-card-body {
            padding: 1.5rem;
        }

        .progress-tracker {
            display: flex;
            justify-content: space-between;
            margin: 1rem 0 0.5rem;
            position: relative;
        }

        .progress-step {
            flex: 1;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .progress-step .step-marker {
            width: 40px;
            height: 40px;
            margin: 0 auto 0.75rem;
            background: #f0f4f8;
            color: #4a5568;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e0e5ec;
        }

        .progress-step.completed .step-marker {
            background: #10b981;
            color: white;
            border-color: #10b981;
        }

        .progress-step.active .step-marker {
            background: #0A57FF;
            color: white;
            border-color: #0A57FF;
        }

        .progress-step .step-label {
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 0.25rem;
        }

        .progress-step .step-date {
            font-size: 0.75rem;
            color: #718096;
        }

        .progress-connector {
            position: absolute;
            top: 20px;
            left: 0;
            width: 100%;
            height: 2px;
            background: #e0e5ec;
            z-index: 0;
        }

        .progress-connector-fill {
            height: 2px;
            background: #0A57FF;
            width: 0%;
            transition: width 0.3s ease;
        }

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
            padding: 0.75rem 1rem;
            background: #fafcfc;
        }

        .table td {
            padding: 0.75rem 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #f0f4f8;
            color: #2d3748;
        }

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

        /* ----- ENHANCED INVOICE CARD ----- */
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
            border: 1px solid #edf2f7;
            padding: 1.25rem;
            margin-top: 1rem;
        }

        /* ----- ENHANCED REFUND CARD ----- */
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

        /* ----- UPLOAD FORM ----- */
        .upload-form-row {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: 1rem;
        }

        .upload-form-col-file {
            flex: 1 1 300px;
        }

        .upload-form-col-desc {
            flex: 1 1 250px;
        }

        .upload-form-col-btn {
            flex: 0 0 auto;
            display: flex;
            align-items: center;
        }

        .upload-form-col-btn .btn {
            height: 42px;
            margin-bottom: 0;
            padding-top: 0;
            padding-bottom: 0;
        }

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
            padding: 0.5rem 0.75rem;
            height: 42px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #0A57FF;
            box-shadow: none;
        }

        .doc-request-card {
            background: #f8fafc;
            border: 1px solid #edf2f7;
            padding: 1.25rem;
            margin-bottom: 1rem;
        }

        .cancellation-note {
            background: #fff5f5;
            border-left: 4px solid #e53e3e;
            padding: 1rem;
            margin-top: 1rem;
        }

        .modal-content {
            border: 1px solid #edf2f7;
            border-radius: 0;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05);
        }

        .modal-header {
            border-bottom: 1px solid #edf2f7;
            padding: 1.25rem 1.5rem;
        }

        .modal-footer {
            border-top: 1px solid #edf2f7;
            padding: 1rem 1.5rem;
        }

        .btn-close {
            filter: none;
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
                            <div class="avatar mb-3"><i class="fas fa-user-circle fa-4x" style="color: #0A57FF;"></i></div>
                            <h5 class="mb-1 fw-semibold"><?php echo htmlspecialchars($user['full_name'] ?? ''); ?></h5>
                            <p class="text-muted small"><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
                        </div>
                        <nav class="sidebar-nav">
                            <a href="<?php echo base_url('dashboard.php'); ?>" class="nav-item"><i class="fas fa-chart-pie"></i> Dashboard</a>
                             <a href="<?php echo base_url('new-request.php'); ?>" class="nav-item">
                                <i class="fas fa-plus-circle"></i> New Request
                            </a>
                            <a href="<?php echo base_url('vehicle-requests.php'); ?>" class="nav-item"><i class="fas fa-car"></i> Vehicle Requests</a>
                            <a href="<?php echo base_url('license-requests.php'); ?>" class="nav-item"><i class="fas fa-id-card"></i> License Requests</a>
                            <a href="<?php echo base_url('profile.php'); ?>" class="nav-item"><i class="fas fa-user-cog"></i> Profile</a>
                            <div class="sidebar-divider"></div>
                            <a href="<?php echo base_url('assets/app/logout.php'); ?>" class="nav-item logout" onclick="return confirm('Logout?')"><i class="fas fa-sign-out-alt"></i> Logout</a>
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
                <!-- Page Header -->
                <div class="page-header">
                    <div>
                        <h1><i class="fas fa-file-invoice me-3" style="color: #0A57FF;"></i> Request #<?php echo $requestId; ?></h1>
                        <p class="text-muted mb-0">Track progress, upload documents, and manage your request</p>
                    </div>
                    <div class="d-flex gap-2">
                        <?php if ($canCancel): ?>
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#cancelModal">
                                <i class="fas fa-times-circle me-2"></i>Cancel Request
                            </button>
                        <?php endif; ?>
                        <?php if ($canRequestRefund): ?>
                            <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#refundModal">
                                <i class="fas fa-undo-alt me-2"></i>Request Refund
                            </button>
                        <?php endif; ?>
                        <a href="<?php echo base_url('dashboard.php'); ?>" class="btn btn-outline-secondary px-4">
                            <i class="fas fa-arrow-left me-2"></i>Dashboard
                        </a>
                    </div>
                </div>

                <!-- REQUEST SUMMARY -->
                <div class="detail-card">
                    <div class="detail-card-header">
                        <i class="fas fa-clipboard-list"></i>
                        <h5>Request Summary</h5>
                    </div>
                    <div class="detail-card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm border-0">
                                    <tr>
                                        <th style="width:140px;">Type</th>
                                        <td>
                                            <span class="badge <?php echo $request['request_type'] == 'vehicle' ? 'bg-primary' : 'bg-info'; ?>">
                                                <i class="fas fa-<?php echo $request['request_type'] == 'vehicle' ? 'car' : 'id-card'; ?> me-1"></i>
                                                <?php echo ucfirst($request['request_type']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php if ($request['request_type'] == 'vehicle'): ?>
                                        <tr>
                                            <th>Full Name</th>
                                            <td><?php echo htmlspecialchars($request['vr_fullname'] ?? 'N/A'); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Plate</th>
                                            <td class="fw-medium"><?php echo htmlspecialchars($request['plate'] ?? 'N/A'); ?></td>
                                        </tr>
                                        <tr>
                                            <th>VIN</th>
                                            <td><?php echo htmlspecialchars($request['vin'] ?? '—'); ?></td>
                                        </tr>
                                    <?php else: ?>
                                        <tr>
                                            <th>Full Name</th>
                                            <td><?php echo htmlspecialchars($request['lr_fullname'] ?? 'N/A'); ?></td>
                                        </tr>
                                        <tr>
                                            <th>License #</th>
                                            <td class="fw-medium"><?php echo htmlspecialchars($request['license_number'] ?? 'N/A'); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Expiration</th>
                                            <td><?php echo htmlspecialchars($request['license_exp'] ?? '—'); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm border-0">
                                    <tr>
                                        <th>Submitted</th>
                                        <td><?php echo date('F j, Y', strtotime($request['created_at'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Status</th>
                                        <td>
                                            <span class="badge bg-<?php echo match ($request['status']) {
                                                                        'Pending' => 'secondary',
                                                                        'Under Review' => 'info',
                                                                        'Awaiting Payment' => 'warning',
                                                                        'Processing' => 'primary',
                                                                        'Completed' => 'success',
                                                                        'Cancelled' => 'danger',
                                                                        'Refund Requested' => 'danger',
                                                                        'Refunded' => 'secondary',
                                                                        default => 'secondary'
                                                                    }; ?>">
                                                <?php echo $request['status']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Payment</th>
                                        <td>
                                            <span class="badge bg-<?php echo $request['invoice_status'] == 'paid' ? 'success' : 'warning'; ?>">
                                                <i class="fas fa-<?php echo $request['invoice_status'] == 'paid' ? 'check-circle' : 'hourglass-half'; ?> me-1"></i>
                                                <?php echo $request['invoice_status'] == 'paid' ? 'Paid' : ($request['invoice_status'] ? 'Pending' : '—'); ?>
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <?php if ($request['status'] === 'Cancelled' && !empty($request['cancellation_reason'])): ?>
                            <div class="cancellation-note mt-3">
                                <strong><i class="fas fa-info-circle me-2"></i>Cancellation Reason:</strong>
                                <p class="mb-0 mt-1"><?php echo nl2br(htmlspecialchars($request['cancellation_reason'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- PROGRESS TRACKER -->
                <?php if (!in_array($request['status'], ['Cancelled', 'Refunded'])): ?>
                    <?php
                    $statusOrder = ['Pending', 'Under Review', 'Awaiting Payment', 'Processing', 'Completed'];
                    $currentIdx = array_search($request['status'], $statusOrder);
                    if ($currentIdx === false) $currentIdx = -1;
                    if ($request['invoice_status'] !== 'paid' && $currentIdx >= 2) $currentIdx = 1;
                    ?>
                    <div class="detail-card">
                        <div class="detail-card-header">
                            <i class="fas fa-chart-line"></i>
                            <h5>Progress</h5>
                        </div>
                        <div class="detail-card-body">
                            <div class="progress-tracker">
                                <div class="progress-connector">
                                    <div class="progress-connector-fill" style="width: <?php echo ($currentIdx / (count($statusOrder) - 1)) * 100; ?>%;"></div>
                                </div>
                                <?php foreach ($statusOrder as $i => $step): ?>
                                    <div class="progress-step <?php echo $i <= $currentIdx ? 'completed' : ''; ?> <?php echo $i == $currentIdx ? 'active' : ''; ?>">
                                        <div class="step-marker">
                                            <?php if ($i < $currentIdx): ?><i class="fas fa-check"></i><?php else: ?><?php echo $i + 1; ?><?php endif; ?>
                                        </div>
                                        <div class="step-label"><?php echo $step; ?></div>
                                        <?php if ($i == $currentIdx): ?><div class="step-date">Current</div><?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if ($currentIdx == 1 && $request['invoice_status'] !== 'paid'): ?>
                                <div class="alert alert-warning mt-3 mb-0 py-2 small">
                                    <i class="fas fa-info-circle me-1"></i> Payment confirmation required before processing can begin.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- ===== COMBINED DOCUMENTS SECTION ===== -->
                <div class="detail-card">
                    <div class="detail-card-header">
                        <i class="fas fa-file-alt text-primary"></i>
                        <h5>Documents & Requests</h5>
                    </div>
                    <div class="detail-card-body">
                        <?php if (empty($documentRequests) && empty($orphanDocs)): ?>
                            <p class="text-muted mb-0">No document requests or uploads yet.</p>
                        <?php else: ?>
                            <!-- PENDING REQUESTS (with upload forms) -->
                            <?php if (!empty($pendingDocRequests) && $canUploadDocument): ?>
                                <div class="mb-4">
                                    <h6 class="fw-semibold mb-3"><i class="fas fa-clock text-warning me-2"></i>Pending Document Requests</h6>
                                    <?php foreach ($pendingDocRequests as $dr): ?>
                                        <div class="doc-request-card">
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <div>
                                                    <p class="mb-1 fw-medium"><?php echo htmlspecialchars($dr['message'] ?? ''); ?></p>
                                                    <small class="text-muted">
                                                        <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($dr['admin_name'] ?? 'Admin'); ?> ·
                                                        <?php echo date('M j, Y', strtotime($dr['requested_at'])); ?>
                                                    </small>
                                                </div>
                                                <span class="badge bg-warning px-3 py-2">
                                                    <i class="fas fa-clock me-1"></i> Pending
                                                </span>
                                            </div>
                                            <form method="post" enctype="multipart/form-data" action="<?php echo base_url('assets/app/upload_additional.php'); ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="request_id" value="<?php echo $requestId; ?>">
                                                <input type="hidden" name="document_request_id" value="<?php echo $dr['id']; ?>">
                                                <div class="upload-form-row">
                                                    <div class="upload-form-col-file">
                                                        <input type="file" name="additional_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                                                        <small class="text-muted">PDF, JPG, PNG up to 10MB</small>
                                                    </div>
                                                    <div class="upload-form-col-desc">
                                                        <input type="text" name="doc_description" class="form-control" placeholder="Description (optional)">
                                                    </div>
                                                    <div class="upload-form-col-btn">
                                                        <button type="submit" class="btn btn-primary px-4">
                                                            <i class="fas fa-upload me-2"></i>Upload
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- FULFILLED REQUESTS (with attachments) -->
                            <?php if (!empty($fulfilledDocRequests)): ?>
                                <div class="mb-4">
                                    <h6 class="fw-semibold mb-3"><i class="fas fa-check-circle text-success me-2"></i>Completed Document Requests</h6>
                                    <?php foreach ($fulfilledDocRequests as $dr): ?>
                                        <div class="doc-request-card">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <p class="mb-1 fw-medium"><?php echo htmlspecialchars($dr['message'] ?? ''); ?></p>
                                                    <small class="text-muted">
                                                        <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($dr['admin_name'] ?? 'Admin'); ?> ·
                                                        <?php echo date('M j, Y', strtotime($dr['requested_at'])); ?>
                                                    </small>
                                                </div>
                                                <span class="badge bg-success px-3 py-2">
                                                    <i class="fas fa-check-circle me-1"></i> Fulfilled
                                                </span>
                                            </div>
                                            <?php if (!empty($dr['attachments'])): ?>
                                                <div class="mt-2 ps-3 border-start border-2 border-light">
                                                    <small class="text-muted d-block mb-1"><i class="fas fa-paperclip me-1"></i>Your uploaded files:</small>
                                                    <div class="d-flex flex-wrap gap-2">
                                                        <?php foreach ($dr['attachments'] as $att): ?>
                                                            <a href="<?php echo base_url('assets/app/download_document.php?type=additional_document&id=' . $att['id']); ?>" class="btn btn-sm btn-outline-secondary" download>
                                                                <i class="fas fa-file me-1"></i><?php echo htmlspecialchars(substr($att['file_name'] ?? '', 0, 20)) . '…'; ?>
                                                                <?php if (!empty($att['description'])): ?>
                                                                    <small class="d-block text-muted"><?php echo htmlspecialchars($att['description']); ?></small>
                                                                <?php endif; ?>
                                                            </a>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- ORPHAN DOCUMENTS (uploads not tied to a request) -->
                            <?php if (!empty($orphanDocs)): ?>
                                <div>
                                    <h6 class="fw-semibold mb-3"><i class="fas fa-archive text-secondary me-2"></i>Other Uploaded Documents</h6>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>File name</th>
                                                    <th>Description</th>
                                                    <th>Uploaded</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($orphanDocs as $doc): ?>
                                                    <tr>
                                                        <td><i class="fas fa-file-pdf me-2 text-danger"></i> <?php echo htmlspecialchars($doc['file_name'] ?? ''); ?></td>
                                                        <td><?php echo htmlspecialchars($doc['description'] ?? '—'); ?></td>
                                                        <td><?php echo date('M j, Y', strtotime($doc['uploaded_at'])); ?></td>
                                                        <td>
                                                            <a href="<?php echo base_url('assets/app/download_document.php?type=additional_document&id=' . $doc['id']); ?>" class="btn btn-sm btn-outline-primary" download>
                                                                <i class="fas fa-download me-1"></i>Download
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ===== REFUND REQUESTS SECTION (ENHANCED) ===== -->
                <?php if (!empty($refundRequests)): ?>
                    <div class="detail-card">
                        <div class="detail-card-header">
                            <i class="fas fa-undo-alt text-primary"></i>
                            <h5>Refund Requests</h5>
                        </div>
                        <div class="detail-card-body">
                            <?php foreach ($refundRequests as $rr):
                                $statusClass = match ($rr['status']) {
                                    'pending'   => 'warning',
                                    'approved'  => 'info',
                                    'rejected'  => 'danger',
                                    'completed' => 'success',
                                    default     => 'secondary'
                                };
                                $statusIcon = match ($rr['status']) {
                                    'pending'   => 'clock',
                                    'approved'  => 'check-circle',
                                    'rejected'  => 'times-circle',
                                    'completed' => 'check-double',
                                    default     => 'circle'
                                };
                                $initiatedBy = $rr['initiated_by'] ?? 'customer';
                                $badgeLabel = $initiatedBy === 'admin' ? 'Admin Initiated' : 'You Requested';
                            ?>
                                <div class="refund-card <?php echo $rr['status']; ?>" id="refund-<?php echo $rr['id']; ?>">
                                    <div class="refund-header">
                                        <div>
                                            <span class="refund-title">
                                                <i class="fas fa-<?php echo $statusIcon; ?> me-2 text-<?php echo $statusClass; ?>"></i>
                                                Refund Request #<?php echo $rr['id']; ?>
                                            </span>
                                            <span class="badge bg-<?php echo $initiatedBy === 'admin' ? 'secondary' : 'primary'; ?> ms-2">
                                                <?php echo $badgeLabel; ?>
                                            </span>
                                        </div>
                                        <span class="badge bg-<?php echo $statusClass; ?> px-3 py-2">
                                            <i class="fas fa-<?php echo $statusIcon; ?> me-1"></i>
                                            <?php echo ucfirst($rr['status']); ?>
                                        </span>
                                    </div>

                                    <div class="refund-meta">
                                        <span><i class="fas fa-calendar me-1"></i> Requested: <?php echo date('M j, Y', strtotime($rr['created_at'])); ?></span>
                                        <span><i class="fas fa-dollar-sign me-1"></i> Amount: $<?php echo number_format($rr['amount'], 2); ?></span>
                                    </div>

                                    <div class="refund-detail-row">
                                        <span class="refund-detail-label">Reason</span>
                                        <span class="refund-detail-value"><?php echo nl2br(htmlspecialchars($rr['reason'] ?? '—')); ?></span>
                                    </div>

                                    <?php if (!empty($rr['bank_details'])): ?>
                                        <div class="refund-detail-row">
                                            <span class="refund-detail-label">Bank Account</span>
                                            <span class="refund-detail-value"><?php echo nl2br(htmlspecialchars($rr['bank_details'])); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div class="refund-detail-row">
                                            <span class="refund-detail-label">Bank Account</span>
                                            <span class="refund-detail-value text-muted"><em>Awaiting submission</em></span>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($rr['admin_note'])): ?>
                                        <div class="refund-detail-row">
                                            <span class="refund-detail-label">Admin Note</span>
                                            <span class="refund-detail-value"><?php echo nl2br(htmlspecialchars($rr['admin_note'])); ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($rr['refund_proof_file'])): ?>
                                        <div class="refund-proof-link">
                                            <a href="<?php echo base_url('assets/app/download_document.php?type=refund_proof&id=' . $rr['id']); ?>" class="btn btn-sm btn-outline-success" download>
                                                <i class="fas fa-download me-1"></i> Download Refund Proof
                                            </a>
                                        </div>
                                    <?php endif; ?>

                                    <!-- ADMIN-INITIATED: Bank Details Submission Form (appears when pending, no bank details) -->
                                    <?php if ($initiatedBy === 'admin' && $rr['status'] === 'pending' && empty($rr['bank_details'])): ?>
                                        <div class="bank-details-form">
                                            <form method="post">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="action" value="submit_refund_bank_details">
                                                <input type="hidden" name="refund_id" value="<?php echo $rr['id']; ?>">
                                                <div class="row g-3 align-items-end">
                                                    <div class="col-md-8">
                                                        <label for="bankDetails<?php echo $rr['id']; ?>" class="form-label fw-semibold">Bank Account Details <span class="text-danger">*</span></label>
                                                        <textarea class="form-control" id="bankDetails<?php echo $rr['id']; ?>" name="bank_details" rows="2" required placeholder="Account holder name, account number, routing number, bank name"></textarea>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <button type="submit" class="btn btn-info w-100">
                                                            <i class="fas fa-paper-plane me-2"></i>Submit Bank Details
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    <?php endif; ?>

                                    <!-- CUSTOMER CONFIRMATION: Approved refund awaiting confirmation -->
                                    <?php if ($rr['status'] === 'approved' && empty($rr['refund_confirmed_at'])): ?>
                                        <div class="bank-details-form">
                                            <form method="post">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="action" value="confirm_refund">
                                                <input type="hidden" name="refund_id" value="<?php echo $rr['id']; ?>">
                                                <div class="d-flex align-items-center gap-3">
                                                    <button type="submit" class="btn btn-success">
                                                        <i class="fas fa-check-circle me-2"></i>Confirm Refund Receipt
                                                    </button>
                                                    
                                                </div>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- ===== INVOICE & PAYMENT (ENHANCED) ===== -->
                <?php if ($request['invoice_id']): ?>
                    <div class="detail-card">
                        <div class="detail-card-header">
                            <i class="fas fa-file-invoice-dollar text-primary"></i>
                            <h5>Invoice & Payment</h5>
                        </div>
                        <div class="detail-card-body">
                            <?php
                            $breakdown = json_decode($request['breakdown'] ?? '{}', true);
                            $dueDate = !empty($request['due_date']) ? new DateTime($request['due_date']) : null;
                            $isOverdue = $dueDate && $dueDate < new DateTime() && $request['invoice_status'] !== 'paid';
                            ?>
                            <div class="invoice-card">
                                <div class="invoice-header">
                                    <span class="invoice-title">Invoice #<?php echo htmlspecialchars($request['invoice_number']); ?></span>
                                    <span class="badge bg-<?php echo $request['invoice_status'] == 'paid' ? 'success' : ($isOverdue ? 'danger' : 'warning'); ?> px-3 py-2">
                                        <i class="fas fa-<?php echo $request['invoice_status'] == 'paid' ? 'check-circle' : ($isOverdue ? 'exclamation-triangle' : 'clock'); ?> me-1"></i>
                                        <?php echo $request['invoice_status'] == 'paid' ? 'Paid' : ($isOverdue ? 'Overdue' : ucfirst($request['invoice_status'])); ?>
                                    </span>
                                </div>

                                <!-- Invoice Line Items -->
                                <div class="invoice-row">
                                    <span>Service Fee</span>
                                    <span class="fw-medium">$<?php echo number_format($breakdown['service_fee'] ?? 0, 2); ?></span>
                                </div>
                                <div class="invoice-row">
                                    <span>Government Fee</span>
                                    <span class="fw-medium">$<?php echo number_format($breakdown['gov_fee'] ?? 0, 2); ?></span>
                                </div>
                                <div class="invoice-row fw-bold">
                                    <span>Total</span>
                                    <span class="fs-6">$<?php echo number_format($request['amount_estimated'], 2); ?></span>
                                </div>

                                <!-- Invoice Dates -->
                                <div class="d-flex justify-content-between mt-3 small">
                                    <span class="text-muted">
                                        <i class="fas fa-calendar-alt me-1" style="color: #0A57FF;"></i> Issued: <?php echo date('M j, Y', strtotime($request['invoice_created_at'])); ?>
                                    </span>
                                    <span class="<?php echo $isOverdue ? 'invoice-due overdue' : 'invoice-due'; ?>">
                                        <i class="fas fa-calendar-check me-1" style="color: <?php echo $isOverdue ? '#e53e3e' : '#0A57FF'; ?>;"></i> Due: <?php echo $dueDate ? $dueDate->format('M j, Y') : 'Not set'; ?>
                                    </span>
                                </div>

                                <!-- INVOICE NOTES – beautifully styled callout -->
                                <?php
                                $notesText = $request['invoice_notes'] ?? ($breakdown['notes'] ?? null);
                                if (!empty($notesText)):
                                ?>
                                    <div class="invoice-notes">
                                        <div class="d-flex gap-2">
                                            <div ></div>
                                            <div class="flex-grow-1">
                                                <div class="d-flex align-items-center gap-2 mb-1">
                                                    <i class="fas fa-sticky-note" style="color: #0A57FF; font-size: 0.9rem;"></i>
                                                    <span style="font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.3px; color: #0A57FF;">Invoice Note</span>
                                                </div>
                                                <p style="margin-bottom: 0; font-size: 0.85rem; color: #1a202c; line-height: 1.5;">
                                                    <?php echo nl2br(htmlspecialchars($notesText)); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- COMPANY BANK DETAILS – sleek, professional card -->
                                <div class="bank-details">
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
                                            <i class="fas fa-info-circle" style="color: #0A57FF;"></i> Include your invoice number in the transfer description.
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <?php if ($canUploadPaymentProof): ?>
                                <hr class="my-4">
                                <h6 class="mb-3 fw-semibold"><i class="fas fa-receipt me-2"></i>Proof of Payment</h6>
                                <form action="<?php echo base_url('assets/app/submit_proof.php'); ?>" method="post" enctype="multipart/form-data">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="invoice_id" value="<?php echo $request['invoice_id']; ?>">
                                    <input type="hidden" name="request_id" value="<?php echo $requestId; ?>">
                                    <div class="upload-form-row">
                                        <div class="upload-form-col-file">
                                            <input type="file" name="proof_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                                            <small class="text-muted">PDF, JPG, PNG up to 10MB</small>
                                        </div>
                                        <div class="upload-form-col-btn">
                                            <button type="submit" class="btn btn-primary px-4">
                                                <i class="fas fa-upload me-2"></i>Upload Proof
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            <?php endif; ?>

                            <?php if (!empty($paymentProofs)): ?>
                                <div class="mt-4">
                                    <p class="mb-2 fw-semibold">Submitted Proofs:</p>
                                    <div class="d-flex flex-wrap gap-3">
                                        <?php foreach ($paymentProofs as $proof): ?>
                                            <div class="proof-item">
                                                <div class="d-flex align-items-center mb-2">
                                                    <i class="fas fa-file-pdf fa-lg me-2 text-danger"></i>
                                                    <a href="<?php echo base_url('assets/app/download_document.php?type=payment_proof&id=' . $proof['id']); ?>" target="_blank" class="text-decoration-none">
                                                        <?php echo htmlspecialchars(substr($proof['file_name'] ?? '', 0, 15)) . '…'; ?>
                                                    </a>
                                                </div>
                                                <span class="badge w-100 bg-<?php echo $proof['status'] == 'confirmed' ? 'success' : ($proof['status'] == 'rejected' ? 'danger' : 'warning'); ?>">
                                                    <i class="fas fa-<?php echo $proof['status'] == 'confirmed' ? 'check-circle' : ($proof['status'] == 'rejected' ? 'times-circle' : 'hourglass-half'); ?> me-1"></i>
                                                    <?php echo ucfirst($proof['status']); ?>
                                                </span>
                                                <?php if ($proof['status'] == 'rejected' && $proof['admin_notes']): ?>
                                                    <small class="text-danger d-block mt-2"><?php echo htmlspecialchars($proof['admin_notes']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- (No receipt section – removed as requested) -->
            </div>
        </div>
    </main>

    <!-- Cancel Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-times-circle text-danger me-2"></i>Cancel Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="cancel">
                    <div class="modal-body">
                        <p>Are you sure you want to cancel this request? This action cannot be undone.</p>
                        <div class="mb-3">
                            <label for="cancelNote" class="form-label">Reason (optional)</label>
                            <textarea class="form-control" id="cancelNote" name="note" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-danger">Yes, Cancel Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Refund Modal (Customer-Initiated) -->
    <div class="modal fade" id="refundModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-undo-alt text-warning me-2"></i>Request Refund</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="refund_request">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="refundReason" class="form-label">Reason for refund <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="refundReason" name="reason" rows="2" required placeholder="Why are you requesting a refund?"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="bankDetails" class="form-label">Bank Account Details <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="bankDetails" name="bank_details" rows="3" required placeholder="Account holder name, account number, routing number, bank name"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-warning">Submit Refund Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>