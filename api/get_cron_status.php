<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';

// --- Config ---
$MAX_DELAY_MINUTES = 10;   // si el último last_checked es <= a esto → "active"

// --- Logging ---
$logDir  = __DIR__ . '/logs';
$logFile = $logDir . '/cron-status-' . date('Y-m-d') . '.log';
if (!is_dir($logDir)) @mkdir($logDir, 0777, true);
function logLine($m){global $logFile; @file_put_contents($logFile,'['.date('Y-m-d H:i:s')."] $m\n",FILE_APPEND);}

logLine('Consulta de estado de cron recibida.');

// --- Consultar último last_checked ---
$sql = "SELECT MAX(last_checked) AS last_run FROM routers";
$res = $conn->query($sql);

$response = ["status" => "fail", "message" => "No se pudo verificar."];

if ($res && ($row = $res->fetch_assoc())) {
    $lastRun = $row['last_run'];
    logLine('Último last_checked: ' . ($lastRun ?: 'NULL'));

    if ($lastRun) {
        $lastTs = strtotime($lastRun);
        $diffMin = (time() - $lastTs) / 60;

        if ($diffMin <= $MAX_DELAY_MINUTES) {
            $response['status']  = 'active';
            $response['message'] = "Cron activo (última ejecución hace " . round($diffMin, 1) . " min).";
        } else {
            $response['status']  = 'inactive';
            $response['message'] = "Sin actividad reciente (última ejecución: $lastRun).";
        }

        logLine("Evaluado: diff={$diffMin}min → status={$response['status']}");
    } else {
        $response['status']  = 'inactive';
        $response['message'] = "Nunca se ejecutó ninguna verificación (sin last_checked).";
        logLine('No hay last_checked en la tabla.');
    }
} else {
    $response['status']  = 'fail';
    $response['message'] = 'Error de base de datos al consultar last_checked.';
    logLine('ERROR DB al consultar MAX(last_checked).');
}

$conn->close();
echo json_encode($response);
