<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';

$pdo = db();

/** Pull "(Lat:.., Lng:..)" out of a place string if present. */
function parseLatLng(string $s): ?array
{
    if (preg_match('/Lat:\s*(-?\d+\.\d+),\s*Lng:\s*(-?\d+\.\d+)/i', $s, $m)) {
        return ['lat' => (float) $m[1], 'lng' => (float) $m[2]];
    }
    return null;
}

/** Clean a geofence-tagged place into something geocodable. */
function cleanPlace(string $s): string
{
    // Capture the inside of the first [tag] as a fallback name.
    $inside = '';
    if (preg_match('/\[([^\]]+)\]/', $s, $m)) {
        $inside = trim(explode(',', $m[1])[0]); // first part, e.g. "Panabo City GeoFence"
    }
    $t = preg_replace('/\[[^\]]*\]/', '', $s);          // drop [GeoFence tags]
    $t = preg_replace('/\(Lat:[^)]*\)/i', '', $t);       // drop coord blobs
    $t = trim($t, " ,-");
    // If stripping left nothing usable, fall back to the geofence name itself.
    if (mb_strlen($t) < 4 && $inside !== '') {
        return $inside;
    }
    return $t;
}

// List drivers that have trip data.
if (isset($_GET['drivers'])) {
    $rows = $pdo->query("SELECT d.id, d.code, d.name, COUNT(*) trips
        FROM events e JOIN drivers d ON d.id=e.driver_id
        WHERE e.event_type='trip' GROUP BY d.id ORDER BY d.code")->fetchAll();
    json_out(['data' => $rows]);
}

$driverId = (int) q('driver_id', 0);
if (!$driverId) json_out(['data' => []]);

// Dates available for this driver.
if (isset($_GET['dates'])) {
    $st = $pdo->prepare("SELECT DISTINCT event_date FROM events
        WHERE driver_id=? AND event_type='trip' AND event_date IS NOT NULL ORDER BY event_date DESC");
    $st->execute([$driverId]);
    json_out(['data' => $st->fetchAll(PDO::FETCH_COLUMN)]);
}

// Trips for a driver + date.
$date = (string) q('date', '');
$sql = "SELECT id, event_date, start_dt, end_dt, entrance_place, exit_place,
               duration_min, metric_num AS distance, metric_num2 AS max_speed, details
        FROM events WHERE driver_id=? AND event_type='trip'";
$args = [$driverId];
if ($date !== '') { $sql .= ' AND event_date=?'; $args[] = $date; }
$sql .= ' ORDER BY start_dt';
$st = $pdo->prepare($sql);
$st->execute($args);

$trips = [];
foreach ($st->fetchAll() as $r) {
    $det = json_decode((string) $r['details'], true) ?: [];
    $trips[] = [
        'id' => (int) $r['id'],
        'date' => $r['event_date'],
        'start_time' => $r['start_dt'] ? date('H:i', strtotime($r['start_dt'])) : '',
        'end_time' => $r['end_dt'] ? date('H:i', strtotime($r['end_dt'])) : '',
        'start_place' => $r['entrance_place'],
        'end_place' => $r['exit_place'],
        'start_clean' => cleanPlace((string) $r['entrance_place']),
        'end_clean' => cleanPlace((string) $r['exit_place']),
        'start_coord' => parseLatLng((string) $r['entrance_place']),
        'end_coord' => parseLatLng((string) $r['exit_place']),
        'distance' => (float) $r['distance'],
        'max_speed' => (float) $r['max_speed'],
        'avg_speed' => (float) ($det['avg_speed'] ?? 0),
        'duration_min' => (float) $r['duration_min'],
    ];
}

$driver = $pdo->prepare('SELECT code, name FROM drivers WHERE id=?');
$driver->execute([$driverId]);
json_out(['driver' => $driver->fetch(), 'trips' => $trips]);
