<?php

declare(strict_types=1);

require_once __DIR__ . "/../configuracao/bootstrap.php";

$usuarioAtor = exigir_sessao_usuario();
if ($usuarioAtor["company_id"] === null) {
    responder_json(["error" => "Usuário sem empresa vinculada."], 403);
}

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $statement = obter_conexao_banco()->prepare(
        'SELECT c.id, c.state, c.equipment_id, c.started_at, c.finished_at,
                r.number AS romaneio_number, rt.plate, e.equipment_code,
                COALESCE((SELECT SUM(ri.planned_quantity) FROM romaneio_itens ri WHERE ri.romaneio_id = c.romaneio_id AND (ri.truck_id = c.truck_id OR ri.truck_id IS NULL)), 0) AS planned_quantity,
                COALESCE(c.leituras_validas, 0) AS valid_readings
         FROM carregamentos c
         JOIN romaneios r ON r.id = c.romaneio_id
         JOIN romaneio_caminhoes rt ON rt.id = c.truck_id
         JOIN equipamentos e ON e.id = c.equipment_id
         WHERE c.company_id = :company_id
         ORDER BY c.id DESC',
    );
    $statement->execute(["company_id" => $usuarioAtor["company_id"]]);
    responder_json(["data" => $statement->fetchAll()]);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responder_json(["error" => "Método não permitido."], 405);
}
if (is_file(dirname(__DIR__, 2) . "/armazenamento/.maintenance")) {
    responder_json(["error" => "Atualização do sistema em andamento; tente preparar o carregamento novamente em instantes."], 503);
}
exigir_csrf();
if (!in_array($usuarioAtor["role"], ["ADMIN_EMPRESA", "SUPERVISOR"], true)) {
    responder_json(
        [
            "error" =>
            "A preparação do carregamento é permitida somente para administração ou supervisão.",
        ],
        403,
    );
}
validar_licenca_ativa(obter_conexao_banco(), (int) $usuarioAtor["company_id"]);

$payload = ler_json_da_requisicao();
$romaneioId = filter_var($payload["romaneio_id"] ?? null, FILTER_VALIDATE_INT);
$truckId = filter_var($payload["truck_id"] ?? null, FILTER_VALIDATE_INT);
$equipmentId = filter_var(
    $payload["equipment_id"] ?? ($payload["esteira_id"] ?? null),
    FILTER_VALIDATE_INT,
);
if (!$romaneioId || !$truckId || !$equipmentId) {
    responder_json(
        ["error" => "Romaneio, caminhão e esteira são obrigatórios."],
        422,
    );
}

$pdo = obter_conexao_banco();
$statement = $pdo->prepare(
    'SELECT r.id AS romaneio_id, rt.id AS truck_id, e.id AS equipment_id
     FROM romaneios r
     JOIN romaneio_caminhoes rt ON rt.romaneio_id = r.id
     JOIN equipamentos e ON e.id = :equipment_id AND e.company_id = r.company_id
     WHERE r.id = :romaneio_id AND rt.id = :truck_id AND r.company_id = :company_id LIMIT 1',
);
$statement->execute([
    "equipment_id" => $equipmentId,
    "romaneio_id" => $romaneioId,
    "truck_id" => $truckId,
    "company_id" => $usuarioAtor["company_id"],
]);
$valid = $statement->fetch();
if (!$valid) {
    responder_json(
        [
            "error" =>
            "Romaneio, caminhão ou equipamento não pertence à empresa.",
        ],
        422,
    );
}

try {
    $pdo->beginTransaction();
    $available = $pdo->prepare(
        "SELECT r.status
         FROM romaneios r
         WHERE r.id = :romaneio_id AND r.company_id = :company_id FOR UPDATE",
    );
    $available->execute([
        "romaneio_id" => $romaneioId,
        "company_id" => $usuarioAtor["company_id"],
    ]);
    $romaneio = $available->fetch();
    if (
        !$romaneio ||
        in_array($romaneio["status"], ["FINALIZADO", "CANCELADO"], true)
    ) {
        $pdo->rollBack();
        responder_json(
            [
                "error" =>
                "Este romaneio não está disponível para novo carregamento.",
            ],
            409,
        );
    }
    $conflict = $pdo->prepare(
        "SELECT id FROM carregamentos
         WHERE company_id = :company_id AND state <> 'FINALIZADO'
           AND (equipment_id = :equipment_id OR (romaneio_id = :romaneio_id AND truck_id = :truck_id))
         LIMIT 1 FOR UPDATE",
    );
    $conflict->execute([
        "company_id" => $usuarioAtor["company_id"],
        "equipment_id" => $equipmentId,
        "romaneio_id" => $romaneioId,
        "truck_id" => $truckId,
    ]);
    if ($conflict->fetch()) {
        $pdo->rollBack();
        responder_json(
            [
                "error" =>
                "A Dala ou este caminhão já possui um carregamento em andamento.",
            ],
            409,
        );
    }
    $insert = $pdo->prepare(
        'INSERT INTO carregamentos (company_id, equipment_id, romaneio_id, truck_id, state, started_at) VALUES (:company_id, :equipment_id, :romaneio_id, :truck_id, \'PREPARANDO\', NOW())',
    );
    $insert->execute([
        "company_id" => $usuarioAtor["company_id"],
        "equipment_id" => $equipmentId,
        "romaneio_id" => $romaneioId,
        "truck_id" => $truckId,
    ]);
    $loadingId = (int) $pdo->lastInsertId();
    $pdo->prepare(
        "UPDATE romaneios SET status = 'EM_ANDAMENTO' WHERE id = :id",
    )->execute(["id" => $romaneioId]);
    record_operational_event(
        $pdo,
        $usuarioAtor,
        "CARREGAMENTO_PREPARADO",
        "carregamento",
        $loadingId,
        [
            "romaneio_id" => $romaneioId,
            "truck_id" => $truckId,
            "equipment_id" => $equipmentId,
        ],
    );
    $pdo->commit();
    responder_json(
        ["data" => ["id" => $loadingId, "state" => "PREPARANDO"]],
        201,
    );
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Loading preparation failed: " . $exception->getMessage());
    responder_json(
        ["error" => "Não foi possível preparar o carregamento."],
        500,
    );
}
