<?php
header('Content-Type: text/plain; charset=utf-8');

$type = $_GET['type'] ?? 'cron';

if ($type === 'cron') {
    $logDir = __DIR__ . '/../cron/logs';
    $files = glob("$logDir/cron-rest-*.log");
    rsort($files);
    $file = $files[0] ?? null;
    if ($file && file_exists($file)) {
        readfile($file);
    } else {
        echo "No hay logs del cron.";
    }
}
elseif ($type === 'routers') {
    $logFile = __DIR__ . '/../logs/routers-actions.log';
    if (file_exists($logFile)) {
        readfile($logFile);
    } else {
        echo "No hay logs de acciones de routers.";
    }
}
else {
    echo "Tipo de log no válido.";
}
