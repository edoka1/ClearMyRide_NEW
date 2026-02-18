<?php
// admin/payment_confirm.php
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

$proofId = (int)($_POST['proof_id'] ?? 0);
$status = $_POST['status'] ?? '';
$adminNotes = trim($_POST['admin_notes'] ?? '');

if (!$proofId || !in_array($status, ['confirmed','rejected'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Get invoice and request info
    $stmt = $pdo->prepare("
        SELECT pp.invoice_id, i.request_id, i.status as invoice_status
        FROM payment_proofs pp
        JOIN invoices i ON pp.invoice_id = i.id
        WHERE pp.id = ?
    ");
    $stmt->execute([$proofId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$data) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Proof not found']);
        exit;
    }
    $invoiceId = $data['invoice_id'];
    $requestId = $data['request_id'];
    $invoiceStatus = $data['invoice_status'];

    // Update payment proof
    $stmt = $pdo->prepare("
        UPDATE payment_proofs 
        SET status = ?, admin_notes = ?, confirmed_by = ?, confirmed_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$status, $adminNotes, $_SESSION['admin_id'], $proofId]);

    if ($status === 'confirmed') {
        // Update invoice status to paid
        $stmt = $pdo->prepare("UPDATE invoices SET status = 'paid' WHERE id = ?");
        $stmt->execute([$invoiceId]);

        // **TRANSITION REQUEST TO PROCESSING**
        $trans = transitionStatus(
            $pdo,
            $requestId,
            'Processing',
            'admin',
            $_SESSION['admin_id'],
            'Payment confirmed',
            ['payment_confirmed' => true]
        );

        if (!$trans['success']) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => $trans['message']]);
            exit;
        }

        // Optionally generate receipt here? Rules say receipt can be after completion, so skip for now.
    }

    $pdo->commit();

    // Clean output buffer and send JSON
    ob_clean();
    echo json_encode(['success' => true]);
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Payment confirmation exception: " . $e->getMessage());
    http_response_code(500);
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}