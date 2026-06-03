<?php
require_once __DIR__ . '/../utils/response.php';

sendResponse(200, 'API MathWarriors berjalan dengan baik', [
    'php_version' => phpversion(),
    'server_time' => date('Y-m-d H:i:s'),
]);
