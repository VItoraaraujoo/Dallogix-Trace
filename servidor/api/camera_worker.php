<?php
declare(strict_types=1);

require_once __DIR__ . "/../configuracao/bootstrap.php";

exigir_metodo_http(["POST"]);
$device = require_device_token(["CAMERA"]);
$payload = request_json();
$action = strtoupper(trim((string) ($payload["action"] ?? "CLAIM")));
$pdo = db();

if ($action === "CLAIM") {
    $pdo->beginTransaction();
    try {
        $statement = $pdo->prepare(
            "SELECT r.id, r.sensor_event_id, r.carregamento_id, r.equipment_id, r.reason, c.company_id
             FROM solicitacoes_captura_camera r
             JOIN carregamentos c ON c.id = r.carregamento_id
             WHERE r.equipment_id = :equipment_id AND r.status = 'PENDENTE'
             ORDER BY r.requested_at, r.id LIMIT 1 FOR UPDATE SKIP LOCKED",
        );
        $statement->execute(["equipment_id" => $device["equipment_id"]]);
        $request = $statement->fetch();
        if (!$request) {
            $pdo->commit();
            http_response_code(204);
            exit();
        }
        $update = $pdo->prepare(
            "UPDATE solicitacoes_captura_camera SET status = 'CAPTURANDO', claimed_by_device_id = :device_id
             WHERE id = :id AND equipment_id = :equipment_id AND status = 'PENDENTE'",
        );
        $update->execute([
            "id" => $request["id"],
            "equipment_id" => $device["equipment_id"],
            "device_id" => $device["id"],
        ]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException("Pedido de captura já foi reservado.");
        }
        record_operational_event(
            $pdo,
            ["id" => null, "company_id" => (int) $request["company_id"]],
            "CAMERA_CAPTURA_INICIADA",
            "camera_request",
            (int) $request["id"],
            [
                "equipment_id" => (int) $request["equipment_id"],
                "device_id" => $device["id"],
                "reason" => $request["reason"],
            ],
        );
        $pdo->commit();
        unset($request["company_id"]);
        $request["claimed_by_device_id"] = $device["id"];
        json_response(["data" => $request]);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

if ($action !== "COMPLETE") {
    json_response(["error" => "Ação inválida."], 422);
}
$requestId = filter_var($payload["request_id"] ?? null, FILTER_VALIDATE_INT);
$imagePath = trim((string) ($payload["image_path"] ?? ""));
if (!$requestId || $imagePath === "") {
    json_response(["error" => "request_id e image_path são obrigatórios."], 422);
}
if (
    strlen($imagePath) > 500 ||
    str_contains($imagePath, "..") ||
    str_starts_with($imagePath, "/") ||
    !preg_match('/^[a-zA-Z0-9._\/-]+$/', $imagePath)
) {
    json_response(["error" => "image_path inválido. Use um caminho relativo permitido."], 422);
}

$pdo->beginTransaction();
try {
    $request = $pdo->prepare(
        "SELECT r.id, r.carregamento_id, r.equipment_id, r.reason, c.company_id
         FROM solicitacoes_captura_camera r
         JOIN carregamentos c ON c.id = r.carregamento_id
         WHERE r.id = :id AND r.equipment_id = :equipment_id
           AND r.claimed_by_device_id = :device_id AND r.status = 'CAPTURANDO'
         LIMIT 1 FOR UPDATE",
    );
    $request->execute([
        "id" => $requestId,
        "equipment_id" => $device["equipment_id"],
        "device_id" => $device["id"],
    ]);
    $capture = $request->fetch();
    if (!$capture) {
        $pdo->rollBack();
        json_response(["error" => "Pedido de captura não está reservado por este dispositivo."], 404);
    }
    $update = $pdo->prepare(
        "UPDATE solicitacoes_captura_camera
         SET status = 'CAPTURADA', captured_at = NOW(3), image_path = :image_path, error_message = NULL
         WHERE id = :id AND claimed_by_device_id = :device_id AND status = 'CAPTURANDO'",
    );
    $update->execute(["image_path" => $imagePath, "id" => $requestId, "device_id" => $device["id"]]);
    if ($update->rowCount() !== 1) {
        throw new RuntimeException("Pedido de captura já foi concluído.");
    }
    $image = $pdo->prepare(
        "INSERT INTO imagens (company_id, carregamento_id, equipment_id, path, reason, captured_at)
         VALUES (:company_id, :carregamento_id, :equipment_id, :path, :reason, NOW())",
    );
    $image->execute([
        "company_id" => $capture["company_id"],
        "carregamento_id" => $capture["carregamento_id"],
        "equipment_id" => $capture["equipment_id"],
        "path" => $imagePath,
        "reason" => $capture["reason"],
    ]);
    $imageId = (int) $pdo->lastInsertId();
    record_operational_event(
        $pdo,
        ["id" => null, "company_id" => (int) $capture["company_id"]],
        "CAMERA_CAPTURA_CONCLUIDA",
        "camera_request",
        (int) $requestId,
        [
            "image_id" => $imageId,
            "carregamento_id" => (int) $capture["carregamento_id"],
            "equipment_id" => (int) $capture["equipment_id"],
            "device_id" => $device["id"],
            "image_path" => $imagePath,
            "reason" => $capture["reason"],
        ],
    );
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($exception instanceof RuntimeException && $exception->getMessage() === "Pedido de captura já foi concluído.") {
        json_response(["error" => $exception->getMessage()], 409);
    }
    throw $exception;
}
json_response([
    "data" => [
        "request_id" => (int) $requestId,
        "image_id" => $imageId,
        "status" => "CAPTURADA",
    ],
]);
