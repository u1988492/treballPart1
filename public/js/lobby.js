// Cargar preferencias del usuario desde el servidor
async function loadUserPreferences() {
  try {
    const response = await fetch('../api/snake_game.php?action=get_user_preferences');
    if (!response.ok) throw new Error('Error al cargar preferencias');
    
    const prefs = await response.json();
    if (prefs.error) throw new Error(prefs.error);
    
    // Aplicar color de preferencias
    if (prefs.preferred_color) {
      const colorInput = document.getElementById('playerColor');
      colorInput.value = prefs.preferred_color;
      colorInput.disabled = true; // Deshabilitar selector
      colorInput.style.cursor = 'not-allowed';
      
      // Agregar mensaje informativo
      const label = colorInput.parentElement;
      let hint = label.querySelector('.prefs-hint');
      if (!hint) {
        hint = document.createElement('small');
        hint.className = 'prefs-hint';
        hint.style.display = 'block';
        hint.style.marginTop = '5px';
        hint.style.color = '#7f8c8d';
        hint.innerHTML = '💡 Cambiar en <a href="../api/preferences.php" style="color: #3498db;">Preferencias</a>';
        label.appendChild(hint);
      }
    }
    
    return prefs;
  } catch (error) {
    console.error('Error loading preferences:', error);
    // Si falla, mantener comportamiento por defecto
    return null;
  }
}

// Inicializa color y preferencias al cargar la página
document.addEventListener('DOMContentLoaded', async () => {
  await loadUserPreferences();
  loadGames(true); // true = primera carga, mostrar mensaje
  setInterval(() => loadGames(false), 2000); // false = actualización silenciosa
});

/**
 * Maneja el envío del formulario para crear una nueva partida
 * Ya NO necesita validar el nombre, se usa el de la sesión
 */
document.getElementById('playerForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  setLoading(true);
  
  const color = document.getElementById('playerColor').value;
  
  const formData = new FormData();
  formData.append('player_color', color);
  
  try {
    const res = await fetch('../api/snake_game.php?action=create_lobby', { 
      method: 'POST', 
      body: formData 
    });
    
    if (!res.ok) {
      throw new Error(`Error HTTP: ${res.status}`);
    }
    
    const data = await res.json();
    
    if (data.error) {
      // Si el error es de autenticación, redirigir al login
      if (res.status === 401 || data.error.includes('autenticado')) {
        alert('Sesión expirada. Por favor inicia sesión de nuevo.');
        window.location.href = '/?page=login';
        return;
      }
      throw new Error(data.error);
    }
    
    // Guardar controles preferidos en localStorage
    if (data.preferred_controls) {
      localStorage.setItem('preferred_controls', data.preferred_controls);
    }
    
    // Redirigir a la pantalla del juego
    window.location.href = `game.html?game_id=${data.game_id}`;
  } catch (err) {
    alert('Error al crear partida: ' + err.message);
    setLoading(false);
  }
});

// Variable para trackear si tenías una partida propia
let hadOwnGame = false;
let lastOwnGameId = null;
let gameCancelledByUser = false; // Nueva variable para evitar redirección después de cancelar

// Botón actualizar lista
document.getElementById('refreshBtn').addEventListener('click', () => loadGames(true));

/**
 * Carga la lista de partidas disponibles desde el servidor
 * Implementa medición de latencia y filtrado por calidad de conexión
 * @param {boolean} showMessage - Si debe mostrar el mensaje de "midiendo calidad"
 */
async function loadGames(showMessage = false) {
  if (showMessage) {
    setGamesLoading(true);
    showLatencyCheckMessage(true);
  }
  
  try {
    const res = await fetch('../api/snake_game.php?action=list_games');
    
    if (!res.ok) {
      throw new Error(`Error HTTP: ${res.status}`);
    }
    
    const games = await res.json();
    
    // Verificar si hay error de autenticación
    if (games.error && res.status === 401) {
      window.location.href = '/?page=login';
      return;
    }
    
    // Verificar si tenías una partida propia que ya no está (alguien se unió)
    const hasOwnGame = games.some(g => g.is_own_game);
    
    if (hadOwnGame && !hasOwnGame && lastOwnGameId && !gameCancelledByUser) {
      // Tu partida desapareció de la lista = alguien se unió
      // Redirigir automáticamente al juego
      console.log('Alguien se unió a tu partida, redirigiendo...');
      window.location.href = `game.html?game_id=${lastOwnGameId}`;
      return;
    }
    
    // Resetear flag de cancelación
    if (gameCancelledByUser && !hasOwnGame) {
      gameCancelledByUser = false;
    }
    
    // Actualizar estado
    hadOwnGame = hasOwnGame;
    if (hasOwnGame) {
      const ownGame = games.find(g => g.is_own_game);
      lastOwnGameId = ownGame.game_id;
    }
    
    if (games.length === 0) {
      updateGamesList([]);
      if (showMessage) {
        showLatencyCheckMessage(false);
        setGamesLoading(false);
      }
      return;
    }
    
    // Medir latencia para cada juego (con timeout de 2 segundos)
    const gamesWithLatency = await measureGamesLatency(games);
    
    // Filtrar juegos por latencia similar (±50ms del median)
    const filteredGames = filterGamesByLatency(gamesWithLatency);
    
    updateGamesList(filteredGames);
    if (showMessage) {
      showLatencyCheckMessage(false);
    }
  } catch (error) {
    updateGamesList([]);
    if (showMessage) {
      showLatencyCheckMessage(false);
    }
    
    if (window.location.search.includes('debug=true')) {
      console.error('Error loading games:', error);
    }
  }
  
  if (showMessage) {
    setGamesLoading(false);
  }
}

/**
 * Mide la latencia para cada juego disponible
 */
async function measureGamesLatency(games) {
  const latencyPromises = games.map(game => measureGameLatency(game));
  const results = await Promise.all(latencyPromises);
  return results;
}

/**
 * Mide la latencia de un juego específico mediante ping
 */
async function measureGameLatency(game) {
  const startTime = performance.now();
  
  try {
    // Timeout de 2 segundos por juego
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 2000);
    
    const response = await fetch(
      `../api/snake_game.php?action=ping&game_id=${game.game_id}&client_timestamp=${Date.now() / 1000}`,
      { signal: controller.signal }
    );
    
    clearTimeout(timeoutId);
    
    if (!response.ok) {
      return { ...game, latency: 9999 }; // Latencia muy alta si falla
    }
    
    const endTime = performance.now();
    const latency = Math.round(endTime - startTime);
    
    return { ...game, latency };
  } catch (error) {
    // Si timeout o error, asignar latencia muy alta
    return { ...game, latency: 9999 };
  }
}

/**
 * Filtra juegos mostrando solo los top 5 con latencia similar (±50ms del median)
 */
function filterGamesByLatency(games) {
  // Filtrar juegos con latencia válida
  const validGames = games.filter(g => g.latency < 9999);
  
  if (validGames.length === 0) {
    // Si ningún juego tiene latencia válida, mostrar todos
    return games.slice(0, 5);
  }
  
  // Calcular mediana de latencia
  const latencies = validGames.map(g => g.latency).sort((a, b) => a - b);
  const median = latencies[Math.floor(latencies.length / 2)];
  
  // Filtrar juegos dentro de ±50ms de la mediana
  const filtered = validGames.filter(g => Math.abs(g.latency - median) <= 50);
  
  // Si hay menos de 5 juegos que coinciden, mostrar todos los que coinciden
  // Si hay más de 5, mostrar solo los top 5 con menor latencia
  const sorted = filtered.sort((a, b) => a.latency - b.latency);
  
  return sorted.slice(0, Math.min(5, sorted.length));
}

// Actualiza la lista de partidas solo si hay cambios estructurales (evita parpadeo)
let lastGamesSnapshot = '';

function updateGamesList(games) {
  const ul = document.getElementById('gamesUl');
  const noGamesMsg = document.getElementById('noGamesMessage');
  
  if (!games.length) {
    if (ul.innerHTML !== '') {
      ul.innerHTML = '';
      lastGamesSnapshot = '';
    }
    if (noGamesMsg) noGamesMsg.style.display = 'block';
    return;
  }
  
  if (noGamesMsg) noGamesMsg.style.display = 'none';
  
  // Crear snapshot de IDs y datos relevantes (sin timestamps)
  const currentSnapshot = games.map(g => 
    `${g.game_id}_${g.player1_name}_${g.player1_color}_${g.is_own_game}_${g.latency}`
  ).join('|');
  
  // Solo actualizar si hay cambios estructurales
  if (currentSnapshot === lastGamesSnapshot) {
    // Solo actualizar timestamps sin re-renderizar todo
    games.forEach((game, index) => {
      const timeEl = ul.children[index]?.querySelector('.waiting-time');
      if (timeEl) {
        timeEl.textContent = `(${timeSince(game.created_at)})`;
      }
    });
    return;
  }
  
  // Hay cambios estructurales, re-renderizar completamente
  lastGamesSnapshot = currentSnapshot;
  let html = '';
  
  games.forEach(game => {
    const isOwnGame = game.is_own_game || false;
    const latencyDisplay = game.latency && game.latency < 9999 ? `${game.latency}ms` : '-';
    const latencyClass = getLatencyClass(game.latency);
    
    // Nombre a mostrar: "Tu partida" si es propia, nombre del jugador si no
    const displayName = isOwnGame ? 'Tu partida' : game.player1_name;
    
    // Estilo diferente para juegos propios
    const gameClass = isOwnGame ? 'game-item own-game' : 'game-item';
    
    html += `<li class="${gameClass}">
      <div style="display: flex; align-items: center; gap: 10px;">
        <span class="color-box" style="background:${game.player1_color || '#888'}"></span>
        <span><strong>${displayName}</strong></span>
        <span class="waiting-time">(${timeSince(game.created_at)})</span>
        ${game.latency ? `<span class="latency-badge ${latencyClass}"><span class="tooltip-hint" title="Tiempo de respuesta del servidor. Menor es mejor.">💡</span> ${latencyDisplay}</span>` : ''}
      </div>
      ${isOwnGame 
        ? `<div style="display: flex; gap: 8px;">
            <button onclick="rejoinGame('${game.game_id}')" class="button-primary">Volver a la partida</button>
            <button onclick="cancelGame('${game.game_id}')" class="button-cancel">Cancelar</button>
          </div>`
        : `<button onclick="joinGame('${game.game_id}')">Unirse</button>`
      }
    </li>`;
  });
  
  ul.innerHTML = html;
}

/**
 * Determina la clase CSS según la latencia
 */
function getLatencyClass(latency) {
  if (!latency || latency >= 9999) return 'latency-unknown';
  if (latency < 50) return 'latency-good';
  if (latency < 150) return 'latency-fair';
  return 'latency-poor';
}

/**
 * Calcula el tiempo transcurrido desde un timestamp y lo formatea
 */
function timeSince(ts) {
  const now = Math.floor(Date.now() / 1000);
  const sec = now - parseInt(ts);
  
  if (sec < 60) return sec + 's';
  if (sec < 3600) return Math.floor(sec / 60) + 'm';
  return Math.floor(sec / 3600) + 'h';
}

/**
 * Vuelve a una partida propia (sin contar como segundo jugador)
 */
function rejoinGame(gameId) {
  // Simplemente redirigir a la pantalla del juego
  window.location.href = `game.html?game_id=${gameId}`;
}

/**
 * Intenta unirse a una partida existente
 * Ya NO necesita validar el nombre, se usa el de la sesión
 */
async function joinGame(gameId) {
  setLoading(true);
  
  const color = document.getElementById('playerColor').value;
  
  const formData = new FormData();
  formData.append('game_id', gameId);
  formData.append('player_color', color);
  
  try {
    const res = await fetch('../api/snake_game.php?action=join_game', { 
      method: 'POST', 
      body: formData 
    });
    
    if (!res.ok) {
      throw new Error(`Error HTTP: ${res.status}`);
    }
    
    const data = await res.json();
    
    if (data.error) {
      // Si el error es de autenticación, redirigir al login
      if (res.status === 401 || data.error.includes('autenticado')) {
        alert('Sesión expirada. Por favor inicia sesión de nuevo.');
        window.location.href = '/?page=login';
        return;
      }
      throw new Error(data.error);
    }
    
    // Guardar controles preferidos en localStorage
    if (data.preferred_controls) {
      localStorage.setItem('preferred_controls', data.preferred_controls);
    }
    
    // Redirigir a la pantalla del juego
    window.location.href = `game.html?game_id=${gameId}`;
  } catch (err) {
    alert('Error al unirse: ' + err.message);
    setLoading(false);
  }
}

// Estados de carga
function setLoading(loading) {
  document.getElementById('createBtn').disabled = loading;
  document.getElementById('refreshBtn').disabled = loading;
  document.getElementById('playerColor').disabled = loading;
}

function setGamesLoading(loading) {
  document.getElementById('loadingGames').style.display = loading ? 'block' : 'none';
  document.getElementById('gamesUl').style.opacity = loading ? '0.5' : '1';
}

/**
 * Muestra u oculta el mensaje de verificación de latencia
 */
function showLatencyCheckMessage(show) {
  let msg = document.getElementById('latencyCheckMessage');
  
  if (show) {
    if (!msg) {
      msg = document.createElement('div');
      msg.id = 'latencyCheckMessage';
      msg.className = 'latency-check-message';
      msg.innerHTML = `
        <div class="spinner"></div>
        <span>Midiendo la calidad de conexión...</span>
        <span class="tooltip-icon" title="Los juegos se filtran según la calidad de conexión para una mejor experiencia">ℹ️</span>
      `;
      document.getElementById('gamesSection').insertBefore(
        msg,
        document.getElementById('gamesUl')
      );
    }
    msg.style.display = 'flex';
  } else {
    if (msg) {
      msg.style.display = 'none';
    }
  }
}

/**
 * Cancela un juego creado por el usuario
 */
async function cancelGame(gameId) {
  if (!confirm('¿Estás seguro de que quieres cancelar esta partida?')) {
    return;
  }
  
  // Marcar que el usuario canceló la partida manualmente
  gameCancelledByUser = true;
  
  setLoading(true);
  
  try {
    const formData = new FormData();
    formData.append('game_id', gameId);
    
    const res = await fetch('../api/snake_game.php?action=cancel_game', {
      method: 'POST',
      body: formData
    });
    
    if (!res.ok) {
      throw new Error(`Error HTTP: ${res.status}`);
    }
    
    const data = await res.json();
    
    if (data.error) {
      throw new Error(data.error);
    }
    
    // Recargar lista de juegos
    await loadGames();
  } catch (err) {
    alert('Error al cancelar la partida: ' + err.message);
  } finally {
    setLoading(false);
  }
}