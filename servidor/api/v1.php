<?php

declare(strict_types=1);

require_once __DIR__ . "/../configuracao/bootstrap.php";

header("X-Trace-API-Version: 1");
exigir_metodo_http(["GET", "POST"]);
$resource = strtolower(trim((string) ($_GET["resource"] ?? "")));
$method = strtoupper((string) $_SERVER["REQUEST_METHOD"]);
$requiredScope = match ($resource) {
    "produtos" => "produtos:read",
    "romaneios" => $method === "POST" ? "romaneios:write" : "romaneios:read",
    default => "",
};
if ($requiredScope === "") {
    responder_json(["error" => "Recurso de integração não encontrado."], 404);
}

$integration = exigir_chave_integracao([$requiredScope]);
$pdo = obter_conexao_banco();
$companyId = $integration["company_id"];

$respond = static function (array $payload, int $status = 200): never {
    responder_json($payload, $status);
};

if ($resource === "produtos") {
    $statement = $pdo->prepare(
        "SELECT p.id, p.code, p.name, p.category, p.active,
                GROUP_CONCAT(pc.barcode ORDER BY pc.barcode SEPARATOR ',') AS barcodes
         FROM produtos p LEFT JOIN codigos_produtos pc ON pc.product_id = p.id
         WHERE p.company_id = :company_id AND p.active = 1
         GROUP BY p.id ORDER BY p.name",
    );
    $statement->execute(["company_id" => $companyId]);
    $respond(["data" => $statement->fetchAll(), "meta" => ["company_id" => $companyId]]);
}

if ($method === "GET") {
    $number = trim((string) ($_GET["number"] ?? ""));
    $conditions = ["r.company_id = :company_id"];
    $params = ["company_id" => $companyId];
    if ($number !== "") {
        $conditions[] = "r.number = :number";
        $params["number"] = $number;
    }
    $statement = $pdo->prepare(
        "SELECT r.id, r.number, r.scheduled_date, r.status, r.expedidor,
                (SELECT rt.plate FROM romaneio_caminhoes rt WHERE rt.romaneio_id = r.id ORDER BY rt.id LIMIT 1) AS plate,
                COALESCE((SELECT SUM(ri.planned_quantity) FROM romaneio_itens ri WHERE ri.romaneio_id = r.id), 0) AS planned_quantity
         FROM romaneios r WHERE " . implode(" AND ", $conditions) . " ORDER BY r.id DESC LIMIT 200",
    );
    $statement->execute($params);
    $respond(["data" => $statement->fetchAll(), "meta" => ["company_id" => $companyId]]);
}

$idempotencyKey = trim((string) ($_SERVER["HTTP_IDEMPOTENCY_KEY"] ?? ""));
if (!preg_match('/^[A-Za-z0-9_-]{8,100}$/', $idempotencyKey)) {
    $respond(["error" => "O cabeçalho Idempotency-Key é obrigatório para criar romaneios."], 422);
}
$previous = $pdo->prepare(
    "SELECT response_code, response_body FROM requisicoes_integracao
     WHERE integration_key_id = :key_id AND idempotency_key = :idempotency_key LIMIT 1",
);
$previous->execute(["key_id" => $integration["key_id"], "idempotency_key" => $idempotencyKey]);
if ($stored = $previous->fetch()) {
    $respond(json_decode((string) $stored["response_body"], true) ?: ["error" => "Resposta anterior inválida."], (int) $stored["response_code"]);
}

$payload = ler_json_da_requisicao();
$number = trim((string) ($payload["number"] ?? ""));
$scheduledDate = trim((string) ($payload["scheduled_date"] ?? ""));
$plate = strtoupper(trim((string) ($payload["plate"] ?? "")));
$driverName = trim((string) ($payload["driver_name"] ?? ""));
$expedidor = trim((string) ($payload["expedidor"] ?? ""));
$items = $payload["items"] ?? [];
if ($number === "" || mb_strlen($number) > 80 || $plate === "" || !is_array($items) || $items === []) {
    $respond(["error" => "Número, placa e ao menos um item são obrigatórios."], 422);
}
$date = DateTime::createFromFormat("Y-m-d", $scheduledDate);
if (!$date || $date->format("Y-m-d") !== $scheduledDate) {
    $respond(["error" => "scheduled_date deve usar AAAA-MM-DD."], 422);
}

try {
    $pdo->beginTransaction();
    $resolved = [];
    foreach (array_values($items) as $index => $item) {
        if (!is_array($item)) $respond(["error" => "Item " . ($index + 1) . " inválido."], 422);
        $code = trim((string) ($item["product_code"] ?? ""));
        $quantity = filter_var($item["quantity"] ?? null, FILTER_VALIDATE_INT);
        if ($code === "" || $quantity === false || $quantity < 1) $respond(["error" => "Item " . ($index + 1) . " requer product_code e quantity positiva."], 422);
        $product = $pdo->prepare("SELECT id FROM produtos WHERE company_id = :company_id AND code = :code AND active = 1 LIMIT 1");
        $product->execute(["company_id" => $companyId, "code" => $code]);
        $row = $product->fetch();
        if (!$row) $respond(["error" => "Produto não encontrado ou inativo: {$code}."], 422);
        $resolved[] = ["product_id" => (int) $row["id"], "quantity" => (int) $quantity];
    }
    $insert = $pdo->prepare("INSERT INTO romaneios (company_id, number, scheduled_date, status, expedidor) VALUES (:company_id, :number, :scheduled_date, 'AGUARDANDO', :expedidor)");
    $insert->execute(["company_id" => $companyId, "number" => $number, "scheduled_date" => $scheduledDate, "expedidor" => $expedidor ?: null]);
    $romaneioId = (int) $pdo->lastInsertId();
    $truck = $pdo->prepare("INSERT INTO romaneio_caminhoes (romaneio_id, plate, driver_name) VALUES (:romaneio_id, :plate, :driver_name)");
    $truck->execute(["romaneio_id" => $romaneioId, "plate" => $plate, "driver_name" => $driverName ?: null]);
    $truckId = (int) $pdo->lastInsertId();
    $itemInsert = $pdo->prepare("INSERT INTO romaneio_itens (romaneio_id, product_id, truck_id, planned_quantity) VALUES (:romaneio_id, :product_id, :truck_id, :quantity)");
    foreach ($resolved as $item) $itemInsert->execute(["romaneio_id" => $romaneioId, "product_id" => $item["product_id"], "truck_id" => $truckId, "quantity" => $item["quantity"]]);
    $response = ["data" => ["id" => $romaneioId, "number" => $number, "status" => "AGUARDANDO"]];
    $record = $pdo->prepare("INSERT INTO requisicoes_integracao (company_id, integration_key_id, idempotency_key, method, resource, response_code, response_body) VALUES (:company_id, :key_id, :idempotency_key, 'POST', 'romaneios', 201, :response_body)");
    $record->execute(["company_id" => $companyId, "key_id" => $integration["key_id"], "idempotency_key" => $idempotencyKey, "response_body" => json_encode($response, JSON_THROW_ON_ERROR)]);
    $pdo->commit();
    $respond($response, 201);
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ((int) ($exception->errorInfo[1] ?? 0) === 1062) $respond(["error" => "Já existe um romaneio com este número."], 409);
    throw $exception;
}
