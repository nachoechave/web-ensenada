param([switch]$KeepContainers, [ValidateSet('per-table','cpanel-dml')][string]$Profile='per-table')
$ErrorActionPreference='Stop'
$taskLaravel=(Resolve-Path "$PSScriptRoot/../..").Path
$taskRepository=(Resolve-Path "$taskLaravel/..").Path
$taskBaseline=(Resolve-Path "$taskRepository/../../output/iwinds-production-cutover-20261127/web-ensenada-iwinds-production.zip").Path
$taskPrior=(Resolve-Path "$taskRepository/../../output/iwinds-shared-database-20261127/web-ensenada-iwinds-shared-database.zip").Path
$taskOldProfile=$env:ENSENADA_TEST_DATABASE_PROFILE
$taskOldSql=$env:ENSENADA_TEST_SQL_PROFILE
$env:ENSENADA_TEST_DATABASE_PROFILE=$Profile
$env:ENSENADA_TEST_SQL_PROFILE=if($Profile-eq 'cpanel-dml'){'accounts-dml.sql'}else{'accounts.sql'}
$taskOutput=Join-Path $taskLaravel 'output/shared-update'
New-Item -ItemType Directory -Force $taskOutput | Out-Null
function Run-Docker { & docker @args; if($LASTEXITCODE -ne 0){throw "Docker falló: $($args[0]) $($args[1])"} }
Push-Location $taskLaravel
try {
    # Only this named disposable project, all DB storage is tmpfs; no persistent volumes or published ports.
    Run-Docker compose -f tests/Production/compose.yaml down --volumes
    Run-Docker run --rm --mount "type=bind,source=$taskLaravel,target=/source" --mount "type=bind,source=$taskPrior,target=/base.zip,readonly" --entrypoint sh ensenada-auth-stage2-app:local -c 'php /source/deploy/production/build-shared-update.php /source /base.zip /tmp/increment.zip && cp /tmp/increment.zip /source/output/shared-update/web-ensenada-iwinds-shared-database.zip'
    Run-Docker compose -f tests/Production/compose.yaml up -d apache
    $ready=$false
    for($i=0;$i -lt 90;$i++) {
        $running=& docker inspect --format '{{.State.Running}}' ensenada-production-cutover-tests-apache-1
        if($running-ne 'true'){& docker compose -f tests/Production/compose.yaml logs --tail 12 apache; throw 'Apache descartable terminó antes de estar listo.'}
        $status=& docker compose -f tests/Production/compose.yaml exec -T apache curl -ks --connect-timeout 2 --max-time 5 -o /dev/null -w '%{http_code}' -u staging:synthetic-basic-only https://prueba.ensenada.gov.ar/api/site-content 2>$null
        if($status -eq '200'){$ready=$true;break}; Start-Sleep -Seconds 1
    }
    if(!$ready){throw 'Apache no inició; inspeccionar logs.'}
    if($Profile-eq 'per-table'){
    Run-Docker compose -f tests/Production/compose.yaml exec -T -e MYSQL_PWD=synthetic-disposable-root mysql-auth mariadb -uroot -e "GRANT SELECT,INSERT,UPDATE,DELETE ON ensenada_web.* TO 'ensenada_web_app'@'127.0.0.1'"
    Run-Docker compose -f tests/Production/compose.yaml exec -T -u www-data apache php /source/tests/SharedDatabase/reject-dml.php
    (Get-Content -Raw deploy/production/grants-production.sql).Replace("@'localhost'","@'127.0.0.1'") | & docker compose -f tests/Production/compose.yaml exec -T -e MYSQL_PWD=synthetic-disposable-root mysql-auth mariadb -uroot ensenada_web
    if($LASTEXITCODE-ne 0){throw 'Perfil mínimo por tabla rechazado.'}
    }
    Run-Docker compose -f tests/Production/compose.yaml exec -T -u www-data apache php /source/tests/SharedDatabase/configure-dml.php
    Run-Docker compose -f tests/Production/compose.yaml exec -T -u www-data apache php /source/tests/SharedDatabase/pre.php
    $taskBackup=Join-Path $taskOutput ('restore-'+[guid]::NewGuid().ToString('N'))
    New-Item -ItemType Directory $taskBackup | Out-Null
    $taskNames=@('backup.json','database.sql','production-private.tar','staging-private.tar','control-private.tar','web-rollback.tar')
    foreach($name in $taskNames){ & docker exec ensenada-production-cutover-tests-apache-1 cat "/home/ensenada/respaldos_privados/corte-01/$name" > (Join-Path $taskBackup $name); if($LASTEXITCODE-ne 0){throw 'Extracción de respaldo fallida.'} }
    $restoreContainer='ensenada-shared-restore-'+[guid]::NewGuid().ToString('N').Substring(0,12)
    Run-Docker run -d --name $restoreContainer --hostname ensenada-shared-restore-disposable --network ensenada-production-cutover-tests_isolated --tmpfs /var/lib/mysql --tmpfs /tmp -e MARIADB_ROOT_PASSWORD=synthetic-disposable-root -e MARIADB_DATABASE=ensenada_web --mount "type=bind,source=$taskLaravel/tests/SharedDatabase/restore-init.sql,target=/docker-entrypoint-initdb.d/10-accounts.sql,readonly" mariadb:10.11.19 --skip-log-bin --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_520_ci
    try {
        $healthy=$false
        for($i=0;$i-lt 90;$i++){ & docker exec $restoreContainer healthcheck.sh --connect --innodb_initialized *> $null;if($LASTEXITCODE-eq 0){$healthy=$true;break};Start-Sleep -Seconds 1 }
        if(!$healthy){throw 'MariaDB aislado no inició.'}
        Run-Docker run --rm --network "container:$restoreContainer" --mount "type=bind,source=$taskBackup,target=/backup,readonly" -e MYSQL_PWD=synthetic-disposable-root --entrypoint sh mariadb:10.11.19 -c 'mariadb -h127.0.0.1 -uroot ensenada_web < /backup/database.sql'
        $proofFile=Join-Path $taskOutput 'restore-proof.json'
        if(Test-Path $proofFile){Move-Item -LiteralPath $proofFile -Destination ($proofFile+'.'+[guid]::NewGuid().ToString('N'))}
        Run-Docker run --rm --network "container:$restoreContainer" --mount "type=bind,source=$taskLaravel,target=/source,readonly" --mount "type=bind,source=$taskLaravel/output/dependencies/vendor,target=/source/vendor,readonly" --mount "type=bind,source=$taskBackup,target=/backup,readonly" --mount "type=bind,source=$taskOutput,target=/proof" --tmpfs /restore -e ENSENADA_ISOLATED_RESTORE=authorized-disposable -e RESTORE_SCHEMA_PASSWORD=synthetic-schema-only --entrypoint php ensenada-auth-stage2-app:local /source/deploy/production/verify-restore.php /backup /proof/restore-proof.json
    } finally { Run-Docker stop $restoreContainer; Run-Docker rm $restoreContainer }
    Get-Content -Raw (Join-Path $taskOutput 'restore-proof.json') | & docker exec -i ensenada-production-cutover-tests-apache-1 sh -c 'umask 077; cat > /home/ensenada/respaldos_privados/restore-proof.json'
    if($LASTEXITCODE-ne 0){throw 'Transferencia de prueba fallida.'}
    Run-Docker compose -f tests/Production/compose.yaml exec -T apache sh -c 'chown www-data:www-data /home/ensenada/respaldos_privados/restore-proof.json && chmod 600 /home/ensenada/respaldos_privados/restore-proof.json'
    Run-Docker compose -f tests/Production/compose.yaml exec -T -u www-data apache php /source/tests/SharedDatabase/post.php
    Run-Docker compose -f tests/Production/compose.yaml exec -T -u www-data apache php /source/tests/Production/http.php
    Run-Docker compose -f tests/Production/compose.yaml exec -T -u www-data apache php /source/tests/Production/operations.php
    Run-Docker compose -f tests/Production/compose.yaml exec -T -u www-data apache php /source/tests/SharedDatabase/audit-integrity.php
    Run-Docker compose -f tests/Production/compose.yaml exec -T -u www-data apache php /source/tests/SharedDatabase/reset-actors.php
    $taskBrowser=Join-Path $taskLaravel 'output/browser-production'
    New-Item -ItemType Directory -Force $taskBrowser | Out-Null
    Run-Docker run --rm --mount "type=bind,source=$taskLaravel,target=/source,readonly" --mount "type=bind,source=$taskBrowser,target=/results" --entrypoint php ensenada-production-apache:local /source/tests/Production/fixtures.php
    Run-Docker run --rm --network container:ensenada-production-cutover-tests-mysql-auth-1 --mount "type=bind,source=$taskLaravel,target=/source,readonly" --mount "type=bind,source=$taskBrowser,target=/results" --entrypoint node ensenada-auth-stage2-browser:local /source/tests/Production/browser.cjs
    Run-Docker compose -f tests/Production/compose.yaml exec -T -u www-data apache php /source/tests/Production/photos.php
    Run-Docker compose -f tests/Production/compose.yaml exec -T -u www-data apache php /source/tests/SharedDatabase/acceptance.php
    Run-Docker compose -f tests/Production/compose.yaml exec -T -u www-data apache php /source/tests/SharedDatabase/rollback.php
} finally {
    if(!$KeepContainers){Run-Docker compose -f tests/Production/compose.yaml down --volumes}
    $env:ENSENADA_TEST_DATABASE_PROFILE=$taskOldProfile
    $env:ENSENADA_TEST_SQL_PROFILE=$taskOldSql
    Pop-Location
}
