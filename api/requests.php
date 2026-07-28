<?php
require __DIR__ . '/../connect.php';
$db = getDB();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

// GET /api/requests.php?action=hospital_options&hospital_id=1
// Returns doctors + patients for a hospital, used by requests/request_form.php
if ($method === 'GET' && $action === 'hospital_options') {
    $hospitalId = (int)($_GET['hospital_id'] ?? 0);
    if (!$hospitalId) err('hospital_id is required');

    $doctors = $db->prepare("SELECT doctor_id, name, specialization FROM doctor WHERE hospital_id = :hid ORDER BY name");
    $doctors->execute([':hid' => $hospitalId]);

    $patients = $db->prepare("SELECT patient_id, name FROM patient WHERE hospital_id = :hid ORDER BY name");
    $patients->execute([':hid' => $hospitalId]);

    normal([
        'doctors'  => $doctors->fetchAll(),
        'patients' => $patients->fetchAll(),
    ]);
}

// GET /api/requests.php            -> list all requests (optionally ?status=Pending)
// GET /api/requests.php?id=5       -> single request
if ($method === 'GET') {
    $id = $_GET['id'] ?? null;

    $base = "
        SELECT r.request_id, r.units_needed, r.urgency, r.status, r.request_date, r.notes, r.component,
               p.name AS patient_name, h.name AS hospital_name, d.name AS doctor_name, bg.group_name
        FROM blood_request r
        JOIN patient p ON p.patient_id = r.patient_id
        JOIN hospital h ON h.hospital_id = p.hospital_id
        JOIN doctor d ON d.doctor_id = r.doctor_id
        JOIN blood_groups bg ON bg.group_id = r.group_id
    ";

    if ($id !== null) {
        $stmt = $db->prepare($base . " WHERE r.request_id = :id");
        $stmt->execute([':id' => (int)$id]);
        $row = $stmt->fetch();
        if (!$row) err('Request not found', 404);
        normal($row);
    }

    $status = $_GET['status'] ?? null;
    if ($status) {
        $stmt = $db->prepare($base . " WHERE r.status = :status ORDER BY r.request_date DESC");
        $stmt->execute([':status' => $status]);
    } else {
        $stmt = $db->query($base . " ORDER BY r.request_date DESC");
    }
    normal(['requests' => $stmt->fetchAll()]);
}

// POST /api/requests.php -> create a new request
// body: { patient_id, doctor_id, group_id, component, units_needed, urgency, notes }
if ($method === 'POST') {
    $data = body();
    foreach (['patient_id', 'doctor_id', 'group_id'] as $field) {
        if (empty($data[$field])) err("$field is required");
    }


    $stmt = $db->prepare("
        INSERT INTO blood_request (patient_id, doctor_id, group_id, component, units_needed, urgency, status, notes)
        VALUES (:patient_id, :doctor_id, :group_id, :component, :units_needed, :urgency, 'Pending', :notes)
        RETURNING request_id
    ");
    $stmt->execute([
        ':patient_id'   => (int)$data['patient_id'],
        ':doctor_id'    => (int)$data['doctor_id'],
        ':group_id'     => (int)$data['group_id'],
        ':component'    => $data['component'] ?? 'Whole Blood',
        ':units_needed' => max(1, (int)($data['units_needed'] ?? 1)),
        ':urgency'      => $data['urgency'] ?? 'Medium',
        ':notes'        => $data['notes'] ?? null,
    ]);

    normal(['request_id' => (int)$stmt->fetchColumn(), 'status' => 'Pending']);
}

if ($method === 'PUT') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) err('id is required');

    $data = body();
    $validStatuses = ['Pending', 'Processing', 'Fulfilled', 'Cancelled'];
    if (empty($data['status']) || !in_array($data['status'], $validStatuses, true)) {
        err('Valid status is required: ' . implode(', ', $validStatuses));
    }

    $stmt = $db->prepare("UPDATE blood_request SET status = :status WHERE request_id = :id");
    $stmt->execute([':status' => $data['status'], ':id' => $id]);

    if ($stmt->rowCount() === 0) err('Request not found', 404);
    normal(['request_id' => $id, 'status' => $data['status']]);
}

err('Method not supported', 405);
