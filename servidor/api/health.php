<?php

declare(strict_types=1);

require_once __DIR__ . "/../configuracao/bootstrap.php";

// Liveness check: load balancers must only use PHP and the basic database
// connection here. Operational readiness belongs to /api/prontidao.php.
try {
    $pdo = obter_conexao_banco();
    $pdo->query("SELECT 1");
    responder_json([
        "status" => "ok",
        "php" => true,
        "mysql" => true,
        "checked_at" => date("c"),
    ]);
} catch (Throwable $error) {
    error_log("Healthcheck banco-de-dados failure: " . $error->getMessage());
    responder_json(
        ["status" => "degraded", "php" => true, "mysql" => false],
        503,
    );
}
