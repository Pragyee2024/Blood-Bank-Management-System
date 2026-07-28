<?php
declare(strict_types=1);
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: text/html; charset=UTF-8'); // override connect.php's JSON header — this page renders HTML

$user = require_login(['donor']);
$db = getDB();

function fetchDonor(PDO $db, int $donor_id): ?array {
    $stmt = $db->prepare(
        'SELECT d.*, bg.group_name
         FROM donor d JOIN blood_groups bg ON bg.group_id = d.group_id
         WHERE d.donor_id = :id'
    );
    $stmt->execute(['id' => $donor_id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

$donor = fetchDonor($db, (int)$user['donor_id']);
if (!$donor) { err('Donor record not found.', 404); }

$message = '';
$errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $gender  = $_POST['gender'] ?? $donor['gender'];
    $dob     = $_POST['dob'] ?? $donor['dob'];

    if ($name === '')  $errors[] = 'Name is required.';
    if ($phone === '') $errors[] = 'Phone is required.';

    if (!$errors) {
        $upd = $db->prepare(
            'UPDATE donor
             SET name = :name, phone = :phone, email = :email,
                 address = :address, gender = :gender, dob = :dob
             WHERE donor_id = :id'
        );
        $upd->execute([
            'name'    => $name,
            'phone'   => $phone,
            'email'   => $email ?: null,
            'address' => $address ?: null,
            'gender'  => $gender,
            'dob'     => $dob ?: null,
            'id'      => $donor['donor_id'],
        ]);
        $message = 'Profile updated successfully.';
        $donor = fetchDonor($db, (int)$user['donor_id']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Profile — HemoLink</title>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/theme.css">
<style>
  body { margin:0; }
  .main-inner { max-width:560px; }
  .card { padding:30px; }
  h1 { font-size:21px; margin:0 0 6px; }
  .sub { color:var(--muted); font-size:13px; margin-bottom:20px; }
  .badge { background:var(--red); color:#fff; margin-left:8px; }
  .stat-row { display:flex; gap:12px; margin-bottom:22px; }
  .stat { flex:1; background:var(--red-lt); border-radius:var(--radius-sm); padding:14px; text-align:center; }
  .stat b { display:block; font-size:19px; color:var(--red); }
  .stat span { font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.03em; }
  label { margin:12px 0 4px; }
  input, select { width:100%; padding:10px; box-sizing:border-box; }
  input[readonly] { color:#9aa0aa; background:#f7f7f7; }
  button { padding:11px 22px; border:none; background:var(--red); color:#fff; }
  button:hover { background:var(--red-dk); }
  .row { display:flex; gap:10px; }
  .row > div { flex:1; }
  .msg { margin-bottom:16px; background:var(--green-lt); color:#14532d; border:1px solid #bfe3cd; padding:11px 14px; border-radius:var(--radius-sm); font-size:.88rem; }
  .error { margin-bottom:16px; background:var(--red-lt); color:var(--red-dk); padding:11px 14px; border-radius:var(--radius-sm); font-size:.88rem; }
  .error ul { margin:4px 0 0 18px; padding:0; }
</style>
</head>
<body>
<div class="app-shell">
  <?php include __DIR__ . '/../includes/donor_nav.php'; ?>

  <div class="main">
  <div class="main-inner">
  <div class="card">
    <h1>Welcome, <?= htmlspecialchars($donor['name']) ?> <span class="badge"><?= htmlspecialchars($donor['group_name']) ?></span></h1>
    <div class="sub">Eligibility: <?= $donor['is_eligible'] ? 'Eligible to donate' : 'Not currently eligible' ?> · <?= htmlspecialchars($donor['health_status']) ?></div>

    <div class="stat-row">
      <div class="stat"><b><?= (int)$donor['total_donations'] ?></b><span>Total Donations</span></div>
      <div class="stat"><b><?= $donor['last_donation'] ? htmlspecialchars($donor['last_donation']) : '—' ?></b><span>Last Donation</span></div>
    </div>

    <?php if ($message): ?><div class="msg"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($errors): ?>
      <div class="error"><ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <form method="post">
      <label for="name">Full Name</label>
      <input type="text" id="name" name="name" value="<?= htmlspecialchars($donor['name']) ?>" required>

      <div class="row">
        <div>
          <label for="dob">Date of Birth</label>
          <input type="date" id="dob" name="dob" value="<?= htmlspecialchars((string)$donor['dob']) ?>">
        </div>
        <div>
          <label for="gender">Gender</label>
          <select id="gender" name="gender">
            <?php foreach (['Male','Female','Other'] as $g): ?>
              <option value="<?= $g ?>" <?= $donor['gender'] === $g ? 'selected' : '' ?>><?= $g ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <label for="phone">Phone</label>
      <input type="text" id="phone" name="phone" value="<?= htmlspecialchars($donor['phone']) ?>" required>

      <label for="email">Email</label>
      <input type="email" id="email" name="email" value="<?= htmlspecialchars((string)$donor['email']) ?>">

      <label for="address">Address</label>
      <input type="text" id="address" name="address" value="<?= htmlspecialchars((string)$donor['address']) ?>">

      <label>Blood Group (contact admin to correct)</label>
      <input type="text" value="<?= htmlspecialchars($donor['group_name']) ?>" readonly>

      <button type="submit">Save Changes</button>
    </form>
  </div>
  </div>
  </div>
</div>
</body>
</html>