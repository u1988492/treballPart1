<?php
/**
 * Script de inicialización de bases de datos para Azure
 * Ejecutar UNA VEZ después del primer despliegue mediante Kudu Console:
 * 
 * Azure Portal → Tu Web App → Advanced Tools → Go → Debug console → CMD
 * cd D:\home\site\wwwroot
 * php init_azure_db.php
 */

require_once 'config.php';

echo "=== Inicialización de Bases de Datos en Azure ===\n\n";

function init_database($connection_string, $db_name) {
    echo "Inicializando $db_name...\n";
    
    // Extraer ruta de la cadena de conexión
    $db_path = str_replace('sqlite:', '', $connection_string);
    
    // Crear directorio si no existe
    $dir = dirname($db_path);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            die("ERROR: No se pudo crear el directorio $dir\n");
        }
        echo "  ✓ Directorio creado: $dir\n";
    }
    
    // Verificar si la base de datos ya existe
    if (file_exists($db_path)) {
        echo "  ⚠ Base de datos ya existe: $db_path\n";
        echo "  ¿Desea recrearla? (se perderán los datos)\n";
        echo "  Si desea recrearla, elimine el archivo manualmente y vuelva a ejecutar este script.\n\n";
        return;
    }
    
    try {
        // Crear base de datos
        $db = new PDO($connection_string);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Leer SQL desde archivo
        $sql_file = __DIR__ . '/../setup/create_databases.sql';
        if (!file_exists($sql_file)) {
            die("ERROR: No se encuentra el archivo SQL: $sql_file\n");
        }
        
        $sql = file_get_contents($sql_file);
        
        // Ejecutar SQL
        $db->exec($sql);
        
        echo "  ✓ Base de datos creada: $db_path\n";
        
        // Verificar tablas creadas
        $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        echo "  ✓ Tablas creadas: " . implode(', ', $tables) . "\n";
        
        // Establecer permisos
        chmod($db_path, 0644);
        echo "  ✓ Permisos establecidos (0644)\n\n";
        
    } catch (PDOException $e) {
        die("ERROR: " . $e->getMessage() . "\n");
    }
}

// Inicializar ambas bases de datos
echo "Entorno: " . (IS_AZURE ? "Azure" : "Local") . "\n";
echo "-------------------------------------------\n\n";

init_database(DB_CONNECTION, 'Users Database');
init_database(DB_GAMES_CONNECTION, 'Games Database');

echo "=== Inicialización completada ===\n";
echo "\nPróximos pasos:\n";
echo "1. Verificar que las bases de datos se crearon correctamente\n";
echo "2. Probar el registro de un usuario en la aplicación\n";
echo "3. Configurar las variables de entorno SMTP en Azure Portal\n";
echo "4. Probar el flujo completo de autenticación\n";
