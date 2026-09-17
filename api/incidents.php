<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';

$pdo = db();
$canManage = Auth::role() !== 'viewer';
$action = q('action', '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canManage) json_out(['status' => 'error', 'message' => 'You do not have permission for this action.'], 403);

    if ($action === 'delete') {
        $id = (int) q('id');
        $row = $pdo->prepare('SELECT photo FROM incidents WHERE id=?');
        $row->execute([$id]);
        if ($p = $row->fetchColumn()) { @unlink(__DIR__ . '/../uploads/' . $p); }
        $pdo->prepare('DELETE FROM incidents WHERE id=?')->execute([$id]);
        json_out(['status' => 'ok']);
    }

    // create / update
    $id          = (int) q('id', 0);
    $incidentDate= trim((string) q('incident_date', ''));
    $driverId    = q('driver_id', '') !== '' ? (int) q('driver_id') : null;
    $truck       = trim((string) q('truck_code', ''));
    $itype       = trim((string) q('itype', 'Other'));
    $severity    = (string) q('severity', 'medium');
    $lat         = q('lat', '') !== '' ? (float) q('lat') : null;
    $lng         = q('lng', '') !== '' ? (float) q('lng') : null;
    $address     = trim((string) q('address', ''));
    $description = trim((string) q('description', ''));
    $status      = (string) q('status', 'open');

    if (!in_array($severity, ['low', 'medium', 'high', 'critical'], true)) $severity = 'medium';
    if (!in_array($status, ['open', 'investigating', 'resolved', 'closed'], true)) $status = 'open';
    if ($incidentDate === '') json_out(['status' => 'error', 'message' => 'Incident date/time is required.'], 400);
    $incidentDate = str_replace('T', ' ', $incidentDate);

    // optional photo upload
    $photo = null;
    if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        if ($_FILES['photo']['size'] > 5 * 1024 * 1024) json_out(['status' => 'error', 'message' => 'Photo must be under 5 MB.'], 400);
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) json_out(['status' => 'error', 'message' => 'Photo must be an image.'], 400);
        $dir = __DIR__ . '/../uploads';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        $photo = 'incident_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
        move_uploaded_file($_FILES['photo']['tmp_name'], "$dir/$photo");
    }

    if ($id > 0) {
        $sql = 'UPDATE incidents SET incident_date=?, driver_id=?, truck_code=?, itype=?, severity=?,
                lat=?, lng=?, address=?, description=?, status=?' . ($photo ? ', photo=?' : '') . ' WHERE id=?';
        $args = [$incidentDate, $driverId, $truck, $itype, $severity, $lat, $lng, $address, $description, $status];
        if ($photo) $args[] = $photo;
        $args[] = $id;
        $pdo->prepare($sql)->execute($args);
        json_out(['status' => 'ok', 'id' => $id]);
    } else {
        $me = Auth::user();
        $pdo->prepare('INSERT INTO incidents (incident_date, driver_id, truck_code, itype, severity, lat, lng, address, description, status, photo, reported_by)
                       VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$incidentDate, $driverId, $truck, $itype, $severity, $lat, $lng, $address, $description, $status, $photo ?? '', $me['name'] ?? '']);
        json_out(['status' => 'ok', 'id' => (int) $pdo->lastInsertId()]);
    }
}

// GET single
if (($id = q('id')) !== null && $id !== '') {
    $st = $pdo->prepare('SELECT i.*, d.code AS driver_code, d.name AS driver_name
                         FROM incidents i LEFT JOIN drivers d ON d.id=i.driver_id WHERE i.id=?');
    $st->execute([(int) $id]);
    $row = $st->fetch();
    if (!$row) json_out(['status' => 'error', 'message' => 'Not found'], 404);
    json_out(['status' => 'ok', 'data' => $row]);
}

// GET list
$rows = $pdo->query('SELECT i.id, i.incident_date, i.truck_code, i.itype, i.severity, i.status,
                            i.address, i.lat, i.lng, i.photo, i.reported_by,
                            d.code AS driver_code, d.name AS driver_name
                     FROM incidents i LEFT JOIN drivers d ON d.id=i.driver_id
                     ORDER BY i.incident_date DESC')->fetchAll();
json_out(['data' => $rows]);
