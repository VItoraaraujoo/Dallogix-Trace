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

$pdo = db();
$companyId = $user["company_id"];
$query = static function (PDO $pdo, string $sql, array $params): array {
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll();
};

$romaneios = $query(
    $pdo,
    "SELECT status, COUNT(*) AS total FROM romaneios WHERE company_id = :company_id GROUP BY status",
    ["company_id" => $companyId],
);
$readings = $query(
    $pdo,
    "SELECT l.result, COUNT(*) AS total FROM leituras l JOIN carregamentos c ON c.id = l.carregamento_id WHERE c.company_id = :company_id GROUP BY l.result",
    ["company_id" => $companyId],
);
$lastReading = $query(
    $pdo,
    "SELECT l.barcode, l.result, l.read_at FROM leituras l JOIN carregamentos c ON c.id = l.carregamento_id WHERE c.company_id = :company_id ORDER BY l.id DESC LIMIT 1",
    ["company_id" => $companyId],
);
$occurrences = $query(
    $pdo,
    "SELECT o.id, o.type, o.quantity, o.description, o.created_at FROM ocorrencias o WHERE o.company_id = :company_id ORDER BY o.id DESC LIMIT 10",
    ["company_id" => $companyId],
);
$audit = $query(
    $pdo,
    "SELECT action, entity_type, entity_id, created_at FROM logs_auditoria WHERE company_id = :company_id ORDER BY id DESC LIMIT 10",
    ["company_id" => $companyId],
);
$pendingSync = $query(
    $pdo,
    "SELECT COUNT(*) AS total FROM fila_sincronizacao q WHERE q.company_id = :company_id AND q.status IN ('PENDENTE', 'ERRO', 'PROCESSANDO')",
    ["company_id" => $companyId],
);
$devices = $query(
    $pdo,
    "SELECT d.equipment_id, d.device_type,
            CASE
                WHEN d.status = 'ONLINE' AND d.last_seen_at >= DATE_SUB(NOW(), INTERVAL 3 SECOND) THEN 'ONLINE'
                WHEN d.status = 'ERRO' THEN 'ERRO'
                ELSE 'OFFLINE'
            END AS status,
            d.last_seen_at,
            CASE WHEN d.last_seen_at IS NULL THEN NULL ELSE TIMESTAMPDIFF(SECOND, d.last_seen_at, NOW()) END AS segundos_sem_sinal,
            e.equipment_code
     FROM status_dispositivos d
     JOIN equipamentos e ON e.id = d.equipment_id
     WHERE e.company_id = :company_id
     ORDER BY e.equipment_code, d.device_type",
    ["company_id" => $companyId],
);
$maquinas = $query(
    $pdo,
    "SELECT e.id, e.equipment_code, e.name,
        d.status AS clp_status, d.last_seen_at,
        c.id AS carregamento_id, c.state AS carregamento_state, r.id AS romaneio_id, r.number AS romaneio_number, rt.plate,
        COALESCE((SELECT SUM(ri.planned_quantity) FROM romaneio_itens ri WHERE ri.romaneio_id = c.romaneio_id AND (ri.truck_id = c.truck_id OR ri.truck_id IS NULL)), 0) AS planned_quantity,
        COALESCE(c.leituras_validas, 0) AS valid_readings
 FROM equipamentos e
 LEFT JOIN status_dispositivos d ON d.equipment_id = e.id AND d.device_type = 'CLP'
 LEFT JOIN carregamentos c ON c.id = (SELECT c2.id FROM carregamentos c2 WHERE c2.equipment_id = e.id ORDER BY c2.id DESC LIMIT 1)
 LEFT JOIN romaneios r ON r.id = c.romaneio_id
 LEFT JOIN romaneio_caminhoes rt ON rt.id = c.truck_id
 WHERE e.company_id = :company_id
 ORDER BY e.equipment_code",
    ["company_id" => $companyId],
);

$romaneioSummary = [];
foreach ($romaneios as $row) {
    $romaneioSummary[$row["status"]] = (int) $row["total"];
}
$readingSummary = [];
foreach ($readings as $row) {
    $readingSummary[$row["result"]] = (int) $row["total"];
}

json_response([
    "data" => [
        "romaneios" => $romaneioSummary,
        "leituras" => $readingSummary,
        "ultima_leitura" => $lastReading[0] ?? null,
        "ocorrencias" => $occurrences,
        "auditoria" => $audit,
        "sync_pendente" => (int) ($pendingSync[0]["total"] ?? 0),
        "dispositivos" => $devices,
        "maquinas" => $maquinas,
    ],
]);
