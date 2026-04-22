<?php
/**
 * eliminar_direccion.php — Elimina una dirección guardada del usuario.
 * POST: id_direccion
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario'])) {
    http_response_code(401);
    die(json_encode(['error' => 'No autenticado']));
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['error' => 'Método no permitido']));
}

require_once '../config/conexion.php';

$id_usuario   = (int) $_SESSION['id_usuario'];
$id_direccion = (int) ($_POST['id_direccion'] ?? 0);

if ($id_direccion <= 0) {
    die(json_encode(['error' => 'ID de dirección inválido']));
}

$stmt = $pdo->prepare("DELETE FROM direcciones_usuario WHERE id_direccion = ? AND id_usuario = ?");
$stmt->execute([$id_direccion, $id_usuario]);

if ($stmt->rowCount() === 0) {
    die(json_encode(['error' => 'Dirección no encontrada']));
}

echo json_encode(['ok' => true, 'mensaje' => 'Dirección eliminada']);
