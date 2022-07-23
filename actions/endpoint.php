<?php

use BB\Clockwork\Profiler;

error_reporting(E_ALL);

require_once '../../../config/config.inc.php';
require_once '../../../init.php';
require_once __DIR__ . '/../vendor/autoload.php';

$clockwork = Clockwork\Support\Vanilla\Clockwork::init([
    'storage_files_path' => __DIR__ . '/../storage/clockwork',
    'api' => __PS_BASE_URI__ . 'modules/clockwork/actions/endpoint.php?request=',
    'web' => [
        'enable' => __PS_BASE_URI__ . 'module/clockwork/web',
        'path' => __DIR__ . '/../views/web/public',
        'uri' =>  __PS_BASE_URI__ . 'modules/clockwork/views/web/public',
    ],
]);

header('Content-type: application/json;charset=utf-8');
echo json_encode($clockwork->getMetadata());

// disable the profiler.
Profiler::getInstance()->disable();
