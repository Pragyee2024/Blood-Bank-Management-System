<?php
declare(strict_types=1);
require_once __DIR__ . '/../connect.php';
require_once __DIR__ . '/../includes/auth.php';
// connect.php already sets JSON + CORS headers, so no override needed here.

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

// ── GET /api/donors.php?search=&group_id= ─────────────────────
// List / search donors. Staff & admin only.
if ($method === 'GET') {
    require_login(['admin', 'staff'], true);

    $search = trim($_GET['search'] ?? '');
    $group  = $_GET['group_id'] ?? '';

    $sql = 'SELECT d.donor_id, d.name, d.phone, d.email, d.gender, d.dob,
                   bg.group_name, d.last_donation, d.total_donations,
                   d.is_eligible, d.health_status
            FROM donor d
            JOIN blood_groups bg ON bg.group_id = d.group_id
            WHERE 1=1';
    $params = [];

    if ($search !== '') {
        $sql .= ' AND (d.name ILIKE :search OR d.phone ILIKE :search)';
        $params['search'] = '%' . $search . '%';
    }
    if ($group !== '') {
        $sql .= ' AND d.group_id = :group_id';
        $params['group_id'] = $group;
    }
    $sql .= ' ORDER BY d.name';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    normal(['donors' => $stmt->fetchAll()]);
}

// ── POST /api/donors.php ───────────────────────────────────────
// Add a new donor. Staff & admin only. Body: JSON
// { name, dob?, gender?, group_id, phone, email?, address? }
if ($method === 'POST') {
    require_login(['admin', 'staff'], true);

    $data = body();
    foreach (['name', 'group_id', 'phone'] as $field) {
        if (empty($data[$field])) {
            err("Field '$field' is required.");
        }
    }

    $groupCheck = $db->prepare('SELECT 1 FROM blood_groups WHERE group_id = :id');
    $groupCheck->execute(['id' => $data['group_id']]);
    if (!$groupCheck->fetch()) {
        err('Invalid group_id.');
    }

    $stmt = $db->prepare(
        'INSERT INTO donor (name, dob, gender, group_id, phone, email, address)
         VALUES (:name, :dob, :gender, :group_id, :phone, :email, :address)
         RETURNING donor_id'
    );
    $stmt->execute([
        'name'     => $data['name'],
        'dob'      => $data['dob'] ?? null,
        'gender'   => $data['gender'] ?? 'Male',
        'group_id' => $data['group_id'],
        'phone'    => $data['phone'],
        'email'    => $data['email'] ?? null,
        'address'  => $data['address'] ?? null,
    ]);

    normal([
        'donor_id' => $stmt->fetchColumn(),
        'message'  => 'Donor added successfully.',
    ]);
}

err('Method not allowed', 405);
