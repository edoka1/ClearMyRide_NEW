<?php
session_start();
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db_connect.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$receiptId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$receiptId) die('Invalid receipt');

$stmt = $pdo->prepare("
    SELECT r.*, cr.request_type, cr.id as request_id,
           vr.plate, lr.license_number
    FROM receipts r
    JOIN customer_requests cr ON r.request_id = cr.id
    LEFT JOIN vehicle_requests vr ON vr.customer_request_id = cr.id AND cr.request_type = 'vehicle'
    LEFT JOIN license_requests lr ON lr.customer_request_id = cr.id AND cr.request_type = 'license'
    WHERE r.id = ?
");
$stmt->execute([$receiptId]);
$receipt = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$receipt) die('Receipt not found');

$identifier = $receipt['request_type'] == 'vehicle' ? $receipt['plate'] : $receipt['license_number'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Receipt #<?php echo htmlspecialchars($receipt['receipt_number']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { border-radius: 0 !important; margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: white; padding: 2rem; }
        .receipt { max-width: 700px; margin: 0 auto; border: 1px solid #000; padding: 2rem; }
        .header { border-bottom: 2px solid #000; padding-bottom: 1rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; }
        .brand { font-size: 1.5rem; font-weight: 700; color: #0A57FF; }
        .details p { margin: 0.5rem 0; }
        .total { font-size: 1.2rem; font-weight: 700; border-top: 2px solid #000; padding-top: 1rem; margin-top: 1rem; }
        .footer { margin-top: 2rem; font-size: 0.8rem; color: #555; text-align: center; }
        .btn { display: inline-block; margin-top: 1rem; padding: 0.5rem 1rem; border: 1px solid #000; background: white; text-decoration: none; color: #000; }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <span class="brand">ClearMyRide</span>
            <span>Receipt #<?php echo htmlspecialchars($receipt['receipt_number']); ?></span>
        </div>
        <div class="details">
            <p><strong>Request ID:</strong> #<?php echo $receipt['request_id']; ?></p>
            <p><strong>Service:</strong> <?php echo ucfirst($receipt['request_type']); ?> Renewal</p>
            <p><strong>Identifier:</strong> <?php echo htmlspecialchars($identifier); ?></p>
            <p><strong>Date Paid:</strong> <?php echo date('F j, Y', strtotime($receipt['paid_at'])); ?></p>
            <p class="total"><strong>Amount Paid:</strong> $<?php echo number_format($receipt['amount_paid'], 2); ?></p>
        </div>
        <div class="footer">Thank you for using ClearMyRide.</div>
    </div>
    <div style="text-align: center; margin-top: 1.5rem;">
        <button class="btn" onclick="window.print()">Print Receipt</button>
        <a href="view_license.php" class="btn">Back to License Requests</a>
    </div>
</body>
</html>