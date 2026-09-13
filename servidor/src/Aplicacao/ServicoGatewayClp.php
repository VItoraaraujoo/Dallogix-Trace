<?php
declare(strict_types=1);

namespace App\Aplicacao;

use PDO;
use RuntimeException;

final class ExcecaoGatewayClp extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus,
    ) {
        parent::__construct($message);
    }
}

final class ServicoGatewayClp
{
    private const FINAL_STATUSES = ["APLICADO", "REJEITADO", "ERRO"];

    public function __construct(private readonly PDO $connection) {}

    public function claim(int $equipmentId): ?array
    {
        if ($equipmentId <= 0) {
            throw new ExcecaoGatewayClp("equipment_id é obrigatório.", 422);
        }

        $this->connection->beginTransaction();
        try {
            $expired = $this->connection->prepare(
                "UPDATE solicitacoes_comandos_clp SET status = 'ERRO', completed_at = NOW(3), response_message = 'Tempo de confirmação do gateway expirado.' WHERE equipment_id = :equipment_id AND status = 'PROCESSANDO' AND expires_at < NOW(3)",
            );
            $expired->execute(["equipment_id" => $equipmentId]);
            $statement = $this->connection
                ->prepare("SELECT id, company_id, equipment_id, carregamento_id, command, requested_at
                FROM solicitacoes_comandos_clp
                WHERE equipment_id = :equipment_id AND status = 'PENDENTE'
                ORDER BY requested_at, id
                LIMIT 1 FOR UPDATE SKIP LOCKED");
            $statement->execute(["equipment_id" => $equipmentId]);
            $request = $statement->fetch();
            if (!$request) {
                $this->connection->commit();
                return null;
            }
            $update = $this->connection->prepare(
                "UPDATE solicitacoes_comandos_clp SET status = 'PROCESSANDO', claimed_at = NOW(3), expires_at = DATE_ADD(NOW(3), INTERVAL 2 MINUTE) WHERE id = :id",
            );
            $update->execute(["id" => $request["id"]]);
            $this->connection->commit();
            return $request;
        } catch (\Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            error_log(
                "PLC gateway could not claim command: " .
                    $exception->getMessage(),
            );
            throw new ExcecaoGatewayClp(
                "Não foi possível reservar o comando industrial.",
                500,
            );
        }
    }

    public function complete(
        int $requestId,
        string $status,
        string $message,
    ): array {
        if ($requestId <= 0 || !in_array($status, self::FINAL_STATUSES, true)) {
            throw new ExcecaoGatewayClp(
                "request_id e status final válido são obrigatórios.",
                422,
            );
        }
        if (mb_strlen($message) > 1000) {
            throw new ExcecaoGatewayClp(
                "message excede 1000 caracteres.",
                422,
            );
        }

        $statement = $this->connection->prepare(
            "SELECT id, command, carregamento_id FROM solicitacoes_comandos_clp WHERE id = :id AND status = 'PROCESSANDO' AND (expires_at IS NULL OR expires_at >= NOW(3)) LIMIT 1",
        );
        $statement->execute(["id" => $requestId]);
        $request = $statement->fetch();
        if (!$request) {
            throw new ExcecaoGatewayClp(
                "Comando não está em processamento.",
                404,
            );
        }

        $update = $this->connection->prepare(
            "UPDATE solicitacoes_comandos_clp SET status = :status, completed_at = NOW(3), response_message = :message WHERE id = :id AND status = 'PROCESSANDO' AND (expires_at IS NULL OR expires_at >= NOW(3))",
        );
        $update->execute([
            "status" => $status,
            "message" => $message === "" ? null : $message,
            "id" => $requestId,
        ]);
        if ($update->rowCount() !== 1) {
            throw new ExcecaoGatewayClp(
                "Comando já concluído ou com prazo de confirmação expirado.",
                409,
            );
        }
        if ($status === "APLICADO" && $request["command"] === "DESBLOQUEAR_MAQUINA") {
            $loading = $this->connection->prepare(
                "UPDATE carregamentos SET state = 'PREPARANDO' WHERE id = :id AND state = 'EMERGENCIA'",
            );
            $loading->execute(["id" => $request["carregamento_id"]]);
        }
        return [
            "request_id" => $requestId,
            "command" => $request["command"],
            "status" => $status,
        ];
    }
}
