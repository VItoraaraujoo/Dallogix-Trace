<?php
declare(strict_types=1);

require_once __DIR__ . "/../configuracao/bootstrap.php";

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    json_response(["error" => "Método não permitido."], 405);
}
$device = require_device_token(["CLP"]);

$statement = db()->prepare(
    "SELECT id, equipment_code, name, plc_ip, plc_port, plc_protocol
     FROM equipamentos WHERE id = :equipment_id AND company_id = :company_id LIMIT 1",
);
$statement->execute([
    "equipment_id" => $device["equipment_id"],
    "company_id" => $device["company_id"],
]);
json_response(["data" => $statement->fetchAll()]);
