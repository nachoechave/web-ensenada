# Carga de fotos en iWinds

El incremento parte del paquete de cierre de Web Ensenada. Actualiza las cargas de Noticias, el carrusel y las imágenes de Intendencia. El ZIP contiene los archivos PHP, la política privada de ImageMagick, el `index.html` de Angular para el sitio estático y sus bundles con hash. `FILES` y `SHA256SUMS` enumeran los componentes. Para reconstruirlo con PHP 8.3 y `ext-zip`, usar `php deploy/iwinds/build-photos.php /ruta/dist/frontend/browser` sobre un build de producción de Angular. Para compilar sin red se desactivó únicamente la incrustación de fuentes externas en la copia temporal del build. La hoja CSS conserva la referencia original a la fuente.

## Formatos, almacenamiento y seguridad

| Entrada | Tamaño máximo | Resolución máxima |
| --- | --- | --- |
| JPEG | 30 MiB | 48 megapíxeles y 12.000 px por lado |
| PNG y WebP estático | 30 MiB | 24 megapíxeles y 12.000 px por lado |

Laravel comprueba la cabecera, el tamaño y las dimensiones antes de decodificar. Usa un proceso de ImageMagick con un solo hilo, un plazo total de 20 segundos, un límite de espacio de direcciones de 512 MiB, un presupuesto de caché de imagen de 48 MiB y hasta 192 MiB de disco temporal. Un bloqueo local serializa la optimización de fotos en ambos módulos. El límite de resolución puede ser superior a lo que una foto compleja alcance a procesar dentro de esos recursos. La API devuelve un error legible en ese caso. Las cargas de documentos de Hacienda y Proveedores usan sus controles propios. [ImageMagick, opciones de conversión](https://imagemagick.org/command-line-options/)

La conversión aplica la orientación disponible antes de quitar metadatos, mantiene las proporciones y la transparencia, y reduce el lado mayor a un máximo de 1.920 px. WebP usa calidad 82. Si la política efectiva o el binario no pueden escribir WebP, las fotos JPEG salen como JPEG y las demás se escriben en PNG cuando se pueden decodificar. Solo se publica el archivo optimizado, con UUID, tamaño y MIME coherentes. El límite de salida es 5 MiB. La segunda pasada baja la dimensión a 1.440 px si hace falta. Los archivos JPG y PNG anteriores conservan su URL y su MIME. El almacenamiento sigue fuera de `public_html`. Se limpian los temporales de optimización y escritura aun ante errores. El trabajo programado de limpieza de Spring Boot pertenece a su propio almacenamiento. Laravel conserva su almacenamiento separado y este incremento mantiene el alcance actual de limpieza. [ImageMagick, codificación WebP](https://imagemagick.org/webp/)

Noticias conserva portada y hasta seis fotos por artículo. El editor de sitio permite hasta seis imágenes nuevas por carrusel o galería, sin recortar listas históricas. Los decodificadores institucionales heredados de GIF, BMP, WBMP y TIFF se conservan para integraciones existentes. Angular ofrece JPEG, PNG y WebP. HEIC y HEIF quedan deshabilitados. Su aparición en `convert -list format` indica que el binario tiene un códec, pero falta verificar la decodificación segura y acotada en el hosting.

## PHP web y capacidad de iWinds

Los valores informados para el subdominio son `upload_max_filesize=32M`, `post_max_size=40M` y `memory_limit=256M`. Alcanzan para una foto de 30 MiB por solicitud y el margen multipart. Verificar los valores del PHP web del subdominio en MultiPHP INI Editor y mediante una carga real protegida. El verificador `platform.php` comprueba CLI, GD, `proc_open`, ImageMagick y la política privada. Ejecutarlo con `/opt/cpanel/ea-php83/root/usr/bin/php`. CLI y PHP web pueden usar configuraciones distintas. Si se detecta un valor menor, estos son los valores propuestos **solo para el subdominio**, mediante la opción de cPanel correspondiente. [PHP, configuración de cargas](https://www.php.net/manual/en/ini.core.php#ini.upload-max-filesize)

```ini
upload_max_filesize = 32M
post_max_size = 40M
memory_limit = 256M
```

Si el panel no permite confirmar el valor efectivo, se puede usar temporalmente este diagnóstico mínimo dentro del document root protegido. Revisar primero que Basic cubre todo el subdominio. Guardarlo como `verificar-limites-foto.php`, abrirlo por HTTPS con Basic y retirarlo al terminar. Devuelve únicamente los tres valores de la solicitud. Mantener los límites de tamaño del WAF y del proxy por encima de una solicitud multipart de 30 MiB.

```php
<?php
if (($_SERVER['HTTPS'] ?? '') !== 'on'
    || ($_SERVER['HTTP_HOST'] ?? '') !== 'prueba.ensenada.gov.ar'
    || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET'
    || ($_SERVER['REMOTE_USER'] ?? $_SERVER['REDIRECT_REMOTE_USER'] ?? '') === '') {
    http_response_code(404);
    exit;
}
header('Content-Type: application/json');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
echo json_encode([
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'memory_limit' => ini_get('memory_limit'),
]);
```

## Instalación en staging

Subir el ZIP y su `.sha256` a `/home/ensenada/iwinds-photos`, fuera del document root. Mantener las tres ventanas cerradas. Hacer una copia privada de la base y de las imágenes antes de aceptar cargas WebP. Registrar y conservar cualquier cron externo ya instalado para que no elimine nombres nuevos con extensión `.webp` ni referencias de portada, galería, hero o Intendencia. La instalación conserva Basic, los marcadores, el puente, las rutas, `.htaccess` y los recursos públicos existentes de Angular.

```sh
umask 077
cd /home/ensenada/iwinds-photos || exit 1
sha256sum -c web-ensenada-iwinds-photos.zip.sha256 || exit 1
unzip -n web-ensenada-iwinds-photos.zip || exit 1
sha256sum -c SHA256SUMS || exit 1
release=$PWD
backend=/home/ensenada/backend-laravel
docroot=/home/ensenada/public_html/prueba.ensenada.gov.ar
PHP83=/opt/cpanel/ea-php83/root/usr/bin/php
for marker in .iwinds-api-public-probe .iwinds-api-admin-probe .iwinds-api-enabled; do
  test ! -e "$backend/$marker" && test ! -L "$backend/$marker" || exit 1
done
backup=$(mktemp -d /home/ensenada/iwinds-photos-backup.XXXXXXXX) || exit 1
printf 'Guardar la ruta del respaldo: %s\n' "$backup"
cp -p "$docroot/index.html" "$backup/index.html" || exit 1
while IFS= read -r component; do
  case "$component" in
    private/*)
      rel=${component#private/}
      mkdir -p "$backup/$(dirname "$rel")" || exit 1
      cp -p "$backend/$rel" "$backup/$rel" || exit 1
      case "$rel" in *.php) "$PHP83" -l "$release/$component" || exit 1;; esac
      ;;
  esac
done < "$release/FILES"
while IFS= read -r component; do
  case "$component" in
    private/*)
      rel=${component#private/}
      cp -p "$backup/$rel" "$backend/$rel.photos-next" || exit 1
      cat "$release/$component" > "$backend/$rel.photos-next" || exit 1
      mv "$backend/$rel.photos-next" "$backend/$rel" || exit 1
      ;;
    angular/*.js|angular/*.css)
      rel=${component#angular/}
      if test -e "$docroot/$rel"; then
        cmp "$release/$component" "$docroot/$rel" || exit 1
      else
        install -m 644 "$release/$component" "$docroot/$rel" || exit 1
      fi
      ;;
  esac
done < "$release/FILES"
cd "$backend" || exit 1
"$PHP83" deploy/iwinds/platform.php || exit 1
"$PHP83" artisan route:clear || exit 1
"$PHP83" artisan route:cache || exit 1
cp -p "$backup/index.html" "$backup/index.photos-next" || exit 1
cat "$release/angular/index.html" > "$backup/index.photos-next" || exit 1
mv "$backup/index.photos-next" "$docroot/index.html" || exit 1
```

Abrir una ventana administrativa sintética mediante el procedimiento de cierre ya instalado. Probar un JPEG de 6.000 × 4.000 px con EXIF y un PNG con transparencia, un WebP válido, una foto de 30 MiB y un archivo que exceda el límite. En Noticias, seleccionar portada y guardar el artículo. En Sitio, probar carrusel, imagen principal de Intendencia y galería. Comprobar MIME, extensión, tamaño, píxeles, transparencia, descarga GET y HEAD, persistencia y una URL JPG histórica. Comprobar también que Basic sigue activo, que la API cerrada responde `503`, y que durante Public Probe solo se sirve la imagen fija autorizada. Cerrar la ventana al terminar.

## Reversión

Cerrar las ventanas y recuperar las variables `backend`, `docroot`, `release`, `PHP83` y la ruta `backup` anterior. Para revertir solo Angular, restaurar el índice anterior. Los bundles anteriores siguen en el document root, de modo que las páginas abiertas puedan terminar de cargarlos.

```sh
cp -p "$backup/index.html" "$backup/index.restore" || exit 1
mv "$backup/index.restore" "$docroot/index.html" || exit 1
```

Antes de revertir PHP, comprobar si se publicaron referencias `.webp`. Si ya existen y hay que conservarlas, mantener los lectores y las rutas de este incremento. Para una reversión completa con WebP publicado, recuperar conjuntamente la copia anterior de datos y almacenamiento o convertir y actualizar esas referencias mediante un procedimiento autorizado. El lector anterior solo conoce JPG y PNG. Con las referencias compatibles y las ventanas cerradas, restaurar los archivos privados y regenerar la caché de rutas.

```sh
while IFS= read -r component; do
  case "$component" in
    private/*)
      rel=${component#private/}
      cp -p "$backup/$rel" "$backend/$rel" || exit 1
      ;;
  esac
done < "$release/FILES"
cd "$backend" || exit 1
"$PHP83" artisan route:clear || exit 1
"$PHP83" artisan route:cache || exit 1
```
