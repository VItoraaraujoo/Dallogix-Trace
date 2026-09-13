<?php
declare(strict_types=1);

require_once __DIR__ . "/../configuracao/bootstrap.php";

require_internal_token("CAMERA_INTERNAL_TOKEN", "change-me-camera-token");
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    json_response(["error" => "Método não permitido."], 405);
}

$payload = request_json();
$action = strtoupper(trim((string) ($payload["action"] ?? "CLAIM")));
$pdo = db();

if ($action === "CLAIM") {
    $pdo->beginTransaction();
    $statement = $pdo->query(
        "SELECT id, sensor_event_id, carregamento_id, equipment_id, reason FROM solicitacoes_captura_camera WHERE status = 'PENDENTE' ORDER BY requested_at, id LIMIT 1 FOR UPDATE SKIP LOCKED",
    );
    $request = $statement->fetch();
    if (!$request) {
        $pdo->commit();
        json_response(["data" => null], 204);
    }
    $update = $pdo->prepare(
        "UPDATE solicitacoes_captura_camera SET status = 'CAPTURANDO' WHERE id = :id",
    );
    $update->execute(["id" => $request["id"]]);
    $pdo->commit();
    json_response(["data" => $request]);
}

if ($action !== "COMPLETE") {
    json_response(["error" => "Ação inválida."], 422);
}
$requestId = filter_var($payload["request_id"] ?? null, FILTER_VALIDATE_INT);
$imagePath = trim((string) ($payload["image_path"] ?? ""));
if (!$requestId || $imagePath === "") {
    json_response(
        ["error" => "request_id e image_path são obrigatórios."],
        422,
    );
}
if (
    strlen($imagePath) > 500 ||
    str_contains($imagePath, "..") ||
    str_starts_with($imagePath, "/") ||
    !preg_match('/^[a-zA-Z0-9._\/-]+$/', $imagePath)
) {
    json_response(
        ["error" => "image_path inválido. Use um caminho relativo permitido."],
        422,
    );
}

$pdo->beginTransaction();
try {
    $request = $pdo->prepare(
        'SELECT id, carregamento_id, equipment_id, reason FROM solicitacoes_captura_camera WHERE id = :id AND status = \'CAPTURANDO\' LIMIT 1 FOR UPDATE',
    );
    $request->execute(["id" => $requestId]);
    $capture = $request->fetch();
    if (!$capture) {
        $pdo->rollBack();
        json_response(["error" => "Pedido de captura não está em processamento."], 404);
    }
    $update = $pdo->prepare(
        "UPDATE solicitacoes_captura_camera SET status = 'CAPTURADA', captured_at = NOW(3), image_path = :image_path, error_message = NULL WHERE id = :id AND status = 'CAPTURANDO'",
    );
    $update->execute(["image_path" => $imagePath, "id" => $requestId]);
    if ($update->rowCount() !== 1) {
        throw new RuntimeException("Pedido de captura já foi concluído.");
    }
    $image = $pdo->prepare(
        "INSERT INTO imagens (carregamento_id, equipment_id, path, reason, captured_at) VALUES (:carregamento_id, :equipment_id, :path, :reason, NOW())",
    );
    $image->execute([
        "carregamento_id" => $capture["carregamento_id"],
        "equipment_id" => $capture["equipment_id"],
        "path" => $imagePath,
        "reason" => $capture["reason"],
    ]);
    $imageId = (int) $pdo->lastInsertId();
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
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
