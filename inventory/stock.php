<?php
declare(strict_types=1);
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: text/html; charset=UTF-8');

$user = require_login(['admin', 'staff']);
$db = getDB();

// ── GET OPTION LISTS ───────────────────────────────────────────
$bloodGroups = $db->query('SELECT group_id, group_name FROM blood_groups ORDER BY group_name')->fetchAll();
$bloodBanks  = $db->query('SELECT bank_id, name FROM blood_bank ORDER BY name')->fetchAll();

// ── AGGREGATE CALCULATIONS ────────────────────────────────────
// Total Available Units
$totalAvailable = (int)$db->query("SELECT COUNT(*) FROM blood_unit WHERE status = 'Available'")->fetchColumn();

// Total Volume Available
$totalVolume = (int)$db->query("SELECT COALESCE(SUM(volume_ml), 0) FROM blood_unit WHERE status = 'Available'")->fetchColumn();

// Group Counts (LEFT JOIN to show 0 for groups with no stock)
$groupStats = $db->query("
    SELECT bg.group_name, COUNT(bu.unit_id) AS total_count
    FROM blood_groups bg
    LEFT JOIN blood_unit bu ON bu.group_id = bg.group_id AND bu.status = 'Available'
    GROUP BY bg.group_name
    ORDER BY bg.group_name
")->fetchAll();

// ── FILTER HANDLING ────────────────────────────────────────────
$filterGroup     = $_GET['group_id'] ?? '';
$filterComponent = $_GET['component'] ?? '';
$filterBank      = $_GET['bank_id'] ?? '';
$filterStatus    = $_GET['status'] ?? 'Available'; // Default to Available

$sql = '
    SELECT bu.unit_id, bu.component, bu.volume_ml, bu.collection_date, bu.expiry_date, bu.status,
           bg.group_name, d.name AS donor_name, bb.name AS bank_name
    FROM blood_unit bu
    JOIN blood_groups bg ON bg.group_id = bu.group_id
    JOIN donor d ON d.donor_id = bu.donor_id
    JOIN blood_bank bb ON bb.bank_id = bu.bank_id
    WHERE 1=1
';
$params = [];

if ($filterGroup !== '') {
    $sql .= ' AND bu.group_id = :group_id';
    $params['group_id'] = (int)$filterGroup;
}
if ($filterComponent !== '') {
    $sql .= ' AND bu.component = :component';
    $params['component'] = $filterComponent;
}
if ($filterBank !== '') {
    $sql .= ' AND bu.bank_id = :bank_id';
    $params['bank_id'] = (int)$filterBank;
}
if ($filterStatus !== 'All') {
    $sql .= ' AND bu.status = :status';
    $params['status'] = $filterStatus;
}

$sql .= ' ORDER BY bu.expiry_date ASC, bu.unit_id DESC';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$units = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inventory Stock — HemoLink</title>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/theme.css">
<style>
  body { margin: 0; }
  .main-inner { max-width: 1080px; }
  h1 { font-size: 20px; margin: 0 0 20px 0; }

  /* Stat Grid */
  .cards { display: flex; gap: 14px; flex-wrap: wrap; margin: 20px 0; }
  .stat-card { flex: 1; min-width: 180px; background: var(--red-lt); border-radius: var(--radius); padding: 18px; text-align: center; }
  .stat-card .num { font-size: 1.8rem; font-weight: 800; color: var(--red); }
  .stat-card .label { font-size: .78rem; color: var(--muted); margin-top: 4px; text-transform: uppercase; letter-spacing: .03em; }

  /* Group stock tags */
  .group-grid { display: grid; grid-template-columns: repeat(8, 1fr); gap: 8px; margin: 20px 0; }
  @media (max-width: 768px) { .group-grid { grid-template-columns: repeat(4, 1fr); } }
  .group-box { background: var(--white); border: 1px solid var(--border); border-radius: var(--radius-sm); padding: 11px; text-align: center; }
  .group-box .group-title { font-size: 14px; font-weight: 700; color: var(--muted); }
  .group-box .group-count { font-size: 12px; color: var(--muted); margin-top: 2px; }
  .group-box.has-stock { background: var(--red-lt); border-color: var(--red-md); }
  .group-box.has-stock .group-title { color: var(--red); }

  /* Filter Panel & Cards */
  .card { padding: 22px 24px; margin-top: 20px; }
  .filter-form { display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end; }
  .filter-group { display: flex; flex-direction: column; flex: 1; min-width: 140px; }
  .filter-group label { font-size: 12px; margin-bottom: 4px; }
  .filter-group select { padding: 8px; font-size: 13px; }

  .btn { padding: 8px 14px; background: var(--red); color: #fff; border: none; font-size: 13px; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
  .btn:hover { background: var(--red-dk); }
  .btn-outline { background: transparent; border: 1px solid var(--border); color: var(--muted); }
  .btn-outline:hover { background: #f9f9f9; }
  .btn-sm { padding: 4px 9px; font-size: 11px; border-radius: 6px; }
  .btn-danger { background: var(--red); }
  .btn-danger:hover { background: var(--red-dk); }

  /* Table */
  table { margin-top: 10px; }
  th, td { padding: 10px 8px; font-size: .85rem; }

  /* Badges */
  .badge-available  { background-color: var(--green-lt); color: var(--green); }
  .badge-reserved   { background-color: var(--amber-lt); color: var(--amber); }
  .badge-transfused { background-color: var(--blue-lt);  color: var(--blue); }
  .badge-expired    { background-color: var(--red-lt);   color: var(--red-dk); }
  .badge-discarded  { background-color: #f1f3f4; color: #3c4043; }

  /* Expiry colors */
  .expiry-critical { color: var(--red); font-weight: 700; }
  .expiry-warning  { color: var(--amber); font-weight: 700; }

  /* Select inside row */
  .status-select { padding: 5px; font-size: 12px; border-radius: 6px; }

  .action-cell { display: flex; gap: 6px; align-items: center; }

  .toast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    background: var(--slate);
    color: #fff;
    padding: 11px 20px;
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    font-size: 13px;
    z-index: 1000;
    opacity: 0;
    transform: translateY(20px);
    transition: opacity 0.3s, transform 0.3s;
    pointer-events: none;
  }
  .toast.show { opacity: 1; transform: translateY(0); }
</style>
</head>
<body>

<div class="app-shell">
  <?php include __DIR__ . '/../includes/staff_nav.php'; ?>
  <div class="main">
  <div class="main-inner">

  <h1>🩸 Blood Inventory Stock</h1>

  <!-- Statistics Grid -->
  <div class="cards">
    <div class="stat-card">
      <div class="num"><?= $totalAvailable ?></div>
      <div class="label">Total Available Units</div>
    </div>
    <div class="stat-card">
      <div class="num"><?= number_format($totalVolume / 1000, 1) ?>L</div>
      <div class="label">Total Available Volume</div>
    </div>
  </div>

  <!-- Blood Group Stock Status -->
  <div class="group-grid">
    <?php foreach ($groupStats as $stat): ?>
      <?php $hasStock = (int)$stat['total_count'] > 0; ?>
      <div class="group-box <?= $hasStock ? 'has-stock' : '' ?>">
        <div class="group-title"><?= htmlspecialchars($stat['group_name']) ?></div>
        <div class="group-count"><?= (int)$stat['total_count'] ?> unit<?= (int)$stat['total_count'] === 1 ? '' : 's' ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Filter Panel -->
  <div class="card">
    <form class="filter-form" method="GET">
      <div class="filter-group">
        <label for="group_id">Blood Group</label>
        <select id="group_id" name="group_id">
          <option value="">-- All Groups --</option>
          <?php foreach ($bloodGroups as $bg): ?>
            <option value="<?= $bg['group_id'] ?>" <?= $filterGroup === (string)$bg['group_id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($bg['group_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-group">
        <label for="component">Component</label>
        <select id="component" name="component">
          <option value="">-- All Components --</option>
          <?php foreach (['Whole Blood', 'RBC', 'Plasma', 'Platelets', 'Cryoprecipitate'] as $c): ?>
            <option value="<?= $c ?>" <?= $filterComponent === $c ? 'selected' : '' ?>><?= $c ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-group">
        <label for="bank_id">Blood Bank</label>
        <select id="bank_id" name="bank_id">
          <option value="">-- All Banks --</option>
          <?php foreach ($bloodBanks as $bb): ?>
            <option value="<?= $bb['bank_id'] ?>" <?= $filterBank === (string)$bb['bank_id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($bb['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="filter-group">
        <label for="status">Status</label>
        <select id="status" name="status">
          <option value="All" <?= $filterStatus === 'All' ? 'selected' : '' ?>>-- All Statuses --</option>
          <?php foreach (['Available', 'Reserved', 'Transfused', 'Expired', 'Discarded'] as $s): ?>
            <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display:flex; gap: 8px;">
        <button type="submit" class="btn">Filter</button>
        <a href="stock.php" class="btn btn-outline">Reset</a>
      </div>
    </form>
  </div>

  <!-- Detailed Stock List -->
  <div class="table-container">
    <table>
      <thead>
        <tr>
          <th>Unit ID</th>
          <th>Donor</th>
          <th>Group</th>
          <th>Component</th>
          <th>Volume (ml)</th>
          <th>Collection Date</th>
          <th>Expiry Date</th>
          <th>Location Bank</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($units)): ?>
          <tr>
            <td colspan="10" style="text-align: center; color: #777; padding: 32px;">
              No blood units match the active filters.
            </td>
          </tr>
        <?php endif; ?>
        <?php foreach ($units as $unit): ?>
          <?php
            // Calculate days to expiry
            $daysLeft = (int)ceil((strtotime($unit['expiry_date']) - time()) / 86400);
            $expiryClass = '';
            $expiryText = date('d M Y', strtotime($unit['expiry_date']));
            if ($unit['status'] === 'Available') {
                if ($daysLeft <= 0) {
                    $expiryClass = 'expiry-critical';
                    $expiryText .= ' (Expired)';
                } elseif ($daysLeft <= 7) {
                    $expiryClass = 'expiry-warning';
                    $expiryText .= " (Expiring in $daysLeft d)";
                }
            }
          ?>
          <tr id="unit-row-<?= $unit['unit_id'] ?>">
            <td><strong>#<?= $unit['unit_id'] ?></strong></td>
            <td><?= htmlspecialchars($unit['donor_name']) ?></td>
            <td><span style="font-weight: 600; color: #111827;"><?= htmlspecialchars($unit['group_name']) ?></span></td>
            <td><?= htmlspecialchars($unit['component']) ?></td>
            <td><?= $unit['volume_ml'] ?> ml</td>
            <td><?= date('d M Y', strtotime($unit['collection_date'])) ?></td>
            <td class="<?= $expiryClass ?>"><?= $expiryText ?></td>
            <td><?= htmlspecialchars($unit['bank_name']) ?></td>
            <td>
              <span id="badge-<?= $unit['unit_id'] ?>" class="badge badge-<?= strtolower($unit['status']) ?>">
                <?= htmlspecialchars($unit['status']) ?>
              </span>
            </td>
            <td>
              <div class="action-cell">
                <select class="status-select" onchange="updateStatus(<?= $unit['unit_id'] ?>, this.value)">
                  <?php foreach (['Available', 'Reserved', 'Transfused', 'Expired', 'Discarded'] as $s): ?>
                    <option value="<?= $s ?>" <?= $unit['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn-outline btn-sm btn-danger" onclick="deleteUnit(<?= $unit['unit_id'] ?>)">Delete</button>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  </div>
  </div>
</div>

<div class="toast" id="toast">Notification text</div>

<script>
  function showToast(text, isError = false) {
    const toast = document.getElementById('toast');
    toast.textContent = text;
    toast.style.backgroundColor = isError ? '#c0152b' : '#1e2430';
    toast.classList.add('show');
    setTimeout(() => {
      toast.classList.remove('show');
    }, 3000);
  }

  function updateStatus(unitId, status) {
    fetch('../api/inventory.php?id=' + unitId, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ status: status })
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast('Unit #' + unitId + ' status updated to ' + status + '.');
        
        // Update the badge styling classes
        const badge = document.getElementById('badge-' + unitId);
        badge.textContent = status;
        badge.className = 'badge badge-' + status.toLowerCase();
      } else {
        showToast(data.error || 'Failed to update status.', true);
      }
    })
    .catch(() => {
      showToast('Connection error.', true);
    });
  }

  function deleteUnit(unitId) {
    if (!confirm('Are you sure you want to delete blood unit #' + unitId + '? This will also cascade delete any associated transfusion logs!')) {
      return;
    }
    
    fetch('../api/inventory.php?id=' + unitId, {
      method: 'DELETE'
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast('Unit #' + unitId + ' has been permanently deleted.');
        
        // Fade out and remove the row from the table
        const row = document.getElementById('unit-row-' + unitId);
        row.style.transition = 'all 0.5s ease';
        row.style.opacity = '0';
        row.style.transform = 'scale(0.95)';
        setTimeout(() => {
          row.remove();
        }, 500);
      } else {
        showToast(data.error || 'Failed to delete unit.', true);
      }
    })
    .catch(() => {
      showToast('Connection error.', true);
    });
  }
</script>
</body>
</html>
