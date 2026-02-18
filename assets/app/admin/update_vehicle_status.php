<?php
// admin/update_license_status.php
session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db_connect.php';

header('Content-Type: application/json');

if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$adminId = (int) $_SESSION['admin_id'];
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$action = isset($_POST['action']) ? trim($_POST['action']) : '';

if ($id <= 0 || !in_array($action, ['process', 'complete'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    $pdo->beginTransaction();

    if ($action === 'process') {
        $sql = "UPDATE license_requests
                SET status = 'processing',
                    processing_started_at = NOW(),
                    processing_by = :admin_id,
                    updated_at = NOW()
                WHERE id = :id
                  AND (status IS NULL OR status = '' OR status = 'received' OR LOWER(status) IN ('pending','new'))";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':admin_id' => $adminId, ':id' => $id]);
        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Cannot set to processing (status may not be pending).']);
            exit;
        }
        // Update customer_requests status as well
        $stmt = $pdo->prepare("UPDATE customer_requests SET status = 'processing' WHERE id = ?");
        $stmt->execute([$id]);
        $pdo->commit();
        echo json_encode(['success' => true, 'new_status' => 'processing']);
        exit;
    }

    if ($action === 'complete') {
        $sql = "UPDATE license_requests
                SET status = 'complete',
                    processed_at = NOW(),
                    processed_by = :admin_id,
                    updated_at = NOW()
                WHERE id = :id
                  AND LOWER(status) IN ('processing', 'process')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':admin_id' => $adminId, ':id' => $id]);
        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Cannot mark complete (status may not be processing).']);
            exit;
        }
        // Update customer_requests
        $stmt = $pdo->prepare("UPDATE customer_requests SET status = 'completed' WHERE id = ?");
        $stmt->execute([$id]);

        // --- GENERATE RECEIPT ---
        // Get invoice_id from customer_requests
        $stmt = $pdo->prepare("SELECT invoice_id FROM customer_requests WHERE id = ?");
        $stmt->execute([$id]);
        $invoiceId = $stmt->fetchColumn();
        if ($invoiceId) {
            // Get amount from invoice
            $stmt = $pdo->prepare("SELECT amount_estimated FROM invoices WHERE id = ?");
            $stmt->execute([$invoiceId]);
            $amountPaid = $stmt->fetchColumn();
            // Generate receipt number
            $year = date('Y');
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM receipts WHERE receipt_number LIKE ?");
            $stmt->execute(["RCP-{$year}-%"]);
            $count = $stmt->fetchColumn() + 1;
            $receiptNumber = sprintf("RCP-%s-%04d", $year, $count);
            // Insert receipt
            $stmt = $pdo->prepare("
                INSERT INTO receipts (request_id, invoice_id, receipt_number, amount_paid, paid_at, created_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$id, $invoiceId, $receiptNumber, $amountPaid]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'new_status' => 'complete']);
        exit;
    }
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}