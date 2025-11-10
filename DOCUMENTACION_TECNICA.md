# Documentación Técnica - Snake Multijugador

**Proyecto:** Mossegam - Juego Snake Multijugador en Tiempo Real  
**Autores:** Claudia Hodoroga y Federico Diaz  
**Curso:** Cuarto GDDV - Asignatura Multijugador  
**Fecha:** Noviembre 2025

---

## 1. Resumen del Proyecto

Implementación de un juego Snake multijugador en tiempo real para 2 jugadores, con sistema completo de gestión de usuarios, autenticación segura, y sincronización de estado de juego mediante polling.

**Tecnologías:**
- **Frontend:** HTML5, CSS3, JavaScript vanilla, Canvas API
- **Backend:** PHP 8.2
- **Base de datos:** SQLite (2 bases: usuarios y partidas)
- **Deployment:** Azure App Service (F1 Free tier)
- **Email:** Brevo API para verificación de usuarios

**URL de producción:** https://snake-game-snakegame20251110123137.azurewebsites.net

---

## 2. Arquitectura del Sistema

### 2.1. Estructura de Directorios

```
treballPart1/
├── public/                      # Document root
│   ├── index.php               # Controlador principal (router)
│   ├── config.php              # Configuración (DB, SMTP, constantes)
│   ├── api/
│   │   ├── functions.php       # Funciones auxiliares (auth, email, rate limiting)
│   │   ├── snake_game.php      # API del juego (crear, unir, actualizar, cancelar)
│   │   └── preferences.php     # API de preferencias de usuario
│   ├── js/
│   │   ├── lobby.js            # Lógica del lobby (listar partidas, crear, unirse)
│   │   └── game.js             # Lógica del juego (render, input, sincronización)
│   ├── pages/
│   │   ├── templates/          # Plantillas HTML para auth (login, register, etc.)
│   │   ├── lobby.html          # Interfaz del lobby
│   │   ├── game.html           # Interfaz del juego
│   │   └── profile_settings.html  # Configuración de usuario
│   └── styles/                 # CSS
├── private/                     # Archivos privados (fuera del document root)
│   ├── users.db                # Base de datos de usuarios
│   ├── games.db                # Base de datos de partidas
│   ├── php_errors.log          # Logs de errores
│   └── emails/                 # Fallback para emails (desarrollo)
└── setup/
    └── create_databases.sql    # Schema SQL de las bases de datos
```

### 2.2. Bases de Datos

**`users.db`** - Gestión de usuarios y autenticación:
```sql
CREATE TABLE users (
    user_id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_name TEXT UNIQUE NOT NULL,
    user_email TEXT UNIQUE NOT NULL,
    user_password TEXT NOT NULL,
    totp_secret TEXT,
    totp_enabled INTEGER DEFAULT 0,
    email_verified INTEGER DEFAULT 0,
    recovery_token TEXT,
    recovery_expires INTEGER,
    failed_attempts INTEGER DEFAULT 0,
    last_attempt INTEGER DEFAULT 0,
    locked_until INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    display_name TEXT,
    preferred_color TEXT,
    preferred_controls TEXT
);
```

**`games.db`** - Estado de partidas multijugador:
```sql
CREATE TABLE game_state (
    game_id TEXT PRIMARY KEY,
    player1_id INTEGER NOT NULL,
    player2_id INTEGER,
    player1_name TEXT,
    player2_name TEXT,
    player1_color TEXT,
    player2_color TEXT,
    player1_snake TEXT,  -- JSON: [{"x":20,"y":20}, ...]
    player2_snake TEXT,
    player1_direction TEXT,
    player2_direction TEXT,
    player1_next_direction TEXT,
    player2_next_direction TEXT,
    fruits TEXT,         -- JSON: [{"x":10,"y":15,"type":"apple"}, ...]
    game_status TEXT DEFAULT 'waiting',  -- waiting, playing, finished
    winner TEXT,
    last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## 3. Gestión de Sesiones Multijugador

### 3.1. Flujo de Creación y Unión a Partida

```
1. JUGADOR 1 (Host):
   ├─ Entra al lobby
   ├─ Crea partida → POST /api/snake_game.php?action=create_game
   │  └─ Se genera game_id único
   │  └─ Estado: 'waiting', solo player1_id asignado
   ├─ Aparece en lista de partidas disponibles
   └─ Espera a que alguien se una (polling cada 2s)

2. JUGADOR 2 (Guest):
   ├─ Ve la partida en el lobby
   ├─ Se une → POST /api/snake_game.php?action=join_game
   │  └─ Se asigna player2_id
   │  └─ Estado cambia a 'playing'
   │  └─ Se inicializan serpientes y frutas
   └─ Ambos jugadores son redirigidos automáticamente al juego

3. DURANTE LA PARTIDA:
   ├─ Cada jugador:
   │  ├─ Captura input local (WASD o flechas)
   │  ├─ Envía dirección → POST /api/snake_game.php?action=update_game
   │  └─ Recibe estado completo → GET /api/snake_game.php?action=get_state
   │       (cada 100ms)
   └─ El servidor:
      ├─ Valida movimientos (no reversa)
      ├─ Mueve ambas serpientes
      ├─ Detecta colisiones (frutas, paredes, serpientes)
      ├─ Actualiza puntuaciones
      └─ Retorna estado actualizado
```

### 3.2. Sincronización Cliente-Servidor

**Archivo clave:** `public/js/game.js`

```javascript
// Polling del estado del juego (línea ~120)
async function fetchGameState() {
    const res = await fetch(`../api/snake_game.php?action=get_state&game_id=${gameId}`);
    const data = await res.json();
    
    if (data.error) {
        // Manejar errores (juego terminado, jugador desconectado)
    }
    
    gameState = data;
    updateUI();      // Actualizar marcadores, latencias
    renderGame();    // Redibujar canvas
}

// Envío de input del jugador (línea ~150)
async function sendMove(direction) {
    const formData = new FormData();
    formData.append('game_id', gameId);
    formData.append('direction', direction);
    
    await fetch('../api/snake_game.php?action=update_game', {
        method: 'POST',
        body: formData
    });
}
```

**Archivo clave:** `public/api/snake_game.php`

```php
// Actualización del juego (línea ~280)
case 'update_game':
    // 1. Validar que el juego existe y el jugador participa
    // 2. Aplicar dirección del jugador (validando no-reversa)
    // 3. Mover ambas serpientes
    // 4. Detectar colisiones:
    //    - Con frutas → aumentar score, crecer serpiente
    //    - Con paredes/sí mismo → game_status = 'finished'
    //    - Con otro jugador → ambos mueren (empate)
    // 5. Guardar estado actualizado en DB
    // 6. Retornar confirmación
```

### 3.3. Gestión de Latencia

**Medición de latencia por jugador:**
- Cada jugador envía ping al servidor al cargar el lobby
- Se calcula RTT (Round Trip Time) con cada partida disponible
- Solo se muestran partidas con latencia < 300ms
- Durante el juego, se monitoriza la latencia de ambos jugadores

**Compensación de latencia:**
- Sistema de predicción local (cliente mueve serpiente antes de confirmar con servidor)
- Corrección del servidor si hay desincronización

```javascript
// public/js/lobby.js (línea ~170)
async function measureLatency(gameId) {
    const start = performance.now();
    await fetch(`../api/snake_game.php?action=ping&game_id=${gameId}`);
    const latency = performance.now() - start;
    return latency;
}
```

---

## 4. Sistema de Autenticación

### 4.1. Registro y Verificación de Email

```
1. Usuario completa formulario registro
2. Validaciones:
   - Contraseña ≥ 12 caracteres
   - Password strength check (pwned passwords API)
   - Email único
3. Se crea usuario con email_verified = 0
4. Se envía email con código verificación (6 dígitos)
   └─ Brevo API: POST https://api.brevo.com/v3/smtp/email
5. Usuario introduce código
6. Se marca email_verified = 1
```

**Archivo clave:** `public/api/functions.php` (líneas 105-190)

### 4.2. Rate Limiting y Seguridad

```php
// Prevención de brute force (línea 230)
function check_rate_limit($conn, $username) {
    // Máximo 5 intentos fallidos
    // Lock por 15 minutos después de 5 intentos
    // Reset de contador tras login exitoso
}

// CSRF Protection
// Tokens de sesión generados y validados en cada acción crítica
```

---

## 5. Puntos de Interés para Revisión del Código

### 5.1. Gestión de Estado del Juego

**`public/api/snake_game.php` (líneas 140-230):**
- Función `move_snake()`: Lógica de movimiento y detección de colisiones
- Función `random_fruits()`: Generación de frutas evitando serpientes
- Función `check_collision()`: Detección de colisiones (paredes, auto-colisión, jugador-jugador)

### 5.2. Sincronización Multijugador

**`public/js/game.js`:**
- **Líneas 100-130:** Loop principal del juego (polling cada 100ms)
- **Líneas 495-580:** Renderizado del canvas (ambas serpientes, frutas, grid)
- **Líneas 140-180:** Captura y envío de input del usuario

### 5.3. Lobby y Matchmaking

**`public/js/lobby.js`:**
- **Líneas 104-180:** Carga de partidas disponibles con medición de latencia
- **Líneas 240-320:** Renderizado de lista de partidas (filtrado por calidad)
- **Líneas 340-390:** Lógica de unirse a partida
- **Líneas 120-140:** Auto-redirección cuando alguien se une a tu partida

### 5.4. Prevención de Colores Similares

**`public/js/game.js` (líneas 405-485):**
```javascript
// Ajuste automático de colores si son demasiado similares
function adjustColorsIfNeeded() {
    // Calcula diferencia de color HSL
    // Si diferencia < umbral, genera color distinto
    // Muestra indicador visual al jugador
}
```

---

## 6. Características Implementadas

### ✅ Autenticación y Usuarios
- [x] Registro con verificación de email
- [x] Login con rate limiting
- [x] Recuperación de contraseña
- [x] Perfil de usuario (display name, color preferido, controles)
- [x] Validación de contraseñas seguras (pwned passwords)

### ✅ Gestión de Partidas
- [x] Crear partida (host)
- [x] Unirse a partida disponible
- [x] Cancelar partida (solo host)
- [x] Auto-redirección al juego cuando alguien se une

### ✅ Juego Multijugador
- [x] Sincronización en tiempo real (polling 100ms)
- [x] Detección de colisiones (frutas, paredes, serpientes)
- [x] Sistema de puntuación
- [x] 6 frutas simultáneas en el tablero
- [x] Detección de ganador/empate
- [x] Pantalla de game over con estadísticas

### ✅ Optimizaciones
- [x] Medición de latencia por partida
- [x] Filtrado de partidas por calidad de conexión
- [x] Display de latencia de ambos jugadores
- [x] Ajuste automático de colores similares
- [x] Predicción local de movimientos

---

## 7. Deployment en Azure

**Configuración:**
- **App Service:** Linux, PHP 8.2, F1 Free tier
- **Document Root:** `/home/site/wwwroot/public/` (configurado via nginx)
- **Bases de datos:** SQLite en `/home/site/wwwroot/private/`
- **Logs:** Application Insights + archivo local

**Variables de entorno configuradas:**
```
SMTP_HOST=smtp-relay.brevo.com
SMTP_FROM_EMAIL=u1988492@campus.udg.edu
BREVO_API_KEY=xkeysib-...
APPINSIGHTS_INSTRUMENTATIONKEY=c35268a5-...
```

**Método de deployment:**
- Manual via Kudu (https://[app-name].scm.azurewebsites.net)
- Subida de archivos via web interface
- GitHub Actions deshabilitado (problemas con Oryx build)

---

## 8. Testing y Demostración

### Escenarios de Prueba

1. **Registro y Login:**
   - Crear usuario → Verificar email → Login
   - Usuario de prueba: `claudia_test` / `ClaudiaSecure2024!`

2. **Crear Partida:**
   - Login → Lobby → Elegir color → Crear partida
   - Esperar en lobby (estado: 'waiting')

3. **Unirse y Jugar:**
   - Segundo usuario → Lobby → Ver partida disponible
   - Unirse → Ambos redirigidos al juego
   - Usar controles (WASD o flechas) → Comer frutas → Ganar/perder

4. **Latencia y Sincronización:**
   - Durante el juego, observar indicadores de latencia
   - Probar movimientos rápidos → Verificar sincronización
   - Probar colisión con otra serpiente

### Logs para Debugging

```bash
# En Kudu SSH
tail -f /home/site/wwwroot/private/php_errors.log

# Ver partidas activas
sqlite3 /home/site/wwwroot/private/games.db "SELECT game_id, game_status, player1_name, player2_name FROM game_state;"
```

---

## 9. Limitaciones Conocidas

- **Polling vs WebSockets:** Se usa polling HTTP cada 100ms en lugar de WebSockets (limitaciones de Azure Free tier y simplicidad)
- **Escalabilidad:** SQLite no es óptimo para muchos jugadores simultáneos (suficiente para demo)
- **Sin persistencia de historial:** Las partidas terminadas no se almacenan a largo plazo
- **2 jugadores máximo:** No soporta más de 2 jugadores por partida

---

## 10. Referencias y Recursos

**Documentación del proyecto:**
- `DEPLOYMENT.md` - Guía completa de deployment en Azure
- `MONITORING.md` - Configuración de Application Insights
- `TODO.md` - Issues pendientes y mejoras futuras

**APIs utilizadas:**
- Brevo (SendInBlue) Email API: https://developers.brevo.com/
- Have I Been Pwned Passwords: https://haveibeenpwned.com/API/v3#PwnedPasswords

**Repositorio:**
- GitHub: https://github.com/u1988492/treballPart1

---

## Contacto

**Autores:**
- Claudia Hodoroga - u1988492@campus.udg.edu
- Federico Diaz

**Curso:** Cuarto GDDV - Multijugador  
**Fecha de entrega:** Noviembre 2025
