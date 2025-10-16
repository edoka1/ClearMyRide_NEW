<?php
// assets/app/admin/export_vehicle_xlsx.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db_connect.php';

// Composer autoload (adjust path if your vendor is elsewhere)
require_once __DIR__ . '/../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// sanity checks
if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
    http_response_code(500);
    echo 'PhpSpreadsheet not loaded — check your require path to vendor/autoload.php';
    exit;
}

// require admin session
if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo 'Unauthorized';
    exit;
}

// Read filters from GET
$search = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$filterStatus = isset($_GET['status']) ? trim((string)$_GET['status']) : '';

// Map filterStatus to filename token
$statusToken = 'all';
if ($filterStatus !== '') {
    $fs = strtolower($filterStatus);
    if ($fs === 'received') $statusToken = 'pending';
    elseif ($fs === 'processing') $statusToken = 'processing';
    elseif ($fs === 'processed' || $fs === 'complete') $statusToken = 'completed';
    else $statusToken = preg_replace('/[^a-z0-9_-]/i', '', $fs) ?: 'custom';
}

// Build WHERE parts and params — same filtering rules as view_vehicle page
$whereParts = [];
$params = [];

if ($search !== '') {
    $cols = ['full_name', 'email', 'phone', 'plate', 'vin'];
    $likeParts = [];
    foreach ($cols as $i => $col) {
        $ph = "q{$i}";
        $likeParts[] = "{$col} LIKE :{$ph}";
        $params[$ph] = '%' . $search . '%';
    }
    if (!empty($likeParts)) $whereParts[] = '(' . implode(' OR ', $likeParts) . ')';
}

if ($filterStatus !== '') {
    $fs = strtolower($filterStatus);
    if ($fs === 'received') {
        $whereParts[] = "(status = 'received' OR status IS NULL OR status = '' OR LOWER(status) IN ('pending','new'))";
    } elseif ($fs === 'processed' || $fs === 'complete') {
        $whereParts[] = "(LOWER(status) = 'processed' OR LOWER(status) = 'complete')";
    } elseif ($fs === 'processing') {
        $whereParts[] = "LOWER(status) = 'processing'";
    } else {
        $whereParts[] = "status = :status";
        $params['status'] = $filterStatus;
    }
}

$whereSql = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';

// Query: join attachments and group
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

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue(':' . $k, $v, PDO::PARAM_STR);
}
$stmt->execute();

// Prepare spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Vehicle Requests');

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
    'Attachments'
];

// Write header row
foreach ($headers as $index => $h) {
    $colLetter = Coordinate::stringFromColumnIndex($index + 1);
    $sheet->setCellValue("{$colLetter}1", $h);
}
$lastCol = Coordinate::stringFromColumnIndex(count($headers));
$sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);

// Fill rows
$rowNum = 2;
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $colIndex = 1;

    $values = [];
    $values[] = $r['full_name'] ?? '';
    $values[] = $r['email'] ?? '';
    $values[] = $r['phone'] ?? '';
    $values[] = $r['dob'] ?? '';
    $values[] = $r['plate'] ?? '';
    $values[] = $r['vin'] ?? '';
    $values[] = $r['reg_exp'] ?? '';
    $values[] = $r['renew_when'] ?? '';

    // Normalize status text
    $status = isset($r['status']) ? strtolower(trim($r['status'])) : '';
    if ($status === '' || in_array($status, ['pending','new'], true)) $status = 'received';
    if ($status === 'processed') $status = 'complete';
    $statusLabel = ($status === 'received') ? 'Pending' : ucfirst($status);
    $values[] = $statusLabel;

    $values[] = $r['created_at'] ?? '';
    $values[] = $r['updated_at'] ?? '';
    $values[] = $r['attachments'] ?? '';

    foreach ($values as $v) {
        $colLetter = Coordinate::stringFromColumnIndex($colIndex);
        $sheet->setCellValue("{$colLetter}{$rowNum}", $v);
        $colIndex++;
    }
    $rowNum++;
}

// Auto-size columns
for ($ci = 1; $ci <= count($headers); $ci++) {
    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($ci))->setAutoSize(true);
}

// Output - include filter token in filename
$now = date('Ymd_His');
$filename = sprintf('vehicle_requests_%s_%s.xlsx', $statusToken, $now);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'. $filename .'"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
