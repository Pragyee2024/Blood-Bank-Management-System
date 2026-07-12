<?php
require_once __DIR__ . '/../page_db.php';
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
<title>Request Status</title>
<style>
  body { font-family: Arial, sans-serif; max-width: 900px; margin: 40px auto; padding: 0 16px; color: #222; }
  h1 { font-size: 1.4rem; }
  form.filters { display: flex; gap: 10px; margin-bottom: 16px; }
  form.filters input, form.filters select { padding: 6px; }
  table { width: 100%; border-collapse: collapse; }
  th, td { border-bottom: 1px solid #ddd; padding: 8px; text-align: left; font-size: .9rem; }
  th { background: #f4f4f4; }
  .badge { padding: 3px 8px; border-radius: 12px; font-size: .75rem; font-weight: bold; }
  .st-pending    { background: #fff3cd; color: #7a5c00; }
  .st-processing { background: #cfe2ff; color: #084298; }
  .st-fulfilled  { background: #d1e7dd; color: #0a5c36; }
  .st-cancelled  { background: #f8d7da; color: #842029; }
  .urg-Critical { color: #d93025; font-weight: bold; }
  .urg-High     { color: #e65100; font-weight: bold; }
</style>
</head>
<body>
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

<p style="margin-top:20px;"><a href="request_form.php">&larr; Submit a new request</a></p>
</body>
</html>
