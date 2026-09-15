<?php
declare(strict_types=1);

namespace App\Aplicacao;

use PDO;
use RuntimeException;
use Throwable;

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

    public function claim(int $equipmentId, int $deviceId): ?array
    {
        if ($equipmentId <= 0 || $deviceId <= 0) {
            throw new ExcecaoGatewayClp("Equipamento e dispositivo são obrigatórios.", 422);
        }

        $this->connection->beginTransaction();
        try {
            $expired = $this->connection->prepare(
                "SELECT id, company_id, equipment_id, carregamento_id, command
                 FROM solicitacoes_comandos_clp
                 WHERE equipment_id = :equipment_id AND status = 'PROCESSANDO'
                   AND expires_at < NOW(3)
                 ORDER BY id FOR UPDATE",
            );
            $expired->execute(["equipment_id" => $equipmentId]);
            $expire = $this->connection->prepare(
                "UPDATE solicitacoes_comandos_clp
                 SET status = 'ERRO', completed_at = NOW(3),
                     response_message = 'Tempo de confirmação do gateway expirado.'
                 WHERE id = :id AND status = 'PROCESSANDO' AND expires_at < NOW(3)",
            );
            foreach ($expired->fetchAll() as $request) {
                $expire->execute(["id" => $request["id"]]);
                \record_operational_event(
                    $this->connection,
                    $this->deviceActor((int) $request["company_id"]),
                    "COMANDO_CLP_EXPIRADO",
                    "solicitacao_comando_clp",
                    (int) $request["id"],
                    [
                        "equipment_id" => (int) $request["equipment_id"],
                        "carregamento_id" => (int) $request["carregamento_id"],
                        "command" => $request["command"],
                        "device_id" => $deviceId,
                    ],
                );
            }

            $statement = $this->connection->prepare(
                "SELECT id, company_id, equipment_id, carregamento_id, command, requested_at
                 FROM solicitacoes_comandos_clp
                 WHERE equipment_id = :equipment_id AND status = 'PENDENTE'
                 ORDER BY requested_at, id
                 LIMIT 1 FOR UPDATE SKIP LOCKED",
            );
            $statement->execute(["equipment_id" => $equipmentId]);
            $request = $statement->fetch();
            if (!$request) {
                $this->connection->commit();
                return null;
            }
            $update = $this->connection->prepare(
                "UPDATE solicitacoes_comandos_clp
                 SET status = 'PROCESSANDO', claimed_at = NOW(3),
                     expires_at = DATE_ADD(NOW(3), INTERVAL 2 MINUTE),
                     claimed_by_device_id = :device_id
                 WHERE id = :id AND status = 'PENDENTE'",
            );
            $update->execute(["id" => $request["id"], "device_id" => $deviceId]);
            if ($update->rowCount() !== 1) {
                throw new ExcecaoGatewayClp("Comando já foi reservado por outro gateway.", 409);
            }
            $this->connection->commit();
            $request["claimed_by_device_id"] = $deviceId;
            return $request;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            if ($exception instanceof ExcecaoGatewayClp) {
                throw $exception;
            }
            error_log("PLC gateway could not claim command: " . $exception->getMessage());
            throw new ExcecaoGatewayClp("Não foi possível reservar o comando industrial.", 500);
        }
    }

    public function complete(
        int $requestId,
        int $deviceId,
        string $status,
        string $message,
    ): array {
        if ($requestId <= 0 || $deviceId <= 0 || !in_array($status, self::FINAL_STATUSES, true)) {
            throw new ExcecaoGatewayClp(
                "request_id, dispositivo e status final válido são obrigatórios.",
                422,
            );
        }
        if (mb_strlen($message) > 1000) {
            throw new ExcecaoGatewayClp("message excede 1000 caracteres.", 422);
        }

        $this->connection->beginTransaction();
        try {
            $statement = $this->connection->prepare(
                "SELECT id, company_id, equipment_id, command, carregamento_id
                 FROM solicitacoes_comandos_clp
                 WHERE id = :id AND claimed_by_device_id = :device_id
                   AND status = 'PROCESSANDO'
                   AND (expires_at IS NULL OR expires_at >= NOW(3))
                 LIMIT 1 FOR UPDATE",
            );
            $statement->execute(["id" => $requestId, "device_id" => $deviceId]);
            $request = $statement->fetch();
            if (!$request) {
                throw new ExcecaoGatewayClp(
                    "Comando não está reservado por este dispositivo.",
                    404,
                );
            }

            $update = $this->connection->prepare(
                "UPDATE solicitacoes_comandos_clp
                 SET status = :status, completed_at = NOW(3), response_message = :message
                 WHERE id = :id AND claimed_by_device_id = :device_id
                   AND status = 'PROCESSANDO'
                   AND (expires_at IS NULL OR expires_at >= NOW(3))",
            );
            $update->execute([
                "status" => $status,
                "message" => $message === "" ? null : $message,
                "id" => $requestId,
                "device_id" => $deviceId,
            ]);
            if ($update->rowCount() !== 1) {
                throw new ExcecaoGatewayClp(
                    "Comando já concluído ou com prazo de confirmação expirado.",
                    409,
                );
            }

            $actor = $this->deviceActor((int) $request["company_id"]);
            \record_operational_event(
                $this->connection,
                $actor,
                "COMANDO_CLP_CONCLUIDO",
                "solicitacao_comando_clp",
                $requestId,
                [
                    "equipment_id" => (int) $request["equipment_id"],
                    "carregamento_id" => (int) $request["carregamento_id"],
                    "command" => $request["command"],
                    "status" => $status,
                    "message" => $message,
                    "device_id" => $deviceId,
                ],
            );

            $stateChanged = false;
            if ($status === "APLICADO" && $request["command"] === "DESBLOQUEAR_MAQUINA") {
                $latest = $this->connection->prepare(
                    "SELECT id FROM solicitacoes_comandos_clp
                     WHERE carregamento_id = :carregamento_id AND command = 'DESBLOQUEAR_MAQUINA'
                     ORDER BY id DESC LIMIT 1",
                );
                $latest->execute(["carregamento_id" => $request["carregamento_id"]]);
                // Um ACK antigo nunca pode liberar uma emergência mais recente.
                if ((int) $latest->fetchColumn() !== $requestId) {
                    $this->connection->commit();
                    return ["request_id" => $requestId, "command" => $request["command"], "status" => $status, "state_changed" => false, "stale" => true];
                }
                $loading = $this->connection->prepare(
                    "UPDATE carregamentos SET state = 'PREPARANDO'
                     WHERE id = :id AND company_id = :company_id AND state = 'EMERGENCIA'",
                );
                $loading->execute([
                    "id" => $request["carregamento_id"],
                    "company_id" => $request["company_id"],
                ]);
                $stateChanged = $loading->rowCount() === 1;
                if ($stateChanged) {
                    \record_operational_event(
                        $this->connection,
                        $actor,
                        "ESTADO_CARREGAMENTO_ALTERADO",
                        "carregamento",
                        (int) $request["carregamento_id"],
                        [
                            "previous_state" => "EMERGENCIA",
                            "state" => "PREPARANDO",
                            "command_request_id" => $requestId,
                            "command" => $request["command"],
                            "device_id" => $deviceId,
                        ],
                    );
                }
            }
            $this->connection->commit();
            return [
                "request_id" => $requestId,
                "command" => $request["command"],
                "status" => $status,
                "state_changed" => $stateChanged,
            ];
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            if ($exception instanceof ExcecaoGatewayClp) {
                throw $exception;
            }
            error_log("PLC gateway could not complete command: " . $exception->getMessage());
            throw new ExcecaoGatewayClp("Não foi possível concluir o comando industrial.", 500);
        }
    }

    /** @return array{id:null, company_id:int} */
    private function deviceActor(int $companyId): array
    {
        return ["id" => null, "company_id" => $companyId];
    }
}
