<?php
/**
 * Guardar mensaje de contacto en la BD
 */
require_once '../config/conexion.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método no permitido']); exit;
}

$nombre  = trim($_POST['nombre']  ?? '');
$correo  = trim($_POST['correo']  ?? '');
$asunto  = trim($_POST['asunto']  ?? '');
$mensaje = trim($_POST['mensaje'] ?? '');

if (!$nombre || !$correo || !$mensaje) {
    echo json_encode(['error' => 'Nombre, correo y mensaje son obligatorios.']); exit;
}

if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'El correo no es válido.']); exit;
}

$nombre  = mb_substr($nombre,  0, 120);
$correo  = mb_substr($correo,  0, 180);
$asunto  = mb_substr($asunto,  0, 255);
$mensaje = mb_substr($mensaje, 0, 5000);

try {
    $stmt = $pdo->prepare("INSERT INTO mensajes_contacto (nombre, correo, asunto, mensaje) VALUES (?, ?, ?, ?)");
    $stmt->execute([$nombre, $correo, $asunto ?: null, $mensaje]);
    echo json_encode(['ok' => true, 'mensaje' => '¡Mensaje enviado! Te responderemos a la brevedad.']);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Error al guardar el mensaje: ' . $e->getMessage()]);
}
