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

$id_pedido     = (int) ($_POST['id_pedido']     ?? 0);
$nuevo_estado  = trim($_POST['estado']          ?? '');

$estados_validos = ['pendiente', 'en_proceso', 'listo', 'entregado', 'cancelado'];

if ($id_pedido <= 0 || !in_array($nuevo_estado, $estados_validos, true)) {
    die(json_encode(['error' => 'Datos invalidos']));
}

$stmt = $pdo->prepare("UPDATE pedidos SET estado_pedido = ? WHERE id_pedido = ?");
$stmt->execute([$nuevo_estado, $id_pedido]);

if ($stmt->rowCount() === 0) {
    die(json_encode(['error' => 'Pedido no encontrado']));
}

echo json_encode([
    'ok'     => true,
    'estado' => $nuevo_estado
]);
