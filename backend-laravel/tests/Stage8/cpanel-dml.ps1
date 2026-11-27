$ErrorActionPreference = 'Stop'
Set-Location (Join-Path $PSScriptRoot '../..')

# From backend-laravel, start a fresh project with compose.stage2.yaml,
# compose.stage8.yaml and compose.stage8b.yaml using `up -d --wait angular`.
# Set AUTH_STAGE8_COLLATION=utf8mb4_unicode_520_ci and use the synthetic runtime
# password in compose.stage2.yaml. Destroy only this test project after the run.
$project = 'ensenada-cpanel-dml-test'
$db = "$project-mysql-auth-1"
$app = "$project-app-1"
$mysql = docker inspect $db | ConvertFrom-Json
$api = docker inspect $app | ConvertFrom-Json
if ($LASTEXITCODE -ne 0 -or $mysql.Config.Labels.'com.docker.compose.project' -ne $project `
    -or $api.Config.Labels.'com.docker.compose.project' -ne $project `
    -or $mysql.Config.Env -notcontains 'MYSQL_DATABASE=ensenada_auth_test' `
    -or -not $mysql.HostConfig.Tmpfs.PSObject.Properties['/var/lib/mysql'] `
    -or $mysql.NetworkSettings.Ports.PSObject.Properties.Value) {
    throw 'Se requiere el proyecto descartable 8B sin puertos de base publicados.'
}

function AdminSql([string]$sql) {
    $sql | docker exec -i -e MYSQL_PWD=synthetic-root-only-1234 $db mariadb --protocol=SOCKET -uroot -Densenada_auth_test -N
    if ($LASTEXITCODE -ne 0) { throw 'Falló la preparación de la base descartable.' }
}

function RuntimeSql([string]$sql, [string]$denied = '') {
    $result = $sql | docker exec -i -e MYSQL_PWD=synthetic-runtime-only-1234 $db mariadb --protocol=TCP -h '127.0.0.1' -uauth_runtime -Densenada_auth_test -N 2>&1
    if ($denied) {
        if ($LASTEXITCODE -eq 0 -or "$result" -notmatch "ERROR ($denied)\b") { throw "Se esperaba denegación $denied. Resultado $result" }
    } elseif ($LASTEXITCODE -ne 0) {
        throw "Falló la consulta de la cuenta sintética. Resultado $result"
    } else {
        $result
    }
}

function CheckProfile([string]$label, [string]$expectedError = '', [bool]$cpanel = $true) {
    $flags = @('--disposable')
    if ($cpanel) { $flags += '--privilege-profile=cpanel-dml' }
    $result = docker exec $app sh docker/local-env.sh php deploy/iwinds/schema-check.php @flags 2>&1
    $status = $LASTEXITCODE
    if ($expectedError) {
        if ($status -ne 1 -or ($result -join "`n") -notmatch $expectedError) { throw "$label. Rechazo inesperado. $result" }
    } elseif ($status -ne 0) {
        throw "$label. El verificador debía aprobar. $result"
    }
    Write-Output "OK    $label"
}

docker cp deploy/iwinds/schema-check.php "${app}:/app/deploy/iwinds/schema-check.php"
if ($LASTEXITCODE -ne 0) { throw 'No se pudo copiar el verificador al contenedor local.' }

$grantError = 'no coinciden con el perfil'
CheckProfile 'El perfil por tabla sigue siendo el predeterminado' '' $false
CheckProfile 'El perfil explícito exige sus propios grants' $grantError

$trigger = [regex]::Match((Get-Content -Raw deploy/iwinds/schema-init-mariadb.sql), '(?s)CREATE TRIGGER revoke_auth_sessions.*?END\$\$').Value
if (-not $trigger) { throw 'No se encontró la definición del trigger.' }
$trigger = $trigger.Replace('CREATE TRIGGER', "CREATE DEFINER='auth_setup'@'%' TRIGGER")
AdminSql @"
DROP TRIGGER revoke_auth_sessions;
DELIMITER `$`$
$trigger
DELIMITER ;
REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'auth_setup'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON ``ensenada\_auth\_test``.* TO 'auth_setup'@'%';
GRANT SELECT ON ``ensenada\_auth\_unit``.* TO 'auth_setup'@'%';
GRANT TRIGGER ON ensenada_auth_test.usuarios TO 'auth_setup'@'%';
GRANT TRIGGER ON ensenada_auth_test.auditoria_noticias TO 'auth_setup'@'%';
GRANT TRIGGER ON ensenada_auth_test.publicaciones_hacienda TO 'auth_setup'@'%';
GRANT TRIGGER ON ensenada_auth_test.archivos_hacienda TO 'auth_setup'@'%';
GRANT TRIGGER ON ensenada_auth_test.documentos_proveedores TO 'auth_setup'@'%';
GRANT TRIGGER ON ensenada_auth_test.portal_site_content TO 'auth_setup'@'%';
CREATE DATABASE ensenadaXauthYtest;
CREATE TABLE ensenadaXauthYtest.scope_probe (id INT);
"@

$literal = '`ensenada\_auth\_test`.*'
$principal = "'auth_runtime'@'%'"
$reset = "REVOKE ALL PRIVILEGES, GRANT OPTION FROM $principal;"
$dml = "GRANT SELECT, INSERT, UPDATE, DELETE ON $literal TO $principal;"
AdminSql "$reset $dml"
CheckProfile 'DML exacto sobre el nombre literal, con cuenta sin DDL'
CheckProfile 'El verificador predeterminado no acepta DML de base' $grantError $false
RuntimeSql 'SELECT id FROM ensenadaXauthYtest.scope_probe;' '1142'
foreach ($sql in @(
    'CREATE TABLE auth_dml_probe (id INT);',
    'ALTER TABLE usuarios ADD COLUMN auth_dml_probe INT;',
    'TRUNCATE TABLE auth_mutexes;',
    'DROP TRIGGER revoke_auth_sessions;',
    'CREATE PROCEDURE auth_dml_probe() SELECT 1;',
    'LOCK TABLES auth_mutexes WRITE;',
    "GRANT SELECT ON $literal TO 'auth_setup'@'%';",
    'SELECT User FROM mysql.user;',
    'SELECT id FROM ensenada_auth_unit.noticias;'
)) { RuntimeSql $sql '1044|1142|1227|1370' }
Write-Output 'OK    Denegaciones de DDL, rutinas, bloqueos de tabla, delegación y lectura de otros esquemas'

$risk = @(RuntimeSql @'
START TRANSACTION;
UPDATE auth_mutexes SET name = 'dml-probe' WHERE name = 'schema-ready';
SELECT ROW_COUNT();
INSERT INTO auditoria_noticias (noticia_id, noticia_titulo, accion, estado_resultante, usuario_nombre, usuario_email, fecha)
VALUES (1, 'Prueba', 'CREACION', 'BORRADOR', 'Sintético', 'dml-probe@example.test', CURRENT_TIMESTAMP);
UPDATE auditoria_noticias SET noticia_titulo = 'Alterado' WHERE usuario_email = 'dml-probe@example.test';
SELECT ROW_COUNT();
DELETE FROM auditoria_noticias WHERE usuario_email = 'dml-probe@example.test';
SELECT ROW_COUNT();
INSERT INTO usuarios (nombre,email,password,rol,activo) VALUES ('Sintético','dml-probe@example.test','fixture','PRENSA',1);
DELETE FROM usuarios WHERE email = 'dml-probe@example.test';
SELECT ROW_COUNT();
ROLLBACK;
SELECT COUNT(*) FROM auth_mutexes WHERE name = 'schema-ready';
SET SESSION foreign_key_checks = 0, unique_checks = 0, check_constraint_checks = 0;
SELECT @@foreign_key_checks + @@unique_checks + @@check_constraint_checks;
'@)
if (($risk -join ',') -ne '1,1,1,1,1,0') { throw "Resultados inesperados de la prueba de alcance. $risk" }
Write-Output 'OK    DML de base permite modificar mutexes y auditoría y borrar usuarios; rollback verificado. Los controles de integridad de sesión se pueden desactivar.'

AdminSql "$reset GRANT SELECT, INSERT, UPDATE, DELETE ON ``ensenada_auth_test``.* TO $principal;"
RuntimeSql 'SELECT id FROM ensenadaXauthYtest.scope_probe;'
CheckProfile 'Rechaza el grant con comodín aunque tenga los cuatro verbos' $grantError

$invalidGrants = [ordered]@{
    'ALL PRIVILEGES' = "GRANT ALL PRIVILEGES ON $literal TO $principal;"
    'Falta DELETE' = "GRANT SELECT, INSERT, UPDATE ON $literal TO $principal;"
    'DDL adicional' = "$dml GRANT CREATE ON $literal TO $principal;"
    'Rutinas' = "$dml GRANT EXECUTE ON $literal TO $principal;"
    'Delegación' = "GRANT SELECT, INSERT, UPDATE, DELETE ON $literal TO $principal WITH GRANT OPTION;"
    'Grant global adicional' = "$dml GRANT PROCESS ON *.* TO $principal;"
    'Grant de otro esquema' = "$dml GRANT SELECT ON ensenadaXauthYtest.* TO $principal;"
    'Grant adicional por tabla' = "$dml GRANT SELECT ON ensenada_auth_test.usuarios TO $principal;"
}
foreach ($case in $invalidGrants.GetEnumerator()) {
    AdminSql "$reset $($case.Value)"
    CheckProfile "Rechaza $($case.Key)" $grantError
}

AdminSql "$reset $dml CREATE ROLE cpanel_probe; GRANT cpanel_probe TO $principal;"
CheckProfile 'Rechaza un rol asignado aunque esté inactivo' $grantError
AdminSql "REVOKE cpanel_probe FROM $principal; DROP ROLE cpanel_probe; GRANT SELECT ON ensenadaXauthYtest.* TO PUBLIC;"
CheckProfile 'Rechaza un grant heredado de PUBLIC' $grantError
AdminSql 'REVOKE SELECT ON ensenadaXauthYtest.* FROM PUBLIC; CREATE VIEW auth_dml_probe AS SELECT 1 AS id;'
CheckProfile 'Rechaza objetos ajenos al esquema en la base dedicada' 'sin tablas ni vistas ajenas'
AdminSql 'DROP VIEW auth_dml_probe;'
CheckProfile 'Aprueba nuevamente con solo DML y el esquema dedicado'

docker exec $app vendor/bin/phpunit -c phpunit.http.mysql.xml
if ($LASTEXITCODE -ne 0) { throw 'Falló la suite HTTP de MariaDB.' }
