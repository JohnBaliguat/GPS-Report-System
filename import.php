<?php
$page = 'import';
$title = 'Import Reports';
$subtitle = 'Upload Geotab .xlsx exports — type is auto-detected';
require __DIR__ . '/includes/header.php';
?>
<div class="panel">
    <h2><i class="bi bi-cloud-upload"></i> Upload Telematics Reports</h2>
    <div class="dropzone" id="dropzone">
        <i class="bi bi-filetype-xlsx"></i>
        <h3>Drop Geotab .xlsx files here</h3>
        <p>or click to browse · supports multiple files · restricted zones, speeding, idling, stops, geofence &amp; POI</p>
        <input type="file" id="fileInput" multiple accept=".xlsx" hidden>
    </div>
    <div id="uploadResults" class="mt-3"></div>
    <div class="mt-3 d-none" id="postImport">
        <a href="index.php" class="btn btn-primary btn-sm"><i class="bi bi-speedometer2"></i> Go to Dashboard</a>
        <a href="drivers.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-person-badge"></i> View Scorecards</a>
    </div>
</div>

<div class="panel">
    <h2><i class="bi bi-clock-history"></i> Imported Reports</h2>
    <table id="importTable" class="table" style="width:100%">
        <thead>
            <tr>
                <th>File</th>
                <th>Type</th>
                <th>Category</th>
                <th>Period</th>
                <th class="text-end">Events</th>
                <th>Imported</th>
                <th></th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>
<?php $pageScript = 'PAGE="import";'; require __DIR__ . '/includes/footer.php'; ?>
