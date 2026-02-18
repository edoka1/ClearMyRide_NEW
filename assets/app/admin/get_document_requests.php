<?php
session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db_connect.php';

if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$requestId = isset($_GET['request_id']) ? (int)$_GET['request_id'] : 0;
$requestType = isset($_GET['type']) ? trim($_GET['type']) : '';

if (!$requestId || !in_array($requestType, ['vehicle', 'license'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid parameters']);
    exit;
}

try {
    // $requestId is customer_requests.id – use directly
    $customerRequestId = $requestId;

    $stmt = $pdo->prepare("
        SELECT 
            adr.*,
            au.username as admin_name,
            (
                SELECT COALESCE(
                    JSON_ARRAYAGG(
                        JSON_OBJECT(
                            'id', ad.id,
                            'file_name', ad.file_name,
                            'file_path', ad.file_path,
                            'uploaded_at', ad.uploaded_at
                        )
                    ), JSON_ARRAY()
                )
                FROM additional_documents ad
                WHERE ad.document_request_id = adr.id
            ) as attachments
        FROM additional_document_requests adr
        LEFT JOIN admin_users au ON adr.admin_id = au.id
        WHERE adr.request_id = ?
        ORDER BY adr.requested_at DESC
    ");
    $stmt->execute([$customerRequestId]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode JSON – ensures it's an array
    foreach ($requests as &$req) {
        $req['attachments'] = json_decode($req['attachments'], true) ?: [];
    }

    header('Content-Type: application/json');
    echo json_encode($requests);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(404);
    echo json_encode(['error' => $e->getMessage()]);
}