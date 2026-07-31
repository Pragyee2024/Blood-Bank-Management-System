<?php
declare(strict_types=1);
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/includes/auth.php';
header('Content-Type: text/html; charset=UTF-8'); // override connect.php's JSON header — this page renders HTML

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
  body { margin: 0; background: var(--white); }
  a { text-decoration: none; }

  /* Top bar */
  .topbar {
    position: sticky; top: 0; z-index: 50;
    display: flex; 
    align-items: center; 
    justify-content: space-between;
    padding: 16px 32px;
    background: rgba(255,255,255,.92);
    backdrop-filter: blur(6px);
    border-bottom: 1px solid var(--border);
  }

  .topbar .brand {
    display: flex; 
    align-items: center; 
    gap: 8px;
    font-weight: 800; 
    font-size: 19px; 
    color: var(--ink);
  }

  .topbar nav.links { 
    display: flex; 
    align-items: center; 
    gap: 6px; 
  }

  .topbar nav.links a {
    color: #4b5563; 
    font-size: 14.5px; 
    font-weight: 600;
    padding: 8px 14px; 
    border-radius: 999px; 
    transition: background .15s, color .15s;
  }

  .topbar nav.links a:hover { 
    background: var(--red-lt); 
    color: var(--red); 
  }

  .topbar .actions { 
    display: flex; 
    align-items: center; 
    gap: 10px; 
  }

  .btn-login {
    background: var(--red); 
    color: #fff !important; 
    padding: 9px 20px;
    border-radius: 999px; 
    font-weight: 700; 
    font-size: 14px;
  }

  .btn-login:hover { 
    background: var(--red-dk); 
  }

  .btn-ghost {
    color: var(--ink) !important; 
    font-weight: 600; 
    font-size: 14px;
    padding: 9px 14px;
  }

  .mobile-menu-btn { 
    display: none; 
    background: none; 
    border: none; 
    font-size: 22px; 
    cursor: pointer; 
    color: var(--ink); 
  }

  @media (max-width: 860px) {
    .topbar nav.links { 
      display: none; 
    }
    .mobile-menu-btn { 
      display: block; 
    }
  }


  .hero {
    background: radial-gradient(circle at 15% 15%, #2a3140 0%, var(--slate) 45%, #14171f 100%);
    color: #fff;
    padding: 90px 32px 100px;
    text-align: center;
  }
  .hero .eyebrow {
    display: inline-block; 
    background: rgba(255,255,255,.08); 
    color: #f3c1c7;
    padding: 6px 16px; 
    border-radius: 999px; 
    font-size: 12.5px; 
    font-weight: 700;
    letter-spacing: .04em; 
    margin-bottom: 22px;
  }

  .hero h1 { 
    font-size: 42px; 
    margin: 0 0 16px;
    letter-spacing: -.5px; 
    max-width: 760px; 
    margin-inline: auto; 
  }

  .hero h1 span { 
    color: var(--red-md); 
  }

  .hero p.tagline { 
    color: #b7bfd0; 
    font-size: 16.5px; 
    max-width: 560px; 
    margin: 0 auto 36px; 
    line-height: 1.6; 
  }

  .hero .cta-row { 
    display: flex; 
    justify-content: center; 
    gap: 14px; 
    flex-wrap: wrap; 
  }

  .hero .cta-row a {
    padding: 13px 26px; 
    border-radius: 10px; 
    font-weight: 700; 
    font-size: 14.5px;
  }

  .hero .cta-primary { 
    background: var(--red); 
    color: #fff; 
  }

  .hero .cta-primary:hover { 
    background: var(--red-dk); 
  }

  .hero .cta-secondary { 
    background: rgba(255,255,255,.1); 
    color: #fff; 
    border: 1px solid rgba(255,255,255,.2); 
  }

  .hero .cta-secondary:hover { 
    background: rgba(255,255,255,.16); 
  }


  /* ---------- Sections ---------- */
  section { 
    padding: 80px 32px; 
  }

  .section-inner { 
    max-width: 1080px; 
    margin: 0 auto; 
  }

  .section-head { 
    text-align: center; 
    max-width: 620px; 
    margin: 0 auto 48px; 
  }

  .section-head .kicker { 
    color: var(--red); 
    font-weight: 700; 
    font-size: 13px; 
    letter-spacing: .06em; 
    text-transform: uppercase; 
  }

  .section-head h2 { 
    font-size: 30px; 
    margin: 8px 0 12px; 
    color: var(--ink); 
    letter-spacing: -.3px; 
  }

  .section-head p { 
    color: var(--muted); 
    font-size: 15px; 
    line-height: 1.6; 
    margin: 0; 
  }

  /* Why us */
  .feature-grid { 
    display: grid; 
    grid-template-columns: repeat(3, 1fr); 
    gap: 24px; 
  }

  @media (max-width: 820px) { 
    .feature-grid { 
      grid-template-columns: 1fr; 
    } 
  }

  .feature {
    background: var(--white); 
    border: 1px solid var(--border); 
    border-radius: var(--radius);
    box-shadow: var(--shadow); 
    padding: 28px;
  }

  .feature .icon {
    width: 44px; 
    height: 44px; 
    border-radius: 12px; 
    background: var(--red-lt); 
    color: var(--red);
    display: flex; 
    align-items: center; 
    justify-content: center; 
    margin-bottom: 16px;
  }

  .feature .icon svg { 
    width: 22px; 
    height: 22px; 
  }

  .feature h3 { 
    margin: 0 0 8px; 
    font-size: 16.5px; 
    color: var(--ink); 
  }

  .feature p { 
    margin: 0; 
    color: var(--muted); 
    font-size: 14px; 
    line-height: 1.6; 
  }

  /* Stats */
  .stats-band { 
    background: var(--surface); 
    border-top: 1px solid var(--border); 
    border-bottom: 1px solid var(--border); 
  }

  .stats-grid { 
    display: grid; 
    grid-template-columns: repeat(4, 1fr); 
    gap: 24px; 
    text-align: center; 
  }

  @media (max-width: 760px) { 
    .stats-grid { 
      grid-template-columns: 1fr 1fr; 
    } 
  }

  .stats-grid .num { 
    font-size: 32px; 
    font-weight: 800; 
    color: var(--red); 
  }

  .stats-grid .label {
    font-size: 13.5px; 
    color: var(--muted); 
    margin-top: 4px; 
  }

  /* How it works */
  .steps { 
    display: grid; 
    grid-template-columns: repeat(3, 1fr); 
    gap: 24px; 
  }

  @media (max-width: 820px) { 
    .steps { 
    grid-template-columns: 1fr; 
  } 
}

  .step { 
    text-align: center; 
    padding: 12px; 
  }

  .step .num {
    width: 40px; 
    height: 40px; 
    border-radius: 50%; 
    background: var(--red); 
    color: #fff;
    display: flex; 
    align-items: center; 
    justify-content: center; 
    font-weight: 800; 
    margin: 0 auto 16px;
  }

  .step h3 { 
    margin: 0 0 8px; 
    font-size: 16px; 
    color: var(--ink); 
  }

  .step p { 
    margin: 0; 
    color: var(--muted); 
    font-size: 14px; 
    line-height: 1.6; 
  }

  /* CTA band */
  .cta-band {
    background: var(--red);
    color: #fff;
    text-align: center;
    padding: 64px 32px;
  }

  .cta-band h2 { 
    font-size: 26px; 
    margin: 0 0 10px; 
  }

  .cta-band p { 
    color: var(--red-lt); 
    margin: 0 0 26px; 
    font-size: 15px; 
  }

  .cta-band .cta-row { 
    display: flex; 
    justify-content: center; 
    gap: 14px; 
    flex-wrap: wrap; 
  }

  .cta-band a {
    padding: 12px 24px; 
    border-radius: 10px; 
    font-weight: 700; 
    font-size: 14.5px;
  }

  .cta-band a.solid { 
    background: #fff; 
    color: var(--red); 
  }

  .cta-band a.solid:hover { 
    background: var(--red-lt); 
  }

  .cta-band a.outline { 
    border: 1px solid rgba(255,255,255,.5); 
    color: #fff; 
  }

  .cta-band a.outline:hover { 
    background: rgba(255,255,255,.12); 
  }

  /* Footer */
  footer { 
    background: var(--slate); 
    color: #9aa2b3; 
    padding: 40px 32px 26px; 
  }

  footer .footer-inner { 
    max-width: 1080px; 
    margin: 0 auto; 
  }

  footer .brand { 
    color: #fff; 
    font-weight: 800; 
    font-size: 17px; 
    margin-bottom: 8px; 
    display: flex; 
    align-items: center; 
    gap: 8px; 
  }

  footer p { 
    font-size: 13.5px; 
    line-height: 1.6; 
    max-width: 420px; 
  }

  footer .bottom-line {
    border-top: 1px solid rgba(255,255,255,.08); 
    margin-top: 26px; 
    padding-top: 18px;
    font-size: 12.5px; 
    display: flex; 
    justify-content: space-between; 
    flex-wrap: wrap; 
    gap: 8px;
  }

</style>
</head>
<body>

<div class="topbar">
  <div class="brand">&#129656; HemoLink</div>
  <nav class="links">
    <a href="#why">Why HemoLink</a>
    <a href="#how-it-works">How it works</a>
    <a href="#stats">Impact</a>
  </nav>
  <div class="actions">
    <a class="btn-ghost" href="<?= BASE_URL ?>login.php?as=donor">Donor Login</a>
    <a class="btn-login" href="<?= BASE_URL ?>login.php?as=staff">Staff / Admin Login</a>
  </div>
</div>

<section class="hero">
  <span class="eyebrow">Digital Blood Bank Management</span>
  <h1>Connecting <span>donors</span>, hospitals, and blood banks — in one place.</h1>
  <p class="tagline">HemoLink digitizes blood collection, inventory, and requests so hospitals get the right blood group, on time, every time.</p>
  <div class="cta-row">
    <a class="cta-primary" href="<?= BASE_URL ?>register.php">New Donor? Register</a>
    
  </div>
</section>

<section id="why">
  <div class="section-inner">
    <div class="section-head">
      <span class="kicker">Why HemoLink</span>
      <h2>Built for how blood banks actually work</h2>
      <p>From donor registration to hospital requests, every step is tracked so nothing falls through the cracks.</p>
    </div>
    <div class="feature-grid">
      <div class="feature">
        <div class="icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7.5-4.6-10-9.2C.6 8.2 2.3 4.5 6 4a5.4 5.4 0 0 1 6 3 5.4 5.4 0 0 1 6-3c3.7.5 5.4 4.2 4 7.8C19.5 16.4 12 21 12 21z"/></svg>
        </div>
        <h3>Donor management</h3>
        <p>Eligibility, health status, and donation history are tracked automatically so donors are matched safely and reliably.</p>
      </div>
      <div class="feature">
        <div class="icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7h-9m0 0 3-3m-3 3 3 3M4 17h9m0 0-3 3m3-3-3-3"/></svg>
        </div>
        <h3>Live inventory</h3>
        <p>Stock levels, expiry dates, and components are updated in real time so staff always know what's actually available.</p>
      </div>
      <div class="feature">
        <div class="icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12l2 2 4-4m5 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
        </div>
        <h3>Faster requests</h3>
        <p>Hospitals submit requests with urgency levels, and admins can approve, track, and fulfil them from a single dashboard.</p>
      </div>
    </div>
  </div>
</section>

<div class="stats-band" id="stats">
  <section>
    <div class="section-inner">
      <div class="stats-grid">
        <div><div class="num">8</div><div class="label">Blood groups tracked</div></div>
        <div><div class="num">4</div><div class="label">Partner hospitals</div></div>
        <div><div class="num">4</div><div class="label">Blood banks connected</div></div>
        <div><div class="num">24/7</div><div class="label">Request tracking</div></div>
      </div>
    </div>
  </section>
</div>

<section id="how-it-works">
  <div class="section-inner">
    <div class="section-head">
      <span class="kicker">How it works</span>
      <h2>Three steps from donation to transfusion</h2>
    </div>
    <div class="steps">
      <div class="step">
        <div class="num">1</div>
        <h3>Donate</h3>
        <p>Eligible donors register and give blood, which is logged into the bank's inventory with an expiry date.</p>
      </div>
      <div class="step">
        <div class="num">2</div>
        <h3>Request</h3>
        <p>A hospital submits a request for a patient with a doctor, blood group, and urgency level.</p>
      </div>
      <div class="step">
        <div class="num">3</div>
        <h3>Approve &amp; transfuse</h3>
        <p>Admin staff check inventory, approve the request, and the matching unit is reserved for transfusion.</p>
      </div>
    </div>
  </div>
</section>

<div class="cta-band">
  <h2>Ready to get started?</h2>
  <p>Sign in to donate, request blood, or manage the blood bank.</p>
  <div class="cta-row">
    <a class="solid" href="<?= BASE_URL ?>login.php?as=donor">Donor Login</a>
    <a class="outline" href="<?= BASE_URL ?>login.php?as=staff">Staff / Admin Login</a>
  </div>
</div>

<footer>
  <div class="footer-inner">
    <div class="brand">&#129656; HemoLink</div>
    <p>A Blood Bank Management System project connecting donors, hospitals, and blood banks for a more transparent blood supply.</p>
    <div class="bottom-line">
      <span>HemoLink</span>
      <span><a href="<?= BASE_URL ?>login.php" style="color:#cbd2e0;">Login</a> &middot; <a href="<?= BASE_URL ?>register.php" style="color:#cbd2e0;">Register</a></span>
    </div>
  </div>
</footer>

</body>
</html>