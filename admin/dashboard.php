<?php
declare(strict_types=1);
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: text/html; charset=UTF-8'); // override connect.php's JSON header — this page renders HTML

$user = require_login(['admin', 'staff']);
$db = getDB();

// Requests by status
$reqByStatus = $db->query("
    SELECT status, COUNT(*) AS total
    FROM blood_request
    GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);

$statuses = ['Pending' => 0, 'Processing' => 0, 'Fulfilled' => 0, 'Cancelled' => 0];
foreach ($reqByStatus as $status => $count) $statuses[$status] = (int)$count;

// Pending critical/high urgency requests needing attention
$urgentPending = $db->query("
    SELECT COUNT(*) FROM blood_request
    WHERE status = 'Pending' AND urgency IN ('High','Critical')
")->fetchColumn();

// Inventory: available units by blood group
$inventory = $db->query("
    SELECT bg.group_name, COUNT(*) AS available_units, COALESCE(SUM(bu.volume_ml),0) AS total_ml
    FROM blood_unit bu
    JOIN blood_groups bg ON bg.group_id = bu.group_id
    WHERE bu.status = 'Available'
    GROUP BY bg.group_name
    ORDER BY bg.group_name
")->fetchAll();

// Totals
$totalDonors  = $db->query("SELECT COUNT(*) FROM donor")->fetchColumn();
$totalUnits   = $db->query("SELECT COUNT(*) FROM blood_unit WHERE status='Available'")->fetchColumn();
$totalRequests = array_sum($statuses);
$expiringSoon = $db->query("
    SELECT COUNT(*) FROM blood_unit
    WHERE status = 'Available' AND expiry_date BETWEEN CURRENT_DATE AND (CURRENT_DATE + INTERVAL '7 days')
")->fetchColumn();

// Recent pending requests for quick action (Postgres: CASE instead of MySQL's FIELD())
$recentPending = $db->query("
    SELECT r.request_id, p.name AS patient_name, bg.group_name, r.units_needed, r.urgency, r.request_date
    FROM blood_request r
    JOIN patient p ON p.patient_id = r.patient_id
    JOIN blood_groups bg ON bg.group_id = r.group_id
    WHERE r.status = 'Pending'
    ORDER BY
      CASE r.urgency
        WHEN 'Critical' THEN 1
        WHEN 'High'     THEN 2
        WHEN 'Medium'   THEN 3
        WHEN 'Low'      THEN 4
      END,
      r.request_date ASC
    LIMIT 10
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard — Blood Bank Management System</title>
<style>
  body { font-family: Arial, sans-serif; background:#ffffff; color:#222; margin:0; padding:24px; }
  .wrap { max-width: 1000px; margin: 0 auto; }
  nav { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; font-size:14px; }
  nav a { color:#c0392b; text-decoration:none; margin-left:14px; }
  h1 { font-size: 20px; margin: 0 0 20px; color:#222; }
  .cards { display: flex; gap: 14px; flex-wrap: wrap; margin: 20px 0; }
  .stat-card { flex: 1; min-width: 150px; background: #fdecea; border-radius: 8px; padding: 16px; text-align: center; }
  .stat-card .num { font-size: 1.8rem; font-weight: bold; color: #c0392b; }
  .stat-card .label { font-size: .8rem; color: #555; margin-top: 4px; }
  .warn .num { color: #e65100; }
  .card { background:#fff; padding:24px; border-radius:10px; border:2px solid #c0392b; margin-top: 20px; }
  table { width: 100%; border-collapse: collapse; margin-top: 10px; }
  th, td { border-bottom: 1px solid #f0d9d5; padding: 8px; text-align: left; font-size: .85rem; }
  th { color:#777; font-weight:normal; }
  h2 { font-size: 1rem; margin: 0 0 6px; color:#222; }
  a.btn { padding: 4px 10px; background: #c0392b; color: #fff; text-decoration: none; border-radius: 6px; font-size: .8rem; }
  a.btn:hover { background: #a5281c; }
</style>
</head>
<body>
<div class="wrap">
  <?php include __DIR__ . '/../includes/staff_nav.php'; ?>
  <h1>Admin Dashboard</h1>

<div class="cards">
  <div class="stat-card"><div class="num"><?= (int)$totalDonors ?></div><div class="label">Registered Donors</div></div>
  <div class="stat-card"><div class="num"><?= (int)$totalUnits ?></div><div class="label">Available Blood Units</div></div>
  <div class="stat-card"><div class="num"><?= (int)$totalRequests ?></div><div class="label">Total Requests</div></div>
  <div class="stat-card <?= $urgentPending > 0 ? 'warn' : '' ?>"><div class="num"><?= (int)$urgentPending ?></div><div class="label">Urgent Pending Requests</div></div>
  <div class="stat-card <?= $expiringSoon > 0 ? 'warn' : '' ?>"><div class="num"><?= (int)$expiringSoon ?></div><div class="label">Units Expiring in 7 Days</div></div>
</div>

<div class="card">
<h2>Requests by Status</h2>
<table>
  <tr><th>Pending</th><th>Processing</th><th>Fulfilled</th><th>Cancelled</th></tr>
  <tr>
    <td><?= $statuses['Pending'] ?></td>
    <td><?= $statuses['Processing'] ?></td>
    <td><?= $statuses['Fulfilled'] ?></td>
    <td><?= $statuses['Cancelled'] ?></td>
  </tr>
</table>
</div>

<div class="card">
<h2>Available Inventory by Blood Group</h2>
<table>
  <thead><tr><th>Group</th><th>Available Units</th><th>Total Volume (ml)</th></tr></thead>
  <tbody>
    <?php if (!$inventory): ?>
      <tr><td colspan="3">No inventory data yet.</td></tr>
    <?php endif; ?>
    <?php foreach ($inventory as $row): ?>
      <tr>
        <td><?= htmlspecialchars($row['group_name']) ?></td>
        <td><?= (int)$row['available_units'] ?></td>
        <td><?= (int)$row['total_ml'] ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

<div class="card">
<h2>Pending Requests (needs review)</h2>
<table>
  <thead><tr><th>ID</th><th>Patient</th><th>Group</th><th>Units</th><th>Urgency</th><th>Requested</th><th></th></tr></thead>
  <tbody>
    <?php if (!$recentPending): ?>
      <tr><td colspan="7">No pending requests.</td></tr>
    <?php endif; ?>
    <?php foreach ($recentPending as $r): ?>
      <tr>
        <td>#<?= (int)$r['request_id'] ?></td>
        <td><?= htmlspecialchars($r['patient_name']) ?></td>
        <td><?= htmlspecialchars($r['group_name']) ?></td>
        <td><?= (int)$r['units_needed'] ?></td>
        <td><?= htmlspecialchars($r['urgency']) ?></td>
        <td><?= htmlspecialchars(date('d M Y, H:i', strtotime($r['request_date']))) ?></td>
        <td><a class="btn" href="approve.php?request_id=<?= (int)$r['request_id'] ?>">Review</a></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

</div>
</body>
</html>
