<?php
/**
 * Thin wrapper around PHPMailer that pulls SMTP config + recipients from the
 * database, and sends the GPS violation report (or a test message).
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/ReportExcel.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

class Mailer
{
    private static function configure(PHPMailer $mail): void
    {
        $mail->isSMTP();
        $mail->Host       = Settings::get('smtp_host', 'smtp.gmail.com');
        $mail->SMTPAuth   = true;
        $mail->Username   = Settings::get('smtp_user');
        $mail->Password   = Settings::get('smtp_pass');
        $secure           = Settings::get('smtp_secure', 'tls');
        $mail->SMTPSecure = $secure === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) Settings::get('smtp_port', '587');
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom(
            Settings::get('from_email', Settings::get('smtp_user')),
            Settings::get('from_name', 'GPS Report System')
        );
    }

    /** @return array{recipients:int} */
    private static function addRecipients(PHPMailer $mail): array
    {
        $rows = db()->query('SELECT email, name, rtype FROM report_recipients WHERE active = 1')->fetchAll();
        $n = 0;
        foreach ($rows as $r) {
            $email = trim($r['email']);
            if ($email === '') continue;
            switch ($r['rtype']) {
                case 'cc':  $mail->addCC($email, $r['name']); break;
                case 'bcc': $mail->addBCC($email); break;
                default:    $mail->addAddress($email, $r['name']); break;
            }
            $n++;
        }
        return ['recipients' => $n];
    }

    /** Send the violation report for a date range. Logs the result. */
    public static function sendReport(string $fromDate, string $toDate, string $sentBy = ''): array
    {
        $report = ReportExcel::build($fromDate, $toDate);
        $subject = Settings::get('email_subject', 'NOTICE OF GPS POLICY VIOLATIONS');
        $mail = new PHPMailer(true);
        $recipients = 0;
        try {
            self::configure($mail);
            $r = self::addRecipients($mail);
            $recipients = $r['recipients'];
            if ($recipients === 0) {
                throw new MailException('No active recipients configured.');
            }
            $mail->addAttachment($report['file'], $report['filename']);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = self::buildBody($fromDate, $toDate, $report['summary']);
            $mail->send();
            @unlink($report['file']);

            self::log($fromDate, $toDate, $recipients, $subject, $report['filename'], 'sent', 'Email sent successfully.', $sentBy);
            return ['status' => 'ok', 'message' => "Report emailed to $recipients recipient group(s).",
                    'drivers' => $report['total_drivers'], 'violations' => $report['total_violations']];
        } catch (\Throwable $e) {
            @unlink($report['file']);
            $err = $mail->ErrorInfo ?: $e->getMessage();
            self::log($fromDate, $toDate, $recipients, $subject, $report['filename'], 'failed', $err, $sentBy);
            return ['status' => 'error', 'message' => 'Email could not be sent: ' . $err];
        }
    }

    /** Send a quick test message to verify SMTP credentials. */
    public static function sendTest(string $to): array
    {
        $mail = new PHPMailer(true);
        try {
            self::configure($mail);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = 'FleetIQ SMTP Test';
            $mail->Body = '<p>This is a test email from the <b>FleetIQ GPS Report System</b>. '
                        . 'If you received this, your SMTP settings are working.</p>';
            $mail->send();
            return ['status' => 'ok', 'message' => "Test email sent to $to."];
        } catch (\Throwable $e) {
            return ['status' => 'error', 'message' => 'Test failed: ' . ($mail->ErrorInfo ?: $e->getMessage())];
        }
    }

    private static function buildBody(string $from, string $to, array $summary): string
    {
        $startTime = Settings::get('report_start_time', '7:00 AM');
        $endTime   = Settings::get('report_end_time', '7:00 AM');
        $fromF = date('F d, Y', strtotime($from));
        $toF   = date('F d, Y', strtotime($to));

        $table = '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-family:Arial,sans-serif;font-size:13px">
            <tr style="background:#4F46E5;color:#fff">
              <th>Rank</th><th>Driver</th><th>Speeding</th><th>Restricted</th><th>No-Parking</th><th>Overstay</th><th>Idling</th><th>Total</th>
            </tr>';
        if (!$summary) {
            $table .= '<tr><td colspan="8" style="text-align:center">No violations found for this period.</td></tr>';
        }
        foreach ($summary as $e) {
            $table .= '<tr>'
                . '<td align="center">' . $e['rank'] . '</td>'
                . '<td>' . htmlspecialchars($e['driver']) . '</td>'
                . '<td align="center">' . $e['speeding'] . '</td>'
                . '<td align="center">' . $e['restricted'] . '</td>'
                . '<td align="center">' . $e['no_parking'] . '</td>'
                . '<td align="center">' . $e['overstay'] . '</td>'
                . '<td align="center">' . $e['idling'] . '</td>'
                . '<td align="center"><b>' . $e['total'] . '</b></td>'
                . '</tr>';
        }
        $table .= '</table>';

        return "
            <div style=\"font-family:Arial,sans-serif;font-size:14px;color:#1f2937\">
            <p>Dear Sir/Madam:</p>
            <p>Please be advised that the following drivers have been identified with GPS policy
               violations for the reporting period from <strong>$fromF</strong> at $startTime to
               <strong>$toF</strong> at $endTime.</p>
            <p><strong>SUMMARY OF THE TOP 10 DRIVERS WITH THE HIGHEST NUMBER OF VIOLATIONS:</strong></p>
            $table
            <p>For the complete list of all involved drivers and the specific details of each
               violation, kindly refer to the attached Excel file.</p>
            <p>Thank you for your attention to this matter.</p>
            <p><strong><i>GPS Monitoring Team</i></strong><br>
               <i>This is a system-generated email. Please do not reply.</i></p>
            </div>";
    }

    private static function log(string $from, string $to, int $recips, string $subject, string $file, string $status, string $msg, string $by): void
    {
        db()->prepare('INSERT INTO email_log (period_from, period_to, recipients, subject, filename, status, message, sent_by)
                       VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$from, $to, $recips, $subject, $file, $status, $msg, $by]);
    }
}
