<?php
require 'db.php';
session_start();

// ===== CHECK SESSION =====
if (!isset($_SESSION['customer_id']) && !isset($_SESSION['guest'])) {
    header('Location: welcome.php');
    exit;
}

$isGuest     = isset($_SESSION['guest']) && $_SESSION['guest'] === true;
$cart_items  = [];
$customer_id = null;

if ($isGuest) {
    // ===== GUEST: Get cart from SESSION =====
    $cart_items = $_SESSION['guest_cart'] ?? [];
} else {
    // ===== LOGGED IN: Get cart from DATABASE =====
    $customer_id = $_SESSION['customer_id'];

    $stmt = $pdo->prepare("
        SELECT cart_id FROM CART WHERE customer_id = ?
    ");
    $stmt->execute([$customer_id]);
    $cart = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cart) {
        $cart_id = $cart['cart_id'];
        $stmt    = $pdo->prepare("
            SELECT co.cart_order_id,
                   co.menu_id,
                   m.menu_name,
                   m.price,
                   co.quantity
            FROM CART_ORDER co
            JOIN MENU m ON co.menu_id = m.menu_id
            WHERE co.cart_id = ?
        ");
        $stmt->execute([$cart_id]);
        $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// ===== CALCULATE TOTALS =====
$subtotal   = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

$sstRate    = 0.06;
$sstAmount  = $subtotal * $sstRate;
$grandTotal = $subtotal + $sstAmount;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Your Cart - MIA CAFE</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f4f6f9;
    min-height: 100vh;
    padding-bottom: 40px;
  }

  /* ===== TOP NAVBAR ===== */
  .top-navbar {
    background: #fff;
    padding: 12px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #f0f0f0;
    position: sticky;
    top: 0;
    z-index: 200;
    box-shadow: 0 2px 12px rgba(0,0,0,0.07);
  }

  .navbar-title {
    font-size: 1.2rem;
    font-weight: 800;
    color: #222;
  }

  .navbar-title span { color: #080909; }

  .back-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    background: none;
    border: 2px solid #0d47a1;
    color: #0d47a1;
    border-radius: 50px;
    padding: 6px 16px;
    font-size: 0.85rem;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.3s ease;
  }

  .back-btn:hover {
    background: #1558b0;
    color: #fff;
  }

  /* ===== MAIN CONTAINER ===== */
  .main-container {
    max-width: 650px;
    margin: 25px auto;
    padding: 0 15px;
  }

  /* ===== PAGE TITLE ===== */
  .page-title {
    font-size: 1.4rem;
    font-weight: 800;
    color: #222;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .page-title span {
    background: #1558b0;
    color: #fff;
    font-size: 0.8rem;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 20px;
  }

  /* ===== EMPTY CART ===== */
  .empty-cart {
    background: #fff;
    border-radius: 16px;
    padding: 50px 30px;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.06);
  }

  .empty-cart .empty-icon {
    font-size: 4rem;
    display: block;
    margin-bottom: 15px;
  }

  .empty-cart h3 {
    font-size: 1.2rem;
    font-weight: 700;
    color: #333;
    margin-bottom: 8px;
  }

  .empty-cart p {
    font-size: 0.9rem;
    color: #888;
    margin-bottom: 20px;
  }

  .go-menu-btn {
    display: inline-block;
    background: #1558b0;
    color: #fff;
    text-decoration: none;
    padding: 12px 28px;
    border-radius: 50px;
    font-weight: 700;
    font-size: 0.95rem;
    transition: background 0.3s;
  }

  .go-menu-btn:hover { background: #1558b0; }

  /* ===== CART CARD ===== */
  .cart-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.06);
    overflow: hidden;
    margin-bottom: 15px;
  }

  .cart-card-header {
    padding: 15px 20px;
    border-bottom: 1px solid #f0f0f0;
    font-size: 0.9rem;
    font-weight: 700;
    color: #555;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  /* ===== CART ITEMS ===== */
  .cart-item {
    display: flex;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #f5f5f5;
    gap: 12px;
    transition: background 0.2s;
  }

  .cart-item:last-child { border-bottom: none; }
  .cart-item:hover { background: #fafafa; }

  .item-number {
    width: 28px;
    height: 28px;
    background: #f0f0f0;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.78rem;
    font-weight: 700;
    color: #666;
    flex-shrink: 0;
  }

  .item-info { flex: 1; }

  .item-name {
    font-size: 0.95rem;
    font-weight: 700;
    color: #222;
    margin-bottom: 3px;
  }

  .item-price-unit {
    font-size: 0.78rem;
    color: #aaa;
  }

  .qty-controls {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
  }

  .qty-btn {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 2px solid #0d47a1;
    background: #fff;
    color: #0d47a1;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    text-decoration: none;
    line-height: 1;
  }

  .qty-btn:hover {
    background: #1558b0;
    color: #fff;
  }

  .qty-num {
    font-size: 0.95rem;
    font-weight: 700;
    color: #222;
    min-width: 20px;
    text-align: center;
  }

  .item-subtotal {
    font-size: 0.95rem;
    font-weight: 700;
    color: #1558b0;
    min-width: 70px;
    text-align: right;
    flex-shrink: 0;
  }

  .remove-btn {
    background: none;
    border: none;
    color: #ccc;
    font-size: 1.1rem;
    cursor: pointer;
    padding: 4px;
    border-radius: 50%;
    transition: all 0.2s;
    flex-shrink: 0;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .remove-btn:hover {
    color: #1558b0;
    background: #fff0f0;
  }

  /* ===== ORDER SUMMARY ===== */
  .summary-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.06);
    overflow: hidden;
    margin-bottom: 15px;
  }

  .summary-header {
    padding: 15px 20px;
    border-bottom: 1px solid #f0f0f0;
    font-size: 0.9rem;
    font-weight: 700;
    color: #555;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .summary-body { padding: 15px 20px; }

  .summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    font-size: 0.9rem;
    color: #555;
    border-bottom: 1px solid #f5f5f5;
  }

  .summary-row:last-child { border-bottom: none; }
  .summary-row .label { font-weight: 500; }
  .summary-row .value { font-weight: 600; color: #333; }

  .summary-row.sst-row .label {
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .sst-badge {
    background: #fff3cd;
    color: #856404;
    font-size: 0.68rem;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 10px;
    border: 1px solid #ffc107;
  }

  .summary-row.total-row {
    padding-top: 12px;
    margin-top: 5px;
    border-top: 2px solid #f0f0f0;
    border-bottom: none;
  }

  .summary-row.total-row .label {
    font-size: 1rem;
    font-weight: 800;
    color: #222;
  }

  .summary-row.total-row .value {
    font-size: 1.2rem;
    font-weight: 800;
    color: #1558b0;
  }

  .sst-info {
    background: #fff8e1;
    border: 1px solid #ffe082;
    border-radius: 10px;
    padding: 10px 14px;
    margin-top: 12px;
    display: flex;
    align-items: flex-start;
    gap: 8px;
    font-size: 0.78rem;
    color: #795548;
    line-height: 1.4;
  }

  /* ===== COUPON SECTION ===== */
  .coupon-section {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.06);
    padding: 15px 20px;
    margin-bottom: 15px;
  }

  .coupon-section-title {
    font-size: 0.9rem;
    font-weight: 700;
    color: #555;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .coupon-input-row {
    display: flex;
    gap: 10px;
  }

  .coupon-input {
    flex: 1;
    padding: 10px 14px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 600;
    letter-spacing: 1px;
    text-transform: uppercase;
    transition: border-color 0.3s;
    color: #333;
  }

  .coupon-input:focus {
    border-color: #1558b0;
    outline: none;
  }

  .coupon-apply-btn {
    background: #1558b0;
    color: #fff;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.88rem;
    cursor: pointer;
    transition: background 0.3s;
    white-space: nowrap;
  }

  .coupon-apply-btn:hover { background: #b21427; }

  .coupon-msg {
    margin-top: 8px;
    font-size: 0.82rem;
    font-weight: 600;
    padding: 6px 10px;
    border-radius: 6px;
    display: none;
  }

  .coupon-msg.success {
    background: #d4edda;
    color: #155724;
    display: block;
  }

  .coupon-msg.error {
    background: #f8d7da;
    color: #721c24;
    display: block;
  }

  /* ===== PAYMENT METHOD SECTION ===== */
  .payment-section {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.06);
    overflow: hidden;
    margin-bottom: 15px;
  }

  .payment-header {
    padding: 15px 20px;
    border-bottom: 1px solid #f0f0f0;
    font-size: 0.9rem;
    font-weight: 700;
    color: #555;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .payment-body { padding: 15px 20px; }

  /* ===== PAYMENT METHOD CARDS ===== */
  .payment-methods {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 15px;
  }

    .payment-method-card {
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    padding: 14px 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    background: #fafafa;
    position: relative;
  }

  .payment-method-card:hover {
    border-color: #e31937;
    background: #fff5f5;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(227,25,55,0.1);
  }

  .payment-method-card.selected {
    border-color: #e31937;
    background: #fff0f0;
    box-shadow: 0 4px 15px rgba(227,25,55,0.15);
  }

  .payment-method-card input[type="radio"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
  }

  .payment-check {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 2px solid #ddd;
    background: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    transition: all 0.3s;
  }

  .payment-method-card.selected .payment-check {
    background: #e31937;
    border-color: #e31937;
    color: #fff;
  }

  .payment-icon {
    font-size: 2rem;
    line-height: 1;
  }

  .payment-name {
    font-size: 0.82rem;
    font-weight: 700;
    color: #333;
    text-align: center;
  }

  .payment-desc {
    font-size: 0.7rem;
    color: #aaa;
    text-align: center;
    line-height: 1.3;
  }

  /* ===== PAYMENT DETAIL PANEL ===== */
  .payment-detail {
    display: none;
    border-radius: 12px;
    padding: 15px;
    margin-top: 5px;
    animation: fadeIn 0.3s ease;
  }

  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(-5px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  .payment-detail.show { display: block; }

  /* ===== ONLINE BANKING DETAIL ===== */
  .banking-detail {
    background: #f0f4ff;
    border: 1px solid #c5d5ff;
  }

  .bank-list {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-top: 10px;
  }

  .bank-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    cursor: pointer;
    background: #fff;
    transition: all 0.2s;
    font-size: 0.82rem;
    font-weight: 600;
    color: #333;
  }

  .bank-item:hover {
    border-color: #3a5bd9;
    background: #f0f4ff;
  }

  .bank-item.selected {
    border-color: #3a5bd9;
    background: #e8eeff;
  }

  .bank-item input[type="radio"] {
    accent-color: #3a5bd9;
    width: 15px;
    height: 15px;
    flex-shrink: 0;
  }

  .bank-logo {
    font-size: 1.2rem;
    flex-shrink: 0;
  }

  /* ===== E-WALLET DETAIL ===== */
  .ewallet-detail {
    background: #f0fff4;
    border: 1px solid #b2dfdb;
  }

  .ewallet-list {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-top: 10px;
  }

  .ewallet-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    cursor: pointer;
    background: #fff;
    transition: all 0.2s;
    font-size: 0.82rem;
    font-weight: 600;
    color: #333;
  }

  .ewallet-item:hover {
    border-color: #28a745;
    background: #f0fff4;
  }

  .ewallet-item.selected {
    border-color: #28a745;
    background: #e8fff0;
  }

  .ewallet-item input[type="radio"] {
    accent-color: #28a745;
    width: 15px;
    height: 15px;
    flex-shrink: 0;
  }

  /* ===== QR PAYMENT DETAIL ===== */
  .qr-detail {
    background: #fff8f0;
    border: 1px solid #ffcc80;
    text-align: center;
  }

  .qr-code-box {
    background: #fff;
    border: 3px solid #333;
    border-radius: 12px;
    padding: 15px;
    display: inline-block;
    margin: 12px auto;
    position: relative;
  }

  .qr-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 2px;
    width: 140px;
    height: 140px;
  }

  .qr-cell {
    border-radius: 1px;
  }

  .qr-cell.dark { background: #333; }
  .qr-cell.light { background: #fff; }

  .qr-label {
    font-size: 0.78rem;
    color: #888;
    margin-top: 8px;
  }

  .qr-amount {
    font-size: 1.1rem;
    font-weight: 800;
    color: #e31937;
    margin: 8px 0;
  }

  .qr-note {
    font-size: 0.75rem;
    color: #aaa;
    font-style: italic;
  }

  /* ===== CASH DETAIL ===== */
  .cash-detail {
    background: #f9f0ff;
    border: 1px solid #ce93d8;
  }

  .cash-info-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 10px;
  }

  .cash-info-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px 12px;
    background: #fff;
    border-radius: 8px;
    border: 1px solid #f0e0ff;
  }

  .cash-info-icon {
    font-size: 1.2rem;
    flex-shrink: 0;
  }

  .cash-info-text {
    font-size: 0.82rem;
    color: #555;
    line-height: 1.4;
  }

  .cash-info-text b { color: #333; }

  /* ===== DUMMY BADGE ===== */
  .dummy-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffc107;
    border-radius: 20px;
    padding: 4px 12px;
    font-size: 0.72rem;
    font-weight: 700;
    margin-bottom: 10px;
  }

  /* ===== PAYMENT DETAIL TITLE ===== */
  .detail-title {
    font-size: 0.88rem;
    font-weight: 700;
    color: #333;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  /* ===== PLACE ORDER BUTTON ===== */
  .place-order-btn {
    width: 100%;
    padding: 16px;
    background: #e31937;
    color: #fff;
    border: none;
    border-radius: 14px;
    font-size: 1.05rem;
    font-weight: 800;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 5px 20px rgba(227,25,55,0.4);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
  }

  .place-order-btn:hover {
    background: #b21427;
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(227,25,55,0.5);
  }

  .place-order-btn:disabled {
    background: #ccc;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
  }

  /* ===== GUEST NOTE ===== */
  .guest-note {
    background: #f0f4ff;
    border: 1px solid #c5d5ff;
    border-radius: 10px;
    padding: 12px 15px;
    margin-bottom: 15px;
    font-size: 0.82rem;
    color: #3a5bd9;
    display: flex;
    align-items: flex-start;
    gap: 8px;
    line-height: 1.5;
  }

  .guest-note a {
    color: #e31937;
    font-weight: 700;
    text-decoration: none;
  }

  .guest-note a:hover { text-decoration: underline; }

  /* ===== RESPONSIVE ===== */
  @media (max-width: 480px) {
    .main-container { padding: 0 10px; }
    .cart-item { padding: 12px 14px; gap: 8px; }
    .item-name { font-size: 0.88rem; }
    .item-subtotal { min-width: 60px; font-size: 0.88rem; }
    .payment-methods { grid-template-columns: 1fr 1fr; }
    .bank-list,
    .ewallet-list { grid-template-columns: 1fr; }
  }
</style>
</head>
<body>

<!-- ===== TOP NAVBAR ===== -->
<div class="top-navbar">
  <div class="navbar-title">
     <span>MIA</span> COFFEE
  </div>
  <a href="menu.php" class="back-btn">← Back to Menu</a>
</div>

<!-- ===== MAIN CONTAINER ===== -->
<div class="main-container">

  <!-- Page Title -->
  <div class="page-title">
    🛒 My Cart
    <?php if (!empty($cart_items)): ?>
      <span><?= count($cart_items) ?> items</span>
    <?php endif; ?>
  </div>

  <?php if (empty($cart_items)): ?>
  <!-- ===== EMPTY CART ===== -->
  <div class="empty-cart">
    <span class="empty-icon">🛒</span>
    <h3>Your cart is empty</h3>
    <p>Looks like you haven't added anything yet.</p>
    <a href="menu.php" class="go-menu-btn">🍽️ Browse Menu</a>
  </div>

  <?php else: ?>

  <!-- ===== GUEST NOTE ===== -->
  <?php if ($isGuest): ?>
  <div class="guest-note">
    <span>ℹ️</span>
    <span>
      You are ordering as a <b>Guest</b>.
      <a href="login.php">Login</a> or
      <a href="register.php">Register</a>
      to save your order history and get exclusive coupons!
    </span>
  </div>
  <?php endif; ?>

  <!-- ===== CART ITEMS ===== -->
  <div class="cart-card">
    <div class="cart-card-header">🍽️ Order Items</div>
    <?php
    $subtotal = 0;
    foreach ($cart_items as $index => $item):
      $itemSubtotal = $item['price'] * $item['quantity'];
      $subtotal    += $itemSubtotal;
    ?>
    <div class="cart-item">
      <div class="item-number"><?= $index + 1 ?></div>
      <div class="item-info">
        <div class="item-name">
          <?= htmlspecialchars($item['menu_name']) ?>
        </div>
        <div class="item-price-unit">
          RM <?= number_format($item['price'], 2) ?> each
        </div>
      </div>
      <div class="qty-controls">
        <?php if ($isGuest): ?>
          <a href="update_cart.php?action=decrease&menu_id=<?= $item['menu_id'] ?>"
             class="qty-btn">−</a>
        <?php else: ?>
          <a href="update_cart.php?action=decrease&cart_order_id=<?= $item['cart_order_id'] ?>"
             class="qty-btn">−</a>
        <?php endif; ?>
        <span class="qty-num"><?= $item['quantity'] ?></span>
        <?php if ($isGuest): ?>
          <a href="update_cart.php?action=increase&menu_id=<?= $item['menu_id'] ?>"
             class="qty-btn">+</a>
        <?php else: ?>
          <a href="update_cart.php?action=increase&cart_order_id=<?= $item['cart_order_id'] ?>"
             class="qty-btn">+</a>
        <?php endif; ?>
      </div>
      <div class="item-subtotal">
        RM <?= number_format($itemSubtotal, 2) ?>
      </div>
      <?php if ($isGuest): ?>
        <a href="update_cart.php?action=remove&menu_id=<?= $item['menu_id'] ?>"
           class="remove-btn"
           onclick="return confirm('Remove this item?')">🗑️</a>
      <?php else: ?>
        <a href="update_cart.php?action=remove&cart_order_id=<?= $item['cart_order_id'] ?>"
           class="remove-btn"
           onclick="return confirm('Remove this item?')">🗑️</a>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>

    <!-- ===== COUPON SECTION (Logged in only) ===== -->
  <?php if (!$isGuest): ?>
  <div class="coupon-section">
    <div class="coupon-section-title">
      🎫 Have a Coupon Code?
    </div>
    <div class="coupon-input-row">
      <input type="text"
             class="coupon-input"
             id="couponInput"
             placeholder="Enter coupon code..."
             maxlength="20">
      <button class="coupon-apply-btn"
              onclick="applyCoupon()">
        Apply
      </button>
    </div>
    <div class="coupon-msg" id="couponMsg"></div>
  </div>
  <?php endif; ?>

  <!-- ===== ORDER SUMMARY ===== -->
  <?php
  $sstRate    = 0.06;
  $sstAmount  = $subtotal * $sstRate;
  $grandTotal = $subtotal + $sstAmount;
  ?>
  <div class="summary-card">
    <div class="summary-header">📋 Order Summary</div>
    <div class="summary-body">

      <!-- Subtotal -->
      <div class="summary-row">
        <span class="label">Subtotal</span>
        <span class="value">
          RM <?= number_format($subtotal, 2) ?>
        </span>
      </div>

      <!-- SST -->
      <div class="summary-row sst-row">
        <span class="label">
          Service Tax
          <span class="sst-badge">SST 6%</span>
        </span>
        <span class="value">
          RM <?= number_format($sstAmount, 2) ?>
        </span>
      </div>

      <!-- Discount (hidden by default) -->
      <div class="summary-row"
           id="discountRow"
           style="display:none;">
        <span class="label" style="color:#28a745;">
          🎫 Coupon Discount
        </span>
        <span class="value"
              style="color:#28a745;"
              id="discountValue">
          - RM 0.00
        </span>
      </div>

      <!-- Grand Total -->
      <div class="summary-row total-row">
        <span class="label">Grand Total</span>
        <span class="value" id="grandTotalDisplay">
          RM <?= number_format($grandTotal, 2) ?>
        </span>
      </div>

      <!-- SST Info -->
      <div class="sst-info">
        <span>ℹ️</span>
        <span>
          Prices are subject to 6% Sales & Service Tax (SST)
          as required by Malaysian law.
        </span>
      </div>

    </div>
  </div>

  <!-- ===== PAYMENT METHOD SECTION ===== -->
  <div class="payment-section">
    <div class="payment-header">
      💳 Select Payment Method
    </div>
    <div class="payment-body">

      <!-- Dummy Badge -->
      <div style="text-align:center; margin-bottom:15px;">
        <span class="dummy-badge">
          🧪 Demo Mode — No Real Transaction
        </span>
      </div>

      <!-- ===== PAYMENT METHOD CARDS ===== -->
      <div class="payment-methods">

        <!-- Online Banking -->
        <div class="payment-method-card"
             id="card-banking"
             onclick="selectPayment('banking')">
          <input type="radio"
                 name="payment_method"
                 value="banking"
                 id="pay-banking">
          <div class="payment-check" id="check-banking">✓</div>
          <div class="payment-icon">🏦</div>
          <div class="payment-name">Online Banking</div>
          <div class="payment-desc">
            Maybank, CIMB, RHB & more
          </div>
        </div>

        <!-- E-Wallet -->
        <div class="payment-method-card"
             id="card-ewallet"
             onclick="selectPayment('ewallet')">
          <input type="radio"
                 name="payment_method"
                 value="ewallet"
                 id="pay-ewallet">
          <div class="payment-check" id="check-ewallet">✓</div>
          <div class="payment-icon">📱</div>
          <div class="payment-name">E-Wallet</div>
          <div class="payment-desc">
            Touch 'n Go, GrabPay & more
          </div>
        </div>

        <!-- QR Payment -->
        <div class="payment-method-card"
             id="card-qr"
             onclick="selectPayment('qr')">
          <input type="radio"
                 name="payment_method"
                 value="qr"
                 id="pay-qr">
          <div class="payment-check" id="check-qr">✓</div>
          <div class="payment-icon">📷</div>
          <div class="payment-name">QR Payment</div>
          <div class="payment-desc">
            Scan & Pay instantly
          </div>
        </div>

        <!-- Cash -->
        <div class="payment-method-card"
             id="card-cash"
             onclick="selectPayment('cash')">
          <input type="radio"
                 name="payment_method"
                 value="cash"
                 id="pay-cash">
          <div class="payment-check" id="check-cash">✓</div>
          <div class="payment-icon">💵</div>
          <div class="payment-name">Cash</div>
          <div class="payment-desc">
            Pay at counter
          </div>
        </div>

      </div>

      <!-- ===== ONLINE BANKING DETAIL ===== -->
      <div class="payment-detail banking-detail"
           id="detail-banking">
        <div class="detail-title">
          🏦 Select Your Bank
        </div>
        <p style="font-size:0.78rem; color:#888; margin-bottom:5px;">
          You will be redirected to your bank's secure page.
          <b>(Demo only - no real redirect)</b>
        </p>
        <div class="bank-list">
          <label class="bank-item" id="bank-maybank">
            <input type="radio"
                   name="bank_choice"
                   value="Maybank"
                   onchange="selectBank(this)">
            <span class="bank-logo">🟡</span>
            Maybank2u
          </label>
          <label class="bank-item" id="bank-cimb">
            <input type="radio"
                   name="bank_choice"
                   value="CIMB"
                   onchange="selectBank(this)">
            <span class="bank-logo">🔴</span>
            CIMB Clicks
          </label>
          <label class="bank-item" id="bank-rhb">
            <input type="radio"
                   name="bank_choice"
                   value="RHB"
                   onchange="selectBank(this)">
            <span class="bank-logo">🟢</span>
            RHB Now
          </label>
          <label class="bank-item" id="bank-public">
            <input type="radio"
                   name="bank_choice"
                   value="Public Bank"
                   onchange="selectBank(this)">
            <span class="bank-logo">🔵</span>
            Public Bank
          </label>
          <label class="bank-item" id="bank-hongleong">
            <input type="radio"
                   name="bank_choice"
                   value="Hong Leong"
                   onchange="selectBank(this)">
            <span class="bank-logo">🟠</span>
            Hong Leong
          </label>
          <label class="bank-item" id="bank-ambank">
            <input type="radio"
                   name="bank_choice"
                   value="AmBank"
                   onchange="selectBank(this)">
            <span class="bank-logo">🟣</span>
            AmBank
          </label>
        </div>
      </div>

      <!-- ===== E-WALLET DETAIL ===== -->
      <div class="payment-detail ewallet-detail"
           id="detail-ewallet">
        <div class="detail-title">
          📱 Select Your E-Wallet
        </div>
        <p style="font-size:0.78rem; color:#888; margin-bottom:5px;">
          Open your e-wallet app to complete payment.
          <b>(Demo only)</b>
        </p>
        <div class="ewallet-list">
          <label class="ewallet-item">
            <input type="radio"
                   name="ewallet_choice"
                   value="Touch n Go"
                   onchange="selectEwallet(this)">
            <span style="font-size:1.2rem;">💚</span>
            Touch 'n Go
          </label>
          <label class="ewallet-item">
            <input type="radio"
                   name="ewallet_choice"
                   value="GrabPay"
                   onchange="selectEwallet(this)">
            <span style="font-size:1.2rem;">🟢</span>
            GrabPay
          </label>
          <label class="ewallet-item">
            <input type="radio"
                   name="ewallet_choice"
                   value="Boost"
                   onchange="selectEwallet(this)">
            <span style="font-size:1.2rem;">🔴</span>
            Boost
          </label>
          <label class="ewallet-item">
            <input type="radio"
                   name="ewallet_choice"
                   value="ShopeePay"
                   onchange="selectEwallet(this)">
            <span style="font-size:1.2rem;">🟠</span>
            ShopeePay
          </label>
          <label class="ewallet-item">
            <input type="radio"
                   name="ewallet_choice"
                   value="MAE"
                   onchange="selectEwallet(this)">
            <span style="font-size:1.2rem;">🟡</span>
            MAE
          </label>
          <label class="ewallet-item">
            <input type="radio"
                   name="ewallet_choice"
                   value="BigPay"
                   onchange="selectEwallet(this)">
            <span style="font-size:1.2rem;">🔵</span>
            BigPay
          </label>
        </div>
      </div>

      <!-- ===== QR PAYMENT DETAIL ===== -->
      <div class="payment-detail qr-detail"
           id="detail-qr">
        <div class="detail-title"
             style="justify-content:center;">
          📷 Scan QR Code to Pay
        </div>
        <p style="font-size:0.78rem; color:#888;">
          Use any banking app or e-wallet to scan.
          <b>(Demo QR - not real)</b>
        </p>

        <!-- Dummy QR Code -->
        <div class="qr-code-box">
          <div style="display:grid;
                      grid-template-columns: repeat(10,1fr);
                      gap:2px; width:130px; height:130px;">
            <?php
            // Generate dummy QR pattern
            $pattern = [
              1,1,1,1,1,1,1,0,1,0,
              1,0,0,0,0,0,1,0,0,1,
              1,0,1,1,1,0,1,0,1,0,
              1,0,1,1,1,0,1,0,0,1,
              1,0,1,1,1,0,1,0,1,1,
              1,0,0,0,0,0,1,0,0,0,
              1,1,1,1,1,1,1,0,1,0,
              0,0,0,0,0,0,0,0,1,1,
              1,0,1,1,0,1,1,1,0,1,
              0,1,0,0,1,0,1,0,1,0,
            ];
            foreach ($pattern as $cell):
            ?>
              <div style="background:<?= $cell ? '#333' : '#fff' ?>;
                          border-radius:1px;">
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="qr-amount" id="qrAmount">
          RM <?= number_format($grandTotal, 2) ?>
        </div>
        <div class="qr-label">
          Cafe Digital Payment
        </div>
        <div class="qr-note">
          ⚠️ This is a demo QR code.
          No real payment will be processed.
        </div>
      </div>

      <!-- ===== CASH DETAIL ===== -->
      <div class="payment-detail cash-detail"
           id="detail-cash">
        <div class="detail-title">
          💵 Pay with Cash
        </div>
        <div class="cash-info-list">
          <div class="cash-info-item">
            <span class="cash-info-icon">📋</span>
            <div class="cash-info-text">
              <b>Step 1:</b> Place your order by
              clicking the button below.
            </div>
          </div>
          <div class="cash-info-item">
            <span class="cash-info-icon">🧾</span>
            <div class="cash-info-text">
              <b>Step 2:</b> Show your
              <b>Order ID</b> to the cashier
              at the counter.
            </div>
          </div>
          <div class="cash-info-item">
            <span class="cash-info-icon">💵</span>
            <div class="cash-info-text">
              <b>Step 3:</b> Pay the total amount of
              <b id="cashTotal">
                RM <?= number_format($grandTotal, 2) ?>
              </b>
              at the counter.
            </div>
          </div>
          <div class="cash-info-item">
            <span class="cash-info-icon">✅</span>
            <div class="cash-info-text">
              <b>Step 4:</b> Collect your receipt
              and wait for your order to be ready!
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- ===== PLACE ORDER FORM ===== -->
  <form method="post"
        action="place_order.php"
        id="orderForm"
        onsubmit="return validateOrder()">

    <input type="hidden" name="subtotal"
           value="<?= $subtotal ?>">
    <input type="hidden" name="sst_amount"
           value="<?= $sstAmount ?>">
    <input type="hidden" name="grand_total"
           id="grandTotalInput"
           value="<?= $grandTotal ?>">
    <input type="hidden" name="coupon_code"
           id="couponCodeInput" value="">
    <input type="hidden" name="discount"
           id="discountInput" value="0">
    <input type="hidden" name="is_guest"
           value="<?= $isGuest ? '1' : '0' ?>">
    <input type="hidden" name="payment_method"
           id="paymentMethodInput" value="">
    <input type="hidden" name="payment_detail"
           id="paymentDetailInput" value="">
               <button type="submit"
            class="place-order-btn"
            id="placeOrderBtn"
            disabled>
      🛒 Place Order —
      RM <span id="btnTotal">
        <?= number_format($grandTotal, 2) ?>
      </span>
    </button>

  </form>

  <!-- Payment Required Note -->
  <p style="text-align:center; font-size:0.78rem;
            color:#aaa; margin-top:10px;">
    ⚠️ Please select a payment method to continue
  </p>

  <?php endif; ?>

</div>

<script>
  // ===== BASE VALUES =====
  const baseSubtotal   = <?= $subtotal ?>;
  const sstRate        = <?= $sstRate ?>;
  let   discountAmount = 0;
  let   appliedCode    = '';
  let   selectedPayment = '';
  let   selectedDetail  = '';

  // ===== SELECT PAYMENT METHOD =====
  function selectPayment(method) {
    // ===== REMOVE ALL SELECTED =====
    const allCards = document.querySelectorAll('.payment-method-card');
    allCards.forEach(card => card.classList.remove('selected'));

    // ===== HIDE ALL DETAILS =====
    const allDetails = document.querySelectorAll('.payment-detail');
    allDetails.forEach(detail => detail.classList.remove('show'));

    // ===== SELECT CLICKED CARD =====
    const card = document.getElementById('card-' + method);
    if (card) card.classList.add('selected');

    // ===== CHECK RADIO =====
    const radio = document.getElementById('pay-' + method);
    if (radio) radio.checked = true;

    // ===== SHOW DETAIL PANEL =====
    const detail = document.getElementById('detail-' + method);
    if (detail) detail.classList.add('show');

    // ===== SET SELECTED PAYMENT =====
    selectedPayment = method;
    selectedDetail  = '';

    // ===== UPDATE HIDDEN INPUT =====
    document.getElementById('paymentMethodInput').value = method;
    document.getElementById('paymentDetailInput').value = '';

    // ===== ENABLE PLACE ORDER BUTTON =====
    // For cash & QR - enable immediately
    // For banking & ewallet - wait for sub-selection
    if (method === 'cash' || method === 'qr') {
      enableOrderBtn();
    } else {
      disableOrderBtn();
    }

    // ===== HIDE PAYMENT NOTE =====
    const note = document.querySelector(
      'p[style*="Please select a payment"]'
    );
    if (note) note.style.display = 'none';
  }

  // ===== SELECT BANK =====
  function selectBank(radio) {
    // Remove selected from all bank items
    document.querySelectorAll('.bank-item').forEach(item => {
      item.classList.remove('selected');
    });

    // Add selected to parent label
    if (radio.parentElement) {
      radio.parentElement.classList.add('selected');
    }

    selectedDetail = radio.value;
    document.getElementById('paymentDetailInput').value = radio.value;

    // Enable order button
    enableOrderBtn();
  }

  // ===== SELECT E-WALLET =====
  function selectEwallet(radio) {
    // Remove selected from all ewallet items
    document.querySelectorAll('.ewallet-item').forEach(item => {
      item.classList.remove('selected');
    });

    // Add selected to parent label
    if (radio.parentElement) {
      radio.parentElement.classList.add('selected');
    }

    selectedDetail = radio.value;
    document.getElementById('paymentDetailInput').value = radio.value;

    // Enable order button
    enableOrderBtn();
  }

  // ===== ENABLE ORDER BUTTON =====
  function enableOrderBtn() {
    const btn = document.getElementById('placeOrderBtn');
    if (btn) {
      btn.disabled = false;
      btn.style.background = '#e31937';
      btn.style.cursor     = 'pointer';
    }
  }

  // ===== DISABLE ORDER BUTTON =====
  function disableOrderBtn() {
    const btn = document.getElementById('placeOrderBtn');
    if (btn) {
      btn.disabled = true;
      btn.style.background = '#ccc';
      btn.style.cursor     = 'not-allowed';
    }
  }

  // ===== VALIDATE ORDER BEFORE SUBMIT =====
  function validateOrder() {
    if (!selectedPayment) {
      alert('❌ Please select a payment method!');
      return false;
    }

    if (selectedPayment === 'banking' && !selectedDetail) {
      alert('❌ Please select your bank!');
      return false;
    }

    if (selectedPayment === 'ewallet' && !selectedDetail) {
      alert('❌ Please select your e-wallet!');
      return false;
    }

    // ===== SHOW PROCESSING POPUP =====
    showProcessingPopup();
    return true;
  }

  // ===== SHOW PROCESSING POPUP =====
  function showProcessingPopup() {
    const method = selectedPayment;
    let   icon   = '💳';
    let   msg    = 'Processing payment...';

    if (method === 'banking') {
      icon = '🏦';
      msg  = 'Connecting to ' + selectedDetail + '...';
    } else if (method === 'ewallet') {
      icon = '📱';
      msg  = 'Opening ' + selectedDetail + '...';
    } else if (method === 'qr') {
      icon = '📷';
      msg  = 'Verifying QR payment...';
    } else if (method === 'cash') {
      icon = '💵';
      msg  = 'Confirming cash order...';
    }

    // Create overlay
    const overlay = document.createElement('div');
    overlay.style.cssText = `
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background: rgba(0,0,0,0.7);
      z-index: 99999;
      display: flex;
      align-items: center;
      justify-content: center;
    `;

    overlay.innerHTML = `
      <div style="background:#fff; border-radius:20px;
                  padding:35px 30px; text-align:center;
                  max-width:300px; width:90%;
                  box-shadow:0 20px 60px rgba(0,0,0,0.3);
                  animation: popIn 0.3s ease;">
        <div style="font-size:3rem; margin-bottom:15px;
                    animation: spin 1s linear infinite;">
          ${icon}
        </div>
        <h3 style="font-size:1.1rem; font-weight:800;
                   color:#222; margin-bottom:8px;">
          ${msg}
        </h3>
        <p style="font-size:0.82rem; color:#888;
                  margin-bottom:15px; line-height:1.5;">
          Please wait while we process your order...
        </p>
        <div style="background:#f0f0f0; border-radius:10px;
                    height:6px; overflow:hidden;">
          <div style="background:#e31937; height:100%;
                      border-radius:10px;
                      animation: progress 2s ease forwards;"
               id="progressBar">
          </div>
        </div>
        <p style="font-size:0.72rem; color:#bbb;
                  margin-top:12px;">
          🧪 Demo Mode — No real payment processed
        </p>
      </div>
    `;

    document.body.appendChild(overlay);
  }

  // ===== APPLY COUPON =====
  function applyCoupon() {
    const code  = document.getElementById('couponInput')
                          .value.trim().toUpperCase();
    const msgEl = document.getElementById('couponMsg');

    if (!code) {
      showCouponMsg('❌ Please enter a coupon code.', 'error');
      return;
    }

    // Show loading
    showCouponMsg('⏳ Validating coupon...', 'success');

    fetch('validate_coupon.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: 'code=' + encodeURIComponent(code)
            + '&subtotal=' + encodeURIComponent(baseSubtotal),
    })
    .then(res => res.json())
    .then(data => {
      if (data.valid) {
        discountAmount = parseFloat(data.discount);
        appliedCode    = code;

        // Update totals
        updateTotals();

        // Show discount row
        document.getElementById('discountRow').style.display = 'flex';
        document.getElementById('discountValue').textContent =
          '- RM ' + discountAmount.toFixed(2);

        // Set hidden inputs
        document.getElementById('couponCodeInput').value = code;
        document.getElementById('discountInput').value   = discountAmount;

        showCouponMsg(
          '✅ Coupon applied! You save RM '
          + discountAmount.toFixed(2),
          'success'
        );
      } else {
        discountAmount = 0;
        appliedCode    = '';
        document.getElementById('discountRow').style.display = 'none';
        document.getElementById('couponCodeInput').value     = '';
        document.getElementById('discountInput').value       = '0';
        updateTotals();
        showCouponMsg(
          '❌ ' + (data.message || 'Invalid coupon code.'),
          'error'
        );
      }
    })
    .catch(() => {
      showCouponMsg(
        '❌ Error validating coupon. Please try again.',
        'error'
      );
    });
  }

  // ===== UPDATE TOTALS =====
  function updateTotals() {
    const afterDiscount = Math.max(0, baseSubtotal - discountAmount);
    const sst           = afterDiscount * sstRate;
    const grand         = afterDiscount + sst;

    // Update display
    document.getElementById('grandTotalDisplay').textContent =
      'RM ' + grand.toFixed(2);

    // Update hidden input
    document.getElementById('grandTotalInput').value = grand.toFixed(2);

    // Update button total
    document.getElementById('btnTotal').textContent = grand.toFixed(2);

    // Update QR amount
    const qrAmount = document.getElementById('qrAmount');
    if (qrAmount) {
      qrAmount.textContent = 'RM ' + grand.toFixed(2);
    }

    // Update cash total
    const cashTotal = document.getElementById('cashTotal');
    if (cashTotal) {
      cashTotal.textContent = 'RM ' + grand.toFixed(2);
    }
  }

  // ===== SHOW COUPON MESSAGE =====
  function showCouponMsg(msg, type) {
    const el      = document.getElementById('couponMsg');
    el.textContent = msg;
    el.className   = 'coupon-msg ' + type;
  }

  // ===== APPLY COUPON ON ENTER KEY =====
  const couponInput = document.getElementById('couponInput');
  if (couponInput) {
    couponInput.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        applyCoupon();
      }
    });
  }

  // ===== CSS ANIMATIONS =====
  const style = document.createElement('style');
  style.textContent = `
    @keyframes spin {
      0%   { transform: rotate(0deg);   }
      100% { transform: rotate(360deg); }
    }
    @keyframes progress {
      0%   { width: 0%;   }
      100% { width: 100%; }
    }
    @keyframes popIn {
      from { transform: scale(0.8); opacity: 0; }
      to   { transform: scale(1);   opacity: 1; }
    }
  `;
  document.head.appendChild(style);
</script>

</body>
</html>