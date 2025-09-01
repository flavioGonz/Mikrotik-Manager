<?php
header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/ros_rest_client.php';

// --- Logging opcional ---
$logDir  = __DIR__ . '/logs';
if (!is_dir($logDir)) @mkdir($logDir, 0777, true);
$logFile = $logDir . '/trigger-rest-' . date('Y-m-d') . '.log';
$log = function($m) use ($logFile) { @file_put_contents($logFile, '['.date('H:i:s')."] $m\n", FILE_APPEND); };

$in = json_decode(file_get_contents('php://input'), true);
if (($in['action'] ?? '') !== 'FORCE_CHECK_ALL') {
    echo json_encode(['success'=>false,'message'=>'Acción inválida']); exit;
}

$log('=== Verificación forzada (REST) ===');

$q = "SELECT id, ip_address, TRIM(COALESCE(api_user,'')) api_user,
             TRIM(COALESCE(api_password,'')) api_password,
             COALESCE(api_port,0) api_port, COALESCE(api_ssl,0) api_ssl
      FROM routers";
$rs = $conn->query($q);
if (!$rs || !$rs->num_rows) { echo json_encode(['success'=>false,'message'=>'No hay routers']); exit; }

$updated=0;
while ($r = $rs->fetch_assoc()) {
    $id   = (int)$r['id'];
    $host = $r['ip_address'];
    $user = $r['api_user']; $pass = $r['api_password'];
    $ssl  = ((int)$r['api_ssl']) === 1;
    $port = (int)$r['api_port'];
    if ($port <= 0) $port = $ssl ? 443 : 8333; // <-- default HTTP=8333

    $status='FAIL'; $model=$version=$uptime=null; $cpu_load=null;

    try {
        $res = ros_rest_call($host,$user,$pass,'/system/resource','GET',null,$port,$ssl,8);
        $row = is_array($res) ? ($res[0] ?? $res) : [];
        $version  = $row['version']    ?? null;
        $uptime   = $row['uptime']     ?? null;
        $cpu_load = isset($row['cpu-load']) ? (int)$row['cpu-load'] : null;
        $model    = $row['board-name'] ?? null;
        $status   = 'OK';

        try {
            $rb  = ros_rest_call($host,$user,$pass,'/system/routerboard','GET',null,$port,$ssl,8);
            $rb0 = is_array($rb) ? ($rb[0] ?? $rb) : [];
            if (!empty($rb0['model'])) $model = $rb0['model'];
        } catch (\Throwable $e) { /* no crítico */ }

    } catch (\Throwable $e) {
        $cmd = (PHP_OS_FAMILY==='Windows') ? "ping -n 1 ".escapeshellarg($host)
                                           : "ping -c 1 ".escapeshellarg($host);
        @exec($cmd,$out,$rc);
        $status = ($rc===0)?'OK':'FAIL';
        $log("REST FAIL id=$id host=$host : ".$e->getMessage()." => ping $status");
    }

    $stmt = $conn->prepare("UPDATE routers
        SET status=?, last_checked=NOW(), model=?, version=?, uptime=?, cpu_load=?
        WHERE id=?");
    $stmt->bind_param("ssssii",$status,$model,$version,$uptime,$cpu_load,$id);
    $stmt->execute(); $stmt->close();

    $updated++;
    $log("id=$id $host:$port ".($ssl?'https':'http')." -> $status");
    usleep(200000);
}

$conn->close();
echo json_encode(['success'=>true,'message'=>"Verificación forzada: $updated routers"]);
