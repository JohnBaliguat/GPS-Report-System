<?php
/** Returns events that have usable GPS coordinates, for the map view. */
declare(strict_types=1);
require_once __DIR__ . '/_common.php';

$pdo = db();
[$where, $args] = event_filters();

// Only event rows that could carry coordinates (exceptions, zone/stop visits, trips).
$sql = "SELECT e.id, e.event_type, e.zone_name, e.event_date, e.start_dt,
               e.entrance_place, e.exit_place, e.metric_num, e.details, e.points,
               d.code, d.name
        FROM events e JOIN drivers d ON d.id = e.driver_id
        $where
        ORDER BY e.start_dt DESC
        LIMIT 4000";
$stmt = $pdo->prepare($sql);
$stmt->execute($args);

function coordFrom(string $s): ?array
{
    if (preg_match('/Lat:\s*(-?\d+\.\d+),\s*Lng:\s*(-?\d+\.\d+)/i', $s, $m)) {
        return [(float) $m[1], (float) $m[2]];
    }
    return null;
}

/** Strip geofence [tags] / coord blobs so the address can be geocoded client-side. */
function cleanAddr(string $s): string
{
    $inside = '';
    if (preg_match('/\[([^\]]+)\]/', $s, $m)) { $inside = trim(explode(',', $m[1])[0]); }
    $t = preg_replace('/\[[^\]]*\]/', '', $s);
    $t = preg_replace('/\(Lat:[^)]*\)/i', '', $t);
    $t = trim($t, " ,-");
    return (mb_strlen($t) < 4 && $inside !== '') ? $inside : $t;
}

// Event types worth plotting even when only an address is known (geocoded on the client).
$GEOCODE_TYPES = ['speeding', 'idling', 'harsh_driving', 'seatbelt', 'exception', 'restricted_zone',
                  'no_parking', 'overstaying', 'hot_spot', 'geofence_visit'];

$points = [];
$counts = [];
foreach ($stmt->fetchAll() as $r) {
    $lat = $lng = null;

    $det = json_decode((string) $r['details'], true);
    if (is_array($det) && !empty($det['lat']) && !empty($det['lng'])) {
        $lat = (float) $det['lat'];
        $lng = (float) $det['lng'];
    } else {
        foreach ([$r['entrance_place'], $r['exit_place'], $r['zone_name']] as $field) {
            if ($c = coordFrom((string) $field)) { [$lat, $lng] = $c; break; }
        }
    }

    $hasCoord = !($lat === null || $lng === null || ($lat == 0 && $lng == 0));
    $geo = '';
    if (!$hasCoord) {
        // Keep address-only violation events so the client can geocode + plot them.
        if (!in_array($r['event_type'], $GEOCODE_TYPES, true)) continue;
        $geo = cleanAddr((string) ($r['zone_name'] ?: $r['entrance_place'] ?: $r['exit_place']));
        if ($geo === '') continue;
    }

    $meta = Scoring::TYPES[$r['event_type']] ?? ['label' => $r['event_type'], 'color' => '#94a3b8'];
    $points[] = [
        'lat' => $hasCoord ? $lat : null,
        'lng' => $hasCoord ? $lng : null,
        'geo' => $geo,                       // address to geocode when no coord
        'type' => $r['event_type'], 'label' => $meta['label'], 'color' => $meta['color'],
        'driver' => $r['code'] . ($r['name'] ? ' · ' . $r['name'] : ''),
        'date' => $r['event_date'],
        'time' => $r['start_dt'] ? date('H:i', strtotime($r['start_dt'])) : '',
        'zone' => $r['zone_name'],
        'metric' => $r['metric_num'] !== null ? (float) $r['metric_num'] : null,
        'points' => (int) $r['points'],
    ];
    $counts[$r['event_type']] = ($counts[$r['event_type']] ?? 0) + 1;
}

json_out(['data' => $points, 'counts' => $counts, 'total' => count($points)]);
