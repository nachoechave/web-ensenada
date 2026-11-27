# Período administrativo de staging en iWinds

El período dura de **1 a 7 días**, vence en UTC y se renueva mediante un comando explícito. Usa el mismo marcador administrativo que la ventana corta con un formato firmado distinto. Conserva Basic de cPanel, las sesiones individuales, roles, revocación, CSRF y el bloqueo de Bearer. Opera exclusivamente en el staging protegido `prueba.ensenada.gov.ar` con datos sintéticos. El marcador de activación completa sigue separado.

## Instalación

Con la API cerrada, subí el ZIP y su SHA-256 a `/home/ensenada/iwinds-admin-period`, fuera de `public_html`. El instalador comprueba que estén los archivos de los paquetes de cierre e imágenes ya instalados. Hace un respaldo de los dos PHP privados que actualiza. Conservá el Basic de cPanel y el comprobante privado de rotación vigente. La instalación y la reversión pueden repetirse. Si PHP web tiene OPcache sin revalidación, usá la actualización de OPcache del hosting y verificá HTTPS antes de abrir el período para el equipo.

```sh
umask 077
cd /home/ensenada/iwinds-admin-period &&
sha256sum -c web-ensenada-iwinds-admin-period.zip.sha256 &&
unzip -n web-ensenada-iwinds-admin-period.zip &&
sha256sum --strict -c SHA256SUMS &&
bash install.sh install
```

## Apertura, renovación y cierre

```sh
cd /home/ensenada/backend-laravel || exit 1
PHP83=/opt/cpanel/ea-php83/root/usr/bin/php
"$PHP83" deploy/iwinds/provision-staging.php open-admin-extended 7 --synthetic-only
"$PHP83" deploy/iwinds/provision-staging.php status-admin-extended
```

Para renovar **antes** del vencimiento, ejecutá `"$PHP83" deploy/iwinds/provision-staging.php renew-admin-extended 7 --synthetic-only`. El nuevo vencimiento se calcula desde el momento de la renovación y debe extender el anterior. Si ya venció, cerrá el marcador anterior y abrí un período nuevo. La apertura y la renovación verifican los grants, el contenido institucional y el comprobante de rotación del SUPER_ADMIN existente. No piden ni cambian contraseñas. Los demás marcadores deben estar ausentes.

Para cerrar de inmediato desde cualquier directorio, incluso si MariaDB está fuera de servicio:

```sh
/opt/cpanel/ea-php83/root/usr/bin/php /home/ensenada/backend-laravel/deploy/iwinds/provision-staging.php close-admin-extended
```

El cierre retira el marcador administrativo y cancela una apertura o renovación que todavía esté verificando la base. También cierra una ventana administrativa corta. Las solicitudes nuevas quedan bloqueadas. Las operaciones que ya empezaron pueden terminar. El período de staging y la sesión de cada persona vencen por separado. Una sesión vigente puede volver a servir al reabrir hasta que expire, se revoque o se cierre.

## Comprobaciones HTTPS

```sh
base=https://prueba.ensenada.gov.ar
read -r -p 'Usuario Basic de cPanel: ' basic
curl -sS -o /dev/null -w '%{http_code}\n' "$base/"
curl -sS --user "$basic" -o /dev/null -w '%{http_code}\n' "$base/api/auth/me"
curl -sS --user "$basic" -o /dev/null -w '%{http_code}\n' "$base/api/admin/noticias"
curl -sS --user "$basic" -o /dev/null -w '%{http_code}\n' "$base/api/admin/usuarios"
```

Sin Basic, el primer comando debe devolver `401`. Con Basic pero sin sesión Laravel, los otros tres devuelven `401` durante el período y `503` después del cierre o vencimiento. Desde una ventana privada, autenticá un usuario PRENSA existente, creá un borrador sintético y subí una foto. Verificá que PRENSA reciba `403` en Usuarios y Hacienda, y que, tras cerrar la sesión, Noticias administrativa vuelva a `401`. Confirmá el cierre por HTTPS con Basic después del ensayo. El perfil `cpanel-dml` da permisos de escritura amplios en MariaDB. Conservar exclusivamente datos sintéticos en este staging. La admisión de datos municipales reales requiere permisos mínimos por tabla y una validación separada.

## Reversión

```sh
/opt/cpanel/ea-php83/root/usr/bin/php /home/ensenada/backend-laravel/deploy/iwinds/provision-staging.php close-admin-extended &&
cd /home/ensenada/iwinds-admin-period &&
bash install.sh rollback
```

Si hay otro marcador activo, cerralo con su procedimiento antes de revertir. El instalador restaura los dos PHP previos y verifica que la API quede cerrada. Comprobá `503` por HTTPS con Basic. Las ventanas cortas se pueden volver a abrir luego con el comando original.
