# Segundo incremento — producción con cpanel-dml aceptado explícitamente

Aplicar sobre el incremento instalado SHA `7fde8cd1577de52689e26f1227f356aedb6db0ad0e45221ce3dccbfc4f9183da`, staging todavía propietario (`legacy`), producción cerrada. Conserva el backend privado, .env, APP_KEY/JWT, datos, archivos, vendor y puente preparado. El perfil **per-table** sigue siendo el predeterminado; instalar este ZIP no elige cpanel-dml ni abre la API. No se modifica el esquema, Apache, WordPress, DNS, correo ni otras bases/subdominios.

## Instalar sin tráfico

Subir ZIP y .sha256 fuera de public_html. Extraer en una carpeta nueva privada:

```sh
umask 077
cd /home/ensenada
sha256sum -c web-ensenada-iwinds-cpanel-dml.zip.sha256
mkdir -m 700 iwinds-cpanel-dml
unzip -q web-ensenada-iwinds-cpanel-dml.zip -d iwinds-cpanel-dml
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
P=/home/ensenada/backend-laravel-produccion
H=$P/deploy/production/handover.php
S=/home/ensenada/respaldos_privados/schema.cnf
"$PHP" /home/ensenada/iwinds-cpanel-dml/update.php install-private
"$PHP" "$H" status
```

No volver a instalar Laravel ni ejecutar init-env, migraciones o aprovisionamiento. No usar `prepare-web`: el puente del primer incremento se conserva idéntico. El instalador verifica toda la instalación previa y rechaza archivos ajenos; una diferencia no autoriza forzar sobrescrituras.

## Permisos reales y decisión responsable

Las cuentas informadas son runtime con cuatro DML y schema con ALL, ambas limitadas a esta base y sin GRANT OPTION. Este incremento **no ejecuta GRANT/REVOKE** y no necesita que puedan delegar. No ejecutar grants-production.sql para este perfil. El validador exige exactamente las capacidades declaradas, USAGE global sin capacidades y alcance literal `ensenada\_web` en SHOW GRANTS. Rechaza wildcard sin escapar, otros privilegios/objetos/bases, roles SQL y delegación. Nunca imprime metadatos de autenticación de SHOW GRANTS. Si los grants reales difieren, bloquear; no normalizar un comodín peligroso para aprobarlo.

Leer completa [CPANEL-DML-RISK.md](CPANEL-DML-RISK.md). Una autoridad identificada debe aceptar el riesgo de modificación/borrado de usuarios, sesiones, mutexes, contenidos y auditoría mediante SQL directo, incluyendo escalamiento de roles. No se garantiza inmutabilidad frente a credenciales o UID comprometidos.

Copiar el formulario vacío y completarlo mediante editor privado, modo 0600. No contiene ni pide contraseñas. Mantener schema.cnf privado 0600 con la credencial existente de esquema, fuera del .env web.

```sh
cp "$P/deploy/production/cpanel-dml-risk.example.json" /home/ensenada/respaldos_privados/aceptacion-dml.json
chmod 600 /home/ensenada/respaldos_privados/aceptacion-dml.json
sha256sum "$P/deploy/production/CPANEL-DML-RISK.md"
```

Completar `policy_sha256`, nombre/función del responsable, referencia de autorización, custodia externa, monitoreo e incidentes, y confirmar individualmente los riesgos. Los falsos iniciales bloquean. No aprobar masivamente datos sintéticos con esta aceptación: la revisión de contenidos es independiente.

```sh
"$PHP" "$H" configure-profile --profile=cpanel-dml --schema-config="$S" --risk-review=/home/ensenada/respaldos_privados/aceptacion-dml.json --accept-cpanel-dml-production-risk
"$PHP" "$H" status
```

Se conserva un comprobante privado ligado al estado firmado. Ninguna variable .env selecciona el perfil. Se puede retirar la elección antes del corte con `configure-profile --profile=per-table`; no modifica los permisos MySQL y, si siguen siendo DML amplios, el perfil estricto bloqueará el corte.

## Transferencia, respaldo y restauración obligatorios

Seguir [SHARED-DATABASE.md](SHARED-DATABASE.md) para revisión provisional, cierre de staging, aparcado, rotación MySQL, copia privada y auditoría definitiva, **omitiendo solamente sus instrucciones anteriores de exigir grants por tabla**. No ejecutar el SQL de grants bajo cpanel-dml. Mantener todas las demás autorizaciones y la espera real de 125 segundos. La copia y el respaldo no sobrescriben históricos. Mantener .htaccess principal intacto.

```sh
"$PHP" /home/ensenada/backend-laravel/deploy/iwinds/provision-staging.php suspend-staging-api
"$PHP" "$H" pause-stage --authorize-staging-pause --confirm-exclusive-account-use --confirm-no-background-writers
"$PHP" "$H" rotate-production --authorize-mysql-password-rotation
sleep 125
"$PHP" "$H" copy-storage --confirm-account-quota
"$PHP" "$H" audit --schema-config="$S" --out=/home/ensenada/respaldos_privados/revision-dml.json
"$PHP" "$H" backup --schema-config="$S" --out=/home/ensenada/respaldos_privados/corte-dml-01
```

Descargar/custodiar cifrado el respaldo completo y verificar su restauración local aislada con `restore-local.ps1`. Subir su restore-proof.json con modo 0600; no usar la prueba sintética del desarrollo. Clasificar y justificar manualmente cada registro/archivo de revision-dml.json y conservar huellas/epoch. Solo los contenidos oficiales aprobados y usuarios autorizados pueden quedar públicos/activos.

```sh
"$PHP" "$H" approve-data --schema-config="$S" --review=/home/ensenada/respaldos_privados/revision-dml-aprobada.json --authorize-reviewed-data
"$PHP" "$H" attest-restore --proof=/home/ensenada/respaldos_privados/restore-proof.json --authorize-restore-proof
"$PHP" "$H" audit-checkpoint --schema-config="$S" --out=/home/ensenada/respaldos_privados/auditoria-corte.json
sha256sum /home/ensenada/respaldos_privados/auditoria-corte.json
```

Copiar el checkpoint y su SHA a una custodia externa al hosting. La herramienta no puede comprobar ese acto humano: debe quedar documentado con una referencia real y responsable. No declarar la copia sin haberla realizado.

```sh
"$PHP" "$H" attest-audit-copy --schema-config="$S" --checkpoint=/home/ensenada/respaldos_privados/auditoria-corte.json --offsite-reference=REFERENCIA_REAL_DE_CUSTODIA --authorize-offsite-audit-copy
"$PHP" "$P/artisan" config:cache
"$PHP" "$P/artisan" route:cache
"$PHP" "$P/deploy/iwinds/schema-check.php" --privilege-profile=cpanel-dml
"$PHP" "$P/deploy/production/control.php" check
```

## Activación posterior, separada de instalar

Solo con aceptación real, esquema/datos aprobados, permisos exactos, almacenamiento completo, respaldo restaurado, copia externa y límites PHP web comprobados:

```sh
"$PHP" "$H" activate --schema-config="$S" --authorize-session-revocation --authorize-production --accept-cpanel-dml-production-risk
```

La bandera adicional es obligatoria incluso para reabrir una API cerrada. No modifica Apache. La decisión de aplicar la reescritura selectiva sigue siendo independiente; no está automatizada por este incremento. No activar tráfico para subsanar un control fallido. La aceptación HTTPS utiliza el script existente con todos sus IDs/URLs aprobados y archivo Basic privado, agregando `--accept-cpanel-dml-production-risk`. Debe demostrar producción operativa y staging 503 para API y archivos aun con Basic válido, además de los flujos administrativos reales.

## Monitoreo compensatorio

```sh
"$PHP" "$H" verify-audit --schema-config="$S" --checkpoint=/home/ensenada/respaldos_privados/auditoria-corte.json
"$PHP" "$H" audit-checkpoint --schema-config="$S" --previous=/home/ensenada/respaldos_privados/auditoria-corte.json --out=/home/ensenada/respaldos_privados/auditoria-siguiente.json
```

Cada salida debe ser nueva. El estado firmado recuerda el último checkpoint y exige continuar esa observación: no permite pasar uno anterior para ocultar filas ya observadas. Si se omite --previous, se verifica automáticamente el último registrado. Después de generar auditoria-siguiente.json, actualizar el monitoreo para verificar ese último archivo y custodiarlo externamente. Programar la frecuencia aprobada con cron privado por aplicación y monitorear su código de salida; no se instala cron automáticamente. Custodiar externamente los checkpoints nuevos y sus SHA; verificar siempre contra una copia previamente confiable. Actualizaciones/borrados de filas observadas o checkpoint alterado cierran la API y registran bloqueo de integridad. No se ofrece un comando de reset para ocultar un incidente. Un evento insertado y retirado entre dos observaciones puede ser indetectable; el intervalo debe aceptarse expresamente. Los respaldos completos periódicos siguen siendo necesarios para recuperar datos.

## Reversión

Antes del corte, con staging propietario y producción cerrada:

```sh
"$PHP" "$H" configure-profile --profile=per-table
"$PHP" /home/ensenada/iwinds-cpanel-dml/update.php rollback-code
```

Restaura el código del **primer incremento instalado**, no el backend original, sin cambiar datos, claves, grants, puente o Apache. No puede revertirse el código sobre una transferencia activa. Después de activar, cerrar/pausar producción, hacer el respaldo más reciente y recuperar exclusivamente staging con los datos actuales según SHARED-DATABASE.md, agregando la aceptación de riesgo:

```sh
"$PHP" -n "$P/deploy/production/control.php" close
"$PHP" "$H" pause-production --authorize-production-pause
"$PHP" "$H" backup --schema-config="$S" --out=/home/ensenada/respaldos_privados/reversion-dml-01
"$PHP" "$H" recover-stage --minutes=30 --authorize-recovery-with-current-data --authorize-mysql-password-rotation --authorize-session-revocation --accept-cpanel-dml-production-risk
```

El rollback no importa un dump viejo: conserva escrituras y archivos nuevos, rota otra vez la credencial y revoca sesiones sin cambiar contraseñas de usuarios ni APP_KEY/JWT. No utilizar recuperación para ocultar una alerta de integridad. El tráfico y las reglas selectivas se revierten separadamente, con el diario y SHA originales, manteniendo WordPress intacto.

## Bloqueos

No se accedió a iWinds ni se aprobaron sus datos. Siguen pendientes la aceptación formal, permisos literales exactos, custodia externa efectiva, ensayo de restauración del respaldo real, revisión oficial del contenido y aceptación HTTPS/administrativa. Ninguna prueba local convierte cpanel-dml en equivalente al perfil estricto. El mismo usuario MySQL no puede asegurar por sí solo integridad de auditoría, impedir escalamiento tras SQL comprometido o limitar DML por tabla.
