<?php

declare(strict_types=1);

require_once __DIR__ . "/../configuracao/bootstrap.php";

responder_json([
    "service" => "dallogix-trace-local",
    "status" => "ok",
    "mode" => "local-first",
    "equipment_id" => getenv("TRACE_EQUIPMENT_ID") ?: "EST-001",
]);
