<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db_connect.php';

// Autoload PhpSpreadsheet (adjust path if needed)
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

if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo 'Unauthorized';
    exit;
}

// Filters
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$filterStatus = isset($_GET['status']) ? trim($_GET['status']) : '';

// Build WHERE (same as CSV export)
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
}

$whereSql = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

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

// Create spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('License Requests');

$headers = [
    'Full name', 'Email', 'Phone', 'DOB', 'License number', 'License expiry',
    'Has issues', 'Signature', 'Consent', 'Status', 'Notes', 'Created At',
    'Updated At', 'Attachments'
];

// Write header row
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
    $values = [
        $r['full_name'] ?? '',
        $r['email'] ?? '',
        $r['phone'] ?? '',
        $r['dob'] ?? '',
        $r['license_number'] ?? '',
        $r['license_exp'] ?? '',
        !empty($r['has_issues']) ? '1' : '0',
        $r['signature'] ?? '',
        $r['consent'] ?? '',
        $r['status'] ?? '',
        $r['notes'] ?? '',
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

// Output
$statusToken = $filterStatus ?: 'all';
$now = date('Ymd_His');
$filename = "license_requests_{$statusToken}_{$now}.xlsx";

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;