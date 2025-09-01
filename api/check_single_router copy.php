<?php
header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/ros_rest_client.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = isset($data['id']) ? (int)$data['id'] : 0;
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID inválido.']);
        exit;
    }

    $sql = "SELECT ip_address, TRIM(COALESCE(api_user,'')) AS api_user,
                    TRIM(COALESCE(api_password,'')) AS api_password,
                    COALESCE(api_port,0) AS api_port,
                    COALESCE(api_ssl,0)  AS api_ssl
            FROM routers WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$r) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Router no encontrado.']);
        exit;
    }

    $host = $r['ip_address'];
    $user = $r['api_user'];
    $pass = $r['api_password'];
    $ssl  = ((int)$r['api_ssl']) === 1;                 // 0=http, 1=https
    $port = (int)$r['api_port'];
    if ($port <= 0) $port = $ssl ? 443 : 8333;          // <--- default HTTP = 8333

    $status   = 'FAIL';
    $model    = null;
    $version  = null;
    $uptime   = null;
    $cpu_load = null;

    // 1) REST
    try {
        $res = ros_rest_call($host, $user, $pass, '/system/resource', 'GET', null, $port, $ssl, 8);
        $row = is_array($res) ? ($res[0] ?? $res) : [];

        $version  = $row['version']    ?? null;
        $uptime   = $row['uptime']     ?? null;
        $cpu_load = isset($row['cpu-load']) ? (int)$row['cpu-load'] : null;
        $model    = $row['board-name'] ?? null;
        $status   = 'OK';

        try {
            $rb  = ros_rest_call($host, $user, $pass, '/system/routerboard', 'GET', null, $port, $ssl, 8);
            $rb0 = is_array($rb) ? ($rb[0] ?? $rb) : [];
            if (!empty($rb0['model'])) $model = $rb0['model'];
        } catch (\Throwable $e) {}

    } catch (\Throwable $e) {
        // 2) Ping fallback
        $cmd = (PHP_OS_FAMILY === 'Windows') ? "ping -n 1 " . escapeshellarg($host) : "ping -c 1 " . escapeshellarg($host);
        @exec($cmd, $out, $rc);
        $status = ($rc === 0) ? 'OK' : 'FAIL';
    }

    // 3) Persistir
    $upd = $conn->prepare(
        "UPDATE routers
            SET status = ?,
                last_checked = NOW(),
                model = ?,
                version = ?,
                uptime = ?,
                cpu_load = ?
          WHERE id = ?"
    );
    $upd->bind_param("ssssii", $status, $model, $version, $uptime, $cpu_load, $id);
    $upd->execute();
    $upd->close();

    echo json_encode([
        'success'    => true,
        'new_status' => $status,
        'model'      => $model,
        'version'    => $version,
        'uptime'     => $uptime,
        'cpu_load'   => $cpu_load
    ]);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno: '.$e->getMessage()]);
} finally {
    if (isset($conn)) $conn->close();
}
