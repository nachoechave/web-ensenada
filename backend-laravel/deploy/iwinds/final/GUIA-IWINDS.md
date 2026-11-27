# Web Ensenada · instalación final en staging

Subí el ZIP y su archivo `.sha256` a `/home/ensenada/iwinds-final`, fuera de `public_html`. Dejá cerradas las tres ventanas de API. Ejecutá desde la Terminal de cPanel.

```sh
umask 077
cd /home/ensenada/iwinds-final &&
sha256sum -c web-ensenada-iwinds-final.zip.sha256 &&
unzip -n web-ensenada-iwinds-final.zip &&
sha256sum --strict -c SHA256SUMS &&
bash install.sh
```

El instalador coteja los nueve componentes privados, prepara los directorios de fotos con permisos 700 y verifica la caché de rutas GET y HEAD para JPEG, PNG y WebP. Regenera la caché si falta alguna ruta. Guarda el primer índice en `/home/ensenada/iwinds-final-state/index.original.html`, instala los bundles y cambia el índice al final. Se puede repetir. Conservá también los respaldos previos de Angular y Laravel. Un archivo privado distinto o una colisión de bundles detiene la instalación.

## Respaldo y aceptación HTTPS

Usá una cuenta exportadora existente y autorizada con acceso al esquema y a su trigger. El comando solicita su contraseña en la terminal. Cerrá las ventanas de API y pausá otras escrituras en la base o almacenamiento durante la copia.

```sh
cd /home/ensenada/iwinds-final || exit 1
read -r -p 'Usuario exportador autorizado existente: ' exportador
bash backup.sh create "$exportador"
```

Guardá la ruta «Copia privada» y copiá su contenido cifrado fuera del hosting. El respaldo guarda las 12 tablas, el trigger con su definidor y `storage/app/private`. Conservá además una copia privada de la configuración vigente y de las reglas y archivos públicos del vhost, junto con el respaldo Laravel ya existente. Para demostrar recuperabilidad, verificá hashes y ensayá la restauración de la **misma copia** sobre un destino descartable ya autorizado y vacío. Importá con `gzip -dc ensenada_web.sql.gz | mariadb --password --database="$DESTINO_DESCARTABLE"` y extraé `archivos.tar.gz` en una raíz aislada con `tar -xzf archivos.tar.gz -C "$RAIZ_DESCARTABLE"`. El destino debe tener el definidor autorizado del trigger y una cuenta de restauración capaz de crearlo. Verificá tablas, filas, trigger, revocación y descargas desde el destino recuperado. Guardá el resultado y el SHA-256 ensayado. La verificación de archivos por sí sola no acredita restauración. [MariaDB, opciones de respaldo y restauración](https://mariadb.com/docs/server/clients-and-utilities/backup-restore-and-import-clients/mariadb-dump)

Con **datos exclusivamente sintéticos** y sin otros editores activos, ejecutá la aceptación. Introducí las credenciales de Basic y del SUPER_ADMIN vigente cuando se soliciten. El script comprueba la protección y el PHP web antes de abrir una ventana administrativa temporal. Si la memoria es menor a 256M o la carga o POST son menores a 32M y 40M, corregí la configuración solo en ese vhost y repetí. Los valores de CLI se aplican únicamente a los comandos del paquete. [PHP, límites de cargas](https://www.php.net/manual/en/ini.core.php#ini.upload-max-filesize)

```sh
cd /home/ensenada/iwinds-final || exit 1
read -r -p 'Ruta de la copia privada verificada: ' copia
bash accept.sh --write-synthetic "$copia"
```

La aceptación verifica una carga JPEG de 6.000 × 4.000 y 30 MiB, PNG y WebP con transparencia, el máximo de 1.920 px, MIME y descargas GET/HEAD. Verifica CSRF, sesión y administración, publica una noticia sintética, guarda portada, carrusel y galería, archiva la noticia y restaura el JSON institucional. Las fotos sintéticas y la auditoría quedan registradas. Ante un fallo, revisá la evidencia privada impresa antes de repetir. Al salir, retira el diagnóstico temporal y cierra la ventana creada por el script. Probá también los formularios de Noticias y Sitio en Chromium con HTTPS y cookies y confirmá que se ven las fotos tras guardar y recargar. Una aprobación del script valida el contrato HTTPS, mientras que este paso comprueba el navegador.

## Permisos mínimos por tabla

Con la API cerrada, un **concedente existente autorizado** debe confirmar que `SHOW GRANTS FOR 'ensenada_web_app'@'localhost'` muestra el perfil exacto de staging en `ensenada\_web`.`*` y que el usuario efectivo de Laravel coincide con la cuenta indicada. Guardá los grants previos en privado. Aplicá primero los grants por tabla y luego retirales los permisos a nivel de base. Conservá el definidor del trigger y su cuenta. Usá la contraseña del concedente únicamente en el prompt.

```sh
cd /home/ensenada/iwinds-final || exit 1
read -r -p 'Usuario concedente autorizado existente: ' concedente
mariadb --no-defaults --protocol=socket --user="$concedente" --password < schema-grants.sql &&
mariadb --no-defaults --protocol=socket --user="$concedente" --password < revoke-database-dml.sql &&
/opt/cpanel/ea-php83/root/usr/bin/php /home/ensenada/backend-laravel/deploy/iwinds/schema-check.php
```

El último comando debe aprobar sin `--privilege-profile=cpanel-dml`. Repetí la aceptación sintética y verificá la revocación con un operador de prueba. Si falla algún paso, dejá la API cerrada y pedile al concedente revisar los grants efectivos. MariaDB aplica cada cambio de privilegios por separado. Si el panel solo ofrece permisos de base y ninguna cuenta existente puede conceder permisos por tabla, el corte queda bloqueado. [cPanel, administración de privilegios](https://docs.cpanel.net/cpanel/databases/manage-my-databases/)

## Aprobación y reversión

- [ ] Instalación idempotente, caché actualizada, Basic vigente y API cerrada al finalizar.
- [ ] Aceptación HTTPS y prueba de formularios en Chromium aprobadas. Límites efectivos de PHP web y cargas de los tres formatos verificados.
- [ ] `schema-check.php` aprueba los permisos por tabla. El trigger revoca sesiones y la cuenta de ejecución conserva su acceso operativo.
- [ ] Copia de datos, archivos, configuración y vhost recuperable. Restauración de la copia ensayada y registrada.
- [ ] Dominio definitivo, vhost, certificados, raíz pública, configuración Laravel y tratamiento de datos reales confirmados y autorizados. El puente y Laravel actuales solo autorizan staging. Antes de un corte público se necesita una configuración de producción revisada y validada para ese dominio. `open-full` solo sirve para la ventana del subdominio protegido.

Si falla una condición, mantené el dominio definitivo sin cambios. Para cerrar una ventana que quedó abierta, mové sus marcadores a un directorio privado nuevo y verificá `503` con Basic antes de revertir. El rollback automático restaura Angular y conserva los lectores WebP para cualquier foto ya guardada.

```sh
cd /home/ensenada/iwinds-final || exit 1
umask 077
closed=$(mktemp -d /home/ensenada/iwinds-final-closed.XXXXXXXX) || exit 1
for marker in .iwinds-api-public-probe .iwinds-api-admin-probe .iwinds-api-enabled; do
  file="/home/ensenada/backend-laravel/$marker"
  if test -e "$file" || test -L "$file"; then mv -- "$file" "$closed/" || exit 1; fi
done
bash rollback.sh
```

Antes de restaurar PHP desde `laravel-original.tar`, revisá si la base ya referencia WebP. Si existen esas referencias, una reversión previa a WebP requiere restaurar la pareja de base y archivos de la misma copia autorizada. Para el futuro corte definitivo, conservá el vhost, índice y configuración anteriores y registrá quién puede restituirlos. Ante un fallo, cerrá primero el tráfico de Laravel y recuperá el vhost previo. La restauración de datos y la eventual reversión de permisos son acciones separadas y requieren autorización.
