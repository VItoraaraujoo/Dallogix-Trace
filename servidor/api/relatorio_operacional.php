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

$parseDate = static function (string $value, string $label): ?string {
    $value = trim($value);
    if ($value === "") {
        return null;
    }
    $date = DateTime::createFromFormat("Y-m-d", $value);
    if (!$date || $date->format("Y-m-d") !== $value) {
        json_response(["error" => "{$label} inválida."], 422);
    }
    return $value;
};
$dateFrom = $parseDate((string) ($_GET["date_from"] ?? ""), "Data inicial");
$dateTo = $parseDate((string) ($_GET["date_to"] ?? ""), "Data final");
$status = strtoupper(trim((string) ($_GET["status"] ?? "")));
$allowedStatuses = ["AGUARDANDO", "EM_ANDAMENTO", "FINALIZADO", "CANCELADO"];
if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
    json_response(
        ["error" => "Data inicial não pode ser posterior à data final."],
        422,
    );
}
if ($status !== "" && !in_array($status, $allowedStatuses, true)) {
    json_response(["error" => "Status inválido."], 422);
}

$conditions = ["r.company_id = :company_id"];
$params = ["company_id" => $user["company_id"]];
if ($dateFrom !== null) {
    $conditions[] = "r.scheduled_date >= :date_from";
    $params["date_from"] = $dateFrom;
}
if ($dateTo !== null) {
    $conditions[] = "r.scheduled_date <= :date_to";
    $params["date_to"] = $dateTo;
}
if ($status !== "") {
    $conditions[] = "r.status = :status";
    $params["status"] = $status;
}

$pdo = db();
$statement = $pdo->prepare(
    "SELECT r.id, r.number, r.scheduled_date, r.status, r.expedidor,
            MAX(rt.plate) AS plate,
            COALESCE((SELECT SUM(ri2.planned_quantity) FROM romaneio_itens ri2 WHERE ri2.romaneio_id = r.id), 0) AS planned_quantity,
            COALESCE((SELECT COUNT(*) FROM leituras l JOIN carregamentos cx ON cx.id = l.carregamento_id WHERE cx.romaneio_id = r.id AND l.result = 'VALIDO'), 0) AS loaded_quantity,
            COALESCE((SELECT COUNT(*) FROM leituras l JOIN carregamentos cx ON cx.id = l.carregamento_id WHERE cx.romaneio_id = r.id AND l.result = 'SEM_LEITURA'), 0) AS no_readings,
            COALESCE((SELECT COUNT(*) FROM leituras l JOIN carregamentos cx ON cx.id = l.carregamento_id WHERE cx.romaneio_id = r.id AND l.result = 'PRODUTO_INCORRETO'), 0) AS wrong_products,
            COALESCE((SELECT COUNT(*) FROM ocorrencias o JOIN carregamentos cx ON cx.id = o.carregamento_id WHERE cx.romaneio_id = r.id), 0) AS occurrences,
            c.state AS loading_state, c.started_at, c.finished_at,
            CASE WHEN c.started_at IS NOT NULL AND c.finished_at IS NOT NULL THEN TIMESTAMPDIFF(MINUTE, c.started_at, c.finished_at) ELSE NULL END AS duration_minutes
     FROM romaneios r
     LEFT JOIN romaneio_caminhoes rt ON rt.romaneio_id = r.id
     LEFT JOIN romaneio_itens ri ON ri.romaneio_id = r.id
     LEFT JOIN carregamentos c ON c.id = (SELECT c2.id FROM carregamentos c2 WHERE c2.romaneio_id = r.id ORDER BY c2.id DESC LIMIT 1)
     WHERE " .
        implode(" AND ", $conditions) .
        "
     GROUP BY r.id, c.id
     ORDER BY r.scheduled_date DESC, r.id DESC",
);
$statement->execute($params);
$rows = $statement->fetchAll();

$summary = [
    "romaneios" => count($rows),
    "planejado" => 0,
    "carregado" => 0,
    "ocorrencias" => 0,
    "sem_leitura" => 0,
    "produto_incorreto" => 0,
    "divergentes" => 0,
];
foreach ($rows as &$row) {
    foreach (
        [
            "planned_quantity",
            "loaded_quantity",
            "no_readings",
            "wrong_products",
            "occurrences",
        ]
        as $field
    ) {
        $row[$field] = (int) $row[$field];
    }
    $row["duration_minutes"] =
        $row["duration_minutes"] === null
            ? null
            : (int) $row["duration_minutes"];
    $row["has_divergence"] =
        $row["status"] === "FINALIZADO" &&
        ($row["planned_quantity"] !== $row["loaded_quantity"] ||
            $row["occurrences"] > 0)
            ? 1
            : 0;
    $summary["planejado"] += $row["planned_quantity"];
    $summary["carregado"] += $row["loaded_quantity"];
    $summary["ocorrencias"] += $row["occurrences"];
    $summary["sem_leitura"] += $row["no_readings"];
    $summary["produto_incorreto"] += $row["wrong_products"];
    $summary["divergentes"] += $row["has_divergence"];
}
unset($row);
json_response([
    "data" => [
        "filters" => [
            "date_from" => $dateFrom,
            "date_to" => $dateTo,
            "status" => $status,
        ],
        "summary" => $summary,
        "rows" => $rows,
    ],
]);
