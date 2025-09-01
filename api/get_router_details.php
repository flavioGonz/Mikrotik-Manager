<?php
/**
 * Devuelve detalles live con caché en disco:
 *  - model, uptime, cpu, arp_count
 *  - últimos eventos (solo error/critical/warning)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/ros_rest_client.php';

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Falta id']);
        exit;
    }

    // Config de caché
    $cacheDir = __DIR__ . '/cache';
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0777, true);
    $cacheFile = $cacheDir . "/router_$id.json";
    $cacheTTL  = 300; // 5 min

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTTL)) {
        $data = json_decode(file_get_contents($cacheFile), true);
        if (is_array($data)) {
            echo json_encode(['success' => true, 'data' => $data, 'cached' => true]);
            exit;
        }
    }

    // === DB: credenciales ===
    $stmt = $conn->prepare(
        "SELECT ip_address,
                TRIM(COALESCE(api_user,''))     AS api_user,
                TRIM(COALESCE(api_password,'')) AS api_password,
                COALESCE(api_port,0)            AS api_port,
                COALESCE(api_ssl,0)             AS api_ssl
         FROM routers WHERE id=?"
    );
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$r) {
        echo json_encode(['success'=>false,'message'=>'Router no encontrado']);
        exit;
    }

    $host = $r['ip_address'];
    $user = $r['api_user'];
    $pass = $r['api_password'];
    $ssl  = ((int)$r['api_ssl']) === 1;
    $port = (int)$r['api_port'];
    if ($port <= 0) $port = $ssl ? 443 : 8333;

    $model = null; $uptime = null; $cpu = null; $events = []; $arp_count = null;

    try {
        // /system/resource
        $res = ros_rest_call($host, $user, $pass, '/system/resource', 'GET', null, $port, $ssl, 8);
        $row = is_array($res) ? ($res[0] ?? $res) : [];
        $uptime = $row['uptime'] ?? null;
        $cpu    = isset($row['cpu-load']) ? (int)$row['cpu-load'] : null;
        $model  = $row['board-name'] ?? null;
    } catch (\Throwable $e) {
        echo json_encode(['success'=>false,'message'=>'Error consultando /system/resource: '.$e->getMessage()]);
        exit;
    }

    // /system/routerboard (modelo más preciso)
    try {
        $rb  = ros_rest_call($host, $user, $pass, '/system/routerboard', 'GET', null, $port, $ssl, 8);
        $rb0 = is_array($rb) ? ($rb[0] ?? $rb) : [];
        if (!empty($rb0['model'])) $model = $rb0['model'];
    } catch (\Throwable $e) {
        // No crítico
    }

    // /log últimos 5 solo error/critical/warning
    try {
        $log = ros_rest_call($host, $user, $pass, '/log', 'GET', null, $port, $ssl, 8);
        if (is_array($log)) {
            $filtered = array_filter($log, function($ev){
                $topics = strtolower($ev['topics'] ?? '');
                return str_contains($topics, 'error') || str_contains($topics,'critical') || str_contains($topics,'warning');
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
    } catch (\Throwable $e) {
        // sin logs
    }

    // /ip/arp cantidad
    try {
        $arp = ros_rest_call($host, $user, $pass, '/ip/arp', 'GET', null, $port, $ssl, 8);
        if (is_array($arp)) $arp_count = count($arp);
    } catch (\Throwable $e) {
        // sin arp
    }

    $out = [
        'model'     => $model,
        'uptime'    => $uptime,
        'cpu'       => $cpu,
        'arp_count' => $arp_count,
        'events'    => $events
    ];

    file_put_contents($cacheFile, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    echo json_encode(['success' => true, 'data' => $out, 'cached' => false]);

} catch (\Throwable $e) {
    echo json_encode(['success'=>false,'message'=>'PHP Exception: '.$e->getMessage()]);
} finally {
    if (isset($conn)) $conn->close();
}
