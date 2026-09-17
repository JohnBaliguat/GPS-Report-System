<?php
require __DIR__ . '/lib/Auth.php';
Auth::requirePage();
$page = 'reports';
$title = 'Report Builder';
$subtitle = 'Build, preview and export a custom report';
require __DIR__ . '/includes/header.php';
?>
<div class="filters">
  <div class="field"><label>Report Type</label>
    <select id="rbType">
      <option value="">All events</option>
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
    </select></div>
  <div class="field"><label>Driver</label><select id="rbDriver" style="min-width:200px"><option value="">All drivers</option></select></div>
  <div class="field"><label>From</label><input type="date" id="rbFrom"></div>
  <div class="field"><label>To</label><input type="date" id="rbTo"></div>
  <div class="field"><label>&nbsp;</label><button class="btn btn-primary btn-sm" id="rbApply"><i class="bi bi-funnel"></i> Build</button></div>
  <div class="field"><label>&nbsp;</label><button class="btn btn-success btn-sm" id="rbExport"><i class="bi bi-file-earmark-excel"></i> Export Excel</button></div>
  <div class="field"><label>&nbsp;</label><button class="btn btn-outline-secondary btn-sm" id="rbPrint"><i class="bi bi-printer"></i> Print</button></div>
</div>
<div class="panel">
  <h2><i class="bi bi-file-earmark-bar-graph"></i> Report Preview</h2>
  <table id="rbTable" class="table" style="width:100%">
    <thead><tr>
      <th>Date</th><th>Type</th><th>Driver</th><th>Zone / Location</th>
      <th class="text-end">Duration (min)</th><th class="text-end">Metric</th><th class="text-end">Pts</th>
    </tr></thead><tbody></tbody>
  </table>
</div>
<?php $pageScript = 'PAGE="reports";'; require __DIR__ . '/includes/footer.php'; ?>
