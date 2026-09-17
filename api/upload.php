<?php
declare(strict_types=1);
require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../lib/Importer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['files'])) {
    json_out(['status' => 'error', 'message' => 'No files uploaded.'], 400);
}

$results = [];
$files   = $_FILES['files'];
$count   = is_array($files['name']) ? count($files['name']) : 0;

for ($i = 0; $i < $count; $i++) {
    $name = $files['name'][$i];
    if ($files['error'][$i] !== UPLOAD_ERR_OK) {
        $results[] = ['file' => $name, 'status' => 'error', 'message' => 'Upload failed.'];
        continue;
    }
    if (!preg_match('/\.xlsx$/i', $name)) {
        $results[] = ['file' => $name, 'status' => 'error', 'message' => 'Only .xlsx files are supported.'];
        continue;
    }
    try {
        $res = Importer::import($files['tmp_name'][$i], $name);
    } catch (Throwable $ex) {
        $res = ['status' => 'error', 'message' => $ex->getMessage()];
    }
    $res['file'] = $name;
    $results[] = $res;
}

json_out(['status' => 'ok', 'results' => $results]);
