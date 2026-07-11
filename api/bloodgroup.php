<?php
//  reference list of the 8 blood groups.
// GET  /api/blood_groups.php        -> list all groups
// Every other module (donor, patient, blood_unit, blood_request)
// stores group_id (FK) and joins back to this table for the label.

require __DIR__ . '/../connect.php';
$db = getDB();

$stmt = $db->query("SELECT group_id, group_name FROM blood_groups ORDER BY group_id");
$groups = $stmt->fetchAll();

normal(['blood_groups' => $groups]);