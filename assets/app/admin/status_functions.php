<?php
// admin/status_functions.php
// Shared logic for status changes, logging, and notifications
// Include this in every admin action script.

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__) . '/');
}

require_once ROOT_PATH . 'db_connect.php';
require_once __DIR__ . '/../mail_helper.php';   

/**
 * Execute a status transition with full validation, audit log, and notifications.
 *
 * @param PDO      $pdo
 * @param int      $requestId      customer_requests.id
 * @param string   $newStatus      Target status (must be in allowed transition list)
 * @param string   $actorType      'admin' or 'customer'
 * @param int      $actorId        ID of admin or customer
 * @param string   $note           Optional note for audit
 * @param array    $extraData      Additional data for specific transitions (e.g. invoice_id, refund_id)
 *
 * @return array   ['success' => bool, 'message' => string, 'old_status' => string, 'new_status' => string]
 * @throws Exception
 */
function transitionStatus($pdo, $requestId, $newStatus, $actorType, $actorId, $note = '', $extraData = []) {
    // ---------- 1. Fetch current status ----------
    $stmt = $pdo->prepare("SELECT status FROM customer_requests WHERE id = ?");
    $stmt->execute([$requestId]);
    $current = $stmt->fetchColumn();
    if ($current === false) {
        return ['success' => false, 'message' => 'Request not found.'];
    }
    $oldStatus = $current;

    // ---------- 2. Validate transition rules ----------
    $allowed = [
        'Pending'           => ['Under Review', 'Cancelled'],
        'Under Review'      => ['Awaiting Payment', 'Cancelled'],
        'Awaiting Payment'  => ['Processing', 'Cancelled'],
        'Processing'        => ['Completed', 'Refund Requested'],
        'Completed'         => ['Refund Requested'],
        'Refund Requested'  => ['Refunded', 'Processing'], // rejected -> back to Processing
    ];

    if (!isset($allowed[$oldStatus]) || !in_array($newStatus, $allowed[$oldStatus])) {
        return [
            'success' => false,
            'message' => "Transition from '$oldStatus' to '$newStatus' is not allowed."
        ];
    }

    // ---------- 3. Enforce additional business rules ----------
    // Only allow cancellation before payment confirmation
    if ($newStatus === 'Cancelled' && in_array($oldStatus, ['Processing', 'Completed'])) {
        return ['success' => false, 'message' => 'Cannot cancel after payment is confirmed. Use refund request.'];
    }

    // If moving to Awaiting Payment, ensure an invoice exists
    if ($newStatus === 'Awaiting Payment') {
        $inv = $pdo->prepare("SELECT id FROM invoices WHERE request_id = ? AND status IN ('sent','draft')");
        $inv->execute([$requestId]);
        if (!$inv->fetchColumn()) {
            return ['success' => false, 'message' => 'Cannot set Awaiting Payment without a generated invoice.'];
        }
    }

    // ---------- 4. Perform the update ----------
    $stmt = $pdo->prepare("UPDATE customer_requests SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $requestId]);

    // ---------- 5. Log to audit trail ----------
    $log = $pdo->prepare("
        INSERT INTO audit_log (actor_type, actor_id, action, request_id, old_status, new_status, note, ip_address)
        VALUES (?, ?, 'status_change', ?, ?, ?, ?, ?)
    ");
    $log->execute([
        $actorType,
        $actorId,
        $requestId,
        $oldStatus,
        $newStatus,
        $note ?: null,
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);

    // ---------- 6. Trigger notifications ----------
    notifyStatusChange($pdo, $requestId, $oldStatus, $newStatus, $actorType, $extraData);

    return [
        'success' => true,
        'message' => "Status changed from $oldStatus to $newStatus.",
        'old_status' => $oldStatus,
        'new_status' => $newStatus
    ];
}

/**
 * Send email notifications according to rule #11.
 */
function notifyStatusChange($pdo, $requestId, $oldStatus, $newStatus, $actorType, $extra = []) {
    // Fetch request details and customer email
    $stmt = $pdo->prepare("
        SELECT cr.*, u.email, u.full_name
        FROM customer_requests cr
        JOIN users u ON cr.user_id = u.id
        WHERE cr.id = ?
    ");
    $stmt->execute([$requestId]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$req) return;

    $customerEmail = $req['email'];
    $customerName = $req['full_name'];
        $dashboardLink = base_url('dashboard.php');

    $adminEmail = 'admin@clearmyride.local'; // Configure properly – could fetch from admin_users

    // Map events to email subjects and bodies
    $customerMessages = [
        'Under Review'      => [
            'subject' => 'Your request is now under review',
            'body'    => "Your request #{$requestId} has been moved to <strong>Under Review</strong>. We'll update you soon."
        ],
        'Awaiting Payment'  => [
            'subject' => 'Invoice issued – payment required',
            'body'    => "An invoice has been generated for your request #{$requestId}. Please log in to your dashboard to view and pay."
        ],
        'Processing'        => [
            'subject' => 'Payment confirmed – processing started',
            'body'    => "Your payment has been confirmed. We are now processing your request #{$requestId}."
        ],
        'Completed'         => [
            'subject' => 'Your request has been completed',
            'body'    => "Your request #{$requestId} is now <strong>Completed</strong>. You can view the receipt in your dashboard."
        ],
        'Refund Requested'  => [
            'subject' => 'Refund request received',
            'body'    => "Your refund request for #{$requestId} has been submitted. We'll notify you once it's processed."
        ],
        'Refunded'          => [
            'subject' => 'Refund completed',
            'body'    => "Your refund for request #{$requestId} has been processed. Please check your bank account."
        ],
    ];

    // Customer email (always sent for specific statuses)
    if (isset($customerMessages[$newStatus])) {
        $msg = $customerMessages[$newStatus];
        $fullBody = $msg['body'] . "<br><br><a href='{$dashboardLink}'>View your request</a>";
        sendEmail($customerEmail, $msg['subject'], $fullBody);
    }

    // Admin email for customer-initiated actions
    if ($newStatus === 'Refund Requested' || ($newStatus === 'Cancelled' && $actorType === 'customer')) {
        $subject = ($newStatus === 'Refund Requested') ? 'Customer requested a refund' : 'Request cancelled by customer';
            $adminLink = base_url('admin/dashboard.php');
    $body = "Request #{$requestId} changed to {$newStatus} by customer. <a href='{$adminLink}'>Go to admin dashboard</a>";
        // Use admin notification function
        notify_admins($subject, $body, strip_tags($body));
    }

    // Additional specific notifications
    if (!empty($extra['invoice_id']) && !empty($extra['invoice_number'])) {
        $subject = 'Invoice Issued';
        $body = "Invoice #{$extra['invoice_number']} for request #{$requestId} is ready.<br><a href='{$dashboardLink}'>View invoice</a>";
        sendEmail($customerEmail, $subject, $body);
    }
    if (!empty($extra['document_message'])) {
        $subject = 'Additional Documents Required';
        $body = "Admin has requested additional documents for request #{$requestId}:<br><em>" . htmlspecialchars($extra['document_message']) . "</em><br><a href='{$dashboardLink}'>Upload documents</a>";
        sendEmail($customerEmail, $subject, $body);
    }
    if (!empty($extra['payment_confirmed'])) {
        $subject = 'Payment Confirmed';
        $body = "Your payment for request #{$requestId} has been confirmed. We are now processing your request.<br><a href='{$dashboardLink}'>Track progress</a>";
        sendEmail($customerEmail, $subject, $body);
    }
    if (!empty($extra['refund_proof'])) {
        $subject = 'Refund Processed';
        $body = "Your refund for request #{$requestId} has been completed. Proof is attached in your dashboard.<br><a href='{$dashboardLink}'>View refund</a>";
        sendEmail($customerEmail, $subject, $body);
    }
}

/**
 * Send email using PHPMailer.
 */
function sendEmail($to, $subject, $body) {
    // Fetch customer name
    global $pdo;
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE email = ?");
    $stmt->execute([$to]);
    $name = $stmt->fetchColumn() ?: 'Customer';

    // Build a nice HTML wrapper
    $htmlBody = "
        <div style='font-family:Inter,sans-serif;max-width:600px;margin:0 auto;border:1px solid #eef2ff;'>
            <div style='background:#0A57FF;padding:16px 20px;color:white;font-size:18px;font-weight:600;'>
                ClearMyRide Notification
            </div>
            <div style='padding:20px;color:#111827;'>
                {$body}
                <p style='margin-top:20px;font-size:13px;color:#6b7280;'>ClearMyRide • support@clearmyride.com</p>
            </div>
        </div>
    ";

    return send_customer_email($to, $name, $subject, $htmlBody, strip_tags($body));
}

/**
 * Helper to fetch customer_request_id from vehicle_requests / license_requests using new FK.
 */
function getCustomerRequestId($pdo, $type, $id) {
    if ($type === 'vehicle') {
        $stmt = $pdo->prepare("SELECT customer_request_id FROM vehicle_requests WHERE id = ?");
    } else {
        $stmt = $pdo->prepare("SELECT customer_request_id FROM license_requests WHERE id = ?");
    }
    $stmt->execute([$id]);
    $cid = $stmt->fetchColumn();
    if (!$cid) {
        throw new Exception("No linked customer request found.");
    }
    return (int)$cid;
}