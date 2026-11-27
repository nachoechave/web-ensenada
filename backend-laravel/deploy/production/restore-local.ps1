param(
    [Parameter(Mandatory=$true)][string]$Backup,
    [Parameter(Mandatory=$true)][string]$Proof,
    [string]$LaravelPath=(Join-Path $PSScriptRoot '../..'),
    [string]$VendorPath='',
    [string]$PhpImage='ensenada-auth-stage2-app:local'
)
# LOCAL Docker only. Does not contact iWinds, publish ports, restore PHP, or use a persistent volume.
$ErrorActionPreference='Stop'
$taskCode=(Resolve-Path $LaravelPath).Path
if(!$VendorPath){$VendorPath=Join-Path $taskCode 'output/dependencies/vendor'}
$taskVendor=(Resolve-Path $VendorPath).Path
$taskBackup=(Resolve-Path $Backup).Path
$taskProof=[IO.Path]::GetFullPath($Proof)
if((Test-Path $taskProof)-or !(Test-Path (Join-Path $taskCode 'app/Content/SiteContentDocument.php'))-or !(Test-Path (Join-Path $taskVendor 'autoload.php'))){throw 'Código confiable, vendor verificado y salida nueva requeridos.'}
$taskManifest=Get-Content -Raw (Join-Path $taskBackup 'backup.json') | ConvertFrom-Json
foreach($item in $taskManifest.archives.psobject.Properties){
    if($item.Name-notmatch '^(database.sql|production-private.tar|staging-private.tar|recovery-private.tar|control-private.tar|web-rollback.tar)$'){throw 'Archivo de respaldo inesperado.'}
    if((Get-FileHash -Algorithm SHA256 -LiteralPath (Join-Path $taskBackup $item.Name)).Hash.ToLowerInvariant()-ne $item.Value){throw 'SHA de respaldo incorrecto.'}
}
function Docker-Local { & docker @args; if($LASTEXITCODE-ne 0){throw "Restauración aislada falló: $($args[0])"} }
$taskId=[guid]::NewGuid().ToString('N')
$taskNetwork='ensenada-restore-'+$taskId
$taskContainer='ensenada-restore-db-'+$taskId
$taskTemp=Join-Path ([IO.Path]::GetTempPath()) ('ensenada-restore-'+$taskId)
New-Item -ItemType Directory $taskTemp | Out-Null
$taskRootPassword=[Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(32)).ToLowerInvariant()
$taskSchemaPassword=[Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(32)).ToLowerInvariant()
$taskInit=Join-Path $taskTemp 'init.sql'
$taskSql="CREATE USER 'ensenada_web_schema'@'127.0.0.1' IDENTIFIED BY '$taskSchemaPassword';`nCREATE USER 'ensenada_web_schema'@'localhost' IDENTIFIED BY '$taskSchemaPassword';`nGRANT ALL ON ensenada_web.* TO 'ensenada_web_schema'@'127.0.0.1';`nGRANT ALL ON ensenada_web.* TO 'ensenada_web_schema'@'localhost';`n"
[IO.File]::WriteAllText($taskInit,$taskSql)
try {
    Docker-Local network create --internal $taskNetwork
    Docker-Local run -d --name $taskContainer --hostname ensenada-shared-restore-disposable --network $taskNetwork --tmpfs /var/lib/mysql --tmpfs /tmp -e "MARIADB_ROOT_PASSWORD=$taskRootPassword" -e MARIADB_DATABASE=ensenada_web --mount "type=bind,source=$taskInit,target=/docker-entrypoint-initdb.d/10-accounts.sql,readonly" mariadb:10.11.19 --skip-log-bin --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_520_ci
    $ready=$false
    for($i=0;$i-lt 90;$i++){& docker exec $taskContainer healthcheck.sh --connect --innodb_initialized *> $null;if($LASTEXITCODE-eq 0){$ready=$true;break};Start-Sleep -Seconds 1}
    if(!$ready){throw 'MariaDB descartable no inició.'}
    Docker-Local run --rm --network "container:$taskContainer" --mount "type=bind,source=$taskBackup,target=/backup,readonly" -e "MYSQL_PWD=$taskRootPassword" --entrypoint sh mariadb:10.11.19 -c 'if ! mariadb -h127.0.0.1 -uroot ensenada_web < /backup/database.sql >/dev/null 2>/tmp/restore-error; then echo "BLOQUEADO: importacion SQL local rechazada; no se muestran filas ni hashes." >&2; exit 1; fi'
    $taskName=Split-Path -Leaf $taskProof
    $taskDirectory=Split-Path -Parent $taskProof
    Docker-Local run --rm --network "container:$taskContainer" --read-only --tmpfs /tmp --tmpfs /restore --mount "type=bind,source=$taskCode,target=/source,readonly" --mount "type=bind,source=$taskVendor,target=/source/vendor,readonly" --mount "type=bind,source=$taskBackup,target=/backup,readonly" --mount "type=bind,source=$taskDirectory,target=/proof" -e ENSENADA_ISOLATED_RESTORE=authorized-disposable -e "RESTORE_SCHEMA_PASSWORD=$taskSchemaPassword" --entrypoint php $PhpImage /source/deploy/production/verify-restore.php /backup "/proof/$taskName"
    Write-Output "Prueba local generada: $taskProof. El hosting permanece sin cambios."
} finally {
    # Only this newly named container/network and our one temporary init file, never user data.
    & docker stop $taskContainer *> $null
    & docker rm $taskContainer *> $null
    & docker network rm $taskNetwork *> $null
    if(Test-Path -LiteralPath $taskInit){Remove-Item -LiteralPath $taskInit}
    if(Test-Path -LiteralPath $taskTemp){Remove-Item -LiteralPath $taskTemp}
}
