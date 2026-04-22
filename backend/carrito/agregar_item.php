<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario'])) {
    die(json_encode(['no_sesion' => true, 'error' => 'Debes iniciar sesion para agregar al carrito']));
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['error' => 'Metodo no permitido']));
}

require_once '../config/conexion.php';

$id_usuario      = (int) $_SESSION['id_usuario'];
$id_producto_raw = (int) ($_POST['id_producto'] ?? 0);
$nombre_producto = trim($_POST['nombre_producto'] ?? '');
$cantidad        = max(1, min(20, (int) ($_POST['cantidad'] ?? 1)));

// Buscar producto: primero por ID, luego por nombre
if ($id_producto_raw > 0) {
    $stmt_p = $pdo->prepare("
        SELECT id_producto, nombre_producto, precio_producto,
               COALESCE(stock, 0) as stock,
               COALESCE(tipo_stock, 'stock') as tipo_stock
        FROM productos
        WHERE id_producto = ? AND activo = 1
        LIMIT 1
    ");
    $stmt_p->execute([$id_producto_raw]);
} elseif ($nombre_producto !== '') {
    $stmt_p = $pdo->prepare("
        SELECT id_producto, nombre_producto, precio_producto,
               COALESCE(stock, 0) as stock,
               COALESCE(tipo_stock, 'stock') as tipo_stock
        FROM productos
        WHERE nombre_producto = ? AND activo = 1
        LIMIT 1
    ");
    $stmt_p->execute([$nombre_producto]);
} else {
    die(json_encode(['error' => 'Producto no especificado']));
}

$producto = $stmt_p->fetch();

if (!$producto) {
    die(json_encode(['error' => 'Producto no disponible o no encontrado']));
}

// Verificar stock si no es ilimitado
if ($producto['tipo_stock'] === 'stock') {
    // Cuanto ya tiene en el carrito
    $stmt_cart = $pdo->prepare("SELECT COALESCE(cantidad,0) FROM carrito WHERE id_usuario=? AND id_producto=?");
    $stmt_cart->execute([$id_usuario, $producto['id_producto']]);
    $cant_en_carrito = (int)$stmt_cart->fetchColumn();

    if ($producto['stock'] <= 0) {
        die(json_encode(['error' => 'Producto agotado', 'agotado' => true]));
    }
    if (($cant_en_carrito + $cantidad) > $producto['stock']) {
        die(json_encode(['error' => 'Solo quedan ' . $producto['stock'] . ' unidades disponibles', 'stock_max' => $producto['stock']]));
    }
}

// Insertar o incrementar en carrito
$stmt_existe = $pdo->prepare("SELECT id_carrito, cantidad FROM carrito WHERE id_usuario = ? AND id_producto = ? LIMIT 1");
$stmt_existe->execute([$id_usuario, $producto['id_producto']]);
$item_existente = $stmt_existe->fetch();

if ($item_existente) {
    $nueva_cant = (int)$item_existente['cantidad'] + $cantidad;
    $stmt_u = $pdo->prepare("UPDATE carrito SET cantidad = ?, fecha_agregado = NOW() WHERE id_carrito = ?");
    $stmt_u->execute([$nueva_cant, $item_existente['id_carrito']]);
} else {
    $stmt_i = $pdo->prepare("INSERT INTO carrito (id_usuario, id_producto, cantidad, fecha_agregado) VALUES (?, ?, ?, NOW())");
    $stmt_i->execute([$id_usuario, $producto['id_producto'], $cantidad]);
}

// Total items en carrito (suma de cantidades)
$stmt_cnt = $pdo->prepare("SELECT COALESCE(SUM(cantidad), 0) FROM carrito WHERE id_usuario = ?");
$stmt_cnt->execute([$id_usuario]);
$cant_carrito = (int)$stmt_cnt->fetchColumn();

echo json_encode([
    'ok'           => true,
    'mensaje'      => 'Producto agregado al carrito',
    'cant_carrito' => $cant_carrito,
    'id_producto'  => $producto['id_producto'],
    'producto'     => $producto['nombre_producto'],
    'precio'       => number_format((float)$producto['precio_producto'], 2, '.', ''),
    'cantidad'     => $cantidad
]);
