<?php
/**
 * Test Suite - Verificación de Funcionalidad Básica
 * Ejecutar desde: php tests/test_basic.php
 */

// Cambiar al directorio raíz del proyecto
chdir(__DIR__ . '/..');

require_once 'public/config.php';
require_once 'public/api/functions.php';

// Colores para output
$GREEN = "\033[32m";
$RED = "\033[31m";
$YELLOW = "\033[33m";
$BLUE = "\033[34m";
$RESET = "\033[0m";

$passed = 0;
$failed = 0;
$tests = [];

function test($name, $callback) {
    global $passed, $failed, $tests, $GREEN, $RED, $YELLOW;
    
    echo "{$YELLOW}▶{$RESET} Testing: $name\n";
    
    try {
        $result = $callback();
        if ($result === true) {
            echo "  {$GREEN}✓ PASSED{$RESET}\n";
            $passed++;
            $tests[] = ['name' => $name, 'status' => 'PASSED'];
        } else {
            echo "  {$RED}✗ FAILED{$RESET}: $result\n";
            $failed++;
            $tests[] = ['name' => $name, 'status' => 'FAILED', 'reason' => $result];
        }
    } catch (Exception $e) {
        echo "  {$RED}✗ EXCEPTION{$RESET}: " . $e->getMessage() . "\n";
        $failed++;
        $tests[] = ['name' => $name, 'status' => 'EXCEPTION', 'reason' => $e->getMessage()];
    }
    
    echo "\n";
}

echo "\n";
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║         🧪 MOSSEGAM - TEST SUITE BÁSICO 🐍              ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n";
echo "\n";

// ==================== TESTS DE BASE DE DATOS ====================
echo "{$BLUE}📂 Tests de Base de Datos{$RESET}\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

test("Base de datos users.db existe", function() {
    return file_exists('private/users.db');
});

test("Base de datos games.db existe", function() {
    return file_exists('private/games.db');
});

test("Conexión a base de datos usuarios", function() {
    try {
        $db = get_db_connection();
        return $db instanceof PDO;
    } catch (Exception $e) {
        return $e->getMessage();
    }
});

test("Conexión a base de datos juegos", function() {
    try {
        $db = get_game_db_connection();
        return $db instanceof PDO;
    } catch (Exception $e) {
        return $e->getMessage();
    }
});

test("Tabla users tiene columna preferred_color", function() {
    $db = get_db_connection();
    $result = $db->query("PRAGMA table_info(users)");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $col) {
        if ($col['name'] === 'preferred_color') {
            return true;
        }
    }
    return "Columna preferred_color no encontrada";
});

test("Tabla users tiene columna preferred_controls", function() {
    $db = get_db_connection();
    $result = $db->query("PRAGMA table_info(users)");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $col) {
        if ($col['name'] === 'preferred_controls') {
            return true;
        }
    }
    return "Columna preferred_controls no encontrada";
});

test("Tabla users tiene columna display_name", function() {
    $db = get_db_connection();
    $result = $db->query("PRAGMA table_info(users)");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $col) {
        if ($col['name'] === 'display_name') {
            return true;
        }
    }
    return "Columna display_name no encontrada";
});

// ==================== TESTS DE FUNCIONES ====================
echo "\n{$BLUE}🔧 Tests de Funciones{$RESET}\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

test("Función clean_input sanitiza correctamente", function() {
    $input = "<script>alert('xss')</script>";
    $cleaned = clean_input($input);
    return (strpos($cleaned, '<script>') === false);
});

test("Función validate_password_length valida correctamente", function() {
    $short = "short123";
    $long = "longenoughpassword123";
    return (!validate_password_length($short) && validate_password_length($long));
});

test("Función hash_password genera hash bcrypt", function() {
    $hash = hash_password("testpassword123");
    return (strpos($hash, '$2y$') === 0);
});

test("Función verify_password verifica correctamente", function() {
    $password = "testpassword123";
    $hash = hash_password($password);
    return verify_password($password, $hash);
});

test("Función generate_token genera token de longitud correcta", function() {
    $token = generate_token(32);
    return strlen($token) === 64; // hex = 2x length
});

test("Función generate_csrf_token genera token válido", function() {
    session_start();
    $token = generate_csrf_token();
    return (strlen($token) === 64 && isset($_SESSION['csrf_token']));
});

// ==================== TESTS DE ARCHIVOS ====================
echo "\n{$BLUE}📄 Tests de Archivos{$RESET}\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

test("Archivo profile_settings.html existe", function() {
    return file_exists('public/pages/profile_settings.html');
});

test("Archivo preferences.php existe", function() {
    return file_exists('public/api/preferences.php');
});

test("Archivo styles/profile_settings.css existe", function() {
    return file_exists('public/styles/profile_settings.css');
});

test("Archivo styles/lobby.css existe", function() {
    return file_exists('public/styles/lobby.css');
});

test("Archivo migrations/001_add_user_preferences.sql existe", function() {
    return file_exists('migrations/001_add_user_preferences.sql');
});

test("Archivo SETUP.md existe", function() {
    return file_exists('SETUP.md');
});

test("Archivo CHANGELOG.md existe", function() {
    return file_exists('CHANGELOG.md');
});

test("Archivo TESTING.md existe", function() {
    return file_exists('TESTING.md');
});

// ==================== TESTS DE CONFIGURACIÓN ====================
echo "\n{$BLUE}⚙️ Tests de Configuración{$RESET}\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

test("Constante BREVO_API_KEY está definida", function() {
    return defined('BREVO_API_KEY');
});

test("Constante BREVO_API_URL está definida", function() {
    return defined('BREVO_API_URL');
});

test("Constante DEVELOPMENT_MODE está definida", function() {
    return defined('DEVELOPMENT_MODE');
});

test("Carpeta private/ tiene permisos correctos", function() {
    return is_writable('private/');
});

test("Carpeta private/emails/ existe o se puede crear", function() {
    if (!is_dir('private/emails/')) {
        mkdir('private/emails/', 0700, true);
    }
    return is_dir('private/emails/');
});

// ==================== RESUMEN ====================
echo "\n";
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║                     📊 RESUMEN                           ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "Total tests: " . ($passed + $failed) . "\n";
echo "{$GREEN}✓ Passed: $passed{$RESET}\n";
echo "{$RED}✗ Failed: $failed{$RESET}\n";

if ($failed > 0) {
    echo "\n{$RED}❌ Algunos tests fallaron. Revisa los errores arriba.{$RESET}\n";
    exit(1);
} else {
    echo "\n{$GREEN}✅ ¡Todos los tests pasaron exitosamente!{$RESET}\n";
    exit(0);
}
?>
