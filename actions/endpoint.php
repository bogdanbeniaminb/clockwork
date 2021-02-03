<?php

error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';

$clockwork = Clockwork\Support\Vanilla\Clockwork::init([
    'storage_files_path' => __DIR__ . '/../storage/clockwork',
]);

error_log(json_encode($_GET), 3, __DIR__ . '/../logs.log');
header('Content-type: application/json;charset=utf-8');
echo json_encode($clockwork->getMetadata());
