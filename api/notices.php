<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../lib/Notices.php';

$pdo = db();
$canManage = Auth::role() !== 'viewer';
$action = q('action', '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canManage) json_out(['status' => 'error', 'message' => 'You do not have permission for this action.'], 403);

    if ($action === 'generate') {
        $from = (string) q('from', '');
        $to   = (string) q('to', '');
        if (!$from || !$to) json_out(['status' => 'error', 'message' => 'Select a date range.'], 400);
        $me = Auth::user();
        $res = Notices::generate($from, $to, [
            'counseling_date'  => q('counseling_date', '') ?: null,
            'counseling_time'  => q('counseling_time', ''),
            'counseling_venue' => q('counseling_venue', ''),
            'issued_by'        => q('issued_by', '') ?: ($me['name'] ?? ''),
            'created_by'       => $me['name'] ?? '',
        ]);
        $msg = "Generated {$res['created']} notice(s) from {$res['drivers']} violating driver(s)"
             . ($res['skipped'] ? ", skipped {$res['skipped']} already existing." : '.');
        json_out(['status' => 'ok', 'message' => $msg] + $res);
    }

    if ($action === 'update_status') {
        $id     = (int) q('id');
        $status = (string) q('status', 'pending');
        if (!in_array($status, ['pending', 'scheduled', 'completed', 'no_show'], true)) {
            json_out(['status' => 'error', 'message' => 'Invalid status.'], 400);
        }
        $me = Auth::user();
        $counseledAt = in_array($status, ['completed', 'no_show'], true) ? date('Y-m-d H:i:s') : null;
        $counseledBy = $counseledAt ? ($me['name'] ?? '') : '';
        $pdo->prepare('UPDATE violation_notices SET status=?, counseled_at=?, counseled_by=?, remarks=? WHERE id=?')
            ->execute([$status, $counseledAt, $counseledBy, trim((string) q('remarks', '')), $id]);
        json_out(['status' => 'ok']);
    }

    if ($action === 'update_schedule') {
        $pdo->prepare('UPDATE violation_notices SET counseling_date=?, counseling_time=?, counseling_venue=?, location=?,
                       status=IF(status="pending" AND ?<>"", "scheduled", status) WHERE id=?')
            ->execute([
                q('counseling_date', '') ?: null, q('counseling_time', ''), q('counseling_venue', ''),
                q('location', ''), q('counseling_date', ''), (int) q('id'),
            ]);
        json_out(['status' => 'ok']);
    }

    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM violation_notices WHERE id=?')->execute([(int) q('id')]);
        json_out(['status' => 'ok']);
    }

    json_out(['status' => 'error', 'message' => 'Unknown action.'], 400);
}

// GET single
if (($id = q('id')) !== null && $id !== '') {
    $st = $pdo->prepare('SELECT * FROM violation_notices WHERE id=?');
    $st->execute([(int) $id]);
    $row = $st->fetch();
    if (!$row) json_out(['status' => 'error', 'message' => 'Not found'], 404);
    json_out(['status' => 'ok', 'data' => $row]);
}

// GET list (+ summary counts for the cards)
$rows = $pdo->query('SELECT id, control_no, driver_name, unit_no, period_from, period_to, location,
                            c_speeding, c_restricted, c_no_parking, c_overstay, c_idle, total,
                            counseling_date, counseling_time, counseling_venue, status, counseled_at, counseled_by
                     FROM violation_notices ORDER BY id DESC')->fetchAll();
$summary = $pdo->query("SELECT
        COUNT(*) total,
        SUM(status='pending') pending,
        SUM(status='scheduled') scheduled,
        SUM(status='completed') completed,
        SUM(status='no_show') no_show
    FROM violation_notices")->fetch();
json_out(['data' => $rows, 'summary' => $summary]);
