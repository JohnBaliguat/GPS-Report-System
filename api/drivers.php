<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';

$pdo = db();

// Single driver detail (scorecard breakdown).
if (($id = q('id')) !== null && $id !== '') {
    $id = (int) $id;
    $d = $pdo->prepare('SELECT * FROM drivers WHERE id = ?');
    $d->execute([$id]);
    $driver = $d->fetch();
    if (!$driver) json_out(['status' => 'error', 'message' => 'Driver not found'], 404);

    $agg = $pdo->prepare('SELECT COALESCE(SUM(points),0) pts, COUNT(*) ev FROM events WHERE driver_id = ?');
    $agg->execute([$id]);
    $a = $agg->fetch();
    $score = Scoring::score((int) $a['pts']);

    $bt = $pdo->prepare('SELECT event_type, COUNT(*) c, COALESCE(SUM(points),0) pts,
                                COALESCE(SUM(duration_min),0) dur
                         FROM events WHERE driver_id = ? GROUP BY event_type ORDER BY pts DESC');
    $bt->execute([$id]);
    $breakdown = [];
    foreach ($bt->fetchAll() as $r) {
        $meta = Scoring::TYPES[$r['event_type']] ?? ['label' => $r['event_type'], 'color' => '#94a3b8'];
        $breakdown[] = [
            'type' => $r['event_type'], 'label' => $meta['label'], 'color' => $meta['color'],
            'count' => (int) $r['c'], 'points' => (int) $r['pts'], 'duration' => round((float) $r['dur'], 1),
        ];
    }

    json_out([
        'status' => 'ok',
        'driver' => ['id' => (int) $driver['id'], 'code' => $driver['code'], 'name' => $driver['name']],
        'score' => $score,
        'grade' => Scoring::grade($score),
        'grade_color' => Scoring::gradeColor($score),
        'points' => (int) $a['pts'],
        'events' => (int) $a['ev'],
        'breakdown' => $breakdown,
    ]);
}

// Scorecard list (used by DataTables, client-side is fine — fleet is small).
$sql = "SELECT d.id, d.code, d.name,
        COUNT(e.id) ev,
        COALESCE(SUM(e.points),0) pts,
        COALESCE(SUM(e.event_type='speeding'),0) speeding,
        COALESCE(SUM(e.event_type IN ('restricted_zone','no_parking','overstaying')),0) zone_viol,
        COALESCE(SUM(e.event_type='idling'),0) idling,
        COALESCE(SUM(CASE WHEN e.event_type='idling' THEN e.duration_min ELSE 0 END),0) idle_min
    FROM drivers d LEFT JOIN events e ON e.driver_id = d.id
    GROUP BY d.id ORDER BY pts DESC";
$rows = [];
foreach ($pdo->query($sql)->fetchAll() as $r) {
    $score = Scoring::score((int) $r['pts']);
    $rows[] = [
        'id' => (int) $r['id'],
        'code' => $r['code'],
        'name' => $r['name'],
        'events' => (int) $r['ev'],
        'points' => (int) $r['pts'],
        'speeding' => (int) $r['speeding'],
        'zone_viol' => (int) $r['zone_viol'],
        'idling' => (int) $r['idling'],
        'idle_min' => round((float) $r['idle_min'], 0),
        'score' => $score,
        'grade' => Scoring::grade($score),
        'grade_color' => Scoring::gradeColor($score),
    ];
}
json_out(['data' => $rows]);
