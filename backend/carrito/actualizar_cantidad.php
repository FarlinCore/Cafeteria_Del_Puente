<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario'])) {
    http_response_code(401);
    die(json_encode(['error' => 'No autenticado']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['error' => 'Método no permitido']));
}

require_once '../config/conexion.php';

$id_carrito  = (int) ($_POST['id_carrito'] ?? 0);
$cantidad    = (int) ($_POST['cantidad']   ?? 0);
$id_usuario  = (int) $_SESSION['id_usuario'];

if ($id_carrito <= 0 || $cantidad <= 0) {
    die(json_encode(['error' => 'Datos inválidos']));
}

// Actualizar solo si el item pertenece al usuario
$stmt = $pdo->prepare("
    UPDATE carrito SET cantidad = ?
    WHERE id_carrito = ? AND id_usuario = ?
");
$stmt->execute([$cantidad, $id_carrito, $id_usuario]);

if ($stmt->rowCount() === 0) {
    die(json_encode(['error' => 'Item no encontrado']));
}

// Calcular nuevo subtotal del item
$stmt_sub = $pdo->prepare("
    SELECT (p.precio_producto * c.cantidad) AS subtotal
    FROM carrito c
    JOIN productos p ON c.id_producto = p.id_producto
    WHERE c.id_carrito = ?
");
$stmt_sub->execute([$id_carrito]);
$sub = $stmt_sub->fetchColumn();

// Calcular total del carrito del usuario
$stmt_total = $pdo->prepare("
    SELECT SUM(p.precio_producto * c.cantidad) AS total
    FROM carrito c
    JOIN productos p ON c.id_producto = p.id_producto
    WHERE c.id_usuario = ?
");
$stmt_total->execute([$id_usuario]);
$total = $stmt_total->fetchColumn() ?? 0;

echo json_encode([
    'ok'       => true,
    'subtotal' => number_format((float) $sub,   2, '.', ''),
    'total'    => number_format((float) $total, 2, '.', '')
]);
