<?php
declare(strict_types=1);
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/includes/auth.php';
header('Content-Type: text/html; charset=UTF-8'); 
$db = getDB();
$groups = $db->query('SELECT group_id, group_name FROM blood_groups ORDER BY group_name')->fetchAll();

$errors = [];
$old = ['name'=>'','dob'=>'','gender'=>'Male','group_id'=>'','phone'=>'','email'=>'','address'=>'','username'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['name']     = trim($_POST['name'] ?? '');
    $old['dob']      = $_POST['dob'] ?? '';
    $old['gender']   = $_POST['gender'] ?? 'Male';
    $old['group_id'] = $_POST['group_id'] ?? '';
    $old['phone']    = trim($_POST['phone'] ?? '');
    $old['email']    = trim($_POST['email'] ?? '');
    $old['address']  = trim($_POST['address'] ?? '');
    $old['username'] = trim($_POST['username'] ?? '');
    $password        = $_POST['password'] ?? '';
    $confirm         = $_POST['confirm_password'] ?? '';

    if ($old['name'] === '')      $errors[] = 'Name is required.';
    if ($old['phone'] === '')     $errors[] = 'Phone is required.';
    if ($old['group_id'] === '')  $errors[] = 'Blood group is required.';
    if ($old['username'] === '')  $errors[] = 'Username is required.';
    if (strlen($password) < 6)    $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm)   $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $check = $db->prepare('SELECT 1 FROM app_user WHERE username = :u');
        $check->execute(['u' => $old['username']]);
        if ($check->fetch()) {
            $errors[] = 'That username is already taken.';
        }
    }

    if (!$errors) {
        try {
            $db->beginTransaction();

            $insDonor = $db->prepare(
                'INSERT INTO donor (name, dob, gender, group_id, phone, email, address)
                 VALUES (:name, :dob, :gender, :group_id, :phone, :email, :address)
                 RETURNING donor_id'
            );
            $insDonor->execute([
                'name'     => $old['name'],
                'dob'      => $old['dob'] ?: null,
                'gender'   => $old['gender'],
                'group_id' => $old['group_id'],
                'phone'    => $old['phone'],
                'email'    => $old['email'] ?: null,
                'address'  => $old['address'] ?: null,
            ]);
            $donor_id = $insDonor->fetchColumn();

            $insUser = $db->prepare(
                "INSERT INTO app_user (username, password_hash, role, donor_id)
                 VALUES (:username, :hash, 'donor', :donor_id)"
            );
            $insUser->execute([
                'username'  => $old['username'],
                'hash'      => password_hash($password, PASSWORD_DEFAULT),
                'donor_id'  => $donor_id,
            ]);

            $db->commit();
            header('Location: login.php?registered=1');
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Register — HemoLink</title>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/theme.css">
<style>
  body {
    display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; padding:24px 0;
    background: radial-gradient(circle at 15% 15%, #2a3140 0%, var(--slate) 45%, #14171f 100%);
  }
  .card { padding:32px; width:360px; box-shadow: 0 24px 60px -20px rgba(0,0,0,.45); }
  .back { display:block; text-align:center; font-size:12px; color:var(--muted); text-decoration:none; margin-bottom:16px; }
  .back:hover { color: var(--red); }
  h1 { font-size:20px; margin-bottom:20px; text-align:center; color: var(--ink); }
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
<div class="card">
  <a class="back" href="<?= BASE_URL ?>index.php">&larr; Back to home</a>
  <h1>&#129656; Donor Registration</h1>

  <?php if ($errors): ?>
    <div class="error"><ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <form method="post">
    <label for="name">Full Name</label>
    <input type="text" id="name" name="name" value="<?= htmlspecialchars($old['name']) ?>" required>

    <div class="row">
      <div>
        <label for="dob">Date of Birth</label>
        <input type="date" id="dob" name="dob" value="<?= htmlspecialchars($old['dob']) ?>">
      </div>
      <div>
        <label for="gender">Gender</label>
        <select id="gender" name="gender">
          <?php foreach (['Male','Female','Other'] as $g): ?>
            <option value="<?= $g ?>" <?= $old['gender'] === $g ? 'selected' : '' ?>><?= $g ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <label for="group_id">Blood Group</label>
    <select id="group_id" name="group_id" required>
      <option value="">-- Select --</option>
      <?php foreach ($groups as $g): ?>
        <option value="<?= $g['group_id'] ?>" <?= (string)$old['group_id'] === (string)$g['group_id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['group_name']) ?></option>
      <?php endforeach; ?>
    </select>

    <label for="phone">Phone</label>
    <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($old['phone']) ?>" required>

    <label for="email">Email</label>
    <input type="email" id="email" name="email" value="<?= htmlspecialchars($old['email']) ?>">

    <label for="address">Address</label>
    <input type="text" id="address" name="address" value="<?= htmlspecialchars($old['address']) ?>">

    <label for="username">Choose a Username</label>
    <input type="text" id="username" name="username" value="<?= htmlspecialchars($old['username']) ?>" required>

    <label for="password">Password</label>
    <div class="pw-wrap">
      <input type="password" id="password" name="password" required>
      <button type="button" class="pw-toggle" onclick="togglePw('password', this)" aria-label="Show password">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
      </button>
    </div>

    <label for="confirm_password">Confirm Password</label>
    <div class="pw-wrap">
      <input type="password" id="confirm_password" name="confirm_password" required>
      <button type="button" class="pw-toggle" onclick="togglePw('confirm_password', this)" aria-label="Show password">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
      </button>
    </div>

    <button type="submit">Register</button>
  </form>

  <p class="link">Already have an account? <a href="login.php">Log in</a></p>
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