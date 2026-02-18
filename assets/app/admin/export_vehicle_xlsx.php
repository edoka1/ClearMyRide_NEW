<?php
// admin/export_vehicle_xlsx.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db_connect.php';

// Composer autoload (adjust path if needed)
require_once __DIR__ . '/../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo 'Unauthorized';
    exit;
}

// Read filters from GET
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$filterStatus = isset($_GET['status']) ? trim($_GET['status']) : '';

// Build WHERE parts – join with customer_requests
$whereParts = [];
$params = [];

$baseJoin = "FROM vehicle_requests vr
             JOIN customer_requests cr ON vr.customer_request_id = cr.id
             LEFT JOIN users u ON cr.user_id = u.id";

if ($search !== '') {
    $cols = ['vr.full_name', 'vr.email', 'vr.phone', 'vr.plate', 'vr.vin', 'u.email', 'u.full_name'];
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
    // Add more statuses if your dropdown includes others
}

$whereSql = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

// Query with attachments (grouped)
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
    cr.status,
    vr.created_at,
    vr.updated_at,
    GROUP_CONCAT(a.file_name SEPARATOR ' ; ') AS attachments
$baseJoin
LEFT JOIN attachments a ON a.parent_type = 'vehicle' AND a.parent_id = vr.id
$whereSql
GROUP BY vr.id
ORDER BY vr.id DESC
";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue(':' . $k, $v, PDO::PARAM_STR);
}
$stmt->execute();

// Create spreadsheet
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
    $values = [
        $r['full_name'] ?? '',
        $r['email'] ?? '',
        $r['phone'] ?? '',
        $r['dob'] ?? '',
        $r['plate'] ?? '',
        $r['vin'] ?? '',
        $r['reg_exp'] ?? '',
        $r['renew_when'] ?? '',
        $r['status'] ?? '',          // now from cr.status
        $r['created_at'] ?? '',
        $r['updated_at'] ?? '',
        $r['attachments'] ?? ''
    ];
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

// Output filename with filter token
$statusToken = $filterStatus ?: 'all';
$now = date('Ymd_His');
$filename = "vehicle_requests_{$statusToken}_{$now}.xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;