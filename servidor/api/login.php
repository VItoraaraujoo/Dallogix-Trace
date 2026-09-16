<?php

declare(strict_types=1);

require_once __DIR__ . "/../configuracao/bootstrap.php";

exigir_metodo_http(["POST"]);

$payload = ler_json_da_requisicao();
$email = strtolower(trim((string) ($payload["email"] ?? "")));
$password = (string) ($payload["password"] ?? "");

if ($email === "" || $password === "") {
    usleep(150000);
    responder_json(["error" => "Credenciais inválidas."], 401);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 254) {
    usleep(150000);
    responder_json(["error" => "Credenciais inválidas."], 401);
}

if (mb_strlen($password) < 6 || mb_strlen($password) > 128) {
    usleep(150000);
    responder_json(["error" => "Credenciais inválidas."], 401);
}

verificar_taxa_de_login($email);

$statement = obter_conexao_banco()->prepare(
    "SELECT id, company_id, name, email, password_hash, role, active, must_change_password FROM usuarios WHERE email = :email LIMIT 1",
);
$statement->execute(["email" => $email]);
$usuario = $statement->fetch();

if (
    !$usuario ||
    !(bool) $usuario["active"] ||
    !password_verify($password, $usuario["password_hash"])
) {
    usleep(200000);
    responder_json(["error" => "Credenciais inválidas."], 401);
}

registrar_login_sucesso($email);

if (password_needs_rehash($usuario["password_hash"], PASSWORD_DEFAULT)) {
    $rehash = db()->prepare(
        "UPDATE usuarios SET password_hash = :password_hash WHERE id = :id",
    );
    $rehash->execute([
        "password_hash" => password_hash($password, PASSWORD_DEFAULT),
        "id" => $usuario["id"],
    ]);
}

session_regenerate_id(true);
$_SESSION["user"] = usuario_publico($usuario);
$_SESSION["user_validated_at"] = time();

responder_json([
    "authenticated" => true,
    "user" => $_SESSION["user"],
    "csrf_token" => gerar_token_csrf(),
    "password_change_required" => (bool) $_SESSION["user"]["must_change_password"],
]);
