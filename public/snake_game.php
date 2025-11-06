<?php
// IMPORTANTE: Requiere autenticación de usuario
require_once 'config.php';

// Iniciar sesión para verificar autenticación
session_start();

header('Content-Type: application/json');

// ==================== VERIFICACIÓN DE AUTENTICACIÓN ====================
// Verificar que el usuario está autenticado
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_name'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado. Por favor inicia sesión.']);
    exit;
}

// Obtener información del usuario autenticado
$authenticated_user_id = $_SESSION['user_id'];
$authenticated_user_name = $_SESSION['user_name'];

// Configuración
$db_path = __DIR__ . '/../private/games.db';
$db = new PDO('sqlite:' . $db_path);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ==================== FUNCIONES AUXILIARES ====================

// Devuelve el ID del jugador basado en la sesión autenticada
function get_player_id() {
    global $authenticated_user_id;
    // Usar el user_id de la sesión como identificador del jugador
    return 'user_' . $authenticated_user_id;
}

// Verifica si una celda está dentro del grid permitido
function is_valid_cell($x, $y) {
    return $x >= 0 && $x <= 39 && $y >= 0 && $y <= 39;
}

// Genera una serpiente inicial (2 celdas) que no se solape con las posiciones excluidas
function random_snake($exclude = []) {
    do {
        $x = rand(5, 34);
        $y = rand(5, 34);
        
        $directions = [
            ['dx' => -1, 'dy' => 0],
            ['dx' => 1, 'dy' => 0],
            ['dx' => 0, 'dy' => -1],
            ['dx' => 0, 'dy' => 1]
        ];
        $dir = $directions[array_rand($directions)];
        
        $head = ["x" => $x, "y" => $y];
        $body = ["x" => $x + $dir['dx'], "y" => $y + $dir['dy']];
        $snake = [$head, $body];
        
        $invalid = false;
        foreach ($snake as $cell) {
            if (!is_valid_cell($cell['x'], $cell['y'])) $invalid = true;
            foreach ($exclude as $e) {
                if ($e['x'] == $cell['x'] && $e['y'] == $cell['y']) $invalid = true;
            }
        }
    } while ($invalid);
    return $snake;
}

// Devuelve una dirección aleatoria
function random_direction() {
    $dirs = ['up','down','left','right'];
    return $dirs[array_rand($dirs)];
}

// Genera frutas en posiciones válidas
function random_fruits($exclude = [], $n = 3) {
    $types = ['apple','banana','cherry'];
    $fruits = [];
    $attempts = 0;
    while (count($fruits) < $n && $attempts < 100) {
        $x = rand(0, 39);
        $y = rand(0, 39);
        $invalid = false;
        foreach ($exclude as $e) {
            if ($e['x'] == $x && $e['y'] == $y) $invalid = true;
        }
        foreach ($fruits as $f) {
            if ($f['x'] == $x && $f['y'] == $y) $invalid = true;
        }
        if (!is_valid_cell($x, $y)) $invalid = true;
        if (!$invalid) {
            $fruits[] = ["x"=>$x, "y"=>$y, "type"=>$types[array_rand($types)]];
        }
        $attempts++;
    }
    return $fruits;
}

// Aplica la dirección encolada si es válida (no reversa)
function apply_next_direction($current, $next) {
    if ($current === null) return $next;
    if ($next === null) return $current;
    
    $opposite = ['up'=>'down','down'=>'up','left'=>'right','right'=>'left'];
    
    if (isset($opposite[$current]) && $next === $opposite[$current]) {
        return $current;
    }
    
    return $next;
}

// Mueve una serpiente según las reglas
function move_snake($snake, $dir, &$fruits, $other_snake = null) {
    if (!$snake || !count($snake)) return ['snake'=>$snake,'collision'=>false];
    
    $head = $snake[0];
    $dx = 0; $dy = 0;
    if ($dir === 'up') $dy = -1;
    if ($dir === 'down') $dy = 1;
    if ($dir === 'left') $dx = -1;
    if ($dir === 'right') $dx = 1;
    
    $new_head = ['x'=>$head['x']+$dx, 'y'=>$head['y']+$dy];
    
    $ate_fruit = false;
    $new_fruits = [];
    foreach ($fruits as $fruit) {
        if ($fruit['x'] == $new_head['x'] && $fruit['y'] == $new_head['y']) {
            $ate_fruit = true;
        } else {
            $new_fruits[] = $fruit;
        }
    }
    $fruits = $new_fruits;
    
    if (!is_valid_cell($new_head['x'], $new_head['y'])) {
        return ['snake'=>$snake,'collision'=>true];
    }
    
    if ($other_snake) {
        foreach ($other_snake as $cell) {
            if ($cell['x'] == $new_head['x'] && $cell['y'] == $new_head['y']) {
                return ['snake'=>$snake,'collision'=>true];
            }
        }
    }
    
    $new_snake = [$new_head];
    foreach ($snake as $cell) $new_snake[] = $cell;
    if (!$ate_fruit) array_pop($new_snake);
    
    return ['snake'=>$new_snake,'collision'=>false];
}

// Mueve las serpientes y aplica reglas de colisión, frutas y dirección
function move_snakes($game, $p1_snake, $p2_snake, &$fruits) {
    $p1_dir = apply_next_direction($game['player1_direction'], $game['player1_next_direction']);
    $p2_dir = apply_next_direction($game['player2_direction'], $game['player2_next_direction']);
    
    $status = 'playing';
    $winner = null;
    
    if ($p1_dir !== null) {
        $move1 = move_snake($p1_snake, $p1_dir, $fruits, $p2_snake);
        $p1_snake = $move1['snake'];
        
        if ($move1['collision']) {
            $status = 'finished';
            $winner = $game['player2_id'];
        }
    }
    
    if ($p2_snake && $p2_dir !== null && $status === 'playing') {
        $move2 = move_snake($p2_snake, $p2_dir, $fruits, $p1_snake);
        $p2_snake = $move2['snake'];
        
        if ($move2['collision']) {
            $status = 'finished';
            $winner = $game['player1_id'];
        }
    }
    
    while (count($fruits) < 3) {
        $exclude = array_merge($p1_snake ?? [], $p2_snake ?? []);
        $new_fruits = random_fruits($exclude, 1);
        if (count($new_fruits) > 0) {
            $fruits[] = $new_fruits[0];
        } else {
            break;
        }
    }
    
    return [
        'p1_snake' => $p1_snake,
        'p2_snake' => $p2_snake,
        'fruits' => $fruits,
        'game_status' => $status,
        'winner' => $winner,
        'p1_direction' => $p1_dir,
        'p2_direction' => $p2_dir
    ];
}

// ==================== ENDPOINTS ====================

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    if ($action === 'create_lobby') {
        $player_name = $authenticated_user_name; // Usar nombre del usuario autenticado
        $player_color = $_POST['player_color'] ?? '#000000';
        $player_id = get_player_id();
        $game_id = bin2hex(random_bytes(8));
        
        $player1_snake_arr = random_snake();
        $player1_snake = json_encode($player1_snake_arr);
        $fruits = json_encode(random_fruits($player1_snake_arr));
        
        $now = microtime(true);
        $stmt = $db->prepare("INSERT INTO game_state (
            game_id, player1_id, player1_name, player1_color, player1_snake,
            player1_direction, player1_next_direction, fruits, game_status, last_update, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'waiting', ?, ?)");
        
        $stmt->execute([
            $game_id, $player_id, $player_name, $player_color, $player1_snake,
            null, null, $fruits, $now, time()
        ]);
        
        echo json_encode([
            'game_id' => $game_id,
            'player_id' => $player_id,
            'player_number' => 1
        ]);
        exit;
    }

    if ($action === 'join_game') {
        $game_id = $_POST['game_id'] ?? '';
        $player_name = $authenticated_user_name; // Usar nombre del usuario autenticado
        $player_color = $_POST['player_color'] ?? '#000000';
        $player_id = get_player_id();
        
        $stmt = $db->prepare("SELECT player1_snake, player2_id FROM game_state WHERE game_id = ?");
        $stmt->execute([$game_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) throw new Exception('Game not found');
        if ($row['player2_id']) throw new Exception('Game full');
        
        $player1_snake = json_decode($row['player1_snake'], true);
        $player2_snake_arr = random_snake($player1_snake);
        $player2_snake = json_encode($player2_snake_arr);
        
        $dir = null;
        $stmt2 = $db->prepare("UPDATE game_state SET
            player2_id=?, player2_name=?, player2_color=?, player2_snake=?,
            player2_direction=?, player2_next_direction=?, game_status='playing'
            WHERE game_id=?");
        $stmt2->execute([
            $player_id, $player_name, $player_color, $player2_snake,
            $dir, $dir, $game_id
        ]);
        
        echo json_encode([
            'success' => true,
            'player_id' => $player_id,
            'player_number' => 2
        ]);
        exit;
    }

    if ($action === 'list_games') {
        $stmt = $db->query("SELECT game_id, player1_name, player1_color, created_at FROM game_state WHERE game_status='waiting'");
        $games = [];
        foreach ($stmt as $row) {
            $games[] = [
                'game_id' => $row['game_id'],
                'player1_name' => $row['player1_name'],
                'player1_color' => $row['player1_color'],
                'created_at' => $row['created_at']
            ];
        }
        echo json_encode($games);
        exit;
    }

    if ($action === 'get_state') {
        $game_id = $_POST['game_id'] ?? $_GET['game_id'] ?? '';
        $player_id = get_player_id();
        $now = microtime(true);
        
        $stmt = $db->prepare("SELECT * FROM game_state WHERE game_id = ?");
        $stmt->execute([$game_id]);
        $game = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$game) throw new Exception('Game not found');

        $p1_snake = $game['player1_snake'] ? json_decode($game['player1_snake'], true) : null;
        $p2_snake = $game['player2_snake'] ? json_decode($game['player2_snake'], true) : null;
        $fruits = $game['fruits'] ? json_decode($game['fruits'], true) : [];

        $player_number = ($player_id === $game['player1_id']) ? 1 : (($player_id === $game['player2_id']) ? 2 : null);

        if ($game['game_status'] === 'playing') {
            $last_update = floatval($game['last_update']);
            $elapsed = $now - $last_update;
            
            if ($elapsed >= 0.5) {
                $move_result = move_snakes($game, $p1_snake, $p2_snake, $fruits);
                $p1_snake = $move_result['p1_snake'];
                $p2_snake = $move_result['p2_snake'];
                $fruits = $move_result['fruits'];
                $game['game_status'] = $move_result['game_status'];
                $game['winner'] = $move_result['winner'];
                
                $game['player1_direction'] = $move_result['p1_direction'];
                $game['player2_direction'] = $move_result['p2_direction'];
                
                $stmt2 = $db->prepare("UPDATE game_state SET 
                    player1_snake=?, player2_snake=?, fruits=?, last_update=?, 
                    game_status=?, winner=?, player1_direction=?, player2_direction=?
                    WHERE game_id=?");
                $stmt2->execute([
                    json_encode($p1_snake),
                    json_encode($p2_snake),
                    json_encode($fruits),
                    $now,
                    $game['game_status'],
                    $game['winner'],
                    $game['player1_direction'],
                    $game['player2_direction'],
                    $game_id
                ]);
            }
        }

        $latency_p1 = null;
        $latency_p2 = null;
        
        if ($game['player1_id']) {
            $stmt3 = $db->prepare("SELECT latency_ms FROM player_latency WHERE player_id=? AND game_id=? ORDER BY ping_sent DESC LIMIT 1");
            $stmt3->execute([$game['player1_id'], $game_id]);
            $row = $stmt3->fetch(PDO::FETCH_ASSOC);
            if ($row) $latency_p1 = intval($row['latency_ms']);
        }
        
        if ($game['player2_id']) {
            $stmt4 = $db->prepare("SELECT latency_ms FROM player_latency WHERE player_id=? AND game_id=? ORDER BY ping_sent DESC LIMIT 1");
            $stmt4->execute([$game['player2_id'], $game_id]);
            $row = $stmt4->fetch(PDO::FETCH_ASSOC);
            if ($row) $latency_p2 = intval($row['latency_ms']);
        }
        
        $your_latency = ($player_id === $game['player1_id']) ? $latency_p1 : $latency_p2;

        $resp = [
            'game_status' => $game['game_status'],
            'player_number' => $player_number,
            'players' => [
                'player1' => [
                    'name' => $game['player1_name'],
                    'color' => $game['player1_color'],
                    'snake' => $p1_snake,
                    'direction' => $game['player1_direction'],
                    'score' => $p1_snake ? count($p1_snake) - 1 : 0
                ],
                'player2' => $game['player2_id'] ? [
                    'name' => $game['player2_name'],
                    'color' => $game['player2_color'],
                    'snake' => $p2_snake,
                    'direction' => $game['player2_direction'],
                    'score' => $p2_snake ? count($p2_snake) - 1 : 0
                ] : null
            ],
            'fruits' => $fruits,
            'winner' => $game['winner'],
            'server_time' => round($now),
            'your_latency' => $your_latency,
            'latency' => [
                'player1' => $latency_p1,
                'player2' => $latency_p2
            ]
        ];
        
        echo json_encode($resp);
        exit;
    }

    if ($action === 'ping') {
        $game_id = $_POST['game_id'] ?? $_GET['game_id'] ?? '';
        $player_id = get_player_id();
        $client_timestamp = floatval($_POST['client_timestamp'] ?? $_GET['client_timestamp'] ?? 0);
        
        $now = microtime(true);
        $estimated_latency = $client_timestamp > 0 ? round(($now - $client_timestamp) * 500) : 0;
        
        $stmt = $db->prepare("INSERT OR REPLACE INTO player_latency (player_id, game_id, ping_sent, ping_received, latency_ms) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $player_id,
            $game_id,
            round($client_timestamp * 1000),
            round($now * 1000),
            $estimated_latency
        ]);
        
        echo json_encode([
            'server_time' => $now,
            'estimated_latency_ms' => $estimated_latency
        ]);
        exit;
    }

    if ($action === 'set_direction') {
        $game_id = $_POST['game_id'] ?? $_GET['game_id'] ?? '';
        $player_id = get_player_id();
        $direction = $_POST['direction'] ?? $_GET['direction'] ?? '';
        
        if (!in_array($direction, ['up', 'down', 'left', 'right'])) {
            throw new Exception('Invalid direction');
        }
        
        $stmt = $db->prepare("SELECT * FROM game_state WHERE game_id = ?");
        $stmt->execute([$game_id]);
        $game = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$game) throw new Exception('Game not found');
        if ($game['game_status'] !== 'playing') throw new Exception('Game not in progress');
        
        $is_player1 = ($player_id === $game['player1_id']);
        $is_player2 = ($player_id === $game['player2_id']);
        
        if (!$is_player1 && !$is_player2) throw new Exception('Not a player in this game');
        
        $current_dir = $is_player1 ? $game['player1_direction'] : $game['player2_direction'];
        
        $opposite = ['up'=>'down','down'=>'up','left'=>'right','right'=>'left'];
        
        if ($current_dir !== null && isset($opposite[$current_dir]) && $direction === $opposite[$current_dir]) {
            echo json_encode(['success' => true, 'ignored' => true, 'reason' => 'Cannot reverse direction']);
            exit;
        }
        
        if ($is_player1) {
            if ($current_dir === null) {
                $stmt2 = $db->prepare("UPDATE game_state SET player1_direction=?, player1_next_direction=? WHERE game_id=?");
                $stmt2->execute([$direction, $direction, $game_id]);
            } else {
                $stmt2 = $db->prepare("UPDATE game_state SET player1_next_direction=? WHERE game_id=?");
                $stmt2->execute([$direction, $game_id]);
            }
        } else {
            if ($current_dir === null) {
                $stmt2 = $db->prepare("UPDATE game_state SET player2_direction=?, player2_next_direction=? WHERE game_id=?");
                $stmt2->execute([$direction, $direction, $game_id]);
            } else {
                $stmt2 = $db->prepare("UPDATE game_state SET player2_next_direction=? WHERE game_id=?");
                $stmt2->execute([$direction, $game_id]);
            }
        }
        
        echo json_encode(['success' => true]);
        exit;
    }

    throw new Exception('Invalid action');

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
?>