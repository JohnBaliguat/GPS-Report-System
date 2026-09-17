<?php
require __DIR__ . '/lib/Auth.php';
Auth::requirePage();
if (Auth::role() === 'viewer') { header('Location: index.php'); exit; }
$page = 'email';
$title = 'Email Reports';
$subtitle = 'Generate and email the GPS violation report';
require __DIR__ . '/includes/header.php';
?>
<div class="grid-2" style="margin-bottom:24px">
  <!-- Send report -->
  <div class="panel">
    <h2><i class="bi bi-send"></i> Send Violation Report</h2>
    <p class="text-muted" style="font-size:13px;margin-top:-8px">
      Builds a ranked Excel workbook (summary + per-driver detail sheets) and emails it to all active recipients with a top-10 summary table.</p>
    <div id="sendMsg"></div>
    <div class="row g-3 align-items-end">
      <div class="col-sm-5"><label class="form-label">From date</label><input type="date" class="form-control" id="sendFrom"></div>
      <div class="col-sm-5"><label class="form-label">To date</label><input type="date" class="form-control" id="sendTo"></div>
      <div class="col-sm-2"><button class="btn btn-primary w-100" id="sendBtn"><i class="bi bi-send"></i></button></div>
    </div>
    <div class="form-check mt-3">
      <input class="form-check-input" type="checkbox" id="sendGenNotices" checked>
      <label class="form-check-label" for="sendGenNotices">Also create Violation Notices for these drivers (skips any that already exist)</label>
    </div>
    <button class="btn btn-outline-secondary btn-sm mt-3" id="previewBtn"><i class="bi bi-download"></i> Download workbook only (no email)</button>
  </div>

  <!-- SMTP settings -->
  <div class="panel">
    <h2><i class="bi bi-gear"></i> SMTP Settings</h2>
    <div id="settingsMsg"></div>
    <form id="settingsForm" class="row g-2">
      <div class="col-8"><label class="form-label">SMTP Host</label><input class="form-control form-control-sm" name="smtp_host" id="s_host"></div>
      <div class="col-4"><label class="form-label">Port</label><input class="form-control form-control-sm" name="smtp_port" id="s_port"></div>
      <div class="col-6"><label class="form-label">Security</label>
        <select class="form-select form-select-sm" name="smtp_secure" id="s_secure"><option value="tls">TLS</option><option value="ssl">SSL</option></select></div>
      <div class="col-6"><label class="form-label">SMTP Username</label><input class="form-control form-control-sm" name="smtp_user" id="s_user"></div>
      <div class="col-12"><label class="form-label">SMTP Password <small class="text-muted" id="s_pwhint"></small></label>
        <input type="password" class="form-control form-control-sm" name="smtp_pass" id="s_pass" placeholder="••••••••" autocomplete="new-password"></div>
      <div class="col-6"><label class="form-label">From Email</label><input class="form-control form-control-sm" name="from_email" id="s_femail"></div>
      <div class="col-6"><label class="form-label">From Name</label><input class="form-control form-control-sm" name="from_name" id="s_fname"></div>
      <div class="col-12"><label class="form-label">Email Subject</label><input class="form-control form-control-sm" name="email_subject" id="s_subject"></div>
      <div class="col-6"><label class="form-label">Report Start Time</label><input class="form-control form-control-sm" name="report_start_time" id="s_start"></div>
      <div class="col-6"><label class="form-label">Report End Time</label><input class="form-control form-control-sm" name="report_end_time" id="s_end"></div>
      <div class="col-12 d-flex gap-2 mt-2">
        <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-check-lg"></i> Save Settings</button>
        <div class="input-group input-group-sm" style="max-width:320px">
          <input class="form-control" id="testEmail" placeholder="test@email.com">
          <button class="btn btn-outline-secondary" type="button" id="testBtn"><i class="bi bi-envelope-check"></i> Send Test</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Recipients -->
<div class="panel">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="m-0"><i class="bi bi-people"></i> Report Recipients</h2>
    <button class="btn btn-primary btn-sm" id="addRecipientBtn"><i class="bi bi-person-plus"></i> Add Recipient</button>
  </div>
  <div id="recipMsg"></div>
  <table id="recipTable" class="table" style="width:100%">
    <thead><tr><th>Email</th><th>Name</th><th>Type</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
    <tbody></tbody>
  </table>
</div>

<!-- Email log -->
<div class="panel">
  <h2><i class="bi bi-clock-history"></i> Email Log</h2>
  <table id="logTable" class="table" style="width:100%">
    <thead><tr><th>Sent At</th><th>Period</th><th class="text-end">Recipients</th><th>Status</th><th>Detail</th><th>By</th></tr></thead>
    <tbody></tbody>
  </table>
</div>

<!-- Add recipient modal -->
<div class="modal fade" id="recipModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content" style="border-radius:16px;border:none">
    <form id="recipForm">
      <div class="modal-header"><h5 class="modal-title">Add Recipient</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div id="rmError" class="alert alert-danger py-2 d-none"></div>
        <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" required></div>
        <div class="mb-3"><label class="form-label">Name (optional)</label><input class="form-control" name="name"></div>
        <div class="mb-1"><label class="form-label">Recipient Type</label>
          <select class="form-select" name="rtype">
            <option value="to">To</option><option value="cc">CC</option><option value="bcc">BCC</option>
          </select></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg"></i> Add</button>
      </div>
    </form>
  </div></div>
</div>
<?php $pageScript = 'PAGE="email";'; require __DIR__ . '/includes/footer.php'; ?>
