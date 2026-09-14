<?php
declare(strict_types=1);

require_once __DIR__ . "/../configuracao/bootstrap.php";

$user = require_session_user();
if ($user["company_id"] === null) {
    json_response(["error" => "Usuário sem empresa vinculada."], 403);
}
$pdo = db();
$canManage = in_array($user["role"], ["ADMIN_DALLOGIX", "ADMIN_EMPRESA"], true);
$canDelete = in_array($user["role"], ["ADMIN_DALLOGIX", "ADMIN_EMPRESA"], true);

// Identificador no padrão da referência: letras minúsculas, números e underscores (máx. 30).
$validIdentifier = static function (string $code): bool {
    return (bool) preg_match('/^[a-z0-9_]{1,30}$/', $code);
};

// Verificação de comunicação com o serviço Modbus da dala.
// Alvo: gateway público do cliente + porta externa (serviço dala-modbus na edge);
// sem gateway configurado, testa a rede local do CLP diretamente.
// PENDENTE DE CONFIRMAÇÃO: protocolo/endpoint real do serviço dala-modbus — por enquanto,
// apenas conectividade TCP é verificada.
if (
    $_SERVER["REQUEST_METHOD"] === "GET" &&
    ($_GET["check"] ?? "") === "status"
) {
    $id = filter_var($_GET["id"] ?? null, FILTER_VALIDATE_INT);
    if (!$id) {
        json_response(["error" => "Dala não informada."], 422);
    }
    $statement = $pdo->prepare(
        "SELECT e.id, e.equipment_code, e.plc_ip, e.plc_port, e.external_port, s.gateway_public_ip FROM equipamentos e LEFT JOIN configuracoes_empresa s ON s.company_id = e.company_id WHERE e.id = :id AND e.company_id = :company_id LIMIT 1",
    );
    $statement->execute(["id" => $id, "company_id" => $user["company_id"]]);
    $dala = $statement->fetch();
    if (!$dala) {
        json_response(["error" => "Dala não encontrada."], 404);
    }
    $host =
        trim((string) ($dala["gateway_public_ip"] ?: "")) !== ""
            ? $dala["gateway_public_ip"]
            : $dala["plc_ip"];
    $port = (int) ($dala["external_port"] ?: $dala["plc_port"] ?: 0);
    if (!$host || $port < 1) {
        json_response([
            "data" => [
                "status" => "OFFLINE",
                "message" => "Dala sem endereço de comunicação configurado.",
            ],
        ]);
    }
    $connection = @fsockopen($host, $port, $errno, $errstr, 2.0);
    if ($connection) {
        fclose($connection);
        json_response([
            "data" => [
                "status" => "ONLINE",
                "message" => "Serviço Modbus respondeu.",
                "target" => "{$host}:{$port}",
            ],
        ]);
    }
    json_response([
        "data" => [
            "status" => "OFFLINE",
            "message" => "Não foi possível conectar ao serviço Modbus.",
            "target" => "{$host}:{$port}",
        ],
    ]);
}

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $id = filter_var($_GET["id"] ?? null, FILTER_VALIDATE_INT);
    if ($id) {
        $statement = $pdo->prepare(
            "SELECT id, equipment_code, name, plc_ip, plc_port, external_port, plc_protocol, created_at, updated_at FROM equipamentos WHERE id = :id AND company_id = :company_id LIMIT 1",
        );
        $statement->execute(["id" => $id, "company_id" => $user["company_id"]]);
        $dala = $statement->fetch();
        if (!$dala) {
            json_response(["error" => "Dala não encontrada."], 404);
        }
        json_response(["data" => $dala]);
    }
    $query = $pdo->prepare(
        "SELECT id, equipment_code, name, plc_ip, plc_port, external_port, plc_protocol, created_at, updated_at FROM equipamentos WHERE company_id = :company_id ORDER BY equipment_code",
    );
    $query->execute(["company_id" => $user["company_id"]]);
    json_response(["data" => $query->fetchAll()]);
}

$validatePayload = static function (array $payload) use (
    $validIdentifier,
): array {
    $code = trim((string) ($payload["equipment_code"] ?? ""));
    $name = trim((string) ($payload["name"] ?? ""));
    $ip = trim((string) ($payload["plc_ip"] ?? ""));
    $port = filter_var($payload["plc_port"] ?? 502, FILTER_VALIDATE_INT);
    $externalPort =
        ($payload["external_port"] ?? "") === "" ||
        $payload["external_port"] === null
            ? null
            : filter_var($payload["external_port"], FILTER_VALIDATE_INT);
    $protocol = strtoupper(
        trim((string) ($payload["plc_protocol"] ?? "MODBUS_TCP")),
    );
    if (!$validIdentifier($code)) {
        json_response(
            [
                "error" =>
                    "Identificador inválido: use letras minúsculas, números e underscores (máx. 30 caracteres).",
            ],
            422,
        );
    }
    if (
        $name === "" ||
        $ip === "" ||
        !in_array($protocol, ["MODBUS_TCP", "MODBUS_RTU"], true) ||
        $port === false ||
        $port < 1 ||
        $port > 65535 ||
        ($externalPort !== null &&
            ($externalPort === false ||
                $externalPort < 1 ||
                $externalPort > 65535))
    ) {
        json_response(
            ["error" => "Dados da Dala ou comunicação inválidos."],
            422,
        );
    }
    return [
        "code" => $code,
        "name" => $name,
        "ip" => $ip,
        "port" => $port,
        "externalPort" => $externalPort,
        "protocol" => $protocol,
    ];
};

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_csrf();
    if (!$canManage) {
        json_response(
            ["error" => "Perfil sem permissão para cadastrar Dala."],
            403,
        );
    }
    $data = $validatePayload(request_json());
    try {
        $insert = $pdo->prepare(
            "INSERT INTO equipamentos (company_id, equipment_code, name, plc_ip, plc_port, external_port, plc_protocol) VALUES (:company_id, :equipment_code, :name, :plc_ip, :plc_port, :external_port, :plc_protocol)",
        );
        $insert->execute([
            "company_id" => $user["company_id"],
            "equipment_code" => $data["code"],
            "name" => $data["name"],
            "plc_ip" => $data["ip"],
            "plc_port" => $data["port"],
            "external_port" => $data["externalPort"],
            "plc_protocol" => $data["protocol"],
        ]);
        $id = (int) $pdo->lastInsertId();
        record_operational_event(
            $pdo,
            $user,
            "DALA_CADASTRADA",
            "equipment",
            $id,
            [
                "equipment_code" => $data["code"],
                "plc_protocol" => $data["protocol"],
            ],
        );
        json_response(
            ["data" => ["id" => $id, "equipment_code" => $data["code"]]],
            201,
        );
    } catch (Throwable $exception) {
        json_response(["error" => "Identificador da Dala já cadastrado."], 409);
    }
}

if ($_SERVER["REQUEST_METHOD"] === "PUT") {
    require_csrf();
    if (!$canManage) {
        json_response(
            ["error" => "Perfil sem permissão para editar Dala."],
            403,
        );
    }
    $payload = request_json();
    $id = filter_var($payload["id"] ?? null, FILTER_VALIDATE_INT);
    if (!$id) {
        json_response(["error" => "Dala não informada."], 422);
    }
    $find = $pdo->prepare(
        "SELECT id FROM equipamentos WHERE id = :id AND company_id = :company_id LIMIT 1",
    );
    $find->execute(["id" => $id, "company_id" => $user["company_id"]]);
    if (!$find->fetch()) {
        json_response(["error" => "Dala não encontrada."], 404);
    }
    $data = $validatePayload($payload);
    try {
        $update = $pdo->prepare(
            "UPDATE equipamentos SET equipment_code = :equipment_code, name = :name, plc_ip = :plc_ip, plc_port = :plc_port, external_port = :external_port, plc_protocol = :plc_protocol WHERE id = :id",
        );
        $update->execute([
            "equipment_code" => $data["code"],
            "name" => $data["name"],
            "plc_ip" => $data["ip"],
            "plc_port" => $data["port"],
            "external_port" => $data["externalPort"],
            "plc_protocol" => $data["protocol"],
            "id" => $id,
        ]);
        record_operational_event(
            $pdo,
            $user,
            "DALA_ATUALIZADA",
            "equipment",
            $id,
            ["equipment_code" => $data["code"]],
        );
        json_response(["data" => ["updated" => true]]);
    } catch (Throwable $exception) {
        json_response(["error" => "Identificador da Dala já cadastrado."], 409);
    }
}

if ($_SERVER["REQUEST_METHOD"] === "DELETE") {
    require_csrf();
    if (!$canDelete) {
        json_response(
            ["error" => "Somente administradores podem excluir Dala."],
            403,
        );
    }
    $id = filter_var($_GET["id"] ?? null, FILTER_VALIDATE_INT);
    if (!$id) {
        json_response(["error" => "Dala não informada."], 422);
    }
    $find = $pdo->prepare(
        "SELECT id, equipment_code FROM equipamentos WHERE id = :id AND company_id = :company_id LIMIT 1",
    );
    $find->execute(["id" => $id, "company_id" => $user["company_id"]]);
    $dala = $find->fetch();
    if (!$dala) {
        json_response(["error" => "Dala não encontrada."], 404);
    }
    try {
        $pdo->beginTransaction();
        $pdo->prepare(
            "DELETE FROM status_dispositivos WHERE equipment_id = :id",
        )->execute(["id" => $id]);
        $pdo->prepare("DELETE FROM equipamentos WHERE id = :id")->execute([
            "id" => $id,
        ]);
        record_operational_event(
            $pdo,
            $user,
            "DALA_EXCLUIDA",
            "equipment",
            $id,
            ["equipment_code" => $dala["equipment_code"]],
        );
        $pdo->commit();
        json_response(["data" => ["deleted" => true]]);
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ((int) $exception->errorInfo[1] === 1451) {
            json_response(
                [
                    "error" =>
                        "Dala possui carregamentos ou eventos vinculados e não pode ser excluída.",
                ],
                409,
            );
        }
        json_response(["error" => "Não foi possível excluir a Dala."], 500);
    }
}

json_response(["error" => "Método não permitido."], 405);
