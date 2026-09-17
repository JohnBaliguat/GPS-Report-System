<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';
Auth::requireApi(true); // admin only

$pdo = db();
$action = q('action', '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = (int) q('id', 0);
    $name     = trim((string) q('name', ''));
    $username = trim((string) q('username', ''));
    $email    = trim((string) q('email', ''));
    $phone    = trim((string) q('phone', ''));
    $role     = (string) q('role', 'viewer');
    $status   = (string) q('status', 'active');
    $password = (string) q('password', '');

    if (!in_array($role, ['admin', 'manager', 'viewer'], true)) $role = 'viewer';
    if (!in_array($status, ['active', 'inactive'], true)) $status = 'active';

    if ($action === 'delete') {
        if ($id === Auth::id()) json_out(['status' => 'error', 'message' => 'You cannot delete your own account.'], 400);
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
        json_out(['status' => 'ok']);
    }

    if ($name === '' || $username === '') {
        json_out(['status' => 'error', 'message' => 'Name and username are required.'], 400);
    }

    // Unique username check
    $chk = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id <> ?');
    $chk->execute([$username, $id]);
    if ($chk->fetch()) json_out(['status' => 'error', 'message' => 'That username is already taken.'], 400);

    if ($action === 'create') {
        if (strlen($password) < 6) json_out(['status' => 'error', 'message' => 'Password must be at least 6 characters.'], 400);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare('INSERT INTO users (name, username, email, phone, role, status, password_hash)
                       VALUES (?,?,?,?,?,?,?)')
            ->execute([$name, $username, $email, $phone, $role, $status, $hash]);
        json_out(['status' => 'ok', 'id' => (int) $pdo->lastInsertId()]);
    }

    if ($action === 'update') {
        // Prevent an admin from locking themselves out (demote/deactivate self).
        if ($id === Auth::id() && ($role !== 'admin' || $status !== 'active')) {
            json_out(['status' => 'error', 'message' => 'You cannot change your own role or status.'], 400);
        }
        if ($password !== '') {
            if (strlen($password) < 6) json_out(['status' => 'error', 'message' => 'Password must be at least 6 characters.'], 400);
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET name=?, username=?, email=?, phone=?, role=?, status=?, password_hash=? WHERE id=?')
                ->execute([$name, $username, $email, $phone, $role, $status, $hash, $id]);
        } else {
            $pdo->prepare('UPDATE users SET name=?, username=?, email=?, phone=?, role=?, status=? WHERE id=?')
                ->execute([$name, $username, $email, $phone, $role, $status, $id]);
        }
        json_out(['status' => 'ok']);
    }

    json_out(['status' => 'error', 'message' => 'Unknown action.'], 400);
}

// GET — list all users
$rows = $pdo->query('SELECT id, name, username, email, phone, role, status, last_login, created_at FROM users ORDER BY created_at DESC')->fetchAll();
$data = [];
foreach ($rows as $r) {
    $data[] = [
        'id' => (int) $r['id'],
        'name' => $r['name'],
        'username' => $r['username'],
        'email' => $r['email'],
        'phone' => $r['phone'],
        'role' => $r['role'],
        'status' => $r['status'],
        'last_login' => $r['last_login'],
        'created_at' => $r['created_at'],
        'is_self' => ((int) $r['id'] === Auth::id()),
    ];
}
json_out(['data' => $data]);
