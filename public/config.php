<?php

// Detectar entorno Azure
define('IS_AZURE', getenv('WEBSITE_SITE_NAME') !== false);

// Modo de desarrollo - SIEMPRE false en Azure
define('DEVELOPMENT_MODE', !IS_AZURE && (getenv('DEV_MODE') === 'true'));

// Configuración de bases de datos
if (IS_AZURE) {
    // En Azure, usar directorio dentro de wwwroot (más compatible con permisos)
    define('DB_CONNECTION', 'sqlite:' . __DIR__ . '/../private/users.db');
    define('DB_GAMES_CONNECTION', 'sqlite:' . __DIR__ . '/../private/games.db');
} else {
    // Desarrollo local
    define('DB_CONNECTION', 'sqlite:' . __DIR__ . '/../private/users.db');
    define('DB_GAMES_CONNECTION', 'sqlite:' . __DIR__ . '/../private/games.db');
}

// Configuración de seguridad  
define('PASSWORD_MIN_LENGTH', 12);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutos
define('SESSION_LIFETIME', 3600); // 1 hora

// Configuración de email para recuperación y 2FA
// En Azure, usar variables de entorno; en local, valores por defecto
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: 'default@mail.com');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '123pwd');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: 'default@mail.com');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'Mossegam');

// Configuración del sitio
define('SITE_NAME', 'Mossegam');

// URL del sitio - auto-detectar en Azure
if (IS_AZURE) {
    $site_name = getenv('WEBSITE_SITE_NAME');
    define('SITE_URL', 'https://' . $site_name . '.azurewebsites.net');
} else {
    define('SITE_URL', getenv('SITE_URL') ?: 'http://localhost:8000');
}

// Configuración de tokens
define('RECOVERY_TOKEN_LIFETIME', 3600); // 1 hora
define('TOTP_WINDOW', 1);

// Tiempo de vida de la cookie de sesión (30 días)
define('COOKIE_LIFETIME', 60 * 60 * 24 * 30);

// Configuración de sesiones seguras en Azure (HTTPS)
if (IS_AZURE) {
    ini_set('session.cookie_secure', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.cookie_httponly', 1);
}
