<?php
/**
 * listar_direcciones.php — Devuelve las direcciones del usuario autenticado.
 * GET (no parameters required)
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario'])) {
    http_response_code(401);
    die(json_encode(['error' => 'No autenticado']));
}

require_once '../config/conexion.php';

$id_usuario = (int) $_SESSION['id_usuario'];

// Crear tabla y columnas si faltan (auto-migración defensiva)
try {
    $pdo->exec("
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
    ");
    // Agregar columnas que puedan faltar en instalaciones antiguas
    $cols = $pdo->query("SHOW COLUMNS FROM direcciones_usuario")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('alias',       $cols)) $pdo->exec("ALTER TABLE direcciones_usuario ADD COLUMN alias VARCHAR(80) NOT NULL DEFAULT 'Mi dirección' AFTER id_usuario");
    if (!in_array('referencia',  $cols)) $pdo->exec("ALTER TABLE direcciones_usuario ADD COLUMN referencia VARCHAR(200) NULL");
    if (!in_array('es_favorita', $cols)) $pdo->exec("ALTER TABLE direcciones_usuario ADD COLUMN es_favorita TINYINT(1) NOT NULL DEFAULT 0");
    if (!in_array('creado_en',   $cols)) $pdo->exec("ALTER TABLE direcciones_usuario ADD COLUMN creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
} catch (PDOException $e) { /* ignorar */ }

$stmt = $pdo->prepare("
    SELECT id_direccion, alias, direccion, referencia, es_favorita, creado_en
    FROM direcciones_usuario
    WHERE id_usuario = ?
    ORDER BY es_favorita DESC, creado_en DESC
");
$stmt->execute([$id_usuario]);
$dirs = $stmt->fetchAll();

echo json_encode(['ok' => true, 'direcciones' => $dirs]);
