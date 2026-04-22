<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    die(json_encode(['error' => 'Sin permisos']));
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['error' => 'Metodo no permitido']));
}

require_once '../config/conexion.php';

$id_producto = (int)($_POST['id_producto'] ?? 0);
$nuevo_stock = (int)($_POST['stock']       ?? 0);
$operacion   = trim($_POST['operacion']    ?? 'set'); // set | sumar | restar

if ($id_producto < 1) {
    die(json_encode(['error' => 'ID de producto invalido']));
}

// Verificar que el producto existe y es de tipo stock
$prod = $pdo->prepare("SELECT id_producto, stock, tipo_stock FROM productos WHERE id_producto = ? AND activo = 1");
$prod->execute([$id_producto]);
$producto = $prod->fetch();

if (!$producto) {
    die(json_encode(['error' => 'Producto no encontrado']));
}
if ($producto['tipo_stock'] !== 'stock') {
    die(json_encode(['error' => 'Este producto no maneja stock numerico']));
}

$stock_actual = (int)($producto['stock'] ?? 0);

switch ($operacion) {
    case 'sumar':
        $nuevo_stock = max(0, $stock_actual + $nuevo_stock);
        break;
    case 'restar':
        $nuevo_stock = max(0, $stock_actual - $nuevo_stock);
        break;
    default: // 'set'
        $nuevo_stock = max(0, $nuevo_stock);
}

$stmt = $pdo->prepare("UPDATE productos SET stock = ? WHERE id_producto = ?");
$stmt->execute([$nuevo_stock, $id_producto]);

echo json_encode([
    'ok'          => true,
    'id_producto' => $id_producto,
    'stock'       => $nuevo_stock,
    'estado'      => $nuevo_stock === 0 ? 'agotado' : ($nuevo_stock < 5 ? 'bajo' : 'normal')
]);
