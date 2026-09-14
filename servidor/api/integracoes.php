<?php

declare(strict_types=1);

require_once __DIR__ . "/../configuracao/bootstrap.php";

exigir_metodo_http(["GET", "POST", "DELETE"]);
$user = exigir_perfil(["ADMIN_DALLOGIX", "ADMIN_EMPRESA"]);
$companyId = $user["company_id"];
if ($companyId === null) {
    responder_json(["error" => "Selecione uma empresa para administrar integrações."], 422);
}

$pdo = obter_conexao_banco();
if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $statement = $pdo->prepare(
        "SELECT id, label, key_prefix, scopes, active, expires_at, last_used_at, created_at, revoked_at
         FROM chaves_integracao WHERE company_id = :company_id ORDER BY id DESC",
    );
    $statement->execute(["company_id" => $companyId]);
    $rows = $statement->fetchAll();
    foreach ($rows as &$row) {
        $row["id"] = (int) $row["id"];
        $row["active"] = (bool) $row["active"];
        $row["scopes"] = json_decode((string) $row["scopes"], true) ?: [];
    }
    unset($row);
    responder_json(["data" => $rows]);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    exigir_csrf();
    $payload = ler_json_da_requisicao();
    $label = trim((string) ($payload["label"] ?? ""));
    $allowedScopes = ["produtos:read", "romaneios:read", "romaneios:write"];
    $scopes = array_values(array_unique(array_filter(
        $payload["scopes"] ?? [],
        static fn(mixed $scope): bool => is_string($scope) && in_array($scope, $allowedScopes, true),
    )));
    if ($label === "" || mb_strlen($label) > 120 || $scopes === []) {
        responder_json(["error" => "Informe um nome e ao menos uma permissão válida."], 422);
    }

    $expiresAt = trim((string) ($payload["expires_at"] ?? ""));
    if ($expiresAt !== "") {
        $date = DateTime::createFromFormat("Y-m-d", $expiresAt);
        if (!$date || $date->format("Y-m-d") !== $expiresAt || $expiresAt <= date("Y-m-d")) {
            responder_json(["error" => "A validade deve ser uma data futura no formato AAAA-MM-DD."], 422);
        }
        $expiresAt .= " 23:59:59";
    } else {
        $expiresAt = null;
    }

    $token = "trc_" . rtrim(strtr(base64_encode(random_bytes(32)), "+/", "-_"), "=");
    $statement = $pdo->prepare(
        "INSERT INTO chaves_integracao (company_id, label, key_prefix, secret_hash, scopes, expires_at)
         VALUES (:company_id, :label, :prefix, :hash, :scopes, :expires_at)",
    );
    $statement->execute([
        "company_id" => $companyId,
        "label" => $label,
        "prefix" => substr($token, 0, 16),
        "hash" => hash("sha256", $token),
        "scopes" => json_encode($scopes, JSON_THROW_ON_ERROR),
        "expires_at" => $expiresAt,
    ]);
    $keyId = (int) $pdo->lastInsertId();
    registrar_evento_operacional($pdo, $user, "INTEGRACAO_CRIADA", "chave_integracao", $keyId, ["label" => $label, "scopes" => $scopes]);
    responder_json(["data" => ["id" => $keyId, "label" => $label, "token" => $token, "scopes" => $scopes]], 201);
}

exigir_csrf();
$id = filter_var($_GET["id"] ?? null, FILTER_VALIDATE_INT);
if (!$id) {
    responder_json(["error" => "Integração inválida."], 422);
}
$statement = $pdo->prepare(
    "UPDATE chaves_integracao SET active = 0, revoked_at = NOW()
     WHERE id = :id AND company_id = :company_id AND active = 1",
);
$statement->execute(["id" => $id, "company_id" => $companyId]);
if ($statement->rowCount() !== 1) {
    responder_json(["error" => "Integração não encontrada ou já revogada."], 404);
}
registrar_evento_operacional($pdo, $user, "INTEGRACAO_REVOGADA", "chave_integracao", $id);
responder_json(["data" => ["id" => $id, "active" => false]]);
