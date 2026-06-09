<?php
// ─────────────────────────────────────────
//  MIA COFFEE — Root Router
//  Serves admin or customer app based on host
// ─────────────────────────────────────────

// --- Configuration ---
define('ADMIN_HOST',    'admin.yourdomain.com');   // ← change to your actual admin subdomain
define('ADMIN_PATH',   __DIR__ . '/admin/index.php');
define('CUSTOMER_PATH',__DIR__ . '/customer/index.php');

// --- Routing logic ---
$host = $_SERVER['HTTP_HOST'] ?? '';

// Strip port if present (e.g. localhost:8080 → localhost)
$host = strtolower(explode(':', $host)[0]);

if ($host === ADMIN_HOST) {

    // ── Admin route ──────────────────────────────
    if (!file_exists(ADMIN_PATH)) {
        http_response_code(503);
        exit('Admin app not found. Please check your server configuration.');
    }
    include ADMIN_PATH;

} else {

    // ── Customer route (default) ─────────────────
    if (!file_exists(CUSTOMER_PATH)) {
        // Fallback: serve the splash inline if /customer/index.php is missing
        serveSplash();
    } else {
        include CUSTOMER_PATH;
    }

}

// ─────────────────────────────────────────
//  Fallback splash page (shown if /customer/index.php is missing)
//  Once your customer folder is set up, this function is never called.
// ─────────────────────────────────────────
function serveSplash(): void
{
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="refresh" content="3;url=/customer/login.php">
  <title>MIA COFFEE</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'DM Sans', sans-serif;
      background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .splash {
      text-align: center;
      animation: fadeUp 0.6s cubic-bezier(.22,.68,0,1.2) both;
    }

    @keyframes fadeUp {
      from { transform: translateY(24px); opacity: 0; }
      to   { transform: translateY(0);    opacity: 1; }
    }

    .brand-icon {
      width: 72px; height: 72px;
      border-radius: 20px;
      background: rgba(255,255,255,0.07);
      border: 1px solid rgba(255,255,255,0.12);
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 20px;
    }

    .brand-icon svg { width: 38px; height: 38px; }

    .brand-name {
      font-family: 'Playfair Display', serif;
      font-size: 36px; font-weight: 700;
      color: #fff; letter-spacing: -0.5px;
      margin-bottom: 8px;
    }

    .brand-name span { color: #e31937; }

    .brand-tagline {
      font-size: 13px; color: rgba(255,255,255,0.45);
      margin-bottom: 40px; letter-spacing: 0.3px;
    }

    .spinner-wrap {
      display: flex; flex-direction: column;
      align-items: center; gap: 14px;
    }

    .spinner {
      width: 32px; height: 32px;
      border: 2.5px solid rgba(255,255,255,0.12);
      border-top-color: #e31937;
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
    }

    @keyframes spin { to { transform: rotate(360deg); } }

    .redirect-text {
      font-size: 13px; color: rgba(255,255,255,0.4);
    }

    .manual-link {
      margin-top: 28px;
      font-size: 12px; color: rgba(255,255,255,0.3);
    }

    .manual-link a {
      color: rgba(255,255,255,0.55);
      text-decoration: none;
      border-bottom: 1px solid rgba(255,255,255,0.2);
      transition: color 0.2s;
    }

    .manual-link a:hover { color: #fff; }
  </style>
</head>
<body>

<div class="splash">

  <div class="brand-icon">
    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M5 8h14l-1.5 9H6.5L5 8z" stroke="#fff" stroke-width="1.5" stroke-linejoin="round"/>
      <path d="M5 8c0 0 0-3 3-3" stroke="#fff" stroke-width="1.5" stroke-linecap="round"/>
      <path d="M17 8c1.5 0 2.5 1 2.5 2.5S18.5 13 17 13" stroke="#e31937" stroke-width="1.5" stroke-linecap="round"/>
      <path d="M9 12c.5 1.5 2 2 3 1s1-3 2.5-3" stroke="#fff" stroke-width="1.2" stroke-linecap="round"/>
    </svg>
  </div>

  <div class="brand-name"><span>MIA</span> COFFEE</div>
  <div class="brand-tagline">Your favourite coffee, one click away.</div>

  <div class="spinner-wrap">
    <div class="spinner"></div>
    <div class="redirect-text">Redirecting you to login…</div>
  </div>

  <div class="manual-link">
    Not redirected? <a href="/customer/login.php">Click here</a>
  </div>

</div>

<script>
  setTimeout(() => { window.location.href = '/customer/login.php'; }, 2500);
</script>

</body>
</html>
<?php
} // end serveSplash()