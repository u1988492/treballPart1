# Monitoreo y Observabilidad

## Snake Game Multijugador - Azure Application Insights

**Fecha:** Noviembre 2025  
**Proyecto:** Snake Game Multijugador

---

## 📊 Índice

1. [Configuración de Application Insights](#configuración-de-application-insights)
2. [Dashboards y Métricas](#dashboards-y-métricas)
3. [Queries KQL Útiles](#queries-kql-útiles)
4. [Alertas Configuradas](#alertas-configuradas)
5. [Análisis de Logs](#análisis-de-logs)
6. [Troubleshooting](#troubleshooting)
7. [Optimización de Rendimiento](#optimización-de-rendimiento)

---

## 1. Configuración de Application Insights

### 1.1 Acceso al Portal

**URL:** https://portal.azure.com  
**Navegación:** Home → Application Insights → snake-game-insights

### 1.2 Instrumentation Key

Verificar configuración:

```powershell
az monitor app-insights component show `
  --app snake-game-insights `
  --resource-group rg-snake-game `
  --query instrumentationKey
```

### 1.3 Configuración en la Aplicación

La instrumentación es automática via App Service. Verificar en Azure Portal:

**Tu Web App → Configuration → Application Settings**

- `APPINSIGHTS_INSTRUMENTATIONKEY`: debe estar presente

### 1.4 Datos Recopilados Automáticamente

✅ **Peticiones HTTP:**

- URL, método, código de estado
- Duración de la petición
- User agent, IP (anonimizada)

✅ **Excepciones:**

- Stack trace completo
- Contexto de la petición
- Variables de entorno

✅ **Dependencias:**

- Llamadas a base de datos
- Llamadas HTTP externas (SMTP, APIs)

✅ **Rendimiento:**

- CPU, memoria, I/O
- Throughput de red

✅ **Disponibilidad:**

- Tests de ping automáticos
- Tiempo de respuesta

---

## 2. Dashboards y Métricas

### 2.1 Dashboard Principal - Overview

**Métricas Clave:**

| Métrica                  | Descripción                  | Objetivo  |
| ------------------------ | ---------------------------- | --------- |
| **Server Response Time** | Tiempo promedio de respuesta | < 500ms   |
| **Failed Requests**      | % de peticiones con error    | < 1%      |
| **Server Requests**      | Peticiones/segundo           | Monitoreo |
| **Availability**         | % uptime                     | > 99%     |

**Acceso:** Application Insights → Overview

### 2.2 Dashboard de Performance

**Ruta:** Application Insights → Performance

**Vistas Principales:**

**Operations:**

- Lista de endpoints ordenados por duración
- Identificar operaciones lentas
- Ver distribución de tiempos

**Dependencies:**

- Tiempo de respuesta de SQLite
- Llamadas a APIs externas (HaveIBeenPwned, SMTP)

**Análisis Típico:**

```
Endpoint: POST /api/snake_game.php
├─ Duración total: 156ms
├─ Queries SQL: 3 (45ms)
├─ Lógica aplicación: 98ms
└─ Network overhead: 13ms
```

### 2.3 Dashboard de Failures

**Ruta:** Application Insights → Failures

**Tipos de Fallos Monitoreados:**

- Excepciones no capturadas
- Errores HTTP 4xx/5xx
- Timeouts
- Dependencias fallidas

**Vista Drill-Down:**

- Stack trace completo
- Petición que causó el error
- Contexto del usuario
- Frecuencia del error

### 2.4 Dashboard de Users

**Ruta:** Application Insights → Users

**Métricas de Usuario:**

- Usuarios activos (1h, 24h, 7d, 30d)
- Sesiones por usuario
- Duración promedio de sesión
- Páginas más visitadas

**Análisis de Comportamiento:**

- User flows (recorrido por la app)
- Funnels (conversión en registro/juego)
- Retention (usuarios que regresan)

---

## 3. Queries KQL Útiles

### 3.1 Queries de Rendimiento

#### Tiempo de Respuesta por Endpoint

```kql
requests
| where timestamp > ago(24h)
| summarize
    avgDuration = avg(duration),
    p50 = percentile(duration, 50),
    p95 = percentile(duration, 95),
    p99 = percentile(duration, 99),
    count = count()
    by name
| order by avgDuration desc
```

#### Top 10 Endpoints Más Lentos

```kql
requests
| where timestamp > ago(1h)
| where duration > 1000  // > 1 segundo
| top 10 by duration desc
| project timestamp, name, url, duration, resultCode
```

#### Distribución de Tiempos de Respuesta

```kql
requests
| where timestamp > ago(24h)
| summarize count() by bin(duration, 100)
| render barchart
```

### 3.2 Queries de Tráfico

#### Peticiones por Hora

```kql
requests
| where timestamp > ago(7d)
| summarize RequestCount = count() by bin(timestamp, 1h)
| render timechart
```

#### Peticiones por Endpoint

```kql
requests
| where timestamp > ago(24h)
| summarize count() by name
| render piechart
```

#### Top User Agents (Navegadores)

```kql
requests
| where timestamp > ago(7d)
| extend browser = tostring(client_Browser)
| summarize count() by browser
| order by count_ desc
| take 10
```

### 3.3 Queries de Errores

#### Errores por Tipo

```kql
exceptions
| where timestamp > ago(24h)
| summarize ErrorCount = count() by type, outerMessage
| order by ErrorCount desc
```

#### Tasa de Error por Hora

```kql
requests
| where timestamp > ago(24h)
| summarize
    total = count(),
    failed = countif(success == false)
    by bin(timestamp, 1h)
| extend errorRate = (failed * 100.0) / total
| project timestamp, errorRate
| render timechart
```

#### Errores 500 con Stack Trace

```kql
requests
| where timestamp > ago(1h)
| where resultCode >= 500
| join kind=inner (
    exceptions
    | where timestamp > ago(1h)
) on operation_Id
| project
    timestamp,
    url,
    resultCode,
    exceptionType = type,
    message = outerMessage,
    stackTrace = details
| order by timestamp desc
```

### 3.4 Queries de Juego (Custom Events)

#### Partidas Creadas vs. Completadas

```kql
customEvents
| where timestamp > ago(24h)
| where name in ("game_created", "game_completed")
| summarize count() by name, bin(timestamp, 1h)
| render timechart
```

#### Duración Promedio de Partidas

```kql
customEvents
| where name == "game_completed"
| where timestamp > ago(7d)
| extend gameDuration = todouble(customDimensions.duration)
| summarize
    avgDuration = avg(gameDuration),
    p50 = percentile(gameDuration, 50),
    p95 = percentile(gameDuration, 95)
```

#### Latencia de Jugadores

```kql
customEvents
| where name == "player_latency"
| where timestamp > ago(1h)
| extend latency = todouble(customDimensions.latency_ms)
| summarize
    avgLatency = avg(latency),
    p95Latency = percentile(latency, 95)
    by bin(timestamp, 5m)
| render timechart
```

### 3.5 Queries de Usuarios

#### Registros por Día

```kql
customEvents
| where name == "user_registered"
| where timestamp > ago(30d)
| summarize count() by bin(timestamp, 1d)
| render columnchart
```

#### Usuarios Activos por Día

```kql
requests
| where timestamp > ago(30d)
| where isnotempty(user_Id)
| summarize dcount(user_Id) by bin(timestamp, 1d)
| render timechart
```

#### Tasa de Conversión (Registro → Juego)

```kql
let registrations = customEvents
    | where name == "user_registered"
    | where timestamp > ago(7d)
    | distinct user_Id;
let gamesPlayed = customEvents
    | where name == "game_completed"
    | where timestamp > ago(7d)
    | distinct user_Id;
print
    registeredUsers = toscalar(registrations | count),
    playedGames = toscalar(gamesPlayed | count),
    conversionRate = (toscalar(gamesPlayed | count) * 100.0) / toscalar(registrations | count)
```

### 3.6 Queries de Base de Datos

#### Queries SQL Más Lentas

```kql
dependencies
| where type == "SQL"
| where timestamp > ago(1h)
| where duration > 100  // > 100ms
| top 20 by duration desc
| project timestamp, name, duration, data, resultCode
```

#### Volumen de Queries por Tabla

```kql
dependencies
| where type == "SQL"
| where timestamp > ago(24h)
| extend tableName = extract(@"FROM (\w+)", 1, data)
| summarize count() by tableName
| render piechart
```

---

## 4. Alertas Configuradas

### 4.1 Alert: Alto Tiempo de Respuesta

**Configuración:**

```yaml
Nombre: High Response Time Alert
Condición: Avg response time > 2000ms
Ventana temporal: 5 minutos
Frecuencia evaluación: 1 minuto
Severity: Warning (Sev 2)
Acción: Email a admin
```

**Creación via CLI:**

```powershell
az monitor metrics alert create `
  --name "high-response-time" `
  --resource-group rg-snake-game `
  --scopes "/subscriptions/{sub-id}/resourceGroups/rg-snake-game/providers/Microsoft.Insights/components/snake-game-insights" `
  --condition "avg requests/duration > 2000" `
  --window-size 5m `
  --evaluation-frequency 1m `
  --severity 2 `
  --description "Alert when average response time exceeds 2 seconds"
```

### 4.2 Alert: Tasa de Error Elevada

**Configuración:**

```yaml
Nombre: High Error Rate Alert
Condición: Failed requests > 5%
Ventana temporal: 5 minutos
Severity: Error (Sev 1)
Acción: Email + SMS
```

**Query KQL:**

```kql
requests
| where timestamp > ago(5m)
| summarize
    total = count(),
    failed = countif(success == false)
| extend errorRate = (failed * 100.0) / total
| where errorRate > 5
```

### 4.3 Alert: Disponibilidad Baja

**Configuración:**

```yaml
Nombre: Low Availability Alert
Condición: Availability < 99%
Ventana temporal: 15 minutos
Severity: Critical (Sev 0)
Acción: Email + SMS + PagerDuty
```

### 4.4 Alert: Excepciones No Manejadas

**Configuración:**

```yaml
Nombre: Unhandled Exceptions
Condición: Exceptions count > 10
Ventana temporal: 5 minutos
Severity: Warning (Sev 2)
Acción: Email con stack trace
```

### 4.5 Alert: Cold Start Frecuente

**Configuración:**

```yaml
Nombre: Frequent Cold Starts
Condición: Response time > 5s más de 3 veces/hora
Ventana temporal: 1 hora
Severity: Informational (Sev 4)
Acción: Email (FYI)
```

**Query KQL:**

```kql
requests
| where timestamp > ago(1h)
| where duration > 5000
| summarize coldStarts = count()
| where coldStarts > 3
```

---

## 5. Análisis de Logs

### 5.1 Tipos de Logs Disponibles

**Application Logs:**

- Errores PHP
- Warnings
- Notice
- Custom logs

**HTTP Logs:**

- Peticiones entrantes
- Headers
- Respuestas

**Detailed Error Messages:**

- Stack traces
- Contexto de ejecución

**Failed Request Tracing:**

- Peticiones lentas o fallidas
- Timeline detallado

### 5.2 Ver Logs en Tiempo Real

**Azure CLI:**

```powershell
az webapp log tail `
  --name snake-game-XXXXX `
  --resource-group rg-snake-game
```

**Azure Portal:**

1. Tu Web App → Log stream
2. Seleccionar: Application logs o HTTP logs

### 5.3 Descargar Logs

```powershell
# Descargar todos los logs
az webapp log download `
  --name snake-game-XXXXX `
  --resource-group rg-snake-game `
  --log-file logs.zip

# Extraer y analizar
Expand-Archive logs.zip -DestinationPath ./logs
```

### 5.4 Análisis de Logs con KQL

#### Errores PHP por Tipo

```kql
traces
| where message contains "PHP"
| where severityLevel >= 2  // Warning o superior
| extend errorType = extract(@"PHP (\w+):", 1, message)
| summarize count() by errorType
| render piechart
```

#### Búsqueda de Texto en Logs

```kql
traces
| where timestamp > ago(1h)
| where message contains "database"
| project timestamp, message, severityLevel
| order by timestamp desc
```

#### Logs Agrupados por Archivo

```kql
traces
| where timestamp > ago(24h)
| extend sourceFile = extract(@"in (/[\w/]+\.php)", 1, message)
| summarize count() by sourceFile
| order by count_ desc
```

---

## 6. Troubleshooting

### 6.1 Problema: Sitio Retorna 500 Error

#### Diagnóstico

**1. Ver logs en tiempo real:**

```powershell
az webapp log tail --name snake-game-XXXXX --resource-group rg-snake-game
```

**2. Buscar excepciones recientes:**

```kql
exceptions
| where timestamp > ago(1h)
| order by timestamp desc
| take 10
```

**3. Verificar errores HTTP 500:**

```kql
requests
| where timestamp > ago(1h)
| where resultCode == 500
| join kind=inner (traces | where severityLevel == 3) on operation_Id
| project timestamp, url, message
```

#### Soluciones Comunes

**Error: Base de datos no encontrada**

```powershell
# Conectar via SSH y verificar
az webapp ssh --name snake-game-XXXXX --resource-group rg-snake-game
ls -la /home/data/
# Si no existe, ejecutar:
cd /home/site/wwwroot
php init_azure_db.php
```

**Error: Permisos de archivo**

```bash
chmod 755 /home/data
chmod 666 /home/data/*.db
```

**Error: PHP Fatal error**

- Revisar `traces` en Application Insights
- Verificar sintaxis PHP
- Comprobar extensiones requeridas

### 6.2 Problema: Alto Tiempo de Respuesta

#### Diagnóstico

**1. Identificar endpoints lentos:**

```kql
requests
| where timestamp > ago(1h)
| summarize avg(duration) by name
| order by avg_duration desc
```

**2. Analizar dependencias:**

```kql
dependencies
| where timestamp > ago(1h)
| where duration > 100
| summarize count(), avg(duration) by name
```

**3. Verificar queries SQL:**

```kql
dependencies
| where type == "SQL"
| where duration > 50
| project timestamp, name, duration, data
| order by duration desc
```

#### Soluciones

**Query SQL lenta:**

- Agregar índices en SQLite
- Optimizar JOINs
- Usar EXPLAIN QUERY PLAN

**Llamada externa lenta:**

- Implementar timeout
- Agregar caché
- Considerar llamada asíncrona

**Cold Start:**

- Documentado como limitación del Free tier
- Upgrade a Basic B1 con "Always On"

### 6.3 Problema: Email No Se Envía

#### Diagnóstico

**1. Verificar configuración SMTP:**

```powershell
az webapp config appsettings list `
  --name snake-game-XXXXX `
  --resource-group rg-snake-game `
  --output table | findstr SMTP
```

**2. Revisar logs de SMTP:**

```kql
dependencies
| where target contains "smtp"
| where timestamp > ago(1h)
| project timestamp, name, resultCode, duration
```

**3. Buscar errores de envío:**

```kql
traces
| where message contains "mail" or message contains "smtp"
| where timestamp > ago(1h)
| order by timestamp desc
```

#### Soluciones

**Credenciales incorrectas:**

```powershell
# Actualizar settings
az webapp config appsettings set `
  --name snake-game-XXXXX `
  --resource-group rg-snake-game `
  --settings `
    SMTP_HOST="smtp-relay.brevo.com" `
    SMTP_USERNAME="tu-email-correcto@ejemplo.com" `
    SMTP_PASSWORD="tu-api-key-correcta"
```

**Límite de envío alcanzado:**

- Brevo free: 300 emails/día
- Verificar dashboard de Brevo

**Email en spam:**

- Verificar configuración SPF/DKIM en Brevo
- Usar dominio verificado

### 6.4 Problema: Sesión No Persiste

#### Diagnóstico

**1. Verificar headers de cookie:**

```kql
requests
| where timestamp > ago(1h)
| where name contains "login"
| project timestamp, url, resultCode
```

**2. Revisar configuración de sesión:**

```bash
# Via SSH
az webapp ssh --name snake-game-XXXXX --resource-group rg-snake-game
php -i | grep session
```

#### Soluciones

**Cookies no seguras en HTTPS:**

- Verificar que `session.cookie_secure = 1` en Azure
- Comprobar en `config.php`

**SameSite strict bloqueando:**

- Ajustar `session.cookie_samesite` si necesario
- Verificar CORS

**Directorio de sesión no escribible:**

```bash
ls -la /tmp/
# Debería tener permisos 1777
```

### 6.5 Problema: Cold Start Delays

#### Diagnóstico

```kql
requests
| where timestamp > ago(24h)
| where duration > 5000  // > 5 segundos
| project timestamp, name, duration, url
| order by timestamp desc
```

#### Soluciones

**Limitación del Free Tier:**

- Documentar como conocido
- Informar a usuarios (página de carga)
- Para producción: Upgrade a Basic B1 con Always On

**Optimizar inicio:**

- Minimizar autoload de Composer
- Caché de configuración
- Lazy loading de dependencias

### 6.6 Problema: Database Locked

#### Diagnóstico

```kql
traces
| where message contains "database is locked"
| where timestamp > ago(1h)
| count
```

#### Soluciones

**Concurrencia SQLite:**

```php
// Configurar timeout y WAL mode
$db = new PDO('sqlite:/home/data/users.db');
$db->exec('PRAGMA busy_timeout = 5000');
$db->exec('PRAGMA journal_mode = WAL');
```

**Para producción:**

- Migrar a PostgreSQL (soporta alta concurrencia)

---

## 7. Optimización de Rendimiento

### 7.1 Métricas Objetivo

| Métrica            | Actual (estimado) | Objetivo | Excelente |
| ------------------ | ----------------- | -------- | --------- |
| **TTFB**           | 200-500ms         | < 200ms  | < 100ms   |
| **Page Load**      | 1-2s              | < 1s     | < 500ms   |
| **API Response**   | 100-300ms         | < 150ms  | < 50ms    |
| **Database Query** | 10-50ms           | < 20ms   | < 10ms    |
| **Error Rate**     | < 1%              | < 0.5%   | < 0.1%    |

### 7.2 Optimizaciones Implementadas

✅ **PDO Prepared Statements:** Previene SQL injection y mejora rendimiento  
✅ **Polling Optimizado:** 100ms en juego activo  
✅ **HTTP/2:** Habilitado automáticamente en Azure  
✅ **Compresión:** Gzip habilitado

### 7.3 Optimizaciones Recomendadas

#### Backend

**1. Caché de Queries Frecuentes:**

```php
// Implementar APCu o Redis para caché
$cache_key = 'user_prefs_' . $user_id;
$prefs = apcu_fetch($cache_key);
if ($prefs === false) {
    $prefs = $db->query("SELECT * FROM preferences WHERE user_id = ?");
    apcu_store($cache_key, $prefs, 300); // 5 minutos
}
```

**2. Índices en SQLite:**

```sql
CREATE INDEX IF NOT EXISTS idx_game_status ON game_state(game_status, last_update);
CREATE INDEX IF NOT EXISTS idx_player_latency ON player_latency(game_id, player_id);
```

**3. Batch Updates:**

```php
// En lugar de múltiples updates
$db->beginTransaction();
$stmt = $db->prepare("UPDATE game_state SET ... WHERE game_id = ?");
foreach ($updates as $update) {
    $stmt->execute([$update['game_id']]);
}
$db->commit();
```

#### Frontend

**1. Minimizar JavaScript/CSS:**

```bash
# Usar herramientas de minificación
npm install -g uglify-js
uglifyjs game.js -o game.min.js -c -m
```

**2. Lazy Loading de Imágenes:**

```html
<img src="placeholder.png" data-src="real-image.png" loading="lazy" />
```

**3. Service Worker para Assets:**

```javascript
// Cache assets estáticos
self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open("v1").then((cache) => {
      return cache.addAll(["/styles/main.css", "/js/game.js"]);
    })
  );
});
```

#### Base de Datos

**1. WAL Mode para SQLite:**

```sql
PRAGMA journal_mode = WAL;
PRAGMA synchronous = NORMAL;
PRAGMA cache_size = 10000;
```

**2. Vacuum Periódico:**

```bash
# Cron job semanal
0 2 * * 0 sqlite3 /home/data/users.db "VACUUM;"
```

### 7.4 Monitoreo Continuo

**Establecer Baseline:**

```kql
requests
| where timestamp > ago(7d)
| summarize
    baseline_p50 = percentile(duration, 50),
    baseline_p95 = percentile(duration, 95),
    baseline_p99 = percentile(duration, 99)
    by name
```

**Detectar Regresiones:**

```kql
let baseline = requests
    | where timestamp between (ago(7d) .. ago(1d))
    | summarize baseline = percentile(duration, 95) by name;
let current = requests
    | where timestamp > ago(1h)
    | summarize current = percentile(duration, 95) by name;
baseline
| join kind=inner current on name
| extend regression = (current - baseline) * 100.0 / baseline
| where regression > 20  // > 20% más lento
| project name, baseline, current, regression
```

---

## 8. Reportes y Exportación

### 8.1 Exportar Datos para Informe

**Query para métricas del proyecto:**

```kql
requests
| where timestamp between (datetime('2025-11-01') .. datetime('2025-11-30'))
| summarize
    TotalRequests = count(),
    AvgResponseTime = avg(duration),
    P95ResponseTime = percentile(duration, 95),
    ErrorRate = countif(success == false) * 100.0 / count(),
    UniqueUsers = dcount(user_Id)
| project
    TotalRequests,
    AvgResponseTime = round(AvgResponseTime, 2),
    P95ResponseTime = round(P95ResponseTime, 2),
    ErrorRate = round(ErrorRate, 2),
    UniqueUsers
```

**Exportar a CSV:**

1. Ejecutar query en Application Insights
2. Click en "Export" → "Export to CSV"
3. Usar en informe académico

### 8.2 Screenshots Recomendadas para Informe

1. **Overview Dashboard** con métricas principales
2. **Performance Chart** mostrando tiempos de respuesta
3. **Application Map** mostrando dependencias
4. **Failures Dashboard** (ojalá vacío 😊)
5. **Users Dashboard** mostrando actividad
6. **Custom Query** de partidas jugadas

---

## 9. Recursos Adicionales

### 9.1 Documentación Oficial

- [Application Insights Overview](https://docs.microsoft.com/azure/azure-monitor/app/app-insights-overview)
- [KQL Query Language](https://docs.microsoft.com/azure/data-explorer/kusto/query/)
- [Azure Monitor Alerts](https://docs.microsoft.com/azure/azure-monitor/alerts/alerts-overview)

### 9.2 Herramientas Útiles

- [Kusto Explorer](https://docs.microsoft.com/azure/data-explorer/kusto/tools/kusto-explorer) - IDE para queries KQL
- [Azure Mobile App](https://azure.microsoft.com/features/azure-portal/mobile-app/) - Monitoreo desde móvil
- [Power BI](https://powerbi.microsoft.com/) - Dashboards avanzados

### 9.3 Comunidad y Soporte

- [Stack Overflow - Azure Tag](https://stackoverflow.com/questions/tagged/azure)
- [Azure Forums](https://docs.microsoft.com/answers/products/azure)
- [Azure Status](https://status.azure.com/) - Estado de servicios

---

**Documento preparado por:** Claudia Hodoroga  
**Fecha:** Noviembre 2025  
**Versión:** 1.0
