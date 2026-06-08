<?php
require 'db.php';
session_start();

if (!isset($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit;
}

$customer_id = $_SESSION['customer_id'];

// Get cart id and items
$stmt = $pdo->prepare("SELECT cart_id FROM CART WHERE customer_id = ?");
$stmt->execute([$customer_id]);
$cart = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cart) {
    header('Location: menu.php');
    exit;
}

$cart_id = $cart['cart_id'];

// Get cart items
$stmt = $pdo->prepare(
    "SELECT m.menu_id, m.menu_name, m.price, co.quantity 
     FROM CART_ORDER co 
     JOIN MENU m ON co.menu_id = m.menu_id 
     WHERE co.cart_id = ?"
);
$stmt->execute([$cart_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($items)) {
    header('Location: cart.php');
    exit;
}

// Calculate total
$total = 0;
foreach ($items as $item) {
    $total += $item['price'] * $item['quantity'];
}

// Tax & charges
$taxRate    = 0.06; // 6% tax
$tax        = $total * $taxRate;
$grandTotal = $total + $tax;

// Insert ORDER
$stmt = $pdo->prepare("INSERT INTO `ORDER` 
    (customer_id, staff_id, order_date, status, total) 
    VALUES (?, NULL, NOW(), 'Pending', ?)");
$stmt->execute([$customer_id, $grandTotal]);
$order_id = $pdo->lastInsertId();

// Insert ORDER_MENU records
$stmtOrderMenu = $pdo->prepare(
    "INSERT INTO ORDER_MENU (order_id, menu_id, quantity) 
     VALUES (?, ?, ?)"
);
foreach ($items as $item) {
    $stmtOrderMenu->execute([$order_id, $item['menu_id'], $item['quantity']]);
}

// Clear cart
$pdo->prepare("DELETE FROM CART_ORDER WHERE cart_id = ?")->execute([$cart_id]);
$pdo->prepare("DELETE FROM CART WHERE cart_id = ?")->execute([$cart_id]);

// Get customer name
$stmtCust = $pdo->prepare("SELECT * FROM CUSTOMER WHERE customer_id = ?");
$stmtCust->execute([$customer_id]);
$customer = $stmtCust->fetch(PDO::FETCH_ASSOC);

// Order date & time
$orderDate = date('d M Y');
$orderTime = date('h:i A');
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order Receipt - MIA COFFEE</title>
<style>
  * {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
  }

  body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background: #f0f2f5;
    min-height: 100vh;
    padding: 30px 15px;
    display: flex;
    align-items: flex-start;
    justify-content: center;
  }

  .receipt-wrapper {
    width: 100%;
    max-width: 420px;
  }

  /* ===== SUCCESS ANIMATION ===== */
  .success-header {
    text-align: center;
    margin-bottom: 20px;
    animation: fadeDown 0.5s ease;
  }

  @keyframes fadeDown {
    from { opacity: 0; transform: translateY(-20px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  .success-icon {
    width: 70px;
    height: 70px;
    background: #28a745;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 12px;
    font-size: 2rem;
    box-shadow: 0 5px 20px rgba(40,167,69,0.4);
    animation: popIn 0.5s ease 0.2s both;
  }

  @keyframes popIn {
    from { transform: scale(0); }
    to   { transform: scale(1); }
  }

  .success-header h2 {
    font-size: 1.4rem;
    font-weight: 700;
    color: #222;
    margin-bottom: 5px;
  }

  .success-header p {
    color: #888;
    font-size: 0.9rem;
  }

  /* ===== RECEIPT CARD ===== */
  .receipt-card {
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 5px 30px rgba(0,0,0,0.1);
    overflow: hidden;
    animation: fadeUp 0.5s ease 0.3s both;
  }

  @keyframes fadeUp {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  /* Receipt Top */
  .receipt-top {
    background: linear-gradient(135deg, #e31937, #b21427);
    padding: 25px 25px 35px;
    text-align: center;
    color: #fff;
    position: relative;
  }

  .cafe-logo {
    font-size: 2rem;
    margin-bottom: 5px;
  }

  .cafe-name {
    font-size: 1.3rem;
    font-weight: 700;
    letter-spacing: 1px;
    margin-bottom: 3px;
  }

  .cafe-tagline {
    font-size: 0.8rem;
    opacity: 0.85;
  }

  /* Zigzag bottom edge */
  .receipt-top::after {
    content: '';
    position: absolute;
    bottom: -12px;
    left: 0;
    right: 0;
    height: 25px;
    background: #fff;
    clip-path: polygon(
      0% 100%, 2.5% 0%, 5% 100%, 7.5% 0%, 10% 100%,
      12.5% 0%, 15% 100%, 17.5% 0%, 20% 100%,
      22.5% 0%, 25% 100%, 27.5% 0%, 30% 100%,
      32.5% 0%, 35% 100%, 37.5% 0%, 40% 100%,
      42.5% 0%, 45% 100%, 47.5% 0%, 50% 100%,
      52.5% 0%, 55% 100%, 57.5% 0%, 60% 100%,
      62.5% 0%, 65% 100%, 67.5% 0%, 70% 100%,
      72.5% 0%, 75% 100%, 77.5% 0%, 80% 100%,
      82.5% 0%, 85% 100%, 87.5% 0%, 90% 100%,
      92.5% 0%, 95% 100%, 97.5% 0%, 100% 100%
    );
  }

  /* Receipt Body */
  .receipt-body {
    padding: 30px 25px 20px;
  }

  /* Order Info */
  .order-info {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 2px dashed #eee;
  }

  .info-box {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 10px 12px;
  }

  .info-label {
    font-size: 0.72rem;
    color: #999;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 3px;
  }

  .info-value {
    font-size: 0.9rem;
    font-weight: 700;
    color: #222;
  }

  .info-value.order-id {
    color: #1558b0;
    font-size: 1rem;
  }

  .info-value.status {
    color: #f39c12;
  }

  /* Items Section */
  .items-title {
    font-size: 0.85rem;
    font-weight: 700;
    color: #888;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 12px;
  }

  .item-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 10px 0;
    border-bottom: 1px solid #f5f5f5;
    gap: 10px;
  }

  .item-row:last-child {
    border-bottom: none;
  }

  .item-left {
    flex: 1;
  }

  .item-name {
    font-size: 0.92rem;
    font-weight: 600;
    color: #222;
    margin-bottom: 2px;
  }

  .item-qty {
    font-size: 0.78rem;
    color: #999;
  }

  .item-price {
    font-size: 0.92rem;
    font-weight: 700;
    color: #222;
    white-space: nowrap;
  }

  /* Totals Section */
  .totals-section {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 2px dashed #eee;
  }

  .total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 5px 0;
    font-size: 0.88rem;
    color: #555;
  }

  .total-row.tax {
    color: #888;
    font-size: 0.82rem;
  }

  .total-row.grand {
    margin-top: 10px;
    padding-top: 12px;
    border-top: 2px solid #eee;
    font-size: 1.1rem;
    font-weight: 800;
    color: #222;
  }

  .total-row.grand .grand-amount {
    color: #1558b0;
    font-size: 1.2rem;
  }

  /* Receipt Bottom */
  .receipt-bottom {
    padding: 20px 25px;
    background: #fafafa;
    border-top: 2px dashed #eee;
    text-align: center;
  }

  .status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffc107;
    padding: 6px 16px;
    border-radius: 50px;
    font-size: 0.82rem;
    font-weight: 700;
    margin-bottom: 15px;
  }

  .status-dot {
    width: 8px;
    height: 8px;
    background: #ffc107;
    border-radius: 50%;
    animation: blink 1s infinite;
  }

  @keyframes blink {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.3; }
  }

  .thank-you {
    font-size: 0.88rem;
    color: #888;
    margin-bottom: 5px;
  }

  .barcode {
    font-size: 2rem;
    letter-spacing: 3px;
    color: #ddd;
    margin: 10px 0;
    font-family: monospace;
  }

  .order-ref {
    font-size: 0.75rem;
    color: #bbb;
  }

  /* ===== ACTION BUTTONS ===== */
  .action-buttons {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 20px;
    animation: fadeUp 0.5s ease 0.5s both;
  }

  .btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px;
    border-radius: 12px;
    font-size: 0.95rem;
    font-weight: 700;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
  }

  .btn-primary {
    background: linear-gradient(135deg, #e31937, #b21427);
    color: #fff;
    box-shadow: 0 4px 15px rgba(227,25,55,0.3);
  }

  .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(227,25,55,0.4);
  }

  .btn-secondary {
    background: #f0f2f5;
    color: #555;
  }

  .btn-secondary:hover {
    background: #e0e0e0;
    transform: translateY(-1px);
  }

  /* Print styles */
  @media print {
    body {
      background: #fff;
      padding: 0;
    }

    .action-buttons {
      display: none;
    }

    .receipt-card {
      box-shadow: none;
      border-radius: 0;
    }

    .success-header {
      display: none;
    }
  }

  /* Responsive */
  @media (max-width: 480px) {
    body {
      padding: 15px 10px;
    }

    .receipt-body {
      padding: 25px 18px 15px;
    }

    .order-info {
      grid-template-columns: 1fr 1fr;
      gap: 8px;
    }
  }
</style>
</head>
<body>

<div class="receipt-wrapper">

  <!-- Success Header -->
  <div class="success-header">
    <div class="success-icon">✅</div>
    <h2>Order Placed!</h2>
    <p>Your order has been received successfully</p>
  </div>

  <!-- Receipt Card -->
  <div class="receipt-card">

    <!-- Top Header -->
    <div class="receipt-top">
      <div class="cafe-logo">☕</div>
      <div class="cafe-name">MIA COFFEE</div>
      <div class="cafe-tagline">Thank you for your order!</div>
    </div>

    <!-- Body -->
    <div class="receipt-body">

      <!-- Order Info Grid -->
      <div class="order-info">
        <div class="info-box">
          <div class="info-label">Order ID</div>
                   <div class="info-value order-id">#<?= str_pad($order_id, 4, '0', STR_PAD_LEFT) ?></div>
        </div>

        <div class="info-box">
          <div class="info-label">Status</div>
          <div class="info-value status">⏳ Pending</div>
        </div>

        <div class="info-box">
          <div class="info-label">Date</div>
          <div class="info-value"><?= $orderDate ?></div>
        </div>

        <div class="info-box">
          <div class="info-label">Time</div>
          <div class="info-value"><?= $orderTime ?></div>
        </div>

        <div class="info-box" style="grid-column: span 2;">
          <div class="info-label">Customer</div>
          <div class="info-value">
            <?= htmlspecialchars($customer['name'] ?? 
                $customer['customer_name'] ?? 
                'Customer #' . $customer_id) ?>
          </div>
        </div>
      </div>

      <!-- Items -->
      <div class="items-title">🧾 Order Items</div>

      <?php foreach ($items as $item):
        $subtotal = $item['price'] * $item['quantity'];
      ?>
        <div class="item-row">
          <div class="item-left">
            <div class="item-name">
              <?= htmlspecialchars($item['menu_name']) ?>
            </div>
            <div class="item-qty">
              <?= $item['quantity'] ?> x 
              RM <?= number_format($item['price'], 2) ?>
            </div>
          </div>
          <div class="item-price">
            RM <?= number_format($subtotal, 2) ?>
          </div>
        </div>
      <?php endforeach; ?>

      <!-- Totals -->
      <div class="totals-section">
        <div class="total-row">
          <span>Subtotal</span>
          <span>RM <?= number_format($total, 2) ?></span>
        </div>
        <div class="total-row tax">
          <span>Tax (6%)</span>
          <span>RM <?= number_format($tax, 2) ?></span>
        </div>
        <div class="total-row grand">
          <span>Total</span>
          <span class="grand-amount">
            RM <?= number_format($grandTotal, 2) ?>
          </span>
        </div>
      </div>

    </div>

    <!-- Receipt Bottom -->
    <div class="receipt-bottom">

      <!-- Status Badge -->
      <div class="status-badge">
        <span class="status-dot"></span>
        Order is being prepared
      </div>

      <div class="thank-you">
        Thank you for dining with us! 🙏
      </div>

      <!-- Barcode Design -->
      <div class="barcode">
        ||||| |||| ||||| ||||
      </div>

      <div class="order-ref">
        Ref: CD-<?= date('Ymd') ?>-<?= str_pad($order_id, 4, '0', STR_PAD_LEFT) ?>
      </div>

    </div>

  </div>
  <!-- End Receipt Card -->

  <!-- Action Buttons -->
  <div class="action-buttons">

    <button class="btn btn-secondary" onclick="window.print()">
      🖨️ Print Receipt
    </button>

    <a href="menu.php" class="btn btn-primary">
      🍽️ Back to Menu
    </a>

  </div>

</div>

</body>
</html>