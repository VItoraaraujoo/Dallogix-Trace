<?php

declare(strict_types=1);

require_once __DIR__ . "/../configuracao/bootstrap.php";

exigir_metodo_http(["GET"]);

$usuario = require_session_user();

responder_json([
    "authenticated" => true,
    "user" => $usuario,
    "csrf_token" => gerar_token_csrf(),
]);
