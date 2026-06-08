<?php
require __DIR__ . '/../db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$admin_name  = $_SESSION['admin_name'] ?? 'Admin';
$message     = '';
$messageType = 'success';

try {
    $pdo->exec("ALTER TABLE MENU ADD COLUMN IF NOT EXISTS file_path VARCHAR(255) NULL DEFAULT NULL");
    $pdo->exec("ALTER TABLE MENU ADD COLUMN IF NOT EXISTS best_seller TINYINT(1) NOT NULL DEFAULT 0");
    $pdo->exec("ALTER TABLE MENU ADD COLUMN IF NOT EXISTS description TEXT NULL");
    $pdo->exec("ALTER TABLE MENU ADD COLUMN IF NOT EXISTS availability TINYINT(1) NOT NULL DEFAULT 1");
    $pdo->exec("ALTER TABLE MENU ADD COLUMN IF NOT EXISTS subcategory VARCHAR(50) NULL DEFAULT NULL");
} catch (PDOException $e) {}

if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    try {
        $stmt = $pdo->prepare("SELECT file_path FROM MENU WHERE menu_id = ?");
        $stmt->execute([$delete_id]);
        $menuItem = $stmt->fetch(PDO::FETCH_ASSOC);
        $pdo->prepare("DELETE FROM MENU WHERE menu_id = ?")->execute([$delete_id]);
        if ($menuItem && $menuItem['file_path']) {
            $fullPath = __DIR__ . '/../' . $menuItem['file_path'];
            if (file_exists($fullPath)) unlink($fullPath);
        }
        $message = '✅ Menu item deleted successfully!';
        $messageType = 'success';
    } catch (PDOException $e) {
        $message = '❌ Failed to delete: ' . $e->getMessage();
        $messageType = 'error';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_id'])) {
    $update_id    = intval($_POST['update_id']);
    $menu_name    = trim($_POST['menu_name']   ?? '');
    $description  = trim($_POST['description'] ?? '');
    $price        = $_POST['price']             ?? '';
    $category     = $_POST['category']          ?? '';
    $availability = isset($_POST['availability']) ? 1 : 0;
    $best_seller  = isset($_POST['best_seller'])  ? 1 : 0;
    $file_path    = $_POST['existing_file']       ?? null;

    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath   = $_FILES['file']['tmp_name'];
        $fileName      = $_FILES['file']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExt    = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($fileExtension, $allowedExt)) {
            $message = '❌ Only image files allowed: JPG, JPEG, PNG, GIF, WEBP';
            $messageType = 'error';
        } elseif ($_FILES['file']['size'] > 5 * 1024 * 1024) {
            $message = '❌ File size must be less than 5MB.';
            $messageType = 'error';
        } else {
            $uploadDir = __DIR__ . '/../uploads/menu_files/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $newFileName = 'menu_' . time() . '_' . uniqid() . '.' . $fileExtension;
            if (move_uploaded_file($fileTmpPath, $uploadDir . $newFileName)) {
                if ($file_path) { $old = __DIR__ . '/../' . $file_path; if (file_exists($old)) unlink($old); }
                $file_path = 'uploads/menu_files/' . $newFileName;
            } else {
                $message = '❌ Error uploading file.';
                $messageType = 'error';
            }
        }
    }

    if (!$message) {
        try {
            $subcategory = ($category === 'beverages') ? trim($_POST['subcategory'] ?? '') : null;
            $stmt = $pdo->prepare("UPDATE MENU SET menu_name=?,description=?,price=?,category=?,subcategory=?,availability=?,best_seller=?,file_path=? WHERE menu_id=?");
            $stmt->execute([$menu_name,$description,$price,$category,$subcategory,$availability,$best_seller,$file_path,$update_id]);
            $message = '✅ Menu item updated successfully!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = '❌ Failed to update: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

try {
    $menu_items = $pdo->query("SELECT * FROM MENU ORDER BY category, menu_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $menu_items = []; }

$totalItems     = count($menu_items);
$availableItems = count(array_filter($menu_items, fn($i) => $i['availability'] == 1));
$bestSellers    = count(array_filter($menu_items, fn($i) => !empty($i['best_seller']) && $i['best_seller'] == 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>View / Edit Menu — Cafe Digital</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
:root {
  --sidebar-bg: #0f1629; --sidebar-accent: #3b82f6; --sidebar-text: #94a3b8;
  --sidebar-hover: rgba(255,255,255,.06); --body-bg: #f1f5f9; --card-bg: #fff;
  --border: #e2e8f0; --text-primary: #0f172a; --text-secondary: #64748b; --text-muted: #94a3b8;
  --blue: #3b82f6; --green: #10b981; --orange: #f59e0b; --red: #ef4444;
  --sidebar-width: 240px; --radius: 12px;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'DM Sans', sans-serif; background: var(--body-bg); color: var(--text-primary); min-height: 100vh; display: flex; }

/* ─── SIDEBAR ─── */
.sidebar {
  position: fixed; top: 0; left: 0;
  width: var(--sidebar-width); height: 100vh;
  background: var(--sidebar-bg); display: flex; flex-direction: column;
  z-index: 200; overflow-y: auto;
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.sidebar-brand { padding: 28px 22px 22px; border-bottom: 1px solid rgba(255,255,255,.07); }
.brand-icon { width: 38px; height: 38px; background: var(--sidebar-accent); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; margin-bottom: 10px; }
.sidebar-brand h2 { font-size: .95rem; font-weight: 700; color: #fff; }
.sidebar-brand p  { font-size: .72rem; color: var(--sidebar-text); margin-top: 2px; }
.sidebar-nav { padding: 18px 0; flex: 1; }
.nav-section-label { font-size: .65rem; font-weight: 600; color: #475569; text-transform: uppercase; letter-spacing: .1em; padding: 14px 22px 6px; }
.nav-link { display: flex; align-items: center; gap: 11px; padding: 10px 22px; color: var(--sidebar-text); text-decoration: none; font-size: .85rem; font-weight: 500; transition: .2s; position: relative; }
.nav-link:hover { background: var(--sidebar-hover); color: #fff; }
.nav-link.active { background: rgba(59,130,246,.15); color: var(--sidebar-accent); }
.nav-link.active::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 3px; background: var(--sidebar-accent); border-radius: 0 3px 3px 0; }
.nav-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: .9rem; background: rgba(255,255,255,.04); transition: background .2s; }
.nav-link.active .nav-icon { background: rgba(59,130,246,.2); }
.nav-link:hover  .nav-icon { background: rgba(255,255,255,.08); }
.sidebar-footer { padding: 16px 22px; border-top: 1px solid rgba(255,255,255,.07); }
.logout-btn { display: flex; align-items: center; gap: 10px; color: #f87171; text-decoration: none; font-size: .85rem; font-weight: 500; padding: 9px 12px; border-radius: 8px; transition: background .2s; }
.logout-btn:hover { background: rgba(239,68,68,.1); }

/* ─── OVERLAY ─── */
.sidebar-overlay {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.5); z-index: 199;
  opacity: 0; pointer-events: none;
  transition: opacity .3s ease;
  backdrop-filter: blur(2px); -webkit-backdrop-filter: blur(2px);
}
.sidebar-overlay.visible { opacity: 1; pointer-events: auto; }

/* ─── MAIN ─── */
.main { margin-left: var(--sidebar-width); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }

/* ─── TOPBAR ─── */
.topbar {
  background: #fff; border-bottom: 1px solid var(--border);
  padding: 14px 30px; display: flex; align-items: center;
  justify-content: space-between; gap: 16px; flex-wrap: wrap;
  position: sticky; top: 0; z-index: 210;
}
.topbar-left { display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0; }
.topbar-title h1 { font-size: 1.05rem; font-weight: 700; }
.topbar-title p  { font-size: .75rem; color: var(--text-secondary); margin-top: 1px; }
.topbar-right { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.date-pill { display: flex; align-items: center; gap: 6px; background: #f8fafc; border: 1px solid var(--border); padding: 6px 13px; border-radius: 999px; font-size: .75rem; color: var(--text-secondary); font-weight: 500; }
.admin-pill { display: flex; align-items: center; gap: 9px; background: #f8fafc; border: 1px solid var(--border); padding: 6px 14px 6px 7px; border-radius: 999px; }
.admin-avatar { width: 28px; height: 28px; background: var(--sidebar-accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: .78rem; font-weight: 700; }
.admin-pill span { font-size: .8rem; font-weight: 600; }
.add-btn-link { background: var(--blue); color: #fff; text-decoration: none; padding: 8px 14px; border-radius: 10px; font-weight: 700; font-size: .78rem; }

/* ─── HAMBURGER ─── */
.hamburger {
  display: none; flex-direction: column; justify-content: center; align-items: center;
  gap: 5px; width: 36px; height: 36px; border: 1px solid var(--border);
  border-radius: 9px; background: #f8fafc; cursor: pointer;
  flex-shrink: 0; transition: background .2s, border-color .2s; padding: 0;
}
.hamburger:hover { background: #f1f5f9; border-color: #cbd5e1; }
.hamburger span { display: block; width: 16px; height: 2px; background: var(--text-primary); border-radius: 2px; transition: transform .3s ease, opacity .3s ease, width .3s ease; transform-origin: center; }
.hamburger.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
.hamburger.open span:nth-child(2) { opacity: 0; width: 0; }
.hamburger.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

/* ─── CONTENT ─── */
.content { padding: 26px 30px; flex: 1; }
.stats-row { display: grid; grid-template-columns: repeat(3,1fr); gap: 14px; margin-bottom: 18px; }
.stat-card { background: #fff; border: 1px solid var(--border); border-radius: var(--radius); padding: 16px; display: flex; gap: 12px; }
.stat-icon { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; }
.blue { background: #eff6ff; } .green { background: #f0fdf4; } .orange { background: #fffbeb; }
.stat-info h3 { font-family: 'Space Mono', monospace; font-size: 1.25rem; }
.stat-info p  { font-size: .74rem; color: var(--text-muted); }
.message { padding: 11px 16px; border-radius: var(--radius); margin-bottom: 18px; font-size: .82rem; font-weight: 600; border: 1px solid; }
.message.success { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
.message.error   { background: #fff1f2; border-color: #fecdd3; color: #9f1239; }
.filter-bar { background: #fff; border: 1px solid var(--border); border-radius: var(--radius); padding: 14px; margin-bottom: 16px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
.search-input, .filter-select { padding: 9px 12px; border: 1px solid var(--border); border-radius: 10px; font-size: .82rem; font-family: inherit; }
.search-input { flex: 1; min-width: 220px; }
.filter-count { font-size: .76rem; color: var(--text-muted); font-weight: 600; }
.card { background: #fff; border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
table { width: 100%; border-collapse: collapse; }
th { padding: 11px 14px; font-size: .68rem; color: var(--text-muted); text-transform: uppercase; background: #f8fafc; border-bottom: 1px solid var(--border); text-align: left; }
td { padding: 12px 14px; font-size: .82rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.menu-thumb, .no-img { width: 56px; height: 56px; border-radius: 10px; }
.menu-thumb { object-fit: cover; border: 1px solid #e5e7eb; }
.no-img { display: flex; align-items: center; justify-content: center; background: #f8fafc; border: 1px solid var(--border); }
.cat-badge, .avail-badge, .best-badge { display: inline-block; border-radius: 999px; font-size: .68rem; font-weight: 700; padding: 4px 10px; }
.cat-food      { background: #fff7ed; color: #9a3412; border: 1px solid #fed7aa; }
.cat-beverages { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.cat-dessert   { background: #fdf2f8; color: #9d174d; border: 1px solid #fbcfe8; }
.avail-yes { background: #f0fdf4; color: #15803d; }
.avail-no  { background: #fff1f2; color: #be123c; }
.best-badge { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; font-size: .66rem; padding: 3px 8px; margin-top: 5px; }
.action-btns { display: flex; gap: 6px; flex-wrap: wrap; }
.btn-edit, .btn-delete { padding: 6px 11px; border-radius: 8px; font-size: .72rem; font-weight: 700; text-decoration: none; border: 1px solid; cursor: pointer; }
.btn-edit   { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
.btn-delete { background: #fff1f2; color: #be123c; border-color: #fecdd3; }

/* ─── MODAL ─── */
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15,23,42,.55); z-index: 9999; align-items: center; justify-content: center; padding: 20px; }
.modal-overlay.show { display: flex; }
.modal-box { background: #fff; border: 1px solid var(--border); border-radius: 14px; max-width: 560px; width: 100%; max-height: 90vh; overflow-y: auto; }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 18px; border-bottom: 1px solid var(--border); }
.modal-close-btn { background: none; border: none; font-size: 1.1rem; cursor: pointer; color: var(--text-muted); }
.modal-body { padding: 16px 18px 18px; }
.form-group { margin-bottom: 14px; }
.form-group label { display: block; font-size: .8rem; font-weight: 600; margin-bottom: 6px; }
.form-group input, .form-group textarea, .form-group select { width: 100%; padding: 9px 12px; border: 1px solid var(--border); border-radius: 10px; font-size: .82rem; font-family: inherit; }
.form-group input:focus, .form-group textarea:focus, .form-group select:focus { border-color: #bfdbfe; outline: none; box-shadow: 0 0 0 3px #eff6ff; }
.price-wrap { display: flex; border: 1px solid var(--border); border-radius: 10px; overflow: hidden; }
.price-prefix { padding: 9px 12px; background: #f8fafc; border-right: 1px solid var(--border); font-size: .82rem; font-weight: 700; }
.price-wrap input { border: none !important; box-shadow: none !important; }
.checkbox-group { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 1px solid var(--border); border-radius: 10px; cursor: pointer; }
.checkbox-group input[type="checkbox"] { width: 16px; height: 16px; accent-color: var(--blue); cursor: pointer; }
.checkbox-label { font-size: .82rem; color: var(--text-secondary); font-weight: 500; cursor: pointer; user-select: none; }
.current-img-wrap { display: flex; align-items: center; gap: 10px; padding: 10px; background: #f8fafc; border: 1px solid var(--border); border-radius: 10px; }
.current-img { width: 66px; height: 66px; object-fit: cover; border-radius: 9px; border: 1px solid #e5e7eb; }
.upload-area { border: 2px dashed #cbd5e1; border-radius: 10px; padding: 16px; text-align: center; position: relative; background: #f8fafc; }
.upload-area input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
.new-img-preview { display: none; margin-top: 10px; text-align: center; }
.new-img-preview img { max-width: 100%; max-height: 150px; border-radius: 9px; border: 1px solid #e5e7eb; }
.new-img-name { font-size: .74rem; color: var(--text-secondary); margin-top: 6px; }
.remove-new-img { display: inline-block; margin-top: 6px; color: #dc2626; font-size: .76rem; cursor: pointer; font-weight: 600; background: none; border: none; text-decoration: underline; }
.submit-btn { width: 100%; padding: 11px; background: var(--blue); color: #fff; border: none; border-radius: 10px; font-size: .84rem; font-weight: 700; margin-top: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; }
.submit-btn:hover { background: #2563eb; }
.footer { padding: 14px 30px; border-top: 1px solid var(--border); text-align: center; font-size: .72rem; color: var(--text-muted); }

/* ─── RESPONSIVE ─── */
@media (max-width: 1024px) { .stats-row { grid-template-columns: 1fr 1fr; } }
@media (max-width: 768px) {
  .sidebar { transform: translateX(-100%); box-shadow: none; }
  .sidebar.open { transform: translateX(0); box-shadow: 8px 0 32px rgba(0,0,0,.25); }
  .sidebar-overlay { display: block; }
  .main { margin-left: 0; }
  body.sidebar-open { overflow: hidden; }
  .hamburger { display: flex; }
  .content { padding: 16px; }
  .topbar, .footer { padding: 12px 16px; }
  .filter-bar { flex-direction: column; }
  .search-input { min-width: unset; width: 100%; }
  .date-pill { display: none; }
  .stats-row { grid-template-columns: 1fr 1fr; }
}
</style>
</head>
<body>

<!-- ─── OVERLAY ─── -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ─── SIDEBAR ─── -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand"><div class="brand-icon">☕</div><h2>Cafe Digital</h2><p>Admin Panel</p></div>
  <nav class="sidebar-nav">
    <div class="nav-section-label">Main</div>
    <a href="admin_dashboard.php" class="nav-link"><span class="nav-icon">🏠</span> Dashboard</a>
    <div class="nav-section-label">Catalogue</div>
    <a href="admin_menu_add.php"  class="nav-link"><span class="nav-icon">➕</span> Add Menu</a>
    <a href="admin_menu_list.php" class="nav-link active"><span class="nav-icon">📋</span> View / Edit Menu</a>
    <div class="nav-section-label">Operations</div>
    <a href="admin_orders.php"  class="nav-link"><span class="nav-icon">🛒</span> Manage Orders</a>
    <a href="admin_coupons.php" class="nav-link"><span class="nav-icon">🎫</span> Manage Coupons</a>
    <div class="nav-section-label">Analytics</div>
    <a href="admin_reports.php" class="nav-link"><span class="nav-icon">📊</span> Sales Report</a>
  </nav>
  <div class="sidebar-footer"><a href="admin_logout.php" class="logout-btn"><span>🚪</span> Logout</a></div>
</aside>

<!-- ─── MAIN ─── -->
<div class="main">

  <!-- TOPBAR -->
  <div class="topbar">
    <div class="topbar-left">
      <button type="button" class="hamburger" id="hamburger" aria-label="Toggle navigation" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
      <div class="topbar-title">
        <h1>View / Edit Menu</h1>
        <p>Manage menu items, update details, and control availability.</p>
      </div>
    </div>
    <div class="topbar-right">
      <div class="date-pill">📅 <?= date('D, d M Y') ?></div>
      <div class="admin-pill">
        <div class="admin-avatar"><?= strtoupper(substr($admin_name,0,1)) ?></div>
        <span><?= htmlspecialchars($admin_name) ?></span>
      </div>
      <a href="admin_menu_add.php" class="add-btn-link">➕ Add New Item</a>
    </div>
  </div>

  <div class="content">
    <div class="stats-row">
      <div class="stat-card"><div class="stat-icon blue">🍽️</div><div class="stat-info"><h3><?= $totalItems ?></h3><p>Total Items</p></div></div>
      <div class="stat-card"><div class="stat-icon green">✅</div><div class="stat-info"><h3><?= $availableItems ?></h3><p>Available</p></div></div>
      <div class="stat-card"><div class="stat-icon orange">⭐</div><div class="stat-info"><h3><?= $bestSellers ?></h3><p>Best Sellers</p></div></div>
    </div>

    <?php if ($message): ?><div class="message <?= $messageType ?>"><?= htmlspecialchars($message) ?></div><?php endif; ?>

    <div class="filter-bar">
      <input type="text" class="search-input" id="searchInput" placeholder="🔍 Search menu items..." oninput="filterTable()">
      <select class="filter-select" id="categoryFilter" onchange="filterTable()">
        <option value="">All Categories</option>
        <option value="food">🍱 Food</option>
        <option value="beverages">☕ Beverages</option>
        <option value="dessert">🍰 Dessert</option>
      </select>
      <select class="filter-select" id="availFilter" onchange="filterTable()">
        <option value="">All Status</option>
        <option value="1">✅ Available</option>
        <option value="0">❌ Unavailable</option>
      </select>
      <span class="filter-count" id="filterCount">Showing <?= $totalItems ?> items</span>
    </div>

    <div class="card">
      <table id="menuTable">
        <thead><tr><th>Image</th><th>Name & Description</th><th>Category</th><th>Subcategory</th><th>Price</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody id="menuTableBody">
          <?php if (!empty($menu_items)): ?>
            <?php foreach ($menu_items as $item):
              $isBestSeller = !empty($item['best_seller']) && $item['best_seller'] == 1;
              $catClass = match(strtolower($item['category'] ?? '')) {
                'food' => 'cat-food', 'beverages' => 'cat-beverages', 'dessert' => 'cat-dessert', default => 'cat-food',
              };
              $catLabel = match(strtolower($item['category'] ?? '')) {
                'food' => '🍱 Food', 'beverages' => '☕ Beverages', 'dessert' => '🍰 Dessert', default => ucfirst($item['category'] ?? ''),
              };
            ?>
            <tr data-name="<?= strtolower(htmlspecialchars($item['menu_name'])) ?>" data-category="<?= strtolower($item['category'] ?? '') ?>" data-avail="<?= $item['availability'] ?>">
              <td>
                <?php if (!empty($item['file_path'])): ?>
                  <img src="../<?= htmlspecialchars($item['file_path']) ?>" alt="" class="menu-thumb" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                  <div class="no-img" style="display:none;"><?= match(strtolower($item['category'] ?? '')) {'food'=>'🍱','beverages'=>'☕','dessert'=>'🍰',default=>'🍴'} ?></div>
                <?php else: ?>
                  <div class="no-img"><?= match(strtolower($item['category'] ?? '')) {'food'=>'🍱','beverages'=>'☕','dessert'=>'🍰',default=>'🍴'} ?></div>
                <?php endif; ?>
              </td>
              <td>
                <div style="font-weight:600;"><?= htmlspecialchars($item['menu_name']) ?></div>
                <?php if (!empty($item['description'])): ?><div style="font-size:.74rem;color:var(--text-muted);margin-top:2px;"><?= htmlspecialchars(mb_substr($item['description'],0,50).(mb_strlen($item['description'])>50?'...':'')) ?></div><?php endif; ?>
                <?php if ($isBestSeller): ?><span class="best-badge">⭐ Best Seller</span><?php endif; ?>
              </td>
              <td><span class="cat-badge <?= $catClass ?>"><?= $catLabel ?></span></td>
              <td>
                <?php if (!empty($item['subcategory'])): ?>
                  <span style="font-size:.72rem;font-weight:700;background:#f0f9ff;color:#0369a1;border:1px solid #bae6fd;padding:3px 9px;border-radius:999px;"><?= htmlspecialchars(ucwords($item['subcategory'])) ?></span>
                <?php else: ?>
                  <span style="font-size:.72rem;color:#cbd5e1;">—</span>
                <?php endif; ?>
              </td>
              <td><b style="color:#2563eb;font-family:'Space Mono',monospace;">RM <?= number_format($item['price'],2) ?></b></td>
              <td><span class="avail-badge <?= $item['availability'] ? 'avail-yes' : 'avail-no' ?>"><?= $item['availability'] ? '✅ Available' : '❌ Unavailable' ?></span></td>
              <td>
                <div class="action-btns">
                  <button class="btn-edit" onclick="openEdit(<?= $item['menu_id'] ?>,'<?= addslashes(htmlspecialchars($item['menu_name'])) ?>','<?= addslashes(htmlspecialchars($item['description'] ?? '')) ?>','<?= $item['price'] ?>','<?= addslashes($item['category'] ?? '') ?>','<?= addslashes($item['subcategory'] ?? '') ?>',<?= $item['availability'] ?>,<?= !empty($item['best_seller']) ? 1 : 0 ?>,'<?= addslashes($item['file_path'] ?? '') ?>')">✏️ Edit</button>
                  <a href="?delete=<?= $item['menu_id'] ?>" class="btn-delete" onclick="return confirm('Delete \'<?= addslashes($item['menu_name']) ?>\'?\nThis cannot be undone.')">🗑️ Delete</a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="7" style="text-align:center;padding:40px;"><span style="font-size:3rem;display:block;margin-bottom:12px;">🍽️</span><p style="font-weight:600;color:#999;margin-bottom:8px;">No menu items found.</p><a href="admin_menu_add.php" style="color:#2563eb;font-weight:700;text-decoration:none;">➕ Add your first menu item</a></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="footer">Cafe Digital Admin Panel &nbsp;|&nbsp; © <?= date('Y') ?></div>
</div>

<!-- ─── EDIT MODAL ─── -->
<div class="modal-overlay" id="editModal">
  <div class="modal-box">
    <div class="modal-header">
      <h3>✏️ Edit Menu Item</h3>
      <button type="button" class="modal-close-btn" onclick="closeEdit()">✕</button>
    </div>
    <div class="modal-body">
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="update_id" id="edit_id">
        <input type="hidden" name="existing_file" id="edit_existing_file">

        <div class="form-group"><label>Menu Name *</label><input type="text" name="menu_name" id="edit_name" required></div>
        <div class="form-group"><label>Description</label><textarea name="description" id="edit_description" rows="3" placeholder="Describe the menu item..."></textarea></div>
        <div class="form-group"><label>Price *</label><div class="price-wrap"><span class="price-prefix">RM</span><input type="number" name="price" id="edit_price" step="0.01" min="0" required></div></div>
        <div class="form-group"><label>Category *</label>
          <select name="category" id="edit_category" required>
            <option value="food">🍱 Food</option>
            <option value="beverages">☕ Beverages</option>
            <option value="dessert">🍰 Dessert</option>
          </select>
        </div>
        <div class="form-group" id="editSubcategoryGroup" style="display:none;"><label>Beverage Type</label>
          <select name="subcategory" id="edit_subcategory">
            <option value="">-- Select Type --</option>
            <option value="coffee">☕ Coffee</option>
            <option value="latte">🥛 Latte</option>
            <option value="sparkling tea">🫧 Sparkling Tea</option>
            <option value="ice crush series">🧊 Ice Crush Series</option>
            <option value="al-hadad shake">🥤 Al-Hadad Shake</option>
          </select>
        </div>
        <div class="form-group"><label>Best Seller</label>
          <div class="checkbox-group" onclick="toggleCheck('edit_best_seller')">
            <input type="checkbox" name="best_seller" id="edit_best_seller">
            <span class="checkbox-label">⭐ Mark as Best Seller</span>
          </div>
        </div>
        <div class="form-group"><label>Availability</label>
          <div class="checkbox-group" onclick="toggleCheck('edit_availability')">
            <input type="checkbox" name="availability" id="edit_availability">
            <span class="checkbox-label">✅ Available</span>
          </div>
        </div>
        <div class="form-group"><label>Current Image</label>
          <div class="current-img-wrap">
            <img id="edit_current_img" src="" alt="" class="current-img" style="display:none;">
            <div><p id="currentImgText" style="font-size:.8rem;font-weight:600;">No image uploaded</p><span id="currentImgSub" style="font-size:.72rem;color:var(--text-muted);">Upload a new image below to replace it</span></div>
          </div>
        </div>
        <div class="form-group"><label>Upload New Image (Optional)</label>
          <div class="upload-area" id="editUploadArea">
            <span style="font-size:1.8rem;">📷</span>
            <div style="font-size:.82rem;font-weight:600;margin-top:6px;">Click to upload new image</div>
            <div style="font-size:.72rem;color:var(--text-muted);">JPG, JPEG, PNG, GIF, WEBP • Max 5MB</div>
            <input type="file" name="file" id="editFileInput" accept="image/*" onchange="previewNewImage(this)">
          </div>
          <div class="new-img-preview" id="newImgPreview">
            <img id="newPreviewImg" src="" alt="New Preview">
            <div class="new-img-name" id="newImgName"></div>
            <button type="button" class="remove-new-img" onclick="removeNewImage()">✕ Remove new image</button>
          </div>
        </div>
        <button type="submit" class="submit-btn">💾 Save Changes</button>
      </form>
    </div>
  </div>
</div>

<script>
// ─── SIDEBAR TOGGLE ───────────────────────────────────────────
const sidebar   = document.getElementById('sidebar');
const overlay   = document.getElementById('sidebarOverlay');
const hamburger = document.getElementById('hamburger');

function openSidebar() {
  sidebar.classList.add('open');
  overlay.classList.add('visible');
  hamburger.classList.add('open');
  hamburger.setAttribute('aria-expanded', 'true');
  document.body.classList.add('sidebar-open');
}

function closeSidebar() {
  sidebar.classList.remove('open');
  overlay.classList.remove('visible');
  hamburger.classList.remove('open');
  hamburger.setAttribute('aria-expanded', 'false');
  document.body.classList.remove('sidebar-open');
}

hamburger.addEventListener('click', () => {
  sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
});

overlay.addEventListener('click', closeSidebar);

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    if (document.getElementById('editModal').classList.contains('show')) closeEdit();
    else closeSidebar();
  }
});

sidebar.querySelectorAll('.nav-link').forEach(link => {
  link.addEventListener('click', () => { if (window.innerWidth <= 768) closeSidebar(); });
});

// ─── EDIT MODAL ───────────────────────────────────────────────
function openEdit(id,name,desc,price,category,subcategory,avail,bestSeller,filePath) {
  document.getElementById('edit_id').value = id;
  document.getElementById('edit_name').value = name;
  document.getElementById('edit_description').value = desc;
  document.getElementById('edit_price').value = price;
  document.getElementById('edit_category').value = category;
  document.getElementById('edit_existing_file').value = filePath;
  document.getElementById('edit_availability').checked = avail == 1;
  document.getElementById('edit_best_seller').checked = bestSeller == 1;

  const subGroup  = document.getElementById('editSubcategoryGroup');
  const subSelect = document.getElementById('edit_subcategory');
  if (category === 'beverages') { subGroup.style.display = 'block'; subSelect.value = subcategory || ''; }
  else { subGroup.style.display = 'none'; subSelect.value = ''; }

  const imgEl = document.getElementById('edit_current_img');
  if (filePath) {
    imgEl.src = '../' + filePath; imgEl.style.display = 'block';
    document.getElementById('currentImgText').textContent = '✅ Image uploaded';
  } else {
    imgEl.style.display = 'none';
    document.getElementById('currentImgText').textContent = '❌ No image uploaded';
  }

  removeNewImage();
  document.getElementById('editModal').classList.add('show');
  document.body.style.overflow = 'hidden';
}

function closeEdit() {
  document.getElementById('editModal').classList.remove('show');
  document.body.style.overflow = '';
}

document.getElementById('editModal').addEventListener('click', function(e) { if (e.target === this) closeEdit(); });

document.getElementById('edit_category').addEventListener('change', function() {
  const subGroup = document.getElementById('editSubcategoryGroup');
  if (this.value === 'beverages') { subGroup.style.display = 'block'; }
  else { subGroup.style.display = 'none'; document.getElementById('edit_subcategory').value = ''; }
});

// ─── IMAGE PREVIEW ────────────────────────────────────────────
function previewNewImage(input) {
  const file = input.files[0];
  if (!file) return;
  const allowed = ['image/jpeg','image/jpg','image/png','image/gif','image/webp'];
  if (!allowed.includes(file.type)) { alert('❌ Only image files allowed.'); input.value = ''; return; }
  if (file.size > 5 * 1024 * 1024) { alert('❌ File size must be less than 5MB.'); input.value = ''; return; }
  const reader = new FileReader();
  reader.onload = function(e) {
    document.getElementById('newPreviewImg').src = e.target.result;
    document.getElementById('newImgName').textContent = '📁 ' + file.name + ' (' + (file.size/1024).toFixed(1) + ' KB)';
    document.getElementById('newImgPreview').style.display = 'block';
    document.getElementById('editUploadArea').style.display = 'none';
  };
  reader.readAsDataURL(file);
}

function removeNewImage() {
  const fi = document.getElementById('editFileInput');
  if (fi) fi.value = '';
  document.getElementById('newPreviewImg').src = '';
  document.getElementById('newImgName').textContent = '';
  document.getElementById('newImgPreview').style.display = 'none';
  document.getElementById('editUploadArea').style.display = 'block';
}

function toggleCheck(id) {
  const cb = document.getElementById(id);
  cb.checked = !cb.checked;
}

// ─── FILTER TABLE ─────────────────────────────────────────────
function filterTable() {
  const searchVal   = document.getElementById('searchInput').value.toLowerCase().trim();
  const categoryVal = document.getElementById('categoryFilter').value.toLowerCase();
  const availVal    = document.getElementById('availFilter').value;
  const rows        = document.querySelectorAll('#menuTableBody tr[data-name]');
  let visibleCount  = 0;

  rows.forEach(row => {
    const match = (!searchVal   || row.dataset.name.includes(searchVal))
               && (!categoryVal || row.dataset.category === categoryVal)
               && (!availVal    || row.dataset.avail === availVal);
    row.style.display = match ? '' : 'none';
    if (match) visibleCount++;
  });

  document.getElementById('filterCount').textContent = 'Showing ' + visibleCount + ' item' + (visibleCount !== 1 ? 's' : '');

  let noRow = document.getElementById('noResultsRow');
  if (visibleCount === 0 && !noRow) {
    const tbody = document.getElementById('menuTableBody');
    const tr = document.createElement('tr');
    tr.id = 'noResultsRow';
    tr.innerHTML = '<td colspan="7" style="text-align:center;padding:40px;color:#aaa;"><span style="font-size:2.5rem;display:block;margin-bottom:10px;">🔍</span><p style="font-weight:600;">No items match your search.</p></td>';
    tbody.appendChild(tr);
  } else if (visibleCount > 0 && noRow) {
    noRow.remove();
  }
}
</script>
</body>
</html>