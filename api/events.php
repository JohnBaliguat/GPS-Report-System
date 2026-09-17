<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';

$pdo  = db();
$draw = (int) q('draw', 1);
$start = (int) q('start', 0);
$length = (int) q('length', 25);
if ($length <= 0 || $length > 200) $length = 25;

[$where, $args] = event_filters();

// Global search box
$search = trim((string) ($_GET['search']['value'] ?? ''));
if ($search !== '') {
    $clause = '(d.code LIKE ? OR d.name LIKE ? OR e.zone_name LIKE ? OR e.entrance_place LIKE ?)';
    $where .= ($where ? ' AND ' : ' WHERE ') . $clause;
    $like = '%' . $search . '%';
    array_push($args, $like, $like, $like, $like);
}

$total = (int) $pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();

$filtStmt = $pdo->prepare("SELECT COUNT(*) FROM events e JOIN drivers d ON d.id=e.driver_id $where");
$filtStmt->execute($args);
$filtered = (int) $filtStmt->fetchColumn();

// Sorting
$cols = ['event_date', 'event_type', 'code', 'zone_name', 'duration_min', 'metric_num', 'points'];
$orderCol = $cols[(int) ($_GET['order'][0]['column'] ?? 0)] ?? 'event_date';
$map = ['code' => 'd.code', 'event_date' => 'e.event_date', 'event_type' => 'e.event_type',
        'zone_name' => 'e.zone_name', 'duration_min' => 'e.duration_min', 'metric_num' => 'e.metric_num', 'points' => 'e.points'];
$orderBy = $map[$orderCol] ?? 'e.event_date';
$dir = (strtolower((string) ($_GET['order'][0]['dir'] ?? 'desc')) === 'asc') ? 'ASC' : 'DESC';

$sql = "SELECT e.id, e.event_type, e.zone_name, e.event_date, e.start_dt, e.end_dt,
               e.duration_min, e.metric_num, e.metric_num2, e.entrance_place, e.points, e.severity,
               d.code, d.name, d.id AS driver_id
        FROM events e JOIN drivers d ON d.id = e.driver_id
        $where ORDER BY $orderBy $dir, e.id DESC LIMIT $length OFFSET $start";
$stmt = $pdo->prepare($sql);
$stmt->execute($args);

$rows = [];
foreach ($stmt->fetchAll() as $r) {
    $meta = Scoring::TYPES[$r['event_type']] ?? ['label' => $r['event_type'], 'color' => '#94a3b8'];
    $rows[] = [
        'id'        => (int) $r['id'],
        'driver_id' => (int) $r['driver_id'],
        'date'      => $r['event_date'],
        'time'      => $r['start_dt'] ? date('H:i', strtotime($r['start_dt'])) : '',
        'type'      => $r['event_type'],
        'type_label' => $meta['label'],
        'type_color' => $meta['color'],
        'driver'    => $r['code'] . ($r['name'] ? ' · ' . $r['name'] : ''),
        'zone'      => $r['zone_name'] ?: $r['entrance_place'],
        'duration'  => $r['duration_min'] !== null ? (float) $r['duration_min'] : null,
        'metric'    => $r['metric_num'] !== null ? (float) $r['metric_num'] : null,
        'metric2'   => $r['metric_num2'] !== null ? (float) $r['metric_num2'] : null,
        'points'    => (int) $r['points'],
        'severity'  => (int) $r['severity'],
    ];
}

json_out([
    'draw' => $draw,
    'recordsTotal' => $total,
    'recordsFiltered' => $filtered,
    'data' => $rows,
]);
