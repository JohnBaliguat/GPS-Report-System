<?php
/**
 * Builds the multi-sheet "Driver Violations" workbook from the normalized
 * events table, mirroring the original Report Management export format.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReportExcel
{
    /** Violation event types that count toward a driver's ranking. */
    public const VIOLATION_TYPES = ['speeding', 'restricted_zone', 'no_parking', 'overstaying', 'idling'];

    /**
     * @return array{file:string, filename:string, summary:array, total_drivers:int, total_violations:int}
     */
    public static function build(string $fromDate, string $toDate): array
    {
        $pdo = db();
        $spreadsheet = new Spreadsheet();
        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle('Summary');

        $summary->mergeCells('A1:H1');
        $summary->setCellValue('A1', "GPS Violations Report Summary ($fromDate to $toDate)");
        $summary->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $summary->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $headers = ['Rank', 'Driver', 'Speeding', 'Restricted', 'No-Parking', 'Overstay', 'Idling', 'Total'];
        $summary->fromArray($headers, null, 'A2');
        $summary->getStyle('A2:H2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // Aggregate violations per driver in the period.
        $sql = "SELECT d.id, d.code, d.name,
                  SUM(e.event_type='speeding') AS speeding,
                  SUM(e.event_type='restricted_zone') AS restricted,
                  SUM(e.event_type='no_parking') AS no_parking,
                  SUM(e.event_type='overstaying') AS overstay,
                  SUM(e.event_type='idling') AS idling,
                  SUM(e.event_type IN ('speeding','restricted_zone','no_parking','overstaying','idling')) AS total
                FROM events e JOIN drivers d ON d.id = e.driver_id
                WHERE e.event_type IN ('speeding','restricted_zone','no_parking','overstaying','idling')
                  AND e.event_date BETWEEN ? AND ?
                GROUP BY d.id HAVING total > 0
                ORDER BY total DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fromDate, $toDate]);
        $drivers = $stmt->fetchAll();

        $row = 3;
        $rank = 1;
        $summaryData = [];
        $totalViolations = 0;
        foreach ($drivers as $d) {
            $label = $d['code'] . ($d['name'] ? ' - ' . $d['name'] : '');
            $summary->fromArray([
                $rank, $label, (int) $d['speeding'], (int) $d['restricted'],
                (int) $d['no_parking'], (int) $d['overstay'], (int) $d['idling'], (int) $d['total'],
            ], null, "A$row");
            $summary->getStyle("A$row:H$row")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            if ($rank <= 10) {
                $summaryData[] = [
                    'rank' => $rank, 'driver' => $label,
                    'speeding' => (int) $d['speeding'], 'restricted' => (int) $d['restricted'],
                    'no_parking' => (int) $d['no_parking'], 'overstay' => (int) $d['overstay'],
                    'idling' => (int) $d['idling'], 'total' => (int) $d['total'],
                ];
            }
            $totalViolations += (int) $d['total'];
            $rank++;
            $row++;
        }
        if (!$drivers) {
            $summary->mergeCells("A3:H3");
            $summary->setCellValue('A3', 'No violations found for the selected period.');
            $summary->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        foreach (range('A', 'H') as $col) {
            $summary->getColumnDimension($col)->setAutoSize(true);
        }

        // One detail sheet per driver listed in the Summary, so the two match.
        foreach ($drivers as $d) {
            self::driverSheet($spreadsheet, $pdo, $d, $fromDate, $toDate);
        }

        $filename = 'GPS_Driver_Violations_' . date('Ymd_His') . '.xlsx';
        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;
        (new Xlsx($spreadsheet))->save($tmp);
        $spreadsheet->disconnectWorksheets();

        return [
            'file' => $tmp, 'filename' => $filename, 'summary' => $summaryData,
            'total_drivers' => count($drivers), 'total_violations' => $totalViolations,
        ];
    }

    private static function driverSheet(Spreadsheet $book, PDO $pdo, array $d, string $from, string $to): void
    {
        // Sheet titles: max 31 chars, no special chars.
        $title = preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $d['code'] . ' ' . $d['name']);
        $title = mb_substr(trim($title), 0, 31);
        // Ensure uniqueness
        $base = $title;
        $i = 2;
        while ($book->sheetNameExists($title)) {
            $title = mb_substr($base, 0, 28) . '-' . $i++;
        }
        $sheet = new Worksheet($book, $title);
        $book->addSheet($sheet);

        $sheet->mergeCells('A1:F1');
        $sheet->setCellValue('A1', "Violation Details — {$d['code']} {$d['name']} ($from to $to)");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);
        $r = 3;

        $sections = [
            ['Speeding Violations', 'speeding',
                ['Date', 'Time', 'Location', 'Avg Speed', 'Max Speed', 'Duration (min)'],
                "SELECT event_date, start_dt, zone_name, metric_num2, metric_num, duration_min
                 FROM events WHERE driver_id=? AND event_type='speeding' AND event_date BETWEEN ? AND ? ORDER BY start_dt",
                fn($v) => [$v['event_date'], self::t($v['start_dt']), $v['zone_name'], $v['metric_num2'], $v['metric_num'], $v['duration_min']]],

            ['Restricted Zone Visits', 'restricted_zone',
                ['Date', 'Zone', 'Entrance', 'Entrance Place', 'Exit', 'Duration (min)'],
                "SELECT event_date, start_dt, end_dt, zone_name, entrance_place, duration_min
                 FROM events WHERE driver_id=? AND event_type='restricted_zone' AND event_date BETWEEN ? AND ? ORDER BY start_dt",
                fn($v) => [$v['event_date'], $v['zone_name'], self::t($v['start_dt']), $v['entrance_place'], self::t($v['end_dt']), $v['duration_min']]],

            ['No-Parking Zone Visits', 'no_parking',
                ['Date', 'Zone', 'Entrance', 'Exit', 'Place', 'Duration (min)'],
                "SELECT event_date, start_dt, end_dt, zone_name, entrance_place, duration_min
                 FROM events WHERE driver_id=? AND event_type='no_parking' AND event_date BETWEEN ? AND ? ORDER BY start_dt",
                fn($v) => [$v['event_date'], $v['zone_name'], self::t($v['start_dt']), self::t($v['end_dt']), $v['entrance_place'], $v['duration_min']]],

            ['Overstaying', 'overstaying',
                ['Date', 'Zone', 'Entrance', 'Exit', 'Place', 'Duration (min)'],
                "SELECT event_date, start_dt, end_dt, zone_name, entrance_place, duration_min
                 FROM events WHERE driver_id=? AND event_type='overstaying' AND event_date BETWEEN ? AND ? ORDER BY start_dt",
                fn($v) => [$v['event_date'], $v['zone_name'], self::t($v['start_dt']), self::t($v['end_dt']), $v['entrance_place'], $v['duration_min']]],

            ['Idling', 'idling',
                ['Date', 'Group', 'Idling (min)', 'Idling %', '', ''],
                "SELECT event_date, zone_name, duration_min, metric_num
                 FROM events WHERE driver_id=? AND event_type='idling' AND event_date BETWEEN ? AND ? ORDER BY event_date",
                fn($v) => [$v['event_date'], $v['zone_name'], $v['duration_min'], round(((float) $v['metric_num']) * 100, 1) . '%', '', '']],
        ];

        foreach ($sections as [$label, $type, $cols, $query, $mapper]) {
            $sheet->setCellValue("A$r", $label);
            $sheet->getStyle("A$r")->getFont()->setBold(true);
            $r++;
            $sheet->fromArray($cols, null, "A$r");
            $sheet->getStyle("A$r:F$r")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ]);
            $r++;
            $st = $pdo->prepare($query);
            $st->execute([$d['id'], $from, $to]);
            $rows = $st->fetchAll();
            if (!$rows) {
                $sheet->setCellValue("A$r", 'No records.');
                $sheet->getStyle("A$r")->getFont()->setItalic(true);
                $r += 2;
                continue;
            }
            foreach ($rows as $v) {
                $sheet->fromArray($mapper($v), null, "A$r");
                $sheet->getStyle("A$r:F$r")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $r++;
            }
            $r++;
        }

        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private static function t(?string $dt): string
    {
        return $dt ? date('H:i', strtotime($dt)) : '';
    }
}
