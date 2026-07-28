<?php
declare(strict_types=1);
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: text/html; charset=UTF-8'); // override connect.php's JSON header — this page renders HTML

$user = require_login(['admin', 'staff']);
$db = getDB();

$search = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';

$sql = "
  SELECT
    r.request_id, r.units_needed, r.urgency, r.status, r.request_date, r.notes,
    p.name AS patient_name,
    h.name AS hospital_name,
    d.name AS doctor_name,
    bg.group_name,
    r.component
  FROM blood_request r
  JOIN patient p       ON p.patient_id = r.patient_id
  JOIN hospital h       ON h.hospital_id = p.hospital_id
  JOIN doctor d         ON d.doctor_id = r.doctor_id
  JOIN blood_groups bg  ON bg.group_id = r.group_id
  WHERE 1=1
";
$params = [];

if ($search !== '') {
    $sql .= " AND (p.name ILIKE :q OR r.request_id = :rid)";
    $params[':q']   = "%$search%";
    $params[':rid'] = is_numeric($search) ? (int)$search : -1;
}
if ($statusFilter !== '' && in_array($statusFilter, ['Pending','Processing','Fulfilled','Cancelled'], true)) {
    $sql .= " AND r.status = :status";
    $params[':status'] = $statusFilter;
}
$sql .= " ORDER BY r.request_date DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

function badgeClass(string $status): string {
    return match ($status) {
        'Pending'    => 'st-pending',
        'Processing' => 'st-processing',
        'Fulfilled'  => 'st-fulfilled',
        'Cancelled'  => 'st-cancelled',
        default      => '',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Request Status — HemoLink</title>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/theme.css">
<style>
  body { 
    margin: 0; 
  }

  .main-inner { 
    max-width: 1080px; 
  }

  .card { 
    padding: 28px; 
  }

  h1 { 
    font-size: 20px; 
    margin: 0 0 20px; 
  }

  form.filters { 
    display: flex; 
    gap: 10px; 
    margin-bottom: 18px; 
  }

  form.filters input, form.filters select { 
    padding: 9px; 
  }

  form.filters button { 
    padding: 9px 18px; 
    background: var(--red); 
    color: #fff; 
    border: none; 
  }

  form.filters button:hover { 
    background: var(--red-dk); 
  }

  th, td { 
    padding: 10px 8px; 
    font-size: .85rem; 
  }

  .badge { 
    padding: 3px 10px; 
  }

  .st-pending    { 
    background: var(--gold-lt); 
    color: var(--gold);
  }

  .st-processing { 
    background: var(--blue-lt); 
    color: var(--blue); 
  }

  .st-fulfilled  { 
    background: var(--green-lt); 
    color: var(--green); 
  }

  .st-cancelled  { 
    background: var(--red-lt); 
    color: var(--red-dk); 
  }

  .urg-Critical { 
    color: var(--red); 
    font-weight: 700; 
  }

  .urg-High     { 
    color: var(--amber); 
    font-weight: 700; 
  }

</style>
</head>
<body>
<div class="app-shell">
  <?php include __DIR__ . '/../includes/staff_nav.php'; ?>
  <div class="main">
  <div class="main-inner">
  <div class="card">
  <h1>Blood Request Status</h1>

<form class="filters" method="GET">
  <input type="text" name="q" placeholder="Search by patient name or request ID" value="<?= htmlspecialchars($search) ?>">
  <select name="status">
    <option value="">All statuses</option>
    <?php foreach (['Pending','Processing','Fulfilled','Cancelled'] as $s): ?>
      <option value="<?= $s ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= $s ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit">Filter</button>
</form>

<table>
  <thead>
    <tr>
      <th>ID</th><th>Patient</th><th>Hospital</th><th>Doctor</th>
      <th>Group</th><th>Component</th><th>Units</th><th>Urgency</th><th>Status</th><th>Requested</th>
    </tr>
  </thead>
  <tbody>
    <?php if (!$requests): ?>
      <tr><td colspan="10">No requests found.</td></tr>
    <?php endif; ?>
    <?php foreach ($requests as $r): ?>
      <tr>
        <td>#<?= (int)$r['request_id'] ?></td>
        <td><?= htmlspecialchars($r['patient_name']) ?></td>
        <td><?= htmlspecialchars($r['hospital_name']) ?></td>
        <td><?= htmlspecialchars($r['doctor_name']) ?></td>
        <td><?= htmlspecialchars($r['group_name']) ?></td>
        <td><?= htmlspecialchars($r['component']) ?></td>
        <td><?= (int)$r['units_needed'] ?></td>
        <td class="urg-<?= htmlspecialchars($r['urgency']) ?>"><?= htmlspecialchars($r['urgency']) ?></td>
        <td><span class="badge <?= badgeClass($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
        <td><?= htmlspecialchars(date('d M Y, H:i', strtotime($r['request_date']))) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<p style="margin-top:20px;"><a href="request_form.php" style="color:var(--red);">&larr; Submit a new request</a></p>
  </div>
  </div>
  </div>
</div>
</body>
</html>

