# Publicación controlada — base existente

La restricción de iWinds obliga a transferir **ensenada_web**, con los usuarios existentes **ensenada_web_app** y **ensenada_web_schema**, sin otra base. La guía de transferencia es [SHARED-DATABASE.md](SHARED-DATABASE.md). El segundo incremento y el perfil alternativo sujeto a aceptación explícita se describen en [CPANEL-DML.md](CPANEL-DML.md). Reemplaza las instrucciones anteriores de base separada, inicialización y aprovisionamiento.

Instalar el incremento sobre `/home/ensenada/backend-laravel-produccion` existente: preserva `.env`, APP_KEY/JWT, vendor, Angular V13 y datos. El instalador valida el paquete anterior completo antes de modificar código. No volver a instalar el ZIP completo, ejecutar `init-env.php`, importar SQL ni aprovisionar contenido/administradores: ya existen.

El corte requiere un perfil SQL autorizado, staging aparcado, contraseña MySQL rotada, conexiones antiguas cerradas, revisión manual de contenidos oficiales/sintéticos, copia privada verificada, respaldo completo y restauración aislada del respaldo concreto. Producción conserva per-table como predeterminado y solo acepta `cpanel-dml` con permisos exactos, aceptación documentada, respaldo restaurado y checkpoint bajo custodia externa. Las sesiones se revocan solo bajo autorización explícita. La activación y la reescritura selectiva de Apache son operaciones humanas separadas.

La reversión conserva la misma base con todas las escrituras de producción y copia sus archivos más recientes; no restaura un dump antiguo sobre ella. Recuperación de staging exclusiva, temporal y protegida por Basic más sesiones individuales. No se modifica WordPress, Spring Boot, otros subdominios, DNS, correo, PHP global ni otras bases. Nada se despliega o activa automáticamente.

Resultados locales y límites de evidencia en [VALIDATION.md](VALIDATION.md). Las verificaciones sintéticas no aprueban los datos reales ni sustituyen la aceptación HTTPS de iWinds.
