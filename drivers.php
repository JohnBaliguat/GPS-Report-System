<?php
$page = 'drivers';
$title = 'Driver Scorecards';
$subtitle = 'Behavior ranking — lower score = higher risk';
require __DIR__ . '/includes/header.php';
?>
<div class="panel">
    <h2><i class="bi bi-person-badge"></i> Fleet Driver Scorecards</h2>
    <table id="driverTable" class="table" style="width:100%">
        <thead>
            <tr>
                <th>Score</th>
                <th>Grade</th>
                <th>Driver</th>
                <th class="text-end">Speeding</th>
                <th class="text-end">Zone Breaches</th>
                <th class="text-end">Idling</th>
                <th class="text-end">Idle (min)</th>
                <th class="text-end">Events</th>
                <th class="text-end">Demerit Pts</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>
<?php require __DIR__ . '/includes/driver_modal.php'; ?>
<?php $pageScript = 'PAGE="drivers";'; require __DIR__ . '/includes/footer.php'; ?>
