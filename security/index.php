<?php
/**
 * ====================================================================
 * Cafe Digital - API Endpoint Example
 * ====================================================================
 * Demonstrates usage of Rate Limiting, Input Validation, and API Keys
 * ====================================================================
 */

require __DIR__ . '/security.php';

// ===== API RESPONSE HELPER =====
class ApiResponse {
    public static function success($data = [], $message = 'Success', $code = 200) {
        http_response_code($code);
        return json_encode([
            'success' => true,
            'code' => $code,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function error($error, $message = 'Error', $code = 400) {
        http_response_code($code);
        return json_encode([
            'success' => false,
            'code' => $code,
            'message' => $message,
            'error' => $error,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }
}

// ===== SET HEADERS =====
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// ===== GET REQUEST METHOD =====
$method = $_SERVER['REQUEST_METHOD'];
 $path = $_GET['endpoint'] ?? '';

// ===== HANDLE CORS PREFLIGHT =====
if ($method === 'OPTIONS') {
    die(json_encode(['success' => true]));
}

// ===== 1️⃣ CHECK RATE LIMITING =====
if ($rate_limiter->isLimited()) {
    SecurityLogger::log('RATE_LIMIT_EXCEEDED', [
        'endpoint' => $path,
        'method' => $method,
    ], 'warning');

    die(ApiResponse::error(
        'Too many requests',
        'Rate limit exceeded. Maximum ' . RATE_LIMIT_REQUESTS . ' requests per ' . (RATE_LIMIT_WINDOW / 60) . ' minutes.',
        429
    ));
}

// ===== 2️⃣ VALIDATE API KEY =====
$api_key = null;
$user_data = null;

if (isset(getallheaders()['Authorization'])) {
    $auth_header = getallheaders()['Authorization'];

    if (preg_match('/Bearer\s+(\S+)/', $auth_header, $matches)) {
        $api_key = $matches[1];
        $key_validation = $api_key_manager->validateKey($api_key);

        if (!$key_validation['valid']) {
            SecurityLogger::log('INVALID_API_KEY', [
                'endpoint' => $path,
                'method' => $method,
            ], 'warning');

            die(ApiResponse::error(
                $key_validation['error'],
                'Authentication failed',
                401
            ));
        }

        $user_data = $key_validation;
    }
}

// ===== EXAMPLE ENDPOINT: GET MENU ITEMS =====
if ($path === 'menu' && $method === 'GET') {
    // ===== 3️⃣ VALIDATE INPUT PARAMETERS =====
    $category = $_GET['category'] ?? '';
    $limit = $_GET['limit'] ?? 10;
    $offset = $_GET['offset'] ?? 0;

    // Validate inputs
    $validation = $input_validator->validateMultiple([
        'limit' => $limit,
        'offset' => $offset,
    ]);

    // Additional custom validation
    if (isset(INPUT_RULES['quantity'])) {
        $limit_check = $input_validator->validate($limit, 'quantity');
        if (!$limit_check['valid']) {
            die(ApiResponse::error(
                'Invalid limit parameter',
                'Limit must be a positive integer',
                400
            ));
        }
    }

    $limit = min((int)$limit, 100);  // Max 100 items per request
    $offset = max(0, (int)$offset);

    try {
        $query = "SELECT menu_id, menu_name, description, price, category FROM MENU WHERE availability = 1";

        if (!empty($category)) {
            $category = htmlspecialchars($category);
            $query .= " AND category = :category";
        }

        $query .= " LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        if (!empty($category)) {
            $stmt->bindValue(':category', $category);
        }

        $stmt->execute();
        $items = $stmt->fetchAll();

        SecurityLogger::logApiCall('/api/menu', 'GET', $user_data['user_id'] ?? null, 200);

        die(ApiResponse::success(
            ['items' => $items, 'limit' => $limit, 'offset' => $offset],
            'Menu retrieved successfully'
        ));

    } catch (Exception $e) {
        error_log("GET /api/menu error: " . $e->getMessage());
        SecurityLogger::log('API_ERROR', [
            'endpoint' => '/api/menu',
            'error' => $e->getMessage(),
        ], 'error');

        die(ApiResponse::error(
            'Database error',
            'Failed to retrieve menu',
            500
        ));
    }
}

// ===== EXAMPLE ENDPOINT: CREATE ORDER (REQUIRES AUTH) =====
if ($path === 'orders' && $method === 'POST') {
    // ===== REQUIRE API KEY =====
    if (!$user_data) {
        die(ApiResponse::error(
            'Missing API key',
            'Authorization required',
            401
        ));
    }

    // ===== GET JSON INPUT =====
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    // ===== 3️⃣ VALIDATE INPUT =====
    if (empty($input['items']) || !is_array($input['items'])) {
        die(ApiResponse::error(
            'Invalid items array',
            'Order must contain at least one item',
            400
        ));
    }

    $total = 0;
    $order_items = [];

    try {
        foreach ($input['items'] as $item) {
            // Validate quantity
            $qty_validation = $input_validator->validate($item['quantity'] ?? 0, 'quantity');
            if (!$qty_validation['valid']) {
                die(ApiResponse::error(
                    $qty_validation['error'],
                    'Invalid item quantity',
                    400
                ));
            }

            $menu_id = (int)($item['menu_id'] ?? 0);
            $quantity = (int)$item['quantity'];

            // Get menu item
            $stmt = $pdo->prepare("SELECT menu_id, price FROM MENU WHERE menu_id = ? LIMIT 1");
            $stmt->execute([$menu_id]);
            $menu = $stmt->fetch();

            if (!$menu) {
                die(ApiResponse::error(
                    'Menu item not found',
                    'Invalid menu_id: ' . $menu_id,
                    404
                ));
            }

            $total += $menu['price'] * $quantity;
            $order_items[] = ['menu_id' => $menu_id, 'quantity' => $quantity, 'price' => $menu['price']];
        }

        // Create order
        $stmt = $pdo->prepare("
            INSERT INTO `ORDER` (customer_id, total, status, order_date)
            VALUES (?, ?, 'Pending', NOW())
        ");
        $stmt->execute([$user_data['user_id'], $total]);
        $order_id = $pdo->lastInsertId();

        // Add order items
        $item_stmt = $pdo->prepare("
            INSERT INTO ORDER_ITEM (order_id, menu_id, quantity, price)
            VALUES (?, ?, ?, ?)
        ");

        foreach ($order_items as $item) {
            $item_stmt->execute([$order_id, $item['menu_id'], $item['quantity'], $item['price']]);
        }

        SecurityLogger::log('ORDER_CREATED', [
            'order_id' => $order_id,
            'user_id' => $user_data['user_id'],
            'total' => $total,
        ], 'info');

        SecurityLogger::logApiCall('/api/orders', 'POST', $user_data['user_id'], 201);

        die(ApiResponse::success(
            ['order_id' => $order_id, 'total' => $total],
            'Order created successfully',
            201
        ));

    } catch (Exception $e) {
        error_log("POST /api/orders error: " . $e->getMessage());
        SecurityLogger::log('API_ERROR', [
            'endpoint' => '/api/orders',
            'error' => $e->getMessage(),
        ], 'error');

        die(ApiResponse::error(
            'Failed to create order',
            'An error occurred while creating your order',
            500
        ));
    }
}

// ===== EXAMPLE ENDPOINT: VALIDATE COUPON CODE =====
if ($path === 'coupons/validate' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    // ===== 3️⃣ VALIDATE COUPON CODE =====
    $coupon_validation = $input_validator->validate($input['code'] ?? '', 'coupon_code');

    if (!$coupon_validation['valid']) {
        die(ApiResponse::error(
            $coupon_validation['error'],
            'Invalid coupon code',
            400
        ));
    }

    $coupon_code = $coupon_validation['value'];

    try {
        // Check if COUPON table exists
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'COUPON'");
        if ($tableCheck->rowCount() === 0) {
            die(ApiResponse::error(
                'Coupon system not available',
                'Coupons are not enabled',
                503
            ));
        }

        $stmt = $pdo->prepare("
            SELECT code, discount_type, discount_amount, min_order, is_active, expiry_date
            FROM COUPON
            WHERE code = ? AND is_active = 1 AND expiry_date >= CURDATE()
            LIMIT 1
        ");

        $stmt->execute([$coupon_code]);
        $coupon = $stmt->fetch();

        if (!$coupon) {
            die(ApiResponse::error(
                'Coupon not found or expired',
                'The coupon code is invalid or has expired',
                404
            ));
        }

        SecurityLogger::logApiCall('/api/coupons/validate', 'POST', $user_data['user_id'] ?? null, 200);

        die(ApiResponse::success(
            $coupon,
            'Coupon is valid'
        ));

    } catch (Exception $e) {
        error_log("POST /api/coupons/validate error: " . $e->getMessage());
        die(ApiResponse::error(
            'Validation failed',
            'An error occurred',
            500
        ));
    }
}

// ===== 404 - ENDPOINT NOT FOUND =====
die(ApiResponse::error(
    'Endpoint not found',
    'The requested API endpoint does not exist',
    404
));
?>
