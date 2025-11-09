<?php
// Funciones auxiliares

// validar longitud contraseña
function validate_password_length($password) {
    return strlen($password) >= PASSWORD_MIN_LENGTH;
}

// hashear contraseña con bcrypt
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// verificar contraseña
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// comprobar contraseña en haveibeenpwned
function check_pwned_password($password) {
    $sha1 = strtoupper(sha1($password));
    $prefix = substr($sha1, 0, 5);
    $suffix = substr($sha1, 5);
    
    $url = "https://api.pwnedpasswords.com/range/" . $prefix;
    
    // Configurar context per file_get_contents con timeout más largo para asegurar verificación
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'PHP-Password-Security-Checker/1.0',
            'method' => 'GET'
        ]
    ]);
    
    // Intentar múltiples veces antes de fallar
    $max_attempts = 3;
    $attempt = 0;
    
    while ($attempt < $max_attempts) {
        $response = @file_get_contents($url, false, $context);
        
        if ($response !== false) {
            // API funcionó correctamente, buscar el hash
            $hashes = explode("\r\n", $response);
            foreach ($hashes as $line) {
                $parts = explode(':', $line);
                if (count($parts) === 2 && $parts[0] === $suffix) {
                    // Contraseña encontrada en breach - BLOQUEAR
                    error_log("[SEGURIDAD] Contraseña comprometida detectada - hash prefix: $prefix, apariciones: " . $parts[1]);
                    return ['pwned' => true, 'count' => (int)$parts[1], 'error' => false];
                }
            }
            // No encontrada en breaches - PERMITIR
            return ['pwned' => false, 'count' => 0, 'error' => false];
        }
        
        $attempt++;
        if ($attempt < $max_attempts) {
            // Esperar antes del siguiente intento
            usleep(500000); // 0.5 segundos
        }
    }
    
    // Si la API falla después de múltiples intentos, registrar el fallo y permitir
    // pero notificar para revisión manual
    error_log("[SEGURIDAD] ALERTA: API HaveIBeenPwned no disponible después de $max_attempts intentos para verificación de contraseña");
    return ['pwned' => false, 'count' => 0, 'error' => true];
}

// generar token seguro
function generate_token($length = 32) {
    return bin2hex(random_bytes($length));
}

// generar token CSRF
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time']) || 
        (time() - $_SESSION['csrf_token_time']) > 3600) { // Regenerar cada hora
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

// validar token CSRF
function validate_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        return false;
    }
    
    // Verificar que el token no haya expirado (1 hora)
    if ((time() - $_SESSION['csrf_token_time']) > 3600) {
        return false;
    }
    
    // Verificar que los tokens coincidan usando hash_equals para prevenir timing attacks
    return hash_equals($_SESSION['csrf_token'], $token);
}

// generar código TOTP, 6 dígitos
function generate_totp_code() {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

// enviar mail (simulado)
function send_email($to, $subject, $body) {
    // Validar email
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    // Headers para email HTML
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
    
    // Para desarrollo local, guardamos el email en un archivo
    $email_content = "To: $to\nSubject: $subject\n\n$body";
    $filename = '../private/emails/' . time() . '_' . md5($to) . '.txt';
    
    // Crear directorio si no existe
    if (!is_dir('../private/emails')) {
        mkdir('../private/emails', 0700, true);
    }
    
    file_put_contents($filename, $email_content);
    
    return true;
}

// verificar límite de intentos de login
function check_rate_limit($db, $user_name) {
    $sql = 'SELECT failed_attempts, last_attempt FROM users WHERE user_name = :user_name';
    $query = $db->prepare($sql);
    $query->bindValue(':user_name', $user_name);
    $query->execute();
    $result = $query->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        return ['locked' => false, 'attempts' => 0];
    }
    
    $failed_attempts = $result['failed_attempts'] ?? 0;
    $last_attempt = $result['last_attempt'] ?? 0;
    
    // si han pasado más de LOCKOUT_TIME segundos, resetear intentos
    if (time() - $last_attempt > LOCKOUT_TIME) {
        $sql = 'UPDATE users SET failed_attempts = 0, last_attempt = 0 WHERE user_name = :user_name';
        $query = $db->prepare($sql);
        $query->bindValue(':user_name', $user_name);
        $query->execute();
        return ['locked' => false, 'attempts' => 0];
    }
    
    // si ha excedido el límite
    if ($failed_attempts >= MAX_LOGIN_ATTEMPTS) {
        $time_left = LOCKOUT_TIME - (time() - $last_attempt);
        return ['locked' => true, 'attempts' => $failed_attempts, 'time_left' => $time_left];
    }
    
    return ['locked' => false, 'attempts' => $failed_attempts];
}

// registrar intento fallido
function record_failed_attempt($db, $user_name) {
    $sql = 'UPDATE users SET failed_attempts = failed_attempts + 1, last_attempt = :time WHERE user_name = :user_name';
    $query = $db->prepare($sql);
    $query->bindValue(':time', time());
    $query->bindValue(':user_name', $user_name);
    $query->execute();
}

// resetear intentos tras login exitoso
function reset_failed_attempts($db, $user_name) {
    $sql = 'UPDATE users SET failed_attempts = 0, last_attempt = 0 WHERE user_name = :user_name';
    $query = $db->prepare($sql);
    $query->bindValue(':user_name', $user_name);
    $query->execute();
}

// limpiar input para evitar XSS
function clean_input($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Añadir delay aleatorio para prevenir timing attacks
function prevent_timing_attack($min_ms = 100, $max_ms = 300) {
    $delay = random_int($min_ms, $max_ms) * 1000; // convertir a microsegundos
    usleep($delay);
}

// Verificar límite de intentos 2FA
function check_2fa_rate_limit($db, $user_id) {
    $sql = 'SELECT failed_2fa_attempts, last_2fa_attempt FROM users WHERE user_id = :user_id';
    $query = $db->prepare($sql);
    $query->bindValue(':user_id', $user_id);
    $query->execute();
    $result = $query->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        return ['locked' => false, 'attempts' => 0];
    }
    
    $failed_attempts = $result['failed_2fa_attempts'] ?? 0;
    $last_attempt = $result['last_2fa_attempt'] ?? 0;
    
    // si han pasado más de 15 minutos, resetear intentos
    if (time() - $last_attempt > 900) {
        $sql = 'UPDATE users SET failed_2fa_attempts = 0, last_2fa_attempt = 0 WHERE user_id = :user_id';
        $query = $db->prepare($sql);
        $query->bindValue(':user_id', $user_id);
        $query->execute();
        return ['locked' => false, 'attempts' => 0];
    }
    
    // máximo 3 intentos para 2FA
    if ($failed_attempts >= 3) {
        $time_left = 900 - (time() - $last_attempt);
        return ['locked' => true, 'attempts' => $failed_attempts, 'time_left' => $time_left];
    }
    
    return ['locked' => false, 'attempts' => $failed_attempts];
}

// registrar intento 2FA fallido
function record_failed_2fa_attempt($db, $user_id) {
    $sql = 'UPDATE users SET failed_2fa_attempts = failed_2fa_attempts + 1, last_2fa_attempt = :time WHERE user_id = :user_id';
    $query = $db->prepare($sql);
    $query->bindValue(':time', time());
    $query->bindValue(':user_id', $user_id);
    $query->execute();
}

// conectar a base de datos usuarios
function get_db_connection() {
    try {
        $db = new PDO(DB_CONNECTION);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $db;
    } catch (PDOException $e) {
        error_log("[DB] Error de conexión usuarios: " . $e->getMessage());
        throw new Exception("Error de base de datos");
    }
}

// conectar a base de datos juegos
function get_game_db_connection() {
    try {
        $db = new PDO(DB_GAMES_CONNECTION);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $db;
    } catch (PDOException $e) {
        error_log("[GAME_DB] Error de conexión juegos: " . $e->getMessage());
        throw new Exception("Error de base de datos de juegos");
    }
}
?>