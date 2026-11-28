<?php

// Existing users and content are retained. No initial provisioning against the transferred database.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
fwrite(STDERR, "Deshabilitado: usar handover.php audit sobre ensenada_web. No se aprovisionan ni reemplazan registros.\n");
exit(1);
