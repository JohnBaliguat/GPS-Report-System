<?php
/** Streams the filtered events as a styled .xlsx (Report Builder export). */
declare(strict_types=1);
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/Scoring.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

Auth::start();
if (!Auth::check()) { http_response_code(403); exit('Forbidden'); }

$pdo = db();
$where = [];
$args = [];
foreach (['type' => 'e.event_type', 'driver_id' => 'e.driver_id'] as $p => $col) {
    if (!empty($_GET[$p])) { $where[] = "$col = ?"; $args[] = $_GET[$p]; }
}
if (!empty($_GET['from'])) { $where[] = 'e.event_date >= ?'; $args[] = $_GET['from']; }
if (!empty($_GET['to']))   { $where[] = 'e.event_date <= ?'; $args[] = $_GET['to']; }
$sql = "SELECT e.event_date, e.start_dt, e.event_type, d.code, d.name, e.zone_name,
               e.duration_min, e.metric_num, e.metric_num2, e.points
        FROM events e JOIN drivers d ON d.id=e.driver_id"
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . ' ORDER BY e.event_date DESC, e.id DESC';
$st = $pdo->prepare($sql);
$st->execute($args);

$book = new Spreadsheet();
$sheet = $book->getActiveSheet();
$sheet->setTitle('Report');

$typeLabel = !empty($_GET['type']) ? (Scoring::TYPES[$_GET['type']]['label'] ?? $_GET['type']) : 'All Events';
$range = (($_GET['from'] ?? '') ?: '…') . ' to ' . (($_GET['to'] ?? '') ?: '…');
$sheet->mergeCells('A1:I1');
$sheet->setCellValue('A1', "FleetIQ Report — $typeLabel ($range)");
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

$headers = ['Date', 'Time', 'Type', 'Driver Code', 'Driver Name', 'Zone / Location', 'Duration (min)', 'Metric', 'Points'];
$sheet->fromArray($headers, null, 'A3');
$sheet->getStyle('A3:I3')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
]);

$row = 4;
foreach ($st->fetchAll() as $r) {
    $label = Scoring::TYPES[$r['event_type']]['label'] ?? $r['event_type'];
    $sheet->fromArray([
        $r['event_date'],
        $r['start_dt'] ? date('H:i', strtotime($r['start_dt'])) : '',
        $label, $r['code'], $r['name'], $r['zone_name'],
        $r['duration_min'], $r['metric_num'], (int) $r['points'],
    ], null, "A$row");
    $sheet->getStyle("A$row:I$row")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $row++;
}
if ($row === 4) { $sheet->setCellValue('A4', 'No records match the selected filters.'); }
foreach (range('A', 'I') as $c) { $sheet->getColumnDimension($c)->setAutoSize(true); }

$filename = 'FleetIQ_Report_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');
(new Xlsx($book))->save('php://output');
exit;
