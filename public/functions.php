<?php
// Funiones auxiliares

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
    
    // Configurar context per file_get_contents
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'user_agent' => 'PHP-Password-Checker'
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        // Si la API falla, permitir la contrasenya
        return ['pwned' => false, 'count' => 0, 'error' => true];
    }
    
    // Buscar el sufix en la resposta
    $hashes = explode("\r\n", $response);
    foreach ($hashes as $line) {
        $parts = explode(':', $line);
        if (count($parts) === 2 && $parts[0] === $suffix) {
            return ['pwned' => true, 'count' => (int)$parts[1], 'error' => false];
        }
    }
    
    return ['pwned' => false, 'count' => 0, 'error' => false];
}

// generar token seguro
function generate_token($length = 32) {
    return bin2hex(random_bytes($length));
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