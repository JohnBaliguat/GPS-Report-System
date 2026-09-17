<?php
require __DIR__ . '/lib/Auth.php';
Auth::requirePage();
require_once __DIR__ . '/lib/Settings.php';
$mapsKey = Settings::get('google_maps_key');
$canManage = Auth::role() !== 'viewer';
$page = 'incidents';
$title = 'Incident Reports';
$subtitle = 'Log and track truck incidents';
require __DIR__ . '/includes/header.php';
?>
<div class="panel">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="m-0"><i class="bi bi-exclamation-triangle"></i> Incidents</h2>
    <?php if ($canManage): ?><button class="btn btn-primary btn-sm" id="addIncidentBtn"><i class="bi bi-plus-lg"></i> Report Incident</button><?php endif; ?>
  </div>
  <table id="incTable" class="table" style="width:100%">
    <thead><tr>
      <th>Date / Time</th><th>Truck / Driver</th><th>Type</th><th>Severity</th>
      <th>Location</th><th>Status</th><th class="text-end">Actions</th>
    </tr></thead>
    <tbody></tbody>
  </table>
</div>

<!-- Incident modal -->
<div class="modal fade" id="incModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content" style="border-radius:16px;border:none">
    <form id="incForm" enctype="multipart/form-data">
      <div class="modal-header"><h5 class="modal-title" id="incTitle">Report Incident</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" name="id" id="incId">
        <div id="incError" class="alert alert-danger py-2 d-none"></div>
        <div class="row g-3">
          <div class="col-md-4"><label class="form-label">Date &amp; Time</label><input type="datetime-local" class="form-control" name="incident_date" id="incDate" required></div>
          <div class="col-md-4"><label class="form-label">Truck Code</label><input class="form-control" name="truck_code" id="incTruck" placeholder="e.g. PM611"></div>
          <div class="col-md-4"><label class="form-label">Driver (optional)</label><select class="form-select" name="driver_id" id="incDriver"><option value="">—</option></select></div>
          <div class="col-md-4"><label class="form-label">Type</label>
            <select class="form-select" name="itype" id="incType">
              <option>Accident / Collision</option><option>Breakdown</option><option>Speeding</option>
              <option>Unauthorized Route</option><option>Cargo Issue</option><option>Theft / Security</option>
              <option>Traffic Violation</option><option>Other</option></select></div>
          <div class="col-md-4"><label class="form-label">Severity</label>
            <select class="form-select" name="severity" id="incSeverity">
              <option value="low">Low</option><option value="medium" selected>Medium</option>
              <option value="high">High</option><option value="critical">Critical</option></select></div>
          <div class="col-md-4"><label class="form-label">Status</label>
            <select class="form-select" name="status" id="incStatus">
              <option value="open">Open</option><option value="investigating">Investigating</option>
              <option value="resolved">Resolved</option><option value="closed">Closed</option></select></div>
          <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" id="incDesc" rows="2"></textarea></div>
          <div class="col-12"><label class="form-label">Location — click the map to drop a pin (drag to adjust)</label>
            <div id="incEditMap"></div>
            <div class="row g-2 mt-1">
              <div class="col-md-8"><input class="form-control form-control-sm" name="address" id="incAddress" placeholder="Address"></div>
              <div class="col-md-2"><input class="form-control form-control-sm" name="lat" id="incLat" placeholder="Lat" readonly></div>
              <div class="col-md-2"><input class="form-control form-control-sm" name="lng" id="incLng" placeholder="Lng" readonly></div>
            </div>
          </div>
          <div class="col-12"><label class="form-label">Photo (optional)</label><input type="file" class="form-control" name="photo" accept="image/*"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg"></i> Save Incident</button>
      </div>
    </form>
  </div></div>
</div>

<!-- View modal -->
<div class="modal fade" id="incViewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content" style="border-radius:16px;border:none">
    <div class="modal-header"><h5 class="modal-title">Incident #<span id="ivId"></span></h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body" id="ivBody"></div>
    <div class="modal-footer">
      <a href="#" id="ivPrint" target="_blank" class="btn btn-outline-primary btn-sm"><i class="bi bi-printer"></i> Print Report</a>
      <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
    </div>
  </div></div>
</div>

<script>window.GMAPS_KEY=<?= json_encode($mapsKey) ?>;window.CAN_MANAGE=<?= $canManage ? 'true' : 'false' ?>;PAGE="incidents";</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
