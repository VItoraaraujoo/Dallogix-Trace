<?php
declare(strict_types=1);

require_once __DIR__ . "/../configuracao/bootstrap.php";
require_once __DIR__ . "/../src/Aplicacao/RelatorioAuditoriaPdf.php";

use App\Aplicacao\RelatorioAuditoriaPdf;

$user = require_session_user();
$romaneioId = filter_var($_GET["romaneio_id"] ?? null, FILTER_VALIDATE_INT);
if ($user["company_id"] === null || !$romaneioId) {
    json_response(["error" => "Romaneio obrigatório."], 422);
}

$pdo = db();
$manifest = $pdo->prepare("SELECT r.id, r.number, r.status, r.scheduled_date, r.expedidor,
    c.name AS company_name,
    (SELECT rt.plate FROM romaneio_caminhoes rt WHERE rt.romaneio_id = r.id ORDER BY rt.id LIMIT 1) AS plate,
    (SELECT rt.driver_name FROM romaneio_caminhoes rt WHERE rt.romaneio_id = r.id ORDER BY rt.id LIMIT 1) AS driver_name
    FROM romaneios r JOIN empresas c ON c.id = r.company_id
    WHERE r.id = :id AND r.company_id = :company_id LIMIT 1");
$manifest->execute(["id" => $romaneioId, "company_id" => $user["company_id"]]);
$romaneio = $manifest->fetch();
if (!$romaneio) {
    json_response(["error" => "Romaneio não encontrado."], 404);
}
if (!in_array($romaneio["status"], ["FINALIZADO", "CANCELADO"], true)) {
    json_response(
        [
            "error" =>
                "O relatório fica disponível após a finalização ou cancelamento do romaneio.",
        ],
        409,
    );
}

$loads = $pdo->prepare('SELECT c.id, c.state, c.started_at, c.finished_at, e.name AS equipment_name, e.equipment_code
    FROM carregamentos c JOIN equipamentos e ON e.id = c.equipment_id WHERE c.romaneio_id = :id ORDER BY c.id');
$loads->execute(["id" => $romaneioId]);
$loadRows = $loads->fetchAll();
$loadIds = array_map(static fn(array $row): int => (int) $row["id"], $loadRows);
$placeholders = implode(",", array_fill(0, count($loadIds), "?"));

$items = $pdo->prepare("SELECT p.name, COALESCE(MIN(pc.barcode), p.code, '—') AS barcode, ri.planned_quantity,
    COALESCE((SELECT COUNT(*) FROM leituras l JOIN carregamentos c ON c.id = l.carregamento_id
      WHERE c.romaneio_id = ri.romaneio_id AND l.product_id = ri.product_id
        AND (ri.truck_id IS NULL OR c.truck_id = ri.truck_id)
        AND l.result = 'VALIDO'), 0) AS moved_quantity
    FROM romaneio_itens ri JOIN produtos p ON p.id = ri.product_id
    LEFT JOIN codigos_produtos pc ON pc.product_id = p.id
    WHERE ri.romaneio_id = ? GROUP BY ri.id, p.id ORDER BY ri.id");
$items->execute([$romaneioId]);
$itemRows = $items->fetchAll();

$occurrences = [];
$incidentImages = [];
if ($loadIds !== []) {
    $occurrenceQuery = $pdo->prepare("SELECT o.type, o.quantity, o.description, o.created_at, p.name AS product_name
        FROM ocorrencias o LEFT JOIN produtos p ON p.id = o.product_id WHERE o.carregamento_id IN ({$placeholders}) ORDER BY o.created_at");
    $occurrenceQuery->execute($loadIds);
    $occurrences = $occurrenceQuery->fetchAll();

    $imageQuery = $pdo->prepare("SELECT path, reason, captured_at FROM imagens WHERE carregamento_id IN ({$placeholders}) AND reason <> 'NORMAL' ORDER BY captured_at");
    $imageQuery->execute($loadIds);
    $incidentImages = $imageQuery->fetchAll();
}

$report = new RelatorioAuditoriaPdf(
    (string) $romaneio["company_name"],
    "Romaneio " . $romaneio["number"] . " · gerado em " . date("d/m/Y H:i"),
);
$report->heading("Resumo da operação");
$report->paragraph(
    "Este relatório reúne o que foi carregado, as diferenças encontradas e os incidentes registrados durante a operação.",
);
$report->summaryCards([
    "Romaneio" => $romaneio["number"],
    "Status" => $romaneio["status"] === "CANCELADO" ? "Cancelado" : "Finalizado",
    "Data programada" => $romaneio["scheduled_date"] ?: "—",
    "Expedidor" => $romaneio["expedidor"] ?: "—",
    "Placa" => $romaneio["plate"] ?: "—",
    "Motorista" => $romaneio["driver_name"] ?: "—",
    "Dalas / esteiras" =>
        implode(
            ", ",
            array_map(
                static fn(array $row): string => $row["equipment_name"] .
                    " (" .
                    $row["equipment_code"] .
                    ")",
                $loadRows,
            ),
        ) ?:
        "—",
    "Início" => $loadRows[0]["started_at"] ?? "—",
    "Fim" => $loadRows[array_key_last($loadRows)]["finished_at"] ?? "—",
]);
if ($romaneio["status"] === "CANCELADO") {
    $report->paragraph("Este romaneio foi cancelado. Os dados abaixo representam o carregamento parcial e os eventos registrados até o cancelamento.");
}
$report->heading("Conferência dos itens");
$report->table(
    ["Produto", "Código", "Previsto", "Movido", "Status"],
    array_map(static function (array $item): array {
        $planned = (int) $item["planned_quantity"];
        $moved = (int) $item["moved_quantity"];
        return [
            $item["name"],
            $item["barcode"],
            $planned,
            $moved,
            $moved === $planned
                ? "Concluído"
                : ($moved < $planned
                    ? "Parcial"
                    : "Excesso"),
        ];
    }, $itemRows),
    [205, 105, 65, 65, 75],
);
$divergences = array_values(
    array_filter(
        $itemRows,
        static fn(array $item): bool => (int) $item["planned_quantity"] !==
            (int) $item["moved_quantity"],
    ),
);
$report->heading("Divergências encontradas");
if ($divergences === []) {
    $report->paragraph("Nenhuma divergência registrada.");
} else {
    $report->table(
        ["Produto", "Código", "Movido / previsto", "Situação"],
        array_map(
            static fn(array $item): array => [
                $item["name"],
                $item["barcode"],
                $item["moved_quantity"] . " / " . $item["planned_quantity"],
                (int) $item["moved_quantity"] < (int) $item["planned_quantity"]
                    ? "Falta"
                    : "Excesso",
            ],
            $divergences,
        ),
        [215, 110, 110, 80],
    );
}
$report->heading("Ocorrências e incidentes");
if ($occurrences === []) {
    $report->paragraph("Nenhuma ocorrência registrada.");
} else {
    $report->table(
        ["Data/hora", "Tipo", "Produto", "Detalhes"],
        array_map(
            static fn(array $row): array => [
                $row["created_at"],
                $row["type"],
                $row["product_name"] ?: "—",
                $row["description"] ?: "—",
            ],
            $occurrences,
        ),
        [100, 115, 150, 150],
    );
}
$report->heading("Evidências fotográficas");
if ($incidentImages === []) {
    $report->paragraph("Nenhuma imagem de incidente registrada.");
} else {
    $storageRoot = realpath(__DIR__ . "/../../armazenamento") ?: "";
    $addedImages = 0;
    foreach ($incidentImages as $image) {
        $relativePath = ltrim((string) $image["path"], "/");
        if (str_starts_with($relativePath, "armazenamento/")) {
            $relativePath = substr($relativePath, strlen("armazenamento/"));
        }
        $absolutePath = $storageRoot !== "" ? realpath($storageRoot . "/" . $relativePath) : false;
        if (
            $absolutePath === false ||
            $storageRoot === "" ||
            !str_starts_with($absolutePath, $storageRoot . DIRECTORY_SEPARATOR)
        ) {
            continue;
        }
        if ($report->incidentImage(
            $absolutePath,
            (string) $image["reason"] . " · " . (string) $image["captured_at"],
        )) {
            $addedImages++;
        }
    }
    if ($addedImages === 0) {
        $report->paragraph("As imagens registradas não estão disponíveis em formato JPEG.");
    }
}
record_operational_event(
    $pdo,
    $user,
    "RELATORIO_AUDITORIA_BAIXADO",
    "romaneio",
    (int) $romaneioId,
    [],
);
header_remove("Content-Type");
header("Content-Type: application/pdf");
header(
    'Content-Disposition: attachment; filename="romaneio-' .
        preg_replace("/[^A-Za-z0-9_-]/", "-", (string) $romaneio["number"]) .
        '-auditoria.pdf"',
);
header("Cache-Control: no-store");
echo $report->output();
