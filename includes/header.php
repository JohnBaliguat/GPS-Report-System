<?php
require_once __DIR__ . '/../lib/Auth.php';
Auth::requirePage();
$me = Auth::user();
$initials = strtoupper(mb_substr((string) ($me['name'] ?? '?'), 0, 1));

$page  = $page  ?? '';
$title = $title ?? 'Fleet Driver Behavior';
$nav = [
    'index'   => ['Dashboard', 'bi-speedometer2'],
    'drivers' => ['Driver Scorecards', 'bi-person-badge'],
    'events'  => ['Event Explorer', 'bi-list-columns-reverse'],
    'movement'=> ['Truck Movement', 'bi-geo-alt'],
    'map'     => ['Violations Map', 'bi-pin-map'],
    'incidents'=> ['Incident Reports', 'bi-exclamation-triangle'],
    'notices' => ['Violation Notices', 'bi-clipboard-check'],
    'reports' => ['Report Builder', 'bi-file-earmark-bar-graph'],
    'import'  => ['Import Reports', 'bi-cloud-upload'],
];
if (Auth::role() !== 'viewer') {
    $nav['email'] = ['Email Reports', 'bi-envelope-paper'];
}
if (Auth::isAdmin()) {
    $nav['users'] = ['User Management', 'bi-people-fill'];
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> · Panabo Fleet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <?php $appCss = __DIR__ . '/../assets/css/app.css'; $cssVer = is_file($appCss) ? filemtime($appCss) : time(); ?>
    <link href="assets/css/app.css?v=<?= $cssVer ?>" rel="stylesheet">
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="brand">
            <i class="bi bi-truck-front-fill"></i>
            <div>
                <span class="brand-title">FleetIQ</span>
                <span class="brand-sub">Driver Behavior</span>
            </div>
        </div>
        <nav class="nav-menu">
            <?php foreach ($nav as $key => [$label, $icon]): ?>
                <a href="<?= $key ?>.php" class="nav-item <?= $page === $key ? 'active' : '' ?>">
                    <i class="bi <?= $icon ?>"></i><span><?= $label ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-foot">
            <div class="org">Panabo Trucking Services</div>
            <div class="powered">Geotab telematics</div>
        </div>
    </aside>

    <div class="main">
        <header class="topbar">
            <div>
                <h1 class="page-title"><?= htmlspecialchars($title) ?></h1>
                <p class="page-sub" id="pageSub"><?= htmlspecialchars($subtitle ?? '') ?></p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <a href="import.php" class="btn btn-primary btn-sm"><i class="bi bi-cloud-upload"></i> Import Reports</a>
                <div class="dropdown user-menu">
                    <button class="btn user-btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="avatar"><?= htmlspecialchars($initials) ?></span>
                        <span class="user-meta">
                            <span class="u-name"><?= htmlspecialchars($me['name'] ?? '') ?></span>
                            <span class="u-role"><?= htmlspecialchars(ucfirst($me['role'] ?? '')) ?></span>
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-circle"></i> My Profile</a></li>
                        <?php if (Auth::isAdmin()): ?>
                        <li><a class="dropdown-item" href="users.php"><i class="bi bi-people"></i> Manage Users</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right"></i> Sign Out</a></li>
                    </ul>
                </div>
            </div>
        </header>
        <main class="content">
