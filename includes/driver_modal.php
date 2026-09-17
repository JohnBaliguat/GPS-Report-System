<div class="modal fade" id="driverModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="border-radius:18px;border:none;overflow:hidden">
      <div class="modal-header" style="background:linear-gradient(90deg,#111827,#1e293b);color:#fff;border:none">
        <div>
          <h5 class="modal-title" id="dmName">Driver</h5>
          <div style="font-size:12px;color:#94a3b8" id="dmCode"></div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex align-items-center gap-4 mb-4 flex-wrap">
          <div class="text-center">
            <div class="score-badge" id="dmScore" style="width:72px;height:72px;font-size:26px">–</div>
            <div class="mt-2"><span class="grade-tag" id="dmGrade">–</span></div>
            <div class="text-muted mt-1" style="font-size:11px">Behavior Score</div>
          </div>
          <div class="flex-grow-1">
            <div class="row g-3">
              <div class="col-6 col-md-4"><div class="text-muted small">Total Events</div><div class="fw-bold fs-5" id="dmEvents">–</div></div>
              <div class="col-6 col-md-4"><div class="text-muted small">Demerit Points</div><div class="fw-bold fs-5 text-danger" id="dmPoints">–</div></div>
              <div class="col-12 col-md-4"><div class="text-muted small">Event Types</div><div class="fw-bold fs-5" id="dmTypes">–</div></div>
            </div>
          </div>
        </div>
        <h6 class="text-muted text-uppercase" style="font-size:11px;letter-spacing:.5px">Behavior Breakdown</h6>
        <div class="chart-box sm" style="height:230px"><canvas id="dmChart"></canvas></div>
        <table class="table table-sm mt-3 mb-0">
          <thead><tr><th>Type</th><th class="text-end">Count</th><th class="text-end">Total Duration (min)</th><th class="text-end">Points</th></tr></thead>
          <tbody id="dmTable"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <a href="#" id="dmViewEvents" class="btn btn-sm btn-outline-primary"><i class="bi bi-list"></i> View all events</a>
        <button class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
