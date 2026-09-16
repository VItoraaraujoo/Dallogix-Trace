<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$servidorDir = $root . DIRECTORY_SEPARATOR . 'servidor';
$criticalFiles = [
    $servidorDir . DIRECTORY_SEPARATOR . 'configuracao' . DIRECTORY_SEPARATOR . 'bootstrap.php',
    $servidorDir . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'login.php',
    $servidorDir . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'usuarios.php',
    $servidorDir . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'empresas.php',
    $servidorDir . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'romaneios.php',
    $servidorDir . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'carregamentos.php',
    $servidorDir . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'leituras.php',
    $servidorDir . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'ocorrencias.php',
];

$checks = [
    'bootstrap_security_headers' => [
        'description' => 'Headers de segurança no bootstrap',
        'status' => false,
        'message' => 'Não encontrado',
    ],
    'bootstrap_session_security' => [
        'description' => 'Sessão segura e HttpOnly',
        'status' => false,
        'message' => 'Não encontrado',
    ],
    'bootstrap_db_access' => [
        'description' => 'Acesso centralizado ao banco',
        'status' => false,
        'message' => 'Não encontrado',
    ],
    'bootstrap_auth_helpers' => [
        'description' => 'Helpers de autenticação e autorização',
        'status' => false,
        'message' => 'Não encontrado',
    ],
    'audit_log_helpers' => [
        'description' => 'Helpers de auditoria e logs de erro',
        'status' => false,
        'message' => 'Não encontrado',
    ],
];

$bootstrapContent = file_exists($criticalFiles[0]) ? file_get_contents($criticalFiles[0]) : '';
if ($bootstrapContent !== false) {
    $checks['bootstrap_security_headers']['status'] = str_contains($bootstrapContent, 'Content-Type')
        && str_contains($bootstrapContent, 'X-Content-Type-Options')
        && str_contains($bootstrapContent, 'X-Frame-Options');
    $checks['bootstrap_security_headers']['message'] = $checks['bootstrap_security_headers']['status'] ? 'OK' : 'Faltando headers';

    $checks['bootstrap_session_security']['status'] = str_contains($bootstrapContent, 'session.cookie_httponly')
        && str_contains($bootstrapContent, 'session.use_strict_mode')
        && str_contains($bootstrapContent, 'session_set_cookie_params');
    $checks['bootstrap_session_security']['message'] = $checks['bootstrap_session_security']['status'] ? 'OK' : 'Configuração incompleta';

    $checks['bootstrap_db_access']['status'] = str_contains($bootstrapContent, 'function obter_conexao_banco')
        && str_contains($bootstrapContent, 'PDO::ATTR_ERRMODE')
        && str_contains($bootstrapContent, 'PDO::ATTR_EMULATE_PREPARES');
    $checks['bootstrap_db_access']['message'] = $checks['bootstrap_db_access']['status'] ? 'OK' : 'Configuração incompleta';

    $checks['bootstrap_auth_helpers']['status'] = str_contains($bootstrapContent, 'function exigir_sessao_usuario')
        && str_contains($bootstrapContent, 'function exigir_perfil')
        && str_contains($bootstrapContent, 'function require_role');
    $checks['bootstrap_auth_helpers']['message'] = $checks['bootstrap_auth_helpers']['status'] ? 'OK' : 'Helpers faltando';

    $checks['audit_log_helpers']['status'] = str_contains($bootstrapContent, 'function registrar_evento_operacional')
        && str_contains($bootstrapContent, 'function registrar_log_erro')
        && str_contains($bootstrapContent, 'set_exception_handler');
    $checks['audit_log_helpers']['message'] = $checks['audit_log_helpers']['status'] ? 'OK' : 'Ações de auditoria incompletas';
}

$results = [];
foreach ($criticalFiles as $filePath) {
    $fileName = str_replace($root . DIRECTORY_SEPARATOR, '', $filePath);
    if (!is_file($filePath)) {
        $results[] = [
            'arquivo' => $fileName,
            'status' => 'faltando',
            'mensagem' => 'Arquivo não encontrado',
        ];
        continue;
    }

    $command = sprintf('php -l %s 2>&1', escapeshellarg($filePath));
    $output = shell_exec($command);
    $exitCode = 0;
    $status = 'ok';
    $mensagem = 'Sem erro de sintaxe';

    if ($output === null || trim((string) $output) === '') {
        $mensagem = 'Resposta vazia do PHP lint';
        $status = 'warning';
    } else {
        $outputText = trim((string) $output);
        if (stripos($outputText, 'No syntax errors') === false && stripos($outputText, 'no syntax errors') === false) {
            $status = 'erro';
            $mensagem = $outputText;
            $exitCode = 1;
        }
    }

    $results[] = [
        'arquivo' => $fileName,
        'status' => $status,
        'mensagem' => $mensagem,
        'codigo' => $exitCode,
    ];
}

$overallStatus = 'OK';
foreach ($results as $result) {
    if ($result['status'] === 'erro') {
        $overallStatus = 'ERRO';
        break;
    }
    if ($result['status'] === 'warning') {
        $overallStatus = 'ATENCAO';
    }
}
foreach ($checks as $check) {
    if ($check['status'] !== true) {
        $overallStatus = $overallStatus === 'ERRO' ? 'ERRO' : 'ATENCAO';
    }
}

printf("Avaliação profissional do sistema\n");
printf("Status geral: %s\n\n", $overallStatus);

foreach ($checks as $name => $check) {
    printf("[%s] %s - %s\n", $check['status'] ? 'OK' : 'WARN', $check['description'], $check['message']);
}

printf("\nValidação de sintaxe PHP\n");
foreach ($results as $result) {
    printf("[%s] %s - %s\n", strtoupper($result['status']), $result['arquivo'], $result['mensagem']);
}

return exit($overallStatus === 'OK' ? 0 : ($overallStatus === 'ATENCAO' ? 1 : 2));
