<?php
/**
 * logger.php
 * Sistema unificado de logs
 *  - cron.log    → Ejecuciones del cron
 *  - routers.log → Acciones de routers (check, delete, etc.)
 *  - audit.log   → Auditoría (acciones globales)
 */

date_default_timezone_set('America/Montevideo');

$LOG_DIR = __DIR__ . '/../logs';
if (!is_dir($LOG_DIR)) {
    @mkdir($LOG_DIR, 0777, true);
}

/**
 * Escribe una línea en un archivo de log.
 */
function write_log($file, $message) {
    global $LOG_DIR;
    $line = "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
    @file_put_contents($LOG_DIR . '/' . $file, $line, FILE_APPEND);
}

/**
 * Log específico para CRON
 */
function log_cron($message) {
    write_log('cron.log', $message);
}

/**
 * Log específico para Routers
 */
function log_router($message) {
    write_log('routers.log', $message);
}

/**
 * Log de auditoría
 */
function log_audit($user, $action, $message) {
    write_log('audit.log', strtoupper($user) . " [$action] " . $message);
}
