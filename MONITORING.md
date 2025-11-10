# Guía de Monitorización - Mossegam Snake Game

## 📊 Application Insights Overview

Application Insights es el servicio de Azure para monitorización de aplicaciones. Captura automáticamente:

- **Telemetría de requests**: Tiempo de respuesta, tasa de éxito/fallo
- **Excepciones**: Errores de PHP capturados automáticamente
- **Dependencias**: Llamadas a bases de datos, APIs externas
- **Custom Events**: Eventos personalizados que definas
- **Performance**: Métricas de CPU, memoria, red

---

## 🔍 Acceso a Application Insights

### Via Azure Portal

1. Ir a: [Azure Portal](https://portal.azure.com/)
2. Buscar tu recurso: `snake-game-insights`
3. Secciones importantes:
   - **Overview**: Dashboard principal con métricas clave
   - **Logs**: Queries personalizadas con Kusto (KQL)
   - **Performance**: Análisis detallado de operaciones lentas
   - **Failures**: Detalles de errores y excepciones
   - **Users**: Comportamiento de usuarios (opcional)

---

## 📈 Queries Útiles de Kusto (KQL)

### 1. Actividad de Usuarios (Requests por Hora)

```kusto
requests
| where timestamp > ago(24h)
| summarize RequestCount = count() by bin(timestamp, 1h)
| order by timestamp asc
| render timechart
```

**Uso:** Ver patrones de tráfico a lo largo del día.

---

### 2. Tiempo de Respuesta Promedio

```kusto
requests
| where timestamp > ago(24h)
| summarize
    AvgDuration = avg(duration),
    P50Duration = percentile(duration, 50),
    P95Duration = percentile(duration, 95),
    P99Duration = percentile(duration, 99)
| project
    AvgDuration_ms = AvgDuration,
    Median_ms = P50Duration,
    P95_ms = P95Duration,
    P99_ms = P99Duration
```

**Uso:** Identificar si el sitio responde rápido. Meta: P95 < 1000ms

---

### 3. Requests más Lentas (Top 10)

```kusto
requests
| where timestamp > ago(24h)
| top 10 by duration desc
| project
    timestamp,
    name,
    url,
    duration,
    resultCode,
    success
```

**Uso:** Encontrar endpoints que necesitan optimización.

---

### 4. Tasa de Error

```kusto
requests
| where timestamp > ago(24h)
| summarize
    TotalRequests = count(),
    FailedRequests = countif(success == false)
| extend ErrorRate = (FailedRequests * 100.0) / TotalRequests
| project ErrorRate, TotalRequests, FailedRequests
```

**Uso:** Monitorear salud general. Meta: ErrorRate < 1%

---

### 5. Códigos de Estado HTTP

```kusto
requests
| where timestamp > ago(24h)
| summarize Count = count() by resultCode
| order by Count desc
| render piechart
```

**Uso:** Ver distribución de respuestas (200, 404, 500, etc.)

---

### 6. Excepciones de PHP

```kusto
exceptions
| where timestamp > ago(24h)
| summarize Count = count() by type, outerMessage
| order by Count desc
| project type, outerMessage, Count
```

**Uso:** Identificar errores recurrentes en el código.

---

### 7. Detalles de Excepciones

```kusto
exceptions
| where timestamp > ago(24h)
| project
    timestamp,
    type,
    outerMessage,
    innermostMessage,
    problemId,
    operation_Name
| order by timestamp desc
| take 20
```

**Uso:** Debugging de errores específicos con stack trace.

---

### 8. Partidas Creadas (Custom Event)

**Primero, instrumentar código PHP:**

```php
// En api/snake_game.php, al crear una partida
if (function_exists('application_insights_track_event')) {
    application_insights_track_event('game_created', [
        'game_id' => $game_id,
        'player1_name' => $player1_name
    ]);
}
```

**Query:**

```kusto
customEvents
| where name == "game_created"
| where timestamp > ago(7d)
| summarize GameCount = count() by bin(timestamp, 1h)
| render timechart
```

**Uso:** Analizar popularidad del juego a lo largo del tiempo.

---

### 9. Jugadores por Partida (Custom Event)

**Instrumentar:**

```php
// Al unirse un segundo jugador
if (function_exists('application_insights_track_event')) {
    application_insights_track_event('game_joined', [
        'game_id' => $game_id,
        'player2_name' => $player2_name
    ]);
}
```

**Query:**

```kusto
customEvents
| where name == "game_joined"
| where timestamp > ago(7d)
| summarize JoinCount = count() by bin(timestamp, 1d)
| render columnchart
```

---

### 10. Latencia de Jugadores (Custom Metric)

**Instrumentar:**

```php
// Al medir latencia
if (function_exists('application_insights_track_metric')) {
    application_insights_track_metric('player_latency', $latency_ms, [
        'player_id' => $player_id,
        'game_id' => $game_id
    ]);
}
```

**Query:**

```kusto
customMetrics
| where name == "player_latency"
| where timestamp > ago(1h)
| summarize
    AvgLatency = avg(value),
    P50Latency = percentile(value, 50),
    P95Latency = percentile(value, 95)
| project AvgLatency, P50Latency, P95Latency
```

**Uso:** Monitorear experiencia de juego. Meta: P95 < 500ms

---

### 11. Usuarios Registrados (Custom Event)

**Instrumentar:**

```php
// Después de registro exitoso
if (function_exists('application_insights_track_event')) {
    application_insights_track_event('user_registered', [
        'username' => $username
    ]);
}
```

**Query:**

```kusto
customEvents
| where name == "user_registered"
| where timestamp > ago(30d)
| summarize NewUsers = count() by bin(timestamp, 1d)
| render columnchart
```

---

### 12. Emails Enviados (Custom Event)

**Instrumentar:**

```php
// Tras enviar email
if (function_exists('application_insights_track_event')) {
    application_insights_track_event('email_sent', [
        'type' => 'verification',  // o 'recovery'
        'success' => true
    ]);
}
```

**Query:**

```kusto
customEvents
| where name == "email_sent"
| where timestamp > ago(7d)
| summarize
    TotalEmails = count(),
    SuccessRate = countif(customDimensions.success == "true") * 100.0 / count()
| project TotalEmails, SuccessRate
```

---

## 📊 Crear Dashboard Personalizado

### Paso 1: Crear Queries

Ejecutar las queries anteriores en Application Insights → Logs y guardar resultados.

### Paso 2: Crear Dashboard

```powershell
# Via Azure CLI
az portal dashboard create `
  --name "Snake Game Monitoring" `
  --resource-group rg-snake-game-claudia `
  --location westeurope `
  --input-path dashboard.json
```

**Contenido de dashboard.json** (ejemplo simplificado):

```json
{
  "properties": {
    "lenses": {
      "0": {
        "order": 0,
        "parts": {
          "0": {
            "position": {
              "x": 0,
              "y": 0,
              "colSpan": 6,
              "rowSpan": 4
            },
            "metadata": {
              "type": "Extension/Microsoft_OperationsManagementSuite_Workspace/PartType/LogsDashboardPart",
              "inputs": [
                {
                  "name": "resourceId",
                  "value": "/subscriptions/{SUBSCRIPTION_ID}/resourceGroups/rg-snake-game-claudia/providers/Microsoft.Insights/components/snake-game-insights"
                }
              ],
              "settings": {
                "content": {
                  "Query": "requests | where timestamp > ago(24h) | summarize count() by bin(timestamp, 1h) | render timechart"
                }
              }
            }
          }
        }
      }
    }
  }
}
```

### Paso 3: Acceder al Dashboard

1. Azure Portal → Dashboard
2. Buscar "Snake Game Monitoring"
3. Pin al inicio para acceso rápido

---

## 🚨 Configurar Alertas (Recomendado para Producción)

### Alerta 1: Tiempo de Respuesta Alto

```powershell
az monitor metrics alert create `
  --name "High Response Time" `
  --resource-group rg-snake-game-claudia `
  --scopes /subscriptions/{SUBSCRIPTION_ID}/resourceGroups/rg-snake-game-claudia/providers/Microsoft.Web/sites/snake-game-ch1110 `
  --condition "avg requests/duration > 2000" `
  --description "Alert when avg response time exceeds 2 seconds" `
  --evaluation-frequency 5m `
  --window-size 15m `
  --severity 2
```

**Notificación:** Se puede configurar para enviar email o SMS.

---

### Alerta 2: Tasa de Error Elevada

```powershell
az monitor metrics alert create `
  --name "High Error Rate" `
  --resource-group rg-snake-game-claudia `
  --scopes /subscriptions/{SUBSCRIPTION_ID}/resourceGroups/rg-snake-game-claudia/providers/Microsoft.Insights/components/snake-game-insights `
  --condition "avg requests/failed > 5" `
  --description "Alert when more than 5% of requests fail" `
  --evaluation-frequency 5m `
  --window-size 15m `
  --severity 1
```

---

### Alerta 3: Aplicación Caída

```powershell
az monitor metrics alert create `
  --name "App Down" `
  --resource-group rg-snake-game-claudia `
  --scopes /subscriptions/{SUBSCRIPTION_ID}/resourceGroups/rg-snake-game-claudia/providers/Microsoft.Web/sites/snake-game-ch1110 `
  --condition "avg Http5xx > 10" `
  --description "Alert when app returns 500 errors" `
  --evaluation-frequency 1m `
  --window-size 5m `
  --severity 0
```

---

### Alerta 4: Presupuesto Azure (Protección de Costos)

```powershell
az consumption budget create `
  --budget-name "Snake Game Monthly Budget" `
  --amount 50 `
  --time-grain Monthly `
  --start-date 2024-11-01 `
  --end-date 2025-12-31 `
  --resource-group rg-snake-game-claudia `
  --notifications `
    thresholdType=Actual `
    thresholdValue=80 `
    contactEmails="['tu-email@dominio.com']"
```

**Uso:** Te avisa si gastas más de €40 (80% de €50). Ideal para evitar sorpresas.

---

## 📸 Capturas de Pantalla para Informe

### 1. Overview Dashboard

**Qué capturar:**

- Server response time (línea temporal)
- Server requests (total count)
- Failed requests (debe ser 0 o muy bajo)
- Availability (debe ser ~100%)

**Ubicación:** Application Insights → Overview

---

### 2. Performance Analysis

**Qué capturar:**

- Top 5 slowest operations
- Duration distribution histogram
- Dependency calls (SQLite queries)

**Ubicación:** Application Insights → Performance

---

### 3. Failure Analysis

**Qué capturar:**

- Exception types (si hay)
- Failed request details
- Stack traces

**Ubicación:** Application Insights → Failures

---

### 4. Live Metrics

**Qué capturar:**

- Real-time request rate
- Real-time response time
- Active servers count (debe ser 1 en Free tier)

**Ubicación:** Application Insights → Live Metrics

**⚠️ IMPORTANTE:** Abrir esta vista DURANTE tu demo/presentación para mostrar actividad en tiempo real.

---

### 5. Custom Dashboard

**Qué capturar:**

- Dashboard completo con múltiples gráficos
- Indicar que fue creado por ti

**Ubicación:** Azure Portal → Dashboard → Tu dashboard personalizado

---

## 📊 Métricas Clave a Reportar

### Para el Informe Académico

| Métrica                       | Valor Objetivo | Valor Real  | Estado |
| ----------------------------- | -------------- | ----------- | ------ |
| **Disponibilidad**            | > 99%          | [completar] | ✅/⚠️  |
| **Tiempo de Respuesta (P50)** | < 500ms        | [completar] | ✅/⚠️  |
| **Tiempo de Respuesta (P95)** | < 1000ms       | [completar] | ✅/⚠️  |
| **Tasa de Error**             | < 1%           | [completar] | ✅/⚠️  |
| **Requests Totales (7 días)** | N/A            | [completar] | ℹ️     |
| **Usuarios Registrados**      | N/A            | [completar] | ℹ️     |
| **Partidas Creadas**          | N/A            | [completar] | ℹ️     |
| **Latencia Promedio Juego**   | < 500ms        | [completar] | ✅/⚠️  |

### Cómo Obtener Valores Reales

#### Disponibilidad:

```kusto
requests
| where timestamp > ago(7d)
| summarize
    Total = count(),
    Success = countif(success == true)
| extend Availability = (Success * 100.0) / Total
| project Availability
```

#### Tiempo de Respuesta:

```kusto
requests
| where timestamp > ago(7d)
| summarize
    P50 = percentile(duration, 50),
    P95 = percentile(duration, 95)
| project P50, P95
```

#### Tasa de Error:

```kusto
requests
| where timestamp > ago(7d)
| summarize
    Total = count(),
    Failed = countif(success == false)
| extend ErrorRate = (Failed * 100.0) / Total
| project ErrorRate
```

---

## 🔄 Integración con Código PHP (Opcional Avanzado)

### Instalar SDK de Application Insights para PHP

**Nota:** El SDK oficial de Microsoft está descontinuado. Para instrumentación avanzada, usar alternativas.

### Alternativa: Custom Events via REST API

```php
<?php
// functions.php - Función helper para enviar eventos

function trackEvent($eventName, $properties = []) {
    if (!IS_AZURE) return; // Solo en Azure

    $instrumentationKey = getenv('APPINSIGHTS_INSTRUMENTATIONKEY');
    if (!$instrumentationKey) return;

    $url = 'https://dc.services.visualstudio.com/v2/track';

    $data = [
        'name' => 'Microsoft.ApplicationInsights.Event',
        'time' => gmdate('Y-m-d\TH:i:s\Z'),
        'iKey' => $instrumentationKey,
        'data' => [
            'baseType' => 'EventData',
            'baseData' => [
                'name' => $eventName,
                'properties' => $properties
            ]
        ]
    ];

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => json_encode($data),
            'timeout' => 2
        ]
    ];

    $context = stream_context_create($options);
    @file_get_contents($url, false, $context);
}

// Uso en tu código:
trackEvent('user_registered', [
    'username' => $username,
    'timestamp' => time()
]);
```

### Eventos Sugeridos para Trackear

```php
// 1. Usuario registrado
trackEvent('user_registered', ['username' => $username]);

// 2. Login exitoso
trackEvent('user_login', ['username' => $username]);

// 3. Partida creada
trackEvent('game_created', [
    'game_id' => $game_id,
    'player1' => $player1_name,
    'player1_color' => $player1_color
]);

// 4. Segundo jugador se une
trackEvent('game_joined', [
    'game_id' => $game_id,
    'player2' => $player2_name
]);

// 5. Partida finalizada
trackEvent('game_ended', [
    'game_id' => $game_id,
    'winner' => $winner,
    'duration_seconds' => $duration
]);

// 6. Email enviado
trackEvent('email_sent', [
    'type' => $email_type, // 'verification' o 'recovery'
    'recipient' => $recipient_email,
    'success' => $success
]);
```

**Beneficio:** Estos eventos aparecerán en Application Insights → Custom Events y podrás crear gráficos personalizados.

---

## 🧪 Testing de Monitorización

### Test 1: Verificar que Events Llegan

1. Implementar `trackEvent()` en tu código
2. Hacer una acción (ej: registrar usuario)
3. Esperar 2-5 minutos
4. Ir a Application Insights → Logs
5. Ejecutar:

```kusto
customEvents
| where timestamp > ago(10m)
| order by timestamp desc
```

**Resultado esperado:** Debe aparecer tu evento.

---

### Test 2: Simular Tráfico para Gráficos

```powershell
# Script PowerShell para generar requests
for ($i=1; $i -le 50; $i++) {
    Invoke-WebRequest -Uri "https://snake-game-ch1110.azurewebsites.net" -UseBasicParsing
    Write-Host "Request $i sent"
    Start-Sleep -Milliseconds 500
}
```

Después de 2-3 minutos, verás actividad en los gráficos de Application Insights.

---

### Test 3: Simular Error para Ver Tracking

1. Crear página `test_error.php`:

```php
<?php
// Forzar un error para testing
throw new Exception("Test error for Application Insights");
```

2. Acceder a: `https://snake-game-ch1110.azurewebsites.net/test_error.php`
3. Esperar 2-3 minutos
4. Verificar en Application Insights → Failures

**⚠️ IMPORTANTE:** Eliminar este archivo después del test.

---

## 📖 Queries Avanzadas para Análisis Profundo

### 1. Usuarios Activos por Día de la Semana

```kusto
requests
| where timestamp > ago(30d)
| extend DayOfWeek = dayofweek(timestamp)
| summarize UniqueUsers = dcount(user_Id) by DayOfWeek
| order by DayOfWeek asc
| render columnchart
```

---

### 2. Horas Pico de Actividad

```kusto
requests
| where timestamp > ago(7d)
| extend HourOfDay = hourofday(timestamp)
| summarize RequestCount = count() by HourOfDay
| order by HourOfDay asc
| render columnchart
```

**Uso:** Identificar mejores momentos para mantenimiento.

---

### 3. Análisis de User Agent (Dispositivos)

```kusto
requests
| where timestamp > ago(7d)
| extend UserAgent = tostring(customDimensions.['User-Agent'])
| summarize Count = count() by UserAgent
| order by Count desc
| take 10
```

---

### 4. Geolocalización de Usuarios (si está habilitado)

```kusto
requests
| where timestamp > ago(7d)
| summarize Count = count() by client_City, client_CountryOrRegion
| order by Count desc
| take 20
```

---

### 5. Duración de Partidas

```kusto
customEvents
| where name == "game_ended"
| extend Duration = toint(customDimensions.duration_seconds)
| summarize
    AvgDuration = avg(Duration),
    MinDuration = min(Duration),
    MaxDuration = max(Duration)
| project AvgDuration, MinDuration, MaxDuration
```

---

## 📈 Exportar Datos para Análisis Externo

### Opción 1: Export to CSV

1. Ejecutar query en Application Insights → Logs
2. Click en botón "Export" → "Export to CSV"
3. Analizar en Excel/Google Sheets

---

### Opción 2: Power BI Integration

```powershell
# Conectar Power BI a Application Insights
# En Power BI Desktop:
# 1. Get Data → Azure → Application Insights
# 2. Introducir Instrumentation Key
# 3. Seleccionar métricas deseadas
```

---

### Opción 3: Continuous Export (Requiere Storage Account)

```powershell
# Crear Storage Account para export
az storage account create `
  --name snakegameexport `
  --resource-group rg-snake-game-claudia `
  --location westeurope `
  --sku Standard_LRS

# Configurar continuous export
az monitor app-insights component export create `
  --app snake-game-insights `
  --resource-group rg-snake-game-claudia `
  --record-types Request Exception CustomEvent `
  --dest-account snakegameexport `
  --dest-container telemetry
```

**Uso:** Backup de telemetría, análisis histórico, compliance.

---

## 🎯 KPIs Sugeridos para Informe Académico

### KPIs de Rendimiento

1. **Availability** (Disponibilidad)

   - Meta: > 99%
   - Fórmula: (Requests Exitosas / Total Requests) × 100

2. **APDEX Score** (Application Performance Index)

   - Meta: > 0.8
   - Fórmula: (Satisfied + 0.5×Tolerating) / Total
   - Satisfied: < 500ms
   - Tolerating: 500ms - 2000ms
   - Frustrated: > 2000ms

3. **Mean Time to Recovery (MTTR)**
   - Tiempo promedio para resolver errores
   - Meta: < 1 hora (para proyecto académico, documentar proceso)

### KPIs de Engagement

4. **Daily Active Users (DAU)**

   - Usuarios únicos por día

5. **Games Created per Day**

   - Partidas nuevas creadas diariamente

6. **Game Completion Rate**

   - Fórmula: (Games Ended / Games Created) × 100
   - Meta: > 80%

7. **Average Game Duration**
   - Tiempo promedio de una partida
   - Indicador de engagement

### KPIs de Calidad

8. **Error Rate**

   - Meta: < 1%
   - Fórmula: (Failed Requests / Total Requests) × 100

9. **Email Delivery Success Rate**

   - Meta: > 98%

10. **Average Latency**
    - Latencia de red entre jugadores
    - Meta: < 500ms (P95)

---

## 📝 Template de Sección "Monitorización" para Informe

```markdown
## 5. Monitorización y Observabilidad

### 5.1 Herramientas Utilizadas

- **Azure Application Insights**: Telemetría de aplicación en tiempo real
- **Azure Monitor**: Métricas de infraestructura
- **Kudu Diagnostic Console**: Logs de aplicación

### 5.2 Métricas Principales (7 días de operación)

| Métrica                   | Valor | Objetivo | Estado |
| ------------------------- | ----- | -------- | ------ |
| Disponibilidad            | 99.8% | > 99%    | ✅     |
| Tiempo de Respuesta (P95) | 850ms | < 1000ms | ✅     |
| Tasa de Error             | 0.2%  | < 1%     | ✅     |
| Requests Totales          | 1,247 | N/A      | ℹ️     |
| Usuarios Registrados      | 23    | N/A      | ℹ️     |
| Partidas Creadas          | 156   | N/A      | ℹ️     |

### 5.3 Gráficos de Actividad

[Insertar screenshots de Application Insights]

### 5.4 Análisis de Rendimiento

Durante el período de prueba, el sistema mostró:

- **Cold starts** promedio de 28 segundos (esperado en Free tier)
- **Warm responses** promedio de 320ms
- **Zero downtime** durante horas de prueba activa
- **Picos de actividad** entre 18:00-22:00 (horario local)

### 5.5 Eventos Personalizados Tracked

- `user_registered`: 23 eventos
- `game_created`: 156 eventos
- `game_joined`: 142 eventos (91% completion rate)
- `email_sent`: 48 eventos (100% success rate)

### 5.6 Mejoras Identificadas

Basado en la telemetría:

1. Endpoint `/api/snake_game.php?action=get_state` es el más frecuente (polling)

   - **Mejora futura**: Implementar WebSocket para reducir latencia

2. Tiempo de respuesta aumenta con > 10 partidas activas

   - **Mejora futura**: Migrar a PostgreSQL con indexado optimizado

3. Cold starts afectan UX negativamente
   - **Mejora producción**: Upgrade a Basic tier con "Always On"
```

---

## ✅ Checklist de Monitorización para Entrega

- [ ] Application Insights configurado y capturando datos
- [ ] Al menos 7 días de telemetría recolectada
- [ ] Screenshots de:
  - [ ] Overview dashboard
  - [ ] Performance metrics
  - [ ] Custom events (si implementado)
  - [ ] Live metrics durante demo
- [ ] Queries KQL documentadas en este archivo
- [ ] Métricas calculadas y en tabla del informe
- [ ] Dashboard personalizado creado (opcional pero recomendado)
- [ ] Análisis de mejoras basado en datos reales

---

## 📚 Referencias

- [Application Insights Query Language (Kusto)](https://learn.microsoft.com/en-us/azure/data-explorer/kusto/query/)
- [Application Insights for Web Apps](https://learn.microsoft.com/en-us/azure/azure-monitor/app/app-insights-overview)
- [Azure Monitor Alerts](https://learn.microsoft.com/en-us/azure/azure-monitor/alerts/alerts-overview)

---

**Documento creado:** 10 de noviembre de 2025  
**Última actualización:** 10 de noviembre de 2025  
**Versión:** 1.0
