<?php
// assets/app/admin/export_license_csv.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db_connect.php';

if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo 'Unauthorized';
    exit;
}

// Read filters from GET
$search = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$filterStatus = isset($_GET['status']) ? trim((string)$_GET['status']) : '';

// Build WHERE parts and params
$whereParts = [];
$params = [];

if ($search !== '') {
    $cols = ['full_name','email','phone','license_number'];
    $likeParts = [];
    foreach ($cols as $i => $col) {
        $ph = "q{$i}";
        $likeParts[] = "{$col} LIKE :{$ph}";
        $params[$ph] = '%' . $search . '%';
    }
    if ($likeParts) $whereParts[] = '(' . implode(' OR ', $likeParts) . ')';
}

if ($filterStatus !== '') {
    if ($filterStatus === 'received') {
        $whereParts[] = "(status = 'received' OR status IS NULL OR status = '' OR LOWER(status) IN ('pending','new'))";
    } elseif ($filterStatus === 'processing') {
        $whereParts[] = "LOWER(status) = 'processing'";
    } elseif ($filterStatus === 'processed') {
        $whereParts[] = "LOWER(status) IN ('processed','complete')";
    } else {
        // direct match fallback
        $whereParts[] = "status = :status";
        $params['status'] = $filterStatus;
    }
}

$whereSql = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';

// Query: join attachments and group them
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
  lr.status,
  lr.notes,
  lr.created_at,
  lr.updated_at,
  GROUP_CONCAT(a.file_name SEPARATOR ' ; ') AS attachments
FROM license_requests lr
LEFT JOIN attachments a ON a.parent_type = 'license' AND a.parent_id = lr.id
{$whereSql}
GROUP BY lr.id
ORDER BY lr.id DESC
";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue(':' . $k, $v, PDO::PARAM_STR);
}
$stmt->execute();

// Prepare CSV (UTF-8 BOM so Excel opens encoding correctly)
$filename = 'license_requests_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// output BOM for Excel (UTF-8)
echo "\xEF\xBB\xBF";

// CSV headers (exclude id)
$headers = [
    'Full name',
    'Email',
    'Phone',
    'DOB',
    'License number',
    'License expiry',
    'Has issues',
    'Signature',
    'Consent',
    'Status',
    'Notes',
    'Created At',
    'Updated At',
    'Attachments'
];

$out = fopen('php://output', 'w');
// fputcsv uses comma by default
fputcsv($out, $headers);

while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // normalize status similar to UI
    $status = isset($r['status']) ? strtolower(trim($r['status'])) : '';
    if ($status === '' || in_array($status, ['pending','new'])) $status = 'received';
    if ($status === 'processed') $status = 'complete';
    $statusLabel = ($status === 'received') ? 'Pending' : ucfirst($status);

    $row = [
        $r['full_name'] ?? '',
        $r['email'] ?? '',
        $r['phone'] ?? '',
        $r['dob'] ?? '',
        $r['license_number'] ?? '',
        $r['license_exp'] ?? '',
        !empty($r['has_issues']) ? '1' : '0',
        $r['signature'] ?? '',
        $r['consent'] ?? '',
        $statusLabel,
        $r['notes'] ?? '',
        $r['created_at'] ?? '',
        $r['updated_at'] ?? '',
        $r['attachments'] ?? ''
    ];
    fputcsv($out, $row);
}
fclose($out);
exit;
