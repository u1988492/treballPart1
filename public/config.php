<?php

// Modo de desarrollo (DESACTIVAR EN PRODUCCIÓN)
define('DEVELOPMENT_MODE', true); // Cambiar a false en producción

// Configuración de bases de datos
define('DB_CONNECTION', 'sqlite:' . __DIR__ . '/../private/users.db');
define('DB_GAMES_CONNECTION', 'sqlite:' . __DIR__ . '/../private/games.db');

// Configuración de seguridad  
define('PASSWORD_MIN_LENGTH', 12);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutos
define('SESSION_LIFETIME', 3600); // 1 hora

// Configuración de email para recuperación y 2FA
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'default@mail.com');
define('SMTP_PASSWORD', '123pwd');
define('SMTP_FROM_EMAIL', 'default@mail.com');
define('SMTP_FROM_NAME', 'Mossegam');

// Configuración del sitio
define('SITE_NAME', 'Mossegam');
define('SITE_URL', 'http://localhost:8000');

// Configuración de tokens
define('RECOVERY_TOKEN_LIFETIME', 3600); // 1 hora
define('TOTP_WINDOW', 1);

// Tiempo de vida de la cookie de sesión (30 días)
define('COOKIE_LIFETIME', 60 * 60 * 24 * 30);

// Configuración de sesiones seguras
// Para producción HTTPS, activar:
// ini_set('session.cookie_secure', 1);
// ini_set('session.cookie_samesite', 'Strict');