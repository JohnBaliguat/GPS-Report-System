<?php
/**
 * Detects the Geotab report type from an .xlsx workbook and normalizes it into
 * a flat list of driver events ready to be stored.
 */
declare(strict_types=1);

require_once __DIR__ . '/XlsxReader.php';
require_once __DIR__ . '/Scoring.php';

class ReportParser
{
    private array $sheets;
    public string $reportType = 'unknown';
    public string $reportTitle = '';
    public string $category = '';
    public string $company = '';
    public ?string $periodFrom = null;
    public ?string $periodTo = null;
    /** @var array<int,array<string,mixed>> */
    public array $events = [];

    public function __construct(array $sheets)
    {
        $this->sheets = $sheets;
    }

    public static function fromFile(string $path): self
    {
        $self = new self(XlsxReader::read($path));
        $self->parse();
        return $self;
    }

    public function parse(): void
    {
        $this->detectPeriodAndCompany();

        // Geotab "generic" reports carry a 'Report' + hidden 'Data' sheet.
        if (isset($this->sheets['Report']) && isset($this->sheets['Data'])) {
            $title = $this->firstText($this->sheets['Report']);
            $this->reportTitle = $title;
            // Detect exceptions/events reports by their Data columns (title varies:
            // "Exceptions Detail Report", "Events Detail Report", "Advanced Exceptions…").
            $isExceptions = stripos($title, 'Exception') !== false
                         || stripos($title, 'Events Detail') !== false
                         || $this->dataHasColumn('ExceptionRule')
                         || $this->dataHasColumn('ExceptionDetail');
            if ($isExceptions) {
                $this->reportType = 'exception';
                $this->category   = 'Advanced Exceptions';
                $this->reportTitle = $title;
                $this->parseExceptions();
            } elseif (stripos($title, 'Idling') !== false) {
                $this->reportType = 'idling';
                $this->category   = 'Idling Violations';
                $this->parseIdling();
            } elseif (stripos($title, 'Speed') !== false) {
                $this->reportType = 'speeding';
                $this->category   = 'Speeding Violations';
                $this->parseSpeedSummary();
            }
            return;
        }

        // Otherwise it's a per-driver "sheet-per-tracker" report.
        $title = $this->detectDriverSheetTitle();
        $this->reportTitle = $title;
        $this->category    = trim(explode(':', $title)[0]);

        if (stripos($title, 'Stops/parkings') !== false) {
            $this->reportType = 'stop';
            $this->parsePerDriver('stop');
        } elseif (stripos($title, 'POI visits') !== false) {
            $this->reportType = 'poi_visit';
            $this->parsePerDriver('geofence');
        } elseif (stripos($title, 'Speed violation') !== false) {
            $this->reportType = 'speeding';
            $this->parsePerDriver('speed');
        } elseif (preg_match('/trip.*report/i', $title)) {
            $this->reportType = 'trip';
            $this->parsePerDriver('trip');
        } elseif (preg_match('/geofence|restricted|no parking|over\s*staying|overstaying|hot\s*spot|present at|zone/i', $title)) {
            // Geofence family (incl. hot-spot / zone-presence reports) — refine the type from the title.
            $this->reportType = $this->geofenceType($this->category);
            $this->parsePerDriver('geofence');
        } else {
            $this->reportType = 'unknown';
        }
    }

    /* ---------------------------------------------------------------- helpers */

    private function geofenceType(string $cat): string
    {
        $c = strtolower($cat);
        if (str_contains($c, 'no parking'))  return 'no_parking';
        if (str_contains($c, 'over staying') || str_contains($c, 'overstaying')) return 'overstaying';
        if (str_contains($c, 'restricted'))  return 'restricted_zone';
        if (str_contains($c, 'hot spot') || str_contains($c, 'hotspot') || str_contains($c, 'present at')) return 'hot_spot';
        return 'geofence_visit';
    }

    /** True if the hidden Data sheet has a header cell containing $frag. */
    private function dataHasColumn(string $frag): bool
    {
        foreach (($this->sheets['Data'] ?? []) as $row) {
            foreach ($row as $cell) {
                if (stripos((string) $cell, $frag) !== false) return true;
            }
        }
        return false;
    }

    private function firstText(array $rows): string
    {
        foreach ($rows as $r) {
            if (isset($r[0]) && trim((string) $r[0]) !== '') {
                return trim((string) $r[0]);
            }
        }
        return '';
    }

    private function detectDriverSheetTitle(): string
    {
        foreach ($this->sheets as $name => $rows) {
            if ($name === 'Summary') {
                $t = $this->firstText($rows);
                if ($t !== '') return $t;
            }
        }
        foreach ($this->sheets as $rows) {
            $t = $this->firstText($rows);
            if ($t !== '') return $t;
        }
        return '';
    }

    private function detectPeriodAndCompany(): void
    {
        // Generic reports keep clean values on the hidden Data sheet.
        if (isset($this->sheets['Data'])) {
            foreach ($this->sheets['Data'] as $r) {
                $k = trim((string) ($r[0] ?? ''));
                $v = trim((string) ($r[1] ?? ''));
                if ($k === 'CompanyName')              $this->company    = $v;
                if ($k === 'FromDate' && is_numeric($v)) $this->periodFrom = self::excelDate((float) $v);
                if ($k === 'ToDate'   && is_numeric($v)) $this->periodTo   = self::excelDate((float) $v);
            }
        }
        // Fallback: scan any "For the period:" line for two textual dates.
        if (!$this->periodFrom) {
            foreach ($this->sheets as $rows) {
                foreach ($rows as $r) {
                    foreach ($r as $cell) {
                        if (is_string($cell) && stripos($cell, 'period') !== false) {
                            if (preg_match_all('/([A-Z][a-z]{2} \d{1,2}, \d{4})/', $cell, $m) && count($m[1]) >= 1) {
                                $this->periodFrom = date('Y-m-d', strtotime($m[1][0]));
                                $this->periodTo   = date('Y-m-d', strtotime($m[1][count($m[1]) - 1]));
                                return;
                            }
                        }
                    }
                }
            }
        }
    }

    private function addEvent(string $type, array $e): void
    {
        [$sev, $pts] = Scoring::evaluate($type, $e);
        $e['event_type'] = $type;
        $e['severity']   = $sev;
        $e['points']     = $pts;
        $e += [
            'category' => $this->category, 'zone_name' => '', 'event_date' => null,
            'start_dt' => null, 'end_dt' => null, 'duration_min' => 0,
            'entrance_place' => '', 'exit_place' => '', 'metric_num' => null,
            'metric_num2' => null, 'details' => null,
        ];
        $this->events[] = $e;
    }

    /* --------------------------------------------------------------- parsers */

    private function parseIdling(): void
    {
        $rows = $this->sheets['Report'];
        $hdr  = $this->findHeaderRow($rows, 'Idling Duration');
        if ($hdr < 0) return;
        for ($i = $hdr + 1; $i < count($rows); $i++) {
            $r = $rows[$i];
            $item = trim((string) ($r[0] ?? ''));
            if ($item === '' || stripos($item, 'in total') !== false) continue;
            if (!isset($r[5])) continue;
            $idlingDur = (float) ($r[5] ?? 0);   // day fraction
            $idlingPct = (float) ($r[8] ?? 0);   // 0..1
            [$code, $name] = self::splitTracker($item);
            $this->addEvent('idling', [
                'tracker' => $item, 'code' => $code, 'name' => $name,
                'zone_name' => trim((string) ($r[1] ?? '')),
                'event_date' => $this->periodFrom,
                'duration_min' => round($idlingDur * 1440, 2),
                'metric_num' => round($idlingPct, 4),
                'metric_num2' => round(((float) ($r[4] ?? 0)) * 1440, 2), // driving min
                'details' => json_encode([
                    'engine_hours_min' => round(((float) ($r[6] ?? 0)) * 1440, 2),
                    'idling_pct' => round($idlingPct * 100, 1),
                ]),
            ]);
        }
    }

    /**
     * Advanced Exceptions Detail report — one event per exception row, read from
     * the hidden Data sheet (which carries lat/lng + clean column names).
     */
    private function parseExceptions(): void
    {
        $rows = $this->sheets['Data'] ?? [];
        if (!$rows) return;

        // Locate the header row and map columns we care about by name fragment.
        $hdr = -1;
        foreach ($rows as $i => $r) {
            foreach ($r as $c) {
                if (stripos((string) $c, 'ExceptionRule') !== false) { $hdr = $i; break 2; }
            }
        }
        if ($hdr < 0) return;

        $col = [];
        foreach ($rows[$hdr] as $ci => $name) {
            $col[trim((string) $name)] = $ci;
        }
        $idx = function (string $frag) use ($col) {
            foreach ($col as $name => $ci) {
                if (stripos($name, $frag) !== false) return $ci;
            }
            return -1;
        };
        $cDevice = $idx('.Device.DeviceName');
        $cFirst  = $idx('UserFirstName');
        $cLast   = $idx('UserLastName');
        $cRule   = $idx('ExceptionRule.Exception');
        $cLng    = $idx('ExceptionDetailLongitude');
        $cLat    = $idx('ExceptionDetailLatitude');
        $cLoc    = $idx('ExceptionDetailLocation');
        $cStart  = $idx('ExceptionDetailStartTime');
        $cDur    = $idx('ExceptionDuration');
        $cDist   = $idx('ExceptionDistance');
        $cExtra  = $idx('ExceptionDetailExtraInfo');
        $cStatus = $idx('ExceptionStatus');

        for ($i = $hdr + 1; $i < count($rows); $i++) {
            $r = $rows[$i];
            $device = trim((string) ($r[$cDevice] ?? ''));
            $rule   = trim((string) ($r[$cRule] ?? ''));
            if ($device === '' || $rule === '') continue;

            $first = $cFirst >= 0 ? trim((string) ($r[$cFirst] ?? '')) : '';
            $last  = $cLast  >= 0 ? trim((string) ($r[$cLast] ?? '')) : '';
            $name  = trim("$first $last");
            [$code] = self::splitTracker($device);

            $type    = self::mapException($rule);
            if ($type === '') continue; // skip operational/tracking exceptions (zone enter/exit, movement, alerts)
            $startSer= (float) ($r[$cStart] ?? 0);
            $startDt = $startSer > 0 ? self::excelDateTime($startSer) : null;
            $date    = $startDt ? substr($startDt, 0, 10) : $this->periodFrom;
            $dur     = (float) ($r[$cDur] ?? 0);
            $dist    = (float) ($r[$cDist] ?? 0);
            $lat     = $cLat >= 0 ? (float) ($r[$cLat] ?? 0) : 0;
            $lng     = $cLng >= 0 ? (float) ($r[$cLng] ?? 0) : 0;
            $loc     = $cLoc >= 0 ? trim((string) ($r[$cLoc] ?? '')) : '';
            $extra   = $cExtra >= 0 ? trim((string) ($r[$cExtra] ?? '')) : '';
            $maxSpeed= self::parseMaxSpeed($extra);
            $coordTxt= ($lat && $lng) ? "(Lat:$lat, Lng:$lng)" : '';

            $this->addEvent($type, [
                'tracker' => $device, 'code' => $code, 'name' => $name,
                'category' => $rule,
                'zone_name' => $loc !== '' ? $loc : $rule,
                'event_date' => $date,
                'start_dt' => $startDt,
                'duration_min' => round($dur * 1440, 2),
                'entrance_place' => $coordTxt,
                'metric_num' => $type === 'speeding' ? $maxSpeed : null,
                'metric_num2' => $dist,
                'details' => json_encode([
                    'rule' => $rule, 'status' => trim((string) ($r[$cStatus] ?? '')),
                    'max_speed' => $maxSpeed, 'distance_km' => $dist,
                    'lat' => $lat ?: null, 'lng' => $lng ?: null, 'location' => $loc, 'extra' => $extra,
                ]),
            ]);
        }
    }

    /**
     * Map a Geotab exception rule to a normalized event type.
     * Driver-behavior violations get a scored type; operational/tracking rules
     * (zone Enter/Exit, "Vehicle Movement Within Zones", engine/battery/device
     * alerts, etc.) return '' so the importer skips them (they'd otherwise flood
     * the dashboard/map and inflate scores).
     */
    private static function mapException(string $rule): string
    {
        $r = strtolower($rule);
        if (str_contains($r, 'speed')) return 'speeding';
        if (str_contains($r, 'idl'))   return 'idling';
        if (preg_match('/harsh|brak|accel|corner|swerv|revers/', $r)) return 'harsh_driving';
        if (preg_match('/seat\s*belt/', $r)) return 'seatbelt';
        return ''; // operational / tracking / maintenance exception — not a driver violation
    }

    private static function parseMaxSpeed(string $extra): float
    {
        if (preg_match('/Max Speed:\s*(\d+(?:\.\d+)?)/i', $extra, $m)) {
            return (float) $m[1];
        }
        return 0.0;
    }

    private function parseSpeedSummary(): void
    {
        $rows = $this->sheets['Report'];
        $hdr  = $this->findHeaderRow($rows, 'Vehicle', 'Device');
        // The generic speeding template is aggregate; one event per vehicle.
        for ($i = max($hdr, 10); $i < count($rows); $i++) {
            $r = $rows[$i];
            $item = trim((string) ($r[0] ?? ''));
            if ($item === '' || stripos($item, 'in total') !== false) continue;
            [$code, $name] = self::splitTracker($item);
            $count = (int) round((float) ($r[7] ?? 0));
            if ($count <= 0) continue;
            $this->addEvent('speeding', [
                'tracker' => $item, 'code' => $code, 'name' => $name,
                'zone_name' => 'Speeding (period total)',
                'event_date' => $this->periodFrom,
                'metric_num' => $count, // treat count as metric for summary rows
                'details' => json_encode(['speeding_count' => $count]),
            ]);
        }
    }

    /**
     * Walks every tracker sheet. $mode = stop | speed | geofence determines the
     * row layout used to read detail rows.
     */
    private function parsePerDriver(string $mode): void
    {
        $lastTracker = '';
        foreach ($this->sheets as $name => $rows) {
            $name = (string) $name; // numeric sheet names arrive as int array keys
            if ($name === 'Summary' || !$rows) continue;

            $title = $this->firstText($rows);
            // Resolve the tracker for this sheet (title "X: TRACKER" or sheet name).
            $tracker = $this->trackerFromTitle($title);
            if ($tracker === '') {
                $tracker = preg_replace('/ - \d+$/', '', $name);
            }
            if ($tracker !== '' && stripos($tracker, 'summary') === false) {
                $lastTracker = $tracker;
            }
            $useTracker = $lastTracker !== '' ? $lastTracker : preg_replace('/ - \d+$/', '', $name);

            if ($mode === 'stop') {
                $this->parseStopSheet($rows, $useTracker);
            } elseif ($mode === 'speed') {
                $this->parseSpeedSheet($rows, $useTracker);
            } elseif ($mode === 'trip') {
                $this->parseTripSheet($rows, $useTracker);
            } else {
                $this->parseGeofenceSheet($rows, $useTracker);
            }
        }
    }

    private function parseStopSheet(array $rows, string $tracker): void
    {
        // Detail sheets have "Stops/parkings details" near the top.
        $isDetail = false;
        foreach ($rows as $r) {
            if (stripos((string) ($r[0] ?? ''), 'Stops/parkings details') !== false) { $isDetail = true; break; }
        }
        if (!$isDetail) return;

        [$code, $name] = self::splitTracker($tracker);
        $curDate = null;
        foreach ($rows as $r) {
            $c0 = trim((string) ($r[0] ?? ''));
            if ($c0 === '') continue;
            if (($d = self::parseDateHeader($c0)) !== null) { $curDate = $d; continue; }
            if (stripos($c0, 'in total') !== false) continue;
            if (!is_numeric($c0)) continue; // start time fraction
            $start = (float) $c0;
            $end   = (float) ($r[1] ?? 0);
            $dur   = (float) ($r[3] ?? 0);
            $this->addEvent('stop', [
                'tracker' => $tracker, 'code' => $code, 'name' => $name,
                'zone_name' => trim((string) ($r[2] ?? '')),
                'event_date' => $curDate,
                'start_dt' => self::dt($curDate, $start),
                'end_dt' => self::dt($curDate, $end),
                'duration_min' => round($dur * 1440, 2),
                'entrance_place' => trim((string) ($r[2] ?? '')),
                'metric_num' => round(((float) ($r[4] ?? 0)) * 1440, 2), // ignition-on minutes
            ]);
        }
    }

    private function parseSpeedSheet(array $rows, string $tracker): void
    {
        $hasHeader = false;
        foreach ($rows as $r) {
            foreach ($r as $c) {
                if (stripos((string) $c, 'Average speed') !== false) { $hasHeader = true; break 2; }
            }
        }
        if (!$hasHeader) return;

        [$code, $name] = self::splitTracker($tracker);
        $curDate = null;
        foreach ($rows as $r) {
            $c0 = trim((string) ($r[0] ?? ''));
            if ($c0 === '') continue;
            if (($d = self::parseDateHeader($c0)) !== null) { $curDate = $d; continue; }
            if (!is_numeric($c0)) continue;
            $start = (float) $c0;
            $dur   = (float) ($r[1] ?? 0);
            $avg   = (float) ($r[2] ?? 0);
            $max   = (float) ($r[3] ?? 0);
            $this->addEvent('speeding', [
                'tracker' => $tracker, 'code' => $code, 'name' => $name,
                'zone_name' => trim((string) ($r[4] ?? '')),
                'event_date' => $curDate,
                'start_dt' => self::dt($curDate, $start),
                'duration_min' => round($dur * 1440, 2),
                'metric_num' => $max,     // max speed drives severity
                'metric_num2' => $avg,    // avg speed
                'entrance_place' => trim((string) ($r[4] ?? '')),
                'details' => json_encode(['avg_speed' => $avg, 'max_speed' => $max]),
            ]);
        }
    }

    private function parseTripSheet(array $rows, string $tracker): void
    {
        // Detail sheet (the main per-tracker sheet) has a "Movement start" header.
        $hasHeader = false;
        foreach ($rows as $r) {
            foreach ($r as $c) {
                if (stripos((string) $c, 'Movement start') !== false) { $hasHeader = true; break 2; }
            }
        }
        if (!$hasHeader) return;

        [$code, $name] = self::splitTracker($tracker);
        $curDate = null;
        foreach ($rows as $r) {
            $c0 = trim((string) ($r[0] ?? ''));
            if ($c0 === '') continue;
            if (($d = self::parseDateHeader($c0)) !== null) { $curDate = $d; continue; }
            if (stripos($c0, 'in total') !== false || stripos($c0, 'movement') !== false) continue;
            // Row: "HH:MM - start place" | "HH:MM - end place" | length km | travel(frac) | avg | max
            if (!preg_match('/^(\d{1,2}:\d{2})\s*-\s*(.*)$/u', $c0, $sm)) continue;
            $startTime  = $sm[1];
            $startPlace = trim($sm[2]);
            $endRaw     = trim((string) ($r[1] ?? ''));
            $endTime = ''; $endPlace = '';
            if (preg_match('/^(\d{1,2}:\d{2})\s*-\s*(.*)$/u', $endRaw, $em)) {
                $endTime = $em[1]; $endPlace = trim($em[2]);
            }
            $lengthKm = (float) ($r[2] ?? 0);
            $travel   = (float) ($r[3] ?? 0);
            $avg      = (float) ($r[4] ?? 0);
            $max      = (float) ($r[5] ?? 0);
            $this->addEvent('trip', [
                'tracker' => $tracker, 'code' => $code, 'name' => $name,
                'zone_name' => $startPlace . ' → ' . $endPlace,
                'event_date' => $curDate,
                'start_dt' => $curDate ? "$curDate $startTime:00" : null,
                'end_dt' => ($curDate && $endTime) ? "$curDate $endTime:00" : null,
                'duration_min' => round($travel * 1440, 2),
                'entrance_place' => $startPlace,
                'exit_place' => $endPlace,
                'metric_num' => $lengthKm,   // distance km
                'metric_num2' => $max,       // max speed
                'details' => json_encode(['distance_km' => $lengthKm, 'avg_speed' => $avg, 'max_speed' => $max]),
            ]);
        }
    }

    private function parseGeofenceSheet(array $rows, string $tracker): void
    {
        // Detail sheet starts with "Geofence visits" or "POI visits".
        $head = $this->firstText($rows);
        if (stripos($head, 'visits') === false || stripos($head, 'Report') !== false) {
            return; // skip the per-driver Summary sheet (keeps the aggregate only)
        }

        [$code, $name] = self::splitTracker($tracker);
        $curDate = null;
        foreach ($rows as $idx => $r) {
            $c0 = trim((string) ($r[0] ?? ''));
            if ($c0 === '' || $idx < 3) continue;
            if (in_array(strtolower($c0), ['geofence', 'place', 'time'], true)) continue;
            if (($d = self::parseDateHeader($c0)) !== null) { $curDate = $d; continue; }
            if (stripos($c0, 'in total') !== false) continue;
            // Layout: Zone | EntranceTime | EntrancePlace | ExitTime | ExitPlace | Duration
            $entT = $r[1] ?? '';
            $exT  = $r[3] ?? '';
            if (!is_numeric($entT)) continue;
            $dur  = (float) ($r[5] ?? 0);
            $this->addEvent($this->reportType, [
                'tracker' => $tracker, 'code' => $code, 'name' => $name,
                'zone_name' => $c0,
                'event_date' => $curDate,
                'start_dt' => self::dt($curDate, (float) $entT),
                'end_dt' => is_numeric($exT) ? self::dt($curDate, (float) $exT) : null,
                'duration_min' => round($dur * 1440, 2),
                'entrance_place' => trim((string) ($r[2] ?? '')),
                'exit_place' => trim((string) ($r[4] ?? '')),
            ]);
        }
    }

    /* --------------------------------------------------------- small helpers */

    private function findHeaderRow(array $rows, string ...$needles): int
    {
        foreach ($rows as $i => $r) {
            $line = strtolower(implode('|', array_map('strval', $r)));
            foreach ($needles as $n) {
                if (str_contains($line, strtolower($n))) return $i;
            }
        }
        return -1;
    }

    private function trackerFromTitle(string $title): string
    {
        $pos = strrpos($title, ': ');
        if ($pos === false) return '';
        $t = trim(substr($title, $pos + 2));
        if (strtolower($t) === 'summary' || $t === '') return '';
        return $t;
    }

    public static function splitTracker(string $tracker): array
    {
        $tracker = trim($tracker);
        if (($p = strpos($tracker, '_')) !== false) {
            return [trim(substr($tracker, 0, $p)), trim(substr($tracker, $p + 1))];
        }
        return [$tracker, ''];
    }

    public static function excelDate(float $serial): string
    {
        $days = (int) floor($serial);
        $dt = new DateTime('1899-12-30');
        $dt->modify("+$days days");
        return $dt->format('Y-m-d');
    }

    public static function excelDateTime(float $serial): string
    {
        $days = (int) floor($serial);
        $secs = (int) round(($serial - $days) * 86400);
        if ($secs >= 86400) { $days++; $secs -= 86400; }
        $dt = new DateTime('1899-12-30');
        $dt->modify("+$days days");
        return $dt->format('Y-m-d') . ' ' . sprintf('%02d:%02d:%02d', intdiv($secs, 3600), intdiv($secs % 3600, 60), $secs % 60);
    }

    private static function dt(?string $date, float $frac): ?string
    {
        if (!$date) return null;
        $secs = (int) round($frac * 86400);
        if ($secs >= 86400) $secs = 86399;
        return $date . ' ' . sprintf('%02d:%02d:%02d', intdiv($secs, 3600), intdiv($secs % 3600, 60), $secs % 60);
    }

    private static function parseDateHeader(string $text): ?string
    {
        // e.g. "Jun 7, 2026 (Sun) : 10"
        if (preg_match('/^([A-Z][a-z]{2} \d{1,2}, \d{4})/', $text, $m)) {
            $ts = strtotime($m[1]);
            return $ts ? date('Y-m-d', $ts) : null;
        }
        return null;
    }
}
