<?php
declare(strict_types=1);
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../includes/auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

// Require login for all inventory API requests
$user = require_login(['admin', 'staff'], true);

// ── GET /api/inventory.php ────────────────────────────────────
// Retrieve and filter blood units
if ($method === 'GET') {
    $id = $_GET['id'] ?? null;

    if ($id !== null) {
        $stmt = $db->prepare('
            SELECT bu.*, bg.group_name, d.name AS donor_name, bb.name AS bank_name
            FROM blood_unit bu
            JOIN blood_groups bg ON bg.group_id = bu.group_id
            JOIN donor d ON d.donor_id = bu.donor_id
            JOIN blood_bank bb ON bb.bank_id = bu.bank_id
            WHERE bu.unit_id = :id
        ');
        $stmt->execute(['id' => (int)$id]);
        $unit = $stmt->fetch();
        if (!$unit) {
            err('Blood unit not found', 404);
        }
        normal($unit);
    }

    $groupId      = $_GET['group_id'] ?? '';
    $component    = $_GET['component'] ?? '';
    $bankId       = $_GET['bank_id'] ?? '';
    $status       = $_GET['status'] ?? 'Available'; // Default to Available
    $expiringSoon = (int)($_GET['expiring_soon'] ?? 0);

    $sql = '
        SELECT bu.unit_id, bu.donor_id, bu.bank_id, bu.group_id, bu.component,
               bu.volume_ml, bu.collection_date, bu.expiry_date, bu.status,
               bg.group_name, d.name AS donor_name, bb.name AS bank_name
        FROM blood_unit bu
        JOIN blood_groups bg ON bg.group_id = bu.group_id
        JOIN donor d ON d.donor_id = bu.donor_id
        JOIN blood_bank bb ON bb.bank_id = bu.bank_id
        WHERE 1=1
    ';
    $params = [];

    if ($groupId !== '') {
        $sql .= ' AND bu.group_id = :group_id';
        $params['group_id'] = (int)$groupId;
    }
    if ($component !== '') {
        $sql .= ' AND bu.component = :component';
        $params['component'] = $component;
    }
    if ($bankId !== '') {
        $sql .= ' AND bu.bank_id = :bank_id';
        $params['bank_id'] = (int)$bankId;
    }
    if ($status !== 'All') {
        $sql .= ' AND bu.status = :status';
        $params['status'] = $status;
    }
    if ($expiringSoon === 1) {
        $sql .= ' AND bu.expiry_date BETWEEN CURRENT_DATE AND (CURRENT_DATE + INTERVAL \'7 days\')';
    }

    $sql .= ' ORDER BY bu.expiry_date ASC, bu.unit_id DESC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    normal(['units' => $stmt->fetchAll()]);
}

// ── POST /api/inventory.php ───────────────────────────────────
// Add a new blood unit (Calls PostgreSQL stored function sp_add_blood_unit)
if ($method === 'POST') {
    $data = body();
    foreach (['donor_id', 'bank_id', 'component', 'volume_ml'] as $field) {
        if (empty($data[$field])) {
            err("Field '$field' is required.");
        }
    }

    $donorId  = (int)$data['donor_id'];
    $bankId   = (int)$data['bank_id'];
    $comp     = trim($data['component']);
    $volume   = (int)$data['volume_ml'];

    // Verify donor exists
    $donorCheck = $db->prepare('SELECT 1 FROM donor WHERE donor_id = :id');
    $donorCheck->execute(['id' => $donorId]);
    if (!$donorCheck->fetch()) {
        err('Donor does not exist.');
    }

    // Verify bank exists
    $bankCheck = $db->prepare('SELECT 1 FROM blood_bank WHERE bank_id = :id');
    $bankCheck->execute(['id' => $bankId]);
    if (!$bankCheck->fetch()) {
        err('Blood bank does not exist.');
    }

    // Call stored procedure sp_add_blood_unit
    try {
        $stmt = $db->prepare('SELECT * FROM sp_add_blood_unit(:donor_id, :bank_id, :component, :volume_ml)');
        $stmt->execute([
            'donor_id'  => $donorId,
            'bank_id'   => $bankId,
            'component' => $comp,
            'volume_ml' => $volume,
        ]);
        $result = $stmt->fetch();
        if (!$result) {
            err('Failed to record blood unit.');
        }

        normal([
            'success'     => true,
            'unit_id'     => $result['unit_id'],
            'expiry_date' => $result['expiry_date'],
            'message'     => 'Blood unit added successfully via stored procedure.'
        ]);
    } catch (Exception $e) {
        err('Database execution error: ' . $e->getMessage());
    }
}

// ── PUT /api/inventory.php ────────────────────────────────────
// Update an existing blood unit (triggers automatic DB trigger checks)
if ($method === 'PUT') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        err('ID is required.');
    }

    $data = body();
    
    // Check if blood unit exists
    $check = $db->prepare('SELECT 1 FROM blood_unit WHERE unit_id = :id');
    $check->execute(['id' => $id]);
    if (!$check->fetch()) {
        err('Blood unit not found.', 404);
    }

    $fields = [];
    $params = ['id' => $id];

    if (isset($data['status'])) {
        $fields[] = 'status = :status';
        $params['status'] = $data['status'];
    }
    if (isset($data['component'])) {
        $fields[] = 'component = :component';
        $params['component'] = $data['component'];
    }
    if (isset($data['volume_ml'])) {
        $fields[] = 'volume_ml = :volume_ml';
        $params['volume_ml'] = (int)$data['volume_ml'];
    }
    if (isset($data['expiry_date'])) {
        $fields[] = 'expiry_date = :expiry_date';
        $params['expiry_date'] = $data['expiry_date'];
    }
    if (isset($data['bank_id'])) {
        $fields[] = 'bank_id = :bank_id';
        $params['bank_id'] = (int)$data['bank_id'];
    }

    if (empty($fields)) {
        err('No fields to update.');
    }

    $sql = 'UPDATE blood_unit SET ' . implode(', ', $fields) . ' WHERE unit_id = :id';
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        normal([
            'success' => true,
            'message' => 'Blood unit updated successfully.'
        ]);
    } catch (Exception $e) {
        err('Database update error: ' . $e->getMessage());
    }
}

// ── DELETE /api/inventory.php ─────────────────────────────────
// Delete an existing blood unit (demonstrates cascade constraint checking)
if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        err('ID is required.');
    }

    // Verify unit exists
    $check = $db->prepare('SELECT 1 FROM blood_unit WHERE unit_id = :id');
    $check->execute(['id' => $id]);
    if (!$check->fetch()) {
        err('Blood unit not found.', 404);
    }

    try {
        $stmt = $db->prepare('DELETE FROM blood_unit WHERE unit_id = :id');
        $stmt->execute(['id' => $id]);
        normal([
            'success' => true,
            'message' => 'Blood unit deleted successfully.'
        ]);
    } catch (Exception $e) {
        err('Database delete error: ' . $e->getMessage());
    }
}

err('Method not allowed', 405);
