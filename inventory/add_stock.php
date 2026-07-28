<?php
declare(strict_types=1);
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: text/html; charset=UTF-8');

$user = require_login(['admin', 'staff']);
$db = getDB();

$message = '';
$error   = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $donorId   = (int)($_POST['donor_id'] ?? 0);
    $bankId    = (int)($_POST['bank_id'] ?? 0);
    $component = $_POST['component'] ?? 'Whole Blood';
    $volume    = (int)($_POST['volume_ml'] ?? 450);

    $validComponents = ['Whole Blood', 'RBC', 'Plasma', 'Platelets', 'Cryoprecipitate'];

    if (!$donorId || !$bankId) {
        $error = 'Please select a donor and a blood bank location.';
    } elseif (!in_array($component, $validComponents, true)) {
        $error = 'Invalid blood component selected.';
    } elseif ($volume <= 0 || $volume > 1000) {
        $error = 'Please enter a valid volume between 1 ml and 1000 ml.';
    } else {
        try {
            // Call PostgreSQL stored function/procedure
            $stmt = $db->prepare('SELECT * FROM sp_add_blood_unit(:donor_id, :bank_id, :component, :volume_ml)');
            $stmt->execute([
                'donor_id'  => $donorId,
                'bank_id'   => $bankId,
                'component' => $component,
                'volume_ml' => $volume,
            ]);
            $result = $stmt->fetch();

            if ($result) {
                $newUnitId   = $result['unit_id'];
                $expiryDate  = date('d M Y', strtotime($result['expiry_date']));
                $message = "Success! Blood unit <strong>#{$newUnitId}</strong> has been added. Expiring on <strong>{$expiryDate}</strong>. Donor donation records and inventory logs have been automatically updated via PostgreSQL transaction.";
            } else {
                $error = 'Failed to create blood unit. Stored procedure returned no results.';
            }
        } catch (Exception $e) {
            $error = 'Database Error: ' . $e->getMessage();
        }
    }
}


$donors = $db->query('
    SELECT d.donor_id, d.name, d.phone, bg.group_name 
    FROM donor d
    JOIN blood_groups bg ON bg.group_id = d.group_id
    ORDER BY d.name
')->fetchAll();

$banks = $db->query('SELECT bank_id, name, location FROM blood_bank ORDER BY name')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Blood Stock — HemoLink</title>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/theme.css">
<style>
  body { margin: 0; }
  .main-inner { max-width: 640px; }
  .card { padding: 28px; }
  h1 { font-size: 20px; margin: 0 0 4px; }
  .subtitle { font-size: 13px; color: var(--muted); margin-bottom: 20px; }
  label { display: block; margin-top: 14px; font-size: .9rem; }
  select, input { width: 100%; padding: 9px; margin-top: 4px; box-sizing: border-box; font-size:14px; }
  button { width: 100%; margin-top: 20px; padding: 11px 18px; background: var(--red); color: #fff; border: none; }
  button:hover { background: var(--red-dk); }
  .row { display: flex; gap: 12px; }
  .row > div { flex: 1; }
  @media (max-width: 640px) {
    .row { flex-direction: column; gap: 0; }
  }
</style>
</head>
<body>

<div class="app-shell">
  <?php include __DIR__ . '/../includes/staff_nav.php'; ?>
  <div class="main">
  <div class="main-inner">

  <div class="card">
    <h1>🩸 Add Blood Stock</h1>
    <div class="subtitle">Record received donations directly into the system</div>

    <?php if ($message): ?>
      <div class="alert alert-success"><?= $message ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
      <label for="donor_id">Donor Name</label>
      <select name="donor_id" id="donor_id" required autofocus>
        <option value="">-- Select Donor --</option>
        <?php foreach ($donors as $d): ?>
          <option value="<?= (int)$d['donor_id'] ?>">
            <?= htmlspecialchars($d['name']) ?> (Group: <?= htmlspecialchars($d['group_name']) ?> — Phone: <?= htmlspecialchars($d['phone']) ?>)
          </option>
        <?php endforeach; ?>
      </select>

      <label for="bank_id">Blood Bank Location</label>
      <select name="bank_id" id="bank_id" required>
        <option value="">-- Select Blood Bank --</option>
        <?php foreach ($banks as $b): ?>
          <option value="<?= (int)$b['bank_id'] ?>">
            <?= htmlspecialchars($b['name']) ?> (<?= htmlspecialchars($b['location']) ?>)
          </option>
        <?php endforeach; ?>
      </select>

      <div class="row">
        <div>
          <label for="component">Component Type</label>
          <select name="component" id="component" required>
            <option value="Whole Blood">Whole Blood (Expiry: 35 days)</option>
            <option value="RBC">RBC - Red Blood Cells (Expiry: 42 days)</option>
            <option value="Plasma">Plasma (Expiry: 365 days)</option>
            <option value="Platelets">Platelets (Expiry: 5 days)</option>
            <option value="Cryoprecipitate">Cryoprecipitate (Expiry: 365 days)</option>
          </select>
        </div>
        <div>
          <label for="volume_ml">Volume (ml)</label>
          <input type="number" name="volume_ml" id="volume_ml" min="50" max="1000" value="450" required>
        </div>
      </div>

      <button type="submit">Record & Save Unit</button>
    </form>
  </div>
  </div>
  </div>
</div>

</body>
</html>
