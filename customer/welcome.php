<?php
session_start();

// If already logged in, redirect to menu
if (isset($_SESSION['customer_id'])) {
    header('Location: menu.php');
    exit;
}

// If already a guest, redirect to menu
if (isset($_SESSION['guest']) && $_SESSION['guest'] === true) {
    header('Location: menu.php');
    exit;
}

// If guest button clicked
if (isset($_GET['guest'])) {
    session_unset();
    $_SESSION['guest'] = true;
    $_SESSION['name']  = 'Guest';
    header('Location: menu.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Welcome - Cafe Digital</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: #1a1a2e;
    position: relative;
    overflow: hidden;
  }

  /* ===== BACKGROUND CIRCLES ===== */
  .bg-circle {
    position: absolute;
    border-radius: 50%;
    background: #e31937;
    opacity: 0.07;
    pointer-events: none;
  }

  .bg-circle-1 {
    width: 500px;
    height: 500px;
    top: -200px;
    right: -150px;
  }

  .bg-circle-2 {
    width: 400px;
    height: 400px;
    bottom: -150px;
    left: -120px;
  }

  .bg-circle-3 {
    width: 200px;
    height: 200px;
    top: 40%;
    left: 20%;
    opacity: 0.04;
  }

  /* ===== PAGE WRAPPER ===== */
  .page-wrapper {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 420px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    animation: fadeUp 0.6s ease;
  }

  @keyframes fadeUp {
    from { transform: translateY(25px); opacity: 0; }
    to   { transform: translateY(0);    opacity: 1; }
  }

  /* ===== CAFE HEADER ===== */
  .cafe-header {
    text-align: center;
    margin-bottom: 45px;
  }

  .cafe-logo {
    font-size: 4rem;
    display: block;
    margin-bottom: 12px;
    animation: float 3s ease-in-out infinite;
  }

  @keyframes float {
    0%, 100% { transform: translateY(0px);   }
    50%       { transform: translateY(-10px); }
  }

  .cafe-name {
    font-size: 2.8rem;
    font-weight: 900;
    color: #fff;
    letter-spacing: 2px;
    margin-bottom: 8px;
    text-shadow: 0 4px 20px rgba(0,0,0,0.4);
  }

  .cafe-name span {
    color: #e31937;
  }

  .cafe-tagline {
    font-size: 0.95rem;
    color: rgba(255,255,255,0.45);
    letter-spacing: 0.5px;
  }

  .header-divider {
    width: 50px;
    height: 4px;
    background: #e31937;
    border-radius: 2px;
    margin: 15px auto 0;
  }

  /* ===== SUBTITLE ===== */
  .subtitle {
    text-align: center;
    margin-bottom: 30px;
    width: 100%;
  }

  .subtitle h3 {
    font-size: 1rem;
    font-weight: 600;
    color: rgba(255,255,255,0.6);
    letter-spacing: 0.5px;
  }

  /* ===== BUTTONS WRAPPER ===== */
  .buttons-wrapper {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 15px;
  }

  /* ===== LOGIN BUTTON ===== */
  .btn-login {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    width: 100%;
    padding: 18px 20px;
    background: #e31937;
    color: #fff;
    text-decoration: none;
    border-radius: 14px;
    font-size: 1.05rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
    box-shadow: 0 6px 20px rgba(227,25,55,0.4);
    border: 2px solid #e31937;
  }

  .btn-login:hover {
    background: #b21427;
    border-color: #b21427;
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(227,25,55,0.5);
  }

  .btn-login:active {
    transform: translateY(0);
  }

  .btn-icon {
    font-size: 1.3rem;
  }

  .btn-text {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 2px;
  }

  .btn-text-main {
    font-size: 1rem;
    font-weight: 700;
    line-height: 1;
  }

  .btn-text-sub {
    font-size: 0.75rem;
    font-weight: 400;
    opacity: 0.85;
  }

  /* ===== GUEST BUTTON ===== */
  .btn-guest {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    width: 100%;
    padding: 18px 20px;
    background: rgba(255,255,255,0.05);
    color: #fff;
    text-decoration: none;
    border-radius: 14px;
    font-size: 1.05rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    transition: all 0.3s ease;
    border: 2px solid rgba(255,255,255,0.2);
  }

  .btn-guest:hover {
    background: rgba(255,255,255,0.12);
    border-color: rgba(255,255,255,0.4);
    transform: translateY(-3px);
    box-shadow: 0 10px 30px rgba(255,255,255,0.08);
  }

  .btn-guest:active {
    transform: translateY(0);
  }

  /* ===== DIVIDER ===== */
  .or-divider {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
  }

  .or-line {
    flex: 1;
    height: 1px;
    background: rgba(255,255,255,0.1);
  }

  .or-text {
    font-size: 0.8rem;
    color: rgba(255,255,255,0.3);
    font-weight: 600;
  }

  /* ===== REGISTER LINK ===== */
  .register-link {
    margin-top: 25px;
    text-align: center;
    color: rgba(255,255,255,0.4);
    font-size: 0.88rem;
  }

  .register-link a {
    color: #e31937;
    font-weight: 700;
    text-decoration: none;
    transition: color 0.3s;
  }

  .register-link a:hover {
    color: #ff6b6b;
    text-decoration: underline;
  }

  /* ===== FOOTER ===== */
  .page-footer {
    margin-top: 35px;
    text-align: center;
    color: rgba(255,255,255,0.2);
    font-size: 0.75rem;
  }

  /* ===== RESPONSIVE ===== */
  @media (max-width: 480px) {
    .cafe-name  { font-size: 2.2rem; }
    .cafe-logo  { font-size: 3.2rem; }
    .page-wrapper { padding: 20px 15px; }

    .btn-login,
    .btn-guest {
      padding: 16px 18px;
    }
  }
</style>
</head>
<body>

<!-- Background Circles -->
<div class="bg-circle bg-circle-1"></div>
<div class="bg-circle bg-circle-2"></div>
<div class="bg-circle bg-circle-3"></div>

<div class="page-wrapper">

  <!-- ===== CAFE NAME ON TOP ===== -->
  <div class="cafe-header">
    <span class="cafe-logo">☕</span>
    <div class="cafe-name">
      Cafe <span>Digital</span>
    </div>
    <div class="cafe-tagline">
      Your favourite cafe, now online
    </div>
    <div class="header-divider"></div>
  </div>

  <!-- ===== SUBTITLE ===== -->
  <div class="subtitle">
    <h3>How would you like to continue?</h3>
  </div>

  <!-- ===== BUTTONS ===== -->
  <div class="buttons-wrapper">

    <!-- LOGIN BUTTON -->
    <a href="login.php" class="btn-login">
      <span class="btn-icon">🔑</span>
      <div class="btn-text">
        <span class="btn-text-main">Login to My Account</span>
        <span class="btn-text-sub">Access full features & order history</span>
      </div>
    </a>

    <!-- OR DIVIDER -->
    <div class="or-divider">
      <div class="or-line"></div>
      <div class="or-text">OR</div>
      <div class="or-line"></div>
    </div>

    <!-- GUEST BUTTON -->
    <a href="?guest=1" class="btn-guest">
      <span class="btn-icon">👤</span>
      <div class="btn-text">
        <span class="btn-text-main">Continue as Guest</span>
        <span class="btn-text-sub">Browse menu without an account</span>
      </div>
    </a>

  </div>

  <!-- ===== REGISTER LINK ===== -->
  <div class="register-link">
    Don't have an account?
    <a href="register.php">Register here</a>
  </div>

  <!-- ===== FOOTER ===== -->
  <div class="page-footer">
    © <?= date('Y') ?> Cafe Digital. All rights reserved.
  </div>

</div>

</body>
</html>