<?php
/**
 * crear_pedido.php — Convierte el carrito en un pedido registrado.
 * POST:  direccion_entrega (opcional)
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

$id_usuario        = (int) $_SESSION['id_usuario'];
$direccion_entrega = trim($_POST['direccion_entrega'] ?? '');

// 1. Obtener items del carrito con precio
$stmt = $pdo->prepare("
    SELECT c.id_carrito, c.id_producto, c.cantidad,
           p.nombre_producto, p.precio_producto,
           COALESCE(p.tipo_stock,'stock') AS tipo_stock,
           COALESCE(p.stock, 0) AS stock
    FROM carrito c
    JOIN productos p ON c.id_producto = p.id_producto
    WHERE c.id_usuario = ?
");
$stmt->execute([$id_usuario]);
$items = $stmt->fetchAll();

if (empty($items)) {
    die(json_encode(['error' => 'Tu carrito está vacío']));
}

// 2. Verificar stock suficiente
foreach ($items as $item) {
    if ($item['tipo_stock'] === 'stock' && $item['cantidad'] > $item['stock']) {
        die(json_encode([
            'error' => 'Stock insuficiente para "' . $item['nombre_producto'] . '". Solo quedan ' . $item['stock'] . ' unidades.'
        ]));
    }
}

// 3. Calcular total
$total = 0;
foreach ($items as $item) {
    $total += $item['precio_producto'] * $item['cantidad'];
}

// 4. Transacción: crear pedido → insertar detalles → descontar stock → limpiar carrito
try {
    $pdo->beginTransaction();

    // Insertar pedido
    $stmtPed = $pdo->prepare("
        INSERT INTO pedidos (id_usuario, fecha_pedido, total_pedido, estado_pedido, notas_pedido, direccion_entrega)
        VALUES (?, NOW(), ?, 'pendiente', '', ?)
    ");
    // Si la columna direccion_entrega no existe aun, intentar sin ella
    try {
        $stmtPed->execute([$id_usuario, $total, $direccion_entrega ?: null]);
    } catch (PDOException $e) {
        // columna no existe — intentar query sin esa columna
        $stmtPed2 = $pdo->prepare("
            INSERT INTO pedidos (id_usuario, fecha_pedido, total_pedido, estado_pedido, notas_pedido)
            VALUES (?, NOW(), ?, 'pendiente', '')
        ");
        $stmtPed2->execute([$id_usuario, $total]);
    }

    $id_pedido = (int) $pdo->lastInsertId();

    // Insertar detalles
    $stmtDet = $pdo->prepare("
        INSERT INTO detalle_pedido (id_pedido, id_producto, cantidad, precio_unitario)
        VALUES (?, ?, ?, ?)
    ");
    foreach ($items as $item) {
        $stmtDet->execute([$id_pedido, $item['id_producto'], $item['cantidad'], $item['precio_producto']]);

        // Descontar stock si aplica
        if ($item['tipo_stock'] === 'stock') {
            $stmtStock = $pdo->prepare("
                UPDATE productos SET stock = GREATEST(0, stock - ?) WHERE id_producto = ?
            ");
            $stmtStock->execute([$item['cantidad'], $item['id_producto']]);
        }
    }

    // Limpiar carrito
    $stmtDel = $pdo->prepare("DELETE FROM carrito WHERE id_usuario = ?");
    $stmtDel->execute([$id_usuario]);

    $pdo->commit();

    echo json_encode([
        'ok'        => true,
        'id_pedido' => $id_pedido,
        'total'     => number_format($total, 2, '.', ''),
        'mensaje'   => 'Pedido #' . $id_pedido . ' creado exitosamente'
    ]);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Error al procesar el pedido: ' . $e->getMessage()]);
}
