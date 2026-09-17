<?php
$page = 'events';
$title = 'Event Explorer';
$subtitle = 'Every imported telematics event, server-side paginated';
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
            <option value="geofence_visit">Geofence Visit</option>
            <option value="hot_spot">Hot Spot Presence</option>
            <option value="poi_visit">POI Visit</option>
            <option value="stop">Stop / Parking</option>
            <option value="trip">Trip</option>
            <option value="harsh_driving">Harsh Driving</option>
            <option value="seatbelt">Seat Belt</option>
            <option value="exception">Exception</option>
        </select>
    </div>
    <div class="field"><label>From</label><input type="date" id="fFrom"></div>
    <div class="field"><label>To</label><input type="date" id="fTo"></div>
    <div class="field"><label>&nbsp;</label><button class="btn btn-primary btn-sm" id="applyFilters"><i class="bi bi-funnel"></i> Apply</button></div>
    <div class="field"><label>&nbsp;</label><button class="btn btn-outline-secondary btn-sm" id="resetFilters">Reset</button></div>
</div>
<div class="panel">
    <h2><i class="bi bi-list-columns-reverse"></i> Telematics Events</h2>
    <table id="eventTable" class="table" style="width:100%">
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Driver</th>
                <th>Zone / Location</th>
                <th class="text-end">Duration (min)</th>
                <th class="text-end">Metric</th>
                <th class="text-end">Pts</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>
<?php $pageScript = 'PAGE="events";'; require __DIR__ . '/includes/footer.php'; ?>
