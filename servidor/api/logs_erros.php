<?php
declare(strict_types=1);

require_once __DIR__ . "/../configuracao/bootstrap.php";

$user = require_role(["ADMIN_DALLOGIX", "ADMIN_EMPRESA"]);
if ($_SERVER["REQUEST_METHOD"] !== "GET") json_response(["error" => "Método não permitido."], 405);
$pdo = db();
$scope = $user["role"] === "ADMIN_DALLOGIX" ? "1=1" : "l.company_id = :company_id";
$query = $pdo->prepare("SELECT l.id, l.origem, l.mensagem, l.contexto, l.criado_em, e.name AS empresa, u.name AS usuario FROM logs_erros l LEFT JOIN empresas e ON e.id = l.company_id LEFT JOIN usuarios u ON u.id = l.user_id WHERE {$scope} ORDER BY l.id DESC LIMIT 100");
$query->execute($user["role"] === "ADMIN_DALLOGIX" ? [] : ["company_id" => $user["company_id"]]);
json_response(["data" => $query->fetchAll()]);
