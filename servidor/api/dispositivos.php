<?php
declare(strict_types=1);

require_once __DIR__ . "/../configuracao/bootstrap.php";

$user = require_session_user();
if ($user["company_id"] === null) {
    json_response(["error" => "Usuário sem empresa vinculada."], 403);
}
$pdo = db();

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $statement = $pdo->prepare(
        "SELECT d.id, d.equipment_id, e.equipment_code, d.device_type, d.status, d.last_seen_at, d.details FROM status_dispositivos d JOIN equipamentos e ON e.id = d.equipment_id WHERE e.company_id = :company_id ORDER BY e.equipment_code, d.device_type",
    );
    $statement->execute(["company_id" => $user["company_id"]]);
    json_response(["data" => $statement->fetchAll()]);
}
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    json_response(["error" => "Método não permitido."], 405);
}
if (ambiente_atual() === "production") {
    json_response(["error" => "Use o heartbeat autenticado do dispositivo para atualizar status."], 410);
}
require_csrf();

$payload = request_json();
$equipmentId = filter_var(
    $payload["equipment_id"] ?? ($payload["esteira_id"] ?? null),
    FILTER_VALIDATE_INT,
);
$deviceType = strtoupper(trim((string) ($payload["device_type"] ?? "")));
$status = strtoupper(trim((string) ($payload["status"] ?? "DESCONHECIDO")));
$allowedDevices = ["CLP", "SCANNER", "SENSOR", "CAMERA", "SERVER"];
$allowedStatuses = ["ONLINE", "OFFLINE", "ERRO", "DESCONHECIDO"];
if (
    !$equipmentId ||
    !in_array($deviceType, $allowedDevices, true) ||
    !in_array($status, $allowedStatuses, true)
) {
    json_response(
        [
            "error" =>
                "Equipamento, dispositivo e status válidos são obrigatórios.",
        ],
        422,
    );
}

$equipment = $pdo->prepare(
    "SELECT id FROM equipamentos WHERE id = :id AND company_id = :company_id LIMIT 1",
);
$equipment->execute([
    "id" => $equipmentId,
    "company_id" => $user["company_id"],
]);
if (!$equipment->fetch()) {
    json_response(["error" => "Equipamento não pertence à empresa."], 422);
}

$details = isset($payload["details"])
    ? json_encode(
        $payload["details"],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    )
    : null;
$upsert = $pdo->prepare(
    "INSERT INTO status_dispositivos (equipment_id, device_type, status, last_seen_at, details) VALUES (:equipment_id, :device_type, :status, NOW(), :details) ON DUPLICATE KEY UPDATE status = VALUES(status), last_seen_at = VALUES(last_seen_at), details = VALUES(details)",
);
$upsert->execute([
    "equipment_id" => $equipmentId,
    "device_type" => $deviceType,
    "status" => $status,
    "details" => $details,
]);
json_response(
    [
        "data" => [
            "equipment_id" => (int) $equipmentId,
            "device_type" => $deviceType,
            "status" => $status,
        ],
    ],
    201,
);
