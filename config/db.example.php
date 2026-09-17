<?php
/**
 * Database bootstrap for the Fleet Driver Behavior System.
 * Auto-creates the database + schema on first run (great for a fresh XAMPP install).
 *
 * ── SETUP ─────────────────────────────────────────────────────────────────
 *  1. Copy this file to  config/db.php
 *  2. Fill in your own DB credentials and secrets below.
 *  3. config/db.php is git-ignored so your credentials are never committed.
 * ──────────────────────────────────────────────────────────────────────────
 */
declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

// ── Database connection ──────────────────────────────────────────────
// Local XAMPP defaults are root / (no password). On shared hosting
// (e.g. Hostinger) use the DB name/user/password from your hosting panel.
const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;
const DB_NAME = 'gps_report_system';
const DB_USER = 'root';
const DB_PASS = '';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    // Connect without a database first so we can create it if missing.
    // NOTE: on shared hosting the DB usually already exists and the user
    // cannot CREATE DATABASE — connect straight to DB_NAME instead:
    //   $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS, $opts);
    $root = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4', DB_USER, DB_PASS, $opts);
    $root->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $root->exec('USE `' . DB_NAME . '`');

    ensure_schema($root);
    seed_admin($root);
    seed_settings($root);

    $pdo = $root;
    return $pdo;
}

/** Seed default SMTP settings + report recipients on a fresh install. */
function seed_settings(PDO $pdo): void
{
    // >>> Put your own Google Maps API key here <<<
    $GOOGLE_MAPS_KEY = 'YOUR_GOOGLE_MAPS_API_KEY';

    $count = (int) $pdo->query('SELECT COUNT(*) FROM settings')->fetchColumn();
    if ($count > 0) {
        $pdo->prepare('INSERT IGNORE INTO settings (skey, svalue) VALUES (?,?)')
            ->execute(['google_maps_key', $GOOGLE_MAPS_KEY]);
        return;
    }
    $defaults = [
        'smtp_host'        => 'smtp.gmail.com',
        'smtp_port'        => '587',
        'smtp_secure'      => 'tls',
        'smtp_user'        => 'your-email@example.com',
        'smtp_pass'        => 'YOUR_SMTP_APP_PASSWORD', // Gmail: use an App Password, not your login
        'from_email'       => 'your-email@example.com',
        'from_name'        => 'GPS Violation Report System',
        'email_subject'    => 'NOTICE OF GPS POLICY VIOLATIONS',
        'report_start_time'=> '7:00 AM',
        'report_end_time'  => '7:00 AM',
        'google_maps_key'  => $GOOGLE_MAPS_KEY,
    ];
    $s = $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?,?)');
    foreach ($defaults as $k => $v) {
        $s->execute([$k, $v]);
    }

    // Default report recipients — add your own via the Email Reports page.
    $recipients = [
        // ['Labor Relations', 'recipient@example.com', 'to'],
    ];
    $r = $pdo->prepare('INSERT INTO report_recipients (name, email, rtype) VALUES (?,?,?)');
    foreach ($recipients as $rec) {
        $r->execute($rec);
    }
}

/** Create a default administrator on a fresh install. */
function seed_admin(PDO $pdo): void
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count === 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT); // change after first login
        $pdo->prepare('INSERT INTO users (name, username, email, role, status, password_hash)
                       VALUES (?,?,?,?,?,?)')
            ->execute(['Administrator', 'admin', 'admin@example.com', 'admin', 'active', $hash]);
    }
}

function ensure_schema(PDO $pdo): void
{
    $sql = file_get_contents(__DIR__ . '/schema.sql');
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if ($stmt !== '') {
            $pdo->exec($stmt);
        }
    }
}
