<?php
$page = 'index';
$title = 'Fleet Dashboard';
$subtitle = 'Driver performance & behavior overview';
require __DIR__ . '/includes/header.php';
?>
<div class="filters">
    <div class="field">
        <label>Event Type</label>
        <select id="fType">
            <option value="">All event types</option>
            <option value="speeding">Speeding</option>
            <option value="restricted_zone">Restricted Zone</option>
            <option value="no_parking">No-Parking Zone</option>
            <option value="overstaying">Overstaying</option>
            <option value="idling">Idling</option>
            <option value="harsh_driving">Harsh Driving</option>
            <option value="seatbelt">Seat Belt</option>
            <option value="geofence_visit">Geofence Visit</option>
            <option value="hot_spot">Hot Spot Presence</option>
            <option value="poi_visit">POI Visit</option>
            <option value="stop">Stop / Parking</option>
            <option value="trip">Trip</option>
            <option value="exception">Exception</option>
        </select>
    </div>
    <div class="field"><label>From</label><input type="date" id="fFrom"></div>
    <div class="field"><label>To</label><input type="date" id="fTo"></div>
    <div class="field"><label>&nbsp;</label><button class="btn btn-primary btn-sm" id="applyFilters"><i class="bi bi-funnel"></i> Apply</button></div>
    <div class="field"><label>&nbsp;</label><button class="btn btn-outline-secondary btn-sm" id="resetFilters">Reset</button></div>
</div>

<div class="kpi-grid">
    <div class="kpi accent-indigo"><i class="bi bi-activity ic"></i><span class="lbl">Total Events</span><span class="val" id="kEvents">–</span><span class="sub">across all reports</span></div>
    <div class="kpi accent-red"><i class="bi bi-exclamation-octagon ic"></i><span class="lbl">Violations</span><span class="val" id="kViol">–</span><span class="sub">speeding + zone breaches</span></div>
    <div class="kpi accent-amber"><i class="bi bi-fuel-pump ic"></i><span class="lbl">Idling Events</span><span class="val" id="kIdle">–</span><span class="sub">excessive engine idle</span></div>
    <div class="kpi accent-slate"><i class="bi bi-people ic"></i><span class="lbl">Drivers Tracked</span><span class="val" id="kDrivers">–</span><span class="sub">active trackers</span></div>
    <div class="kpi accent-green"><i class="bi bi-shield-check ic"></i><span class="lbl">Demerit Points</span><span class="val" id="kPoints">–</span><span class="sub">fleet total</span></div>
</div>

<div class="grid-2" style="margin-bottom:24px">
    <div class="panel">
        <h2><i class="bi bi-graph-up"></i> Daily Violation &amp; Idling Trend</h2>
        <div class="chart-box"><canvas id="trendChart"></canvas></div>
    </div>
    <div class="panel">
        <h2><i class="bi bi-pie-chart"></i> Event Mix</h2>
        <div class="chart-box sm"><canvas id="typeChart"></canvas></div>
        <div class="legend" id="typeLegend"></div>
    </div>
</div>

<div class="panel">
    <h2><i class="bi bi-exclamation-triangle"></i> Highest-Risk Drivers <small class="text-muted fw-normal" style="font-size:12px">(by demerit points — click a bar)</small></h2>
    <div class="chart-box"><canvas id="worstChart"></canvas></div>
</div>

<?php require __DIR__ . '/includes/driver_modal.php'; ?>
<?php $pageScript = 'PAGE="dashboard";'; require __DIR__ . '/includes/footer.php'; ?>
