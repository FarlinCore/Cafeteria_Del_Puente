<?php
/**
 * Migración: Crear tablas nuevas para el sistema completo
 * Ejecutar una sola vez en: http://localhost/programacion_pag_web/Cafeteria-del-puente/backend/migration/crear_tablas_nuevas.php
 */
require_once '../config/conexion.php';

$log = [];
$ok  = true;

$queries = [

    // ── Fotos de clientes pendientes de aprobación ──────────────────────
    "fotos_clientes" => "
        CREATE TABLE IF NOT EXISTS fotos_clientes (
            id_foto       INT AUTO_INCREMENT PRIMARY KEY,
            nombre_envio  VARCHAR(120)                          DEFAULT NULL,
            ruta_imagen   VARCHAR(500)                          NOT NULL,
            estado        ENUM('pendiente','aprobada','rechazada') DEFAULT 'pendiente',
            fecha_envio   TIMESTAMP                             DEFAULT CURRENT_TIMESTAMP,
            fecha_revision TIMESTAMP                            NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",

    // ── Mensajes de contacto ─────────────────────────────────────────────
    "mensajes_contacto" => "
        CREATE TABLE IF NOT EXISTS mensajes_contacto (
            id_mensaje  INT AUTO_INCREMENT PRIMARY KEY,
            nombre      VARCHAR(120)  NOT NULL,
            correo      VARCHAR(180)  NOT NULL,
            asunto      VARCHAR(255)  DEFAULT NULL,
            mensaje     TEXT          NOT NULL,
            leido       TINYINT(1)    DEFAULT 0,
            archivado   TINYINT(1)    DEFAULT 0,
            fecha       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",

    // ── Contenido web editable desde admin ──────────────────────────────
    "contenido_web" => "
        CREATE TABLE IF NOT EXISTS contenido_web (
            id_contenido  INT AUTO_INCREMENT PRIMARY KEY,
            clave         VARCHAR(100)  NOT NULL UNIQUE,
            valor         TEXT          NOT NULL,
            tipo          ENUM('texto','numero','json') DEFAULT 'texto',
            actualizado   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",

    // ── Calificaciones de productos ──────────────────────────────────────
    "calificaciones" => "
        CREATE TABLE IF NOT EXISTS calificaciones (
            id_calificacion INT AUTO_INCREMENT PRIMARY KEY,
            id_producto     VARCHAR(50)  NOT NULL,
            id_sesion       VARCHAR(200) NOT NULL,
            estrellas       TINYINT      NOT NULL CHECK (estrellas BETWEEN 1 AND 5),
            fecha           TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_prod_sesion (id_producto, id_sesion)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ",

];

foreach ($queries as $tabla => $sql) {
    try {
        $pdo->exec($sql);
        $log[] = "✅ Tabla <b>{$tabla}</b>: OK";
    } catch (PDOException $e) {
        $log[] = "❌ Tabla <b>{$tabla}</b>: " . htmlspecialchars($e->getMessage());
        $ok = false;
    }
}

// ── Datos iniciales de contenido_web ────────────────────────────────────
$defaults = [
    ['historia',      'Cafetería del Puente nació con el deseo de crear un espacio donde cada persona se sienta en casa. Con sabores auténticos y atención de corazón, hemos construido una comunidad alrededor de una buena taza.', 'texto'],
    ['horario_lunes',     '7:00 AM – 5:00 PM', 'texto'],
    ['horario_martes',    '7:00 AM – 5:00 PM', 'texto'],
    ['horario_miercoles', '7:00 AM – 5:00 PM', 'texto'],
    ['horario_jueves',    '7:00 AM – 5:00 PM', 'texto'],
    ['horario_viernes',   '7:00 AM – 6:00 PM', 'texto'],
    ['horario_sabado',    '8:00 AM – 3:00 PM', 'texto'],
    ['horario_domingo',   'Cerrado',            'texto'],
    ['stat_anos',         '5',   'numero'],
    ['stat_platos',       '10000', 'numero'],
    ['stat_sonrisas',     '500',   'numero'],
];

$ins = $pdo->prepare("INSERT IGNORE INTO contenido_web (clave, valor, tipo) VALUES (?, ?, ?)");
foreach ($defaults as $d) {
    try {
        $ins->execute($d);
        $log[] = "✅ Default <b>{$d[0]}</b>: insertado (si no existía)";
    } catch (PDOException $e) {
        $log[] = "⚠️ Default <b>{$d[0]}</b>: " . htmlspecialchars($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Migración — Tablas nuevas</title>
    <style>
        body { font-family: monospace; background: #1e0f07; color: #f5ece4; padding: 2rem; }
        h1   { color: #F47E24; }
        li   { margin-bottom: .4rem; line-height: 1.5; }
        .ok  { color: #22c55e; }
        .err { color: #ef4444; }
        a    { color: #F47E24; }
    </style>
</head>
<body>
    <h1>Migración: Tablas nuevas</h1>
    <ul>
        <?php foreach ($log as $l): ?>
        <li><?= $l ?></li>
        <?php endforeach; ?>
    </ul>
    <p style="margin-top:2rem;color:<?= $ok?'#22c55e':'#ef4444' ?>">
        <?= $ok ? '✅ Migración completada correctamente.' : '❌ Hubo errores. Revisa los mensajes anteriores.' ?>
    </p>
    <p><a href="../../panel-admin.php">← Volver al panel</a></p>
</body>
</html>
