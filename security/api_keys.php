<!-- ====================================================================
     Cafe Digital - API Keys Management Panel
     ====================================================================
     User interface for managing API keys, permissions, and settings
     ==================================================================== -->

<?php

require __DIR__ . '/../security.php';

// Check user is logged in
if (!isset($_SESSION['customer_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['customer_id'];
$message = '';
$error = '';

// ===== HANDLE API KEY GENERATION =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Validate CSRF token
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'CSRF token validation failed';
    } else {
        if ($_POST['action'] === 'generate') {
            $name = trim($_POST['key_name'] ?? '');

            // Validate input
            $name_validation = $input_validator->validate($name, 'name');
            if (!$name_validation['valid']) {
                $error = $name_validation['error'];
            } else {
                $permissions = isset($_POST['permissions']) && is_array($_POST['permissions'])
                    ? array_intersect($_POST['permissions'], ['read:menu', 'read:orders', 'write:orders', 'read:coupons'])
                    : [];

                $result = $api_key_manager->generateKey($user_id, $name, $permissions);

                if ($result['success']) {
                    $message = 'API Key generated successfully!';
                    $generated_key = $result['api_key'];

                    SecurityLogger::log('API_KEY_GENERATED', [
                        'user_id' => $user_id,
                        'key_name' => $name,
                        'permissions' => $permissions,
                    ], 'info');
                } else {
                    $error = $result['error'] ?? 'Failed to generate API key';
                }
            }
        } elseif ($_POST['action'] === 'revoke') {
            $key_id = (int)$_POST['key_id'];

            $result = $api_key_manager->revokeKey($key_id, $user_id);

            if ($result['success']) {
                $message = 'API key revoked successfully';

                SecurityLogger::log('API_KEY_REVOKED', [
                    'user_id' => $user_id,
                    'key_id' => $key_id,
                ], 'info');
            } else {
                $error = $result['error'] ?? 'Failed to revoke API key';
            }
        }
    }
}

// Get user's API keys
$api_keys = $api_key_manager->listKeys($user_id);
$user_stmt = $pdo->prepare("SELECT name, email FROM CUSTOMER WHERE customer_id = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="ie=edge">
<meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:;">
<title>API Keys Management - Cafe Digital</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: #f5f7fa;
    min-height: 100vh;
    padding: 20px;
  }

  .container {
    max-width: 900px;
    margin: 0 auto;
  }

  .header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 20px;
  }

  .header h1 {
    font-size: 2rem;
    color: #222;
    font-weight: 700;
  }

  .header-info {
    display: flex;
    align-items: center;
    gap: 15px;
  }

  .user-avatar {
    width: 44px;
    height: 44px;
    background: #e31937;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-weight: 700;
    font-size: 1.1rem;
  }

  .user-info h3 {
    font-size: 0.95rem;
    color: #222;
    margin: 0;
    font-weight: 600;
  }

  .user-info p {
    font-size: 0.82rem;
    color: #888;
    margin: 3px 0 0;
  }

  /* ===== ALERT BOX ===== */
  .alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 0.9rem;
    font-weight: 500;
  }

  .alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
  }

  .alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
  }

  /* ===== CARD ===== */
  .card {
    background: #fff;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    margin-bottom: 20px;
  }

  .card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
  }

  .card-header h2 {
    font-size: 1.3rem;
    color: #222;
    font-weight: 700;
  }

  .btn-primary {
    background: #e31937;
    color: #fff;
    border: none;
    padding: 10px 24px;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.3s;
  }

  .btn-primary:hover { background: #b21427; }

  .btn-secondary {
    background: #f5f5f5;
    color: #333;
    border: 1px solid #ddd;
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
  }

  .btn-secondary:hover {
    background: #e9e9e9;
    border-color: #ccc;
  }

  .btn-danger {
    background: #dc3545;
    color: #fff;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.3s;
  }

  .btn-danger:hover { background: #bb2d3b; }

  /* ===== FORM ===== */
  .form-group {
    margin-bottom: 15px;
  }

  .form-group label {
    display: block;
    font-size: 0.9rem;
    font-weight: 600;
    color: #222;
    margin-bottom: 6px;
  }

  .form-group input[type="text"],
  .form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1.5px solid #ddd;
    border-radius: 6px;
    font-size: 0.9rem;
    font-family: inherit;
    transition: border-color 0.3s;
  }

  .form-group input[type="text"]:focus,
  .form-group textarea:focus {
    outline: none;
    border-color: #e31937;
    box-shadow: 0 0 0 3px rgba(227,25,55,0.1);
  }

  .permissions-list {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    margin-top: 8px;
  }

  .permission-item {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .permission-item input[type="checkbox"] {
    cursor: pointer;
    width: 18px;
    height: 18px;
  }

  .permission-item label {
    margin: 0;
    cursor: pointer;
    font-size: 0.85rem;
    font-weight: 500;
  }

  /* ===== TABLE ===== */
  .table-responsive {
    overflow-x: auto;
  }

  table {
    width: 100%;
    border-collapse: collapse;
  }

  th {
    text-align: left;
    padding: 12px;
    font-size: 0.85rem;
    font-weight: 700;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #f0f0f0;
    background: #fafafa;
  }

  td {
    padding: 12px;
    font-size: 0.9rem;
    color: #444;
    border-bottom: 1px solid #f5f5f5;
  }

  tr:hover td { background: #fafafa; }
  tr:last-child td { border-bottom: none; }

  .badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
  }

  .badge-active { background: #d4edda; color: #155724; }
  .badge-inactive { background: #f8d7da; color: #721c24; }

  .code-display {
    background: #f5f5f5;
    border: 1px solid #ddd;
    padding: 12px;
    border-radius: 6px;
    font-family: 'Courier New', monospace;
    font-size: 0.85rem;
    word-break: break-all;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin: 10px 0;
  }

  .copy-btn {
    background: #e31937;
    color: #fff;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: background 0.3s;
  }

  .copy-btn:hover { background: #b21427; }
  .copy-btn.copied { background: #28a745; }

  .empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #999;
  }

  .empty-icon { font-size: 3rem; margin-bottom: 15px; }

  /* ===== MODAL ===== */
  .modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 20px;
  }

  .modal.show { display: flex; }

  .modal-content {
    background: #fff;
    border-radius: 12px;
    padding: 24px;
    max-width: 500px;
    width: 100%;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    animation: slideUp 0.3s ease;
  }

  @keyframes slideUp {
    from { transform: translateY(30px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
  }

  .modal-header {
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
  }

  .modal-header h3 {
    font-size: 1.2rem;
    color: #222;
    font-weight: 700;
    margin: 0;
  }

  .modal-body {
    margin-bottom: 20px;
  }

  .modal-footer {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
  }

  .modal-close {
    position: absolute;
    top: 15px;
    right: 15px;
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #ccc;
    transition: color 0.3s;
  }

  .modal-close:hover { color: #333; }

  .highlight-warning {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    padding: 12px;
    border-radius: 4px;
    font-size: 0.9rem;
    color: #856404;
    margin-bottom: 15px;
  }

  @media (max-width: 768px) {
    .header { flex-direction: column; align-items: flex-start; }
    .permissions-list { grid-template-columns: 1fr; }
    .code-display { flex-direction: column; }
  }
</style>
</head>
<body>

<div class="container">

  <!-- ===== HEADER ===== -->
  <div class="header">
    <h1>🔑 API Keys</h1>
    <div class="header-info">
      <div class="user-avatar"><?= htmlspecialchars(strtoupper(substr($user['name'], 0, 1)), ENT_QUOTES, 'UTF-8') ?></div>
      <div class="user-info">
        <h3><?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></h3>
        <p><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></p>
      </div>
    </div>
  </div>

  <!-- ===== ALERTS ===== -->
  <?php if ($message): ?>
  <div class="alert alert-success">
    ✅ <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
  </div>
  <?php endif; ?>

  <?php if ($error): ?>
  <div class="alert alert-error">
    ❌ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
  </div>
  <?php endif; ?>

  <!-- ===== GENERATE NEW KEY ===== -->
  <div class="card">
    <div class="card-header">
      <h2>📝 Create New API Key</h2>
    </div>

    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="action" value="generate">

      <div class="form-group">
        <label for="key_name">Key Name</label>
        <input type="text"
               id="key_name"
               name="key_name"
               placeholder="e.g., Mobile App, Integration, etc."
               required
               maxlength="100">
        <small style="color:#888; margin-top:4px; display:block;">
          Give this key a descriptive name
        </small>
      </div>

      <div class="form-group">
        <label>Permissions</label>
        <div class="permissions-list">
          <div class="permission-item">
            <input type="checkbox" id="perm_read_menu" name="permissions[]" value="read:menu" checked>
            <label for="perm_read_menu">Read Menu</label>
          </div>
          <div class="permission-item">
            <input type="checkbox" id="perm_read_orders" name="permissions[]" value="read:orders" checked>
            <label for="perm_read_orders">Read Orders</label>
          </div>
          <div class="permission-item">
            <input type="checkbox" id="perm_write_orders" name="permissions[]" value="write:orders">
            <label for="perm_write_orders">Write Orders</label>
          </div>
          <div class="permission-item">
            <input type="checkbox" id="perm_read_coupons" name="permissions[]" value="read:coupons">
            <label for="perm_read_coupons">Read Coupons</label>
          </div>
        </div>
      </div>

      <button type="submit" class="btn-primary">🔐 Generate API Key</button>
    </form>

    <!-- ===== GENERATED KEY DISPLAY ===== -->
    <?php if (isset($generated_key)): ?>
    <div style="margin-top:20px; padding-top:20px; border-top:2px solid #f0f0f0;">
      <h3 style="margin-bottom:10px; color:#222; font-size:1rem;">✅ Your API Key Has Been Generated</h3>
      
      <div class="highlight-warning">
        ⚠️ <strong>Save this key in a secure location!</strong> You won't be able to see it again.
      </div>

      <div class="code-display">
        <code id="keyCode"><?= htmlspecialchars($generated_key, ENT_QUOTES, 'UTF-8') ?></code>
        <button type="button" class="copy-btn" onclick="copyToClipboard('keyCode')">📋 Copy</button>
      </div>

      <p style="font-size:0.85rem; color:#888; margin-top:10px;">
        Use this key with the <code>Authorization: Bearer</code> header in your API requests.
      </p>
    </div>
    <?php endif; ?>
  </div>

  <!-- ===== API KEYS LIST ===== -->
  <div class="card">
    <div class="card-header">
      <h2>📚 Your API Keys</h2>
    </div>

    <?php if (!empty($api_keys)): ?>
    <div class="table-responsive">
      <table>
        <thead>
          <tr>
            <th>Name</th>
            <th>Created</th>
            <th>Last Used</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($api_keys as $key): ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars($key['name'], ENT_QUOTES, 'UTF-8') ?></strong>
            </td>
            <td>
              <?= htmlspecialchars(date('d M Y, H:i', strtotime($key['created_at'])), ENT_QUOTES, 'UTF-8') ?>
            </td>
            <td>
              <?php if ($key['last_used_at']): ?>
                <?= htmlspecialchars(date('d M Y, H:i', strtotime($key['last_used_at'])), ENT_QUOTES, 'UTF-8') ?>
              <?php else: ?>
                <span style="color:#bbb;">Never</span>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge <?= $key['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                <?= $key['is_active'] ? '✅ Active' : '❌ Revoked' ?>
              </span>
            </td>
            <td>
              <?php if ($key['is_active']): ?>
              <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to revoke this key?');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="revoke">
                <input type="hidden" name="key_id" value="<?= $key['id'] ?>">
                <button type="submit" class="btn-danger">🗑️ Revoke</button>
              </form>
              <?php else: ?>
              <span style="color:#999;">Revoked</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php else: ?>
    <div class="empty-state">
      <div class="empty-icon">🔑</div>
      <p style="color:#666; font-weight:600; margin-bottom:10px;">No API keys yet</p>
      <p style="color:#999; font-size:0.9rem;">Create your first API key above to get started with our API</p>
    </div>
    <?php endif; ?>
  </div>

  <!-- ===== API DOCUMENTATION ===== -->
  <div class="card">
    <div class="card-header">
      <h2>📖 API Documentation</h2>
    </div>

    <div style="line-height:1.8; color:#555; font-size:0.9rem;">
      <h3 style="margin-top:0; margin-bottom:10px; color:#222; font-size:1rem; font-weight:700;">Getting Started</h3>
      
      <p style="margin-bottom:15px;">
        Use your API key to authenticate requests. Include it in the <code>Authorization</code> header:
      </p>

      <div style="background:#f5f5f5; padding:12px; border-radius:6px; margin-bottom:15px; overflow-x:auto;">
        <code style="font-size:0.85rem;">Authorization: Bearer YOUR_API_KEY</code>
      </div>

      <h3 style="margin-top:20px; margin-bottom:10px; color:#222; font-size:1rem; font-weight:700;">Available Endpoints</h3>

      <ul style="margin-left:20px; margin-bottom:15px;">
        <li><code>GET /api/menu</code> - Get all menu items</li>
        <li><code>POST /api/orders</code> - Create a new order</li>
        <li><code>POST /api/coupons/validate</code> - Validate a coupon code</li>
      </ul>

      <h3 style="margin-top:20px; margin-bottom:10px; color:#222; font-size:1rem; font-weight:700;">Rate Limiting</h3>

      <p style="margin-bottom:15px;">
        API requests are rate limited to <strong><?= RATE_LIMIT_REQUESTS ?> requests per <?= (RATE_LIMIT_WINDOW / 60) ?> minutes</strong>.
        Check the <code>X-RateLimit-Remaining</code> header to see your remaining quota.
      </p>

      <p style="margin-bottom:0; color:#888; font-size:0.85rem;">
        For complete API documentation, visit <a href="/docs/api" style="color:#e31937; text-decoration:none;">our API documentation</a>
      </p>
    </div>
  </div>

</div>

<script nonce="api-keys">
  'use strict';

  function copyToClipboard(elementId) {
    const element = document.getElementById(elementId);
    const text = element.textContent;

    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(text).then(() => {
        showCopyFeedback(event.target);
      }).catch(() => {
        fallbackCopy(text, event.target);
      });
    } else {
      fallbackCopy(text, event.target);
    }
  }

  function fallbackCopy(text, button) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.cssText = 'position:fixed;opacity:0;';
    document.body.appendChild(textarea);
    textarea.select();
    try {
      document.execCommand('copy');
      showCopyFeedback(button);
    } catch (err) {
      alert('Failed to copy. Please copy manually.');
    }
    document.body.removeChild(textarea);
  }

  function showCopyFeedback(button) {
    const originalText = button.textContent;
    button.textContent = '✅ Copied!';
    button.classList.add('copied');
    setTimeout(() => {
      button.textContent = originalText;
      button.classList.remove('copied');
    }, 2000);
  }
</script>

</body>
</html>
