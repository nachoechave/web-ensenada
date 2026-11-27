# Laravel de Web Ensenada

Este directorio incorpora el backend existente de la migración, conservando los módulos y contratos de Angular. La preparación de producción y los comandos operativos están en [deploy/production/README.md](deploy/production/README.md).

El perfil `staging` continúa usando su directorio, base, origen y autorizaciones originales. El nuevo perfil `production` utiliza un directorio y base independientes y permanece cerrado hasta una habilitación explícita. No ejecutar migraciones Artisan en el hosting.

Las migraciones del directorio `database/` son auxiliares para pruebas descartables. El esquema histórico compatible con Flyway se prepara por separado y solo sobre una base vacía.

Pruebas locales reproducibles y resultados reales en [tests/Production/RESULTS.md](tests/Production/RESULTS.md). El ZIP excluye pruebas, datos sintéticos, `.env`, almacenamiento, cachés y dependencias de desarrollo.
