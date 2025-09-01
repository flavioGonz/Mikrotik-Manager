<?php
/**
 * Logger simple para MikroTik Manager
 * - Guarda en api/logs/routers.log y api/logs/audit.log
 */

function log_message($file, $msg) {
    $dir = __DIR__ . '/logs';
    if (!is_dir($dir)) @mkdir($dir, 0777, true);

    $path = "$dir/$file";
    $ts   = date('Y-m-d H:i:s');
    $line = "[$ts] $msg\n";
    @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
}

function log_router($msg) {
    log_message('routers.log', $msg);
}

function log_audit($user, $action, $details = '') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
    $msg = "$user@$ip → $action " . ($details ? "| $details" : "");
    log_message('audit.log', $msg);
}
