<?php

declare(strict_types=1);

require_once __DIR__ . "/../configuracao/bootstrap.php";

$user = require_session_user();
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    json_response(["error" => "Método não permitido."], 405);
}
if ($user["company_id"] === null) {
    json_response(["error" => "Usuário sem empresa vinculada."], 403);
}

$pdo = db();
$scope = "q.company_id = :company_id";
$params = ["company_id" => $user["company_id"]];
$counts = $pdo->prepare(
    "SELECT q.status, COUNT(*) AS total FROM fila_sincronizacao q WHERE {$scope} GROUP BY q.status",
);
$counts->execute($params);
$summary = ["PENDENTE" => 0, "PROCESSANDO" => 0, "ENVIADO" => 0, "ERRO" => 0];
foreach ($counts->fetchAll() as $row) {
    $summary[$row["status"]] = (int) $row["total"];
}

$recent = $pdo->prepare(
    "SELECT q.id, q.aggregate_type, q.aggregate_id, q.status, q.attempts, q.last_error, q.available_at, q.created_at FROM fila_sincronizacao q WHERE {$scope} AND q.status IN ('PENDENTE', 'ERRO') ORDER BY q.id DESC LIMIT 20",
);
$recent->execute($params);
$remoteUrl = trim((string) (getenv("SYNC_REMOTE_BATCH_URL") ?: ""));
if ($remoteUrl === "") {
    $remoteUrl = trim((string) (getenv("SYNC_REMOTE_URL") ?: ""));
}
if ($remoteUrl === "") {
    $settings = $pdo->prepare(
        "SELECT sync_remote_url FROM configuracoes_empresa WHERE company_id = :company_id LIMIT 1",
    );
    $settings->execute(["company_id" => $user["company_id"]]);
    $remoteUrl = trim((string) ($settings->fetchColumn() ?: ""));
}
$remoteConfigured = $remoteUrl !== "";

json_response([
    "data" => [
        "summary" => $summary,
        "recent" => $recent->fetchAll(),
        "remote_configured" => $remoteConfigured,
        "checked_at" => date("c"),
    ],
]);
