<?php
// assets/app/admin/export_license_xlsx.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db_connect.php';

// adjust path to composer autoload - change if your vendor/ is elsewhere
$autoload = __DIR__ . '/../../../vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    echo 'Composer autoload not found. Run: composer require phpoffice/phpspreadsheet';
    exit;
}
require_once $autoload;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// sanity check
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
    elseif ($fs === 'processed') $statusToken = 'completed';
    elseif ($fs === 'complete') $statusToken = 'completed';
    else $statusToken = preg_replace('/[^a-z0-9_-]/i', '', $fs) ?: 'custom';
}

// Build WHERE parts and params (same rules as view_license page)
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
    $fs = strtolower($filterStatus);
    if ($fs === 'received') {
        $whereParts[] = "(status = 'received' OR status IS NULL OR status = '' OR LOWER(status) IN ('pending','new'))";
    } elseif ($fs === 'processing') {
        $whereParts[] = "LOWER(status) = 'processing'";
    } elseif ($fs === 'processed' || $fs === 'complete') {
        $whereParts[] = "LOWER(status) IN ('processed','complete')";
    } else {
        $whereParts[] = "status = :status";
        $params['status'] = $filterStatus;
    }
}

$whereSql = $whereParts ? ('WHERE ' . implode(' AND ', $whereParts)) : '';

// Query: join attachments and group
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

// Create spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('License Requests');

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

// Write header row using column letters (avoids any missing method issues)
foreach ($headers as $index => $h) {
    $colLetter = Coordinate::stringFromColumnIndex($index + 1);
    $sheet->setCellValue("{$colLetter}1", $h);
}
$lastCol = Coordinate::stringFromColumnIndex(count($headers));
$sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);

// Fill data
$rowNum = 2;
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $colIndex = 1;

    $values = [];
    $values[] = $r['full_name'] ?? '';
    $values[] = $r['email'] ?? '';
    $values[] = $r['phone'] ?? '';
    $values[] = $r['dob'] ?? '';
    $values[] = $r['license_number'] ?? '';
    $values[] = $r['license_exp'] ?? '';
    $values[] = !empty($r['has_issues']) ? '1' : '0';
    $values[] = $r['signature'] ?? '';
    $values[] = $r['consent'] ?? '';

    // normalize status
    $status = isset($r['status']) ? strtolower(trim($r['status'])) : '';
    if ($status === '' || in_array($status, ['pending','new'], true)) $status = 'received';
    if ($status === 'processed') $status = 'complete';
    $statusLabel = ($status === 'received') ? 'Pending' : ucfirst($status);
    $values[] = $statusLabel;

    $values[] = $r['notes'] ?? '';
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
$filename = sprintf('license_requests_%s_%s.xlsx', $statusToken, $now);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
