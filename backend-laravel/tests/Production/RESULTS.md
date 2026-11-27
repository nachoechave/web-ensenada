# Validación vigente — PR 85

La estrategia de base independiente quedó reemplazada por la transferencia exclusiva de `ensenada_web`.

Resultados ejecutados el 27/11/2026: [deploy/production/VALIDATION.md](../../deploy/production/VALIDATION.md).

Ensayo completo: `tests/SharedDatabase/run.ps1`. Restauración de un respaldo concreto descargado: `deploy/production/restore-local.ps1`. Guía de instalación, permisos, corte y reversión: [SHARED-DATABASE.md](../../deploy/production/SHARED-DATABASE.md).

No se accedió a iWinds ni a sus datos. El paquete no activa la API, no importa SQL en el hosting y no cambia claves de aplicación existentes. Producción requiere evidencia del esquema/datos/backup reales y permisos mínimos por tabla; cpanel-dml no se aprueba.
