<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';

$pdo = db();

// Delete an import and its events.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && q('action') === 'delete') {
    $id = (int) q('id');
    $pdo->prepare('DELETE FROM events WHERE import_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM imports WHERE id = ?')->execute([$id]);
    // Drop drivers that no longer have any events.
    $pdo->exec('DELETE d FROM drivers d LEFT JOIN events e ON e.driver_id=d.id WHERE e.id IS NULL');
    json_out(['status' => 'ok']);
}

$rows = $pdo->query(
    "SELECT i.*, (SELECT COUNT(*) FROM events e WHERE e.import_id=i.id) ev
     FROM imports i ORDER BY i.created_at DESC"
)->fetchAll();

$data = [];
foreach ($rows as $r) {
    $meta = Scoring::TYPES[$r['report_type']] ?? ['label' => $r['report_type'], 'color' => '#94a3b8'];
    $data[] = [
        'id' => (int) $r['id'],
        'filename' => $r['filename'],
        'type' => $r['report_type'],
        'type_label' => $meta['label'],
        'type_color' => $meta['color'],
        'category' => $r['category'],
        'period' => trim(((string) $r['period_from']) . ' – ' . ((string) $r['period_to']), ' –'),
        'events' => (int) $r['ev'],
        'imported' => $r['created_at'],
    ];
}
json_out(['data' => $data]);
