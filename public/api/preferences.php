<?php
// preferences.php - Gestionar preferencias de usuario
session_start();

require_once '../config.php';
require_once 'functions.php';

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$db = get_db_connection();

// Obtener datos del usuario actual
$sql = 'SELECT * FROM users WHERE user_id = :user_id';
$query = $db->prepare($sql);
$query->bindValue(':user_id', $_SESSION['user_id']);
$query->execute();
$user = $query->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header('Location: ../index.php');
    exit;
}

$feedback = '';
$feedback_class = '';

// Procesar actualización de preferencias
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar CSRF token
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $feedback = 'Error de seguretat. Si us plau, torna-ho a intentar.';
        $feedback_class = 'error';
    } else {
        // Obtener y sanitizar datos
        $display_name = isset($_POST['display_name']) ? clean_input($_POST['display_name']) : '';
        $preferred_color = isset($_POST['preferred_color']) ? clean_input($_POST['preferred_color']) : '#00FF00';
        $preferred_controls = isset($_POST['preferred_controls']) ? clean_input($_POST['preferred_controls']) : 'wasd';
        
        // Validaciones
        $errors = [];
        
        // Validar display_name
        if (empty($display_name)) {
            $errors[] = 'El nom a mostrar no pot estar buit';
        } elseif (strlen($display_name) > 63) {
            $errors[] = 'El nom a mostrar no pot tenir més de 63 caràcters';
        }
        
        // Validar color (formato hex)
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $preferred_color)) {
            $errors[] = 'Format de color invàlid';
        }
        
        // Validar controles
        if (!in_array($preferred_controls, ['wasd', 'arrows'])) {
            $errors[] = 'Controls invàlids';
        }
        
        if (empty($errors)) {
            try {
                // Actualizar preferencias
                $sql = 'UPDATE users SET 
                        display_name = :display_name,
                        preferred_color = :preferred_color,
                        preferred_controls = :preferred_controls
                        WHERE user_id = :user_id';
                
                $query = $db->prepare($sql);
                $query->bindValue(':display_name', $display_name);
                $query->bindValue(':preferred_color', $preferred_color);
                $query->bindValue(':preferred_controls', $preferred_controls);
                $query->bindValue(':user_id', $_SESSION['user_id']);
                $query->execute();
                
                // Actualizar datos en memoria
                $user['display_name'] = $display_name;
                $user['preferred_color'] = $preferred_color;
                $user['preferred_controls'] = $preferred_controls;
                
                $feedback = '✓ Preferències guardades correctament!';
                $feedback_class = 'success';
                
                error_log("[PREFERENCES] Usuario {$user['user_name']} actualizó preferencias");
            } catch (Exception $e) {
                error_log("[PREFERENCES] Error al actualizar preferencias: " . $e->getMessage());
                $feedback = 'Error al guardar les preferències. Si us plau, torna-ho a intentar.';
                $feedback_class = 'error';
            }
        } else {
            $feedback = implode('<br>', $errors);
            $feedback_class = 'error';
        }
    }
}

// Preparar valores por defecto si no existen
if (empty($user['display_name'])) {
    $user['display_name'] = $user['user_name'];
}
if (empty($user['preferred_color'])) {
    $user['preferred_color'] = '#00FF00';
}
if (empty($user['preferred_controls'])) {
    $user['preferred_controls'] = 'wasd';
}

// Generar HTML
$html = file_get_contents('../pages/profile_settings.html');

// Reemplazar placeholders
$html = str_replace('{SITE_NAME}', SITE_NAME, $html);
$html = str_replace('{CSRF_TOKEN}', generate_csrf_token(), $html);
$html = str_replace('{DISPLAY_NAME}', htmlspecialchars($user['display_name'], ENT_QUOTES), $html);
$html = str_replace('{PREFERRED_COLOR}', htmlspecialchars($user['preferred_color'], ENT_QUOTES), $html);

// Marcar el control preferido
if ($user['preferred_controls'] === 'wasd') {
    $html = str_replace('{WASD_CHECKED}', 'checked', $html);
    $html = str_replace('{ARROWS_CHECKED}', '', $html);
} else {
    $html = str_replace('{WASD_CHECKED}', '', $html);
    $html = str_replace('{ARROWS_CHECKED}', 'checked', $html);
}

// Feedback
if (!empty($feedback)) {
    $feedback_html = '<div class="feedback ' . $feedback_class . '">' . $feedback . '</div>';
} else {
    $feedback_html = '';
}
$html = str_replace('{FEEDBACK}', $feedback_html, $html);

echo $html;
?>
