<?php
// assets/app/admin/export_vehicle.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db_connect.php';

// Require admin session
if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo 'Unauthorized';
    exit;
}

// Read filters from GET
$search = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$filterStatus = isset($_GET['status']) ? trim((string)$_GET['status']) : '';

// Build WHERE parts and params (same logic as view_vehicle page)
$whereParts = [];
$params = [];

// search across multiple columns
$search = (string)$search;
if ($search !== '') {
    $cols = ['full_name', 'email', 'phone', 'plate', 'vin'];
    $likeParts = [];
    foreach ($cols as $idx => $col) {
        $ph = "q{$idx}";
        $likeParts[] = "{$col} LIKE :{$ph}";
        $params[$ph] = '%' . $search . '%';
    }
    if (!empty($likeParts)) $whereParts[] = '(' . implode(' OR ', $likeParts) . ')';
}

// status filter — handle DB values 'complete' vs 'processed' and null/empty/pending/new for received
$filterStatus = (string)$filterStatus;
if ($filterStatus !== '') {
    if ($filterStatus === 'received') {
        $whereParts[] = "(status = 'received' OR status IS NULL OR status = '' OR LOWER(status) IN ('pending','new'))";
    } elseif ($filterStatus === 'processed') {
        $whereParts[] = "(LOWER(status) = 'processed' OR LOWER(status) = 'complete')";
    } elseif ($filterStatus === 'processing') {
        $whereParts[] = "LOWER(status) = 'processing'";
    } else {
        // fallback exact match
        $whereParts[] = "status = :status";
        $params['status'] = $filterStatus;
    }
}

$whereSql = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';

// Select columns — exclude id. Adjust the column list if your table has different fields.
// Based on your earlier schema: id, full_name, email, phone, dob, plate, vin, reg_exp, renew_when, status, created_at, updated_at
// We'll select all except id and include attachments aggregated.
$sql = "
SELECT
  vr.full_name,
  vr.email,
  vr.phone,
  vr.dob,
  vr.plate,
  vr.vin,
  vr.reg_exp,
  vr.renew_when,
  vr.status,
  vr.created_at,
  vr.updated_at,
  GROUP_CONCAT(a.file_name SEPARATOR ' ; ') AS attachments
FROM vehicle_requests vr
LEFT JOIN attachments a ON a.parent_type = 'vehicle' AND a.parent_id = vr.id
{$whereSql}
GROUP BY vr.id
ORDER BY vr.id DESC
";

// Prepare & execute
$stmt = $pdo->prepare($sql);

// Bind params (PDO accepts associative array without the leading colon)
foreach ($params as $k => $v) {
    // note: our $k keys don't have leading colon
    $stmt->bindValue(':' . $k, $v, PDO::PARAM_STR);
}
$stmt->execute();

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// CSV output — stream with appropriate headers for download
$filename = 'vehicle_requests_export_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Output BOM for Excel (UTF-8)
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// Header row — human-friendly column names
$headers = [
    'Full name',
    'Email',
    'Phone',
    'DOB',
    'Plate',
    'VIN',
    'Registration Expiry',
    'Renew When',
    'Status',
    'Created At',
    'Updated At',
    'Attachments' // aggregated file names, separated by " ; "
];
fputcsv($out, $headers);

// Write rows
foreach ($rows as $r) {
    // ensure proper formatting for each column; convert nulls to empty strings
    $line = [
        $r['full_name'] ?? '',
        $r['email'] ?? '',
        $r['phone'] ?? '',
        $r['dob'] ?? '',
        $r['plate'] ?? '',
        $r['vin'] ?? '',
        $r['reg_exp'] ?? '',
        $r['renew_when'] ?? '',
        // normalize status label (optional)
        (isset($r['status']) ? $r['status'] : ''),
        $r['created_at'] ?? '',
        $r['updated_at'] ?? '',
        $r['attachments'] ?? ''
    ];
    fputcsv($out, $line);
}

fclose($out);
exit;
