# Directorio público y CMS

## Flujo verificado

1. `AdminSitio.actualizarCampoSecretaria()` modifica el formulario. `guardar()` envía una copia a `PortalContentService.guardar()`.
2. El servicio hace `PUT /api/admin/site-content` y vuelve a consultar `GET /api/admin/site-content` para verificar lo persistido. Después invalida su replay público.
3. `PortalSiteContentController` delega en `PortalSiteContentService`, que guarda el JSON en el registro singleton de `PortalSiteContentRepository`. Ambos GET leen ese mismo registro; las respuestas usan `Cache-Control: no-store`.
4. Cada instancia de `Areas` llama `obtenerPublico(true)`: crea una petición nueva a `/api/site-content`, con `transferCache: false` y timeout de ocho segundos.
5. La respuesta actualiza signals leídas por `areas.html`. Angular programa el render tanto para éxito como para error, sin `detectChanges`, `queueMicrotask` ni `afterNextRender`.

## Causa raíz y SSR

Angular 21 funciona por defecto sin Zone.js y esta aplicación no configura Zone.js. `Areas` asignaba propiedades comunes dentro de `subscribe`: el HTTP terminaba pero no notificaba al scheduler de render. La página permanecía en “Cargando áreas municipales…”. El test anterior inyectaba `of(cloneDefaultPortalContent())` y llamaba `detectChanges()`, ocultando el problema real de una respuesta posterior al primer render.

Los cambios previos de los PR #34 y #35 alteraron cuándo se solicitaba el contenido y pasaron la ruta a CSR, pero conservaron esas asignaciones no reactivas. Se mantiene el `RenderMode.Client` existente: cambiar nuevamente el modo de render no resuelve la notificación faltante.

El servicio ya excluía esta petición de HTTP TransferCache. Se conserva esa exclusión y la consulta forzada en cada visita. Se elimina el fallback silencioso de errores del servidor y el directorio público ausente o vacío ya no se sustituye por contactos hardcodeados. Los defaults de otros campos siguen dando estructura a respuestas parciales; el Admin conserva sus valores iniciales de formulario. Navbar y Footer manejan el error de su suscripción para mantener la navegación mientras Áreas muestra el fallo.

`frontend/proxy.conf.json` redirige `/api` y `/uploads` a 8080 en desarrollo: se comprobó el JSON por el mismo origen 4200. `frontend/src/server.ts` sirve el build Angular, no es un proxy de API. En producción sigue siendo necesario el reverse proxy descrito en README: `/api` y `/uploads` a Spring, el resto a Angular. No se modifican estas configuraciones porque no causaban el bloqueo del DOM.

## Regresión automatizada

- `areas.spec.ts`: HTTP posterior al primer render, zoneless explícito y `whenStable()` sin forzar detección después de la respuesta. Con el código anterior fallaba mostrando únicamente “Cargando áreas municipales…”. Con signals pasa y exige la dirección `La Merced y Don Bosco.` y el teléfono `(0221) 469-3154`, sin los valores viejos.
- La misma suite cubre HTTP 503, timeout, contenido sin directorio, directorio vacío y edición con `AdminSitio` seguida de una nueva instancia pública usando el mismo servicio.
- `portal-content.service.spec.ts`: errores tanto en browser como en server, JSON nulo, exclusión de TransferCache y consulta forzada que descarta el replay anterior.
- `PortalSiteContentIntegrationTests`: PUT → GET administrativo → GET público con los valores del caso reportado y `Cache-Control: no-store` en los tres endpoints.

## Verificación manual realizada

- Entrada directa a `http://localhost:4200/areas`, API del mismo origen y navegación Inicio → Ver autoridades: dirección y teléfono coinciden con la base local existente.
- Backend temporal en 8081 con H2 en memoria y frontend en `http://127.0.0.1:4201`: ingreso al CMS, edición de ambos campos, Guardar y publicar, mensaje de persistencia verificada, consulta del endpoint público y navegación Admin → Inicio → Áreas. El DOM muestra ambos valores nuevos sin reiniciar procesos y sin tocar la base local original.
- `npm test -- --run`: 53 tests frontend pasan.
- `npm run build`: pasa; conserva advertencias de presupuesto de bundle y CSS de Home.
- `mvn test`: 35 tests ejecutados sin fallos y uno de validación MySQL omitido por su condición de entorno.

Para repetir el diagnóstico, dejar finalizar el primer render antes de resolver HTTP en los tests. No agregar `fixture.detectChanges()` después de `flush()`: volvería a ocultar una regresión del scheduler.

## Título de Áreas en la Home

`Título Home` edita `areas.titulo`; `Título de /areas` edita `areas.paginaTitulo`. Son encabezados independientes. La Home también necesitaba almacenar el contenido como signal: asignar la propiedad común y llamar `heroIndex.set(0)` no notificaba nada cuando el índice ya era cero. El título guardado podía aparecer recién con otra interacción o con un cambio del carrusel.

`home.spec.ts` reproduce la respuesta tardía del CMS después de completar la carga de noticias, sin carrusel ni detección manual. Antes esperaba “Conocé nuestras secretarías.” y recibía “Conocé nuestras áreas”. Con el contenido reactivo pasan los 54 tests frontend y el build. Se verificó en navegador `http://localhost:4200/#areas` y el recorrido Home → Áreas → Home: cada página muestra su título correspondiente.
