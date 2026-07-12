<?php
declare(strict_types=1);
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: text/html; charset=UTF-8'); // override connect.php's JSON header — this page renders HTML

$user = require_login(['donor']);
$db = getDB();

$stmt = $db->prepare(
    'SELECT bu.unit_id, bu.component, bu.volume_ml, bu.collection_date,
            bu.expiry_date, bu.status, bb.name AS bank_name, bg.group_name
     FROM blood_unit bu
     JOIN blood_bank bb   ON bb.bank_id = bu.bank_id
     JOIN blood_groups bg ON bg.group_id = bu.group_id
     WHERE bu.donor_id = :id
     ORDER BY bu.collection_date DESC'
);
$stmt->execute(['id' => $user['donor_id']]);
$history = $stmt->fetchAll();

$statusColor = [
    'Available'  => '#2e7d32',
    'Reserved'   => '#f9a825',
    'Transfused' => '#1565c0',
    'Expired'    => '#616161',
    'Discarded'  => '#c62828',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Donation History — Blood Bank Management System</title>
<style>
  body { font-family: Arial, sans-serif; background:#ffffff; color:#222; margin:0; padding:24px; }
  .wrap { max-width:760px; margin:0 auto; }
  nav { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; font-size:14px; }
  nav a { color:#c0392b; text-decoration:none; margin-left:14px; }
  .card { background:#fff; padding:28px; border-radius:10px; border:2px solid #c0392b; }
  h1 { font-size:20px; margin:0 0 20px; color:#222; }
  table { width:100%; border-collapse:collapse; font-size:13px; }
  th, td { text-align:left; padding:10px 8px; border-bottom:1px solid #f0d9d5; }
  th { color:#777; font-weight:normal; }
  .pill { padding:3px 10px; border-radius:20px; font-size:11px; color:#fff; }
  .empty { text-align:center; color:#999; padding:30px 0; }
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
    <h1>My Donation History</h1>

    <?php if (!$history): ?>
      <div class="empty">No donations recorded yet.</div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Date</th>
            <th>Component</th>
            <th>Group</th>
            <th>Volume</th>
            <th>Bank</th>
            <th>Expiry</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($history as $h): ?>
          <tr>
            <td><?= htmlspecialchars($h['collection_date']) ?></td>
            <td><?= htmlspecialchars($h['component']) ?></td>
            <td><?= htmlspecialchars($h['group_name']) ?></td>
            <td><?= (int)$h['volume_ml'] ?> ml</td>
            <td><?= htmlspecialchars($h['bank_name']) ?></td>
            <td><?= htmlspecialchars($h['expiry_date']) ?></td>
            <td><span class="pill" style="background:<?= $statusColor[$h['status']] ?? '#555' ?>"><?= htmlspecialchars($h['status']) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
