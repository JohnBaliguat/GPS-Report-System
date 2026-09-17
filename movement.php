<?php
require __DIR__ . '/lib/Auth.php';
Auth::requirePage();
require_once __DIR__ . '/lib/Settings.php';
$mapsKey = Settings::get('google_maps_key');
$page = 'movement';
$title = 'Truck Movement';
$subtitle = 'Trip routes on the map — pick a driver and day';
require __DIR__ . '/includes/header.php';
?>
<div class="filters">
    <div class="field"><label>Driver / Truck</label>
        <select id="mvDriver" style="min-width:240px"><option value="">Select driver…</option></select></div>
    <div class="field"><label>Date</label>
        <select id="mvDate" style="min-width:170px"><option value="">—</option></select></div>
    <div class="field"><label>&nbsp;</label><button class="btn btn-primary btn-sm" id="mvShow"><i class="bi bi-geo-alt"></i> Show Movement</button></div>
    <div class="field"><label>&nbsp;</label>
        <div class="form-check form-switch mt-1"><input class="form-check-input" type="checkbox" id="mvSnap" checked>
        <label class="form-check-label small" for="mvSnap">Snap to roads</label></div></div>
</div>

<?php if (!$mapsKey): ?>
<div class="alert alert-warning">No Google Maps API key configured. Set it in <a href="settings.php">System Settings</a>.</div>
<?php endif; ?>

<div class="grid-2" style="grid-template-columns:1fr 360px">
    <div class="panel" style="padding:0;overflow:hidden">
        <div id="map" style="width:100%;height:640px;background:#e2e8f0"></div>
    </div>
    <div class="panel" style="max-height:640px;overflow:auto">
        <h2><i class="bi bi-signpost-split"></i> Trip Log</h2>
        <div id="mvStats" class="mb-3"></div>
        <div id="mvList"><div class="empty"><i class="bi bi-truck"></i><p>Select a driver and date, then “Show Movement”.</p></div></div>
    </div>
</div>

<script>
window.GMAPS_KEY = <?= json_encode($mapsKey) ?>;
PAGE = "movement";
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
