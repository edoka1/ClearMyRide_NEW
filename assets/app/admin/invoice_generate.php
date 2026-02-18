<?php
// admin/invoice_generate.php
ob_start(); // Start output buffering to catch any accidental output
session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/status_functions.php';

// Clear any previous output (like whitespace before <?php)
ob_clean();

header('Content-Type: application/json');

// Custom error handler to catch warnings/notices
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Log error but continue – we'll still return JSON
    error_log("PHP Error [$errno] $errstr in $errfile line $errline");
    return false; // Let PHP's internal handler run too
});

if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$requestId = (int)($_POST['request_id'] ?? 0);
$requestType = $_POST['request_type'] ?? '';
$serviceFee = floatval($_POST['service_fee'] ?? 0);
$govFee = floatval($_POST['gov_fee'] ?? 0);
$notes = trim($_POST['notes'] ?? '');

if (!$requestId || !in_array($requestType, ['vehicle', 'license']) || $serviceFee < 0 || $govFee < 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

try {
    $pdo->beginTransaction();

    // $requestId is already customer_requests.id
    $customerRequestId = $requestId;

    // Verify request exists and type matches
    $stmt = $pdo->prepare("SELECT id FROM customer_requests WHERE id = ? AND request_type = ?");
    $stmt->execute([$customerRequestId, $requestType]);
    if (!$stmt->fetchColumn()) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Customer request not found.']);
        exit;
    }

    // Generate invoice number
    $year = date('Y');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE invoice_number LIKE ?");
    $stmt->execute(["INV-{$year}-%"]);
    $count = $stmt->fetchColumn() + 1;
    $invoiceNumber = sprintf("INV-%s-%04d", $year, $count);

    $breakdown = json_encode(['service_fee' => $serviceFee, 'gov_fee' => $govFee]);
    $total = $serviceFee + $govFee;
    $dueDate = date('Y-m-d', strtotime('+30 days'));

    // Insert invoice
    $sql = "INSERT INTO invoices (request_id, invoice_number, amount_estimated, breakdown, notes, due_date, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'sent', NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$customerRequestId, $invoiceNumber, $total, $breakdown, $notes, $dueDate]);
    $invoiceId = $pdo->lastInsertId();

    // Update customer_requests.invoice_id
    $stmt = $pdo->prepare("UPDATE customer_requests SET invoice_id = ? WHERE id = ?");
    $stmt->execute([$invoiceId, $customerRequestId]);

    // Transition status to Awaiting Payment
    $trans = transitionStatus(
        $pdo,
        $customerRequestId,
        'Awaiting Payment',
        'admin',
        $_SESSION['admin_id'],
        "Invoice #$invoiceNumber generated",
        ['invoice_id' => $invoiceId, 'invoice_number' => $invoiceNumber]
    );

    if (!$trans['success']) {
        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => $trans['message']]);
        exit;
    }

    $pdo->commit();

    // Clean output buffer and send JSON
    ob_clean();
    echo json_encode(['success' => true, 'invoice_id' => $invoiceId, 'invoice_number' => $invoiceNumber]);
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Invoice generation exception: " . $e->getMessage());
    http_response_code(500);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'An error occurred while generating the invoice.']);
    exit;
}