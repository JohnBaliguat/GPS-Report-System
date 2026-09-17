<?php
require __DIR__ . '/lib/Auth.php';
Auth::requirePage();
require_once __DIR__ . '/config/db.php';

$pdo = db();
$cond = [];
$args = [];
if (($id = $_GET['id'] ?? '') !== '') {
    $cond[] = 'id = ?'; $args[] = (int) $id;
} elseif (($ids = $_GET['ids'] ?? '') !== '') {
    $list = array_filter(array_map('intval', explode(',', $ids)));
    if ($list) { $cond[] = 'id IN (' . implode(',', array_fill(0, count($list), '?')) . ')'; $args = array_merge($args, $list); }
} else {
    if (($status = $_GET['status'] ?? '') !== '') {
        $cond[] = 'status = ?'; $args[] = $status;
    }
    // Filter by the notice's violation period overlapping [from, to].
    if (($from = $_GET['from'] ?? '') !== '') {
        $cond[] = 'period_to >= ?'; $args[] = $from;
    }
    if (($to = $_GET['to'] ?? '') !== '') {
        $cond[] = 'period_from <= ?'; $args[] = $to;
    }
}
$where = $cond ? ' WHERE ' . implode(' AND ', $cond) : '';
$sql = 'SELECT * FROM violation_notices' . $where . ' ORDER BY control_no';
$st = $pdo->prepare($sql);
$st->execute($args);
$notices = $st->fetchAll();

function h($v){ return htmlspecialchars((string) $v); }
function fdate($d){ return $d ? date('F j, Y', strtotime($d)) : '__________________'; }

/** Counseling date as "Month j, Y (Day)" — always falls back to the next Monday if blank. */
function counselDate($d): string
{
    $raw = trim((string) $d);
    $ts = ($raw && $raw !== '0000-00-00') ? strtotime($raw) : false;
    if (!$ts) {
        $ts = strtotime('next monday'); // never leave the Date blank
    }
    return date('F j, Y (l)', $ts);
}

/** One violation-type cell: red+checked when count>0, otherwise grey+unchecked. */
function vopt(string $label, int $n): string
{
    if ($n > 0) {
        return '<span class="box on">&#9746;</span><span class="on">' . $label . ' (' . $n . ')</span>';
    }
    return '<span class="box off">&#9744;</span><span class="off">' . $label . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><title>Violation Notices</title>
<style>
  @page { size: Letter; margin: 0.4in; }
  *{ box-sizing:border-box; }
  body{ font-family:Arial,Helvetica,sans-serif; color:#1a1a1a; margin:0; background:#e9edf2; }
  .toolbar{ position:sticky; top:0; background:#fff; border-bottom:1px solid #ccc; padding:10px 18px; z-index:10; }
  .toolbar button{ padding:8px 16px; background:#4f46e5; color:#fff; border:none; border-radius:8px; cursor:pointer; font-size:14px; }
  .toolbar a{ margin-left:10px; }

  /* 2-up grid: ~4 notices per Letter page */
  .sheet{ width:8.5in; margin:14px auto; padding:0 0.12in; font-size:0; }
  .notice{ display:inline-block; vertical-align:top; width:48.7%; margin:0.06in 0.6%;
           border:1.5px solid #000; background:#fff; padding:9px 11px;
           font-size:9px; line-height:1.28; break-inside:avoid; box-shadow:0 1px 6px rgba(0,0,0,.12); }

  .title{ text-align:center; color:#d32f2f; font-size:12.5px; font-weight:800; letter-spacing:.3px;
          border-top:2px solid #d32f2f; border-bottom:1.5px solid #d32f2f; padding:4px 0; margin:0 0 6px; }

  .meta-line{ margin:2px 0; }
  .lbl{ color:#666; }
  .meta-line b{ color:#000; }
  .gap{ display:inline-block; width:10px; }

  .section{ color:#1d4ed8; font-weight:800; font-size:9.5px; letter-spacing:.3px;
            border-bottom:1.5px solid #1d4ed8; padding-bottom:2px; margin:7px 0 4px; }

  .vgrid{ width:100%; border-collapse:collapse; }
  .vgrid td{ border:1px solid #b9b9b9; padding:4px 7px; width:50%; font-size:9px; vertical-align:middle; }
  .box{ margin-right:5px; font-size:11px; vertical-align:-1px; }
  .on{ color:#d32f2f; font-weight:bold; }
  .off{ color:#444; }
  .total{ text-align:center; font-weight:bold; font-size:10px; }

  .info{ font-style:italic; color:#555; margin:6px 0; line-height:1.35; }
  .mand{ color:#d32f2f; font-weight:800; font-size:9.5px; letter-spacing:.2px;
         border-top:1.5px solid #d32f2f; border-bottom:1.5px solid #d32f2f; padding:3px 0; margin:8px 0 5px; }
  .mand-text{ color:#d32f2f; font-weight:bold; line-height:1.35; margin:5px 0; }
  .sched-intro{ font-weight:bold; margin:7px 0 3px; }

  .sched{ width:100%; border-collapse:collapse; margin:2px 0 4px; }
  .sched td{ padding:4px 5px; }
  .sched .k{ width:54px; color:#222; }
  .sched .v{ border-bottom:1px solid #888; font-weight:bold; }

  .ack{ font-style:italic; color:#555; text-align:center; margin:18px 0 0; }
  .signs{ display:flex; gap:26px; margin-top:6px; }
  .sig{ flex:1; text-align:center; }
  .sig .above{ min-height:18px; display:flex; align-items:flex-end; justify-content:center; font-weight:bold; }
  .sig .line{ border-top:1px solid #333; margin-top:2px; padding-top:3px; color:#444; }

  .counsel{ margin-top:18px; border-top:1px solid #ccc; padding-top:9px; }
  .counsel .ack{ margin-top:0; }
  .counsel .sig{ max-width:70%; margin:18px auto 0; }
  .done-note{ text-align:center; color:#15803d; font-weight:bold; font-size:8.5px; margin-top:8px; }

  @media print{
    body{ background:#fff; } .toolbar{ display:none; }
    .sheet{ width:auto; margin:0; padding:0; }
    .notice{ box-shadow:none; }
  }
</style></head>
<body>
<div class="toolbar">
  <button onclick="window.print()">&#128424; Print <?= count($notices) ?> Notice<?= count($notices) === 1 ? '' : 's' ?> (Letter, 2-up)</button>
  <a href="notices.php">&larr; Back to monitoring</a>
</div>

<div class="sheet">
<?php if (!$notices): ?>
  <div class="notice"><p style="text-align:center;padding:30px 0">No notices found.</p></div>
<?php endif; ?>

<?php foreach ($notices as $n): ?>
<div class="notice">
  <div class="title">GPS MONITORING VIOLATION NOTICE</div>

  <div class="meta-line">
    <span class="lbl">Driver:</span> <b><?= h($n['driver_name']) ?></b><span class="gap"></span>
    <span class="lbl">Unit No.:</span> <b><?= h($n['unit_no']) ?></b><span class="gap"></span>
    <span class="lbl">Control No.:</span> <b><?= h($n['control_no']) ?></b>
  </div>
  <div class="meta-line"><span class="lbl">Date of Violation:</span>
    <b><?= h(fdate($n['period_from'])) ?><?= $n['period_to'] && $n['period_to'] !== $n['period_from'] ? ' &ndash; ' . h(fdate($n['period_to'])) : '' ?></b></div>
  <div class="meta-line"><span class="lbl">Location:</span> <b><?= h($n['location'] ?: '____________________') ?></b></div>

  <div class="section">VIOLATION TYPE</div>
  <table class="vgrid">
    <tr>
      <td><?= vopt('Overspeeding', (int) $n['c_speeding']) ?></td>
      <td><?= vopt('Restricted Zone', (int) $n['c_restricted']) ?></td>
    </tr>
    <tr>
      <td><?= vopt('No-Parking', (int) $n['c_no_parking']) ?></td>
      <td><?= vopt('Overstay', (int) $n['c_overstay']) ?></td>
    </tr>
    <tr>
      <td><?= vopt('Excessive Idle', (int) $n['c_idle']) ?></td>
      <td class="total">TOTAL&nbsp;&nbsp;<?= (int) $n['total'] ?></td>
    </tr>
  </table>

  <p class="info">This is to inform you that the above violation was recorded through the GPS Monitoring System.</p>

  <div class="mand">MANDATORY ACTION REQUIRED</div>
  <p class="mand-text">Your access to the Dispatch System will be temporarily blocked if you failed to report to the
     Admin Office and complete the required counseling session. Access is restored after compliance.</p>

  <div class="sched-intro">You are scheduled for a counseling session as follows:</div>
  <table class="sched">
    <tr><td class="k">Date</td><td class="v"><?= h(counselDate($n['counseling_date'])) ?></td></tr>
    <tr><td class="k">Time</td><td class="v"><?= h($n['counseling_time']) ?></td></tr>
    <tr><td class="k">Venue</td><td class="v"><?= h($n['counseling_venue']) ?></td></tr>
  </table>

  <p class="ack">Received and acknowledged by the driver named above.</p>
  <div class="signs">
    <div class="sig"><div class="above">&nbsp;</div><div class="line">Driver&#39;s Signature</div></div>
    <div class="sig"><div class="above"><?= h($n['issued_by'] ?: 'Admin and Support') ?></div><div class="line">Issued By</div></div>
  </div>

  <div class="counsel">
    <p class="ack">Counseling completion &mdash; to be signed by the counselor after the session.</p>
    <div class="sig"><div class="above">&nbsp;</div><div class="line">Counselor&#39;s Signature</div></div>
    <?php if ($n['status'] === 'completed'): ?>
      <div class="done-note">&#10003; Counseling completed<?= $n['counseled_at'] ? ' on ' . h(date('M j, Y', strtotime($n['counseled_at']))) : '' ?><?= $n['counseled_by'] ? ' &middot; recorded by ' . h($n['counseled_by']) : '' ?></div>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
</div>
</body></html>
