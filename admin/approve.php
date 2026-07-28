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
                WHERE group_id IN ($inClause) AND component = :component AND status = 'Available'
                ORDER BY 
                    CASE WHEN group_id = :exact_group_id THEN 0 ELSE 1 END,
                    expiry_date ASC
                LIMIT :needed
                FOR UPDATE
            ");
            $unitStmt->bindValue(':component', $req['component'], PDO::PARAM_STR);
            $unitStmt->bindValue(':exact_group_id', $req['group_id'], PDO::PARAM_INT);
            $unitStmt->bindValue(':needed', (int)$req['units_needed'], PDO::PARAM_INT);
            $unitStmt->execute();
            $units = $unitStmt->fetchAll(PDO::FETCH_COLUMN);

            if (count($units) < (int)$req['units_needed']) {
                throw new Exception('Not enough compatible available units in stock for this request.');
            }

            $reserve = $db->prepare("UPDATE blood_unit SET status = 'Reserved' WHERE unit_id = :uid");
            $transfusion = $db->prepare("
                INSERT INTO transfusion (request_id, unit_id, doctor_id, outcome, notes)
                VALUES (:rid, :uid, :did, 'Pending', 'Reserved compatible unit')
            ");

            foreach ($units as $uid) {
                $reserve->execute([':uid' => $uid]);
                $transfusion->execute([
                    ':rid' => $requestId,
                    ':uid' => $uid,
                    ':did' => $req['doctor_id']
                ]);

                $db->prepare("
                    INSERT INTO inventory_log (bank_id, unit_id, group_id, component, action, performed_by)
                    SELECT bank_id, unit_id, group_id, component, 'Reserved', 'Admin'
                    FROM blood_unit WHERE unit_id = :uid
                ")->execute([':uid' => $uid]);
            }

            $db->prepare("UPDATE blood_request SET status = 'Processing' WHERE request_id = :id")
               ->execute([':id' => $requestId]);

            $db->commit();
            $message = "Request #$requestId approved. " . count($units) . " compatible unit(s) reserved.";
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

            // Find all reserved units linked to this request
            $stmt = $db->prepare("
                SELECT t.unit_id 
                FROM transfusion t
                JOIN blood_unit bu ON bu.unit_id = t.unit_id
                WHERE t.request_id = :rid AND bu.status = 'Reserved'
            ");
            $stmt->execute([':rid' => $requestId]);
            $units = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $updateUnit = $db->prepare("UPDATE blood_unit SET status = 'Available' WHERE unit_id = :uid");
            foreach ($units as $uid) {
                $updateUnit->execute([':uid' => $uid]);

                $db->prepare("
                    INSERT INTO inventory_log (bank_id, unit_id, group_id, component, action, performed_by)
                    SELECT bank_id, unit_id, group_id, component, 'Discarded', 'Admin'
                    FROM blood_unit WHERE unit_id = :uid
                ")->execute([':uid' => $uid]);
            }

            // Remove the links from the transfusion table
            $db->prepare("DELETE FROM transfusion WHERE request_id = :rid")
               ->execute([':rid' => $requestId]);

            // Set request status to Cancelled
            $db->prepare("UPDATE blood_request SET status = 'Cancelled' WHERE request_id = :id")
               ->execute([':id' => $requestId]);

            $db->commit();
            $message = "Request #$requestId cancelled. " . count($units) . " reserved unit(s) released back to stock.";
        } catch (Exception $e) {
            $db->rollBack();
            $error = $e->getMessage();
        }
    } elseif ($action === 'fulfill') {
        try {
            $db->beginTransaction();

            // Fetch linked reserved units
            $stmt = $db->prepare("
                SELECT t.unit_id 
                FROM transfusion t
                JOIN blood_unit bu ON bu.unit_id = t.unit_id
                WHERE t.request_id = :rid AND bu.status = 'Reserved'
            ");
            $stmt->execute([':rid' => $requestId]);
            $units = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($units)) {
                throw new Exception('No reserved units found to fulfill this request.');
            }

            $updateUnit = $db->prepare("UPDATE blood_unit SET status = 'Transfused' WHERE unit_id = :uid");
            foreach ($units as $uid) {
                $updateUnit->execute([':uid' => $uid]);

                $db->prepare("
                    INSERT INTO inventory_log (bank_id, unit_id, group_id, component, action, performed_by)
                    SELECT bank_id, unit_id, group_id, component, 'Transfused', 'Admin'
                    FROM blood_unit WHERE unit_id = :uid
                ")->execute([':uid' => $uid]);
            }

            // Set transfusion outcomes to Successful
            $db->prepare("UPDATE transfusion SET outcome = 'Successful' WHERE request_id = :rid")
               ->execute([':rid' => $requestId]);

            // Set request status to Fulfilled
            $db->prepare("UPDATE blood_request SET status = 'Fulfilled' WHERE request_id = :id")
               ->execute([':id' => $requestId]);

            $db->commit();
            $message = "Request #$requestId marked fulfilled. " . count($units) . " unit(s) transfused.";
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

$oMinusWarning = '';
if ($req && $req['status'] === 'Pending') {
    $compatibleIds = [(int)$req['group_id']];
    $reqGroupName = $groupNames[(int)$req['group_id']] ?? '';
    if ($reqGroupName && isset($compatibility[$reqGroupName])) {
        $compatibleIds = [];
        foreach ($compatibility[$reqGroupName] as $name) {
            if (isset($groupMap[$name])) {
                $compatibleIds[] = $groupMap[$name];
            }
        }
    }
    
    // Simulate selection
    $inClause = implode(',', $compatibleIds);
    $simStmt = $db->prepare("
        SELECT group_id FROM blood_unit
        WHERE group_id IN ($inClause) AND component = :component AND status = 'Available'
        ORDER BY 
            CASE WHEN group_id = :exact_group_id THEN 0 ELSE 1 END,
            expiry_date ASC
        LIMIT :needed
    ");
    $simStmt->bindValue(':component', $req['component'], PDO::PARAM_STR);
    $simStmt->bindValue(':exact_group_id', $req['group_id'], PDO::PARAM_INT);
    $simStmt->bindValue(':needed', (int)$req['units_needed'], PDO::PARAM_INT);
    $simStmt->execute();
    $matchedGroupIds = $simStmt->fetchAll(PDO::FETCH_COLUMN);

    $oMinusId = $groupMap['O-'] ?? null;
    $willUseOMinus = false;
    foreach ($matchedGroupIds as $mgid) {
        if ((int)$mgid === $oMinusId) {
            $willUseOMinus = true;
            break;
        }
    }

    if ($oMinusId && $willUseOMinus) {
        $stmtCount = $db->prepare("SELECT COUNT(*) FROM blood_unit WHERE group_id = :gid AND status = 'Available'");
        $stmtCount->execute([':gid' => $oMinusId]);
        $oMinusStockCount = (int)$stmtCount->fetchColumn();

        $isUrgent = in_array($req['urgency'], ['High', 'Critical'], true);
        if (!$isUrgent && $oMinusStockCount <= 3) {
            if ($reqGroupName === 'O-') {
                $oMinusWarning = "Alert: Universal donor O- supply is critically low (only $oMinusStockCount unit(s) remaining). However, the patient is O- and can only receive O-. Ensure immediate replenishment.";
            } else {
                $oMinusWarning = "Warning: This is a non-urgent request ({$req['urgency']} urgency). The system is about to allocate universal donor O- blood, but O- stock is low (only $oMinusStockCount unit(s) remaining). Consider finding an exact {$req['group_name']} match instead to conserve universal donor blood.";
            }
        }
    }
}
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
    <dt>Blood Group</dt>
    <dd>
      <?= htmlspecialchars($req['group_name']) ?>
      <?php
        $compList = $compatibility[$req['group_name']] ?? [$req['group_name']];
        echo ' <span style="font-size: 0.8rem; color: #666;">(Compatible Donors: ' . implode(', ', $compList) . ')</span>';
      ?>
    </dd>
    <dt>Component</dt><dd><?= htmlspecialchars($req['component']) ?></dd>
    <dt>Units Needed</dt><dd><?= (int)$req['units_needed'] ?></dd>
    <dt>Urgency</dt><dd><?= htmlspecialchars($req['urgency']) ?></dd>
    <dt>Status</dt><dd><?= htmlspecialchars($req['status']) ?></dd>
    <dt>Notes</dt><dd><?= htmlspecialchars($req['notes'] ?? '—') ?></dd>
  </dl>

  <?php if ($req['status'] === 'Processing' || $req['status'] === 'Fulfilled'): ?>
    <?php
      $reservedUnits = $db->prepare("
          SELECT bu.unit_id, bg.group_name, bu.component, bu.volume_ml, bb.name AS bank_name, t.outcome
          FROM transfusion t
          JOIN blood_unit bu ON bu.unit_id = t.unit_id
          JOIN blood_groups bg ON bg.group_id = bu.group_id
          JOIN blood_bank bb ON bb.bank_id = bu.bank_id
          WHERE t.request_id = :rid
      ");
      $reservedUnits->execute([':rid' => $requestId]);
      $resUnits = $reservedUnits->fetchAll();
    ?>
    <?php if (!empty($resUnits)): ?>
      <div style="margin-top: 20px; border-top: 1px dashed #f0d9d5; padding-top: 15px;">
        <h3 style="font-size: 0.95rem; margin: 0 0 8px; color: #c0392b;">Linked Blood Units:</h3>
        <ul style="margin: 0 0 0 20px; padding: 0; font-size: 0.85rem; line-height: 1.6;">
          <?php foreach ($resUnits as $u): ?>
            <li>
              <strong>Unit #<?= $u['unit_id'] ?></strong>: 
              <?= htmlspecialchars($u['group_name']) ?> <?= htmlspecialchars($u['component']) ?> (<?= $u['volume_ml'] ?> ml) 
              from <em><?= htmlspecialchars($u['bank_name']) ?></em> 
              [Status: <?= htmlspecialchars($u['outcome']) ?>]
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <form method="POST" style="margin-top: 20px;">
    <input type="hidden" name="request_id" value="<?= $requestId ?>">
    <?php if ($req['status'] === 'Pending'): ?>
      <button class="approve" name="action" value="approve">Approve &amp; Reserve Units</button>
      <button class="reject" name="action" value="reject">Reject</button>
    <?php elseif ($req['status'] === 'Processing'): ?>
      <button class="fulfill" name="action" value="fulfill">Mark Fulfilled (transfused)</button>
      <button class="reject" name="action" value="release">Cancel &amp; Release Units</button>
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
