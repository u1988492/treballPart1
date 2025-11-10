# Guía de Despliegue en Azure - Mossegam Snake Game

## 📋 Información del Proyecto

**Proyecto:** Juego Snake Multijugador Seguro  
**Estudiante:** Claudia Hodoroga  
**Asignatura:** Desarrollo de Videojuegos Multijugador  
**Universidad:** [Tu Universidad]  
**Curso Académico:** 2024-2025

---

## 🏗️ Arquitectura de Despliegue

### Servicios Utilizados (Azure for Students - Tier Gratuito)

| Servicio                 | Tier           | Propósito                   | Coste Mensual |
| ------------------------ | -------------- | --------------------------- | ------------- |
| **Azure App Service**    | F1 (Free)      | Hosting PHP + servidor web  | €0            |
| **Application Insights** | Free tier      | Monitorización y telemetría | €0            |
| **SQLite en Filesystem** | N/A            | Base de datos (desarrollo)  | €0            |
| **GitHub Actions**       | Free           | CI/CD automatizado          | €0            |
| **Brevo SMTP**           | Free (300/día) | Envío de emails             | €0            |
| **TOTAL**                |                |                             | **€0**        |

### Arquitectura Visual

```
┌─────────────────────────────────────────────────────────────┐
│                    GitHub Repository                         │
│                    (Código fuente)                           │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       │ Push to main branch
                       ▼
┌─────────────────────────────────────────────────────────────┐
│                  GitHub Actions CI/CD                        │
│  - Checkout code                                             │
│  - Install dependencies (Composer)                           │
│  - Run tests (opcional)                                      │
│  - Deploy to Azure                                           │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       │ Automated deployment
                       ▼
┌─────────────────────────────────────────────────────────────┐
│              Azure App Service (Linux + PHP 8.2)             │
│                                                              │
│  ┌────────────────────────────────────────────────────┐    │
│  │  Web Server (Apache/Nginx)                         │    │
│  │  - Public folder as web root                       │    │
│  │  - HTTPS automático (.azurewebsites.net)           │    │
│  │  - Session management                              │    │
│  └────────────────────────────────────────────────────┘    │
│                                                              │
│  ┌────────────────────────────────────────────────────┐    │
│  │  App Filesystem                                    │    │
│  │  - /home/data/users.db (SQLite)                   │    │
│  │  - /home/data/games.db (SQLite)                   │    │
│  │  - Logs y archivos temporales                     │    │
│  └────────────────────────────────────────────────────┘    │
│                                                              │
│  ┌────────────────────────────────────────────────────┐    │
│  │  Environment Variables                             │    │
│  │  - SMTP_HOST, SMTP_USERNAME, SMTP_PASSWORD        │    │
│  │  - SMTP_FROM_EMAIL, SMTP_FROM_NAME                │    │
│  └────────────────────────────────────────────────────┘    │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       │ Logs & Metrics
                       ▼
┌─────────────────────────────────────────────────────────────┐
│              Application Insights                            │
│  - Request telemetry                                         │
│  - Error tracking                                            │
│  - Performance metrics                                       │
│  - Custom events (game sessions)                             │
└─────────────────────────────────────────────────────────────┘

         │                                    │
         │ SMTP                               │ HTTPS
         ▼                                    ▼
┌──────────────────┐              ┌──────────────────┐
│  Brevo SMTP      │              │   Usuarios       │
│  Email Service   │              │   (Browsers)     │
└──────────────────┘              └──────────────────┘
```

---

## 🚀 FASE 1: Preparación del Proyecto

### ✅ Archivos de Configuración Creados

Los siguientes archivos ya están configurados en tu proyecto:

#### 1. `.deployment`

```ini
[config]
project = public
```

Define `public/` como el directorio raíz web.

#### 2. `web.config`

Configuración de reescritura de URLs para PHP en IIS (servidor de Azure).

#### 3. `composer.json`

Define dependencias de PHP:

- PHP >= 7.4
- Extensiones: pdo, sqlite3, json, mbstring

#### 4. `.gitignore`

Excluye archivos sensibles:

- `private/*.db` - Bases de datos
- `private/*.log` - Logs
- `private/emails/*.txt` - Emails guardados
- `.env` - Variables de entorno locales

#### 5. `config.php` (actualizado)

- ✅ Detecta automáticamente entorno Azure (`WEBSITE_SITE_NAME`)
- ✅ Usa variables de entorno para credenciales SMTP
- ✅ Auto-configura URL del sitio en Azure
- ✅ Activa cookies seguras en HTTPS

#### 6. `init_azure_db.php`

Script para inicializar las bases de datos SQLite en Azure (ejecutar una sola vez).

#### 7. `.github/workflows/azure-deploy.yml`

Workflow de GitHub Actions para CI/CD automatizado.

---

## 🔧 FASE 2: Crear Recursos en Azure

### Prerrequisitos

1. **Azure CLI instalado**

   ```powershell
   # Verificar instalación
   az --version

   # Si no está instalado, descargar de:
   # https://aka.ms/installazurecliwindows
   ```

2. **Cuenta Azure for Students activada**
   - Ir a: https://azure.microsoft.com/es-es/free/students/
   - Verificar créditos disponibles

### Paso 1: Login y Configuración

```powershell
# Login a Azure
az login

# Listar suscripciones disponibles
az account list --output table

# Seleccionar Azure for Students
az account set --subscription "Azure for Students"

# Verificar suscripción activa
az account show
```

### Paso 2: Crear Resource Group

```powershell
# Crear grupo de recursos en West Europe
az group create `
  --name rg-snake-game-claudia `
  --location westeurope

# Verificar creación
az group show --name rg-snake-game-claudia
```

### Paso 3: Crear App Service Plan (Free Tier)

```powershell
# Crear plan gratuito en Linux
az appservice plan create `
  --name plan-snake-game `
  --resource-group rg-snake-game-claudia `
  --sku FREE `
  --is-linux

# Verificar plan
az appservice plan show `
  --name plan-snake-game `
  --resource-group rg-snake-game-claudia
```

### Paso 4: Crear Web App

```powershell
# Generar nombre único (cambiar XXXX por tus iniciales + fecha)
# Ejemplo: snake-game-ch1110 (Claudia Hodoroga - 11/10)

az webapp create `
  --name snake-game-ch1110 `
  --resource-group rg-snake-game-claudia `
  --plan plan-snake-game `
  --runtime "PHP:8.2"

# Verificar creación y obtener URL
az webapp show `
  --name snake-game-ch1110 `
  --resource-group rg-snake-game-claudia `
  --query defaultHostName -o tsv
```

**URL de tu aplicación:** `https://snake-game-ch1110.azurewebsites.net`

### Paso 5: Configurar Variables de Entorno (SMTP)

#### Opción A: Brevo (Recomendado - 300 emails/día gratis)

1. Crear cuenta en [Brevo](https://www.brevo.com/)
2. Ir a: **SMTP & API** → **SMTP**
3. Crear SMTP key
4. Configurar en Azure:

```powershell
az webapp config appsettings set `
  --name snake-game-ch1110 `
  --resource-group rg-snake-game-claudia `
  --settings `
    SMTP_HOST="smtp-relay.brevo.com" `
    SMTP_PORT="587" `
    SMTP_USERNAME="tu-email@dominio.com" `
    SMTP_PASSWORD="tu-brevo-smtp-key" `
    SMTP_FROM_EMAIL="tu-email@dominio.com" `
    SMTP_FROM_NAME="Mossegam Snake Game"
```

#### Opción B: Gmail (Configuración adicional requerida)

```powershell
az webapp config appsettings set `
  --name snake-game-ch1110 `
  --resource-group rg-snake-game-claudia `
  --settings `
    SMTP_HOST="smtp.gmail.com" `
    SMTP_PORT="587" `
    SMTP_USERNAME="tu-email@gmail.com" `
    SMTP_PASSWORD="tu-app-password" `
    SMTP_FROM_EMAIL="tu-email@gmail.com" `
    SMTP_FROM_NAME="Mossegam"

# IMPORTANTE: Usar App Password de Google, no la contraseña normal
# Generar en: https://myaccount.google.com/apppasswords
```

### Paso 6: Habilitar Logging Detallado

```powershell
az webapp log config `
  --name snake-game-ch1110 `
  --resource-group rg-snake-game-claudia `
  --application-logging filesystem `
  --level verbose `
  --detailed-error-messages true `
  --failed-request-tracing true
```

### Paso 7: Crear Application Insights

```powershell
# Crear recurso de Application Insights
az monitor app-insights component create `
  --app snake-game-insights `
  --location westeurope `
  --resource-group rg-snake-game-claudia `
  --application-type web

# Obtener Instrumentation Key
$INSTRUMENTATION_KEY = az monitor app-insights component show `
  --app snake-game-insights `
  --resource-group rg-snake-game-claudia `
  --query instrumentationKey -o tsv

# Vincular con Web App
az webapp config appsettings set `
  --name snake-game-ch1110 `
  --resource-group rg-snake-game-claudia `
  --settings `
    APPINSIGHTS_INSTRUMENTATIONKEY="$INSTRUMENTATION_KEY"

# Mostrar key para referencia
Write-Host "Instrumentation Key: $INSTRUMENTATION_KEY"
```

---

## 🔐 FASE 3: Configurar CI/CD con GitHub Actions

### Paso 1: Obtener Publish Profile

```powershell
# Descargar perfil de publicación
az webapp deployment list-publishing-profiles `
  --name snake-game-ch1110 `
  --resource-group rg-snake-game-claudia `
  --xml > publish-profile.xml

# Mostrar contenido (copiar todo)
Get-Content publish-profile.xml
```

### Paso 2: Agregar Secret en GitHub

1. Ir a tu repositorio en GitHub
2. **Settings** → **Secrets and variables** → **Actions**
3. Click **New repository secret**
4. Nombre: `AZURE_WEBAPP_PUBLISH_PROFILE`
5. Value: Pegar todo el contenido de `publish-profile.xml`
6. Click **Add secret**

### Paso 3: Verificar Workflow

El archivo `.github/workflows/azure-deploy.yml` ya está configurado.

**IMPORTANTE:** Editar línea 45 del workflow con tu nombre de app:

```yaml
app-name: "snake-game-ch1110" # ← Cambiar por tu nombre único
```

### Paso 4: Activar el Despliegue

```powershell
# Desde tu proyecto local
git add .
git commit -m "Configure Azure deployment"
git push origin main
```

El deployment se ejecutará automáticamente. Ver progreso en:

- GitHub → Tu repositorio → **Actions** tab

---

## 🗄️ FASE 4: Inicializar Bases de Datos

**IMPORTANTE:** Ejecutar UNA SOLA VEZ después del primer despliegue.

### Opción 1: Kudu Console (Recomendado)

1. Ir a: [Azure Portal](https://portal.azure.com/)
2. Navegar a tu **Web App** → `snake-game-ch1110`
3. En el menú lateral: **Development Tools** → **Advanced Tools**
4. Click **Go** (abre Kudu)
5. En el menú superior: **Debug console** → **CMD**
6. Navegar a: `D:\home\site\wwwroot`
7. Ejecutar:
   ```cmd
   php init_azure_db.php
   ```
8. Verificar output:

   ```
   === Inicialización de Bases de Datos en Azure ===

   Entorno: Azure
   -------------------------------------------

   Inicializando Users Database...
     ✓ Directorio creado: /home/data
     ✓ Base de datos creada: /home/data/users.db
     ✓ Tablas creadas: users
     ✓ Permisos establecidos (0644)

   Inicializando Games Database...
     ✓ Base de datos creada: /home/data/games.db
     ✓ Tablas creadas: game_state, player_latency
     ✓ Permisos establecidos (0644)

   === Inicialización completada ===
   ```

### Opción 2: SSH (Si está habilitado)

```powershell
az webapp ssh --name snake-game-ch1110 --resource-group rg-snake-game-claudia
cd /home/site/wwwroot
php init_azure_db.php
exit
```

---

## ✅ FASE 5: Testing y Verificación

### Checklist de Funcionalidad

Probar en: `https://snake-game-ch1110.azurewebsites.net`

- [ ] **1. Sitio carga con HTTPS**

  - Verificar candado verde en navegador
  - No debe haber warnings de seguridad

- [ ] **2. Registro de usuario**

  - Crear cuenta nueva
  - Verificar validación de contraseña fuerte
  - Comprobar que se guarda en DB

- [ ] **3. Email de verificación**

  - Recibir email en bandeja de entrada (no spam)
  - Email debe venir de tu SMTP configurado
  - Verificar código 2FA funciona

- [ ] **4. Login/Logout**

  - Login con credenciales correctas
  - Verificar sesión persiste
  - Logout limpia sesión

- [ ] **5. Protección CSRF**

  - Intentar enviar formulario sin token (debe fallar)
  - Usar herramientas de desarrollo para eliminar token

- [ ] **6. Recuperación de contraseña**

  - Solicitar reset
  - Recibir email
  - Completar proceso de reset

- [ ] **7. Preferencias de usuario**

  - Actualizar configuraciones
  - Verificar se guardan en BD

- [ ] **8. Lobby de juego**

  - Cargar lista de partidas
  - Ver latencia estimada

- [ ] **9. Crear partida**

  - Crear nueva partida
  - Verificar aparece en lobby

- [ ] **10. Unirse a partida**

  - Desde otro navegador/dispositivo
  - Segundo jugador se une exitosamente

- [ ] **11. Gameplay multijugador**

  - Ambos jugadores ven el juego
  - Movimientos se sincronizan
  - Frutas aparecen correctamente

- [ ] **12. Latencia**

  - Indicador de latencia visible
  - Valores razonables (<500ms)

- [ ] **13. Game Over**
  - Pantalla de fin de juego
  - Ganador se muestra correctamente
  - Opción de jugar de nuevo

### Tests de Performance

#### Test 1: Tiempo de Respuesta

```powershell
# Crear archivo curl-format.txt
@"
time_namelookup:  %{time_namelookup}s
time_connect:  %{time_connect}s
time_starttransfer:  %{time_starttransfer}s
time_total:  %{time_total}s
"@ | Out-File -FilePath curl-format.txt -Encoding ASCII

# Probar tiempo de respuesta
curl -w "@curl-format.txt" -o $null -s https://snake-game-ch1110.azurewebsites.net
```

**Resultados esperados:**

- `time_total` < 2 segundos (primera carga, cold start)
- `time_total` < 500ms (cargas subsecuentes)

#### Test 2: Disponibilidad

```powershell
# Test simple de disponibilidad
for ($i=1; $i -le 10; $i++) {
    $response = Invoke-WebRequest -Uri "https://snake-game-ch1110.azurewebsites.net" -UseBasicParsing
    Write-Host "Test $i : Status Code = $($response.StatusCode)"
    Start-Sleep -Seconds 2
}
```

**Objetivo:** 100% de respuestas HTTP 200

### Verificar Application Insights

1. Ir a: Azure Portal → `snake-game-insights`
2. Revisar métricas:
   - **Server response time**: < 1 segundo promedio
   - **Failed requests**: 0%
   - **Availability**: > 99%

---

## 📊 FASE 6: Monitorización

Ver documento separado: **[MONITORING.md](MONITORING.md)**

Incluye:

- Queries de Application Insights
- Dashboards personalizados
- Alertas recomendadas
- Análisis de costos

---

## 💰 Análisis de Costos

### Costos Actuales (Tier Gratuito)

| Servicio             | Tier | Límites                              | Coste/Mes |
| -------------------- | ---- | ------------------------------------ | --------- |
| App Service          | F1   | 60 min CPU/día, 1GB RAM, 1GB storage | €0        |
| Application Insights | Free | 1GB/mes de datos                     | €0        |
| Brevo SMTP           | Free | 300 emails/día                       | €0        |
| **TOTAL**            |      |                                      | **€0**    |

### Limitaciones del Tier Gratuito

1. **Compute:**

   - Solo 60 minutos de CPU por día
   - Suficiente para testing académico
   - Cold start después de inactividad (~30 segundos)

2. **Almacenamiento:**

   - 1GB disco total
   - SQLite incluido en este límite

3. **Red:**

   - Sin IP dedicada
   - Solo dominio `.azurewebsites.net`
   - Sin auto-scaling

4. **Alta Disponibilidad:**
   - Single instance (sin redundancia)
   - No hay SLA de uptime
   - Ideal para desarrollo/academia

---

## 📈 Escalabilidad para Producción

### ¿Qué cambiaríamos para 1000+ usuarios concurrentes?

#### 1. Base de Datos → Azure Database for PostgreSQL

**Por qué:**

- SQLite no soporta múltiples escrituras concurrentes
- No funciona con múltiples instancias de App Service

**Configuración:**

```powershell
# Crear PostgreSQL Flexible Server
az postgres flexible-server create `
  --name snake-game-db `
  --resource-group rg-snake-game-claudia `
  --location westeurope `
  --admin-user snakeadmin `
  --admin-password 'TuPasswordSeguro123!' `
  --sku-name Standard_B1ms `
  --tier Burstable `
  --storage-size 32

# Coste: ~€15-20/mes
```

**Migración:**

1. Exportar datos de SQLite a SQL
2. Adaptar queries (sintaxis SQL estándar)
3. Actualizar `config.php` para usar PDO con PostgreSQL

#### 2. Sesiones → Azure Cache for Redis

**Por qué:**

- Sesiones de PHP en filesystem no se comparten entre instancias
- Redis centraliza sesiones

**Configuración:**

```powershell
az redis create `
  --name snake-game-cache `
  --resource-group rg-snake-game-claudia `
  --location westeurope `
  --sku Basic `
  --vm-size c0

# Coste: ~€14/mes
```

**Código PHP:**

```php
// En config.php
ini_set('session.save_handler', 'redis');
ini_set('session.save_path', 'tcp://snake-game-cache.redis.cache.windows.net:6380?auth=KEY');
```

#### 3. Storage → Azure Blob Storage

**Por qué:**

- Logs y emails deben estar fuera del filesystem de App Service
- Mejor para múltiples instancias

**Configuración:**

```powershell
# Crear Storage Account
az storage account create `
  --name snakegamestorage `
  --resource-group rg-snake-game-claudia `
  --location westeurope `
  --sku Standard_LRS

# Coste: ~€0.02/GB/mes (Hot tier)
```

#### 4. App Service → Basic B1 o superior

**Por qué:**

- Auto-scaling
- Multiple instances
- Custom domain
- Deployment slots (staging/production)

**Configuración:**

```powershell
# Upgrade a Basic B1
az appservice plan update `
  --name plan-snake-game `
  --resource-group rg-snake-game-claudia `
  --sku B1

# Habilitar auto-scaling
az monitor autoscale create `
  --resource-group rg-snake-game-claudia `
  --resource snake-game-ch1110 `
  --resource-type Microsoft.Web/sites `
  --name autoscale-rules `
  --min-count 2 `
  --max-count 5 `
  --count 2

# Coste: ~€12/mes (B1) + €12/instancia adicional
```

#### 5. WebSockets en lugar de Polling

**Por qué:**

- Latencia mucho menor
- Menos carga en servidor

**Tecnología:**

- Azure SignalR Service (~€40/mes Basic tier)
- O WebSocket nativo con múltiples workers

#### 6. CDN para Assets Estáticos

**Por qué:**

- JS, CSS, imágenes servidos desde edge locations
- Menor latencia global

```powershell
az cdn profile create `
  --name snake-game-cdn `
  --resource-group rg-snake-game-claudia `
  --sku Standard_Microsoft

# Coste: ~€0.08/GB transferencia
```

### Estimación de Costos Producción

Para **1000 usuarios concurrentes** (~10,000 jugadores/día):

| Servicio             | Tier                    | Coste/Mes     |
| -------------------- | ----------------------- | ------------- |
| App Service          | Basic B1 (2 instancias) | €24           |
| PostgreSQL           | Standard B1ms           | €18           |
| Redis Cache          | Basic C0                | €14           |
| Blob Storage         | 10GB Hot                | €0.20         |
| Application Insights | 5GB/mes                 | €10           |
| CDN                  | 50GB transfer           | €4            |
| SignalR              | Basic                   | €40           |
| **TOTAL**            |                         | **~€110/mes** |

**ROI:** €0.011/usuario = muy rentable para un juego con monetización.

---

## 🔒 Características de Seguridad Implementadas

### 1. HTTPS Obligatorio

- ✅ Certificado SSL automático de Azure
- ✅ Redirección HTTP → HTTPS
- ✅ Cookies con flag `Secure`

### 2. Protección contra Ataques

#### CSRF (Cross-Site Request Forgery)

```php
// Todas las formas generan y validan token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
```

#### XSS (Cross-Site Scripting)

```php
// Escape de output
echo htmlspecialchars($user_input, ENT_QUOTES, 'UTF-8');
```

#### SQL Injection

```php
// Prepared statements en todas las queries
$stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$username]);
```

#### Rate Limiting

```php
// Limitar intentos de login
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutos
```

### 3. Autenticación Robusta

- ✅ Contraseñas hasheadas con `password_hash()` (bcrypt)
- ✅ Longitud mínima de 12 caracteres
- ✅ Verificación contra HaveIBeenPwned API
- ✅ 2FA por email con código de 6 dígitos
- ✅ Tokens de recuperación con expiración

### 4. Gestión de Sesiones

- ✅ `session.cookie_httponly = 1` (no accesible desde JS)
- ✅ `session.cookie_secure = 1` (solo HTTPS)
- ✅ `session.cookie_samesite = Strict` (protección CSRF)
- ✅ Regeneración de session ID tras login
- ✅ Timeout de sesión (1 hora)

---

## ⚠️ Limitaciones Conocidas (Proyecto Académico)

1. **Cold Start Delays**

   - Después de 20 minutos de inactividad, primera request tarda ~30 segundos
   - **Solución producción:** Always On (requiere Basic tier o superior)

2. **Single Instance = Single Point of Failure**

   - Si la instancia falla, sitio inaccesible
   - **Solución producción:** Múltiples instancias con load balancer

3. **SQLite no es Multi-Instance**

   - Funciona solo con una instancia
   - **Solución producción:** PostgreSQL o Azure SQL

4. **Sin Estrategia de Backup**

   - Bases de datos SQLite pueden perderse
   - **Solución producción:** Backups automáticos de Azure Database

5. **Límite de 60 min CPU/día**

   - Suficiente para testing pero no para uso continuo
   - **Solución producción:** Plan de pago

6. **Sin Custom Domain**

   - Solo `.azurewebsites.net`
   - **Solución producción:** App Service con dominio personalizado

7. **Polling en lugar de WebSocket**
   - Latencia mayor (~500ms vs ~50ms)
   - Mayor carga de servidor
   - **Solución producción:** Azure SignalR o WebSocket nativo

---

## 🛠️ Troubleshooting

### Problema: Sitio devuelve Error 500

**Solución 1: Ver logs en tiempo real**

```powershell
az webapp log tail `
  --name snake-game-ch1110 `
  --resource-group rg-snake-game-claudia
```

**Solución 2: Descargar logs**

```powershell
az webapp log download `
  --name snake-game-ch1110 `
  --resource-group rg-snake-game-claudia `
  --log-file logs.zip
```

**Solución 3: Verificar configuración PHP**

1. Kudu Console → Debug console → CMD
2. Ejecutar: `php --version`
3. Ejecutar: `php -m` (ver extensiones cargadas)

### Problema: Base de datos no encontrada

**Diagnóstico:**

```powershell
# Via Kudu Console
cd D:\home\data
dir
# Debe mostrar: users.db, games.db
```

**Solución:**

1. Ejecutar `php init_azure_db.php` de nuevo
2. Verificar permisos:
   ```cmd
   icacls users.db
   ```

### Problema: Emails no se envían

**Verificar variables de entorno:**

```powershell
az webapp config appsettings list `
  --name snake-game-ch1110 `
  --resource-group rg-snake-game-claudia `
  --output table
```

**Probar SMTP manualmente:**

```php
// Crear test_smtp.php en public/
<?php
require_once 'config.php';

$to = 'tu-email@dominio.com';
$subject = 'Test SMTP Azure';
$message = 'Este es un email de prueba';
$headers = 'From: ' . SMTP_FROM_EMAIL;

if (mail($to, $subject, $message, $headers)) {
    echo "✓ Email enviado correctamente";
} else {
    echo "✗ Error al enviar email";
    print_r(error_get_last());
}
```

Acceder a: `https://snake-game-ch1110.azurewebsites.net/test_smtp.php`

### Problema: Sesión no persiste

**Diagnóstico:**

1. Verificar cookies en DevTools:
   - Debe haber cookie `PHPSESSID`
   - Flag `Secure` debe estar activo
   - Flag `HttpOnly` debe estar activo

**Solución:**

```php
// Verificar en config.php
if (IS_AZURE) {
    ini_set('session.cookie_secure', 1);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Strict');
}
```

### Problema: GitHub Actions falla

**Ver logs:**

1. GitHub → Tu repo → Actions
2. Click en el workflow fallido
3. Expandir step que falló

**Problemas comunes:**

#### Error: "publish profile invalid"

```powershell
# Re-descargar publish profile
az webapp deployment list-publishing-profiles `
  --name snake-game-ch1110 `
  --resource-group rg-snake-game-claudia `
  --xml > publish-profile.xml

# Actualizar secret en GitHub
```

#### Error: "composer install fails"

- Verificar `composer.json` tiene sintaxis correcta
- Verificar extensiones PHP están listadas

### Problema: Cold Start muy lento

**Explicación:**

- Normal en Free tier
- Después de 20 min inactividad, instancia se apaga
- Primera request la reinicia (~30 segundos)

**Workarounds para Demo/Presentación:**

1. **Hacer warm-up antes:**

   ```powershell
   # 5 minutos antes de tu presentación
   curl https://snake-game-ch1110.azurewebsites.net
   ```

2. **Crear script de keep-alive:**
   ```powershell
   # keep-alive.ps1
   while ($true) {
       curl https://snake-game-ch1110.azurewebsites.net -UseBasicParsing
       Write-Host "Ping sent at $(Get-Date)"
       Start-Sleep -Seconds 600  # Cada 10 minutos
   }
   ```

**Solución producción:**

- Upgrade a Basic tier
- Habilitar "Always On"

---

## 📸 Screenshots para el Informe Académico

### 1. Azure Portal - Resource Group

Capturar:

- Lista de todos los recursos creados
- Costos (debe mostrar €0)

### 2. Application Insights Dashboard

Capturar:

- Request rate
- Response time
- Failed requests
- Availability

### 3. GitHub Actions - Successful Deployment

Capturar:

- Lista de workflows ejecutados
- Detalles de un deployment exitoso
- Timestamp y commit asociado

### 4. Live Site - Homepage

Capturar:

- URL en barra de dirección (mostrar HTTPS)
- Página de login
- Indicador de conexión segura (candado verde)

### 5. Game Lobby

Capturar:

- Lista de partidas disponibles
- Indicadores de latencia
- Botón crear partida

### 6. Active Game Session

Capturar:

- Dos jugadores en pantalla
- Puntuaciones
- Indicador de latencia en tiempo real

### 7. Email Verification

Capturar:

- Email recibido en bandeja de entrada
- Código de verificación
- Headers mostrando origen (Brevo/tu SMTP)

### 8. Kudu Console - Database Initialization

Capturar:

- Output de `php init_azure_db.php`
- Listado de archivos en `/home/data/`

---

## 🧹 FASE 7: Limpieza Post-Calificación

**IMPORTANTE:** Solo ejecutar después de que el proyecto haya sido evaluado.

### Eliminar Todos los Recursos

```powershell
# Esto BORRA TODO el resource group y todos sus recursos
az group delete `
  --name rg-snake-game-claudia `
  --yes `
  --no-wait

# Verificar eliminación (puede tardar unos minutos)
az group list --output table
```

### Eliminar GitHub Secrets

1. GitHub → Tu repo → Settings
2. Secrets and variables → Actions
3. Encontrar `AZURE_WEBAPP_PUBLISH_PROFILE`
4. Click en "Remove" o "Delete"

### Verificar Costos

```powershell
# Ver costos acumulados durante el proyecto
az consumption usage list `
  --start-date 2024-11-01 `
  --end-date 2024-11-30 `
  --output table

# Debe mostrar €0 si usaste solo free tier
```

---

## 📚 Recursos Adicionales

### Documentación Oficial

- [Azure App Service - PHP](https://learn.microsoft.com/en-us/azure/app-service/quickstart-php)
- [Application Insights](https://learn.microsoft.com/en-us/azure/azure-monitor/app/app-insights-overview)
- [GitHub Actions for Azure](https://learn.microsoft.com/en-us/azure/app-service/deploy-github-actions)
- [Azure CLI Reference](https://learn.microsoft.com/en-us/cli/azure/)

### Tutoriales

- [Deploy PHP to Azure App Service](https://learn.microsoft.com/en-us/azure/app-service/tutorial-php-mysql-app)
- [Configure PHP in App Service](https://learn.microsoft.com/en-us/azure/app-service/configure-language-php)
- [Monitor App Service](https://learn.microsoft.com/en-us/azure/app-service/troubleshoot-diagnostic-logs)

### Herramientas

- [Azure Portal](https://portal.azure.com/)
- [Kudu (Advanced Tools)](https://github.com/projectkudu/kudu/wiki)
- [Application Insights Live Metrics](https://learn.microsoft.com/en-us/azure/azure-monitor/app/live-stream)

---

## ✅ Checklist Final de Entrega

- [ ] Código fuente en GitHub con todos los archivos de configuración
- [ ] GitHub Actions configurado y con al menos 1 deployment exitoso
- [ ] Sitio web accesible via HTTPS en Azure
- [ ] Bases de datos inicializadas y funcionando
- [ ] Emails de verificación funcionando (probar con email real)
- [ ] Dos jugadores pueden jugar simultáneamente
- [ ] Application Insights capturando métricas
- [ ] DEPLOYMENT.md completo (este documento)
- [ ] MONITORING.md completo
- [ ] Screenshots de:
  - [ ] Azure Portal (resources overview)
  - [ ] Application Insights (metrics)
  - [ ] GitHub Actions (successful deployment)
  - [ ] Live site (homepage con HTTPS)
  - [ ] Game lobby
  - [ ] Active game session
  - [ ] Email verification received
  - [ ] Cost analysis (€0)
- [ ] Documento del proyecto académico con:
  - [ ] Arquitectura explicada
  - [ ] Decisiones de diseño
  - [ ] Medidas de seguridad
  - [ ] Análisis de escalabilidad
  - [ ] Limitaciones y mejoras futuras
  - [ ] Conclusiones

---

## 👤 Información del Estudiante

**Nombre:** Claudia Hodoroga  
**URL del Proyecto:** https://snake-game-ch1110.azurewebsites.net  
**Repositorio:** https://github.com/u1988492/treballPart1  
**Fecha de Deployment:** [Completar tras despliegue]  
**Azure Resource Group:** rg-snake-game-claudia

---

## 📝 Notas Finales

Este proyecto demuestra:

1. ✅ **Deployment automatizado** con CI/CD
2. ✅ **Seguridad robusta** (HTTPS, CSRF, XSS, SQL injection protection)
3. ✅ **Arquitectura cloud** con servicios de Azure
4. ✅ **Monitorización** con Application Insights
5. ✅ **Escalabilidad** documentada (path to production)
6. ✅ **Costo-efectividad** (€0 usando free tier)
7. ✅ **Best practices** de desarrollo web

**Limitaciones conocidas y documentadas:**

- Single instance (acceptable para academia)
- SQLite en lugar de Azure SQL (documentado path de migración)
- Polling en lugar de WebSocket (futuro enhancement)
- Cold starts (inherente a Free tier)

---

**Documento creado:** 10 de noviembre de 2025  
**Última actualización:** 10 de noviembre de 2025  
**Versión:** 1.0
