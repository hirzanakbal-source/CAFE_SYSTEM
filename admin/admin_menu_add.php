<?php
// ===== CORRECT DB PATH FOR ADMIN FOLDER =====
require __DIR__ . '/../db.php';

// Check if admin logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

$admin_name  = $_SESSION['admin_name'] ?? 'Admin';
$message     = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $menu_name    = trim($_POST['menu_name']   ?? '');
    $description  = trim($_POST['description'] ?? '');
    $price        = $_POST['price']             ?? '';
    $category     = $_POST['category']          ?? '';
    $availability = isset($_POST['availability']) ? 1 : 0;
    $best_seller  = isset($_POST['best_seller'])  ? 1 : 0;
    $file_path    = null;

    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath   = $_FILES['file']['tmp_name'];
        $fileName      = $_FILES['file']['name'];
        $fileSize      = $_FILES['file']['size'];
        $fileNameCmps  = explode('.', $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $maxFileSize       = 5 * 1024 * 1024;

        if (!in_array($fileExtension, $allowedExtensions)) {
            $message     = '❌ Only image files allowed: JPG, JPEG, PNG, GIF, WEBP';
            $messageType = 'error';
        } elseif ($fileSize > $maxFileSize) {
            $message     = '❌ File size must be less than 5MB.';
            $messageType = 'error';
        } else {
            $uploadDir = __DIR__ . '/../uploads/menu_files/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

            $newFileName = 'menu_' . time() . '_' . uniqid() . '.' . $fileExtension;
            $destPath    = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $file_path = 'uploads/menu_files/' . $newFileName;
            } else {
                $message     = '❌ Error uploading file. Check folder permissions.';
                $messageType = 'error';
            }
        }
    }

    if ($menu_name && $price && $category && !$message) {
        try {
            $subcategory = ($category === 'beverages') ? trim($_POST['subcategory'] ?? '') : null;
            $stmt = $pdo->prepare("
                INSERT INTO MENU (menu_name, description, price, category, subcategory, availability, best_seller, file_path)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$menu_name, $description, $price, $category, $subcategory, $availability, $best_seller, $file_path]);
            $message     = '✅ Menu item "' . htmlspecialchars($menu_name) . '" added successfully!';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message     = '❌ Failed to add menu item: ' . $e->getMessage();
            $messageType = 'error';
            if ($file_path) {
                $fullPath = __DIR__ . '/../' . $file_path;
                if (file_exists($fullPath)) unlink($fullPath);
            }
        }
    } elseif (!$message) {
        $message     = '❌ Please fill in all required fields.';
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Add Menu Item — Cafe Digital</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
:root {
  --sidebar-bg: #0f1629;
  --sidebar-accent: #3b82f6;
  --sidebar-text: #94a3b8;
  --sidebar-hover: rgba(255,255,255,0.06);
  --body-bg: #f1f5f9;
  --card-bg: #ffffff;
  --border: #e2e8f0;
  --text-primary: #0f172a;
  --text-secondary: #64748b;
  --text-muted: #94a3b8;
  --blue: #3b82f6;
  --green: #10b981;
  --orange: #f59e0b;
  --red: #ef4444;
  --purple: #8b5cf6;
  --teal: #14b8a6;
  --pink: #ec4899;
  --indigo: #6366f1;
  --sidebar-width: 240px;
  --radius: 12px;
}

* { box-sizing: border-box; margin: 0; padding: 0; }

body {
  font-family: 'DM Sans', sans-serif;
  background: var(--body-bg);
  color: var(--text-primary);
  min-height: 100vh;
  display: flex;
}

@keyframes fadeUp {
  from { opacity: 0; transform: translateY(16px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ─── SIDEBAR ─── */
.sidebar {
  position: fixed;
  top: 0; left: 0;
  width: var(--sidebar-width);
  height: 100vh;
  background: var(--sidebar-bg);
  display: flex;
  flex-direction: column;
  z-index: 200;
  overflow-y: auto;
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.sidebar-brand {
  padding: 28px 22px 22px;
  border-bottom: 1px solid rgba(255,255,255,0.07);
}

.brand-icon {
  width: 38px; height: 38px;
  background: var(--sidebar-accent);
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.1rem;
  margin-bottom: 10px;
}

.sidebar-brand h2 {
  font-size: 0.95rem;
  font-weight: 700;
  color: #fff;
  letter-spacing: 0.01em;
}

.sidebar-brand p {
  font-size: 0.72rem;
  color: var(--sidebar-text);
  margin-top: 2px;
  letter-spacing: 0.03em;
}

.sidebar-nav { padding: 18px 0; flex: 1; }

.nav-section-label {
  font-size: 0.65rem;
  font-weight: 600;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  padding: 14px 22px 6px;
}

.nav-link {
  display: flex;
  align-items: center;
  gap: 11px;
  padding: 10px 22px;
  color: var(--sidebar-text);
  text-decoration: none;
  font-size: 0.85rem;
  font-weight: 500;
  transition: all 0.2s;
  position: relative;
}

.nav-link:hover { background: var(--sidebar-hover); color: #fff; }

.nav-link.active {
  background: rgba(59,130,246,0.15);
  color: var(--sidebar-accent);
}

.nav-link.active::before {
  content: '';
  position: absolute;
  left: 0; top: 0; bottom: 0;
  width: 3px;
  background: var(--sidebar-accent);
  border-radius: 0 3px 3px 0;
}

.nav-icon {
  width: 32px; height: 32px;
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.9rem;
  flex-shrink: 0;
  background: rgba(255,255,255,0.04);
  transition: background 0.2s;
}

.nav-link.active .nav-icon { background: rgba(59,130,246,0.2); }
.nav-link:hover  .nav-icon { background: rgba(255,255,255,0.08); }

.sidebar-footer {
  padding: 16px 22px;
  border-top: 1px solid rgba(255,255,255,0.07);
}

.logout-btn {
  display: flex;
  align-items: center;
  gap: 10px;
  color: #f87171;
  text-decoration: none;
  font-size: 0.85rem;
  font-weight: 500;
  padding: 9px 12px;
  border-radius: 8px;
  transition: background 0.2s;
}

.logout-btn:hover { background: rgba(239,68,68,0.1); }

/* ─── OVERLAY ─── */
.sidebar-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.5);
  z-index: 199;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.3s ease;
  backdrop-filter: blur(2px);
  -webkit-backdrop-filter: blur(2px);
}

.sidebar-overlay.visible {
  opacity: 1;
  pointer-events: auto;
}

/* ─── MAIN ─── */
.main {
  margin-left: var(--sidebar-width);
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

/* ─── TOPBAR ─── */
.topbar {
  background: #fff;
  border-bottom: 1px solid var(--border);
  padding: 14px 30px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
  position: sticky;
  top: 0;
  z-index: 210;
}

.topbar-left {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
  min-width: 0;
}

/* ─── HAMBURGER ─── */
.hamburger {
  display: none;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  gap: 5px;
  width: 36px; height: 36px;
  border: 1px solid var(--border);
  border-radius: 9px;
  background: #f8fafc;
  cursor: pointer;
  flex-shrink: 0;
  transition: background 0.2s, border-color 0.2s;
  padding: 0;
}

.hamburger:hover { background: #f1f5f9; border-color: #cbd5e1; }

.hamburger span {
  display: block;
  width: 16px; height: 2px;
  background: var(--text-primary);
  border-radius: 2px;
  transition: transform 0.3s ease, opacity 0.3s ease, width 0.3s ease;
  transform-origin: center;
}

.hamburger.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
.hamburger.open span:nth-child(2) { opacity: 0; width: 0; }
.hamburger.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

.topbar-title h1 {
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--text-primary);
}

.topbar-title p {
  font-size: 0.75rem;
  color: var(--text-secondary);
  margin-top: 1px;
}

.topbar-right { display: flex; align-items: center; gap: 10px; }

.date-pill {
  display: flex;
  align-items: center;
  gap: 6px;
  background: #f8fafc;
  border: 1px solid var(--border);
  padding: 6px 13px;
  border-radius: 99px;
  font-size: 0.75rem;
  color: var(--text-secondary);
  font-weight: 500;
}

.admin-pill {
  display: flex;
  align-items: center;
  gap: 9px;
  background: #f8fafc;
  border: 1px solid var(--border);
  padding: 6px 14px 6px 7px;
  border-radius: 99px;
}

.admin-avatar {
  width: 28px; height: 28px;
  background: var(--sidebar-accent);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  color: #fff;
  font-size: 0.78rem;
  font-weight: 700;
}

.admin-pill span {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--text-primary);
}

/* ─── CONTENT ─── */
.content { padding: 26px 30px; flex: 1; }

/* ─── MESSAGE ─── */
.message {
  display: flex;
  align-items: center;
  gap: 10px;
  border-radius: var(--radius);
  padding: 11px 16px;
  margin-bottom: 18px;
  font-size: 0.82rem;
  font-weight: 600;
  animation: fadeUp 0.3s ease both;
  border: 1px solid transparent;
}

.message.success { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
.message.error   { background: #fff1f2; border-color: #fecdd3; color: #9f1239; }

/* ─── PAGE GRID ─── */
.page-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 340px;
  gap: 18px;
  align-items: start;
}

/* ─── CARD ─── */
.card {
  background: var(--card-bg);
  border-radius: var(--radius);
  border: 1px solid var(--border);
  animation: fadeUp 0.4s ease both;
  overflow: hidden;
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 16px 18px;
  border-bottom: 1px solid var(--border);
  background: #fff;
}

.card-title-wrap { display: flex; align-items: center; gap: 10px; }

.card-emoji {
  width: 34px; height: 34px;
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  background: #eff6ff;
  font-size: 1rem;
}

.card-title { font-size: 0.9rem; font-weight: 700; color: var(--text-primary); }
.card-subtitle { font-size: 0.73rem; color: var(--text-muted); margin-top: 2px; }
.card-body { padding: 18px; }

/* ─── FORM ─── */
.form-group { margin-bottom: 16px; }

.form-group label {
  display: block;
  font-weight: 600;
  font-size: 0.82rem;
  color: var(--text-primary);
  margin-bottom: 6px;
}

.form-group label span.required { color: var(--red); }

.form-group input[type="text"],
.form-group input[type="number"],
.form-group textarea,
.form-group select {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid var(--border);
  border-radius: 10px;
  font-size: 0.84rem;
  color: var(--text-primary);
  transition: border-color 0.2s, box-shadow 0.2s;
  background: #fff;
  font-family: inherit;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
  border-color: #bfdbfe;
  outline: none;
  box-shadow: 0 0 0 3px #eff6ff;
}

.form-group textarea { resize: vertical; min-height: 95px; }

.price-input-wrap {
  display: flex;
  align-items: center;
  border: 1px solid var(--border);
  border-radius: 10px;
  overflow: hidden;
  transition: border-color 0.2s, box-shadow 0.2s;
  background: #fff;
}

.price-input-wrap:focus-within {
  border-color: #bfdbfe;
  box-shadow: 0 0 0 3px #eff6ff;
}

.price-prefix {
  padding: 10px 12px;
  background: #f8fafc;
  color: var(--text-secondary);
  font-weight: 700;
  font-size: 0.83rem;
  border-right: 1px solid var(--border);
  white-space: nowrap;
}

.price-input-wrap input {
  border: none !important;
  box-shadow: none !important;
  background: transparent !important;
  flex: 1;
}

.checkbox-group {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border: 1px solid var(--border);
  border-radius: 10px;
  background: #fff;
  cursor: pointer;
  transition: border-color 0.2s, background 0.2s;
}

.checkbox-group:hover { border-color: #bfdbfe; background: #f8fafc; }

.checkbox-group input[type="checkbox"] {
  width: 16px; height: 16px;
  accent-color: var(--blue);
  cursor: pointer;
  flex-shrink: 0;
}

.checkbox-label {
  font-size: 0.82rem;
  color: var(--text-secondary);
  font-weight: 500;
  cursor: pointer;
  user-select: none;
}

.upload-area {
  border: 2px dashed #cbd5e1;
  border-radius: 10px;
  padding: 24px 18px;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s;
  background: #f8fafc;
  position: relative;
}

.upload-area:hover, .upload-area.dragover {
  border-color: #93c5fd;
  background: #eff6ff;
}

.upload-icon { font-size: 2rem; display: block; margin-bottom: 8px; }
.upload-text { font-size: 0.82rem; color: var(--text-primary); font-weight: 600; margin-bottom: 3px; }
.upload-subtext { font-size: 0.72rem; color: var(--text-muted); }

.upload-area input[type="file"] {
  position: absolute;
  top: 0; left: 0;
  width: 100%; height: 100%;
  opacity: 0;
  cursor: pointer;
}

.image-preview {
  display: none;
  margin-top: 12px;
  text-align: center;
  padding: 14px;
  border: 1px solid var(--border);
  border-radius: 10px;
  background: #fff;
}

.image-preview img {
  max-width: 100%;
  max-height: 200px;
  border-radius: 10px;
  border: 1px solid #e5e7eb;
  object-fit: cover;
  box-shadow: 0 3px 10px rgba(0,0,0,0.06);
}

.preview-name { font-size: 0.74rem; color: var(--text-secondary); margin-top: 8px; }

.remove-image {
  display: inline-block;
  margin-top: 7px;
  color: #dc2626;
  font-size: 0.76rem;
  cursor: pointer;
  font-weight: 600;
  background: none;
  border: none;
  text-decoration: underline;
  padding: 0;
}

.submit-btn {
  width: 100%;
  padding: 12px;
  background: var(--blue);
  color: #fff;
  border: none;
  border-radius: 10px;
  font-size: 0.86rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
  margin-top: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.submit-btn:hover {
  background: #2563eb;
  transform: translateY(-1px);
  box-shadow: 0 8px 20px rgba(59,130,246,0.25);
}

.submit-btn:active { transform: translateY(0); }

/* ─── RIGHT PANEL ─── */
.right-panel { display: flex; flex-direction: column; gap: 16px; }
.info-card .card-header { padding: 14px 16px; }
.info-card .card-body   { padding: 14px 16px; }

.category-preview { display: flex; flex-direction: column; gap: 8px; }

.cat-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 11px;
  border-radius: 10px;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s;
  border: 1px solid transparent;
}

.cat-item:hover { transform: translateX(3px); }
.cat-food     { background: #fff7ed; color: #9a3412; border-color: #fed7aa; }
.cat-beverages{ background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
.cat-dessert  { background: #fdf2f8; color: #9d174d; border-color: #fbcfe8; }

.quick-links { display: flex; flex-direction: column; gap: 8px; }

.quick-link {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 10px;
  text-decoration: none;
  font-size: 0.8rem;
  font-weight: 600;
  transition: all 0.2s;
  border: 1px solid var(--border);
  color: var(--text-primary);
  background: #fff;
}

.quick-link:hover {
  border-color: #bfdbfe;
  color: var(--blue);
  background: #f8fafc;
  transform: translateX(3px);
}

.quick-link-arrow { margin-left: auto; color: var(--text-muted); font-size: 1rem; }

.guide-list { display: flex; flex-direction: column; gap: 9px; }

.guide-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px;
  border-radius: 10px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
}

.guide-item.tip { background: #fffbeb; border-color: #fde68a; }

.guide-item-title { font-size: 0.79rem; font-weight: 700; color: var(--text-primary); }
.guide-item-sub   { font-size: 0.72rem; color: var(--text-secondary); }
.guide-item.tip .guide-item-title,
.guide-item.tip .guide-item-sub { color: #92400e; }

.footer {
  padding: 14px 30px;
  border-top: 1px solid var(--border);
  text-align: center;
  font-size: 0.72rem;
  color: var(--text-muted);
}

/* ─── RESPONSIVE ─── */
@media (max-width: 1200px) {
  .page-grid { grid-template-columns: 1fr 320px; }
}

@media (max-width: 1024px) {
  .page-grid { grid-template-columns: 1fr; }
  .right-panel { display: grid; grid-template-columns: 1fr 1fr; }
}

@media (max-width: 768px) {
  /* Sidebar becomes a floating drawer */
  .sidebar {
    transform: translateX(-100%);
    box-shadow: none;
  }

  .sidebar.open {
    transform: translateX(0);
    box-shadow: 8px 0 32px rgba(0,0,0,0.25);
  }

  .sidebar-overlay { display: block; }

  .main { margin-left: 0; }

  body.sidebar-open { overflow: hidden; }

  .hamburger { display: flex; }

  .content { padding: 16px; }
  .topbar  { padding: 12px 16px; }
  .footer  { padding: 12px 16px; }

  .right-panel { grid-template-columns: 1fr; }

  .date-pill { display: none; }
}
</style>
</head>
<body>

<!-- ─── OVERLAY ─── -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ─── SIDEBAR ─── -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon">☕</div>
    <h2>Cafe Digital</h2>
    <p>Admin Panel</p>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Main</div>
    <a href="admin_dashboard.php" class="nav-link">
      <span class="nav-icon">🏠</span> Dashboard
    </a>

    <div class="nav-section-label">Catalogue</div>
    <a href="admin_menu_add.php" class="nav-link active">
      <span class="nav-icon">➕</span> Add Menu
    </a>
    <a href="admin_menu_list.php" class="nav-link">
      <span class="nav-icon">📋</span> View / Edit Menu
    </a>

    <div class="nav-section-label">Operations</div>
    <a href="admin_orders.php" class="nav-link">
      <span class="nav-icon">🛒</span> Manage Orders
    </a>
    <a href="admin_coupons.php" class="nav-link">
      <span class="nav-icon">🎫</span> Manage Coupons
    </a>

    <div class="nav-section-label">Analytics</div>
    <a href="admin_reports.php" class="nav-link">
      <span class="nav-icon">📊</span> Sales Report
    </a>
  </nav>

  <div class="sidebar-footer">
    <a href="admin_logout.php" class="logout-btn">
      <span>🚪</span> Logout
    </a>
  </div>
</aside>

<!-- ─── MAIN ─── -->
<div class="main">

  <!-- TOPBAR -->
  <div class="topbar">
    <div class="topbar-left">
      <button type="button" class="hamburger" id="hamburger" aria-label="Toggle navigation" aria-expanded="false">
        <span></span>
        <span></span>
        <span></span>
      </button>
      <div class="topbar-title">
        <h1>Add Menu Item</h1>
        <p>Create a new food, beverage, or dessert listing.</p>
      </div>
    </div>
    <div class="topbar-right">
      <div class="date-pill">📅 <?= date('D, d M Y') ?></div>
      <div class="admin-pill">
        <div class="admin-avatar"><?= strtoupper(substr($admin_name, 0, 1)) ?></div>
        <span><?= htmlspecialchars($admin_name) ?></span>
      </div>
    </div>
  </div>

  <!-- CONTENT -->
  <div class="content">

    <?php if ($message): ?>
      <div class="message <?= $messageType ?>">
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>

    <div class="page-grid">

      <!-- LEFT: MAIN FORM -->
      <div class="card">
        <div class="card-header">
          <div class="card-title-wrap">
            <span class="card-emoji">🍽️</span>
            <div>
              <div class="card-title">New Menu Item</div>
              <div class="card-subtitle">Fill in required details to publish this item.</div>
            </div>
          </div>
        </div>

        <div class="card-body">
          <form method="post" enctype="multipart/form-data" id="menuForm">

            <div class="form-group">
              <label>Menu Name <span class="required">*</span></label>
              <input type="text" name="menu_name" placeholder="e.g. Nasi Lemak Special" required>
            </div>

            <div class="form-group">
              <label>Description</label>
              <textarea name="description" placeholder="Describe the menu item, ingredients, etc..."></textarea>
            </div>

            <div class="form-group">
              <label>Price <span class="required">*</span></label>
              <div class="price-input-wrap">
                <span class="price-prefix">RM</span>
                <input type="number" name="price" step="0.01" min="0" placeholder="0.00" required>
              </div>
            </div>

            <div class="form-group">
              <label>Category <span class="required">*</span></label>
              <select name="category" id="categorySelect" required onchange="updateCategoryPreview(this.value)">
                <option value="">-- Select Category --</option>
                <option value="food">🍱 Food</option>
                <option value="beverages">☕ Beverages</option>
                <option value="dessert">🍰 Dessert</option>
              </select>
            </div>

            <div class="form-group" id="subcategoryGroup" style="display:none;">
              <label>Beverage Type <span class="required">*</span></label>
              <select name="subcategory" id="subcategorySelect">
                <option value="">-- Select Type --</option>
                <option value="coffee">☕ Coffee</option>
                <option value="latte">🥛 Latte</option>
                <option value="sparkling tea">🫧 Sparkling Tea</option>
                <option value="ice crush series">🧊 Ice Crush Series</option>
                <option value="al-hadad shake">🥤 Al-Hadad Shake</option>
              </select>
            </div>

            <div class="form-group">
              <label>Availability</label>
              <div class="checkbox-group" onclick="toggleCheck('availability')">
                <input type="checkbox" name="availability" id="availability" checked>
                <span class="checkbox-label">✅ Available for order</span>
              </div>
            </div>

            <div class="form-group">
              <label>Best Seller</label>
              <div class="checkbox-group" onclick="toggleCheck('best_seller')">
                <input type="checkbox" name="best_seller" id="best_seller">
                <span class="checkbox-label">⭐ Mark as Best Seller</span>
              </div>
            </div>

            <div class="form-group">
              <label>Menu Image (Optional)</label>
              <div class="upload-area" id="uploadArea">
                <span class="upload-icon">📷</span>
                <div class="upload-text">Click or drag & drop image here</div>
                <div class="upload-subtext">JPG, JPEG, PNG, GIF, WEBP • Max 5MB</div>
                <input type="file" name="file" id="fileInput" accept="image/*">
              </div>

              <div class="image-preview" id="imagePreview">
                <img id="previewImg" src="" alt="Preview">
                <div class="preview-name" id="previewName"></div>
                <button type="button" class="remove-image" onclick="removeImage()">✕ Remove Image</button>
              </div>
            </div>

            <button type="submit" class="submit-btn">
              <span>➕</span>
              <span>Add Menu Item</span>
            </button>

          </form>
        </div>
      </div>

      <!-- RIGHT: INFO PANEL -->
      <div class="right-panel">

        <div class="card info-card">
          <div class="card-header">
            <div class="card-title">📷 Image Guide</div>
          </div>
          <div class="card-body">
            <div class="guide-list">
              <div class="guide-item">
                <span style="font-size:1.3rem;">✅</span>
                <div>
                  <div class="guide-item-title">Recommended</div>
                  <div class="guide-item-sub">Square image, 500x500px+, JPG/PNG</div>
                </div>
              </div>
              <div class="guide-item">
                <span style="font-size:1.3rem;">📏</span>
                <div>
                  <div class="guide-item-title">Max File Size</div>
                  <div class="guide-item-sub">5MB per image</div>
                </div>
              </div>
              <div class="guide-item">
                <span style="font-size:1.3rem;">🎨</span>
                <div>
                  <div class="guide-item-title">Formats Allowed</div>
                  <div class="guide-item-sub">JPG, JPEG, PNG, GIF, WEBP</div>
                </div>
              </div>
              <div class="guide-item tip">
                <span style="font-size:1.3rem;">💡</span>
                <div>
                  <div class="guide-item-title">Pro Tip</div>
                  <div class="guide-item-sub">Use food photography with good lighting for best results</div>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

  </div><!-- /content -->

  <div class="footer">
    Cafe Digital Admin Panel &nbsp;|&nbsp; © <?= date('Y') ?>
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
  if (e.key === 'Escape' && sidebar.classList.contains('open')) closeSidebar();
});

sidebar.querySelectorAll('.nav-link').forEach(link => {
  link.addEventListener('click', () => {
    if (window.innerWidth <= 768) closeSidebar();
  });
});

// ─── FILE UPLOAD ──────────────────────────────────────────────
const fileInput    = document.getElementById('fileInput');
const uploadArea   = document.getElementById('uploadArea');
const imagePreview = document.getElementById('imagePreview');
const previewImg   = document.getElementById('previewImg');
const previewName  = document.getElementById('previewName');

fileInput.addEventListener('change', function () {
  const file = this.files[0];
  if (file) showPreview(file);
});

uploadArea.addEventListener('dragover', function (e) {
  e.preventDefault();
  this.classList.add('dragover');
});

uploadArea.addEventListener('dragleave', function () {
  this.classList.remove('dragover');
});

uploadArea.addEventListener('drop', function (e) {
  e.preventDefault();
  this.classList.remove('dragover');
  const file = e.dataTransfer.files[0];
  if (file) {
    const dt = new DataTransfer();
    dt.items.add(file);
    fileInput.files = dt.files;
    showPreview(file);
  }
});

function showPreview(file) {
  const allowed = ['image/jpeg','image/jpg','image/png','image/gif','image/webp'];
  if (!allowed.includes(file.type)) {
    alert('❌ Only image files are allowed:\nJPG, JPEG, PNG, GIF, WEBP');
    fileInput.value = '';
    return;
  }
  if (file.size > 5 * 1024 * 1024) {
    alert('❌ File size must be less than 5MB.\nYour file: ' + (file.size/1024/1024).toFixed(2) + 'MB');
    fileInput.value = '';
    return;
  }
  const reader = new FileReader();
  reader.onload = function (e) {
    previewImg.src          = e.target.result;
    previewName.textContent = '📁 ' + file.name + ' (' + (file.size/1024).toFixed(1) + ' KB)';
    imagePreview.style.display = 'block';
    uploadArea.style.display   = 'none';
  };
  reader.readAsDataURL(file);
}

function removeImage() {
  fileInput.value            = '';
  previewImg.src             = '';
  previewName.textContent    = '';
  imagePreview.style.display = 'none';
  uploadArea.style.display   = 'block';
}

// ─── CHECKBOX / CATEGORY ─────────────────────────────────────
function toggleCheck(id) {
  const cb = document.getElementById(id);
  cb.checked = !cb.checked;
}

function setCategory(value) {
  document.getElementById('categorySelect').value = value;
  updateCategoryPreview(value);
}

function updateCategoryPreview(value) {
  const subGroup = document.getElementById('subcategoryGroup');
  if (value === 'beverages') {
    subGroup.style.display = 'block';
    document.getElementById('subcategorySelect').required = true;
  } else {
    subGroup.style.display = 'none';
    document.getElementById('subcategorySelect').required = false;
    document.getElementById('subcategorySelect').value = '';
  }
}
</script>
</body>
</html>