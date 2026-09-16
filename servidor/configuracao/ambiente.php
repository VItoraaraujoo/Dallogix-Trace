<?php
declare(strict_types=1);

$configuredTimezone = trim((string) (getenv("TZ") ?: "America/Sao_Paulo"));
if ($configuredTimezone !== "") date_default_timezone_set($configuredTimezone);
header("Content-Type: application/json; charset=utf-8");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: no-referrer");
session_name("dallogix_trace_session");
ini_set("session.use_strict_mode", "1");
ini_set("session.use_only_cookies", "1");
$isSecureSession = (getenv("APP_ENV") ?: "local") === "production" || filter_var(getenv("SESSION_SECURE") ?: "false", FILTER_VALIDATE_BOOLEAN);
session_set_cookie_params(["httponly" => true, "samesite" => "Strict", "secure" => $isSecureSession]);
session_start();
