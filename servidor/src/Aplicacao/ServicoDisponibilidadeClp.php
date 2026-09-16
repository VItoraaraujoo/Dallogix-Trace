<?php
declare(strict_types=1);

namespace App\Aplicacao;

use PDO;
use RuntimeException;

final class ExcecaoDisponibilidadeClp extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus,
    ) {
        parent::__construct($message);
    }
}

/**
 * Centraliza a regra de disponibilidade do CLP para comandos operacionais.
 * O CLP continua responsável pelos intertravamentos e pela segurança física.
 */
final class ServicoDisponibilidadeClp
{
    private const LIMITE_SEM_SINAL_SEGUNDOS = 3;

    public function __construct(private readonly PDO $connection) {}

    public function validarComando(int $companyId, int $equipmentId): void
    {
        $statement = $this->connection->prepare(
            "SELECT d.status, d.last_seen_at,
                    TIMESTAMPDIFF(SECOND, d.last_seen_at, NOW()) AS segundos_sem_sinal
             FROM equipamentos e
             LEFT JOIN status_dispositivos d
               ON d.equipment_id = e.id AND d.device_type = 'CLP'
             WHERE e.id = :equipment_id AND e.company_id = :company_id
             LIMIT 1",
        );
        $statement->execute([
            "equipment_id" => $equipmentId,
            "company_id" => $companyId,
        ]);
        $clp = $statement->fetch();

        $limiteSemSinal = function_exists("\\limite_sinal_clp_segundos")
            ? \limite_sinal_clp_segundos()
            : self::LIMITE_SEM_SINAL_SEGUNDOS;
        $online =
            $clp &&
            $clp["status"] === "ONLINE" &&
            $clp["last_seen_at"] !== null &&
            (int) $clp["segundos_sem_sinal"] <= $limiteSemSinal;
        if ($online) {
            return;
        }

        throw new ExcecaoDisponibilidadeClp(
            "Comunicação com o CLP indisponível há mais de {$limiteSemSinal} segundos. Novos comandos foram bloqueados; o sistema tentará reconectar automaticamente.",
            423,
        );
    }
}
