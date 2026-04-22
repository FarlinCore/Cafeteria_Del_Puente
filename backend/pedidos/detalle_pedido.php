<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario'])) {
    http_response_code(401);
    die(json_encode(['error' => 'No autenticado']));
}

require_once '../config/conexion.php';

$es_admin   = ($_SESSION['rol'] ?? '') === 'admin';
$id_usuario = (int) $_SESSION['id_usuario'];
$id_pedido  = (int) ($_GET['id_pedido'] ?? 0);   // ← leer del query string

if ($id_pedido <= 0) {
    die(json_encode(['error' => 'ID de pedido invalido']));
}

// Admin puede ver cualquier pedido; usuario solo el suyo
if (!$es_admin) {
    $stmt_check = $pdo->prepare("SELECT id_pedido FROM pedidos WHERE id_pedido = ? AND id_usuario = ?");
    $stmt_check->execute([$id_pedido, $id_usuario]);
    if (!$stmt_check->fetch()) {
        die(json_encode(['error' => 'Pedido no encontrado']));
    }
}

// Obtener los items del detalle
$stmt = $pdo->prepare("
    SELECT dp.cantidad, dp.precio_unitario,
           p.nombre_producto
    FROM detalle_pedido dp
    JOIN productos p ON dp.id_producto = p.id_producto
    WHERE dp.id_pedido = ?
");
$stmt->execute([$id_pedido]);
$items = $stmt->fetchAll();

echo json_encode($items);
