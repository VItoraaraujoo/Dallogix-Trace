<?php
declare(strict_types=1);

namespace App\Aplicacao;

use PDO;
use RuntimeException;
use Throwable;

final class ExcecaoComandoClp extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus,
    ) {
        parent::__construct($message);
    }
}

final class ServicoComandoClp
{
    private const REVERSAL_COMMANDS = ["REVERSAO_ATIVAR", "REVERSAO_DESATIVAR"];

    private readonly PDO $connection;
    private readonly ServicoDisponibilidadeClp $disponibilidadeClp;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
        $this->disponibilidadeClp = new ServicoDisponibilidadeClp($connection);
    }

    /** @param array{id:int|string, company_id:int|string|null} $user */
    public function requestReversal(
        array $user,
        int $loadingId,
        string $command,
    ): array {
        if (!in_array($command, self::REVERSAL_COMMANDS, true)) {
            throw new ExcecaoComandoClp(
                "Carregamento e comando de reversão válido são obrigatórios.",
                422,
            );
        }
        if ($user["company_id"] === null) {
            throw new ExcecaoComandoClp(
                "Usuário sem empresa vinculada.",
                403,
            );
        }

        $this->connection->beginTransaction();
        try {
            $loading = $this->findLoading($loadingId, (int) $user["company_id"]);
            if (!$loading) {
                throw new ExcecaoComandoClp(
                    "Carregamento não encontrado para esta empresa.",
                    404,
                );
            }
            if ($loading["state"] !== "PAUSADO") {
                throw new ExcecaoComandoClp(
                    "Para alterar a reversão, pare a máquina primeiro.",
                    409,
                );
            }
            $this->disponibilidadeClp->validarComando(
                (int) $user["company_id"],
                (int) $loading["equipment_id"],
            );
            if ($this->hasPendingCommand($loadingId)) {
                throw new ExcecaoComandoClp(
                    "Já existe um comando de reversão aguardando o gateway industrial.",
                    409,
                );
            }
            $insert = $this->connection
                ->prepare("INSERT INTO solicitacoes_comandos_clp
                (company_id, equipment_id, carregamento_id, command, requested_by)
                VALUES (:company_id, :equipment_id, :carregamento_id, :command, :requested_by)");
            $insert->execute([
                "company_id" => $user["company_id"],
                "equipment_id" => $loading["equipment_id"],
                "carregamento_id" => $loadingId,
                "command" => $command,
                "requested_by" => $user["id"],
            ]);
            $requestId = (int) $this->connection->lastInsertId();
            \record_operational_event(
                $this->connection,
                $user,
                $command,
                "plc_command_request",
                $requestId,
                [
                    "command" => $command,
                    "carregamento_id" => $loadingId,
                    "equipment_id" => (int) $loading["equipment_id"],
                    "requires_clp_adapter" => true,
                ],
            );
            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            if ($exception instanceof ExcecaoComandoClp || $exception instanceof ExcecaoDisponibilidadeClp) {
                throw $exception;
            }
            error_log(
                "PLC command request could not be created: " .
                    $exception->getMessage(),
            );
            throw new ExcecaoComandoClp(
                "Não foi possível registrar a solicitação de reversão.",
                500,
            );
        }

        return [
            "command_request_id" => $requestId,
            "carregamento_id" => $loadingId,
            "equipment_id" => (int) $loading["equipment_id"],
            "command" => $command,
            "state" => $loading["state"],
            "message" =>
                ($command === "REVERSAO_ATIVAR"
                    ? "Ativação da reversão registrada."
                    : "Desativação da reversão registrada.") .
                " O gateway industrial deverá validar os intertravamentos antes de enviar o comando ao CLP.",
        ];
    }

    private function findLoading(int $loadingId, int $companyId): array|false
    {
        $statement = $this->connection->prepare(
            "SELECT id, state, equipment_id FROM carregamentos WHERE id = :id AND company_id = :company_id LIMIT 1 FOR UPDATE",
        );
        $statement->execute(["id" => $loadingId, "company_id" => $companyId]);
        return $statement->fetch();
    }

    private function hasPendingCommand(int $loadingId): bool
    {
        $statement = $this->connection
            ->prepare("SELECT id FROM solicitacoes_comandos_clp
            WHERE carregamento_id = :carregamento_id
              AND status IN ('PENDENTE', 'PROCESSANDO') LIMIT 1 FOR UPDATE");
        $statement->execute(["carregamento_id" => $loadingId]);
        return (bool) $statement->fetch();
    }
}
