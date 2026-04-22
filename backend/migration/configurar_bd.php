<?php
/**
 * MIGRACION: Agrega columnas de inventario y descripcion a la tabla productos.
 * Acceder UNA SOLA VEZ desde el navegador: http://localhost/programacion_pag_web/Cafeteria-del-puente/backend/migration/configurar_bd.php
 */
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../config/conexion.php';

$pasos = [];
$errores = [];

function ejecutarSQL($pdo, $sql, $descripcion, &$pasos, &$errores) {
    try {
        $pdo->exec($sql);
        $pasos[] = "✅ $descripcion";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            $pasos[] = "⏭️ $descripcion — ya existe, se omite";
        } else {
            $errores[] = "❌ $descripcion — " . $e->getMessage();
        }
    }
}

// 1. Columna descripcion
ejecutarSQL($pdo, "ALTER TABLE productos ADD COLUMN descripcion TEXT NULL AFTER nombre_producto", "Columna 'descripcion'", $pasos, $errores);

// 2. Columna stock
ejecutarSQL($pdo, "ALTER TABLE productos ADD COLUMN stock INT DEFAULT NULL AFTER precio_producto", "Columna 'stock'", $pasos, $errores);

// 3. Columna tipo_stock
ejecutarSQL($pdo, "ALTER TABLE productos ADD COLUMN tipo_stock ENUM('stock','ilimitado') DEFAULT 'stock' AFTER stock", "Columna 'tipo_stock'", $pasos, $errores);

// 4. Asignar beverages como ilimitado
ejecutarSQL($pdo, "UPDATE productos SET tipo_stock = 'ilimitado' WHERE categoria_producto = 'bebidas'", "Marcar bebidas como ilimitado", $pasos, $errores);

// 5. Default stock para productos con inventario
ejecutarSQL($pdo, "UPDATE productos SET stock = 20 WHERE tipo_stock = 'stock' AND stock IS NULL", "Stock inicial = 20 para productos existentes", $pasos, $errores);

// 6. Asegurar que imagen existe
ejecutarSQL($pdo, "ALTER TABLE productos ADD COLUMN imagen VARCHAR(500) NULL", "Columna 'imagen'", $pasos, $errores);

// 7. Tabla pedidos: asegurar columna notas
ejecutarSQL($pdo, "ALTER TABLE pedidos ADD COLUMN notas_pedido TEXT NULL", "Columna 'notas_pedido' en pedidos", $pasos, $errores);

?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Migracion BD — Cafeteria del Puente</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<style>
body { font-family:'Plus Jakarta Sans',sans-serif; background:#F8F5F1; padding:40px; max-width:700px; margin:0 auto; }
h1 { color:#1e0f07; font-size:1.5rem; }
.ok  { color:#16a34a; font-weight:600; margin:8px 0; }
.err { color:#dc2626; font-weight:600; margin:8px 0; }
.box { background:#fff; border-radius:12px; padding:24px; box-shadow:0 2px 12px rgba(30,15,7,.08); margin:20px 0; }
a.btn { display:inline-block; background:#F47E24; color:#fff; padding:12px 28px; border-radius:8px; text-decoration:none; font-weight:700; margin-top:20px; }
</style>
</head>
<body>
<h1>⚙️ Migracion de Base de Datos</h1>
<div class="box">
<?php foreach ($pasos as $p): ?><p class="ok"><?= $p ?></p><?php endforeach; ?>
<?php foreach ($errores as $e): ?><p class="err"><?= $e ?></p><?php endforeach; ?>
<?php if (empty($errores)): ?>
<p style="color:#16a34a;font-weight:700;font-size:1.1rem">✅ Migracion completada exitosamente.</p>
<?php else: ?>
<p style="color:#dc2626;font-weight:700">⚠️ Revisa los errores arriba.</p>
<?php endif; ?>
</div>
<a class="btn" href="../../panel-admin.php">Ir al Panel Admin</a>
<a class="btn" href="seed_productos.php" style="background:#3d1e0b;margin-left:10px">Cargar productos de ejemplo</a>
</body>
</html>
