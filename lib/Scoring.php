<?php
/**
 * Central place for behavior-scoring rules so the importer, dashboard and
 * driver scorecards all agree on demerit points, severity and grades.
 */
declare(strict_types=1);

class Scoring
{
    /** Human labels + colors for each normalized event type. */
    public const TYPES = [
        'speeding'        => ['label' => 'Speeding',          'color' => '#ef4444'],
        'restricted_zone' => ['label' => 'Restricted Zone',   'color' => '#dc2626'],
        'no_parking'      => ['label' => 'No-Parking Zone',   'color' => '#f97316'],
        'overstaying'     => ['label' => 'Overstaying',       'color' => '#f59e0b'],
        'idling'          => ['label' => 'Idling',            'color' => '#eab308'],
        'geofence_visit'  => ['label' => 'Geofence Visit',    'color' => '#3b82f6'],
        'poi_visit'       => ['label' => 'POI Visit',         'color' => '#10b981'],
        'stop'            => ['label' => 'Stop / Parking',    'color' => '#64748b'],
        'trip'            => ['label' => 'Trip',              'color' => '#0ea5e9'],
        'harsh_driving'   => ['label' => 'Harsh Driving',    'color' => '#db2777'],
        'seatbelt'        => ['label' => 'Seat Belt',        'color' => '#e11d48'],
        'exception'       => ['label' => 'Exception',        'color' => '#7c3aed'],
        'hot_spot'        => ['label' => 'Hot Spot Presence','color' => '#14b8a6'],
    ];

    /**
     * Returns [severity (0-3), demerit points] for one event.
     * Higher severity / points = worse driver behavior.
     */
    public static function evaluate(string $type, array $m): array
    {
        switch ($type) {
            case 'speeding':
                $max = (float) ($m['metric_num'] ?? 0); // max speed km/h
                if ($max >= 90)      return [3, 10];
                if ($max >= 80)      return [2, 6];
                return [1, 3];

            case 'restricted_zone':
                return [3, 10];

            case 'no_parking':
                return [3, 10];

            case 'overstaying':
                return [2, 8];

            case 'idling':
                $pct = (float) ($m['metric_num'] ?? 0); // 0..1
                if ($pct >= 0.50) return [3, 8];
                if ($pct >= 0.30) return [2, 5];
                if ($pct >= 0.15) return [1, 2];
                return [0, 0];

            case 'geofence_visit':
                return [1, 2];

            case 'hot_spot':
                return [1, 3];

            case 'harsh_driving':
                return [2, 5];

            case 'seatbelt':
                return [2, 4];

            case 'exception':
                return [1, 3];

            case 'poi_visit':
            case 'stop':
            case 'trip':
            default:
                return [0, 0];
        }
    }

    /** Convert total demerit points into a 0-100 behavior score. */
    public static function score(int $points): int
    {
        return (int) max(0, 100 - min(100, $points));
    }

    public static function grade(int $score): string
    {
        if ($score >= 90) return 'A';
        if ($score >= 80) return 'B';
        if ($score >= 70) return 'C';
        if ($score >= 60) return 'D';
        return 'F';
    }

    public static function gradeColor(int $score): string
    {
        if ($score >= 90) return '#10b981';
        if ($score >= 80) return '#22c55e';
        if ($score >= 70) return '#eab308';
        if ($score >= 60) return '#f97316';
        return '#ef4444';
    }
}
