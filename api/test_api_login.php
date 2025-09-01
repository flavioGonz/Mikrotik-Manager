<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';

// Log
$logDir = __DIR__ . '/logs';
$logFile = $logDir . '/test-api-' . date('Y-m-d') . '.log';
if (!is_dir($logDir)) @mkdir($logDir, 0777, true);
function logLine($m){global $logFile; @file_put_contents($logFile,'['.date('Y-m-d H:i:s')."] $m\n",FILE_APPEND);}

// Cargar PHAR PEAR2 en rutas conocidas
$pharCandidates = [
    __DIR__ . '/lib/PEAR2_Net_RouterOS.phar',
    __DIR__ . '/lib/PEAR2_Net_RouterOS-1.0.0b5.phar',
    __DIR__ . '/../lib/PEAR2_Net_RouterOS.phar',
    __DIR__ . '/../lib/PEAR2_Net_RouterOS-1.0.0b5.phar',
];
$pharUsed = null;
foreach ($pharCandidates as $cand) if (file_exists($cand)) { $pharUsed = $cand; break; }
if ($pharUsed) { require_once $pharUsed; logLine("PHAR cargado: $pharUsed"); }
else { logLine("AVISO: PHAR no encontrado. Solo se podrá hacer ping."); }

use PEAR2\Net\RouterOS\Client;
use PEAR2\Net\RouterOS\Request;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'id requerido ?id=8']); exit; }

// Traer credenciales
$stmt = $conn->prepare("SELECT ip_address, TRIM(api_user) api_user, TRIM(api_password) api_password,
                               COALESCE(api_port,0) api_port, COALESCE(api_ssl,0) api_ssl
                        FROM routers WHERE id=?");
$stmt->bind_param("i",$id); $stmt->execute();
$r = $stmt->get_result()->fetch_assoc(); $stmt->close();

if (!$r) { echo json_encode(['ok'=>false,'msg'=>'router no encontrado']); exit; }

$ip   = $r['ip_address'];
$user = $r['api_user'] ?? '';
$pass = $r['api_password'] ?? '';
$port = (int)$r['api_port'];
$ssl  = ((int)$r['api_ssl']) === 1;

// Si no hay puerto definido, probamos ambos automáticamente
$combos = [];
if ($port > 0) {
    $combos[] = ['port'=>$port, 'ssl'=>$ssl];
} else {
    $combos[] = ['port'=>8728, 'ssl'=>false];
    $combos[] = ['port'=>8729, 'ssl'=>true];
}

$result = ['ip'=>$ip,'user'=>$user,'tested'=>[]];
foreach ($combos as $c) {
    $p = $c['port']; $s = $c['ssl'];
    $label = "port=$p ssl=" . ($s?'1':'0');
    $entry = ['combo'=>$label,'api'=>false,'reason'=>null,'version'=>null,'uptime'=>null,'cpu'=>null,'model'=>null];

    try {
        if ($pharUsed && class_exists(Client::class)) {
            $client = new Client($ip, $user, $pass, $p, null, $s);
            $resp = $client->sendSync(new Request('/system/resource/print'));
            $rows = iterator_to_array($resp);
            if (!empty($rows[0])) {
                $row = $rows[0];
                $entry['api']     = true;
                $entry['version'] = $row->getProperty('version');
                $entry['uptime']  = $row->getProperty('uptime');
                $entry['cpu']     = $row->getProperty('cpu-load');
                $entry['model']   = $row->getProperty('board-name');
                logLine("API OK id=$id ip=$ip $label user='$user'");
            }
        } else {
            $entry['reason'] = 'PHAR ausente';
        }
    } catch (\Throwable $e) {
        $entry['reason'] = $e->getMessage();
        logLine("API FAIL id=$id ip=$ip $label : ".$e->getMessage());
    }

    $result['tested'][] = $entry;
}

// Fallback ping simple
if (!$result['tested'] || !array_filter($result['tested'], fn($e)=>$e['api'])) {
    if (PHP_OS_FAMILY==='Windows') $cmd="ping -n 1 ".escapeshellarg($ip);
    else                            $cmd="ping -c 1 ".escapeshellarg($ip);
    @exec($cmd,$out,$rc);
    $result['ping'] = ($rc===0)?'OK':'FAIL';
    logLine("PING id=$id ip=$ip -> ".$result['ping']);
}

echo json_encode(['ok'=>true,'data'=>$result], JSON_PRETTY_PRINT);
