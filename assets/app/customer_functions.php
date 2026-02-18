<?php
// assets/app/customer_functions.php
// Customer‑side status transitions – Cancel, Refund Request
// Version 2.0 – with legacy status normalisation

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mail_helper.php';

/**
 * Convert any legacy status string to the current 8‑value ENUM.
 */
function normaliseStatus($status) {
    if ($status === null) return 'Pending';
    
    $lower = strtolower(trim($status));
    $lower = str_replace('_', ' ', $lower);
    
    $map = [
        'submitted'        => 'Pending',
        'pending'          => 'Pending',
        'new'              => 'Pending',
        'received'         => 'Pending',
        
        'in review'        => 'Under Review',
        'under review'     => 'Under Review',
        'review'           => 'Under Review',
        'in_review'        => 'Under Review',
        
        'awaiting payment' => 'Awaiting Payment',
        'awaiting_payment' => 'Awaiting Payment',
        'invoice sent'     => 'Awaiting Payment',
        
        'processing'       => 'Processing',
        'process'          => 'Processing',
        
        'completed'        => 'Completed',
        'complete'         => 'Completed',
        'processed'        => 'Completed',
        
        'cancelled'        => 'Cancelled',
        'canceled'         => 'Cancelled',
        
        'refund requested' => 'Refund Requested',
        'refund_requested' => 'Refund Requested',
        
        'refunded'         => 'Refunded',
    ];
    
    return $map[$lower] ?? $status;
}

/**
 * Execute a customer‑initiated status transition.
 *
 * @param PDO      $pdo
 * @param int      $requestId      customer_requests.id
 * @param string   $newStatus      'Cancelled' or 'Refund Requested'
 * @param int      $customerId     users.id
 * @param string   $note           Optional note
 * @param array    $extra          Extra data (e.g. refund amount, bank details)
 *
 * @return array   ['success' => bool, 'message' => string]
 */
function customerTransitionStatus($pdo, $requestId, $newStatus, $customerId, $note = '', $extra = []) {
    // 1. Fetch current status and invoice status
    $stmt = $pdo->prepare("
        SELECT cr.status, i.status as invoice_status, cr.request_type
        FROM customer_requests cr
        LEFT JOIN invoices i ON cr.invoice_id = i.id
        WHERE cr.id = ? AND cr.user_id = ?
    ");
    $stmt->execute([$requestId, $customerId]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$data) {
        return ['success' => false, 'message' => 'Request not found.'];
    }
    
    // Normalise the current status to modern ENUM
    $oldStatus = normaliseStatus($data['status']);
    $invoiceStatus = $data['invoice_status'] ?? null;

    // 2. Validate according to Customer Permission Rules
    if ($newStatus === 'Cancelled') {
        // Customer can cancel only if payment NOT confirmed
        if ($invoiceStatus === 'paid') {
            return ['success' => false, 'message' => 'Cannot cancel – payment already completed. Please request a refund.'];
        }
        // Allow cancellation from any status that is essentially pending, under review, or awaiting payment
        $allowedForCancel = ['Pending', 'Under Review', 'Awaiting Payment'];
        if (!in_array($oldStatus, $allowedForCancel)) {
            return ['success' => false, 'message' => 'Cannot cancel request at this stage.'];
        }
    } elseif ($newStatus === 'Refund Requested') {
        // Customer can request refund only after payment confirmed AND status is 'Processing'
        if ($invoiceStatus !== 'paid') {
            return ['success' => false, 'message' => 'Refund can only be requested after payment is confirmed.'];
        }
        if ($oldStatus !== 'Processing') {
            return ['success' => false, 'message' => 'Refund can only be requested while request is in processing.'];
        }
    } else {
        return ['success' => false, 'message' => 'Invalid action.'];
    }

    // 3. Update customer_requests status (store the new ENUM value)
    $stmt = $pdo->prepare("UPDATE customer_requests SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $requestId]);

    // 4. Log to audit trail
    $log = $pdo->prepare("
        INSERT INTO audit_log (actor_type, actor_id, action, request_id, old_status, new_status, note, ip_address)
        VALUES ('customer', ?, 'status_change', ?, ?, ?, ?, ?)
    ");
    $log->execute([
        $customerId,
        $requestId,
        $oldStatus,          // store the normalised old status for audit
        $newStatus,
        $note ?: null,
        $_SERVER['REMOTE_ADDR'] ?? null
    ]);

        // 5. Send admin notification
     $adminLink = base_url('admin/dashboard.php'); // Adjust if your admin URL is different
    $subject = ($newStatus === 'Cancelled') ? 'Request Cancelled by Customer' : 'Refund Requested by Customer';
    $body = "
        <h2>Customer Action Notification</h2>
        <p>A customer has performed an action on request <strong>#{$requestId}</strong>.</p>
        <ul>
            <li><strong>Action:</strong> {$newStatus}</li>
            <li><strong>Customer ID:</strong> {$customerId}</li>
            <li><strong>Previous Status:</strong> {$oldStatus}</li>
            <li><strong>New Status:</strong> {$newStatus}</li>
            " . ($note ? "<li><strong>Note:</strong> " . htmlspecialchars($note) . "</li>" : "") . "
        </ul>
        <p><a href='{$adminLink}'>View in admin dashboard</a></p>
    ";
    $altBody = "Customer action: {$newStatus} on request #{$requestId}. View at {$adminLink}";

    notify_admins($subject, $body, $altBody);

    return [
        'success' => true,
        'message' => $newStatus === 'Cancelled' ? 'Request cancelled successfully.' : 'Refund request submitted.',
        'old_status' => $oldStatus,
        'new_status' => $newStatus
    ];
}