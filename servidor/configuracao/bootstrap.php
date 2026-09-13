<?php

declare(strict_types=1);

function ambiente_atual(): string
{
    return strtolower(trim((string) (getenv("APP_ENV") ?: "local")));
}

$configuredTimezone = trim((string) (getenv("TZ") ?: "America/Sao_Paulo"));
if ($configuredTimezone !== "") {
    date_default_timezone_set($configuredTimezone);
}

header("Content-Type: application/json; charset=utf-8");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: no-referrer");
header("Cross-Origin-Resource-Policy: same-origin");
header("Cross-Origin-Opener-Policy: same-origin");

// Converte avisos do PHP em exceções para que nenhuma resposta de API receba
// HTML misturado ao JSON esperado pelo navegador.
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

if (function_exists("ini_set")) {
    ini_set("session.use_strict_mode", "1");
    ini_set("session.cookie_httponly", "1");
    ini_set("session.cookie_samesite", "Strict");
}

session_name("dallogix_trace_session");
$isSecureSession =
    ambiente_atual() === "production" ||
    filter_var(getenv("SESSION_SECURE") ?: "false", FILTER_VALIDATE_BOOLEAN);
session_set_cookie_params([
    "lifetime" => 0,
    "path" => "/",
    "domain" => "",
    "secure" => $isSecureSession,
    "httponly" => true,
    "samesite" => "Strict",
]);
session_start();

$sessionIdleTimeout = max(300, (int) (getenv("SESSION_IDLE_TIMEOUT") ?: 1800));
if (isset($_SESSION["last_activity"]) && time() - (int) $_SESSION["last_activity"] > $sessionIdleTimeout) {
    $_SESSION = [];
    session_destroy();
    session_start();
}
$_SESSION["last_activity"] = time();

function responder_json(array $dados, int $status = 200): never
{
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

function json_response(array $payload, int $status = 200): never
{
    responder_json($payload, $status);
}

function obter_conexao_banco(): PDO
{
    static $connection;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $host = getenv("DB_HOST") ?: "mysql";
    $name = getenv("DB_NAME") ?: "trace_local";
    $user = getenv("DB_USER") ?: "trace";
    $password = getenv("DB_PASSWORD") ?: "change-me-local";

    if (
        ambiente_atual() === "production" &&
        in_array($password, ["", "change-me-local", "change-me-root"], true)
    ) {
        throw new RuntimeException("DB_PASSWORD de produção não configurado.");
    }

    $connection = new PDO(
        "mysql:host={$host};dbname={$name};charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    );
    $connection->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    return $connection;
}

function db(): PDO
{
    return obter_conexao_banco();
}

function exigir_metodo_http(array $metodosPermitidos): void
{
    $metodoAtual = strtoupper((string) ($_SERVER["REQUEST_METHOD"] ?? "GET"));
    if (!in_array($metodoAtual, array_map('strtoupper', $metodosPermitidos), true)) {
        responder_json(["error" => "Método de requisição não permitido."], 405);
    }
}

function ler_json_da_requisicao(bool $exigirTipoJson = true): array
{
    $metodo = strtoupper((string) ($_SERVER["REQUEST_METHOD"] ?? "GET"));
    if (in_array($metodo, ["GET", "HEAD"], true)) {
        return [];
    }

    if ($exigirTipoJson) {
        $contentType = strtolower((string) ($_SERVER["CONTENT_TYPE"] ?? ""));
        if ($contentType !== "" && !str_contains($contentType, "application/json")) {
            responder_json(["error" => "Content-Type inválido. Envie JSON."], 415);
        }
    }

    $rawBody = file_get_contents("php://input");
    if ($rawBody === false || trim($rawBody) === "") {
        responder_json(["error" => "Corpo da requisição inválido."], 400);
    }

    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        responder_json(["error" => "JSON da requisição inválido."], 400);
    }

    return $payload;
}

function request_json(): array
{
    return ler_json_da_requisicao();
}

function exigir_token_interno(string $environmentKey, string $developmentDefault): void
{
    $expected = trim((string) (getenv($environmentKey) ?: ""));
    if (
        ambiente_atual() === "production" &&
        ($expected === "" || $expected === $developmentDefault)
    ) {
        responder_json(["error" => "Token interno não configurado."], 503);
    }

    $provided = trim((string) ($_SERVER["HTTP_X_INTERNAL_TOKEN"] ?? ""));
    if ($expected === "" || !hash_equals($expected, $provided)) {
        responder_json(["error" => "Token interno inválido."], 401);
    }
}

function require_internal_token(string $environmentKey, string $developmentDefault): void
{
    exigir_token_interno($environmentKey, $developmentDefault);
}

function validar_licenca_ativa(PDO $pdo, int $companyId): array
{
    $statement = $pdo->prepare(
        "SELECT id, status, blocked_reason
         FROM licencas WHERE company_id = :company_id ORDER BY id DESC LIMIT 1",
    );
    $statement->execute(["company_id" => $companyId]);
    $license = $statement->fetch();

    if (!$license) {
        responder_json(["error" => "Empresa sem licença configurada."], 402);
    }

    if ($license["status"] !== "ATIVA") {
        responder_json(
            [
                "error" => "Licença da empresa bloqueada.",
                "license_status" => $license["status"],
                "blocked_reason" => $license["blocked_reason"],
            ],
            402,
        );
    }

    return $license;
}

function require_active_license(PDO $pdo, int $companyId): array
{
    return validar_licenca_ativa($pdo, $companyId);
}

function gerar_token_csrf(): string
{
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION["csrf_token"];
}

function exigir_csrf(): void
{
    if (ambiente_atual() !== "production") {
        return;
    }

    $provided = (string) ($_SERVER["HTTP_X_CSRF_TOKEN"] ?? "");
    if ($provided === "" || !hash_equals(gerar_token_csrf(), $provided)) {
        responder_json(["error" => "Token CSRF inválido ou ausente."], 419);
    }
}

function require_csrf(): void
{
    exigir_csrf();
}

function verificar_taxa_de_login(string $identidade): void
{
    if (ambiente_atual() !== "production") {
        return;
    }

    $chave = hash(
        "sha256",
        ($_SERVER["REMOTE_ADDR"] ?? "unknown") . "|" . strtolower($identidade),
    );
    $arquivo = sys_get_temp_dir() . "/dallogix-login-" . $chave . ".json";
    $handle = fopen($arquivo, "c+");
    if ($handle === false) {
        return;
    }

    flock($handle, LOCK_EX);
    $conteudo = stream_get_contents($handle);
    $tentativas = json_decode($conteudo ?: "[]", true);
    if (!is_array($tentativas)) {
        $tentativas = [];
    }

    $agora = time();
    $tentativas = array_values(
        array_filter(
            $tentativas,
            static fn($timestamp): bool => is_int($timestamp) && $timestamp > $agora - 60,
        ),
    );

    if (count($tentativas) >= 10) {
        flock($handle, LOCK_UN);
        fclose($handle);
        responder_json(["error" => "Muitas tentativas. Aguarde um minuto."], 429);
    }

    $tentativas[] = $agora;
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($tentativas));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

function obter_usuario_sessao(): ?array
{
    return isset($_SESSION["user"]) && is_array($_SESSION["user"])
        ? $_SESSION["user"]
        : null;
}

function exigir_sessao_usuario(): array
{
    $usuario = obter_usuario_sessao();
    if ($usuario === null) {
        responder_json(["error" => "Autenticação necessária."], 401);
    }
    $consulta = obter_conexao_banco()->prepare(
        "SELECT id, company_id, name, email, role, active FROM usuarios WHERE id = :id LIMIT 1",
    );
    $consulta->execute(["id" => (int) ($usuario["id"] ?? 0)]);
    $usuarioAtual = $consulta->fetch();
    if (!$usuarioAtual || !(bool) $usuarioAtual["active"]) {
        $_SESSION = [];
        session_destroy();
        responder_json(["error" => "Sessão expirada ou acesso desativado."], 401);
    }
    $usuarioPublico = usuario_publico($usuarioAtual);
    $_SESSION["user"] = $usuarioPublico;
    return $usuarioPublico;
}

function require_session_user(): array
{
    return exigir_sessao_usuario();
}

function exigir_perfil(array $perfisPermitidos): array
{
    $usuario = exigir_sessao_usuario();
    if (!in_array($usuario["role"], $perfisPermitidos, true)) {
        responder_json(["error" => "Perfil sem permissão para esta ação."], 403);
    }
    return $usuario;
}

function require_role(array $allowedRoles): array
{
    return exigir_perfil($allowedRoles);
}

function usuario_publico(array $usuario): array
{
    return [
        "id" => (int) $usuario["id"],
        "name" => $usuario["name"],
        "email" => $usuario["email"],
        "role" => $usuario["role"],
        "company_id" => $usuario["company_id"] === null ? null : (int) $usuario["company_id"],
    ];
}

function registrar_evento_operacional(
    PDO $conexao,
    array $usuario,
    string $acao,
    string $tipoEntidade,
    int $entidadeId,
    array $payload = [],
): void {
    try {
        $metadata = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
        if ($metadata === false) {
            $metadata = "{}";
        }

        $auditoria = $conexao->prepare(
            "INSERT INTO logs_auditoria (company_id, user_id, action, entity_type, entity_id, metadata) VALUES (:company_id, :user_id, :action, :entity_type, :entity_id, :metadata)",
        );
        $auditoria->execute([
            "company_id" => $usuario["company_id"] ?? null,
            "user_id" => $usuario["id"] ?? null,
            "action" => $acao,
            "entity_type" => $tipoEntidade,
            "entity_id" => $entidadeId,
            "metadata" => $metadata,
        ]);

        $eventUuid = sprintf(
            "%s-%s-%s-%s-%s",
            bin2hex(random_bytes(4)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(6)),
        );

        $payloadSincronizacao = json_encode(
            [
                "action" => $acao,
                "entity_type" => $tipoEntidade,
                "entity_id" => $entidadeId,
                "data" => $payload,
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
        if ($payloadSincronizacao === false) {
            $payloadSincronizacao = "{}";
        }

        $fila = $conexao->prepare(
            "INSERT INTO fila_sincronizacao (event_uuid, aggregate_type, aggregate_id, payload) VALUES (:event_uuid, :aggregate_type, :aggregate_id, :payload)",
        );
        $fila->execute([
            "event_uuid" => $eventUuid,
            "aggregate_type" => $tipoEntidade,
            "aggregate_id" => $entidadeId,
            "payload" => $payloadSincronizacao,
        ]);
    } catch (Throwable $exception) {
        error_log("Operational event could not be recorded: " . $exception->getMessage());
    }
}

function record_operational_event(
    PDO $connection,
    array $user,
    string $action,
    string $entityType,
    int $entityId,
    array $payload = [],
): void {
    registrar_evento_operacional($connection, $user, $action, $entityType, $entityId, $payload);
}

function registrar_log_erro(Throwable $exception, string $origem = "api"): void
{
    $usuario = obter_usuario_sessao();
    $mensagem = mb_substr(trim($exception->getMessage()) ?: "Falha inesperada.", 0, 1000);
    error_log("Dallogix Trace [{$origem}]: {$mensagem}");

    try {
        $contexto = json_encode(
            [
                "tipo" => get_class($exception),
                "arquivo" => basename($exception->getFile()),
                "linha" => $exception->getLine(),
                "metodo" => $_SERVER["REQUEST_METHOD"] ?? "CLI",
                "rota" => strtok($_SERVER["REQUEST_URI"] ?? "", "?"),
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
        if ($contexto === false) {
            $contexto = "{}";
        }

        $statement = db()->prepare(
            "INSERT INTO logs_erros (company_id, user_id, origem, mensagem, contexto) VALUES (:company_id, :user_id, :origem, :mensagem, :contexto)",
        );
        $statement->execute([
            "company_id" => $usuario["company_id"] ?? null,
            "user_id" => $usuario["id"] ?? null,
            "origem" => mb_substr($origem, 0, 120),
            "mensagem" => $mensagem,
            "contexto" => $contexto,
        ]);
    } catch (Throwable $ignored) {
        error_log("Dallogix Trace: não foi possível persistir o log de erro.");
    }
}

set_exception_handler(static function (Throwable $exception): void {
    registrar_log_erro($exception, "erro_nao_tratado");
    if (!headers_sent()) {
        responder_json(["error" => "Ocorreu um erro inesperado. Consulte os logs do sistema."], 500);
    }
});
