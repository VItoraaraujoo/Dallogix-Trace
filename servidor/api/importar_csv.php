<?php
declare(strict_types=1);

require_once __DIR__ . "/../configuracao/bootstrap.php";

$user = require_session_user();
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    json_response(["error" => "Método não permitido."], 405);
}
require_csrf();
if ($user["company_id"] === null) {
    json_response(["error" => "Usuário sem empresa vinculada."], 403);
}
if (!in_array($user["role"], ["ADMIN_EMPRESA", "SUPERVISOR"], true)) {
    json_response(["error" => "Perfil sem permissão para importar romaneio."], 403);
}
if (!isset($_FILES["file"]) || $_FILES["file"]["error"] !== UPLOAD_ERR_OK || !is_uploaded_file($_FILES["file"]["tmp_name"])) {
    json_response(["error" => "Envie um arquivo CSV válido."], 422);
}
if ((int) ($_FILES["file"]["size"] ?? 0) < 1 || (int) ($_FILES["file"]["size"] ?? 0) > 5 * 1024 * 1024) {
    json_response(["error" => "CSV excede o limite de 5 MB."], 413);
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES["file"]["tmp_name"]);
if (!in_array($mime, ["text/csv", "text/plain", "application/csv", "application/vnd.ms-excel", "application/octet-stream"], true)) {
    json_response(["error" => "Tipo de arquivo inválido: envie um CSV."], 422);
}

$handle = fopen($_FILES["file"]["tmp_name"], "rb");
if ($handle === false) {
    json_response(["error" => "Não foi possível ler o arquivo CSV."], 422);
}

$rows = [];
while (($row = fgetcsv($handle, 0, ";")) !== false) {
    if (
        count(
            array_filter(
                $row,
                static fn($value) => trim((string) $value) !== "",
            ),
        ) === 0
    ) {
        continue;
    }
    $rows[] = array_map(static fn($value) => trim((string) $value), $row);
    if (count($rows) > 10000) {
        json_response(
            ["error" => "CSV excede o limite de 10.000 linhas."],
            413,
        );
    }
}
fclose($handle);
if (count($rows) < 2) {
    json_response(["error" => "CSV vazio ou sem linhas de dados."], 422);
}

$header = array_map(
    static fn($value) => strtolower(
        trim(preg_replace('/^\xEF\xBB\xBF/', "", $value) ?? $value),
    ),
    $rows[0],
);
$required = ["romaneio", "data", "placa", "produto", "quantidade"];
foreach ($required as $column) {
    if (!in_array($column, $header, true)) {
        json_response(
            ["error" => "Coluna obrigatória ausente: {$column}."],
            422,
        );
    }
}
$index = array_flip($header);
$pdo = db();
$created = 0;
$romaneioIds = [];
$truckIds = [];
$itemIds = [];
try {
    $pdo->beginTransaction();
    foreach (array_slice($rows, 1) as $line => $row) {
        $lineNumber = $line + 2;
        $number = trim((string) ($row[$index["romaneio"]] ?? ""));
        $dateValue = trim((string) ($row[$index["data"]] ?? ""));
        $plate = strtoupper(trim((string) ($row[$index["placa"]] ?? "")));
        $productCode = trim((string) ($row[$index["produto"]] ?? ""));
        $quantity = filter_var(
            $row[$index["quantidade"]] ?? null,
            FILTER_VALIDATE_INT,
        );
        $driverName = trim((string) ($row[$index["motorista"]] ?? ""));
        $shipper = trim((string) ($row[$index["expedidor"]] ?? ""));
        $date =
            DateTime::createFromFormat("Y-m-d", $dateValue) ?:
            DateTime::createFromFormat("d/m/Y", $dateValue);
        if ($date) {
            $dateValue = $date->format("Y-m-d");
        }
        if (
            $number === "" ||
            mb_strlen($number) > 80 ||
            !preg_match('/^[A-Z0-9-]{7,8}$/', $plate) ||
            $productCode === "" ||
            $quantity === false ||
            $quantity < 1 ||
            !$date
        ) {
            throw new RuntimeException(
                "Linha {$lineNumber} inválida. Informe romaneio, data (AAAA-MM-DD ou DD/MM/AAAA), placa Mercosul, produto e quantidade maior que zero.",
            );
        }
        if (mb_strlen($driverName) > 160 || mb_strlen($shipper) > 160) {
            throw new RuntimeException(
                "Linha {$lineNumber} possui motorista ou expedidor acima de 160 caracteres.",
            );
        }

        $productStatement = $pdo->prepare(
            "SELECT id FROM produtos WHERE company_id = :company_id AND code = :code AND active = 1 LIMIT 1",
        );
        $productStatement->execute([
            "company_id" => $user["company_id"],
            "code" => $productCode,
        ]);
        $product = $productStatement->fetch();
        if (!$product) {
            throw new RuntimeException(
                "Produto {$productCode} não encontrado na linha {$lineNumber}.",
            );
        }

        if (!isset($romaneioIds[$number])) {
            $romaneioStatement = $pdo->prepare(
                'INSERT INTO romaneios (company_id, number, scheduled_date, expedidor, status) VALUES (:company_id, :number, :scheduled_date, :expedidor, \'IMPORTADO\')',
            );
            $romaneioStatement->execute([
                "company_id" => $user["company_id"],
                "number" => $number,
                "scheduled_date" => $dateValue,
                "expedidor" => $shipper === "" ? null : $shipper,
            ]);
            $romaneioIds[$number] = (int) $pdo->lastInsertId();
        }
        $romaneioId = $romaneioIds[$number];
        $truckKey = $number . "|" . $plate;
        if (!isset($truckIds[$truckKey])) {
            $truckStatement = $pdo->prepare(
                "INSERT INTO romaneio_caminhoes (romaneio_id, plate, driver_name) VALUES (:romaneio_id, :plate, :driver_name)",
            );
            $truckStatement->execute([
                "romaneio_id" => $romaneioId,
                "plate" => $plate,
                "driver_name" => $driverName === "" ? null : $driverName,
            ]);
            $truckIds[$truckKey] = (int) $pdo->lastInsertId();
        }
        $itemKey =
            $romaneioId . "|" . $truckIds[$truckKey] . "|" . $product["id"];
        if (isset($itemIds[$itemKey])) {
            $pdo->prepare(
                "UPDATE romaneio_itens SET planned_quantity = planned_quantity + :quantity WHERE id = :id",
            )->execute(["quantity" => $quantity, "id" => $itemIds[$itemKey]]);
        } else {
            $itemStatement = $pdo->prepare(
                "INSERT INTO romaneio_itens (romaneio_id, product_id, truck_id, planned_quantity) VALUES (:romaneio_id, :product_id, :truck_id, :quantity)",
            );
            $itemStatement->execute([
                "romaneio_id" => $romaneioId,
                "product_id" => $product["id"],
                "truck_id" => $truckIds[$truckKey],
                "quantity" => $quantity,
            ]);
            $itemIds[$itemKey] = (int) $pdo->lastInsertId();
        }
        $created++;
    }
    $pdo->commit();
    foreach ($romaneioIds as $number => $romaneioId) {
        record_operational_event(
            $pdo,
            $user,
            "ROMANEIO_IMPORTADO_CSV",
            "romaneio",
            $romaneioId,
            ["number" => $number, "items" => $created],
        );
    }
    json_response(
        [
            "data" => [
                "romaneios" => count($romaneioIds),
                "linhas" => $created,
                "items" => count($itemIds),
            ],
        ],
        201,
    );
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (
        $exception instanceof PDOException &&
        isset($exception->errorInfo[1]) &&
        (int) $exception->errorInfo[1] === 1062
    ) {
        json_response(
            ["error" => "O CSV contém romaneio ou caminhão duplicado."],
            409,
        );
    }
    json_response(["error" => $exception->getMessage()], 422);
}
