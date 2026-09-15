<?php

declare(strict_types=1);

require_once __DIR__ . "/../configuracao/bootstrap.php";

$usuarioAtor = exigir_sessao_usuario();
$eAdministradorPlataforma = $usuarioAtor["role"] === "ADMIN_DALLOGIX";
$eAdministradorEmpresa = $usuarioAtor["role"] === "ADMIN_EMPRESA";
if (!$eAdministradorPlataforma && !$eAdministradorEmpresa) {
    responder_json(["error" => "Perfil sem permissão para gerenciar logins."], 403);
}

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    exigir_metodo_http(["GET"]);
}
if ($_SERVER["REQUEST_METHOD"] === "PUT") {
    exigir_metodo_http(["PUT"]);
}
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    exigir_metodo_http(["POST"]);
}

$resolverIdEmpresa = static function (array $entrada) use ($usuarioAtor, $eAdministradorPlataforma): int {
    $empresaSolicitada = filter_var($entrada["company_id"] ?? null, FILTER_VALIDATE_INT);
    if ($eAdministradorPlataforma) {
        if (!$empresaSolicitada) {
            responder_json(["error" => "Selecione a empresa do login."], 422);
        }
        return (int) $empresaSolicitada;
    }

    if ($usuarioAtor["company_id"] === null) {
        responder_json(["error" => "Administrador sem empresa vinculada."], 403);
    }

    return (int) $usuarioAtor["company_id"];
};

$pdo = obter_conexao_banco();
$ator = $usuarioAtor;

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $empresaId = $resolverIdEmpresa($_GET);
    $empresaExiste = $pdo->prepare("SELECT id FROM empresas WHERE id = :id LIMIT 1");
    $empresaExiste->execute(["id" => $empresaId]);
    if (!$empresaExiste->fetch()) {
        json_response(["error" => "Empresa não encontrada."], 404);
    }

    $statement = $pdo->prepare(
        "SELECT id, name, email, role, active, created_at, updated_at FROM usuarios WHERE company_id = :company_id ORDER BY name",
    );
    $statement->execute(["company_id" => $empresaId]);
    json_response(["data" => $statement->fetchAll()]);
}

if ($_SERVER["REQUEST_METHOD"] === "PUT") {
    require_csrf();
    $payload = request_json();
    $empresaId = $resolverIdEmpresa($payload);

    $id = filter_var($payload["id"] ?? null, FILTER_VALIDATE_INT);
    $nome = trim((string) ($payload["name"] ?? ""));
    $perfil = strtoupper(trim((string) ($payload["role"] ?? "")));
    $ativo = filter_var(
        $payload["active"] ?? null,
        FILTER_VALIDATE_BOOLEAN,
        FILTER_NULL_ON_FAILURE,
    );
    $senha = (string) ($payload["password"] ?? "");

    $perfisPermitidos = $eAdministradorPlataforma
        ? ["ADMIN_EMPRESA", "SUPERVISOR", "USUARIO"]
        : ["SUPERVISOR", "USUARIO"];

    if (
        !$id ||
        $nome === "" ||
        mb_strlen($nome) > 160 ||
        !in_array($perfil, $perfisPermitidos, true) ||
        $ativo === null ||
        ($senha !== "" && strlen($senha) < 10)
    ) {
        json_response(
            [
                "error" => "Dados de atualização inválidos. A nova senha deve ter ao menos 10 caracteres.",
            ],
            422,
        );
    }

    $usuarioAlvo = $pdo->prepare(
        "SELECT id, role, active FROM usuarios WHERE id = :id AND company_id = :company_id LIMIT 1",
    );
    $usuarioAlvo->execute(["id" => $id, "company_id" => $empresaId]);
    $usuarioExistente = $usuarioAlvo->fetch();
    if (!$usuarioExistente) {
        json_response(["error" => "Login não encontrado para esta empresa."], 404);
    }
    if (!$eAdministradorPlataforma && !in_array($usuarioExistente["role"], ["SUPERVISOR", "USUARIO"], true)) {
        json_response(["error" => "Somente o Master pode alterar contas administrativas."], 403);
    }

    if ((int) $id === (int) $ator["id"] && (!$ativo || $perfil !== $usuarioExistente["role"])) {
        json_response(["error" => "Você não pode desativar o próprio acesso."], 409);
    }

    $sql = "UPDATE usuarios SET name = :name, role = :role, active = :active";
    $params = [
        "name" => $nome,
        "role" => $perfil,
        "active" => $ativo ? 1 : 0,
        "id" => $id,
    ];

    if ($senha !== "") {
        $sql .= ", password_hash = :password_hash, must_change_password = 1";
        $params["password_hash"] = password_hash($senha, PASSWORD_DEFAULT);
    }

    $sql .= " WHERE id = :id";
    $pdo->beginTransaction();
    try {
        $pdo->prepare($sql)->execute($params);
        record_operational_event(
            $pdo,
            $ator,
            "USUARIO_ATUALIZADO",
            "user",
            (int) $id,
            [
                "company_id" => $empresaId,
                "role" => $perfil,
                "active" => $ativo ? 1 : 0,
                "password_reset" => $senha !== "",
            ],
        );
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    json_response(["data" => ["id" => (int) $id, "updated" => true]]);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    json_response(["error" => "Método não permitido."], 405);
}

require_csrf();
$payload = request_json();
$empresaId = $resolverIdEmpresa($payload);
$nome = trim((string) ($payload["name"] ?? ""));
$emailPrefix = strtolower(trim((string) ($payload["email_prefix"] ?? "")));
$emailDomain = strtolower(trim((string) (getenv("LOGIN_EMAIL_DOMAIN") ?: "dallogix.local")));
$email = $emailPrefix . "@" . $emailDomain;
$senha = (string) ($payload["password"] ?? "");
$perfil = strtoupper(trim((string) ($payload["role"] ?? "")));

$perfisPermitidos = $eAdministradorPlataforma
    ? ["ADMIN_EMPRESA", "SUPERVISOR", "USUARIO"]
    : ["SUPERVISOR", "USUARIO"];

if (
    $nome === "" ||
    mb_strlen($nome) > 160 ||
    !preg_match('/^[a-z0-9][a-z0-9._-]{2,63}$/', $emailPrefix) ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    strlen($senha) < 10 ||
    !in_array($perfil, $perfisPermitidos, true)
) {
    json_response(
        [
            "error" => "Informe nome, início do e-mail com 3 a 64 caracteres, senha de no mínimo 10 caracteres e um perfil permitido.",
        ],
        422,
    );
}

$empresaExiste = $pdo->prepare("SELECT id FROM empresas WHERE id = :id LIMIT 1");
$empresaExiste->execute(["id" => $empresaId]);
if (!$empresaExiste->fetch()) {
    json_response(["error" => "Empresa não encontrada."], 404);
}

try {
    $pdo->beginTransaction();
    $insert = $pdo->prepare(
        "INSERT INTO usuarios (company_id, name, email, password_hash, role) VALUES (:company_id, :name, :email, :password_hash, :role)",
    );
    $insert->execute([
        "company_id" => $empresaId,
        "name" => $nome,
        "email" => $email,
        "password_hash" => password_hash($senha, PASSWORD_DEFAULT),
        "role" => $perfil,
    ]);

    $id = (int) $pdo->lastInsertId();
    record_operational_event($pdo, $ator, "USUARIO_CRIADO", "user", $id, [
        "company_id" => $empresaId,
        "role" => $perfil,
    ]);
    $pdo->commit();

    json_response(
        [
            "data" => [
                "id" => $id,
                "name" => $nome,
                "email" => $email,
                "role" => $perfil,
            ],
        ],
        201,
    );
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(["error" => "Já existe um login com este e-mail."], 409);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}
