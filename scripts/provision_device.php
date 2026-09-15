<?php
declare(strict_types=1);

$options = getopt("", ["equipment-id:", "device-type:", "device-code:", "token:"]);
$equipmentId = filter_var($options["equipment-id"] ?? null, FILTER_VALIDATE_INT);
$deviceType = strtoupper(trim((string) ($options["device-type"] ?? "")));
$deviceCode = trim((string) ($options["device-code"] ?? ""));
$token = (string) ($options["token"] ?? "");
$allowedTypes = ["CLP", "SCANNER", "SENSOR", "CAMERA", "SERVER"];
if (
    !$equipmentId ||
    !in_array($deviceType, $allowedTypes, true) ||
    !preg_match('/^[A-Za-z0-9._-]{3,80}$/', $deviceCode) ||
    strlen($token) < 32 ||
    strlen($token) > 512
) {
    fwrite(STDERR, "Uso: php provision_device.php --equipment-id=N --device-type=CLP --device-code=PLC-EST-001 --token=TOKEN_FORTE\n");
    exit(2);
}

$host = getenv("DB_HOST") ?: "mysql";
$name = getenv("DB_NAME") ?: "trace_local";
$user = getenv("DB_USER") ?: "trace";
$password = getenv("DB_PASSWORD") ?: "change-me-local";
$pdo = new PDO(
    "mysql:host={$host};dbname={$name};charset=utf8mb4",
    $user,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
);
$equipment = $pdo->prepare("SELECT id, company_id FROM equipamentos WHERE id = :id LIMIT 1");
$equipment->execute(["id" => $equipmentId]);
$row = $equipment->fetch();
if (!$row) {
    fwrite(STDERR, "ERRO: equipamento não encontrado.\n");
    exit(3);
}

$statement = $pdo->prepare(
    "INSERT INTO dispositivos (company_id, equipment_id, device_code, device_type, token_hash, active)
     VALUES (:company_id, :equipment_id, :device_code, :device_type, :token_hash, 1)
     ON DUPLICATE KEY UPDATE company_id = VALUES(company_id), equipment_id = VALUES(equipment_id),
         device_type = VALUES(device_type), token_hash = VALUES(token_hash), active = 1",
);
$statement->execute([
    "company_id" => $row["company_id"],
    "equipment_id" => $equipmentId,
    "device_code" => $deviceCode,
    "device_type" => $deviceType,
    "token_hash" => password_hash($token, PASSWORD_DEFAULT),
]);
echo "OK: dispositivo {$deviceCode} provisionado para o equipamento {$equipmentId}. O token não foi armazenado em texto puro.\n";
