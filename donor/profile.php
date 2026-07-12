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
<title>My Profile — Blood Bank Management System</title>
<style>
  body { font-family: Arial, sans-serif; background:#ffffff; color:#222; margin:0; padding:24px; }
  .wrap { max-width:520px; margin:0 auto; }
  nav { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; font-size:14px; }
  nav a { color:#c0392b; text-decoration:none; margin-left:14px; }
  .card { background:#fff; padding:28px; border-radius:10px; border:2px solid #c0392b; }
  h1 { font-size:20px; margin:0 0 6px; color:#222; }
  .sub { color:#777; font-size:13px; margin-bottom:20px; }
  .badge { display:inline-block; background:#c0392b; color:#fff; padding:3px 10px; border-radius:20px; font-size:12px; margin-left:8px; }
  .stat-row { display:flex; gap:12px; margin-bottom:20px; }
  .stat { flex:1; background:#fdecea; border-radius:8px; padding:12px; text-align:center; }
  .stat b { display:block; font-size:18px; color:#c0392b; }
  .stat span { font-size:11px; color:#777; }
  label { display:block; font-size:13px; margin:12px 0 4px; color:#555; }
  input, select { width:100%; padding:10px; border-radius:6px; border:1px solid #ddd; background:#fff; color:#222; box-sizing:border-box; }
  input[readonly] { color:#999; background:#f7f7f7; }
  button { margin-top:20px; padding:10px 20px; border:none; border-radius:6px; background:#c0392b; color:#fff; font-weight:bold; cursor:pointer; }
  button:hover { background:#a5281c; }
  .row { display:flex; gap:10px; }
  .row > div { flex:1; }
  .msg { background:#fdecea; color:#c0392b; padding:10px; border-radius:6px; font-size:13px; margin-bottom:16px; border:1px solid #f5c6c1; }
  .error { background:#fdecea; color:#c0392b; padding:10px; border-radius:6px; font-size:13px; margin-bottom:16px; }
  .error ul { margin:4px 0 0 18px; padding:0; }
</style>
</head>
<body>
<div class="wrap">
  <nav>
    <div><strong>🩸 Blood Bank</strong></div>
    <div>
      <a href="profile.php">Profile</a>
      <a href="history.php">Donation History</a>
      <a href="../logout.php">Logout</a>
    </div>
  </nav>

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
</body>
</html>
