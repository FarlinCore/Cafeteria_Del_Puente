<?php
/**
 * Sistema de calificaciones de productos
 * Guarda una calificación por sesión por producto
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/conexion.php';
header('Content-Type: application/json');

$accion = $_GET['accion'] ?? $_POST['accion'] ?? 'obtener';

// ── Obtener promedios ────────────────────────────────────────────────────
if ($accion === 'obtener') {
    $ids = $_GET['ids'] ?? '';
    $idsArr = array_filter(array_map('trim', explode(',', $ids)));
    
    if (empty($idsArr)) {
        // Obtener todos
        try {
            $rows = $pdo->query("
                SELECT id_producto, AVG(estrellas) AS promedio, COUNT(*) AS total
                FROM calificaciones GROUP BY id_producto
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            $result = [];
            foreach ($rows as $r) {
                $result[$r['id_producto']] = [
                    'promedio' => round((float)$r['promedio'], 1),
                    'total'    => (int)$r['total']
                ];
            }
            echo json_encode(['ok' => true, 'calificaciones' => $result]);
        } catch (PDOException $e) {
            echo json_encode(['ok' => true, 'calificaciones' => []]);
        }
    } else {
        try {
            $placeholders = implode(',', array_fill(0, count($idsArr), '?'));
            $stmt = $pdo->prepare("
                SELECT id_producto, AVG(estrellas) AS promedio, COUNT(*) AS total
                FROM calificaciones WHERE id_producto IN ({$placeholders})
                GROUP BY id_producto
            ");
            $stmt->execute($idsArr);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $result = [];
            foreach ($rows as $r) {
                $result[$r['id_producto']] = [
                    'promedio' => round((float)$r['promedio'], 1),
                    'total'    => (int)$r['total']
                ];
            }
            echo json_encode(['ok' => true, 'calificaciones' => $result]);
        } catch (PDOException $e) {
            echo json_encode(['ok' => true, 'calificaciones' => []]);
        }
    }
    exit;
}

// ── Guardar calificación ─────────────────────────────────────────────────
if ($accion === 'calificar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_producto = trim($_POST['id_producto'] ?? '');
    $estrellas   = (int)($_POST['estrellas'] ?? 0);
    
    if (!$id_producto || $estrellas < 1 || $estrellas > 5) {
        echo json_encode(['error' => 'Datos inválidos']); exit;
    }
    
    // Usar ID de sesión como identificador anónimo
    $id_sesion = session_id() ?: (isset($_COOKIE['PHPSESSID']) ? $_COOKIE['PHPSESSID'] : 'anon_' . bin2hex(random_bytes(8)));
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO calificaciones (id_producto, id_sesion, estrellas)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE estrellas = VALUES(estrellas), fecha = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$id_producto, $id_sesion, $estrellas]);
        
        // Obtener nuevo promedio
        $avg = $pdo->prepare("
            SELECT AVG(estrellas) AS promedio, COUNT(*) AS total
            FROM calificaciones WHERE id_producto = ?
        ");
        $avg->execute([$id_producto]);
        $data = $avg->fetch();
        
        echo json_encode([
            'ok'       => true,
            'promedio' => round((float)$data['promedio'], 1),
            'total'    => (int)$data['total'],
            'mi_voto'  => $estrellas
        ]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['error' => 'Acción no reconocida']);
