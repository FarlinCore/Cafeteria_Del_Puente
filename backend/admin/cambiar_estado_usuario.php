<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'admin') {
    http_response_code(403);
    die(json_encode(['error' => 'Acceso denegado']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['error' => 'Metodo no permitido']));
}

require_once '../config/conexion.php';

$id_usuario   = (int) ($_POST['id_usuario'] ?? 0);
$nueva_accion = trim($_POST['accion']       ?? '');

if ($id_usuario <= 0 || !in_array($nueva_accion, ['activar', 'desactivar'], true)) {
    die(json_encode(['error' => 'Datos invalidos']));
}

// No permitir desactivar al propio admin logueado
if ($id_usuario === (int) $_SESSION['id_usuario'] && $nueva_accion === 'desactivar') {
    die(json_encode(['error' => 'No puedes desactivar tu propia cuenta']));
}

$nuevo_activo = ($nueva_accion === 'activar') ? '1' : '0';

$stmt = $pdo->prepare("UPDATE usuarios SET activo = ? WHERE id_usuario = ?");
$stmt->execute([$nuevo_activo, $id_usuario]);

echo json_encode([
    'ok'     => true,
    'activo' => $nuevo_activo,
    'accion' => $nueva_accion
]);
