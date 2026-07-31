<?php
declare(strict_types=1);
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: text/html; charset=UTF-8'); 
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

$stmt = $db->prepare("
    SELECT bu.unit_id, bu.component, bu.volume_ml, bu.collection_date, bu.expiry_date,
           bu.status, bb.name AS bank_name
    FROM blood_unit bu
    JOIN blood_bank bb ON bb.bank_id = bu.bank_id
    WHERE bu.donor_id = :donor_id
    ORDER BY bu.collection_date DESC, bu.unit_id DESC
");
$stmt->execute(['donor_id' => $donor['donor_id']]);
$donations = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Donation History — HemoLink</title>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/theme.css">
<style>
  body { margin:0; }
  .main-inner { max-width:820px; }
  .card { padding:30px; }
  h1 { font-size:21px; margin:0 0 6px; }
  .sub { color:var(--muted); font-size:13px; margin-bottom:20px; }
  .badge { background:var(--red); color:#fff; margin-left:8px; }
  .stat-row { display:flex; gap:12px; margin-bottom:22px; }
  .stat { flex:1; background:var(--red-lt); border-radius:var(--radius-sm); padding:14px; text-align:center; }
  .stat b { display:block; font-size:19px; color:var(--red); }
  .stat span { font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.03em; }
  .status-pill { display:inline-block; font-size:.72rem; font-weight:700; padding:3px 10px; border-radius:999px; }
  .status-Available   { background: var(--green-lt); color:#14532d; }
  .status-Reserved    { background: var(--gold-lt);  color:var(--gold); }
  .status-Transfused  { background: var(--blue-lt);  color:var(--blue); }
  .status-Expired      { background: var(--red-lt);   color:var(--red-dk); }
  .status-Discarded    { background: #eee;            color:#666; }
  .empty { text-align:center; padding:36px 12px; color:var(--muted); }
  .empty .icon { font-size:32px; margin-bottom:10px; }
</style>
</head>
<body>
<div class="app-shell">
  <?php include __DIR__ . '/../includes/donor_nav.php'; ?>

  <div class="main">
  <div class="main-inner">
  <div class="card">
    <h1>Donation History <span class="badge"><?= htmlspecialchars($donor['group_name']) ?></span></h1>
    <div class="sub">Every unit you've donated, tracked from collection to use.</div>

    <div class="stat-row">
      <div class="stat"><b><?= (int)$donor['total_donations'] ?></b><span>Total Donations</span></div>
      <div class="stat"><b><?= $donor['last_donation'] ? htmlspecialchars($donor['last_donation']) : '—' ?></b><span>Last Donation</span></div>
    </div>

    <?php if (!$donations): ?>
      <div class="empty">
        <div class="icon">&#129656;</div>
        You haven't made any recorded donations yet.<br>
        Once a blood bank logs a donation under your name, it will show up here.
      </div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Date</th>
            <th>Component</th>
            <th>Volume (ml)</th>
            <th>Blood Bank</th>
            <th>Expiry</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($donations as $d): ?>
            <tr>
              <td><?= htmlspecialchars(date('d M Y', strtotime($d['collection_date']))) ?></td>
              <td><?= htmlspecialchars($d['component']) ?></td>
              <td><?= (int)$d['volume_ml'] ?></td>
              <td><?= htmlspecialchars($d['bank_name']) ?></td>
              <td><?= htmlspecialchars(date('d M Y', strtotime($d['expiry_date']))) ?></td>
              <td><span class="status-pill status-<?= htmlspecialchars($d['status']) ?>"><?= htmlspecialchars($d['status']) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
  </div>
  </div>
</div>
</body>
</html>
