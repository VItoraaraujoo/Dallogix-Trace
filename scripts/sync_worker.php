<?php

declare(strict_types=1);

require_once __DIR__ . "/../servidor/configuracao/bootstrap.php";
require_once __DIR__ . "/../servidor/src/Aplicacao/ServicoSincronizacao.php";

use App\Aplicacao\ServicoSincronizacao;

$batchSize = max(1, min(500, (int) (getenv("SYNC_BATCH_SIZE") ?: 50)));
$summary = (new ServicoSincronizacao(db()))->processBatch($batchSize);
if ($summary["reserved"] > 0 || $summary["failed"] > 0) {
    fwrite(STDOUT, sprintf(
        "sync-worker: reservados=%d enviados=%d falhas=%d ignorados=%d\n",
        $summary["reserved"],
        $summary["sent"],
        $summary["failed"],
        $summary["skipped"],
    ));
}
