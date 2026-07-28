<?php
declare(strict_types=1);
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/includes/auth.php';
header('Content-Type: text/html; charset=UTF-8'); 


if ($u = current_user()) {
    header('Location: ' . role_home($u['role']));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HemoLink — Blood Bank Management System</title>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/theme.css">
<style>
  body {
    margin: 0;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    background: radial-gradient(circle at 15% 15%, #2a3140 0%, var(--slate) 45%, #14171f 100%);
  }
  .hero { max-width: 780px; width: 100%; text-align: center; }
  .brand {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    color: #fff;
    font-weight: 800;
    font-size: 22px;
    margin-bottom: 10px;
  }
  .hero h1 {
    color: #fff;
    font-size: 30px;
    margin: 6px 0 8px;
    letter-spacing: -.3px;
  }
  .hero p.tagline {
    color: #b7bfd0;
    font-size: 15px;
    margin: 0 0 40px;
  }
  .choices {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
  }
  @media (max-width: 620px) {
    .choices { grid-template-columns: 1fr; }
  }
  .choice {
    background: var(--white);
    border-radius: var(--radius);
    padding: 32px 26px;
    text-decoration: none;
    display: block;
    text-align: left;
    box-shadow: var(--shadow);
    border: 1px solid transparent;
    transition: border-color .15s ease, box-shadow .15s ease;
  }
  .choice:hover { border-color: var(--red-md); box-shadow: 0 10px 28px -12px rgba(192,21,43,.25); }
  .choice .icon {
    width: 46px; height: 46px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    background: var(--red-lt);
    color: var(--red);
    margin-bottom: 16px;
  }
  .choice .icon svg { width: 24px; height: 24px; }
  .choice h2 { margin: 0 0 6px; font-size: 17px; color: var(--ink); }
  .choice p { margin: 0 0 14px; font-size: 13px; color: var(--muted); line-height: 1.5; }
  .choice .cta { font-size: 13px; font-weight: 700; color: var(--red); }
  .foot-note { margin-top: 34px; font-size: 12.5px; color: #8b93a7; }
  .foot-note a { color: #d7dbe6; }
</style>
</head>
<body>
<div class="hero">
  <div class="brand">&#129656; HemoLink</div>
  <h1>Who's signing in?</h1>
  <p class="tagline">Choose how you'd like to continue to the Blood Bank Management System.</p>

  <div class="choices">
    <a class="choice" href="<?= BASE_URL ?>login.php?as=staff">
      <div class="icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6M9 11h.01M15 11h.01M9 15h.01M15 15h.01"/>
        </svg>
      </div>
      <h2>Staff / Admin</h2>
      <p>Manage requests, review and approve them, track inventory, and view dashboard stats.</p>
      <span class="cta">Continue as Staff &rarr;</span>
    </a>

    <a class="choice" href="<?= BASE_URL ?>login.php?as=donor">
      <div class="icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 21s-7.5-4.6-10-9.2C.6 8.2 2.3 4.5 6 4a5.4 5.4 0 0 1 6 3 5.4 5.4 0 0 1 6-3c3.7.5 5.4 4.2 4 7.8C19.5 16.4 12 21 12 21z"/>
        </svg>
      </div>
      <h2>Donor</h2>
      <p>View and update your profile, and check your donation history.</p>
      <span class="cta">Continue as Donor &rarr;</span>
    </a>
  </div>

  <p class="foot-note">New donor? <a href="<?= BASE_URL ?>register.php">Register here</a> </p>
</div>
</body>
</html>