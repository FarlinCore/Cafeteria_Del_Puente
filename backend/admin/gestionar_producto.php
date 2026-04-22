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

$accion = trim($_POST['accion'] ?? '');

/* ══ HELPER: procesar imagen ══════════════════════════════════════════ */
function procesarImagen($fileKey, $urlKey, $uploadRelPath = '../../images/') {
    if (!empty($_FILES[$fileKey]['name']) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
            return ['error' => 'Formato de imagen no permitido'];
        }
        if ($_FILES[$fileKey]['size'] > 5 * 1024 * 1024) {
            return ['error' => 'La imagen no puede superar 5 MB'];
        }
        $filename   = 'prod_' . time() . '_' . rand(100, 999) . '.' . $ext;
        $uploadPath = __DIR__ . '/' . $uploadRelPath . $filename;
        if (!is_dir(dirname($uploadPath))) {
            mkdir(dirname($uploadPath), 0755, true);
        }
        if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $uploadPath)) {
            return ['ruta' => './images/' . $filename];
        }
        return ['error' => 'No se pudo guardar la imagen'];
    }
    if (!empty($_POST[$urlKey])) {
        return ['ruta' => trim($_POST[$urlKey])];
    }
    return ['ruta' => null];
}

/* ══ AGREGAR PRODUCTO ════════════════════════════════════════════════ */
if ($accion === 'agregar') {
    $nombre      = trim($_POST['nombre_producto'] ?? '');
    $descripcion = trim($_POST['descripcion']     ?? '');
    $precio      = (float)($_POST['precio_producto']  ?? 0);
    $categoria   = trim($_POST['categoria_producto']   ?? '');
    $tipo_stock  = ($_POST['tipo_stock'] ?? 'stock') === 'ilimitado' ? 'ilimitado' : 'stock';
    $stock       = $tipo_stock === 'stock' ? (int)($_POST['stock'] ?? 0) : null;

    if ($nombre === '' || $precio <= 0 || $categoria === '') {
        die(json_encode(['error' => 'Nombre, precio y categoria son requeridos']));
    }

    $img = procesarImagen('imagen', 'imagen_url');
    if (isset($img['error'])) die(json_encode(['error' => $img['error']]));

    $stmt = $pdo->prepare("
        INSERT INTO productos (nombre_producto, descripcion, precio_producto, categoria_producto, stock, tipo_stock, imagen, activo)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1)
    ");
    $stmt->execute([$nombre, $descripcion, $precio, $categoria, $stock, $tipo_stock, $img['ruta']]);

    echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId(), 'mensaje' => 'Producto creado exitosamente']);
    exit;
}

/* ══ EDITAR PRODUCTO ════════════════════════════════════════════════ */
if ($accion === 'editar') {
    $id          = (int)($_POST['id_producto']       ?? 0);
    $nombre      = trim($_POST['nombre_producto']    ?? '');
    $descripcion = trim($_POST['descripcion']        ?? '');
    $precio      = (float)($_POST['precio_producto'] ?? 0);
    $categoria   = trim($_POST['categoria_producto'] ?? '');
    $tipo_stock  = ($_POST['tipo_stock'] ?? 'stock') === 'ilimitado' ? 'ilimitado' : 'stock';
    $stock       = $tipo_stock === 'stock' ? (int)($_POST['stock'] ?? 0) : null;

    if ($id < 1 || $nombre === '') {
        die(json_encode(['error' => 'Datos invalidos']));
    }

    $img = procesarImagen('imagen', 'imagen_url');
    if (isset($img['error'])) die(json_encode(['error' => $img['error']]));

    if ($img['ruta'] !== null) {
        $stmt = $pdo->prepare("UPDATE productos SET nombre_producto=?, descripcion=?, precio_producto=?, categoria_producto=?, stock=?, tipo_stock=?, imagen=? WHERE id_producto=?");
        $stmt->execute([$nombre, $descripcion, $precio, $categoria, $stock, $tipo_stock, $img['ruta'], $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE productos SET nombre_producto=?, descripcion=?, precio_producto=?, categoria_producto=?, stock=?, tipo_stock=? WHERE id_producto=?");
        $stmt->execute([$nombre, $descripcion, $precio, $categoria, $stock, $tipo_stock, $id]);
    }

    echo json_encode(['ok' => true, 'mensaje' => 'Producto actualizado']);
    exit;
}

/* ══ TOGGLE ACTIVO ══════════════════════════════════════════════════ */
if ($accion === 'toggle_activo') {
    $id    = (int)($_POST['id_producto'] ?? 0);
    $actvo = (int)($_POST['activo']      ?? 0);
    $nuevo = $actvo ? 0 : 1;
    $pdo->prepare("UPDATE productos SET activo = ? WHERE id_producto = ?")->execute([$nuevo, $id]);
    echo json_encode(['ok' => true, 'activo' => $nuevo]);
    exit;
}

echo json_encode(['error' => 'Accion no reconocida: ' . $accion]);
