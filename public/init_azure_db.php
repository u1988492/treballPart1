<?php
/**
 * Script de inicialización de bases de datos para Azure
 * Ejecutar una vez después del primer despliegue vía Kudu Console
 */

require_once __DIR__ . '/config.php';

function init_database($db_path, $sql_file) {
    echo "Inicializando base de datos: $db_path\n";
    
    // Crear directorio si no existe
    $dir = dirname($db_path);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            die("Error: No se pudo crear el directorio $dir\n");
        }
        echo "Directorio creado: $dir\n";
    }
    
    // Verificar que el archivo SQL existe
    if (!file_exists($sql_file)) {
        die("Error: Archivo SQL no encontrado: $sql_file\n");
    }
    
    try {
        // Crear conexión a la base de datos
        $db = new PDO('sqlite:' . $db_path);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Leer y ejecutar el SQL
        $sql = file_get_contents($sql_file);
        $db->exec($sql);
        
        echo "✓ Base de datos inicializada correctamente: $db_path\n";
        
        // Verificar permisos
        if (!is_writable($db_path)) {
            echo "ADVERTENCIA: La base de datos podría no ser escribible\n";
        }
        
    } catch (PDOException $e) {
        die("Error al inicializar la base de datos: " . $e->getMessage() . "\n");
    }
}

echo "=================================\n";
echo "Inicialización de Bases de Datos\n";
echo "=================================\n\n";

// Determinar la ruta del archivo SQL
$sql_file = __DIR__ . '/../setup/create_databases.sql';

// Obtener las rutas de las bases de datos desde config.php
$users_db_path = str_replace('sqlite:', '', DB_CONNECTION);
$games_db_path = str_replace('sqlite:', '', DB_GAMES_CONNECTION);

echo "Configuración detectada:\n";
echo "- Entorno Azure: " . (IS_AZURE ? "SÍ" : "NO") . "\n";
echo "- Base de datos usuarios: $users_db_path\n";
echo "- Base de datos juegos: $games_db_path\n";
echo "- Archivo SQL: $sql_file\n\n";

// Inicializar ambas bases de datos
init_database($users_db_path, $sql_file);
echo "\n";
init_database($games_db_path, $sql_file);

echo "\n=================================\n";
echo "¡Inicialización completada!\n";
echo "=================================\n";
?>
