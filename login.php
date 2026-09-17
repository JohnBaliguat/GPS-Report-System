<?php
require __DIR__ . '/lib/Auth.php';
Auth::start();
if (Auth::check()) { header('Location: index.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = (string) ($_POST['password'] ?? '');
    if (Auth::attempt($u, $p)) {
        header('Location: index.php');
        exit;
    }
    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in · FleetIQ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
            background:linear-gradient(135deg,#0f172a,#1e293b 60%,#312e81);
            font-family:'Inter',system-ui,'Segoe UI',sans-serif}
        .login-card{width:100%;max-width:400px;background:#fff;border-radius:20px;padding:38px 34px;
            box-shadow:0 30px 60px rgba(0,0,0,.35)}
        .brand{display:flex;align-items:center;gap:12px;margin-bottom:6px}
        .brand .bi{font-size:34px;color:#4f46e5}
        .brand h1{font-size:24px;font-weight:800;margin:0}
        .brand small{color:#64748b;text-transform:uppercase;letter-spacing:1.5px;font-size:11px}
        .sub{color:#64748b;font-size:14px;margin:14px 0 24px}
        .form-label{font-weight:600;font-size:13px;color:#334155}
        .form-control{padding:11px 14px;border-radius:11px}
        .btn-login{background:#4f46e5;border:none;padding:12px;border-radius:11px;font-weight:600;width:100%}
        .btn-login:hover{background:#4338ca}
        .hint{margin-top:18px;font-size:12px;color:#94a3b8;text-align:center;
            background:#f8fafc;border:1px dashed #e2e8f0;border-radius:10px;padding:10px}
        .input-group-text{border-radius:11px 0 0 11px;background:#f1f5f9;border-right:0}
    </style>
</head>
<body>
    <div class="login-card">
        <div class="brand"><i class="bi bi-truck-front-fill"></i>
            <div><h1>FleetIQ</h1><small>Driver Behavior</small></div></div>
        <p class="sub">Sign in to the Panabo Trucking fleet console.</p>
        <?php if ($error): ?>
            <div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" name="username" class="form-control" required autofocus>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" class="form-control" required>
                </div>
            </div>
            <button class="btn btn-login text-white" type="submit"><i class="bi bi-box-arrow-in-right"></i> Sign In</button>
        </form>
</body>
</html>
