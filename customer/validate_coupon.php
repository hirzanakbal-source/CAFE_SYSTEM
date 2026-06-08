<?php
require 'db.php';
session_start();

// ===== ONLY FOR LOGGED IN USERS =====
if (!isset($_SESSION['customer_id'])) {
    echo json_encode([
        'valid'   => false,
        'message' => 'Please login to use coupons.',
    ]);
    exit;
}

header('Content-Type: application/json');

$code     = strtoupper(trim($_POST['code']     ?? ''));
$subtotal = floatval($_POST['subtotal']         ?? 0);

if (empty($code)) {
    echo json_encode([
        'valid'   => false,
        'message' => 'Please enter a coupon code.',
    ]);
    exit;
}

try {
    // ===== CHECK IF COUPON TABLE EXISTS =====
    $tableCheck  = $pdo->query("SHOW TABLES LIKE 'COUPON'");
    $tableExists = $tableCheck->rowCount() > 0;

    if (!$tableExists) {
        echo json_encode([
            'valid'   => false,
            'message' => 'Coupon system not available.',
        ]);
        exit;
    }

    // ===== FIND COUPON =====
    $stmt = $pdo->prepare("
        SELECT *
        FROM COUPON
        WHERE code = ?
        AND is_active = 1
        AND expiry_date >= CURDATE()
    ");
    $stmt->execute([$code]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) {
        echo json_encode([
            'valid'   => false,
            'message' => 'Invalid or expired coupon code.',
        ]);
        exit;
    }

    // ===== CHECK MINIMUM ORDER =====
    if (!empty($coupon['min_order']) && $subtotal < $coupon['min_order']) {
        echo json_encode([
            'valid'   => false,
            'message' => 'Minimum order of RM '
                       . number_format($coupon['min_order'], 2)
                       . ' required for this coupon.',
        ]);
        exit;
    }

    // ===== CALCULATE DISCOUNT =====
    $discount = 0;
    if ($coupon['discount_type'] === 'percent') {
        $discount = $subtotal * ($coupon['discount_amount'] / 100);
    } else {
        $discount = floatval($coupon['discount_amount']);
    }

    // Discount cannot exceed subtotal
    $discount = min($discount, $subtotal);

    echo json_encode([
        'valid'    => true,
        'discount' => round($discount, 2),
        'message'  => 'Coupon applied successfully!',
        'type'     => $coupon['discount_type'],
        'amount'   => $coupon['discount_amount'],
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'valid'   => false,
        'message' => 'Error validating coupon.',
    ]);
}
?>