<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db_connect.php';

if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo 'Unauthorized';
    exit;
}

// Read filters from GET (same as view page)
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$filterStatus = isset($_GET['status']) ? trim($_GET['status']) : '';

// Build WHERE parts – join customer_requests
$whereParts = [];
$params = [];

$baseJoin = "FROM license_requests lr
             JOIN customer_requests cr ON lr.customer_request_id = cr.id
             LEFT JOIN users u ON cr.user_id = u.id";

if ($search !== '') {
    $cols = ['lr.full_name', 'lr.email', 'lr.phone', 'lr.license_number', 'u.email', 'u.full_name'];
    $likeParts = [];
    foreach ($cols as $i => $col) {
        $ph = "q{$i}";
        $likeParts[] = "{$col} LIKE :{$ph}";
        $params[$ph] = '%' . $search . '%';
    }
    $whereParts[] = '(' . implode(' OR ', $likeParts) . ')';
}

if ($filterStatus !== '') {
    if ($filterStatus === 'pending') {
        $whereParts[] = "cr.status = 'Pending'";
    } elseif ($filterStatus === 'processing') {
        $whereParts[] = "cr.status = 'Processing'";
    } elseif ($filterStatus === 'completed') {
        $whereParts[] = "cr.status = 'Completed'";
    }
    // Add more statuses if needed, e.g. 'under review', etc.
}

$whereSql = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

// Query with attachments (grouped)
$sql = "
SELECT
    lr.full_name,
    lr.email,
    lr.phone,
    lr.dob,
    lr.license_number,
    lr.license_exp,
    lr.has_issues,
    lr.signature,
    lr.consent,
    cr.status,
    lr.notes,
    lr.created_at,
    lr.updated_at,
    GROUP_CONCAT(a.file_name SEPARATOR ' ; ') AS attachments
$baseJoin
LEFT JOIN attachments a ON a.parent_type = 'license' AND a.parent_id = lr.id
$whereSql
GROUP BY lr.id
ORDER BY lr.id DESC
";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue(':' . $k, $v, PDO::PARAM_STR);
}
$stmt->execute();

// Send CSV headers
$filename = 'license_requests_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// UTF-8 BOM for Excel
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// CSV headers
fputcsv($out, [
    'Full name', 'Email', 'Phone', 'DOB', 'License number', 'License expiry',
    'Has issues', 'Signature', 'Consent', 'Status', 'Notes', 'Created At',
    'Updated At', 'Attachments'
]);

while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($out, [
        $r['full_name'] ?? '',
        $r['email'] ?? '',
        $r['phone'] ?? '',
        $r['dob'] ?? '',
        $r['license_number'] ?? '',
        $r['license_exp'] ?? '',
        !empty($r['has_issues']) ? '1' : '0',
        $r['signature'] ?? '',
        $r['consent'] ?? '',
        $r['status'] ?? '',           // now from cr.status
        $r['notes'] ?? '',
        $r['created_at'] ?? '',
        $r['updated_at'] ?? '',
        $r['attachments'] ?? ''
    ]);
}
fclose($out);
exit;