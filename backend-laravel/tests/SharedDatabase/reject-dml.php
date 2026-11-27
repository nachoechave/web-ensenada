<?php

use App\Support\ProductionPermissions;
use App\Support\SharedDatabaseAudit;

require __DIR__.'/common.php';
try {
    ProductionPermissions::verifyPdo(SharedDatabaseAudit::connect(SharedDatabaseAudit::env(P)));
    throw new LogicException('cpanel-dml indebidamente aceptado.');
} catch (RuntimeException $error) {
    if ($error instanceof LogicException) {
        throw $error;
    }
    echo "OK cpanel-dml rechazado como perfil productivo\n";
}
