<?php
declare(strict_types=1);

namespace App\Aplicacao;

use PDO;
use RuntimeException;
use Throwable;

final class ExcecaoEstadoCarregamento extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus,
    ) {
        parent::__construct($message);
    }
}

final class ServicoEstadoCarregamento
{
    private const TRANSITIONS = [
        "AGUARDANDO" => ["PREPARANDO", "EMERGENCIA"],
        "PREPARANDO" => ["CARREGANDO", "PAUSADO", "EMERGENCIA"],
        "CARREGANDO" => ["PAUSADO", "FINALIZANDO", "EMERGENCIA"],
        "PAUSADO" => ["CARREGANDO", "EMERGENCIA"],
        "FINALIZANDO" => ["EMERGENCIA"],
        // A saída da emergência só pode ocorrer após ACK do gateway industrial.
        // O endpoint genérico não deve permitir que um usuário contorne esse fluxo.
        "EMERGENCIA" => [],
        "FINALIZADO" => [],
    ];

    public function __construct(private readonly PDO $connection) {}

    /** @param array{id:int|string, company_id:int|string|null, role:string} $user */
    public function change(array $user, int $loadingId, string $target): array
    {
        if ($user["company_id"] === null) {
            throw new ExcecaoEstadoCarregamento("Usuário sem empresa vinculada.", 403);
        }
        if (!array_key_exists($target, self::TRANSITIONS)) {
            throw new ExcecaoEstadoCarregamento("Carregamento e estado válido são obrigatórios.", 422);
        }
        if ($target === "FINALIZADO") {
            throw new ExcecaoEstadoCarregamento(
                "Use o encerramento do carregamento para validar capturas e divergências.",
                409,
            );
        }

        $this->connection->beginTransaction();
        try {
            $current = $this->findLoading($loadingId, (int) $user["company_id"]);
            if (!$current) {
                throw new ExcecaoEstadoCarregamento("Carregamento não encontrado para esta empresa.", 404);
            }
            if ($target === $current["state"]) {
                $this->connection->commit();
                return [
                    "id" => $loadingId,
                    "previous_state" => $current["state"],
                    "state" => $target,
                    "changed" => false,
                ];
            }
            if (!in_array($target, self::TRANSITIONS[$current["state"]], true)) {
                throw new ExcecaoEstadoCarregamento(
                    "Transição inválida: {$current["state"]} para {$target}.",
                    409,
                );
            }
            if (in_array($target, ["CARREGANDO", "PAUSADO"], true)) {
                (new ServicoDisponibilidadeClp($this->connection))->validarComando(
                    (int) $user["company_id"],
                    (int) $current["equipment_id"],
                );
            }

            $update = $this->connection->prepare(
                "UPDATE carregamentos SET state = :state
                 WHERE id = :id AND company_id = :company_id AND state = :previous_state",
            );
            $update->execute([
                "state" => $target,
                "id" => $loadingId,
                "company_id" => $user["company_id"],
                "previous_state" => $current["state"],
            ]);
            if ($update->rowCount() !== 1) {
                throw new ExcecaoEstadoCarregamento(
                    "O estado do carregamento mudou. Atualize a operação antes de tentar novamente.",
                    409,
                );
            }
            \record_operational_event(
                $this->connection,
                $user,
                "ESTADO_CARREGAMENTO_ALTERADO",
                "carregamento",
                $loadingId,
                ["previous_state" => $current["state"], "state" => $target],
            );
            $this->connection->commit();
            return [
                "id" => $loadingId,
                "previous_state" => $current["state"],
                "state" => $target,
            ];
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }

    private function findLoading(int $loadingId, int $companyId): array|false
    {
        $statement = $this->connection->prepare(
            "SELECT id, state, equipment_id FROM carregamentos
             WHERE id = :id AND company_id = :company_id LIMIT 1 FOR UPDATE",
        );
        $statement->execute(["id" => $loadingId, "company_id" => $companyId]);
        return $statement->fetch();
    }
}
