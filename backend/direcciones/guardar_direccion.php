<?php
/**
 * guardar_direccion.php — Agrega o edita una dirección del usuario.
 * POST: alias, direccion, referencia, es_favorita, id_direccion (si se edita)
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

$id_usuario   = (int) $_SESSION['id_usuario'];
$id_direccion = (int) ($_POST['id_direccion'] ?? 0);
$alias        = trim($_POST['alias']      ?? 'Mi dirección');
$direccion    = trim($_POST['direccion']  ?? '');
$referencia   = trim($_POST['referencia'] ?? '');
$es_favorita  = (($_POST['es_favorita'] ?? '0') === '1') ? 1 : 0;

// Validaciones
if ($direccion === '') {
    die(json_encode(['error' => 'La dirección no puede estar vacía']));
}
if ($alias === '') $alias = 'Mi dirección';
if (mb_strlen($alias)      > 80)  $alias      = mb_substr($alias,      0, 80);
if (mb_strlen($direccion)  > 250) $direccion  = mb_substr($direccion,  0, 250);
if (mb_strlen($referencia) > 200) $referencia = mb_substr($referencia, 0, 200);

// Si se marca como favorita, quitar la marca de las otras
if ($es_favorita) {
    $stmtFav = $pdo->prepare("UPDATE direcciones_usuario SET es_favorita = 0 WHERE id_usuario = ?");
    $stmtFav->execute([$id_usuario]);
}

if ($id_direccion > 0) {
    // ── Editar dirección existente ──────────────────────────────────────
    // Verificar que la dirección pertenece al usuario
    $stmtCheck = $pdo->prepare("SELECT id_direccion FROM direcciones_usuario WHERE id_direccion = ? AND id_usuario = ?");
    $stmtCheck->execute([$id_direccion, $id_usuario]);
    if (!$stmtCheck->fetch()) {
        die(json_encode(['error' => 'Dirección no encontrada']));
    }

    $stmt = $pdo->prepare("
        UPDATE direcciones_usuario
        SET alias = ?, direccion = ?, referencia = ?, es_favorita = ?
        WHERE id_direccion = ? AND id_usuario = ?
    ");
    $stmt->execute([$alias, $direccion, $referencia, $es_favorita, $id_direccion, $id_usuario]);
    // rowCount puede ser 0 si no hubo cambios reales — eso es OK
} else {
    // ── Agregar nueva dirección ─────────────────────────────────────────
    // Máximo 5 por usuario
    $stmtCnt = $pdo->prepare("SELECT COUNT(*) FROM direcciones_usuario WHERE id_usuario = ?");
    $stmtCnt->execute([$id_usuario]);
    $total = (int) $stmtCnt->fetchColumn();

    if ($total >= 5) {
        die(json_encode(['error' => 'Máximo 5 direcciones por cuenta. Elimina una para agregar otra.']));
    }

    $stmt = $pdo->prepare("
        INSERT INTO direcciones_usuario (id_usuario, alias, direccion, referencia, es_favorita)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$id_usuario, $alias, $direccion, $referencia, $es_favorita]);
    $id_direccion = (int) $pdo->lastInsertId();
}

echo json_encode([
    'ok'          => true,
    'id_direccion' => $id_direccion,
    'mensaje'     => 'Dirección guardada correctamente'
]);
