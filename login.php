<?php
declare(strict_types=1);
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/includes/auth.php';
header('Content-Type: text/html; charset=UTF-8'); // override connect.php's JSON header — this page renders HTML

$error = '';

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
<title>Login — Blood Bank Management System</title>
<style>
    body { font-family: Arial, sans-serif; background:#ffffff; color:#222; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; padding:24px 0; }
    .card { background:#ffffff; padding:32px; border-radius:10px; width:340px; border:2px solid #c0392b; box-shadow:0 4px 16px rgba(192,57,43,0.15); }
    h1 { font-size:20px; margin-bottom:20px; text-align:center; color:#c0392b; }
    label { display:block; font-size:13px; margin:12px 0 4px; color:#555; }
    input, select { width:100%; padding:10px; border-radius:6px; border:1px solid #ddd; background:#fff; color:#222; box-sizing:border-box; }
    input:focus, select:focus { outline:none; border-color:#c0392b; }
    .pw-wrap { position:relative; }
    .pw-wrap input { padding-right:40px; }
    .pw-toggle { position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; width:auto; margin:0; padding:4px; cursor:pointer; display:flex; align-items:center; color:#888; }
    .pw-toggle:hover { background:none; color:#c0392b; }
    .pw-toggle svg { width:18px; height:18px; }
    button { width:100%; margin-top:20px; padding:10px; border:none; border-radius:6px; background:#c0392b; color:#fff; font-weight:bold; cursor:pointer; }
    button:hover { background:#a5281c; }
    .error { background:#fdecea; color:#c0392b; padding:10px; border-radius:6px; font-size:13px; margin-top:12px; border:1px solid #f5c6c1; }
    .error ul { margin:4px 0 0 18px; padding:0; }
    .info { background:#fdecea; color:#c0392b; padding:10px; border-radius:6px; font-size:13px; margin-top:12px; }
    p.link { text-align:center; margin-top:16px; font-size:13px; color:#555; }
    a { color:#c0392b; }
    .row { display:flex; gap:10px; }
    .row > div { flex:1; }
</style>
</head>
<body>
<div class="card">
  <h1>🩸 Blood Bank — Login</h1>

  <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if (isset($_GET['registered'])): ?><div class="info">Registration successful. Please log in.</div><?php endif; ?>

  <form method="post">
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

  <p class="link">Donor? <a href="register.php">Register here</a></p>
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