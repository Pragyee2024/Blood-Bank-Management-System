<?php
declare(strict_types=1);
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: text/html; charset=UTF-8'); 

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
<title>Admin Dashboard — HemoLink</title>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/theme.css">
<style>
  body { 
    margin: 0; 
  }

  .main-inner { 
    max-width: 1080px; 
  }

  h1 { 
    font-size: 21px; 
    margin: 0 0 20px; 
  }

  .cards { 
    display: flex; 
    gap: 14px; 
    flex-wrap: wrap; 
    margin: 20px 0; 
  }

  .stat-card { 
    flex: 1; 
    min-width: 160px; 
    background: var(--red-lt); 
    border-radius: var(--radius); 
    padding: 18px; 
    text-align: center; 
  }

  .stat-card .num { 
    font-size: 1.8rem; 
    font-weight: 800; 
    color: var(--red); 
  }

  .stat-card .label 
  {
    font-size: .78rem; 
    color: var(--muted); 
    margin-top: 4px; 
    text-transform: uppercase; 
    letter-spacing: .03em; 
  }
  .warn 
  { 
    background: var(--amber-lt); 
  }

  .warn .num { 
    color: var(--amber); 
  }
  .card { 
    padding: 24px; 
    margin-top: 20px; 
  }
  table { 
    margin-top: 10px; 
  }
  th, td { 
    padding: 10px 8px; 
    font-size: .85rem; 
  }
  h2 { 
    font-size: 1rem; 
    margin: 0 0 10px; 
  }
  a.btn { 
    padding: 5px 12px; 
    background: var(--red); 
    color: #fff; 
    text-decoration: none; 
    font-size: .8rem; 
    display: inline-block; 
  }
  a.btn:hover { 
    background: var(--red-dk); 
    }
</style>
</head>
<body>
<div class="app-shell">
  <?php include __DIR__ . '/../includes/staff_nav.php'; ?>
  <div class="main">
  <div class="main-inner">
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
  </div>
</div>
</body>
</html>
