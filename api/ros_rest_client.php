<?php
/**
 * Pequeño cliente para la REST de RouterOS v7.
 *
 * - Autenticación: Basic (usuario/clave de RouterOS)
 * - Por defecto usa HTTP en puerto 8333 (tu configuración actual)
 * - Si querés HTTPS: pasá $ssl=true y el puerto (típicamente 443)
 *
 * Uso:
 *   $data = ros_rest_call($host, $user, $pass, '/system/resource', 'GET', null, 8333, false, 8);
 */

function ros_rest_call(
    string $host,
    string $user,
    string $pass,
    string $path,
    string $method = 'GET',
    array  $body = null,
    int    $port = 8333,   // <--- default HTTP 8333
    bool   $ssl  = false,  // <--- por defecto HTTP
    int    $timeout = 8
): array {
    $path   = '/' . ltrim($path, '/');
    $scheme = $ssl ? 'https' : 'http';
    $url    = sprintf('%s://%s:%d/rest%s', $scheme, $host, $port, $path);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => $user . ':' . $pass,
        CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    ]);

    // HTTPS: por ahora no forzamos verificación (si necesitás validar, ajustamos acá)
    if ($ssl) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // cambiar a true si instalás la CA
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);     // cambiar a 2 si verificás host
    }

    if ($body !== null) {
        $jsonBody = json_encode($body, JSON_UNESCAPED_SLASHES);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
    }

    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        throw new RuntimeException("HTTP error: $err");
    }
    if ($code >= 400) {
        // Intentamos extraer mensaje de error si viene en JSON
        $msg = $resp;
        $j = json_decode($resp, true);
        if (is_array($j)) {
            if (isset($j['detail'])) $msg = $j['detail'];
            elseif (isset($j['message'])) $msg = $j['message'];
        }
        throw new RuntimeException("HTTP $code: $msg");
    }

    // La REST devuelve usualmente JSON (array u objeto). Si viene vacío, devolvemos []
    $json = json_decode($resp, true);
    return $json ?? [];
}
