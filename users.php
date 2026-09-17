<?php
require __DIR__ . '/lib/Auth.php';
Auth::requireAdminPage();
$page = 'users';
$title = 'User Management';
$subtitle = 'Add, edit and remove console users';
require __DIR__ . '/includes/header.php';
?>
<div class="panel">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="m-0"><i class="bi bi-people-fill"></i> System Users</h2>
        <button class="btn btn-primary btn-sm" id="addUserBtn"><i class="bi bi-person-plus"></i> Add User</button>
    </div>
    <table id="userTable" class="table" style="width:100%">
        <thead>
            <tr>
                <th>User</th>
                <th>Username</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Role</th>
                <th>Status</th>
                <th>Last Login</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>

<!-- User modal -->
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:16px;border:none">
      <form id="userForm">
        <div class="modal-header">
          <h5 class="modal-title" id="umTitle">Add User</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="umId">
          <input type="hidden" name="action" id="umAction" value="create">
          <div id="umError" class="alert alert-danger py-2 d-none"></div>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Full Name</label><input class="form-control" name="name" id="umName" required></div>
            <div class="col-md-6"><label class="form-label">Username</label><input class="form-control" name="username" id="umUsername" required></div>
            <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="email" id="umEmail"></div>
            <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone" id="umPhone"></div>
            <div class="col-md-6"><label class="form-label">Role</label>
              <select class="form-select" name="role" id="umRole">
                <option value="admin">Admin</option>
                <option value="manager">Manager</option>
                <option value="viewer" selected>Viewer</option>
              </select></div>
            <div class="col-md-6"><label class="form-label">Status</label>
              <select class="form-select" name="status" id="umStatus">
                <option value="active" selected>Active</option>
                <option value="inactive">Inactive</option>
              </select></div>
            <div class="col-12"><label class="form-label">Password <small class="text-muted" id="umPwHint">(min 6 chars)</small></label>
              <input type="password" class="form-control" name="password" id="umPassword" autocomplete="new-password"></div>
          </div>
          <div class="text-muted small mt-2"><b>Roles:</b> Admin = full control + user management · Manager/Viewer = dashboards &amp; reports only.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg"></i> Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php $pageScript = 'PAGE="users";'; require __DIR__ . '/includes/footer.php'; ?>
