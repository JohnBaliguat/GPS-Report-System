<?php
/**
 * Scheduled violation-report sender (run from CLI / Windows Task Scheduler).
 *
 * Usage:
 *   php cron\send_report.php                 # period = yesterday 00:00 .. today 00:00 (by date)
 *   php cron\send_report.php 2026-06-01 2026-06-08
 *
 * Schedule it on Windows (run daily at 7:05 AM):
 *   schtasks /Create /SC DAILY /ST 07:05 /TN "FleetIQ Daily Report" ^
 *     /TR "C:\xampp\php\php.exe \"C:\xampp\htdocs\GPS Report System\cron\send_report.php\""
 *
 * NOTE: this delivers real email to all ACTIVE recipients. Review the recipient
 *       list in the Email Reports page before scheduling.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

require __DIR__ . '/../lib/Mailer.php';

$from = $argv[1] ?? date('Y-m-d', strtotime('yesterday'));
$to   = $argv[2] ?? date('Y-m-d');

fwrite(STDOUT, "[" . date('Y-m-d H:i:s') . "] Sending GPS violation report for $from .. $to\n");
$res = Mailer::sendReport($from, $to, 'Scheduled Task');
fwrite(STDOUT, '  -> ' . $res['status'] . ': ' . $res['message'] . "\n");
exit($res['status'] === 'ok' ? 0 : 1);
