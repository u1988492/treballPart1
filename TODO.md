# Detalles Pendientes - Snake Game Multiplayer

## Estado Actual ✅
- ✅ Aplicación desplegada en Azure (https://snake-game-snakegame20251110123137.azurewebsites.net)
- ✅ Emails funcionando con Brevo API
- ✅ Registro de usuarios con verificación por email
- ✅ Login con autenticación
- ✅ Recuperación de contraseñas
- ✅ Creación y unión a partidas multijugador
- ✅ Juego funcional probado con múltiples usuarios

## Issues a Pulir 🔧

### 1. CSS - Tooltips (Prioridad: Media)
**Archivos afectados:**
- `/public/styles/profile_settings.css` (0 bytes - vacío)
- `/public/styles/preferences.css` (0 bytes - vacío)

**Problema:** Los tooltips en la página de preferencias no tienen estilos. El HTML contiene elementos `<span class="tooltip">` pero los CSS están vacíos.

**Solución:** Copiar o crear los estilos para las clases `.tooltip` desde el diseño local o crear nuevos estilos.

### 2. Bases de Datos - Columnas Faltantes (Prioridad: Alta si hay errores)
**Estado:** Parcialmente resuelto

**Columnas agregadas manualmente:**
- `failed_2fa_attempts`
- `last_2fa_attempt`
- `locked_2fa_until`

**Acción:** Verificar que `setup/create_databases.sql` incluya todas las columnas necesarias para futuras instalaciones limpias.

### 3. GitHub Actions - CI/CD (Prioridad: Baja)
**Estado:** Deshabilitado temporalmente

**Problema:** El workflow de GitHub Actions falló múltiples veces por problemas de build con Oryx.

**Decisión actual:** Deployment manual via Kudu es funcional y suficiente.

**Mejora futura:** Si se necesita CI/CD automático, considerar:
- Azure Static Web Apps (para frontend)
- GitHub Actions con deployment directo vía FTP/Kudu API
- Containerización con Docker

### 4. Limpieza de Archivos de Test (Prioridad: Media)
**Ubicación:** `/home/site/wwwroot/public/` en Azure

**Archivos a eliminar:**
```bash
test_brevo_api.php
test_email_alternate.php
create_test_user.php
test_db_direct.php
test_email_debug.php
```

**Comandos Kudu SSH:**
```bash
cd /home/site/wwwroot/public
rm -f test_*.php create_test_user.php
```

### 5. Usuarios de Prueba en Base de Datos (Prioridad: Baja)
**Usuarios creados durante testing:**
- `claudia_test`
- `claudia_test2` - `claudia_test5`
- `fede_test`

**Acción opcional:** Limpiar usuarios de prueba si ya no son necesarios:
```bash
sqlite3 /home/site/wwwroot/private/users.db "DELETE FROM users WHERE user_name LIKE '%_test%';"
```

### 6. Documentación para Deployment (Prioridad: Baja)
**Archivos existentes:**
- `DEPLOYMENT.md` - Guía completa de deployment
- `MONITORING.md` - Configuración de Application Insights
- `QUICKSTART.md` - Inicio rápido
- `DEPLOY_INSTRUCTIONS.md` - Procedimientos manuales

**Mejora futura:** Consolidar en un solo documento o crear índice README.md

### 7. Validación de Sender Email en Brevo (Prioridad: CRÍTICA - ✅ RESUELTO)
**Estado:** ✅ Resuelto

**Problema anterior:** Emails no llegaban porque el remitente `u1988492@udg.edu` no estaba verificado.

**Solución aplicada:** Cambiado a `u1988492@campus.udg.edu` (verificado en Brevo)

**Variables Azure:**
- `SMTP_FROM_EMAIL=u1988492@campus.udg.edu` ✅
- `BREVO_API_KEY=xkeysib-...` ✅

## Mejoras Opcionales 💡

### Performance
- [ ] Implementar cache para consultas frecuentes
- [ ] Optimizar queries SQLite con índices
- [ ] Minificar CSS/JS en producción

### Seguridad
- [ ] Rate limiting más estricto
- [ ] Logging de intentos de ataque
- [ ] Headers de seguridad adicionales (CSP, HSTS)

### UX/UI
- [ ] Animaciones más suaves en el juego
- [ ] Feedback visual para acciones del usuario
- [ ] Modo oscuro/claro
- [ ] Responsive design para móviles

### Features
- [ ] Ranking de puntuaciones
- [ ] Chat entre jugadores
- [ ] Diferentes modos de juego
- [ ] Customización de colores de serpiente
- [ ] Sonidos y música

## Notas Técnicas 📝

### Configuración Azure App Service
```
Tier: F1 (Free)
Region: West Europe
Runtime: PHP 8.2 Linux
Document Root: /home/site/wwwroot/public/ (via nginx)
```

### Variables de Entorno Configuradas
```
SMTP_HOST=smtp-relay.brevo.com
SMTP_PORT=587
SMTP_USERNAME=u1988492@udg.edu
SMTP_PASSWORD=[API Key de Brevo]
SMTP_FROM_EMAIL=u1988492@campus.udg.edu
SMTP_FROM_NAME=Mossegam
APPINSIGHTS_INSTRUMENTATIONKEY=c35268a5-d569-4672-b4a5-b6b3c9246ca2
BREVO_API_KEY=[API Key]
SCM_DO_BUILD_DURING_DEPLOYMENT=false
ENABLE_ORYX_BUILD=false
```

### Estructura de Base de Datos
**Ubicación:** `/home/site/wwwroot/private/`
- `users.db` - Usuarios, autenticación, preferencias
- `games.db` - Estado de partidas, latencia

**Backup recomendado:** Programar backups periódicos vía Kudu o Azure CLI

---

**Fecha:** 10 de noviembre de 2025  
**Estado general:** ✅ Producción funcional  
**Próximos pasos:** Pulir detalles de UI/UX según prioridades
