<?php
session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/status_functions.php';

if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$requestId = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;
$requestType = $_GET['type'] ?? '';

if (!$requestId || !in_array($requestType, ['vehicle', 'license'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid parameters']);
    exit;
}

try {
    // --- $requestId is already customer_requests.id ---
    $customerRequestId = $requestId;

    $response = ['invoice' => null, 'proofs' => []];

    // Fetch invoice for this customer_request_id
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE request_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$customerRequestId]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($invoice) {
        $response['invoice'] = $invoice;
        $stmt2 = $pdo->prepare("SELECT * FROM payment_proofs WHERE invoice_id = ? ORDER BY uploaded_at DESC");
        $stmt2->execute([$invoice['id']]);
        $response['proofs'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    }

    header('Content-Type: application/json');
    echo json_encode($response);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
} catch (Exception $e) {
    http_response_code(404);
    echo json_encode(['error' => $e->getMessage()]);
}