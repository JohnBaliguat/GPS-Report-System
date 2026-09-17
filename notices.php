<?php
require __DIR__ . '/lib/Auth.php';
Auth::requirePage();
$canManage = Auth::role() !== 'viewer';
$page = 'notices';
$title = 'Violation Notices';
$subtitle = 'Generate, print and monitor driver counseling notices';
require __DIR__ . '/includes/header.php';
?>
<div class="kpi-grid">
  <div class="kpi accent-indigo"><i class="bi bi-clipboard ic"></i><span class="lbl">Total Notices</span><span class="val" id="nkTotal">–</span></div>
  <div class="kpi accent-red"><i class="bi bi-hourglass ic"></i><span class="lbl">Pending</span><span class="val" id="nkPending">–</span></div>
  <div class="kpi accent-amber"><i class="bi bi-calendar-event ic"></i><span class="lbl">Scheduled</span><span class="val" id="nkScheduled">–</span></div>
  <div class="kpi accent-green"><i class="bi bi-check2-circle ic"></i><span class="lbl">Completed</span><span class="val" id="nkCompleted">–</span></div>
  <div class="kpi accent-slate"><i class="bi bi-person-x ic"></i><span class="lbl">No-Show</span><span class="val" id="nkNoShow">–</span></div>
</div>

<div class="panel">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h2 class="m-0"><i class="bi bi-clipboard-check"></i> Notices</h2>
    <div class="d-flex gap-2 align-items-center flex-wrap">
      <input type="date" id="printFrom" class="form-control form-control-sm" style="width:150px" title="Period from (optional)">
      <span class="text-muted small">to</span>
      <input type="date" id="printTo" class="form-control form-control-sm" style="width:150px" title="Period to (optional)">
      <select id="printStatus" class="form-select form-select-sm" style="width:140px" title="Status to print">
        <option value="pending" selected>Pending</option>
        <option value="scheduled">Scheduled</option>
        <option value="completed">Completed</option>
        <option value="no_show">No-Show</option>
        <option value="">All statuses</option>
      </select>
      <button class="btn btn-outline-secondary btn-sm" id="printBatchBtn"><i class="bi bi-printer"></i> Print</button>
      <?php if ($canManage): ?><button class="btn btn-primary btn-sm" id="genNoticesBtn"><i class="bi bi-magic"></i> Generate Notices</button><?php endif; ?>
    </div>
  </div>
  <table id="noticeTable" class="table" style="width:100%">
    <thead><tr>
      <th>Control No.</th><th>Driver</th><th>Unit</th><th>Period</th>
      <th>Violations</th><th>Counseling</th><th>Status</th><th class="text-end">Actions</th>
    </tr></thead><tbody></tbody>
  </table>
</div>

<!-- Generate modal -->
<div class="modal fade" id="genModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content" style="border-radius:16px;border:none">
    <form id="genForm">
      <div class="modal-header"><h5 class="modal-title">Generate Violation Notices</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div id="genMsg"></div>
        <p class="text-muted small">Creates one notice per driver who has violations in the selected period (speeding, restricted zone, no-parking, overstay, idling). Drivers already issued a notice for the same period are skipped.</p>
        <div class="row g-3">
          <div class="col-6"><label class="form-label">Violation Period — From</label><input type="date" class="form-control" name="from" id="genFrom" required></div>
          <div class="col-6"><label class="form-label">To</label><input type="date" class="form-control" name="to" id="genTo" required></div>
          <div class="col-12"><hr class="my-1"><div class="text-muted small">Counseling schedule (printed on each notice)</div></div>
          <div class="col-6"><label class="form-label">Counseling Date</label><input type="date" class="form-control" name="counseling_date" id="genCDate"></div>
          <div class="col-6"><label class="form-label">Time</label><input class="form-control" name="counseling_time" value="8:00 AM and 1:00 PM"></div>
          <div class="col-12"><label class="form-label">Venue</label><input class="form-control" name="counseling_venue" value="PTSI Conference Room"></div>
          <div class="col-12"><label class="form-label">Issued By</label><input class="form-control" name="issued_by" value="Admin and Support"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-magic"></i> Generate</button>
      </div>
    </form>
  </div></div>
</div>

<!-- Status modal -->
<div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content" style="border-radius:16px;border:none">
    <form id="statusForm">
      <div class="modal-header"><h5 class="modal-title">Update Counseling Status</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <input type="hidden" name="id" id="stId">
        <div class="mb-2 text-muted small" id="stDriver"></div>
        <div class="mb-3"><label class="form-label">Status</label>
          <select class="form-select" name="status" id="stStatus">
            <option value="pending">Pending</option>
            <option value="scheduled">Scheduled</option>
            <option value="completed">Completed (attended counseling)</option>
            <option value="no_show">No-Show</option>
          </select></div>
        <div class="mb-1"><label class="form-label">Remarks (optional)</label><textarea class="form-control" name="remarks" id="stRemarks" rows="2"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg"></i> Save</button>
      </div>
    </form>
  </div></div>
</div>

<script>window.CAN_MANAGE=<?= $canManage ? 'true' : 'false' ?>;PAGE="notices";</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
