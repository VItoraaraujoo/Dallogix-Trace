<?php
declare(strict_types=1);

require_once __DIR__ . "/../configuracao/bootstrap.php";

$user = require_session_user();
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    json_response(["error" => "Método não permitido."], 405);
}
require_csrf();
if ($user["company_id"] === null) {
    json_response(["error" => "Usuário sem empresa vinculada."], 403);
}

$payload = request_json();
$loadingId = filter_var(
    $payload["carregamento_id"] ?? null,
    FILTER_VALIDATE_INT,
);
$equipmentId = filter_var(
    $payload["equipment_id"] ?? ($payload["esteira_id"] ?? null),
    FILTER_VALIDATE_INT,
);
$eventUuid = trim((string) ($payload["event_uuid"] ?? ""));
$detectedAt = trim((string) ($payload["detected_at"] ?? ""));
if (
    !$loadingId ||
    !$equipmentId ||
    !preg_match('/^[a-f0-9-]{36}$/i', $eventUuid)
) {
    json_response(
        [
            "error" =>
                "Carregamento, esteira e event_uuid válido são obrigatórios.",
        ],
        422,
    );
}

$loadingStatement = db()->prepare(
    'SELECT c.id FROM carregamentos c
     JOIN equipamentos e ON e.id = c.equipment_id AND e.id = :equipment_id
     WHERE c.id = :loading_id AND c.company_id = :company_id LIMIT 1',
);
$loadingStatement->execute([
    "equipment_id" => $equipmentId,
    "loading_id" => $loadingId,
    "company_id" => $user["company_id"],
]);
if (!$loadingStatement->fetch()) {
    json_response(
        ["error" => "Carregamento e esteira não pertencem à empresa."],
        422,
    );
}

$eventDate =
    $detectedAt !== ""
        ? DateTime::createFromFormat("Y-m-d H:i:s.v", $detectedAt)
        : new DateTime();
if (!$eventDate) {
    json_response(["error" => "Data do evento inválida."], 422);
}

$pdo = db();
try {
    $debounce = $pdo->prepare(
        "SELECT id FROM eventos_sensor
         WHERE carregamento_id = :carregamento_id
           AND equipment_id = :equipment_id
           AND TIMESTAMPDIFF(MICROSECOND, detected_at, :detected_at) BETWEEN 0 AND 400000
         ORDER BY detected_at DESC LIMIT 1",
    );
    $debounce->execute([
        "carregamento_id" => $loadingId,
        "equipment_id" => $equipmentId,
        "detected_at" => $eventDate->format("Y-m-d H:i:s.v"),
    ]);
    $debouncedId = $debounce->fetchColumn();
    if ($debouncedId !== false) {
        json_response([
            "data" => [
                "id" => (int) $debouncedId,
                "duplicate" => true,
                "debounced" => true,
                "debounce_ms" => 400,
            ],
        ]);
    }
    $insert = $pdo->prepare(
        "INSERT INTO eventos_sensor (carregamento_id, equipment_id, event_uuid, detected_at, debounce_ms) VALUES (:carregamento_id, :equipment_id, :event_uuid, :detected_at, 400)",
    );
    $insert->execute([
        "carregamento_id" => $loadingId,
        "equipment_id" => $equipmentId,
        "event_uuid" => $eventUuid,
        "detected_at" => $eventDate->format("Y-m-d H:i:s.v"),
    ]);
    $eventId = (int) $pdo->lastInsertId();
    record_operational_event(
        $pdo,
        $user,
        "SENSOR_EVENTO_RECEBIDO",
        "sensor_event",
        $eventId,
        ["carregamento_id" => (int) $loadingId, "event_uuid" => $eventUuid],
    );
    // O sensor apenas correlaciona o saco à leitura. A câmera é acionada pelo
    // resultado da leitura: não fotografamos cada saco que passa pela esteira.
    json_response(
        [
            "data" => [
                "id" => $eventId,
                "duplicate" => false,
                "camera_trigger" => "AGUARDANDO_RESULTADO_LEITURA",
            ],
        ],
        201,
    );
} catch (PDOException $exception) {
    if (
        isset($exception->errorInfo[1]) &&
        (int) $exception->errorInfo[1] === 1062
    ) {
        $existing = $pdo->prepare(
            "SELECT id FROM eventos_sensor WHERE event_uuid = :event_uuid LIMIT 1",
        );
        $existing->execute(["event_uuid" => $eventUuid]);
        json_response(
            [
                "data" => [
                    "id" => (int) $existing->fetchColumn(),
                    "duplicate" => true,
                ],
            ],
            200,
        );
    }
    json_response(
        ["error" => "Não foi possível registrar o evento do sensor."],
        500,
    );
}
