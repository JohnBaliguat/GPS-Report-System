<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';

$pdo = db();
$id  = Auth::id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim((string) q('name', ''));
    $email = trim((string) q('email', ''));
    $phone = trim((string) q('phone', ''));
    $current = (string) q('current_password', '');
    $new     = (string) q('new_password', '');

    if ($name === '') json_out(['status' => 'error', 'message' => 'Name is required.'], 400);

    // Password change requires the current password.
    if ($new !== '') {
        $u = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $u->execute([$id]);
        $row = $u->fetch();
        if (!$row || !password_verify($current, $row['password_hash'])) {
            json_out(['status' => 'error', 'message' => 'Current password is incorrect.'], 400);
        }
        if (strlen($new) < 6) json_out(['status' => 'error', 'message' => 'New password must be at least 6 characters.'], 400);
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE users SET name=?, email=?, phone=?, password_hash=? WHERE id=?')
            ->execute([$name, $email, $phone, $hash, $id]);
    } else {
        $pdo->prepare('UPDATE users SET name=?, email=?, phone=? WHERE id=?')
            ->execute([$name, $email, $phone, $id]);
    }
    $_SESSION['name'] = $name;
    json_out(['status' => 'ok']);
}

json_out(['status' => 'ok', 'user' => Auth::user()]);
