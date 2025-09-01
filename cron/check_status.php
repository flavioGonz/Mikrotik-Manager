<?php
/**
 * Cron de verificación con REST API RouterOS.
 *  - Actualiza la tabla routers
 *  - Genera caché en api/cache/router_ID.json para tooltips
 *  - Loggea en cron.log, routers.log y audit.log
 *
 * Ejecutar con el Programador de Tareas (cada 5 min):
 *   C:\xampp\php\php.exe -f C:\xampp\htdocs\mk\cron\check_status.php
 */

date_default_timezone_set('America/Montevideo');

require_once __DIR__ . '/../api/db_connect.php';
require_once __DIR__ . '/../api/ros_rest_client.php';
require_once __DIR__ . '/../api/logger.php'; // ✅ NUEVO

// --- Logging local del día (como tenías antes) ---
$logDir  = __DIR__ . '/logs';
if (!is_dir($logDir)) @mkdir($logDir, 0777, true);
$logFile = $logDir . '/cron-rest-' . date('Y-m-d') . '.log';
$log = function($m) use ($logFile) { @file_put_contents($logFile, '['.date('H:i:s')."] $m\n", FILE_APPEND); };

$cacheDir = __DIR__ . '/../api/cache';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0777, true);

$log('=== Inicio verificación REST ===');
log_cron("=== Inicio verificación REST ==="); // ✅ NUEVO

// Traer routers
$q = "SELECT id, ip_address, TRIM(COALESCE(api_user,'')) api_user,
             TRIM(COALESCE(api_password,'')) api_password,
             COALESCE(api_port,0) api_port, COALESCE(api_ssl,0) api_ssl
      FROM routers";
$rs = $conn->query($q);
if (!$rs || !$rs->num_rows) {
    $log('No hay routers.');
    log_cron("No hay routers.");
    $conn->close(); exit;
}

$total=0; $ok=0; $fail=0;

while ($r = $rs->fetch_assoc()) {
    $total++;
    $id   = (int)$r['id'];
    $host = $r['ip_address'];
    $user = $r['api_user'];
    $pass = $r['api_password'];
    $ssl  = ((int)$r['api_ssl']) === 1;
    $port = (int)$r['api_port'];
    if ($port <= 0) $port = $ssl ? 443 : 8333;

    $status='FAIL'; $model=$version=$uptime=null; $cpu_load=null; $events=[]; $arp_count=null;

    try {
        // /system/resource
        $res = ros_rest_call($host,$user,$pass,'/system/resource','GET',null,$port,$ssl,8);
        $row = is_array($res) ? ($res[0] ?? $res) : [];
        $version  = $row['version']    ?? null;
        $uptime   = $row['uptime']     ?? null;
        $cpu_load = isset($row['cpu-load']) ? (int)$row['cpu-load'] : null;
        $model    = $row['board-name'] ?? null;
        $status   = 'OK';

        // /system/routerboard
        try {
            $rb  = ros_rest_call($host,$user,$pass,'/system/routerboard','GET',null,$port,$ssl,8);
            $rb0 = is_array($rb) ? ($rb[0] ?? $rb) : [];
            if (!empty($rb0['model'])) $model = $rb0['model'];
        } catch (\Throwable $e) {}

        // /log últimos 5 (solo error/critical/warning)
        try {
            $logData = ros_rest_call($host,$user,$pass,'/log','GET',null,$port,$ssl,8);
            if (is_array($logData)) {
                $filtered = array_filter($logData, function($ev){
                    $topics = strtolower($ev['topics'] ?? '');
                    return str_contains($topics,'error') || str_contains($topics,'critical') || str_contains($topics,'warning');
                });
                $count = count($filtered);
                $slice = array_slice($filtered, max(0, $count - 5));
                foreach ($slice as $ev) {
                    $events[] = [
                        'time'    => $ev['time']    ?? '',
                        'topics'  => $ev['topics']  ?? '',
                        'message' => $ev['message'] ?? ''
                    ];
                }
            }
        } catch (\Throwable $e) {}

        // /ip/arp cantidad
        try {
            $arp = ros_rest_call($host,$user,$pass,'/ip/arp','GET',null,$port,$ssl,8);
            if (is_array($arp)) $arp_count = count($arp);
        } catch (\Throwable $e) {}

    } catch (\Throwable $e) {
        // fallback ping
        $cmd = (PHP_OS_FAMILY==='Windows') ? "ping -n 1 ".escapeshellarg($host)
                                           : "ping -c 1 ".escapeshellarg($host);
        @exec($cmd,$out,$rc);
        $status = ($rc===0)?'OK':'FAIL';
        $msg = "REST FAIL id=$id host=$host : ".$e->getMessage()." => ping=$status";
        $log($msg);
        log_router($msg); // ✅ NUEVO
    }

    // Update DB
    $stmt = $conn->prepare("UPDATE routers
        SET status=?, last_checked=NOW(), model=?, version=?, uptime=?, cpu_load=?
        WHERE id=?");
    $stmt->bind_param("ssssii",$status,$model,$version,$uptime,$cpu_load,$id);
    $stmt->execute(); $stmt->close();

    if ($status==='OK') $ok++; else $fail++;

    $line = "id=$id $host:$port ".($ssl?'https':'http')." -> $status"
        .($model?" | $model":"").($version?" | v$version":"")
        .($uptime?" | $uptime":"").(isset($cpu_load)?" | cpu=$cpu_load%":"")
        .(isset($arp_count)?" | arp=$arp_count":"");

    $log($line);
    log_router($line); // ✅ NUEVO

    // Escribir caché JSON para tooltips
    $out = [
        'model'     => $model,
        'uptime'    => $uptime,
        'cpu'       => $cpu_load,
        'arp_count' => $arp_count,
        'events'    => $events
    ];
    file_put_contents($cacheDir."/router_$id.json", json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    usleep(200000); // 0.2s
}

$summary = "=== Fin: total=$total OK=$ok FAIL=$fail ===";
$log($summary);
log_cron($summary);      // ✅ NUEVO
log_audit('system','CRON_CHECK',$summary); // ✅ NUEVO

$conn->close();
