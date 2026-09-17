<?php
require __DIR__ . '/lib/Auth.php';
Auth::requirePage();
require_once __DIR__ . '/lib/Settings.php';
$mapsKey = Settings::get('google_maps_key');
$page = 'map';
$title = 'Violations Map';
$subtitle = 'Geolocated exceptions & events across the fleet';
require __DIR__ . '/includes/header.php';
?>
<div class="filters">
    <div class="field"><label>Event Type</label>
        <select id="mpType">
            <option value="">All geolocated events</option>
            <option value="speeding">Speeding</option>
            <option value="idling">Idling</option>
            <option value="harsh_driving">Harsh Driving</option>
            <option value="seatbelt">Seat Belt</option>
            <option value="exception">Exception</option>
            <option value="restricted_zone">Restricted Zone</option>
            <option value="no_parking">No-Parking Zone</option>
            <option value="overstaying">Overstaying</option>
            <option value="hot_spot">Hot Spot Presence</option>
        </select></div>
    <div class="field"><label>Driver / Truck</label>
        <select id="mpDriver" style="min-width:230px"><option value="">All drivers</option></select></div>
    <div class="field"><label>From</label><input type="date" id="mpFrom"></div>
    <div class="field"><label>To</label><input type="date" id="mpTo"></div>
    <div class="field"><label>&nbsp;</label><button class="btn btn-primary btn-sm" id="mpApply"><i class="bi bi-funnel"></i> Apply</button></div>
    <div class="field"><label>View</label>
        <div class="btn-group btn-group-sm" role="group">
            <input type="radio" class="btn-check" name="mpView" id="mpMarkers" value="markers" checked>
            <label class="btn btn-outline-secondary" for="mpMarkers"><i class="bi bi-pin-map"></i> Pins</label>
            <input type="radio" class="btn-check" name="mpView" id="mpHeat" value="heat">
            <label class="btn btn-outline-secondary" for="mpHeat"><i class="bi bi-fire"></i> Heatmap</label>
        </div></div>
</div>

<?php if (!$mapsKey): ?>
<div class="alert alert-warning">No Google Maps API key configured.</div>
<?php endif; ?>

<div class="panel" style="padding:0;overflow:hidden;position:relative">
    <div id="mpBadge" class="map-badge">—</div>
    <div id="mapView" style="width:100%;height:680px;background:#e2e8f0"></div>
</div>
<div class="panel"><div id="mpLegend" class="legend"></div></div>

<script>window.GMAPS_KEY=<?= json_encode($mapsKey) ?>;PAGE="map";</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
