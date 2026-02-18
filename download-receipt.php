<?php
session_start();
require_once __DIR__ . '/assets/app/Auth.php';
require_once __DIR__ . '/assets/app/db_connect.php';

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: /');
    exit;
}
$user = $auth->getCurrentUser();

$receiptId = (int)($_GET['id'] ?? 0);
if (!$receiptId) {
    die('Invalid receipt');
}

// Fetch receipt + verify ownership
$stmt = $pdo->prepare("
    SELECT r.*, cr.user_id, cr.request_type, cr.id as request_id,
           vr.plate, lr.license_number
    FROM receipts r
    INNER JOIN customer_requests cr ON r.request_id = cr.id
    LEFT JOIN vehicle_requests vr ON cr.user_id = vr.user_id 
        AND cr.request_type = 'vehicle'
        AND JSON_EXTRACT(cr.request_data, '$.plate') = vr.plate
    LEFT JOIN license_requests lr ON cr.user_id = lr.user_id 
        AND cr.request_type = 'license'
        AND JSON_EXTRACT(cr.request_data, '$.license_number') = lr.license_number
    WHERE r.id = ? AND cr.user_id = ?
");
$stmt->execute([$receiptId, $user['id']]);
$receipt = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$receipt) {
    die('Receipt not found or access denied.');
}

// Determine identifier (plate or license number)
$identifier = '';
if ($receipt['request_type'] === 'vehicle') {
    $identifier = $receipt['plate'] ?? 'N/A';
} else {
    $identifier = $receipt['license_number'] ?? 'N/A';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?php echo htmlspecialchars($receipt['receipt_number']); ?> – ClearMyRide</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome 6 (minimal usage, optional) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        /* ----- SHARP, PROFESSIONAL, NO ROUNDED CORNERS ----- */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f7f9fc;
            color: #1a202c;
            line-height: 1.5;
            padding: 40px 24px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        /* Receipt Container – clean, sharp, no rounding */
        .receipt-container {
            max-width: 780px;
            width: 100%;
            margin: 0 auto;
        }

        .receipt-card {
            background: white;
            border: 1px solid #e0e5ec;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
            padding: 40px 48px;
        }

        /* Header */
        .receipt-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 2px solid #0A57FF;
        }
        .brand h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #0A57FF;
            margin-bottom: 4px;
            letter-spacing: -0.01em;
        }
        .brand p {
            color: #4a5568;
            font-size: 0.875rem;
        }
        .receipt-meta {
            text-align: right;
        }
        .receipt-meta .receipt-number {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 4px;
        }
        .receipt-meta .receipt-date {
            color: #718096;
            font-size: 0.875rem;
        }

        /* Divider */
        .divider {
            height: 1px;
            background: #edf2f7;
            margin: 24px 0;
        }

        /* Content sections */
        .section-title {
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #4a5568;
            margin-bottom: 16px;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        .detail-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            color: #718096;
            letter-spacing: 0.3px;
            margin-bottom: 4px;
        }
        .detail-value {
            font-size: 1rem;
            font-weight: 600;
            color: #1a202c;
        }
        .detail-value.large {
            font-size: 1.25rem;
        }

        /* Amount table */
        .amount-table {
            width: 100%;
            border-collapse: collapse;
            margin: 16px 0;
        }
        .amount-table td {
            padding: 8px 0;
            border-bottom: 1px solid #edf2f7;
        }
        .amount-table td:last-child {
            text-align: right;
            font-weight: 600;
        }
        .amount-table tr:last-child td {
            border-bottom: none;
            font-size: 1.125rem;
            font-weight: 700;
            color: #0A57FF;
            padding-top: 16px;
        }

        /* Footer */
        .receipt-footer {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #edf2f7;
            text-align: center;
            color: #718096;
            font-size: 0.75rem;
        }

        /* Actions */
        .actions {
            display: flex;
            justify-content: center;
            gap: 16px;
            margin-top: 32px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid transparent;
            transition: all 0.2s;
            background: white;
            color: #1a202c;
            border: 1px solid #e0e5ec;
        }
        .btn:hover {
            background: #f7fafc;
            border-color: #cbd5e0;
        }
        .btn-primary {
            background: #0A57FF;
            color: white;
            border-color: #0A57FF;
        }
        .btn-primary:hover {
            background: #0845cc;
            border-color: #0845cc;
        }
        .btn i {
            font-size: 0.875rem;
        }

        /* Print styles */
        @media print {
            body {
                background: white;
                padding: 0.5in;
            }
            .receipt-card {
                border: none;
                box-shadow: none;
                padding: 0;
            }
            .actions, .btn, .no-print {
                display: none !important;
            }
            .receipt-header {
                border-bottom-color: #000;
            }
            .brand h1 {
                color: #000;
            }
            .amount-table tr:last-child td {
                color: #000;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <!-- Receipt Card -->
        <div class="receipt-card">
            <!-- Header with brand and receipt info -->
            <div class="receipt-header">
                <div class="brand">
                    <h1>ClearMyRide</h1>
                    <p>Official Payment Receipt</p>
                </div>
                <div class="receipt-meta">
                    <div class="receipt-number">#<?php echo htmlspecialchars($receipt['receipt_number']); ?></div>
                    <div class="receipt-date">
                        <i class="fas fa-calendar-alt me-1 no-print" style="margin-right: 4px;"></i>
                        <?php echo date('F j, Y', strtotime($receipt['paid_at'])); ?>
                    </div>
                </div>
            </div>

            <!-- Customer & Request Details -->
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="detail-label">Customer</span>
                    <span class="detail-value"><?php echo htmlspecialchars($user['full_name']); ?></span>
                    <span class="detail-value" style="font-size: 0.875rem; font-weight: 400; color: #4a5568;"><?php echo htmlspecialchars($user['email']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Request #</span>
                    <span class="detail-value"><?php echo $receipt['request_id']; ?></span>
                    <span class="detail-value" style="font-size: 0.875rem; font-weight: 400; color: #4a5568;">
                        <span class="badge" style="background: <?php echo $receipt['request_type'] == 'vehicle' ? '#ebf8ff' : '#edf2f7'; ?>; color: <?php echo $receipt['request_type'] == 'vehicle' ? '#0A57FF' : '#1a202c'; ?>; padding: 4px 8px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; border: 1px solid <?php echo $receipt['request_type'] == 'vehicle' ? '#90cdf4' : '#e2e8f0'; ?>;">
                            <?php echo ucfirst($receipt['request_type']); ?>
                        </span>
                    </span>
                </div>
            </div>

            <div class="detail-grid">
                <div class="detail-item">
                    <span class="detail-label">Identifier</span>
                    <span class="detail-value"><?php echo htmlspecialchars($identifier); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Payment Date</span>
                    <span class="detail-value"><?php echo date('F j, Y \a\t g:i A', strtotime($receipt['paid_at'])); ?></span>
                </div>
            </div>

            <div class="divider"></div>

            <!-- Amount Breakdown -->
            <div>
                <div class="section-title">Payment Summary</div>
                <table class="amount-table">
                    <tr>
                        <td>Service Fee</td>
                        <td>$<?php echo number_format($receipt['amount_paid'] * 0.7, 2); // Placeholder – replace with actual breakdown if available ?></td>
                    </tr>
                    <tr>
                        <td>Government Fees</td>
                        <td>$<?php echo number_format($receipt['amount_paid'] * 0.3, 2); // Placeholder – replace with actual breakdown if available ?></td>
                    </tr>
                    <tr>
                        <td><strong>Total Paid</strong></td>
                        <td><strong>$<?php echo number_format($receipt['amount_paid'], 2); ?></strong></td>
                    </tr>
                </table>
                <p style="font-size: 0.75rem; color: #718096; margin-top: 8px;">
                    <i class="fas fa-check-circle" style="color: #10b981; margin-right: 4px;"></i>
                    Payment confirmed and applied to your request.
                </p>
            </div>

            <!-- Footer -->
            <div class="receipt-footer">
                <p>Thank you for choosing ClearMyRide. Your vehicle or license renewal is complete.</p>
                <p style="margin-top: 8px;">© <?php echo date('Y'); ?> ClearMyRide. All rights reserved.</p>
            </div>
        </div>

        <!-- Action Buttons – hidden when printing -->
        <div class="actions no-print">
            <button onclick="window.print();" class="btn">
                <i class="fas fa-print"></i> Print Receipt
            </button>
            <a href="request-details.php?id=<?php echo $receipt['request_id']; ?>" class="btn">
                <i class="fas fa-arrow-left"></i> Back to Request
            </a>
            <a href="dashboard.php" class="btn btn-primary">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </div>

        <!-- Optional auto-print (remove comment if you want automatic print dialog) -->
        <!-- <script>window.print();</script> -->
    </div>
</body>
</html>