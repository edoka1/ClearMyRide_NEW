<?php
// assets/app/admin/update_license_status.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db_connect.php';

header('Content-Type: application/json; charset=utf-8');

// require admin session (auth.php should set $_SESSION['admin_id'])
if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$adminId = (int) $_SESSION['admin_id'];

$input = $_POST;
$id = isset($input['id']) ? (int)$input['id'] : 0;
$action = isset($input['action']) ? trim($input['action']) : '';

if ($id <= 0 || !in_array($action, ['process', 'complete'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    if ($action === 'process') {
        // Only move to processing if currently 'received' / pending (defensive)
    $sql = "UPDATE license_requests
        SET status = 'processing',
            processing_started_at = NOW(),
            processing_by = :admin_id,
            updated_at = NOW()
        WHERE id = :id
          AND LOWER(TRIM(COALESCE(status, ''))) IN ('', 'received', 'pending', 'new')";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':admin_id' => $adminId, ':id' => $id]);

        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Could not set to processing (status may not be received).']);
            exit;
        }

        echo json_encode(['success' => true, 'new_status' => 'processing']);
        exit;
    }

    if ($action === 'complete') {
        // Only mark complete if currently processing
        $sql = "UPDATE license_requests
        SET status = 'complete',
            processed_at = NOW(),
            processed_by = :admin_id,
            updated_at = NOW()
        WHERE id = :id
          AND LOWER(TRIM(COALESCE(status, ''))) IN ('processing', 'process')";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':admin_id' => $adminId, ':id' => $id]);

        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Could not mark complete (status may not be processing).']);
            exit;
        }

        echo json_encode(['success' => true, 'new_status' => 'complete']);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
    exit;
}
