<?php
/**
 * Persists a parsed report into the database: upserts drivers, records the
 * import and bulk-inserts normalized events. Idempotent per file (hash guard).
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/ReportParser.php';

class Importer
{
    public static function import(string $tmpPath, string $originalName): array
    {
        $hash = sha1_file($tmpPath);
        $pdo  = db();

        $dup = $pdo->prepare('SELECT id, filename FROM imports WHERE file_hash = ?');
        $dup->execute([$hash]);
        if ($row = $dup->fetch()) {
            return ['status' => 'duplicate', 'message' => 'This file was already imported (' . $row['filename'] . ').'];
        }

        $parser = ReportParser::fromFile($tmpPath);
        if ($parser->reportType === 'unknown') {
            return ['status' => 'error', 'message' => 'Unrecognized report layout — this looks like a Geotab sample/template, not live fleet data.'];
        }

        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare(
                'INSERT INTO imports (filename, report_type, report_title, category, company, period_from, period_to, event_count, file_hash)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            );
            $ins->execute([
                $originalName, $parser->reportType, $parser->reportTitle, $parser->category,
                $parser->company, $parser->periodFrom, $parser->periodTo, count($parser->events), $hash,
            ]);
            $importId = (int) $pdo->lastInsertId();

            $driverCache = [];
            $evStmt = $pdo->prepare(
                'INSERT INTO events
                 (import_id, driver_id, event_type, category, zone_name, event_date, start_dt, end_dt,
                  duration_min, entrance_place, exit_place, metric_num, metric_num2, severity, points, details)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );

            foreach ($parser->events as $e) {
                $driverId = self::driverId($pdo, $driverCache, $e['code'], $e['name'], $e['tracker']);
                $evStmt->execute([
                    $importId, $driverId, $e['event_type'], $e['category'], $e['zone_name'],
                    $e['event_date'], $e['start_dt'], $e['end_dt'], $e['duration_min'],
                    $e['entrance_place'], $e['exit_place'], $e['metric_num'], $e['metric_num2'],
                    $e['severity'], $e['points'], $e['details'],
                ]);
            }

            $pdo->commit();
            return [
                'status' => 'ok',
                'import_id' => $importId,
                'report_type' => $parser->reportType,
                'category' => $parser->category,
                'events' => count($parser->events),
                'drivers' => count($driverCache),
                'period' => trim(($parser->periodFrom ?? '') . ' – ' . ($parser->periodTo ?? ''), ' –'),
            ];
        } catch (Throwable $ex) {
            $pdo->rollBack();
            return ['status' => 'error', 'message' => $ex->getMessage()];
        }
    }

    private static function driverId(PDO $pdo, array &$cache, string $code, string $name, string $tracker): int
    {
        $code = $code !== '' ? $code : $tracker;
        if (isset($cache[$code])) {
            return $cache[$code];
        }
        $sel = $pdo->prepare('SELECT id, name FROM drivers WHERE code = ?');
        $sel->execute([$code]);
        if ($d = $sel->fetch()) {
            // Keep the most descriptive name we have seen.
            if (strlen($name) > strlen((string) $d['name'])) {
                $pdo->prepare('UPDATE drivers SET name = ? WHERE id = ?')->execute([$name, $d['id']]);
            }
            return $cache[$code] = (int) $d['id'];
        }
        $pdo->prepare('INSERT INTO drivers (code, name, tracker_raw) VALUES (?,?,?)')
            ->execute([$code, $name, $tracker]);
        return $cache[$code] = (int) $pdo->lastInsertId();
    }
}
