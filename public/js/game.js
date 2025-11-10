// game.js - Lógica del juego Snake

// Obtener parámetros de la URL
const urlParams = new URLSearchParams(window.location.search);
const gameId = urlParams.get('game_id');
let playerId = getCookie('snake_player_id');

if (!gameId) {
  alert('No game ID provided');
  window.location.href = 'lobby.html';
}

// Estado del juego
let gameState = null;
let playerNumber = null;
let myPlayerId = null; // ID real del jugador desde el servidor
let preferredControls = localStorage.getItem('preferred_controls') || 'wasd'; // Controles preferidos del usuario
let lastDirectionChange = 0;
const inputCooldown = 100; // ms entre cambios de dirección

// Colores ajustados para visibilidad
let myColor = null; // Color del jugador actual (nunca cambia)
let opponentAdjustedColor = null; // Color ajustado del oponente si es similar
let colorAdjustmentMade = false; // Si se realizó ajuste de color

// Estadísticas del juego
let gameStartTime = null;
let maxSnakeLength = 0;
let fruitsEaten = 0;

// Estadísticas de conexión
let lastSuccessfulPoll = Date.now();
let failedPolls = 0;
let totalPolls = 0;
let successfulPolls = 0;

// Canvas
const canvas = document.getElementById('canvas');
const ctx = canvas.getContext('2d');
const cellSize = 20;
const gridSize = 40;

// Polling
let pollInterval = setInterval(pollGameState, 200);
let pingInterval = setInterval(sendPing, 2000);
let connectionCheckInterval = setInterval(checkConnection, 1000);

// Controles
document.addEventListener('keydown', handleKeyPress);

// Prevenir salida accidental del juego
let gameFinished = false;

window.addEventListener('beforeunload', (e) => {
  // Solo mostrar aviso si el juego está activo
  if (!gameFinished && gameState && gameState.game_status === 'playing') {
    e.preventDefault();
    e.returnValue = ''; // Chrome requiere esto
    return '¿Estás seguro de que quieres salir? La partida terminará.';
  }
});

// Detectar cuando el usuario realmente abandona la página
// Usar visibilitychange como alternativa más confiable
document.addEventListener('visibilitychange', async () => {
  if (document.visibilityState === 'hidden') {
    // Si el juego está activo, notificar al servidor que abandonó
    if (!gameFinished && gameState && gameState.game_status === 'playing') {
      await leaveGame();
    }
  }
});

// También usar pagehide como respaldo
window.addEventListener('pagehide', async () => {
  // Si el juego está activo, notificar al servidor que abandonó
  if (!gameFinished && gameState && gameState.game_status === 'playing') {
    await leaveGame();
  }
});

// Función para abandonar el juego
async function leaveGame() {
  try {
    // Solo enviar game_id, el backend usa la sesión para identificar al jugador
    const formData = new FormData();
    formData.append('game_id', gameId);
    
    const data = new URLSearchParams(formData);
    const sent = navigator.sendBeacon('../api/snake_game.php?action=leave_game', data);
    
    console.log('Leave game beacon sent:', sent, 'game_id:', gameId);
  } catch (error) {
    console.error('Error leaving game:', error);
  }
}

// Actualizar texto de controles según preferencias del usuario
function updateControlsText() {
  const controlsTextEl = document.getElementById('controlsText');
  if (!controlsTextEl) return;
  
  if (preferredControls === 'wasd') {
    controlsTextEl.textContent = 'W ⬆️ | S ⬇️ | A ⬅️ | D ➡️';
  } else {
    controlsTextEl.textContent = 'Flechas ⬆️⬇️⬅️➡️';
  }
}

// Verificar estado de conexión
function checkConnection() {
  const timeSinceLastPoll = Date.now() - lastSuccessfulPoll;
  
  if (timeSinceLastPoll > 2000) {
    updateConnectionStatus('reconnecting');
  }
  
  if (timeSinceLastPoll > 5000) {
    updateConnectionStatus('disconnected');
  }
}

// ==================== FUNCIONES ====================

// Obtener cookie
function getCookie(name) {
  const value = `; ${document.cookie}`;
  const parts = value.split(`; ${name}=`);
  if (parts.length === 2) return parts.pop().split(';').shift();
  return null;
}

/**
 * Consulta el estado del juego desde el servidor
 * Realiza polling cada 200ms para mantener sincronizado el estado del juego
 */
async function pollGameState() {
  totalPolls++;
  
  try {
    const response = await fetch(`../api/snake_game.php?action=get_state&game_id=${gameId}&player_id=${playerId}`);
    
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    const data = await response.json();
    
    if (data.error) {
      failedPolls++;
      updateConnectionStatus('error');
      return;
    }
    
    // Actualizar estadísticas si hay cambio en el estado
    if (gameState && data.game_status === 'playing') {
      const myPlayer = playerNumber === 1 ? data.players.player1 : data.players.player2;
      const previousLength = gameState.players[playerNumber === 1 ? 'player1' : 'player2']?.snake?.length || 0;
      const currentLength = myPlayer?.snake?.length || 0;
      
      // Detectar si comió una fruta (longitud aumentó)
      if (currentLength > previousLength) {
        fruitsEaten++;
      }
      
      // Actualizar longitud máxima
      if (currentLength > maxSnakeLength) {
        maxSnakeLength = currentLength;
      }
    }
    
    gameState = data;
    playerNumber = data.player_number;
    successfulPolls++;
    lastSuccessfulPoll = Date.now();
    
    // Actualizar UI
    updateUI();
    updateConnectionStatus('connected');
    
    // Renderizar juego según estado
    if (data.game_status === 'waiting') {
      document.getElementById('waitingScreen').style.display = 'block';
      document.getElementById('gameScreen').style.display = 'none';
    } else if (data.game_status === 'playing') {
      // Marcar inicio del juego para calcular duración
      if (!gameStartTime) {
        gameStartTime = Date.now();
        // Actualizar texto de controles solo una vez al iniciar
        updateControlsText();
      }
      document.getElementById('waitingScreen').style.display = 'none';
      document.getElementById('gameScreen').style.display = 'block';
      renderGame();
    } else if (data.game_status === 'finished') {
      gameFinished = true; // Marcar juego como terminado
      document.getElementById('gameScreen').style.display = 'block';
      renderGame();
      showGameOver();
      clearInterval(pollInterval);
      clearInterval(pingInterval);
      clearInterval(connectionCheckInterval);
    }
  } catch (error) {
    failedPolls++;
    updateConnectionStatus('error');
    
    // En modo debug, mostrar error detallado
    if (window.location.search.includes('debug=true')) {
      console.error('Poll error:', error);
    }
  }
}

// Actualizar información de jugadores
function updateUI() {
  if (!gameState) return;
  
  const p1 = gameState.players.player1;
  const p2 = gameState.players.player2;
  
  document.getElementById('p1Name').textContent = p1.name;
  document.getElementById('p1Score').textContent = p1.score;
  document.getElementById('p1Color').style.background = p1.color;
  
  if (p2) {
    document.getElementById('p2Name').textContent = p2.name;
    document.getElementById('p2Score').textContent = p2.score;
    document.getElementById('p2Color').style.background = p2.color;
  }
  
  // Latencia - ambos jugadores
  updateLatencyDisplay();
  
  // Última actualización
  const now = new Date();
  document.getElementById('lastUpdate').textContent = 
    `Última actualización: ${now.toLocaleTimeString()}`;
}

// Actualizar display de latencia
function updateLatencyDisplay() {
  if (!gameState || !gameState.latency) return;
  
  const yourLatencyValue = gameState.your_latency;
  const opponentLatencyValue = playerNumber === 1 ? 
    gameState.latency.player2 : gameState.latency.player1;
  
  // Tu latencia
  const yourLatencyEl = document.getElementById('yourLatency');
  if (yourLatencyValue !== null) {
    yourLatencyEl.textContent = `${yourLatencyValue}ms`;
    yourLatencyEl.className = 'latency-value ' + getLatencyClass(yourLatencyValue);
  } else {
    yourLatencyEl.textContent = '--ms';
    yourLatencyEl.className = 'latency-value';
  }
  
  // Latencia del oponente
  const opponentLatencyEl = document.getElementById('opponentLatency');
  if (opponentLatencyValue !== null) {
    opponentLatencyEl.textContent = `${opponentLatencyValue}ms`;
    opponentLatencyEl.className = 'latency-value ' + getLatencyClass(opponentLatencyValue);
  } else {
    opponentLatencyEl.textContent = '--ms';
    opponentLatencyEl.className = 'latency-value';
  }
  
  // Ventaja/desventaja de latencia
  const advantageEl = document.getElementById('latencyAdvantage');
  if (yourLatencyValue !== null && opponentLatencyValue !== null) {
    const diff = yourLatencyValue - opponentLatencyValue;
    if (Math.abs(diff) > 30) {
      if (diff < 0) {
        advantageEl.textContent = `✓ Tienes ${Math.abs(diff)}ms de ventaja`;
        advantageEl.className = 'latency-advantage';
      } else {
        advantageEl.textContent = `⚠ Desventaja de ${diff}ms`;
        advantageEl.className = 'latency-advantage warning';
      }
    } else {
      advantageEl.textContent = 'Latencia equilibrada';
      advantageEl.className = 'latency-advantage';
    }
  } else {
    advantageEl.textContent = '';
  }
}

// Clasificar latencia
function getLatencyClass(latency) {
  if (latency < 50) return 'good';
  if (latency < 150) return 'fair';
  return 'poor';
}

// Actualizar estado de conexión
function updateConnectionStatus(status) {
  const indicator = document.getElementById('connectionIndicator');
  const text = document.getElementById('connectionText');
  
  const timeSinceLastPoll = Date.now() - lastSuccessfulPoll;
  
  if (status === 'error' || timeSinceLastPoll > 2000) {
    indicator.className = 'connection-indicator reconnecting';
    text.textContent = 'Reconectando...';
  } else if (timeSinceLastPoll > 5000) {
    indicator.className = 'connection-indicator disconnected';
    text.textContent = 'Desconectado';
  } else {
    indicator.className = 'connection-indicator';
    
    // Calcular packet loss
    const packetLoss = totalPolls > 0 ? 
      Math.round((failedPolls / totalPolls) * 100) : 0;
    
    if (packetLoss > 10) {
      text.textContent = `Conectado (${packetLoss}% pérdida)`;
    } else {
      text.textContent = 'Conectado';
    }
  }
}

/**
 * Convierte un color hexadecimal a HSL
 */
function hexToHSL(hex) {
  // Remover # si existe
  hex = hex.replace('#', '');
  
  // Convertir a RGB
  const r = parseInt(hex.substr(0, 2), 16) / 255;
  const g = parseInt(hex.substr(2, 2), 16) / 255;
  const b = parseInt(hex.substr(4, 2), 16) / 255;
  
  const max = Math.max(r, g, b);
  const min = Math.min(r, g, b);
  let h, s, l = (max + min) / 2;
  
  if (max === min) {
    h = s = 0; // gris
  } else {
    const d = max - min;
    s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
    
    switch (max) {
      case r: h = ((g - b) / d + (g < b ? 6 : 0)) / 6; break;
      case g: h = ((b - r) / d + 2) / 6; break;
      case b: h = ((r - g) / d + 4) / 6; break;
    }
  }
  
  return {
    h: h * 360,
    s: s * 100,
    l: l * 100
  };
}

/**
 * Convierte HSL a hexadecimal
 */
function hslToHex(h, s, l) {
  s /= 100;
  l /= 100;
  
  const c = (1 - Math.abs(2 * l - 1)) * s;
  const x = c * (1 - Math.abs((h / 60) % 2 - 1));
  const m = l - c / 2;
  
  let r = 0, g = 0, b = 0;
  
  if (h < 60) { r = c; g = x; b = 0; }
  else if (h < 120) { r = x; g = c; b = 0; }
  else if (h < 180) { r = 0; g = c; b = x; }
  else if (h < 240) { r = 0; g = x; b = c; }
  else if (h < 300) { r = x; g = 0; b = c; }
  else { r = c; g = 0; b = x; }
  
  const toHex = (n) => {
    const hex = Math.round((n + m) * 255).toString(16);
    return hex.length === 1 ? '0' + hex : hex;
  };
  
  return '#' + toHex(r) + toHex(g) + toHex(b);
}

/**
 * Determina si dos colores son demasiado similares
 */
function areColorsSimilar(color1, color2) {
  const hsl1 = hexToHSL(color1);
  const hsl2 = hexToHSL(color2);
  
  const hueDiff = Math.abs(hsl1.h - hsl2.h);
  const satDiff = Math.abs(hsl1.s - hsl2.s);
  const lightDiff = Math.abs(hsl1.l - hsl2.l);
  
  // Colores similares si:
  // - Diferencia de hue < 30° OR
  // - (Diferencia de hue < 60° AND saturación/luminosidad similares)
  if (hueDiff < 30) return true;
  if (hueDiff < 60 && satDiff < 20 && lightDiff < 20) return true;
  
  return false;
}

/**
 * Genera un color aleatorio brillante que difiere del color base
 */
function generateDistinctColor(baseColor) {
  const baseHSL = hexToHSL(baseColor);
  let attempts = 0;
  let newColor;
  
  do {
    // Generar hue aleatorio con al menos 60° de diferencia
    let newHue = (baseHSL.h + 60 + Math.random() * 240) % 360;
    
    // Asegurar color brillante
    const newSat = 70 + Math.random() * 30; // 70-100%
    const newLight = 40 + Math.random() * 30; // 40-70%
    
    newColor = hslToHex(newHue, newSat, newLight);
    attempts++;
    
  } while (areColorsSimilar(baseColor, newColor) && attempts < 10);
  
  return newColor;
}

/**
 * Ajusta los colores de los jugadores si son demasiado similares
 */
function adjustColorsIfNeeded() {
  if (!gameState || !gameState.players.player1 || !gameState.players.player2) {
    return;
  }
  
  // Determinar mi color y el color del oponente
  const isPlayer1 = playerNumber === 1;
  myColor = isPlayer1 ? gameState.players.player1.color : gameState.players.player2.color;
  const opponentColor = isPlayer1 ? gameState.players.player2.color : gameState.players.player1.color;
  
  // Solo ajustar una vez por sesión de juego
  if (opponentAdjustedColor === null) {
    if (areColorsSimilar(myColor, opponentColor)) {
      opponentAdjustedColor = generateDistinctColor(myColor);
      colorAdjustmentMade = true;
      
      // Actualizar el cuadrado de color en la UI
      const opponentColorBox = isPlayer1 ? document.getElementById('p2Color') : document.getElementById('p1Color');
      if (opponentColorBox) {
        opponentColorBox.style.background = opponentAdjustedColor;
      }
      
      // Mostrar indicador sutil
      showColorAdjustmentIndicator();
    } else {
      opponentAdjustedColor = opponentColor;
      colorAdjustmentMade = false;
    }
  }
}

/**
 * Muestra un indicador temporal de que se ajustó el color
 */
function showColorAdjustmentIndicator() {
  const indicator = document.createElement('div');
  indicator.style.cssText = `
    position: fixed;
    top: 80px;
    right: 20px;
    background: rgba(52, 152, 219, 0.9);
    color: white;
    padding: 10px 15px;
    border-radius: 8px;
    font-size: 14px;
    z-index: 1000;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
  `;
  indicator.textContent = '🎨 Color ajustado para mejor visibilidad';
  indicator.title = 'El color del oponente se ha modificado para evitar confusión';
  
  document.body.appendChild(indicator);
  
  // Remover después de 3 segundos
  setTimeout(() => {
    indicator.style.transition = 'opacity 0.5s';
    indicator.style.opacity = '0';
    setTimeout(() => indicator.remove(), 500);
  }, 3000);
}

/**
 * Renderiza el estado completo del juego en el canvas
 * Se ejecuta cada vez que se recibe una actualización del servidor (cada 200ms)
 * Dibuja en orden: fondo -> grid -> frutas -> serpientes
 */
function renderGame() {
  if (!gameState) return;
  
  // Ajustar colores si es necesario (solo se hace una vez)
  adjustColorsIfNeeded();
  
  // Limpiar canvas con color de fondo
  ctx.fillStyle = '#ecf0f1';
  ctx.fillRect(0, 0, canvas.width, canvas.height);
  
  // Dibujar grid (40x40 celdas, 20px cada una)
  ctx.strokeStyle = '#bdc3c7';
  ctx.lineWidth = 1;
  for (let i = 0; i <= gridSize; i++) {
    // Líneas verticales
    ctx.beginPath();
    ctx.moveTo(i * cellSize, 0);
    ctx.lineTo(i * cellSize, canvas.height);
    ctx.stroke();
    
    // Líneas horizontales
    ctx.beginPath();
    ctx.moveTo(0, i * cellSize);
    ctx.lineTo(canvas.width, i * cellSize);
    ctx.stroke();
  }
  
  // Dibujar frutas como círculos rojos
  gameState.fruits.forEach(fruit => {
    ctx.fillStyle = '#e74c3c';
    ctx.beginPath();
    ctx.arc(
      fruit.x * cellSize + cellSize / 2,
      fruit.y * cellSize + cellSize / 2,
      cellSize / 2 - 2,
      0,
      Math.PI * 2
    );
    ctx.fill();
  });
  
  // Dibujar serpiente del jugador 1
  if (gameState.players.player1.snake) {
    const color1 = playerNumber === 1 ? myColor : opponentAdjustedColor || gameState.players.player1.color;
    drawSnake(gameState.players.player1.snake, color1);
  }
  
  // Dibujar serpiente del jugador 2 (si existe)
  if (gameState.players.player2 && gameState.players.player2.snake) {
    const color2 = playerNumber === 2 ? myColor : opponentAdjustedColor || gameState.players.player2.color;
    drawSnake(gameState.players.player2.snake, color2);
  }
}

/**
 * Dibuja una serpiente en el canvas
 * La cabeza (primer elemento) se dibuja con borde oscuro para distinguirla
 * El cuerpo se dibuja más transparente
 * 
 * @param {Array} snake - Array de celdas [{x, y}, {x, y}, ...]
 * @param {string} color - Color en formato hexadecimal (#RRGGBB)
 */
function drawSnake(snake, color) {
  snake.forEach((cell, index) => {
    ctx.fillStyle = color;
    if (index === 0) {
      // Cabeza: más brillante y con borde oscuro para mejor visibilidad
      ctx.globalAlpha = 1;
      ctx.fillRect(cell.x * cellSize + 1, cell.y * cellSize + 1, cellSize - 2, cellSize - 2);
      ctx.strokeStyle = '#2c3e50';
      ctx.lineWidth = 3;
      ctx.strokeRect(cell.x * cellSize + 1, cell.y * cellSize + 1, cellSize - 2, cellSize - 2);
    } else {
      // Cuerpo: más transparente y ligeramente más pequeño
      ctx.globalAlpha = 0.7;
      ctx.fillRect(cell.x * cellSize + 2, cell.y * cellSize + 2, cellSize - 4, cellSize - 4);
    }
  });
  // Restaurar opacidad completa
  ctx.globalAlpha = 1;
}

/**
 * Maneja las pulsaciones de teclas para controlar la serpiente
 * Jugador 1: teclas WASD (case-insensitive)
 * Jugador 2: teclas de flecha
 * 
 * Implementa un cooldown de 100ms entre cambios de dirección para:
 * - Evitar spam de inputs
 * - Prevenir múltiples cambios en un solo tick del servidor
 * - Mejorar la jugabilidad
 * 
 * @param {KeyboardEvent} e - Evento de teclado
 */
async function handleKeyPress(e) {
  // Solo procesar teclas durante el juego
  if (!gameState || gameState.game_status !== 'playing') return;
  if (!playerNumber) return;
  
  let direction = null;
  let isGameKey = false;
  
  // Usar controles preferidos del usuario (independiente del número de jugador)
  if (preferredControls === 'wasd') {
    // WASD controls
    const key = e.key.toLowerCase();
    if (key === 'w') { direction = 'up'; isGameKey = true; }
    else if (key === 's') { direction = 'down'; isGameKey = true; }
    else if (key === 'a') { direction = 'left'; isGameKey = true; }
    else if (key === 'd') { direction = 'right'; isGameKey = true; }
  } else {
    // Arrow controls
    if (e.key === 'ArrowUp') { direction = 'up'; isGameKey = true; }
    else if (e.key === 'ArrowDown') { direction = 'down'; isGameKey = true; }
    else if (e.key === 'ArrowLeft') { direction = 'left'; isGameKey = true; }
    else if (e.key === 'ArrowRight') { direction = 'right'; isGameKey = true; }
  }
  
  // Si es una tecla del juego
  if (isGameKey) {
    // Prevenir comportamiento por defecto (ej: scroll con flechas)
    e.preventDefault();
    
    // Cooldown: limitar frecuencia de cambios de dirección
    const now = Date.now();
    if (now - lastDirectionChange < inputCooldown) return;
    
    lastDirectionChange = now;
    await setDirection(direction);
  }
}

/**
 * Envía el cambio de dirección al servidor
 * El servidor valida que no sea una reversión (dirección opuesta)
 * 
 * @param {string} direction - Dirección: 'up', 'down', 'left', 'right'
 */
async function setDirection(direction) {
  try {
    const formData = new FormData();
    formData.append('game_id', gameId);
    formData.append('player_id', playerId);
    formData.append('direction', direction);
    
    const response = await fetch('../api/snake_game.php?action=set_direction', {
      method: 'POST',
      body: formData
    });
    
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    const data = await response.json();
    
    // Feedback visual si el servidor ignora la dirección (intento de reversión)
    if (data.ignored) {
      flashFeedback('¡No puedes revertir!');
    }
  } catch (error) {
    // Degradación elegante: el juego continúa aunque falle el envío
    // El próximo poll sincronizará el estado
    if (window.location.search.includes('debug=true')) {
      console.error('Error setting direction:', error);
    }
  }
}

// Mostrar feedback temporal
function flashFeedback(message) {
  const existing = document.getElementById('flashFeedback');
  if (existing) existing.remove();
  
  const feedback = document.createElement('div');
  feedback.id = 'flashFeedback';
  feedback.textContent = message;
  feedback.style.cssText = `
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(231, 76, 60, 0.9);
    color: white;
    padding: 12px 24px;
    border-radius: 8px;
    font-weight: bold;
    z-index: 999;
    animation: fadeInOut 0.8s ease-in-out;
  `;
  document.body.appendChild(feedback);
  setTimeout(() => feedback.remove(), 800);
}

/**
 * Envía un ping al servidor para medir latencia
 * Se ejecuta cada 2 segundos para mantener métricas actualizadas
 * La latencia se calcula como el tiempo de ida (cliente -> servidor)
 */
async function sendPing() {
  try {
    const clientTime = Date.now() / 1000;
    const response = await fetch(`../api/snake_game.php?action=ping&game_id=${gameId}&player_id=${playerId}&client_timestamp=${clientTime}`);
    
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    await response.json();
  } catch (error) {
    // Ping fallido no es crítico - el juego continúa
    if (window.location.search.includes('debug=true')) {
      console.error('Ping error:', error);
    }
  }
}

/**
 * Muestra la pantalla de game over con estadísticas detalladas
 * Incluye: duración de la partida, frutas comidas, longitud máxima
 */
function showGameOver() {
  if (!gameState) return;
  
  const gameOver = document.getElementById('gameOver');
  const message = document.getElementById('gameOverMessage');
  const scores = document.getElementById('finalScores');
  
  const p1 = gameState.players.player1;
  const p2 = gameState.players.player2;
  
  // Determinar ganador para el ranking
  const winner = p1.score > p2.score ? p1 : (p2.score > p1.score ? p2 : null);
  const iWon = gameState.winner === playerId;
  
  // Calcular duración del juego
  const gameDuration = gameStartTime ? Math.floor((Date.now() - gameStartTime) / 1000) : 0;
  const minutes = Math.floor(gameDuration / 60);
  const seconds = gameDuration % 60;
  const durationText = minutes > 0 
    ? `${minutes}m ${seconds}s` 
    : `${seconds}s`;
  
  
  // Ranking de jugadores (ordenado por puntuación)
  const ranking = [p1, p2].sort((a, b) => b.score - a.score);
  
  // Estadísticas detalladas con ranking
  scores.innerHTML = `
    <div class="game-stats-header">🏆 Clasificación Final</div>
    <div class="player-stats ranking">
      <div class="stat-row ${ranking[0].name === (playerNumber === 1 ? p1.name : p2.name) ? 'highlight' : ''}">
        <span class="rank-badge gold">1º</span>
        <span class="player-name">${ranking[0].name}</span>
        <span class="player-score">${ranking[0].score} pts</span>
      </div>
      <div class="stat-row ${ranking[1].name === (playerNumber === 1 ? p1.name : p2.name) ? 'highlight' : ''}">
        <span class="rank-badge silver">2º</span>
        <span class="player-name">${ranking[1].name}</span>
        <span class="player-score">${ranking[1].score} pts</span>
      </div>
    </div>
    
    <div class="game-stats-header">🍎 Tus Estadísticas</div>
    <div class="player-stats">
      <div class="stat-row">
        <span>Frutas comidas:</span>
        <span class="stat-highlight">${fruitsEaten}</span>
      </div>
      <div class="stat-row">
        <span>Longitud máxima:</span>
        <span class="stat-highlight">${maxSnakeLength}</span>
      </div>
    </div>
  `;
  
  gameOver.classList.add('show');
}

// Iniciar
pollGameState();
sendPing();

// Estadísticas de rendimiento (opcional - para debugging)
if (window.location.search.includes('debug=true')) {
  setInterval(() => {
    const packetLoss = totalPolls > 0 ? 
      ((failedPolls / totalPolls) * 100).toFixed(1) : 0;
    const successRate = totalPolls > 0 ? 
      ((successfulPolls / totalPolls) * 100).toFixed(1) : 0;
    
    console.log('📊 Network Stats:', {
      totalPolls,
      successfulPolls,
      failedPolls,
      packetLoss: `${packetLoss}%`,
      successRate: `${successRate}%`,
      latency: gameState?.your_latency ? `${gameState.your_latency}ms` : 'N/A'
    });
  }, 10000); // Cada 10 segundos
}