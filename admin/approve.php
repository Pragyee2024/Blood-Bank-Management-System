<?php
declare(strict_types=1);
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: text/html; charset=UTF-8'); // override connect.php's JSON header — this page renders HTML

$user = require_login(['admin', 'staff']);
$db = getDB();

/*
 * INTEGRATION NOTE FOR THE TEAM:
 * Approving a request reserves rows in blood_unit (status Available -> Reserved).
 * Member 3's inventory module also writes to blood_unit. Both of you need to agree
 * that "Reserved" always means "claimed by an approved blood_request" and that no
 * other code path flips a unit from Reserved back to Available except a cancellation
 * here or an explicit inventory correction. The transaction below uses
 * SELECT ... FOR UPDATE to avoid two admins double-booking the same unit.
 */

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

            // Lock and reserve enough available units matching group + component
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
        // Mark reserved units as transfused and close out the request
        // Postgres uses UPDATE ... FROM instead of MySQL's UPDATE ... JOIN
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
<title>Review Request #<?= $requestId ?> — Blood Bank Management System</title>
<style>
  body { font-family: Arial, sans-serif; background:#ffffff; color:#222; margin:0; padding:24px; }
  .wrap { max-width: 640px; margin: 0 auto; }
  nav { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; font-size:14px; }
  nav a { color:#c0392b; text-decoration:none; margin-left:14px; }
  .card { background:#fff; padding:28px; border-radius:10px; border:2px solid #c0392b; }
  h1 { font-size: 20px; margin: 0 0 20px; color:#222; }
  .msg-ok { background: #e6f4ea; border: 1px solid #34a853; padding: 10px; border-radius: 6px; }
  .msg-err { background: #fdecea; color:#c0392b; border: 1px solid #f5c6c1; padding: 10px; border-radius: 6px; }
  dl { display: grid; grid-template-columns: 140px 1fr; row-gap: 6px; }
  dt { font-weight: bold; color: #555; }
  form { display: inline; }
  button { padding: 8px 16px; border: none; border-radius: 6px; color: #fff; cursor: pointer; margin-right: 8px; font-weight:bold; }
  .approve { background: #0a5c36; }
  .reject { background: #842029; }
  .fulfill { background: #084298; }
</style>
</head>
<body>
<div class="wrap">
  <?php include __DIR__ . '/../includes/staff_nav.php'; ?>
  <p><a href="dashboard.php" style="color:#c0392b;">&larr; Back to dashboard</a></p>
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
</body>
</html>
