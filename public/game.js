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
let lastDirectionChange = 0;
const inputCooldown = 100; // ms entre cambios de dirección

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
    const response = await fetch(`snake_game.php?action=get_state&game_id=${gameId}&player_id=${playerId}`);
    
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
      }
      document.getElementById('waitingScreen').style.display = 'none';
      document.getElementById('gameScreen').style.display = 'block';
      renderGame();
    } else if (data.game_status === 'finished') {
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
 * Renderiza el estado completo del juego en el canvas
 * Se ejecuta cada vez que se recibe una actualización del servidor (cada 200ms)
 * Dibuja en orden: fondo -> grid -> frutas -> serpientes
 */
function renderGame() {
  if (!gameState) return;
  
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
    drawSnake(gameState.players.player1.snake, gameState.players.player1.color);
  }
  
  // Dibujar serpiente del jugador 2 (si existe)
  if (gameState.players.player2 && gameState.players.player2.snake) {
    drawSnake(gameState.players.player2.snake, gameState.players.player2.color);
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
  
  // Jugador 1: WASD (case-insensitive para mayor accesibilidad)
  if (playerNumber === 1) {
    const key = e.key.toLowerCase();
    if (key === 'w') { direction = 'up'; isGameKey = true; }
    else if (key === 's') { direction = 'down'; isGameKey = true; }
    else if (key === 'a') { direction = 'left'; isGameKey = true; }
    else if (key === 'd') { direction = 'right'; isGameKey = true; }
  }
  
  // Jugador 2: Flechas del teclado
  if (playerNumber === 2) {
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
    
    const response = await fetch('snake_game.php?action=set_direction', {
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
    const response = await fetch(`snake_game.php?action=ping&game_id=${gameId}&player_id=${playerId}&client_timestamp=${clientTime}`);
    
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
  
  // Calcular duración del juego
  const gameDuration = gameStartTime ? Math.floor((Date.now() - gameStartTime) / 1000) : 0;
  const minutes = Math.floor(gameDuration / 60);
  const seconds = gameDuration % 60;
  const durationText = minutes > 0 
    ? `${minutes}m ${seconds}s` 
    : `${seconds}s`;
  
  // Mensaje de victoria/derrota
  if (gameState.winner === playerId) {
    message.innerHTML = '<div class="winner">¡HAS GANADO! 🎉</div>';
  } else {
    message.innerHTML = '<div class="loser">Has perdido 😢</div>';
  }
  
  // Estadísticas detalladas
  scores.innerHTML = `
    <div class="game-stats-header">📊 Puntuaciones Finales</div>
    <div class="player-stats">
      <div class="stat-row">
        <span class="player-name">${p1.name}:</span>
        <span class="player-score">${p1.score} puntos</span>
      </div>
      <div class="stat-row">
        <span class="player-name">${p2.name}:</span>
        <span class="player-score">${p2.score} puntos</span>
      </div>
    </div>
    
    <!-- // se tiene que arreglar <div class="game-stats-header">⏱️ Duración de la Partida</div> -->
    <!-- <div class="stat-value">${durationText}</div> -->
    
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