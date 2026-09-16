<?php

declare(strict_types=1);

require_once __DIR__ . "/../configuracao/bootstrap.php";

// Readiness check for deploys and support. It stays separate from health.php
// so stale devices or a backed-up queue do not remove PHP from the load balancer.
$mysqlConnected = false;
try {
    $pdo = obter_conexao_banco();
    $pdo->query("SELECT 1");
    $mysqlConnected = true;
    $queue = $pdo->query(
        "SELECT SUM(status = 'PENDENTE') AS pending,
                SUM(status = 'PROCESSANDO') AS processing,
                SUM(status = 'ERRO') AS errors,
                MIN(CASE WHEN status IN ('PENDENTE', 'PROCESSANDO', 'ERRO') THEN created_at END) AS oldest_at
         FROM fila_sincronizacao",
    )->fetch() ?: [];
    $queueDepth = (int) ($queue["pending"] ?? 0) + (int) ($queue["processing"] ?? 0) + (int) ($queue["errors"] ?? 0);
    $oldestAge = $queue["oldest_at"]
        ? max(0, (int) $pdo->query("SELECT TIMESTAMPDIFF(SECOND, " . $pdo->quote($queue["oldest_at"]) . ", NOW())")->fetchColumn())
        : null;
    $staleSeconds = max(5, (int) (getenv("HEALTH_DEVICE_STALE_SECONDS") ?: 30));
    $staleDevices = (int) $pdo->query(
        "SELECT COUNT(*) FROM dispositivos WHERE active = 1
         AND (last_seen_at IS NULL OR last_seen_at < DATE_SUB(NOW(), INTERVAL {$staleSeconds} SECOND))",
    )->fetchColumn();
    $stuckCommands = (int) $pdo->query(
        "SELECT COUNT(*) FROM solicitacoes_comandos_clp
         WHERE status = 'PROCESSANDO'
         AND (claimed_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
              OR (expires_at IS NOT NULL AND expires_at < NOW()))",
    )->fetchColumn();
    $storagePath = dirname(__DIR__, 2) . "/armazenamento";
    $diskFree = is_dir($storagePath) ? disk_free_space($storagePath) : false;
    $diskTotal = is_dir($storagePath) ? disk_total_space($storagePath) : false;
    $diskFreePercent = is_numeric($diskFree) && is_numeric($diskTotal) && (float) $diskTotal > 0
        ? round(((float) $diskFree / (float) $diskTotal) * 100, 2)
        : null;
    $minDiskPercent = max(1, min(50, (int) (getenv("HEALTH_MIN_DISK_FREE_PERCENT") ?: 5)));
    $maxQueueAgeSeconds = max(60, (int) (getenv("HEALTH_MAX_QUEUE_AGE_SECONDS") ?: 604800));
    $schemaMigrations = (int) $pdo->query("SELECT COUNT(*) FROM schema_migrations")->fetchColumn();
    $checks = [
        "queue" => [
            "depth" => $queueDepth,
            "pending" => (int) ($queue["pending"] ?? 0),
            "processing" => (int) ($queue["processing"] ?? 0),
            "errors" => (int) ($queue["errors"] ?? 0),
            "oldest_age_seconds" => $oldestAge,
            "maximum_age_seconds" => $maxQueueAgeSeconds,
        ],
        "heartbeats" => [
            "active_devices" => (int) $pdo->query("SELECT COUNT(*) FROM dispositivos WHERE active = 1")->fetchColumn(),
            "stale_devices" => $staleDevices,
            "stale_after_seconds" => $staleSeconds,
        ],
        "commands" => ["stuck_processing" => $stuckCommands],
        "disk" => [
            "free_bytes" => is_numeric($diskFree) ? (int) $diskFree : null,
            "free_percent" => $diskFreePercent,
            "minimum_free_percent" => $minDiskPercent,
        ],
        "schema_migrations" => $schemaMigrations,
        "version" => trim((string) (getenv("TRACE_VERSION") ?: "development")),
    ];
    $diskCritical = $diskFreePercent !== null && $diskFreePercent < $minDiskPercent;
    $queueStale = $oldestAge !== null && $oldestAge > $maxQueueAgeSeconds;
    $degraded = $schemaMigrations < 1 || (int) ($queue["errors"] ?? 0) > 0 || $queueStale || $staleDevices > 0 || $stuckCommands > 0;
    $status = $diskCritical ? "critical" : ($degraded ? "degraded" : "ready");
    responder_json([
        "status" => $status,
        "php" => true,
        "mysql" => true,
        "checks" => $checks,
        "checked_at" => date("c"),
    ], $status === "ready" ? 200 : 503);
} catch (Throwable $error) {
    error_log("Readiness check failure: " . $error->getMessage());
    responder_json([
        "status" => "not_ready",
        "php" => true,
        "mysql" => $mysqlConnected,
        "error_code" => $mysqlConnected ? "SCHEMA_INCOMPLETO" : "BANCO_INDISPONIVEL",
        "message" => $mysqlConnected
            ? "O banco responde, mas o schema operacional não está pronto. Execute as migrations."
            : "Não foi possível conectar ao banco de dados.",
    ], 503);
}
