<?php
declare(strict_types=1);
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: text/html; charset=UTF-8');

$user = require_login(['admin', 'staff']);
$db = getDB();

$sql = "
    SELECT bu.unit_id, bu.component, bu.volume_ml, bu.collection_date, bu.expiry_date, bu.status,
           bg.group_name, d.name AS donor_name, d.phone AS donor_phone, bb.name AS bank_name
    FROM blood_unit bu
    JOIN blood_groups bg ON bg.group_id = bu.group_id
    JOIN donor d ON d.donor_id = bu.donor_id
    JOIN blood_bank bb ON bb.bank_id = bu.bank_id
    WHERE bu.status = 'Available' AND bu.expiry_date <= CURRENT_DATE + INTERVAL '7 days'
    ORDER BY bu.expiry_date ASC, bu.unit_id DESC
";
$stmt = $db->query($sql);
$expiringUnits = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Expiry Tracker — HemoLink</title>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/theme.css">
<style>
  body { margin: 0; }
  .main-inner { max-width: 1080px; }
  h1 { font-size: 20px; margin: 0 0 4px 0; }
  .subtitle { font-size: 13px; color: var(--muted); margin-bottom: 20px; }

  /* Expiry Banner */
  .expiry-alert-banner {
    background-color: var(--red-lt);
    border: 1px solid var(--red-md);
    border-radius: var(--radius);
    padding: 16px 20px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    color: var(--red-dk);
  }
  .expiry-alert-banner svg { width: 24px; height: 24px; flex-shrink: 0; color: var(--red); }
  .expiry-alert-banner .title { font-weight: 700; font-size: 14px; }
  .expiry-alert-banner .desc { font-size: 13px; color: #7a3a3a; margin-top: 4px; }

 
  .table-container { background: var(--white); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow); padding: 8px 20px; }
  table { margin-top: 4px; }
  th, td { padding: 10px 8px; font-size: .85rem; }


  .days-expired  { background-color: var(--red-lt); color: var(--red-dk); border: 1px solid var(--red-md); }
  .days-critical { background-color: var(--amber-lt); color: var(--amber); border: 1px solid #f6dcb0; }
  .days-warning  { background-color: var(--blue-lt); color: var(--blue); border: 1px solid #c2d6ff; }

  .btn {
    padding: 5px 10px;
    border: none;
    border-radius: var(--radius-sm);
    font-weight: 700;
    font-size: 12px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
  .btn-discard { background-color: var(--red); color: #fff; }
  .btn-discard:hover { background-color: var(--red-dk); }
  .btn-expire { background-color: var(--amber); color: #fff; margin-right: 6px; }
  .btn-expire:hover { background-color: #92400e; }

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

  <h1>🩸 Blood Expiry Tracker</h1>
  <div class="subtitle">Monitor blood units near or past their expiration dates</div>

  <?php if (!empty($expiringUnits)): ?>
    <div class="expiry-alert-banner">
      <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
      </svg>
      <div>
        <div class="title">Attention Required</div>
        <div class="desc">There are <?= count($expiringUnits) ?> available blood units that are either expired or expiring within 7 days. Action is recommended to maintain inventory integrity.</div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Detailed Expiry List -->
  <div class="table-container">
    <table>
      <thead>
        <tr>
          <th>Unit ID</th>
          <th>Donor Info</th>
          <th>Blood Group</th>
          <th>Component</th>
          <th>Volume</th>
          <th>Expiry Date</th>
          <th>Time Left</th>
          <th>Blood Bank</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($expiringUnits)): ?>
          <tr>
            <td colspan="9" style="text-align: center; color: #777; padding: 36px;">
              🎉 No blood units are currently expired or expiring within the next 7 days.
            </td>
          </tr>
        <?php endif; ?>
        <?php foreach ($expiringUnits as $unit): ?>
          <?php
            $daysLeft = (int)ceil((strtotime($unit['expiry_date']) - time()) / 86400);
            
            if ($daysLeft < 0) {
                $statusBadge = '<span class="days-badge days-expired">Expired (' . abs($daysLeft) . 'd ago)</span>';
            } elseif ($daysLeft === 0) {
                $statusBadge = '<span class="days-badge days-critical">Expires Today</span>';
            } elseif ($daysLeft === 1) {
                $statusBadge = '<span class="days-badge days-critical">Expires Tomorrow</span>';
            } else {
                $statusBadge = '<span class="days-badge days-warning">Expires in ' . $daysLeft . ' days</span>';
            }
          ?>
          <tr id="unit-row-<?= $unit['unit_id'] ?>">
            <td><strong>#<?= $unit['unit_id'] ?></strong></td>
            <td>
              <div><?= htmlspecialchars($unit['donor_name']) ?></div>
              <div style="font-size: 12px; color: #777;"><?= htmlspecialchars($unit['donor_phone']) ?></div>
            </td>
            <td><span style="font-weight: 600; color: #111827;"><?= htmlspecialchars($unit['group_name']) ?></span></td>
            <td><?= htmlspecialchars($unit['component']) ?></td>
            <td><?= $unit['volume_ml'] ?> ml</td>
            <td style="font-weight: 500;"><?= date('d M Y', strtotime($unit['expiry_date'])) ?></td>
            <td><?= $statusBadge ?></td>
            <td><?= htmlspecialchars($unit['bank_name']) ?></td>
            <td>
              <button class="btn btn-expire" onclick="markExpired(<?= $unit['unit_id'] ?>)">Mark Expired</button>
              <button class="btn btn-discard" onclick="discardUnit(<?= $unit['unit_id'] ?>)">Discard</button>
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

  function markExpired(unitId) {
    fetch('../api/inventory.php?id=' + unitId, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ status: 'Expired' })
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast('Unit #' + unitId + ' marked as Expired.');
        removeRow(unitId);
      } else {
        showToast(data.error || 'Failed to update status.', true);
      }
    })
    .catch(() => {
      showToast('Connection error.', true);
    });
  }

  function discardUnit(unitId) {
    if (!confirm('Are you sure you want to discard blood unit #' + unitId + '?')) {
      return;
    }
    
    fetch('../api/inventory.php?id=' + unitId, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ status: 'Discarded' })
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast('Unit #' + unitId + ' marked as Discarded.');
        removeRow(unitId);
      } else {
        showToast(data.error || 'Failed to discard unit.', true);
      }
    })
    .catch(() => {
      showToast('Connection error.', true);
    });
  }

  function removeRow(unitId) {
    const row = document.getElementById('unit-row-' + unitId);
    row.style.transition = 'all 0.5s ease';
    row.style.opacity = '0';
    row.style.transform = 'scale(0.95)';
    setTimeout(() => {
      row.remove();
      // Check if table is empty now
      const tbody = document.querySelector('tbody');
      if (tbody.children.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 36px;">
              🎉 No blood units are currently expired or expiring within the next 7 days.
            </td>
          </tr>
        `;
        const banner = document.querySelector('.expiry-alert-banner');
        if (banner) banner.remove();
      }
    }, 500);
  }
</script>
</body>
</html>
