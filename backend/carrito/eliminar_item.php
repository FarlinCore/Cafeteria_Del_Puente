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

$id_carrito = (int) ($_POST['id_carrito'] ?? 0);
$id_usuario = (int) $_SESSION['id_usuario'];

if ($id_carrito <= 0) {
    die(json_encode(['error' => 'Datos inválidos']));
}

// Eliminar solo si el item pertenece al usuario (seguridad)
$stmt = $pdo->prepare("
    DELETE FROM carrito
    WHERE id_carrito = ? AND id_usuario = ?
");
$stmt->execute([$id_carrito, $id_usuario]);

if ($stmt->rowCount() === 0) {
    die(json_encode(['error' => 'Item no encontrado']));
}

// Calcular nuevo total del carrito
$stmt_total = $pdo->prepare("
    SELECT COALESCE(SUM(p.precio_producto * c.cantidad), 0) AS total
    FROM carrito c
    JOIN productos p ON c.id_producto = p.id_producto
    WHERE c.id_usuario = ?
");
$stmt_total->execute([$id_usuario]);
$total = $stmt_total->fetchColumn();

echo json_encode([
    'ok'    => true,
    'total' => number_format((float) $total, 2, '.', '')
]);
