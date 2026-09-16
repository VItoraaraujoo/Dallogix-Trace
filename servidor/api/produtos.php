<?php

declare(strict_types=1);

require_once __DIR__ . "/../configuracao/bootstrap.php";

$usuarioAtor = exigir_sessao_usuario();
if ($usuarioAtor["company_id"] === null) {
    responder_json(["error" => "Usuário sem empresa vinculada."], 403);
}

$podeGerenciar = in_array(
    $usuarioAtor["role"],
    ["ADMIN_DALLOGIX", "ADMIN_EMPRESA", "SUPERVISOR"],
    true,
);
$pdo = obter_conexao_banco();

$productResponse = static function (array $product): array {
    return [
        "id" => (int) $product["id"],
        "code" => $product["code"],
        "name" => $product["name"],
        "category" => $product["category"] ?? null,
        "active" => (int) $product["active"],
        "barcodes" => $product["barcodes"] ?? "",
    ];
};

$barcodeError = static function (PDOException $exception): never {
    if ((int) $exception->errorInfo[1] === 1062) {
        json_response(
            ["error" => "Código de barras já usado por outro produto."],
            409,
        );
    }
    throw $exception;
};

$syncBarcode = static function (PDO $pdo, int $companyId, int $productId, string $barcode) use (
    $barcodeError,
): void {
    $existing = $pdo->prepare(
        "SELECT id FROM codigos_produtos WHERE product_id = :product_id ORDER BY id LIMIT 1",
    );
    $existing->execute(["product_id" => $productId]);
    $row = $existing->fetch();
    try {
        if ($row) {
            $update = $pdo->prepare(
                "UPDATE codigos_produtos SET barcode = :barcode WHERE id = :id",
            );
            $update->execute(["barcode" => $barcode, "id" => $row["id"]]);
            return;
        }
        $insert = $pdo->prepare(
            "INSERT INTO codigos_produtos (company_id, product_id, barcode) VALUES (:company_id, :product_id, :barcode)",
        );
        $insert->execute(["company_id" => $companyId, "product_id" => $productId, "barcode" => $barcode]);
    } catch (PDOException $exception) {
        $barcodeError($exception);
    }
};

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $search = trim((string) ($_GET["search"] ?? ""));
    $conditions = ["p.company_id = :company_id"];
    $params = ["company_id" => $usuarioAtor["company_id"]];
    if ($search !== "") {
        $conditions[] =
            "(p.name LIKE :search OR p.code LIKE :search OR p.category LIKE :search OR EXISTS (SELECT 1 FROM codigos_produtos pc WHERE pc.product_id = p.id AND pc.barcode LIKE :search))";
        $params["search"] = "%" . addcslashes($search, "%_\\") . "%";
    }
    $statement = $pdo->prepare(
        'SELECT p.id, p.code, p.name, p.category, p.active, GROUP_CONCAT(pc.barcode ORDER BY pc.barcode SEPARATOR ",") AS barcodes
         FROM produtos p LEFT JOIN codigos_produtos pc ON pc.product_id = p.id
         WHERE ' .
            implode(" AND ", $conditions) .
            '
         GROUP BY p.id ORDER BY p.name',
    );
    $statement->execute($params);
    json_response([
        "data" => array_map($productResponse, $statement->fetchAll()),
    ]);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_csrf();
    if (!$podeGerenciar) {
        json_response(
            ["error" => "Perfil sem permissão para cadastrar produto."],
            403,
        );
    }
    $payload = request_json();
    $name = trim((string) ($payload["name"] ?? ""));
    $barcode = trim(
        (string) ($payload["barcode"] ?? ($payload["barcodes"] ?? "")),
    );
    $code = trim((string) ($payload["code"] ?? ($payload["sku"] ?? "")));
    $category = trim((string) ($payload["category"] ?? ""));
    if ($name === "" || $barcode === "") {
        json_response(
            ["error" => "Nome e código de barras são obrigatórios."],
            422,
        );
    }
    try {
        $pdo->beginTransaction();
        // SKU opcional: quando vazio, gera um código único a partir do id.
        $temporaryCode =
            $code !== "" ? $code : "TMP-" . bin2hex(random_bytes(8));
        $insert = $pdo->prepare(
            "INSERT INTO produtos (company_id, code, name, category) VALUES (:company_id, :code, :name, :category)",
        );
        $insert->execute([
            "company_id" => $usuarioAtor["company_id"],
            "code" => $temporaryCode,
            "name" => $name,
            "category" => $category !== "" ? $category : null,
        ]);
        $productId = (int) $pdo->lastInsertId();
        if ($code === "") {
            $finalize = $pdo->prepare(
                "UPDATE produtos SET code = :code WHERE id = :id",
            );
            $finalize->execute([
                "code" => "SKU" . $productId,
                "id" => $productId,
            ]);
            $code = "SKU" . $productId;
        }
        $syncBarcode($pdo, (int) $usuarioAtor["company_id"], $productId, $barcode);
        record_operational_event(
            $pdo,
            $usuarioAtor,
            "PRODUTO_CADASTRADO",
            "produto",
            $productId,
            ["code" => $code, "barcode" => $barcode],
        );
        $pdo->commit();
        json_response(
            [
                "data" => [
                    "id" => $productId,
                    "code" => $code,
                    "name" => $name,
                    "category" => $category !== "" ? $category : null,
                    "active" => 1,
                    "barcodes" => $barcode,
                ],
            ],
            201,
        );
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ((int) $exception->errorInfo[1] === 1062) {
            json_response(
                ["error" => "Produto ou código de barras já cadastrado."],
                409,
            );
        }
        json_response(["error" => "Não foi possível salvar o produto."], 500);
    }
}

if ($_SERVER["REQUEST_METHOD"] === "PUT") {
    require_csrf();
    if (!$podeGerenciar) {
        json_response(
            ["error" => "Perfil sem permissão para editar produto."],
            403,
        );
    }
    $payload = request_json();
    $productId = filter_var($payload["id"] ?? null, FILTER_VALIDATE_INT);
    if (!$productId) {
        json_response(["error" => "Produto não informado."], 422);
    }
    $find = $pdo->prepare(
        "SELECT id FROM produtos WHERE id = :id AND company_id = :company_id LIMIT 1",
    );
    $find->execute(["id" => $productId, "company_id" => $usuarioAtor["company_id"]]);
    if (!$find->fetch()) {
        json_response(["error" => "Produto não encontrado."], 404);
    }
    $name = trim((string) ($payload["name"] ?? ""));
    $code = trim((string) ($payload["code"] ?? ($payload["sku"] ?? "")));
    $category = trim((string) ($payload["category"] ?? ""));
    $active = array_key_exists("active", $payload)
        ? (int) (bool) $payload["active"]
        : null;
    if ($name === "") {
        json_response(["error" => "Nome do produto é obrigatório."], 422);
    }
    try {
        $pdo->beginTransaction();
        $update = $pdo->prepare(
            "UPDATE produtos SET name = :name, code = COALESCE(NULLIF(:code, ''), code), category = :category, active = COALESCE(:active, active) WHERE id = :id",
        );
        $update->execute([
            "name" => $name,
            "code" => $code,
            "category" => $category !== "" ? $category : null,
            "active" => $active,
            "id" => $productId,
        ]);
        $barcode = trim(
            (string) ($payload["barcode"] ?? ($payload["barcodes"] ?? "")),
        );
        if ($barcode !== "") {
            $syncBarcode($pdo, (int) $usuarioAtor["company_id"], $productId, $barcode);
        }
        record_operational_event(
            $pdo,
            $usuarioAtor,
            "PRODUTO_ATUALIZADO",
            "produto",
            $productId,
            ["name" => $name],
        );
        $pdo->commit();
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
            json_response(
                [
                    "error" =>
                    "SKU ou código de barras já usado por outro produto.",
                ],
                409,
            );
        }
        json_response(
            ["error" => "Não foi possível atualizar o produto."],
            500,
        );
    }
    json_response(["data" => ["updated" => true]]);
}

if ($_SERVER["REQUEST_METHOD"] === "DELETE") {
    require_csrf();
    if (!$podeGerenciar) {
        json_response(
            ["error" => "Perfil sem permissão para excluir produto."],
            403,
        );
    }
    $productId = filter_var($_GET["id"] ?? null, FILTER_VALIDATE_INT);
    if (!$productId) {
        json_response(["error" => "Produto não informado."], 422);
    }
    $find = $pdo->prepare(
        "SELECT id FROM produtos WHERE id = :id AND company_id = :company_id LIMIT 1",
    );
    $find->execute(["id" => $productId, "company_id" => $usuarioAtor["company_id"]]);
    if (!$find->fetch()) {
        json_response(["error" => "Produto não encontrado."], 404);
    }
    try {
        $pdo->beginTransaction();
        $pdo->prepare(
            "DELETE FROM codigos_produtos WHERE product_id = :id",
        )->execute(["id" => $productId]);
        $pdo->prepare("DELETE FROM produtos WHERE id = :id")->execute([
            "id" => $productId,
        ]);
        record_operational_event(
            $pdo,
            $usuarioAtor,
            "PRODUTO_EXCLUIDO",
            "produto",
            $productId,
            [],
        );
        $pdo->commit();
        json_response(["data" => ["deleted" => true]]);
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ((int) $exception->errorInfo[1] === 1451) {
            // Produto com histórico (romaneios/carregamentos): apenas desativa para preservar o histórico.
            $pdo->prepare(
                "UPDATE produtos SET active = 0 WHERE id = :id",
            )->execute(["id" => $productId]);
            record_operational_event(
                $pdo,
                $usuarioAtor,
                "PRODUTO_DESATIVADO",
                "produto",
                $productId,
                ["motivo" => "possui histórico vinculado"],
            );
            json_response([
                "data" => [
                    "deleted" => false,
                    "deactivated" => true,
                    "message" =>
                    "Produto possui histórico vinculado e foi desativado.",
                ],
            ]);
        }
        json_response(["error" => "Não foi possível excluir o produto."], 500);
    }
}

json_response(["error" => "Método não permitido."], 405);
