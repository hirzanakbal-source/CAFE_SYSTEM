<?php
require 'db.php';
session_start();

// ===== CHECK SESSION =====
if (!isset($_SESSION['customer_id']) && !isset($_SESSION['guest'])) {
    header('Location: welcome.php');
    exit;
}

$isGuest = isset($_SESSION['guest']) && $_SESSION['guest'] === true;
$menu_id = intval($_POST['menu_id'] ?? 0);

if ($menu_id <= 0) {
    header('Location: menu.php');
    exit;
}

if ($isGuest) {
    // ===== GUEST: ADD TO SESSION CART =====
    try {
        $stmt = $pdo->prepare("
            SELECT menu_id, menu_name, price
            FROM MENU
            WHERE menu_id = ?
            AND availability = 1
        ");
        $stmt->execute([$menu_id]);
        $menuItem = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($menuItem) {
            if (!isset($_SESSION['guest_cart'])) {
                $_SESSION['guest_cart'] = [];
            }

            // ===== CHECK IF ITEM ALREADY IN CART =====
            $found = false;
            foreach ($_SESSION['guest_cart'] as &$cartItem) {
                if ($cartItem['menu_id'] == $menu_id) {
                    $cartItem['quantity']++;
                    $found = true;
                    break;
                }
            }
            unset($cartItem);

            // ===== ADD NEW ITEM =====
            if (!$found) {
                $_SESSION['guest_cart'][] = [
                    'menu_id'   => $menuItem['menu_id'],
                    'menu_name' => $menuItem['menu_name'],
                    'price'     => $menuItem['price'],
                    'quantity'  => 1,
                ];
            }
        }
    } catch (PDOException $e) {
        // Handle error silently
    }

} else {
    // ===== LOGGED IN: ADD TO DATABASE CART =====
    $customer_id = $_SESSION['customer_id'];

    try {
        // ===== GET OR CREATE CART =====
        $stmt = $pdo->prepare("
            SELECT cart_id FROM CART
            WHERE customer_id = ?
        ");
        $stmt->execute([$customer_id]);
        $cart = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cart) {
            $stmt = $pdo->prepare("
                INSERT INTO CART (customer_id)
                VALUES (?)
            ");
            $stmt->execute([$customer_id]);
            $cart_id = $pdo->lastInsertId();
        } else {
            $cart_id = $cart['cart_id'];
        }

        // ===== CHECK IF ITEM ALREADY IN CART =====
        $stmt = $pdo->prepare("
            SELECT cart_order_id, quantity
            FROM CART_ORDER
            WHERE cart_id = ?
            AND menu_id = ?
        ");
        $stmt->execute([$cart_id, $menu_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // ===== UPDATE QUANTITY =====
            $stmt = $pdo->prepare("
                UPDATE CART_ORDER
                SET quantity = quantity + 1
                WHERE cart_order_id = ?
            ");
            $stmt->execute([$existing['cart_order_id']]);
        } else {
            // ===== INSERT NEW ITEM =====
            $stmt = $pdo->prepare("
                INSERT INTO CART_ORDER
                (cart_id, menu_id, quantity)
                VALUES (?, ?, 1)
            ");
            $stmt->execute([$cart_id, $menu_id]);
        }

    } catch (PDOException $e) {
        // Handle error silently
    }
}

// ===== REDIRECT BACK TO MENU =====
header('Location: menu.php');
exit;
?>