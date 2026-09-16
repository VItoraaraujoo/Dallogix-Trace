<?php

declare(strict_types=1);

require_once __DIR__ . "/../configuracao/bootstrap.php";

$usuarioAtor = exigir_sessao_usuario();
if ($usuarioAtor["company_id"] === null) {
    responder_json(["error" => "Usuário sem empresa vinculada."], 403);
}
$pdo = obter_conexao_banco();

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $settings = $pdo->prepare(
        "SELECT gateway_public_ip, pdf_field_mapping, pdf_search_field, updated_at FROM configuracoes_empresa WHERE company_id = :company_id LIMIT 1",
    );
    $settings->execute(["company_id" => $usuarioAtor["company_id"]]);
    $data = $settings->fetch() ?: [
        "gateway_public_ip" => null,
        "pdf_field_mapping" => null,
        "pdf_search_field" => "barcode",
        "updated_at" => null,
    ];
    if (is_string($data["pdf_field_mapping"])) {
        $data["pdf_field_mapping"] = json_decode(
            $data["pdf_field_mapping"],
            true,
        );
    }
    $equipment = $pdo->prepare(
        "SELECT id, equipment_code, name, plc_ip, plc_port, external_port, plc_protocol FROM equipamentos WHERE company_id = :company_id ORDER BY equipment_code",
    );
    $equipment->execute(["company_id" => $usuarioAtor["company_id"]]);
    json_response([
        "data" => ["settings" => $data, "dalas" => $equipment->fetchAll()],
    ]);
}

if ($_SERVER["REQUEST_METHOD"] !== "PUT") {
    responder_json(["error" => "Método não permitido."], 405);
}
exigir_csrf();
if (!in_array($usuarioAtor["role"], ["ADMIN_DALLOGIX", "ADMIN_EMPRESA"], true)) {
    responder_json(
        ["error" => "Perfil sem permissão para alterar configurações."],
        403,
    );
}

$payload = ler_json_da_requisicao();
$gatewayIp = trim((string) ($payload["gateway_public_ip"] ?? ""));
if (array_key_exists("sync_remote_url", $payload)) {
    json_response(
        ["error" => "A integração com o servidor é configurada somente no backend."],
        403,
    );
}
$currentSync = $pdo->prepare(
    "SELECT sync_remote_url FROM configuracoes_empresa WHERE company_id = :company_id LIMIT 1",
);
$currentSync->execute(["company_id" => $usuarioAtor["company_id"]]);
$syncUrl = trim((string) ($currentSync->fetchColumn() ?: ""));
$mapping = $payload["pdf_field_mapping"] ?? [];
$pdfSearchField = in_array(
    $payload["pdf_search_field"] ?? "barcode",
    ["barcode", "sku"],
    true,
)
    ? $payload["pdf_search_field"] ?? "barcode"
    : "barcode";
if ($gatewayIp !== "" && strlen($gatewayIp) > 255) {
    json_response(["error" => "IP ou DDNS do gateway inválido."], 422);
}
if (!is_array($mapping)) {
    json_response(["error" => "Mapeamento PDF inválido."], 422);
}

$upsert = $pdo->prepare(
    "INSERT INTO configuracoes_empresa (company_id, gateway_public_ip, sync_remote_url, pdf_field_mapping, pdf_search_field, updated_by) VALUES (:company_id, :gateway_public_ip, :sync_remote_url, :pdf_field_mapping, :pdf_search_field, :updated_by) ON DUPLICATE KEY UPDATE gateway_public_ip = VALUES(gateway_public_ip), sync_remote_url = VALUES(sync_remote_url), pdf_field_mapping = VALUES(pdf_field_mapping), pdf_search_field = VALUES(pdf_search_field), updated_by = VALUES(updated_by)",
);
$upsert->execute([
    "company_id" => $usuarioAtor["company_id"],
    "gateway_public_ip" => $gatewayIp !== "" ? $gatewayIp : null,
    "sync_remote_url" => $syncUrl !== "" ? $syncUrl : null,
    "pdf_field_mapping" => json_encode(
        $mapping,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    ),
    "pdf_search_field" => $pdfSearchField,
    "updated_by" => $usuarioAtor["id"],
]);
record_operational_event(
    $pdo,
    $usuarioAtor,
    "CONFIGURACAO_ATUALIZADA",
    "configuracoes_empresa",
    (int) $usuarioAtor["company_id"],
    [
        "gateway_public_ip" => $gatewayIp,
        "sync_remote_url_configurada" => $syncUrl !== "",
        "pdf_fields" => array_keys($mapping),
        "pdf_search_field" => $pdfSearchField,
    ],
);
json_response(["data" => ["saved" => true]]);
