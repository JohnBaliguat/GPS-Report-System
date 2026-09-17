<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';

$pdo = db();
[$where, $args] = event_filters();

// KPI cards
$kpi = $pdo->prepare("SELECT
    COUNT(*) AS total_events,
    COUNT(DISTINCT e.driver_id) AS drivers,
    COALESCE(SUM(e.points),0) AS points,
    COALESCE(SUM(e.event_type IN ('speeding','restricted_zone','no_parking','overstaying')),0) AS violations,
    COALESCE(SUM(e.event_type='speeding'),0) AS speeding,
    COALESCE(SUM(e.event_type='idling'),0) AS idling
  FROM events e $where");
$kpi->execute($args);
$k = $kpi->fetch();

// Events grouped by type (doughnut)
$byType = $pdo->prepare("SELECT e.event_type, COUNT(*) c FROM events e $where GROUP BY e.event_type ORDER BY c DESC");
$byType->execute($args);
$types = [];
foreach ($byType->fetchAll() as $r) {
    $meta = Scoring::TYPES[$r['event_type']] ?? ['label' => $r['event_type'], 'color' => '#94a3b8'];
    $types[] = ['type' => $r['event_type'], 'label' => $meta['label'], 'color' => $meta['color'], 'count' => (int) $r['c']];
}

// Daily trend (line). Append the date guard correctly whether or not filters exist.
$dateGuard = $where ? "$where AND e.event_date IS NOT NULL" : ' WHERE e.event_date IS NOT NULL';
$trendSql  = "SELECT e.event_date d,
        SUM(e.event_type IN ('speeding','restricted_zone','no_parking','overstaying')) viol,
        SUM(e.event_type='idling') idle
    FROM events e $dateGuard GROUP BY e.event_date ORDER BY e.event_date";
$trend = $pdo->prepare($trendSql);
$trend->execute($args);
$trendRows = $trend->fetchAll();

// Worst drivers by demerit points (bar)
$worstSql = "SELECT d.id, d.code, d.name, COALESCE(SUM(e.points),0) pts, COUNT(*) ev
    FROM events e JOIN drivers d ON d.id = e.driver_id $where
    GROUP BY d.id ORDER BY pts DESC LIMIT 10";
$worst = $pdo->prepare($worstSql);
$worst->execute($args);
$worstRows = [];
foreach ($worst->fetchAll() as $r) {
    $worstRows[] = [
        'driver_id' => (int) $r['id'],
        'label' => $r['code'] . ($r['name'] ? ' · ' . $r['name'] : ''),
        'points' => (int) $r['pts'],
        'events' => (int) $r['ev'],
    ];
}

json_out([
    'kpi' => [
        'total_events' => (int) $k['total_events'],
        'drivers'      => (int) $k['drivers'],
        'violations'   => (int) $k['violations'],
        'points'       => (int) $k['points'],
        'speeding'     => (int) $k['speeding'],
        'idling'       => (int) $k['idling'],
    ],
    'by_type' => $types,
    'trend'   => $trendRows,
    'worst'   => $worstRows,
]);
