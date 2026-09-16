<?php
declare(strict_types=1);

namespace App\Aplicacao;

use PDO;

final class ServicoLeituras
{
    public function __construct(private readonly PDO $connection) {}

    public function listarPendentes(int $companyId, int $loadingId): array
    {
        $statement = $this->connection->prepare(
            "SELECT l.id, l.carregamento_id, l.read_at, l.result
             FROM leituras l JOIN carregamentos c ON c.id = l.carregamento_id
             WHERE l.carregamento_id = :loading_id AND c.company_id = :company_id
               AND l.result = 'SEM_LEITURA' ORDER BY l.id DESC LIMIT 20",
        );
        $statement->execute(["loading_id" => $loadingId, "company_id" => $companyId]);
        return $statement->fetchAll();
    }
}
