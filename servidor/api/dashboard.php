<?php
declare(strict_types=1);

require_once __DIR__ . "/../configuracao/bootstrap.php";

// Métricas do dashboard no padrão da referência TracePlatform:
// total de romaneios, operações finalizadas, ocorrências, taxa de divergência e tempo médio de operação.
$user = require_session_user();
if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    json_response(["error" => "Método não permitido."], 405);
}
if ($user["company_id"] === null) {
    json_response(["error" => "Usuário sem empresa vinculada."], 403);
}

$pdo = db();
$companyId = $user["company_id"];

$count = static function (string $sql, array $params) use ($pdo): int {
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return (int) ($statement->fetchColumn() ?: 0);
};

$totalRomaneios = $count(
    "SELECT COUNT(*) FROM romaneios WHERE company_id = :company_id",
    ["company_id" => $companyId],
);
$operacoesFinalizadas = $count(
    "SELECT COUNT(*) FROM romaneios WHERE company_id = :company_id AND status = 'FINALIZADO'",
    ["company_id" => $companyId],
);
$totalOcorrencias = $count(
    "SELECT COUNT(*) FROM ocorrencias WHERE company_id = :company_id",
    ["company_id" => $companyId],
);

// Divergência: romaneio finalizado cuja contagem de leituras válidas difere do programado
// ou que registrou ocorrências durante o carregamento.
$divergenciaStatement = $pdo->prepare(
    "SELECT COUNT(*) FROM (
        SELECT r.id
        FROM romaneios r
        WHERE r.company_id = :company_id AND r.status = 'FINALIZADO'
          AND (
            COALESCE((SELECT SUM(ri.planned_quantity) FROM romaneio_itens ri WHERE ri.romaneio_id = r.id), 0)
              <> COALESCE((SELECT COUNT(*) FROM leituras l JOIN carregamentos c2 ON c2.id = l.carregamento_id WHERE c2.romaneio_id = r.id AND l.result = 'VALIDO'), 0)
            OR EXISTS (SELECT 1 FROM ocorrencias o WHERE o.carregamento_id IN (SELECT c3.id FROM carregamentos c3 WHERE c3.romaneio_id = r.id))
          )
     ) divergentes",
);
$divergenciaStatement->execute(["company_id" => $companyId]);
$divergentes = (int) ($divergenciaStatement->fetchColumn() ?: 0);
$taxaDivergencia =
    $operacoesFinalizadas > 0
        ? round(($divergentes / $operacoesFinalizadas) * 100, 1)
        : 0.0;

$mediaStatement = $pdo->prepare(
    'SELECT COALESCE(AVG(TIMESTAMPDIFF(MINUTE, started_at, finished_at)), 0) FROM carregamentos WHERE company_id = :company_id AND state = \'FINALIZADO\' AND started_at IS NOT NULL AND finished_at IS NOT NULL',
);
$mediaStatement->execute(["company_id" => $companyId]);
$tempoMedio = (int) round((float) ($mediaStatement->fetchColumn() ?: 0));

json_response([
    "data" => [
        "total_romaneios" => $totalRomaneios,
        "operacoes_finalizadas" => $operacoesFinalizadas,
        "total_ocorrencias" => $totalOcorrencias,
        "taxa_divergencia" => $taxaDivergencia,
        "tempo_medio_operacao" => $tempoMedio,
    ],
]);
