<?php
require_once __DIR__ . '/../page_db.php';
$db = getDB();

$hospitals = $db->query("SELECT hospital_id, name FROM hospital ORDER BY name")->fetchAll();
$groups    = $db->query("SELECT group_id, group_name FROM blood_groups ORDER BY group_id")->fetchAll();

$message = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doctor_id    = (int)($_POST['doctor_id'] ?? 0);
    $patient_id   = (int)($_POST['patient_id'] ?? 0);
    $group_id     = (int)($_POST['group_id'] ?? 0);
    $component    = $_POST['component'] ?? 'Whole Blood';
    $units_needed = max(1, (int)($_POST['units_needed'] ?? 1));
    $urgency      = $_POST['urgency'] ?? 'Medium';
    $notes        = trim($_POST['notes'] ?? '');

    $valid_components = ['Whole Blood','RBC','Plasma','Platelets','Cryoprecipitate'];
    $valid_urgency     = ['Low','Medium','High','Critical'];

    if (!$doctor_id || !$patient_id || !$group_id) {
        $error = 'Please select hospital, doctor, patient, and blood group.';
    } elseif (!in_array($component, $valid_components, true)) {
        $error = 'Invalid component selected.';
    } elseif (!in_array($urgency, $valid_urgency, true)) {
        $error = 'Invalid urgency selected.';
    } else {
        // Postgres: use RETURNING instead of relying on PDO::lastInsertId(),
        // which needs an explicit sequence name for Postgres.
        $stmt = $db->prepare(
            "INSERT INTO blood_request (patient_id, doctor_id, group_id, component, units_needed, urgency, status, notes)
             VALUES (:patient_id, :doctor_id, :group_id, :component, :units_needed, :urgency, 'Pending', :notes)
             RETURNING request_id"
        );
        $stmt->execute([
            ':patient_id'   => $patient_id,
            ':doctor_id'    => $doctor_id,
            ':group_id'     => $group_id,
            ':component'    => $component,
            ':units_needed' => $units_needed,
            ':urgency'      => $urgency,
            ':notes'        => $notes !== '' ? $notes : null,
        ]);
        $newId   = $stmt->fetchColumn();
        $message = "Request #$newId submitted successfully. Status: Pending.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Submit Blood Request</title>
<style>
  body { font-family: Arial, sans-serif; max-width: 640px; margin: 40px auto; padding: 0 16px; color: #222; }
  h1 { font-size: 1.4rem; }
  label { display: block; margin-top: 14px; font-weight: bold; font-size: .9rem; }
  select, input, textarea { width: 100%; padding: 8px; margin-top: 4px; box-sizing: border-box; }
  button { margin-top: 20px; padding: 10px 18px; background: #b71c1c; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
  button:hover { background: #8e1414; }
  .msg-ok  { background: #e6f4ea; border: 1px solid #34a853; padding: 10px; border-radius: 4px; }
  .msg-err { background: #fde8e8; border: 1px solid #d93025; padding: 10px; border-radius: 4px; }
  .row { display: flex; gap: 12px; }
  .row > div { flex: 1; }
</style>
</head>
<body>
<h1>Submit Blood Request</h1>

<?php if ($message): ?><p class="msg-ok"><?= htmlspecialchars($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="msg-err"><?= htmlspecialchars($error) ?></p><?php endif; ?>

<form method="POST" id="reqForm">
  <label for="hospital_id">Hospital</label>
  <select name="hospital_id" id="hospital_id" required>
    <option value="">-- Select hospital --</option>
    <?php foreach ($hospitals as $h): ?>
      <option value="<?= (int)$h['hospital_id'] ?>"><?= htmlspecialchars($h['name']) ?></option>
    <?php endforeach; ?>
  </select>

  <div class="row">
    <div>
      <label for="doctor_id">Requesting Doctor</label>
      <select name="doctor_id" id="doctor_id" required>
        <option value="">-- Select hospital first --</option>
      </select>
    </div>
    <div>
      <label for="patient_id">Patient</label>
      <select name="patient_id" id="patient_id" required>
        <option value="">-- Select hospital first --</option>
      </select>
    </div>
  </div>

  <div class="row">
    <div>
      <label for="group_id">Blood Group</label>
      <select name="group_id" required>
        <option value="">-- Select --</option>
        <?php foreach ($groups as $g): ?>
          <option value="<?= (int)$g['group_id'] ?>"><?= htmlspecialchars($g['group_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label for="component">Component</label>
      <select name="component" required>
        <option>Whole Blood</option>
        <option>RBC</option>
        <option>Plasma</option>
        <option>Platelets</option>
        <option>Cryoprecipitate</option>
      </select>
    </div>
  </div>

  <div class="row">
    <div>
      <label for="units_needed">Units Needed</label>
      <input type="number" name="units_needed" min="1" value="1" required>
    </div>
    <div>
      <label for="urgency">Urgency</label>
      <select name="urgency" required>
        <option>Low</option>
        <option selected>Medium</option>
        <option>High</option>
        <option>Critical</option>
      </select>
    </div>
  </div>

  <label for="notes">Notes</label>
  <textarea name="notes" rows="3" placeholder="Optional clinical notes"></textarea>

  <button type="submit">Submit Request</button>
</form>

<p style="margin-top:20px;"><a href="status.php">View request status &rarr;</a></p>

<script>
// AJAX-load doctors/patients for the chosen hospital, hitting api/requests.php
document.getElementById('hospital_id').addEventListener('change', function () {
  const hospitalId = this.value;
  const doctorSel  = document.getElementById('doctor_id');
  const patientSel = document.getElementById('patient_id');

  doctorSel.innerHTML  = '<option value="">Loading...</option>';
  patientSel.innerHTML = '<option value="">Loading...</option>';
  if (!hospitalId) return;

  fetch('../api/requests.php?action=hospital_options&hospital_id=' + encodeURIComponent(hospitalId))
    .then(r => r.json())
    .then(data => {
      doctorSel.innerHTML = '<option value="">-- Select doctor --</option>' +
        data.doctors.map(d => `<option value="${d.doctor_id}">${d.name} (${d.specialization ?? 'General'})</option>`).join('');
      patientSel.innerHTML = '<option value="">-- Select patient --</option>' +
        data.patients.map(p => `<option value="${p.patient_id}">${p.name}</option>`).join('');
    })
    .catch(() => {
      doctorSel.innerHTML  = '<option value="">Failed to load</option>';
      patientSel.innerHTML = '<option value="">Failed to load</option>';
    });
});
</script>
</body>
</html>
