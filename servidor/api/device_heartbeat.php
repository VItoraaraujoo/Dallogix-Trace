<?php
declare(strict_types=1);

require_once __DIR__ . "/../configuracao/bootstrap.php";

exigir_metodo_http(["POST"]);
$device = require_device_token();
$payload = request_json();
$equipmentId = filter_var($payload["equipment_id"] ?? null, FILTER_VALIDATE_INT);
$deviceType = strtoupper(trim((string) ($payload["device_type"] ?? "")));
$status = strtoupper(trim((string) ($payload["status"] ?? "ONLINE")));
if (
    $equipmentId === false ||
    (int) $equipmentId !== $device["equipment_id"] ||
    $deviceType !== $device["device_type"] ||
    !in_array($status, ["ONLINE", "OFFLINE", "ERRO"], true)
) {
    json_response(
        ["error" => "O dispositivo só pode atualizar o status da própria Dala."],
        403,
    );
}

$details = isset($payload["details"])
    ? json_encode($payload["details"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
    : null;
$pdo = db();
$pdo->beginTransaction();
try {
    $current = $pdo->prepare(
        "SELECT id, status FROM status_dispositivos
         WHERE equipment_id = :equipment_id AND device_type = :device_type
         LIMIT 1 FOR UPDATE",
    );
    $current->execute([
        "equipment_id" => $device["equipment_id"],
        "device_type" => $device["device_type"],
    ]);
    $previous = $current->fetch();

    $upsert = $pdo->prepare(
        "INSERT INTO status_dispositivos
         (device_id, equipment_id, device_type, status, last_seen_at, details)
         VALUES (:device_id, :equipment_id, :device_type, :status, NOW(), :details)
         ON DUPLICATE KEY UPDATE device_id = VALUES(device_id), status = VALUES(status),
             last_seen_at = VALUES(last_seen_at), details = VALUES(details)",
    );
    $upsert->execute([
        "device_id" => $device["id"],
        "equipment_id" => $device["equipment_id"],
        "device_type" => $device["device_type"],
        "status" => $status,
        "details" => $details,
    ]);

    $statusId = (int) ($previous["id"] ?? $pdo->lastInsertId());
    if (!$previous || $previous["status"] !== $status) {
        record_operational_event(
            $pdo,
            ["id" => null, "company_id" => $device["company_id"]],
            "STATUS_DISPOSITIVO_ALTERADO",
            "status_dispositivo",
            $statusId,
            [
                "equipment_id" => $device["equipment_id"],
                "device_id" => $device["id"],
                "device_type" => $device["device_type"],
                "previous_status" => $previous["status"] ?? null,
                "status" => $status,
            ],
        );
    }
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}

json_response([
    "data" => [
        "equipment_id" => $device["equipment_id"],
        "device_type" => $device["device_type"],
        "status" => $status,
    ],
]);
