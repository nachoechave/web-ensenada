<?php

require __DIR__.'/common.php';
schema()->exec('DELETE FROM auth_login_windows');
schema()->exec("UPDATE usuarios SET activo=1 WHERE email IN ('admin@example.test','prensa@example.test','hacienda@example.test')");
