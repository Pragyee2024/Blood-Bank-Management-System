<?php
declare(strict_types=1);
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/includes/auth.php';
header('Content-Type: text/html; charset=UTF-8'); // override connect.php's JSON header — this page renders HTML

$error = '';
$as = $_GET['as'] ?? ($_POST['as'] ?? '');
$as = in_array($as, ['staff', 'donor'], true) ? $as : '';

if ($u = current_user()) {
    header('Location: ' . role_home($u['role']));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $db = getDB();
        $stmt = $db->prepare('SELECT user_id, username, password_hash, role, donor_id FROM app_user WHERE username = :username');
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']  = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];
            $_SESSION['donor_id'] = $user['donor_id'];

            header('Location: ' . role_home($user['role']));
            exit;
        }

        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login — HemoLink</title>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/theme.css">
<style>
    body { margin: 0; }
    .back { display:block; font-size:12px; color:var(--muted); text-decoration:none; margin-bottom:18px; }
    .back:hover { color: var(--red); }
    h1 { font-size:20px; margin:0 0 22px; color: var(--ink); }
    label { display:block; font-size:13px; margin:12px 0 4px; color:#555; }
    input, select { width:100%; padding:10px; box-sizing:border-box; }
    .pw-wrap { position:relative; }
    .pw-wrap input { padding-right:40px; }
    .pw-toggle { position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; width:auto; margin:0; padding:4px; cursor:pointer; display:flex; align-items:center; color:#888; }
    .pw-toggle:hover { background:none; color:var(--red); }
    .pw-toggle svg { width:18px; height:18px; }
    button { width:100%; margin-top:20px; padding:11px; border:none; background:var(--red); color:#fff; }
    button:hover { background:var(--red-dk); }
    p.link { text-align:center; margin-top:16px; font-size:13px; color:#555; }
    .row { display:flex; gap:10px; }
    .row > div { flex:1; }
</style>
</head>
<body>
<div class="auth-shell">
<div class="auth-card">
  <div class="side">
    <div class="brand">&#129656; HemoLink</div>
    <?php if ($as === 'donor'): ?>
      <h2>Welcome back, donor.</h2>
      <p>Sign in to update your profile and see your donation history — every unit you give is tracked here.</p>
    <?php elseif ($as === 'staff'): ?>
      <h2>Staff &amp; admin access.</h2>
      <p>Sign in to review requests, manage approvals, and keep the inventory dashboard up to date.</p>
    <?php else: ?>
      <h2>Every donation counts.</h2>
      <p>Sign in to the Blood Bank Management System to continue.</p>
    <?php endif; ?>
  </div>

  <div class="form-side">
  <div class="inner">
  <a class="back" href="<?= BASE_URL ?>index.php">&larr; Back to home</a>
  <h1>
    <?php if ($as === 'donor'): ?>Donor Login
    <?php elseif ($as === 'staff'): ?>Staff / Admin Login
    <?php else: ?>Login
    <?php endif; ?>
  </h1>

  <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if (isset($_GET['registered'])): ?><p class="info">Registration successful. Please log in.</p><?php endif; ?>

  <form method="post">
    <?php if ($as): ?><input type="hidden" name="as" value="<?= htmlspecialchars($as) ?>"><?php endif; ?>
    <label for="username">Username</label>
    <input type="text" id="username" name="username" required autofocus>

    <label for="password">Password</label>
    <div class="pw-wrap">
      <input type="password" id="password" name="password" required>
      <button type="button" class="pw-toggle" onclick="togglePw('password', this)" aria-label="Show password">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
      </button>
    </div>

    <button type="submit">Log In</button>
  </form>

  <?php if ($as !== 'staff'): ?>
    <p class="link">New donor? <a href="register.php">Register here</a></p>
  <?php endif; ?>
  </div>
  </div>
</div>
</div>
<script>
function togglePw(inputId, btn) {
  const input = document.getElementById(inputId);
  const showing = input.type === 'text';
  input.type = showing ? 'password' : 'text';
  btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
  btn.innerHTML = showing
    ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>'
    : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 19c-7 0-11-7-11-7a21.8 21.8 0 0 1 5.06-6.06M9.9 4.24A10.4 10.4 0 0 1 12 4c7 0 11 7 11 7a21.8 21.8 0 0 1-2.61 3.66M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
}
</script>
</body>
</html>