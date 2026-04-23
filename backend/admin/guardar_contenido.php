<?php
/**
 * Guardar contenido web editable (historia, horarios, stats)
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/conexion.php';
header('Content-Type: application/json');

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    echo json_encode(['error' => 'No autorizado']); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método no permitido']); exit;
}

$campos_permitidos = [
    'historia', 
    'horario_lunes','horario_martes','horario_miercoles',
    'horario_jueves','horario_viernes','horario_sabado','horario_domingo',
    'stat_anos','stat_platos','stat_sonrisas'
];

try {
    $stmt = $pdo->prepare("
        INSERT INTO contenido_web (clave, valor) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE valor = VALUES(valor), actualizado = CURRENT_TIMESTAMP
    ");

    $guardados = [];
    foreach ($campos_permitidos as $campo) {
        if (isset($_POST[$campo])) {
            $valor = trim($_POST[$campo]);
            $valor = mb_substr($valor, 0, 5000);
            $stmt->execute([$campo, $valor]);
            $guardados[] = $campo;
        }
    }

    if (empty($guardados)) {
        echo json_encode(['error' => 'No se recibió ningún campo válido.']); exit;
    }

    echo json_encode(['ok' => true, 'guardados' => $guardados, 'mensaje' => 'Contenido actualizado correctamente.']);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
