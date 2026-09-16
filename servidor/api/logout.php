<?php

declare(strict_types=1);

require_once __DIR__ . "/../configuracao/bootstrap.php";

exigir_csrf();

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $configuracoes = session_get_cookie_params();
    setcookie(
        session_name(),
        "",
        time() - 42000,
        $configuracoes["path"],
        $configuracoes["domain"],
        (bool) $configuracoes["secure"],
        (bool) $configuracoes["httponly"],
    );
}
session_destroy();

responder_json(["authenticated" => false]);
