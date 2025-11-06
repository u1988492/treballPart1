<?php
/*
* GESTIÓN DE USUARIOS CON SESIONES + JUEGO SNAKE MULTIJUGADOR
* ===========================================================
* 
* Fusiona el sistema de gestión de usuarios con el juego Snake multijugador.
* El juego solo es accesible para usuarios autenticados.
*/

require_once 'config.php';
require_once 'functions.php';

// iniciar sesión con configuración segura
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_lifetime', COOKIE_LIFETIME);
session_start();

// regenerar ID de sesión periódicamente para seguridad
if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 300) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// defaults
$template = 'home';
$db_connection = DB_CONNECTION;
$configuration = array(
    '{FEEDBACK}'          => '',
    '{LOGIN_LOGOUT_TEXT}' => 'Login',
    '{LOGIN_LOGOUT_URL}'  => '/?page=login',
    '{METHOD}'            => 'POST',
    '{REGISTER_URL}'      => '/?page=register',
    '{SITE_NAME}'         => SITE_NAME,
    '{LOGIN_USERNAME}'    => '',
    '{REGISTER_USERNAME}' => '',
    '{REGISTER_EMAIL}'    => '',
    '{USER_NAME}'         => '',
    '{USER_EMAIL}'        => ''
);

// comprobar si hay una sesión activa
if (isset($_SESSION['user_id']) && isset($_SESSION['user_name'])) {
    // verificar timeout de sesión
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
        session_unset();
        session_destroy();
        header('Location: /?page=login&timeout=1');
        exit();
    }
    $_SESSION['last_activity'] = time();
    
    // si hay sesión, mostrar página de perfil
    $template = 'profile';
    $configuration['{USER_NAME}'] = clean_input($_SESSION['user_name']);
    $configuration['{USER_EMAIL}'] = clean_input($_SESSION['user_email'] ?? 'No configurado');
    $configuration['{LOGIN_LOGOUT_TEXT}'] = 'Tancar sessió';
    $configuration['{LOGIN_LOGOUT_URL}'] = '/?page=logout';
}

// ==================== API para obtener info del usuario ====================
if (isset($_GET['page']) && $_GET['page'] === 'get_user_info') {
    header('Content-Type: application/json');
    if (isset($_SESSION['user_id']) && isset($_SESSION['user_name'])) {
        echo json_encode([
            'authenticated' => true,
            'user_id' => $_SESSION['user_id'],
            'user_name' => $_SESSION['user_name'],
            'user_email' => $_SESSION['user_email'] ?? ''
        ]);
    } else {
        echo json_encode(['authenticated' => false]);
    }
    exit();
}

// procesar parámetros
if (isset($_GET['page'])) {
    $page = $_GET['page'];
    if ($page == 'register'  && !isset($_SESSION['user_id'])) {
        $template = 'register';
        $configuration['{LOGIN_LOGOUT_TEXT}'] = 'Ja tinc un compte';
    } else if ($page == 'login'  && !isset($_SESSION['user_id'])) {
        $template = 'login';
        if (isset($_GET['timeout'])) {
            $configuration['{FEEDBACK}'] = '<mark>La teva sessió ha expirat. Si us plau, inicia sessió de nou.</mark>';
        }
    } else if ($page == 'logout') {
        session_unset();
        session_destroy();
        header('Location: /');
        exit();
    } else if($page == 'recover'){
        $template = 'recover';
    } else if($page == 'reset' && isset($_GET['token'])){
        $token = $_GET['token'];
        try {
            $db = new PDO(DB_CONNECTION);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $sql = 'SELECT user_id, user_name FROM users WHERE recovery_token = :token AND recovery_expires > :now';
            $query = $db->prepare($sql);
            $query->bindValue(':token', $token);
            $query->bindValue(':now', time());
            $query->execute();
            $result = $query->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $template = 'reset';
                $configuration['{RESET_TOKEN}'] = clean_input($token);
            } else {
                $template = 'recover';
                $configuration['{FEEDBACK}'] = '<mark>ERROR: El enllaç de recuperació ha expirat o no és vàlid</mark>';
            }
        } catch (PDOException $e) {
            $template = 'recover';
            $configuration['{FEEDBACK}'] = '<mark>ERROR: No s\'ha pogut verificar el token</mark>';
        }
    } else if($page == 'verify-2fa' && isset($_SESSION['pending_2fa_user_id'])){
        $template = 'verify_2fa';
    } else if($page == 'profile' && isset($_SESSION['user_id'])){
        $template = 'profile';
    } else {
        $template = 'home';
    }
}

// formularios (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new PDO($db_connection);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // registro de usuario
    if (isset($_POST['register'])) {
        $user_name = $_POST['user_name'] ?? '';
        $user_email = $_POST['user_email'] ?? '';
        $user_password = $_POST['user_password'] ?? '';
        
        $errors = [];

        if(empty($user_name) || empty($user_email) || empty($user_password)) {
            $errors[] = 'El nom d\'usuari, email i contrasenya són obligatoris';
            error_log("[REGISTRO] Campos vacíos: usuario='$user_name', email='$user_email'");
        }
        if(!filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El correu electrònic no és vàlid';
            error_log("[REGISTRO] Email no válido: email='$user_email'");
        }
        if(!validate_password_length($user_password)) {
            $errors[] = 'La contrasenya ha de tenir almenys ' . PASSWORD_MIN_LENGTH . ' caràcters';
            error_log("[REGISTRO] Contraseña demasiado corta: usuario='$user_name'");
        }

        if (empty($errors)) {
            $pwned_check = check_pwned_password($user_password);
            if ($pwned_check['pwned']) {
                $errors[] = 'Aquesta contrasenya ha estat compromesa en ' . number_format($pwned_check['count']) . ' filtracions de dades. Si us plau, utilitza una altra';
                error_log("[REGISTRO] Contraseña comprometida: usuario='$user_name', filtraciones=" . $pwned_check['count']);
            }
        }
        
        if(empty($errors)) {
            try {
                $user_password = hash_password($user_password);
                $totp_code = generate_totp_code();

                $sql = 'INSERT INTO users (user_name, user_email, user_password, totp_secret, email_verified) 
                        VALUES (:user_name, :user_email, :user_password, :totp_secret, 0)';
                $query = $db->prepare($sql);
                $query->bindValue(':user_name', $user_name);
                $query->bindValue(':user_email', $user_email);
                $query->bindValue(':user_password', $user_password);
                $query->bindValue(':totp_secret', $totp_code);
                $query->execute();

                $user_id = $db->lastInsertId();

                $email_subject = 'Verificació del teu compte a ' . SITE_NAME;
                $email_body = "
                    <h2>Benvingut/da a " . SITE_NAME . "</h2>
                    <p>El teu codi de verificació és: <strong style='font-size: 24px;'>$totp_code</strong></p>
                    <p>Aquest codi expirarà en 15 minuts.</p>
                    <p>Si no has sol·licitat aquest registre, ignora aquest missatge.</p>
                ";
                send_email($user_email, $email_subject, $email_body);
                
                $_SESSION['pending_2fa_user_id'] = $user_id;
                $_SESSION['pending_2fa_user_name'] = $user_name;
                $_SESSION['pending_2fa_user_email'] = $user_email;
                $_SESSION['pending_2fa_expires'] = time() + 900;

                header('Location: /?page=verify-2fa');
                exit();

            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $configuration['{FEEDBACK}'] = '<mark>ERROR: El nom d\'usuari <b>' . htmlentities($user_name) . '</b> ja existeix</mark>';
                    error_log("[REGISTRO] Usuario ya existe: usuario='$user_name'");
                } else {
                    $configuration['{FEEDBACK}'] = '<mark>ERROR: No s\'ha pogut crear el compte</mark>';
                    error_log("[REGISTRO] Error de base de datos: " . $e->getMessage());
                }
            }
        }
        else{
            $configuration['{FEEDBACK}'] = '<mark>ERROR: ' . implode('<br>', $errors) . '</mark>';
            $configuration['{REGISTER_USERNAME}'] = clean_input($user_name);
            $configuration['{REGISTER_EMAIL}'] = clean_input($user_email);
            $template = 'register';
        }
    }

    // verificación 2FA
    else if (isset($_POST['verify_2fa'])){
        $code = $_POST['code'] ?? '';

        if(!isset($_SESSION['pending_2fa_user_id'])){
            header('Location: /?page=login');        
            exit();
        }

        if(time() > $_SESSION['pending_2fa_expires']){
            unset($_SESSION['pending_2fa_user_id']);
            $configuration['{FEEDBACK}'] = '<mark>El codi ha expirat. Si us plau, inicia sessió de nou.</mark>';
            header('Location: /?page=login');        
            exit();
        }

        $sql = 'SELECT totp_secret FROM users WHERE user_id = :user_id';
        $query = $db->prepare($sql);
        $query->bindValue(':user_id', $_SESSION['pending_2fa_user_id']);
        $query->execute();
        $result = $query->fetch(PDO::FETCH_ASSOC);

        if($result && $code === $result['totp_secret']){
            $sql = 'UPDATE users SET email_verified = 1 WHERE user_id = :user_id';
            $query = $db->prepare($sql);
            $query->bindValue(':user_id', $_SESSION['pending_2fa_user_id']);
            $query->execute();

            $_SESSION['user_id'] = $_SESSION['pending_2fa_user_id'];
            $_SESSION['user_name'] = $_SESSION['pending_2fa_user_name'];
            $_SESSION['user_email'] = $_SESSION['pending_2fa_user_email'];

            unset($_SESSION['pending_2fa_user_id']);
            unset($_SESSION['pending_2fa_user_name']);
            unset($_SESSION['pending_2fa_user_email']);
            unset($_SESSION['pending_2fa_expires']);

            header('Location: /');
            exit();
        }
        else{
            $configuration['{FEEDBACK}'] = '<mark>ERROR: Codi incorrecte</mark>';
            $template = 'verify_2fa';
        }
    }
    
    // inicio de sesión
    else if (isset($_POST['login'])) {
        $user_name = $_POST['user_name'] ?? '';
        $user_password = $_POST['user_password'] ?? '';
        
        if (empty($user_name) || empty($user_password)) {
            $configuration['{FEEDBACK}'] = '<mark>ERROR: El nom d\'usuari i la contrasenya són obligatoris</mark>';
            $configuration['{LOGIN_USERNAME}'] = htmlentities($user_name);
            error_log("[LOGIN] Campos vacíos: usuario='$user_name'");
        } else {
            $rate_limit = check_rate_limit($db, $user_name);

            if ($rate_limit['locked']) {
                $minutes = ceil($rate_limit['time_left'] / 60);
                $configuration['{FEEDBACK}'] = '<mark>ERROR: Massa intents fallits. Torna-ho a provar en ' . $minutes . ' minuts</mark>';
                error_log("[LOGIN] Usuario bloqueado por demasiados intentos: usuario='$user_name'");
                $template = 'login';
            }
            else {
                $sql = 'SELECT user_id, user_name, user_email, user_password, email_verified FROM users WHERE user_name = :user_name';
                $query = $db->prepare($sql);
                $query->bindValue(':user_name', $user_name);
                $query->execute();
                
                $result = $query->fetch(PDO::FETCH_ASSOC);
                
                if ($result && verify_password($user_password, $result['user_password'])) {
                    if($result['email_verified'] == 0){
                        $configuration['{FEEDBACK}'] = '<mark>ERROR: El teu email no està verificat. Si us plau, comprova la teva safata d\'entrada.</mark>';
                        error_log("[LOGIN] Email no verificado: usuario='$user_name'");
                        $template = 'login';
                    }
                    else{
                        reset_failed_attempts($db, $user_name);

                        $_SESSION['user_id'] = $result['user_id'];
                        $_SESSION['user_name'] = $result['user_name']; 
                        $_SESSION['user_email'] = $result['user_email'];
                        $_SESSION['last_activity'] = time();
                        session_regenerate_id(true);
                        header('Location: /');
                        exit();
                    }
                } else {
                    record_failed_attempt($db, $user_name);
                    $configuration['{FEEDBACK}'] = '<mark>ERROR: Nom d\'usuari o contrasenya incorrectes</mark>';
                    error_log("[LOGIN] Credenciales incorrectas: usuario='$user_name'");
                    $configuration['{LOGIN_USERNAME}'] = clean_input($user_name);
                    $template = 'login';
                }
            }
        }
    }
    
    // recuperación de contraseña
    else if(isset($_POST['recover'])){
        $user_email = $_POST['user_email'] ?? '';

        $configuration['{REGISTER_EMAIL}'] = 'Si el teu email està registrat, rebràs un enllaç de recuperació';
        $template = 'recover';

        if(!empty($user_email) && filter_var($user_email, FILTER_VALIDATE_EMAIL)){
            $sql = 'SELECT user_id, user_name FROM users WHERE user_email = :user_email';
            $query = $db->prepare($sql);
            $query->bindValue(':user_email', $user_email);
            $query->execute();

            $result = $query->fetch(PDO::FETCH_ASSOC);

            if($result){
                $token = generate_token(32);
                $expires = time() + RECOVERY_TOKEN_LIFETIME;

                $sql = 'UPDATE users SET recovery_token = :token, recovery_expires = :expires WHERE user_id = :user_id';
                $query = $db->prepare($sql);
                $query->bindValue(':token', $token);
                $query->bindValue(':expires', $expires);
                $query->bindValue(':user_id', $result['user_id']);
                $query->execute();

                $recovery_link = SITE_URL . '/?page=reset&token=' . $token;
                $email_subject = 'Recuperació de la teva contrasenya a ' . SITE_NAME;
                $email_body = 
                    "<h2>Recuperació de contrasenya</h2>
                    <p>Hola " . clean_input($result['user_name']) . ",</p>
                    <p>Has sol·licitat recuperar la teva contrasenya. Fes clic al següent enllaç per restablir-la:</p>
                    <p><a href='$recovery_link'>$recovery_link</a></p>
                    <p>Aquest enllaç expirarà en 1 hora.</p>
                    <p>Si no has sol·licitat aquest restabliment, ignora aquest missatge.</p>";

                send_email($user_email, $email_subject, $email_body);
            }
        }
    }
    
    // resetear la contraseña
    else if(isset($_POST['reset_password'])){
        $token = $_POST['token'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        $errors = [];

        if(empty($new_password) || empty($confirm_password)){
            $errors[] = 'Tots els camps són obligatoris';
            error_log("[RECUPERACIÓN] Campos vacíos: token='$token'");  
        }
        if($new_password !== $confirm_password){
            $errors[] = 'Les contrasenyes no coincideixen';
            error_log("[RECUPERACIÓN] Contraseñas no coinciden: token='$token'");
        }
        if(!validate_password_length($new_password)){
            $errors[] = 'La contrasenya ha de tenir almenys ' . PASSWORD_MIN_LENGTH . ' caràcters';
            error_log("[RECUPERACIÓN] Contraseña demasiado corta: token='$token'");
        }
        if(empty($errors)){
            $pwned_check = check_pwned_password($new_password);
            if ($pwned_check['pwned']) {
                $errors[] = 'Aquesta contrasenya ha estat compromesa en ' . number_format($pwned_check['count']) . ' filtracions de dades. Si us plau, utilitza una altra';
                error_log("[RECUPERACIÓN] Contraseña comprometida: token='$token', filtraciones=" . $pwned_check['count']);
            }
        }
        
        if(empty($errors)){
            $sql = 'SELECT user_id FROM users WHERE recovery_token = :token AND recovery_expires > :now';
            $query = $db->prepare($sql);
            $query->bindValue(':token', $token);
            $query->bindValue(':now', time());
            $query->execute();

            $result = $query->fetch(PDO::FETCH_ASSOC);

            if($result){
                $hashed_password = hash_password($new_password);

                $sql = 'UPDATE users SET user_password = :user_password, recovery_token = NULL, recovery_expires = NULL WHERE user_id = :user_id';
                $query = $db->prepare($sql);
                $query->bindValue(':user_password', $hashed_password);
                $query->bindValue(':user_id', $result['user_id']);
                $query->execute();

                $configuration['{FEEDBACK}'] = '<mark>La teva contrasenya s\'ha restablert correctament. Ja pots iniciar sessió.</mark>';
                $template = 'login';
            }
            else{
                $errors[] = 'El enllaç de recuperació ha expirat o no és vàlid';
                error_log("[RECUPERACIÓN] Token inválido o expirado: token='$token'");
            }
        }
        if(!empty($errors)){
            $configuration['{FEEDBACK}'] = '<mark>ERROR: ' . implode('<br>', $errors) . '</mark>';
            error_log("[RECUPERACIÓN] Errores: " . implode(' | ', $errors) . " token='$token'");
            $configuration['{RESET_TOKEN}'] = clean_input($token);
            $template = 'reset';
        }
    }
}

// renderizar plantilla
$html = file_get_contents('plantilla_' . $template . '.html', true);
$html = str_replace(array_keys($configuration), array_values($configuration), $html);
echo $html;