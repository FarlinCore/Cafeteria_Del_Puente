<?php
/**
 * Gestionar mensajes de contacto desde el admin
 * Acciones: marcar_leido, archivar, eliminar
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/conexion.php';
header('Content-Type: application/json');

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    echo json_encode(['error' => 'No autorizado']); exit;
}

$accion     = $_POST['accion']     ?? '';
$id_mensaje = (int)($_POST['id_mensaje'] ?? 0);

if (!$id_mensaje) { echo json_encode(['error' => 'ID inválido']); exit; }

try {
    switch ($accion) {
        case 'marcar_leido':
            $pdo->prepare("UPDATE mensajes_contacto SET leido = 1 WHERE id_mensaje = ?")->execute([$id_mensaje]);
            echo json_encode(['ok' => true]);
            break;

        case 'archivar':
            $pdo->prepare("UPDATE mensajes_contacto SET archivado = 1, leido = 1 WHERE id_mensaje = ?")->execute([$id_mensaje]);
            echo json_encode(['ok' => true]);
            break;

        case 'eliminar':
            $pdo->prepare("DELETE FROM mensajes_contacto WHERE id_mensaje = ?")->execute([$id_mensaje]);
            echo json_encode(['ok' => true]);
            break;

        default:
            echo json_encode(['error' => 'Acción desconocida']);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
