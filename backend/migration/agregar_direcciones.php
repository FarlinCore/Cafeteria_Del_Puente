<?php
/**
 * MIGRACIÓN: Crea la tabla direcciones_usuario para guardar direcciones favoritas.
 * Acceder UNA SOLA VEZ desde el navegador:
 * http://localhost/programacion_pag_web/Cafeteria-del-puente/backend/migration/agregar_direcciones.php
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/conexion.php';

$pasos   = [];
$errores = [];

function ejecutarSQL($pdo, $sql, $descripcion, &$pasos, &$errores) {
    try {
        $pdo->exec($sql);
        $pasos[] = "✅ $descripcion";
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'already exists') !== false || strpos($msg, 'Duplicate') !== false) {
            $pasos[] = "⏭️ $descripcion — ya existe, se omite";
        } else {
            $errores[] = "❌ $descripcion — $msg";
        }
    }
}

// 1. Tabla de direcciones
ejecutarSQL($pdo, "
    CREATE TABLE IF NOT EXISTS direcciones_usuario (
        id_direccion  INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario    INT NOT NULL,
        alias         VARCHAR(80)  NOT NULL DEFAULT 'Mi dirección',
        direccion     VARCHAR(250) NOT NULL,
        referencia    VARCHAR(200) NULL,
        es_favorita   TINYINT(1)  NOT NULL DEFAULT 0,
        creado_en     TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
", "Tabla 'direcciones_usuario'", $pasos, $errores);

// 2. Columna direccion_entrega en pedidos (opcional, para guardar la dirección usada)
ejecutarSQL($pdo, "ALTER TABLE pedidos ADD COLUMN direccion_entrega VARCHAR(300) NULL AFTER notas_pedido",
    "Columna 'direccion_entrega' en pedidos", $pasos, $errores);

?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Migración Direcciones — Cafeteria del Puente</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<style>
body { font-family:'Plus Jakarta Sans',sans-serif; background:#F8F5F1; padding:40px; max-width:700px; margin:0 auto; }
h1   { color:#1e0f07; font-size:1.5rem; }
.ok  { color:#16a34a; font-weight:600; margin:8px 0; }
.err { color:#dc2626; font-weight:600; margin:8px 0; }
.box { background:#fff; border-radius:12px; padding:24px; box-shadow:0 2px 12px rgba(30,15,7,.08); margin:20px 0; }
a.btn { display:inline-block; background:#F47E24; color:#fff; padding:12px 28px; border-radius:8px; text-decoration:none; font-weight:700; margin-top:20px; }
</style>
</head>
<body>
<h1>📍 Migración — Direcciones de Usuario</h1>
<div class="box">
<?php foreach ($pasos   as $p): ?><p class="ok"><?= $p ?></p><?php endforeach; ?>
<?php foreach ($errores as $e): ?><p class="err"><?= $e ?></p><?php endforeach; ?>
<?php if (empty($errores)): ?>
<p style="color:#16a34a;font-weight:700;font-size:1.1rem">✅ Migración completada exitosamente.</p>
<?php else: ?>
<p style="color:#dc2626;font-weight:700">⚠️ Revisa los errores arriba.</p>
<?php endif; ?>
</div>
<a class="btn" href="../../panel-usuario.php">Ir a Mi Panel</a>
<a class="btn" href="../../panel-admin.php" style="background:#3d1e0b;margin-left:10px">Panel Admin</a>
</body>
</html>
