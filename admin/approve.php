<?php
declare(strict_types=1);
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: text/html; charset=UTF-8'); 

$user = require_login(['admin', 'staff']);
$db = getDB();


$requestId = (int)($_GET['request_id'] ?? $_POST['request_id'] ?? 0);
$message = '';
$error   = '';

if (!$requestId) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'approve') {
        try {
            $db->beginTransaction();

            $stmt = $db->prepare("SELECT * FROM blood_request WHERE request_id = :id FOR UPDATE");
            $stmt->execute([':id' => $requestId]);
            $req = $stmt->fetch();

            if (!$req) throw new Exception('Request not found.');
            if ($req['status'] !== 'Pending') throw new Exception('Request is not pending.');

            
            $unitStmt = $db->prepare("
                SELECT unit_id FROM blood_unit
                WHERE group_id = :group_id AND component = :component AND status = 'Available'
                ORDER BY expiry_date ASC
                LIMIT :needed
                FOR UPDATE
            ");
            $unitStmt->bindValue(':group_id', $req['group_id'], PDO::PARAM_INT);
            $unitStmt->bindValue(':component', $req['component'], PDO::PARAM_STR);
            $unitStmt->bindValue(':needed', (int)$req['units_needed'], PDO::PARAM_INT);
            $unitStmt->execute();
            $units = $unitStmt->fetchAll(PDO::FETCH_COLUMN);

            if (count($units) < (int)$req['units_needed']) {
                throw new Exception('Not enough available units in stock for this group/component.');
            }

            $reserve = $db->prepare("UPDATE blood_unit SET status = 'Reserved' WHERE unit_id = :uid");
            foreach ($units as $uid) {
                $reserve->execute([':uid' => $uid]);
                $db->prepare("
                    INSERT INTO inventory_log (bank_id, unit_id, group_id, component, action, performed_by)
                    SELECT bank_id, unit_id, group_id, component, 'Reserved', 'Admin'
                    FROM blood_unit WHERE unit_id = :uid
                ")->execute([':uid' => $uid]);
            }

            $db->prepare("UPDATE blood_request SET status = 'Processing' WHERE request_id = :id")
               ->execute([':id' => $requestId]);

            $db->commit();
            $message = "Request #$requestId approved. " . count($units) . " unit(s) reserved.";
        } catch (Exception $e) {
            $db->rollBack();
            $error = $e->getMessage();
        }
    } elseif ($action === 'reject') {
        $db->prepare("UPDATE blood_request SET status = 'Cancelled' WHERE request_id = :id AND status = 'Pending'")
           ->execute([':id' => $requestId]);
        $message = "Request #$requestId rejected.";
    } elseif ($action === 'fulfill') {
        try {
            $db->beginTransaction();
            $db->prepare("
                UPDATE blood_unit bu
                SET status = 'Transfused'
                FROM blood_request r
                WHERE r.request_id = :id
                  AND r.group_id = bu.group_id
                  AND r.component = bu.component
                  AND bu.status = 'Reserved'
            ")->execute([':id' => $requestId]);

            $db->prepare("UPDATE blood_request SET status = 'Fulfilled' WHERE request_id = :id")
               ->execute([':id' => $requestId]);
            $db->commit();
            $message = "Request #$requestId marked fulfilled.";
        } catch (Exception $e) {
            $db->rollBack();
            $error = $e->getMessage();
        }
    }
}

$stmt = $db->prepare("
    SELECT r.*, p.name AS patient_name, h.name AS hospital_name, d.name AS doctor_name, bg.group_name
    FROM blood_request r
    JOIN patient p ON p.patient_id = r.patient_id
    JOIN hospital h ON h.hospital_id = p.hospital_id
    JOIN doctor d ON d.doctor_id = r.doctor_id
    JOIN blood_groups bg ON bg.group_id = r.group_id
    WHERE r.request_id = :id
");
$stmt->execute([':id' => $requestId]);
$req = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Review Request #<?= $requestId ?> — HemoLink</title>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/theme.css">
<style>
  body { margin: 0; }
  .main-inner { max-width: 640px; }
  .card { padding: 28px; }
  h1 { font-size: 20px; margin: 0 0 20px; }
  dl { display: grid; grid-template-columns: 140px 1fr; row-gap: 8px; }
  dt { font-weight: 600; color: var(--muted); }
  form { display: inline; }
  button { padding: 9px 18px; border: none; color: #fff; margin-right: 8px; }
  .approve { background: var(--green); }
  .approve:hover { background: #14532d; }
  .reject { background: var(--red); }
  .reject:hover { background: var(--red-dk); }
  .fulfill { background: var(--blue); }
  .fulfill:hover { background: #1e3a8a; }
</style>
</head>
<body>
<div class="app-shell">
  <?php include __DIR__ . '/../includes/staff_nav.php'; ?>
  <div class="main">
  <div class="main-inner">
  <p><a href="dashboard.php" style="color:var(--red);">&larr; Back to dashboard</a></p>
  <div class="card">
  <h1>Request #<?= $requestId ?></h1>

<?php if ($message): ?><p class="msg-ok"><?= htmlspecialchars($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="msg-err"><?= htmlspecialchars($error) ?></p><?php endif; ?>

<?php if (!$req): ?>
  <p>Request not found.</p>
<?php else: ?>
  <dl>
    <dt>Patient</dt><dd><?= htmlspecialchars($req['patient_name']) ?></dd>
    <dt>Hospital</dt><dd><?= htmlspecialchars($req['hospital_name']) ?></dd>
    <dt>Doctor</dt><dd><?= htmlspecialchars($req['doctor_name']) ?></dd>
    <dt>Blood Group</dt><dd><?= htmlspecialchars($req['group_name']) ?></dd>
    <dt>Component</dt><dd><?= htmlspecialchars($req['component']) ?></dd>
    <dt>Units Needed</dt><dd><?= (int)$req['units_needed'] ?></dd>
    <dt>Urgency</dt><dd><?= htmlspecialchars($req['urgency']) ?></dd>
    <dt>Status</dt><dd><?= htmlspecialchars($req['status']) ?></dd>
    <dt>Notes</dt><dd><?= htmlspecialchars($req['notes'] ?? '—') ?></dd>
  </dl>

  <form method="POST" style="margin-top: 20px;">
    <input type="hidden" name="request_id" value="<?= $requestId ?>">
    <?php if ($req['status'] === 'Pending'): ?>
      <button class="approve" name="action" value="approve">Approve &amp; Reserve Units</button>
      <button class="reject" name="action" value="reject">Reject</button>
    <?php elseif ($req['status'] === 'Processing'): ?>
      <button class="fulfill" name="action" value="fulfill">Mark Fulfilled (transfused)</button>
    <?php else: ?>
      <p><em>No further action available for status "<?= htmlspecialchars($req['status']) ?>".</em></p>
    <?php endif; ?>
  </form>
<?php endif; ?>
  </div>
  </div>
  </div>
</div>
</body>
</html>
