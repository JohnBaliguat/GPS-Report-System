<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../lib/Settings.php';
require_once __DIR__ . '/../lib/Mailer.php';

$pdo = db();
$canManage = Auth::role() !== 'viewer'; // admin + manager
$action = q('action', '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canManage) json_out(['status' => 'error', 'message' => 'You do not have permission for this action.'], 403);

    switch ($action) {
        case 'save_settings':
            $keys = ['smtp_host', 'smtp_port', 'smtp_secure', 'smtp_user', 'from_email',
                     'from_name', 'email_subject', 'report_start_time', 'report_end_time'];
            $kv = [];
            foreach ($keys as $k) {
                if (isset($_POST[$k])) $kv[$k] = trim((string) $_POST[$k]);
            }
            // Only overwrite the password if a new one was supplied.
            $pw = (string) q('smtp_pass', '');
            if ($pw !== '') $kv['smtp_pass'] = $pw;
            Settings::setMany($kv);
            json_out(['status' => 'ok', 'message' => 'Settings saved.']);

        case 'add_recipient':
            $email = trim((string) q('email', ''));
            $name  = trim((string) q('name', ''));
            $rtype = (string) q('rtype', 'to');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_out(['status' => 'error', 'message' => 'Enter a valid email address.'], 400);
            if (!in_array($rtype, ['to', 'cc', 'bcc'], true)) $rtype = 'to';
            try {
                $pdo->prepare('INSERT INTO report_recipients (name, email, rtype) VALUES (?,?,?)')->execute([$name, $email, $rtype]);
            } catch (PDOException $e) {
                json_out(['status' => 'error', 'message' => 'That email is already a recipient.'], 400);
            }
            json_out(['status' => 'ok']);

        case 'toggle_recipient':
            $pdo->prepare('UPDATE report_recipients SET active = 1 - active WHERE id = ?')->execute([(int) q('id')]);
            json_out(['status' => 'ok']);

        case 'delete_recipient':
            $pdo->prepare('DELETE FROM report_recipients WHERE id = ?')->execute([(int) q('id')]);
            json_out(['status' => 'ok']);

        case 'test':
            $to = trim((string) q('to', ''));
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) json_out(['status' => 'error', 'message' => 'Enter a valid test email.'], 400);
            json_out(Mailer::sendTest($to));

        case 'send':
            $from = (string) q('fromDate', '');
            $to   = (string) q('toDate', '');
            if (!$from || !$to) json_out(['status' => 'error', 'message' => 'Select a date range.'], 400);
            $me  = Auth::user();
            $res = Mailer::sendReport($from, $to, $me['name'] ?? '');

            // Optionally also create violation notices for the same drivers/period.
            if ($res['status'] === 'ok' && (string) q('gen_notices', '1') === '1') {
                try {
                    require_once __DIR__ . '/../lib/Notices.php';
                    $gen = Notices::generate($from, $to, [
                        'counseling_date'  => q('counseling_date', '') ?: null,
                        'counseling_time'  => q('counseling_time', '8:00 AM and 1:00 PM'),
                        'counseling_venue' => q('counseling_venue', 'PTSI Conference Room'),
                        'issued_by'        => 'Admin and Support',
                        'created_by'       => $me['name'] ?? '',
                    ]);
                    $res['notices_created'] = $gen['created'];
                    $res['notices_skipped'] = $gen['skipped'];
                    if ($gen['drivers'] === 0) {
                        $res['message'] .= ' No violating drivers in this date range, so no notices were created.';
                    } else {
                        $res['message'] .= " Violation notices: {$gen['created']} created from {$gen['drivers']} driver(s)"
                            . ($gen['skipped'] ? ", {$gen['skipped']} already existed." : '.');
                    }
                } catch (Throwable $ex) {
                    $res['notices_error'] = $ex->getMessage();
                    $res['message'] .= ' BUT creating notices failed: ' . $ex->getMessage()
                        . ' (' . basename($ex->getFile()) . ':' . $ex->getLine() . ')';
                }
            } else {
                $res['message'] .= ' [notice creation skipped: ' . ($res['status'] !== 'ok' ? 'email not sent' : 'checkbox off') . ']';
            }
            json_out($res);

        default:
            json_out(['status' => 'error', 'message' => 'Unknown action.'], 400);
    }
}

// GET — settings, recipients and recent log
$settings = Settings::all();
unset($settings['smtp_pass']); // never expose the stored password
$settings['smtp_pass_set'] = Settings::get('smtp_pass') !== '' ? '1' : '';

$recipients = $pdo->query('SELECT id, name, email, rtype, active FROM report_recipients ORDER BY rtype, email')->fetchAll();
$log = $pdo->query('SELECT period_from, period_to, recipients, status, message, sent_by, sent_at FROM email_log ORDER BY sent_at DESC LIMIT 20')->fetchAll();

// Sensible default date range = min/max event dates.
$range = $pdo->query('SELECT MIN(event_date) mn, MAX(event_date) mx FROM events')->fetch();

json_out([
    'settings'   => $settings,
    'recipients' => $recipients,
    'log'        => $log,
    'range'      => ['from' => $range['mn'] ?? '', 'to' => $range['mx'] ?? ''],
    'can_manage' => $canManage,
]);
