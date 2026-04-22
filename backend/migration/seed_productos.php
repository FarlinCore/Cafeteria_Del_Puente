<?php
/**
 * SEED: Inserta todos los productos originales del menu con stock de prueba.
 * Acceder UNA SOLA VEZ: http://localhost/programacion_pag_web/Cafeteria-del-puente/backend/migration/seed_productos.php
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/conexion.php';

// Verificar que la migracion ya se hizo (columnas existen)
try {
    $pdo->query("SELECT descripcion FROM productos LIMIT 0");
} catch (PDOException $e) {
    die('<p style="font-family:sans-serif;color:red;padding:40px">
        <strong>Error:</strong> Primero ejecuta la migracion de BD en
        <a href="configurar_bd.php">configurar_bd.php</a>
    </p>');
}

// ── Definicion de productos ─────────────────────────────────────────────
// formato: [nombre, descripcion (variantes con ·), precio, categoria, imagen, tipo_stock, stock]
// bebidas => tipo_stock = 'ilimitado', stock = NULL
// salados/sandwiches => tipo_stock = 'stock', stock inicial de prueba

$productos = [
    // ── SALADOS ──────────────────────────────────────────────────────────
    ['Empanadas clasicas',    'Pollo crema · Res mechada',       60,  'salados',    './images/empanada clasica.jpg',           'stock',     30],
    ['Empanadas gourmet',     'Pizza · Queso · Pollo',           85,  'salados',    './images/empanadas de queso calidad.jpg', 'stock',     20],
    ['Quipes',                'Pollo · Res',                     60,  'salados',    './images/quipe.webp',                    'stock',     25],
    ['Catibias',              'Pollo · Queso',                   30,  'salados',    './images/catibia.jpg',                   'stock',     40],
    ['Bolas de yuca',         'Queso · Pollo con queso',         60,  'salados',    './images/BOLA DE YUCA.jpg',              'stock',     20],
    ['Croquetas de pollo',    'El favorito de todos',            40,  'salados',    './images/croquetas.png',                 'stock',     35],
    ['Chulos',                'Sabor autentico dominicano',      40,  'salados',    './images/chulo.jpg',                     'stock',     25],

    // ── SANDWICHES ───────────────────────────────────────────────────────
    ['Tostada jamon y queso', 'Un clasico que no falla',         50,  'sandwiches', './images/tostada jamon y queso.jpg',     'stock',     20],
    ['Tostada de pollo',      'Con el toque casero del puente',  70,  'sandwiches', './images/TOSTADAS.jpg',                  'stock',     15],
    ['Tostada completa',      'Con todo lo que le gusta',        90,  'sandwiches', './images/TOSTADAS.jpg',                  'stock',     10],
    ['Wraps jamon y queso',   'La opcion diferente y sabrosa',   150, 'sandwiches', './images/warps.jpg',                     'stock',     12],
    ['Sandwich jamon y queso','Suave, simple y delicioso',       70,  'sandwiches', './images/Sandwich.jpg',                  'stock',     15],
    ['Sandwich completo',     'Con todo adentro',                100, 'sandwiches', './images/Sandwich completo.jpg',          'stock',     10],

    // ── BEBIDAS (ilimitado) ───────────────────────────────────────────────
    ['Jugos Naturales',       'Del dia, segun temporada',        60,  'bebidas',    './images/JUGO.jpg',                      'ilimitado', null],
    ['Nevadas',               'Elige tu sabor favorito',         50,  'bebidas',    './images/NEVADAS.jpg',                   'ilimitado', null],
    ['Jugo Verde',            'Refrescante y lleno de energia',  100, 'bebidas',    './images/jugo verde.jpg',                'ilimitado', null],
    ['Embotellados',          'Refresco · Agua mineral',         15,  'bebidas',    './images/refresco.jpg',                  'ilimitado', null],
    ['Cervezas y Cafe',       'Presidente · Heineken · Cafe',    20,  'bebidas',    './images/tazas de cafe chocar.jpg',      'ilimitado', null],
];

$insertados = 0;
$omitidos   = 0;
$errores    = [];

$stmt_check = $pdo->prepare("SELECT COUNT(*) FROM productos WHERE nombre_producto = ?");
$stmt_ins   = $pdo->prepare("
    INSERT INTO productos (nombre_producto, descripcion, precio_producto, categoria_producto, imagen, tipo_stock, stock, activo)
    VALUES (?, ?, ?, ?, ?, ?, ?, 1)
");

foreach ($productos as $p) {
    [$nombre, $desc, $precio, $cat, $img, $tipo, $stock] = $p;

    // Verificar si ya existe
    $stmt_check->execute([$nombre]);
    if ((int)$stmt_check->fetchColumn() > 0) {
        $omitidos++;
        continue;
    }

    try {
        $stmt_ins->execute([$nombre, $desc, $precio, $cat, $img, $tipo, $stock]);
        $insertados++;
    } catch (PDOException $e) {
        $errores[] = "$nombre: " . $e->getMessage();
    }
}

?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Seed Productos — Cafeteria del Puente</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<style>
body{font-family:'Plus Jakarta Sans',sans-serif;background:#F8F5F1;padding:40px;max-width:700px;margin:0 auto;}
h1{color:#1e0f07;font-size:1.4rem;}
.ok{color:#16a34a;font-weight:600;margin:8px 0;}
.warn{color:#d97706;font-weight:600;margin:8px 0;}
.err{color:#dc2626;font-weight:600;margin:8px 0;}
.box{background:#fff;border-radius:12px;padding:24px;box-shadow:0 2px 12px rgba(30,15,7,.08);margin:20px 0;}
.stat{display:inline-block;padding:10px 20px;border-radius:8px;font-weight:700;margin:4px;font-size:.95rem;}
.stat-g{background:#dcfce7;color:#16a34a;}
.stat-y{background:#fef3c7;color:#d97706;}
.stat-r{background:#fee2e2;color:#dc2626;}
a.btn{display:inline-block;background:#F47E24;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:700;margin-top:20px;margin-right:10px;}
a.btn2{display:inline-block;background:#3d1e0b;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:700;margin-top:20px;}
</style>
</head>
<body>
<h1>🌱 Seed de Productos</h1>
<div class="box">
    <div>
        <span class="stat stat-g">✅ Insertados: <?= $insertados ?></span>
        <span class="stat stat-y">⏭ Omitidos (ya existian): <?= $omitidos ?></span>
        <span class="stat stat-r">❌ Errores: <?= count($errores) ?></span>
    </div>

    <?php if (!empty($errores)): ?>
    <hr style="margin:16px 0;border-color:#E8DDD6">
    <p style="color:#dc2626;font-weight:700">Errores detallados:</p>
    <?php foreach ($errores as $e): ?><p class="err">&bull; <?= htmlspecialchars($e) ?></p><?php endforeach; ?>
    <?php endif; ?>

    <?php if ($insertados > 0): ?>
    <p class="ok" style="margin-top:16px">✅ <?= $insertados ?> productos agregados exitosamente a la base de datos.</p>
    <?php elseif ($omitidos === count($productos)): ?>
    <p class="warn" style="margin-top:16px">⚠️ Todos los productos ya estaban en la BD. No se hacen cambios duplicados.</p>
    <?php endif; ?>
</div>

<a class="btn" href="../../menu.php">Ver menu</a>
<a class="btn2" href="../../panel-admin.php">Panel Admin</a>
</body>
</html>
