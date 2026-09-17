<?php
/**
 * Generates GPS Monitoring Violation Notices from the drivers who have
 * violations in a period, and provides the counts/location used on each notice.
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

class Notices
{
    /** Municipalities/areas we recognise to build the notice "Location" line. */
    private const PLACES = [
        'Panabo', 'Carmen', 'Tagum', 'Maco', 'Cateel', 'Mabini', 'Pantukan', 'Lupon',
        'Banaybanay', 'Mati', 'Davao City', 'Toril', 'Lasang', 'Bunawan', 'Panacan',
        'Sto. Tomas', 'Santo Tomas', 'Kapalong', 'Asuncion', 'Braulio E. Dujali', 'Dujali',
        'Compostela', 'Montevista', 'Nabunturan', 'Samal', 'Don Marcelino', 'Malita',
    ];

    /**
     * Create notices for every violating driver in [from,to].
     * @return array{created:int, skipped:int, drivers:int}
     */
    public static function generate(string $from, string $to, array $opts): array
    {
        $pdo = db();
        // Counseling is always held on a Monday — default to the next Monday if none given.
        $counselingDate  = ($opts['counseling_date'] ?? '') ?: self::nextMonday();
        $counselingDate  = self::snapToMonday($counselingDate);
        $counselingTime  = trim((string) ($opts['counseling_time'] ?? '')) ?: '8:00 AM and 1:00 PM';
        $counselingVenue = trim((string) ($opts['counseling_venue'] ?? ''));
        $issuedBy        = trim((string) ($opts['issued_by'] ?? ''));
        $createdBy       = trim((string) ($opts['created_by'] ?? ''));

        $sql = "SELECT d.id, d.code, d.name,
                  SUM(e.event_type='speeding')        AS c_speeding,
                  SUM(e.event_type='restricted_zone') AS c_restricted,
                  SUM(e.event_type='no_parking')      AS c_no_parking,
                  SUM(e.event_type='overstaying')     AS c_overstay,
                  SUM(e.event_type='idling')          AS c_idle,
                  SUM(e.event_type IN ('speeding','restricted_zone','no_parking','overstaying','idling')) AS total
                FROM events e JOIN drivers d ON d.id = e.driver_id
                WHERE e.event_type IN ('speeding','restricted_zone','no_parking','overstaying','idling')
                  AND e.event_date BETWEEN ? AND ?
                GROUP BY d.id HAVING total > 0
                ORDER BY total DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$from, $to]);
        $drivers = $stmt->fetchAll();

        $created = 0;
        $skipped = 0;
        $ins = $pdo->prepare(
            'INSERT INTO violation_notices
              (control_no, driver_id, driver_name, unit_no, period_from, period_to, location,
               c_speeding, c_restricted, c_no_parking, c_overstay, c_idle, total,
               counseling_date, counseling_time, counseling_venue, status, issued_by, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );

        foreach ($drivers as $d) {
            // One notice per driver per period.
            $dup = $pdo->prepare('SELECT id FROM violation_notices WHERE driver_id=? AND period_from=? AND period_to=?');
            $dup->execute([$d['id'], $from, $to]);
            if ($dup->fetch()) { $skipped++; continue; }

            $location = self::locationFor($pdo, (int) $d['id'], $from, $to);
            $status   = $counselingDate ? 'scheduled' : 'pending';

            $ins->execute([
                self::nextControlNo($pdo), (int) $d['id'],
                trim((string) $d['name']) ?: (string) $d['code'], (string) $d['code'],
                $from, $to, $location,
                (int) $d['c_speeding'], (int) $d['c_restricted'], (int) $d['c_no_parking'],
                (int) $d['c_overstay'], (int) $d['c_idle'], (int) $d['total'],
                $counselingDate ?: null, $counselingTime, $counselingVenue, $status, $issuedBy, $createdBy,
            ]);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped, 'drivers' => count($drivers)];
    }

    /** The next upcoming Monday (Y-m-d) from today. */
    public static function nextMonday(): string
    {
        return date('Y-m-d', strtotime('next monday'));
    }

    /** Force a date onto a Monday: if not already Monday, move forward to the next one. */
    public static function snapToMonday(string $date): string
    {
        $ts = strtotime($date);
        if (!$ts) return self::nextMonday();
        if ((int) date('N', $ts) === 1) return date('Y-m-d', $ts); // already Monday
        return date('Y-m-d', strtotime('next monday', $ts));
    }

    private static function nextControlNo(PDO $pdo): string
    {
        $year = date('Y');
        // Highest numeric suffix used this year, then +1 (e.g. 20261, 20262 …).
        $stmt = $pdo->prepare("SELECT control_no FROM violation_notices WHERE control_no LIKE ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$year . '%']);
        $last = $stmt->fetchColumn();
        $seq = 1;
        if ($last && preg_match('/^' . $year . '(\d+)$/', (string) $last, $m)) {
            $seq = (int) $m[1] + 1;
        } elseif ($last) {
            $seq = (int) substr((string) $last, strlen($year)) + 1;
        }
        return $year . $seq;
    }

    /** Build a "Carmen, Panabo, Tagum" style location from the driver's events. */
    private static function locationFor(PDO $pdo, int $driverId, string $from, string $to): string
    {
        $stmt = $pdo->prepare(
            "SELECT zone_name, entrance_place FROM events
             WHERE driver_id=? AND event_date BETWEEN ? AND ?
               AND event_type IN ('speeding','restricted_zone','no_parking','overstaying','idling')"
        );
        $stmt->execute([$driverId, $from, $to]);
        $blob = '';
        foreach ($stmt->fetchAll() as $r) {
            $blob .= ' ' . $r['zone_name'] . ' ' . $r['entrance_place'];
        }
        $found = [];
        foreach (self::PLACES as $p) {
            if (stripos($blob, $p) !== false) {
                $found[$p] = true;
                if (count($found) >= 3) break;
            }
        }
        $list = array_keys($found);
        if (!$list) return '';
        return implode(', ', $list) . (count($list) >= 3 ? ', et al.' : '');
    }
}
