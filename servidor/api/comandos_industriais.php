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

$loadingId = filter_var($_GET["carregamento_id"] ?? null, FILTER_VALIDATE_INT);
 $equipmentId = filter_var($_GET["equipment_id"] ?? null, FILTER_VALIDATE_INT);
if ($equipmentId) {
    $statement = db()->prepare("SELECT r.id, r.carregamento_id, r.command, r.status, r.requested_at, r.claimed_at, r.completed_at, r.response_message, m.number AS romaneio_number FROM solicitacoes_comandos_clp r LEFT JOIN carregamentos c ON c.id = r.carregamento_id LEFT JOIN romaneios m ON m.id = c.romaneio_id WHERE r.equipment_id = :equipment_id AND r.company_id = :company_id ORDER BY r.id DESC LIMIT 30");
    $statement->execute(["equipment_id" => $equipmentId, "company_id" => $user["company_id"]]);
    json_response(["data" => $statement->fetchAll()]);
}
if (!$loadingId) {
    json_response(["error" => "equipment_id ou carregamento_id é obrigatório."], 422);
}

$statement = db()
    ->prepare("SELECT id, command, status, requested_at, claimed_at, completed_at, response_message
    FROM solicitacoes_comandos_clp
    WHERE carregamento_id = :carregamento_id AND company_id = :company_id
    ORDER BY id DESC LIMIT 1");
$statement->execute([
    "carregamento_id" => $loadingId,
    "company_id" => $user["company_id"],
]);
$command = $statement->fetch();
json_response(["data" => $command ?: null]);
