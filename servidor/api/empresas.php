<?php

declare(strict_types=1);

require_once __DIR__ . "/../configuracao/bootstrap.php";

exigir_metodo_http(["GET", "POST", "DELETE"]);

$usuarioAtor = exigir_sessao_usuario();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if ($usuarioAtor["role"] !== "ADMIN_DALLOGIX") {
        responder_json(
            ["error" => "Somente o Administrador Dallogix pode criar empresas."],
            403,
        );
    }

    exigir_csrf();
    $payload = ler_json_da_requisicao();
    $nomeEmpresa = trim((string) ($payload["name"] ?? ""));
    if ($nomeEmpresa === "" || mb_strlen($nomeEmpresa) > 160) {
        responder_json(["error" => "Informe o nome da empresa."], 422);
    }

    try {
        $pdo = obter_conexao_banco();
        $empresaExistente = $pdo->prepare(
            "SELECT id FROM empresas WHERE name = :name LIMIT 1",
        );
        $empresaExistente->execute(["name" => $nomeEmpresa]);
        if ($empresaExistente->fetch()) {
            responder_json(["error" => "Já existe uma empresa com este nome."], 409);
        }

        $insercao = $pdo->prepare("INSERT INTO empresas (name) VALUES (:name)");
        $insercao->execute(["name" => $nomeEmpresa]);
        responder_json(
            ["data" => ["id" => (int) $pdo->lastInsertId(), "name" => $nomeEmpresa]],
            201,
        );
    } catch (PDOException $exception) {
        responder_json(["error" => "Não foi possível criar a empresa."], 409);
    }
}

if ($_SERVER["REQUEST_METHOD"] === "DELETE") {
    if ($usuarioAtor["role"] !== "ADMIN_DALLOGIX") {
        json_response(
            ["error" => "Somente o Administrador Dallogix pode remover empresas."],
            403,
        );
    }

    require_csrf();
    $id = filter_var($_GET["id"] ?? null, FILTER_VALIDATE_INT);
    if (!$id) {
        json_response(["error" => "Empresa não informada."], 422);
    }

    $pdo = db();
    $find = $pdo->prepare("SELECT id, name FROM empresas WHERE id = :id LIMIT 1");
    $find->execute(["id" => $id]);
    $empresa = $find->fetch();
    if (!$empresa) {
        json_response(["error" => "Empresa não encontrada."], 404);
    }

    $dependencias = [
        "usuarios" => "SELECT COUNT(*) FROM usuarios WHERE company_id = :id",
        "equipamentos" => "SELECT COUNT(*) FROM equipamentos WHERE company_id = :id",
        "produtos" => "SELECT COUNT(*) FROM produtos WHERE company_id = :id",
        "romaneios" => "SELECT COUNT(*) FROM romaneios WHERE company_id = :id",
        "carregamentos" => "SELECT COUNT(*) FROM carregamentos WHERE company_id = :id",
        "ocorrências" => "SELECT COUNT(*) FROM ocorrencias WHERE company_id = :id",
        "auditoria" => "SELECT COUNT(*) FROM logs_auditoria WHERE company_id = :id",
        "configurações" => "SELECT COUNT(*) FROM configuracoes_empresa WHERE company_id = :id",
        "licenças" => "SELECT COUNT(*) FROM licencas WHERE company_id = :id",
        "comandos industriais" => "SELECT COUNT(*) FROM solicitacoes_comandos_clp WHERE company_id = :id",
    ];

    $encontradas = [];
    foreach ($dependencias as $label => $query) {
        $statement = $pdo->prepare($query);
        $statement->execute(["id" => $id]);
        if ((int) $statement->fetchColumn() > 0) {
            $encontradas[] = $label;
        }
    }

    if ($encontradas) {
        json_response(
            [
                "error" => "A empresa não pode ser removida porque possui dados vinculados: " . implode(", ", $encontradas) . ".",
            ],
            409,
        );
    }

    try {
        $delete = $pdo->prepare("DELETE FROM empresas WHERE id = :id");
        $delete->execute(["id" => $id]);
        json_response(["data" => ["deleted" => true, "name" => $empresa["name"]]]);
    } catch (PDOException $exception) {
        json_response(["error" => "Não foi possível remover a empresa com segurança."], 409);
    }
}

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    json_response(["error" => "Método não permitido."], 405);
}

$isAdminDallogix = $usuarioAtor["role"] === "ADMIN_DALLOGIX";
$rawCompanyId = trim((string) ($_GET["company_id"] ?? ""));
if ($rawCompanyId !== "" && filter_var($rawCompanyId, FILTER_VALIDATE_INT) === false) {
    json_response(["error" => "Identificador de empresa inválido."], 422);
}
$requestedCompanyId = $rawCompanyId === "" ? null : (int) $rawCompanyId;

if (!$isAdminDallogix) {
    if (
        $usuarioAtor["company_id"] === null ||
        ($requestedCompanyId !== null && $requestedCompanyId !== (int) $usuarioAtor["company_id"])
    ) {
        json_response(
            ["error" => "Perfil sem permissão para consultar esta empresa."],
            403,
        );
    }
    $requestedCompanyId = (int) $usuarioAtor["company_id"];
}

$pdo = db();

$machinesSql = "SELECT e.id, e.equipment_code, e.name,
            d.status AS clp_status, d.last_seen_at,
            c.state AS carregamento_state, r.number AS romaneio_number, rt.plate,
            COALESCE((SELECT SUM(ri.planned_quantity) FROM romaneio_itens ri WHERE ri.romaneio_id = c.romaneio_id AND (ri.truck_id = c.truck_id OR ri.truck_id IS NULL)), 0) AS planned_quantity,
            COALESCE(c.leituras_validas, 0) AS valid_readings
     FROM equipamentos e
     LEFT JOIN status_dispositivos d ON d.equipment_id = e.id AND d.device_type = 'CLP'
     LEFT JOIN carregamentos c ON c.id = (SELECT c2.id FROM carregamentos c2 WHERE c2.equipment_id = e.id ORDER BY c2.id DESC LIMIT 1)
     LEFT JOIN romaneios r ON r.id = c.romaneio_id
     LEFT JOIN romaneio_caminhoes rt ON rt.id = c.truck_id
     WHERE e.company_id = :company_id
     ORDER BY e.equipment_code";

if ($requestedCompanyId !== null) {
    $companyStatement = $pdo->prepare(
        "SELECT id, name, created_at FROM empresas WHERE id = :id LIMIT 1",
    );
    $companyStatement->execute(["id" => $requestedCompanyId]);
    $empresa = $companyStatement->fetch();
    if (!$empresa) {
        json_response(["error" => "Empresa não encontrada."], 404);
    }

    $maquinas = $pdo->prepare($machinesSql);
    $maquinas->execute(["company_id" => $requestedCompanyId]);

    $ocorrencias = $pdo->prepare(
        "SELECT type, quantity, description, created_at FROM ocorrencias WHERE company_id = :company_id ORDER BY id DESC LIMIT 10",
    );
    $ocorrencias->execute(["company_id" => $requestedCompanyId]);

    $romaneios = $pdo->prepare(
        "SELECT status, COUNT(*) AS total FROM romaneios WHERE company_id = :company_id GROUP BY status",
    );
    $romaneios->execute(["company_id" => $requestedCompanyId]);
    $resumoRomaneios = [];
    foreach ($romaneios->fetchAll() as $row) {
        $resumoRomaneios[$row["status"]] = (int) $row["total"];
    }

    $sincronizacaoPendente = $pdo->prepare(
        "SELECT COUNT(*) AS total FROM fila_sincronizacao q WHERE q.company_id = :company_id AND q.status IN ('PENDENTE', 'ERRO', 'PROCESSANDO')",
    );
    $sincronizacaoPendente->execute(["company_id" => $requestedCompanyId]);

    json_response([
        "data" => [
            "id" => (int) $empresa["id"],
            "name" => $empresa["name"],
            "created_at" => $empresa["created_at"],
            "maquinas" => $maquinas->fetchAll(),
            "ocorrencias_recentes" => $ocorrencias->fetchAll(),
            "romaneios" => $resumoRomaneios,
            "sync_pendente" => (int) ($sincronizacaoPendente->fetch()["total"] ?? 0),
        ],
    ]);
}

$empresas = $pdo->prepare(
    "SELECT c.id, c.name, c.created_at,
            (SELECT l.status FROM licencas l WHERE l.company_id = c.id ORDER BY l.id DESC LIMIT 1) AS license_status,
            (SELECT l.blocked_reason FROM licencas l WHERE l.company_id = c.id ORDER BY l.id DESC LIMIT 1) AS license_reason,
            COUNT(e.id) AS total_machines,
            COALESCE(SUM(d.status = 'ONLINE'), 0) AS machines_online,
            MAX(d.last_seen_at) AS last_signal_at,
            (SELECT COUNT(*) FROM usuarios u WHERE u.company_id = c.id) AS total_users,
            (SELECT COUNT(*) FROM usuarios u WHERE u.company_id = c.id AND u.active = 1) AS active_users,
            (SELECT COUNT(*) FROM ocorrencias o WHERE o.company_id = c.id AND o.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) AS ocorrencias_24h
     FROM empresas c
     LEFT JOIN equipamentos e ON e.company_id = c.id
     LEFT JOIN status_dispositivos d ON d.equipment_id = e.id AND d.device_type = 'CLP'
     GROUP BY c.id, c.name, c.created_at
     ORDER BY c.name",
);
$empresas->execute();
$rows = array_map(static function (array $row): array {
    $total = (int) $row["total_machines"];
    $online = (int) $row["machines_online"];
    $licenseStatus = $row["license_status"] ?: "SEM_LICENCA";
    return [
        "id" => (int) $row["id"],
        "name" => $row["name"],
        "created_at" => $row["created_at"],
        "total_machines" => $total,
        "machines_online" => $online,
        "machines_offline" => max(0, $total - $online),
        "last_signal_at" => $row["last_signal_at"],
        "total_users" => (int) $row["total_users"],
        "active_users" => (int) $row["active_users"],
        "ocorrencias_24h" => (int) $row["ocorrencias_24h"],
        "license_status" => $licenseStatus,
        "license_reason" => $row["license_reason"],
    ];
}, $empresas->fetchAll());

json_response(["data" => $rows]);
