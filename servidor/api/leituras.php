<?php

declare(strict_types=1);

require_once __DIR__ . "/../configuracao/bootstrap.php";
require_once __DIR__ . "/../src/Aplicacao/ServicoLeituras.php";

use App\Aplicacao\ServicoLeituras;

$usuarioAtor = exigir_sessao_usuario();
if ($usuarioAtor["company_id"] === null) {
    responder_json(["error" => "Usuário sem empresa vinculada."], 403);
}
if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $loadingId = filter_var($_GET["carregamento_id"] ?? null, FILTER_VALIDATE_INT);
    if (!$loadingId) responder_json(["error" => "Carregamento obrigatório."], 422);
    $pendencias = (new ServicoLeituras(obter_conexao_banco()))->listarPendentes(
        (int) $usuarioAtor["company_id"],
        (int) $loadingId,
    );
    responder_json(["data" => $pendencias]);
}
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responder_json(["error" => "Método não permitido."], 405);
}
exigir_csrf();

$payload = ler_json_da_requisicao();
$loadingId = filter_var(
    $payload["carregamento_id"] ?? null,
    FILTER_VALIDATE_INT,
);
$sensorEventId = filter_var(
    $payload["sensor_event_id"] ?? null,
    FILTER_VALIDATE_INT,
);
$barcode = trim((string) ($payload["barcode"] ?? ""));
$attemptNumber = filter_var(
    $payload["attempt_number"] ?? 1,
    FILTER_VALIDATE_INT,
);
if (!$loadingId) {
    json_response(["error" => "Carregamento obrigatório."], 422);
}
if ($attemptNumber !== 1) {
    json_response(
        ["error" => "Cada saco possui uma única tentativa de leitura."],
        422,
    );
}

$pdo = db();
$pdo->beginTransaction();
register_shutdown_function(static function () use ($pdo): void {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
});
$loadingStatement = $pdo->prepare(
    'SELECT c.id, c.state, c.romaneio_id, c.truck_id, c.equipment_id
     FROM carregamentos c
     WHERE c.id = :id AND c.company_id = :company_id LIMIT 1 FOR UPDATE',
);
$loadingStatement->execute([
    "id" => $loadingId,
    "company_id" => $usuarioAtor["company_id"],
]);
$loading = $loadingStatement->fetch();
if (!$loading) {
    $pdo->rollBack();
    responder_json(
        ["error" => "Carregamento não encontrado para esta empresa."],
        404,
    );
}
if ($loading["state"] === "EMERGENCIA") {
    $pdo->rollBack();
    responder_json(
        [
            "error" =>
            "Contagem bloqueada: a máquina está em emergência. Libere a máquina antes de registrar leituras.",
        ],
        423,
    );
}
if ($loading["state"] !== "CARREGANDO") {
    $pdo->rollBack();
    responder_json(
        ["error" => "Leitura ignorada: a esteira está em {$loading["state"]}."],
        409,
    );
}

$plannedStatement = $pdo->prepare(
    "SELECT COALESCE(SUM(ri.planned_quantity), 0) FROM romaneio_itens ri WHERE ri.romaneio_id = :romaneio_id AND (ri.truck_id = :truck_id OR ri.truck_id IS NULL)",
);
$plannedStatement->execute([
    "romaneio_id" => $loading["romaneio_id"],
    "truck_id" => $loading["truck_id"],
]);
$plannedQuantity = (int) $plannedStatement->fetchColumn();
$validCountStatement = $pdo->prepare(
    "SELECT leituras_validas FROM carregamentos WHERE id = :carregamento_id FOR UPDATE",
);
$validCountStatement->execute(["carregamento_id" => $loadingId]);
$validCount = (int) $validCountStatement->fetchColumn();
if ($sensorEventId) {
    $sensorStatement = $pdo->prepare(
        "SELECT id FROM eventos_sensor WHERE id = :sensor_event_id AND carregamento_id = :carregamento_id AND equipment_id = :equipment_id LIMIT 1",
    );
    $sensorStatement->execute([
        "sensor_event_id" => $sensorEventId,
        "carregamento_id" => $loadingId,
        "equipment_id" => $loading["equipment_id"],
    ]);
    if (!$sensorStatement->fetch()) {
        $pdo->rollBack();
        responder_json(
            ["error" => "Evento de sensor não pertence ao carregamento."],
            422,
        );
    }
}

$result = "SEM_LEITURA";
$productId = null;
if ($barcode !== "") {
    $productStatement = $pdo->prepare(
        'SELECT p.id
         FROM codigos_produtos pc
         JOIN produtos p ON p.id = pc.product_id AND p.company_id = :company_id
         WHERE pc.barcode = :barcode AND p.active = 1 LIMIT 1',
    );
    $productStatement->execute([
        "company_id" => $usuarioAtor["company_id"],
        "barcode" => $barcode,
    ]);
    $product = $productStatement->fetch();
    if ($product) {
        $plannedStatement = $pdo->prepare(
            "SELECT 1 FROM romaneio_itens WHERE romaneio_id = :romaneio_id AND product_id = :product_id AND (truck_id = :truck_id OR truck_id IS NULL) LIMIT 1",
        );
        $plannedStatement->execute([
            "romaneio_id" => $loading["romaneio_id"],
            "product_id" => $product["id"],
            "truck_id" => $loading["truck_id"],
        ]);
        $productId = (int) $product["id"];
        $result = $plannedStatement->fetch() ? "VALIDO" : "PRODUTO_INCORRETO";
    } else {
        $result = "PRODUTO_INCORRETO";
    }
}
if ($result === "VALIDO" && $plannedQuantity > 0 && $validCount >= $plannedQuantity) {
    $result = "EXCESSO";
}

$insert = $pdo->prepare(
    "INSERT INTO leituras (company_id, carregamento_id, sensor_event_id, product_id, barcode, attempt_number, result, read_at) VALUES (:company_id, :carregamento_id, :sensor_event_id, :product_id, :barcode, :attempt_number, :result, NOW(3))",
);
try {
    $insert->execute([
        "company_id" => $usuarioAtor["company_id"],
        "carregamento_id" => $loadingId,
        "sensor_event_id" => $sensorEventId ?: null,
        "product_id" => $productId,
        "barcode" => $barcode !== "" ? $barcode : null,
        "attempt_number" => $attemptNumber,
        "result" => $result,
    ]);
} catch (PDOException $exception) {
    if (
        $sensorEventId &&
        isset($exception->errorInfo[1]) &&
        (int) $exception->errorInfo[1] === 1062
    ) {
        $existing = $pdo->prepare(
            "SELECT id, result FROM leituras WHERE sensor_event_id = :sensor_event_id LIMIT 1",
        );
        $existing->execute(["sensor_event_id" => $sensorEventId]);
        $reading = $existing->fetch();
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        responder_json(
            [
                "data" => [
                    "id" => (int) $reading["id"],
                    "result" => $reading["result"],
                    "duplicate" => true,
                    "retry" => false,
                    "final" => true,
                ],
            ],
            200,
        );
    }
    throw $exception;
}
$readingId = (int) $pdo->lastInsertId();
if ($result === "VALIDO") {
    $validCounter = $pdo->prepare(
        "UPDATE carregamentos SET leituras_validas = leituras_validas + 1 WHERE id = :id",
    );
    $validCounter->execute(["id" => $loadingId]);
}
record_operational_event(
    $pdo,
    $usuarioAtor,
    "LEITURA_REGISTRADA",
    "leitura",
    $readingId,
    [
        "carregamento_id" => (int) $loadingId,
        "result" => $result,
        "barcode" => $barcode,
        "attempt_number" => $attemptNumber,
    ],
);
$occurrenceId = null;
$cameraStatus = "NAO_SOLICITADA";
if ($result === "SEM_LEITURA") {
    $occurrence = $pdo->prepare(
        'INSERT INTO ocorrencias (company_id, carregamento_id, type, quantity, description, created_by) VALUES (:company_id, :carregamento_id, \'FALHA_SEM_LEITURA\', 1, :description, :created_by)',
    );
    $occurrence->execute([
        "company_id" => $usuarioAtor["company_id"],
        "carregamento_id" => $loadingId,
        "description" =>
        "Saco detectado pelo sensor sem código de barras válido.",
        "created_by" => $usuarioAtor["id"],
    ]);
    $occurrenceId = (int) $pdo->lastInsertId();
    record_operational_event(
        $pdo,
        $usuarioAtor,
        "FALHA_SEM_LEITURA",
        "ocorrencia",
        $occurrenceId,
        ["carregamento_id" => (int) $loadingId, "leitura_id" => $readingId],
    );
    $cameraStatus = "CAPTURA_PENDENTE_INCIDENTE";
}
if ($result === "PRODUTO_INCORRETO") {
    $occurrence = $pdo->prepare(
        'INSERT INTO ocorrencias (company_id, carregamento_id, type, quantity, description, created_by) VALUES (:company_id, :carregamento_id, \'PRODUTO_INCORRETO\', 1, :description, :created_by)',
    );
    $occurrence->execute([
        "company_id" => $usuarioAtor["company_id"],
        "carregamento_id" => $loadingId,
        "description" => "Produto lido não está previsto para o carregamento.",
        "created_by" => $usuarioAtor["id"],
    ]);
    $occurrenceId = (int) $pdo->lastInsertId();
    record_operational_event(
        $pdo,
        $usuarioAtor,
        "PRODUTO_INCORRETO",
        "ocorrencia",
        $occurrenceId,
        ["carregamento_id" => (int) $loadingId, "leitura_id" => $readingId],
    );
    $cameraStatus = "CAPTURA_PENDENTE_INCIDENTE";
}
if ($result === "EXCESSO") {
    $occurrence = $pdo->prepare(
        'INSERT INTO ocorrencias (company_id, carregamento_id, product_id, type, quantity, description, created_by) VALUES (:company_id, :carregamento_id, :product_id, \'EXCESSO\', 1, :description, :created_by)',
    );
    $occurrence->execute([
        "company_id" => $usuarioAtor["company_id"],
        "carregamento_id" => $loadingId,
        "product_id" => $productId,
        "description" => "Unidade excedente detectada após atingir a quantidade planejada.",
        "created_by" => $usuarioAtor["id"],
    ]);
    $occurrenceId = (int) $pdo->lastInsertId();
    record_operational_event(
        $pdo,
        $usuarioAtor,
        "EXCESSO_DETECTADO",
        "ocorrencia",
        $occurrenceId,
        ["carregamento_id" => (int) $loadingId, "leitura_id" => $readingId],
    );
}
if (in_array($result, ["PRODUTO_INCORRETO", "EXCESSO"], true)) {
    $stopReason = $result === "EXCESSO" ? "EXCESSO" : "PRODUTO_INCORRETO";
    $stopAlert = $result === "EXCESSO"
        ? "Quantidade planejada atingida. Remova a unidade excedente e confirme para continuar."
        : "Produto incorreto detectado. Remova a unidade e confirme para continuar.";
    $stop = $pdo->prepare(
        "UPDATE carregamentos SET state = 'PAUSADO', stop_reason = :stop_reason, operator_alert = :operator_alert WHERE id = :id AND state = 'CARREGANDO'",
    );
    $stop->execute([
        "stop_reason" => $stopReason,
        "operator_alert" => $stopAlert,
        "id" => $loadingId,
    ]);
    record_operational_event(
        $pdo,
        $usuarioAtor,
        "ESTEIRA_PAUSADA_AUTOMATICAMENTE",
        "carregamento",
        (int) $loadingId,
        ["reason" => $stopReason, "safe_physical_action" => "gateway_clp_required"],
    );
}
if (
    in_array($result, ["SEM_LEITURA", "PRODUTO_INCORRETO", "EXCESSO"], true) &&
    $sensorEventId
) {
    $cameraRequest = $pdo->prepare(
        'INSERT INTO solicitacoes_captura_camera (sensor_event_id, carregamento_id, equipment_id, reason, requested_at)
         VALUES (:sensor_event_id, :carregamento_id, :equipment_id, :reason, NOW(3))
         ON DUPLICATE KEY UPDATE reason = VALUES(reason), status = IF(status = \'ERRO\', \'PENDENTE\', status), requested_at = VALUES(requested_at)',
    );
    $cameraRequest->execute([
        "sensor_event_id" => $sensorEventId,
        "carregamento_id" => $loadingId,
        "equipment_id" => $loading["equipment_id"],
        "reason" => $result,
    ]);
    $cameraIdStatement = $pdo->prepare(
        "SELECT id FROM solicitacoes_captura_camera WHERE sensor_event_id = :sensor_event_id LIMIT 1",
    );
    $cameraIdStatement->execute(["sensor_event_id" => $sensorEventId]);
    $cameraRequestId = (int) ($cameraIdStatement->fetchColumn() ?: 0);
    if ($cameraRequestId < 1) {
        throw new RuntimeException("Solicitação de captura da câmera não encontrada após gravação.");
    }
    record_operational_event(
        $pdo,
        $usuarioAtor,
        "CAMERA_CAPTURA_SOLICITADA",
        "camera_request",
        $cameraRequestId,
        ["sensor_event_id" => $sensorEventId, "reason" => $result],
    );
    $cameraStatus = "CAPTURA_PENDENTE_INCIDENTE";
}
$loadStatus = "EM_ANDAMENTO";
if (
    $result === "VALIDO" &&
    $plannedQuantity > 0 &&
    $validCount + 1 >= $plannedQuantity
) {
    $trigger = $pdo->prepare(
        "SELECT a.id, a.comando, a.rotulo
         FROM gatilhos_dala g
         JOIN acoes_dala a ON a.id = g.acao_id AND a.visivel = 1
           AND a.company_id = g.company_id AND a.equipment_id = g.equipment_id
         WHERE g.company_id = :company_id AND g.equipment_id = :equipment_id
           AND g.evento = 'QUANTIDADE_PLANEJADA_ATINGIDA' AND g.ativo = 1
         LIMIT 1",
    );
    $trigger->execute([
        "company_id" => $usuarioAtor["company_id"],
        "equipment_id" => $loading["equipment_id"],
    ]);
    $configuredAction = $trigger->fetch();
    $stateUpdate = $pdo->prepare(
        "UPDATE carregamentos SET state = 'FINALIZANDO' WHERE id = :id AND state IN ('PREPARANDO', 'CARREGANDO', 'PAUSADO')",
    );
    $stateUpdate->execute(["id" => $loadingId]);
    if ($configuredAction) {
        $queue = $pdo->prepare(
            "INSERT INTO solicitacoes_comandos_clp (company_id, equipment_id, carregamento_id, command, requested_by)
             VALUES (:company_id, :equipment_id, :carregamento_id, :command, :requested_by)",
        );
        $queue->execute([
            "company_id" => $usuarioAtor["company_id"],
            "equipment_id" => $loading["equipment_id"],
            "carregamento_id" => $loadingId,
            "command" => $configuredAction["comando"],
            "requested_by" => $usuarioAtor["id"],
        ]);
        record_operational_event(
            $pdo,
            $usuarioAtor,
            "GATILHO_DALA_DISPARADO",
            "solicitacao_comando_clp",
            (int) $pdo->lastInsertId(),
            [
                "carregamento_id" => (int) $loadingId,
                "evento" => "QUANTIDADE_PLANEJADA_ATINGIDA",
                "acao" => $configuredAction["comando"],
                "rotulo" => $configuredAction["rotulo"],
                "gateway_clp_required" => true,
            ],
        );
    }
    record_operational_event(
        $pdo,
        $usuarioAtor,
        "QUANTIDADE_PLANEJADA_ATINGIDA",
        "carregamento",
        (int) $loadingId,
        [
            "planned_quantity" => $plannedQuantity,
            "valid_readings" => $validCount + 1,
            "gatilho_configurado" => $configuredAction["comando"] ?? null,
        ],
    );
    $loadStatus = "COMPLETO";
}
$pdo->commit();
responder_json(
    [
        "data" => [
            "id" => $readingId,
            "result" => $result,
            "barcode" => $barcode !== "" ? $barcode : null,
            "attempt_number" => $attemptNumber,
            "retry" => false,
            "final" => true,
            "occurrence_id" => $occurrenceId,
            "camera_status" => $cameraStatus,
            "load_status" => $loadStatus,
            "excesso" => $result === "EXCESSO",
            "stop_reason" => in_array($result, ["PRODUTO_INCORRETO", "EXCESSO"], true) ? $result : null,
            "operator_alert" => in_array($result, ["PRODUTO_INCORRETO", "EXCESSO"], true) ? ($stopAlert ?? null) : null,
        ],
    ],
    201,
);
