<?php
$page = 'profile';
$title = 'My Profile';
$subtitle = 'Manage your account details and password';
require __DIR__ . '/includes/header.php';
$initials = strtoupper(mb_substr((string) ($me['name'] ?? '?'), 0, 1));
?>
<div class="grid-2">
  <div class="panel">
    <h2><i class="bi bi-person-circle"></i> Account Details</h2>
    <div class="d-flex align-items-center gap-3 mb-4">
      <span class="avatar avatar-lg"><?= htmlspecialchars($initials) ?></span>
      <div>
        <div class="fw-bold fs-5"><?= htmlspecialchars($me['name']) ?></div>
        <div class="text-muted">@<?= htmlspecialchars($me['username']) ?></div>
        <span class="role-pill role-<?= htmlspecialchars($me['role']) ?> mt-1 d-inline-block"><?= htmlspecialchars($me['role']) ?></span>
      </div>
    </div>
    <form id="profileForm">
      <div id="pfMsg"></div>
      <div class="mb-3"><label class="form-label">Full Name</label><input class="form-control" name="name" value="<?= htmlspecialchars($me['name']) ?>" required></div>
      <div class="mb-3"><label class="form-label">Username</label><input class="form-control" value="<?= htmlspecialchars($me['username']) ?>" disabled></div>
      <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?= htmlspecialchars($me['email']) ?>"></div>
      <div class="mb-3"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= htmlspecialchars($me['phone']) ?>"></div>
      <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-check-lg"></i> Save Changes</button>
    </form>
  </div>

  <div class="panel">
    <h2><i class="bi bi-shield-lock"></i> Change Password</h2>
    <form id="passwordForm">
      <div id="pwMsg"></div>
      <div class="mb-3"><label class="form-label">Current Password</label><input type="password" class="form-control" name="current_password" autocomplete="current-password"></div>
      <div class="mb-3"><label class="form-label">New Password</label><input type="password" class="form-control" name="new_password" autocomplete="new-password"></div>
      <div class="mb-3"><label class="form-label">Confirm New Password</label><input type="password" class="form-control" id="pwConfirm"></div>
      <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-key"></i> Update Password</button>
      <div class="text-muted small mt-3">For security, changing your password requires your current password.</div>
    </form>
  </div>
</div>
<?php $pageScript = 'PAGE="profile";'; require __DIR__ . '/includes/footer.php'; ?>
