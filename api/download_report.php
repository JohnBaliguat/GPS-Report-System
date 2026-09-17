<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/ReportExcel.php';

Auth::start();
if (!Auth::check() || Auth::role() === 'viewer') {
    http_response_code(403);
    exit('Forbidden');
}

$from = $_GET['fromDate'] ?? '';
$to   = $_GET['toDate'] ?? '';
if (!$from || !$to) {
    http_response_code(400);
    exit('Select a date range.');
}

$report = ReportExcel::build($from, $to);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $report['filename'] . '"');
header('Content-Length: ' . filesize($report['file']));
header('Cache-Control: no-store');
readfile($report['file']);
@unlink($report['file']);
exit;
