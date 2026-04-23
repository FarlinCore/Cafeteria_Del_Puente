<?php
/**
 * Gestionar fotos de clientes desde el admin
 * Acciones: aprobar, rechazar, eliminar
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/conexion.php';
header('Content-Type: application/json');

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    echo json_encode(['error' => 'No autorizado']); exit;
}

$accion   = $_POST['accion']   ?? '';
$id_foto  = (int)($_POST['id_foto'] ?? 0);

if (!$id_foto) { echo json_encode(['error' => 'ID inválido']); exit; }

try {
    switch ($accion) {
        case 'aprobar':
            $pdo->prepare("UPDATE fotos_clientes SET estado = 'aprobada', fecha_revision = NOW() WHERE id_foto = ?")->execute([$id_foto]);
            echo json_encode(['ok' => true]);
            break;

        case 'rechazar':
            $pdo->prepare("UPDATE fotos_clientes SET estado = 'rechazada', fecha_revision = NOW() WHERE id_foto = ?")->execute([$id_foto]);
            echo json_encode(['ok' => true]);
            break;

        case 'eliminar':
            // Obtener ruta para borrar el archivo físico
            $stmt = $pdo->prepare("SELECT ruta_imagen FROM fotos_clientes WHERE id_foto = ?");
            $stmt->execute([$id_foto]);
            $foto = $stmt->fetch();
            if ($foto) {
                $rutaFisica = __DIR__ . '/../../' . ltrim($foto['ruta_imagen'], './');
                if (file_exists($rutaFisica)) {
                    unlink($rutaFisica);
                }
            }
            $pdo->prepare("DELETE FROM fotos_clientes WHERE id_foto = ?")->execute([$id_foto]);
            echo json_encode(['ok' => true]);
            break;

        default:
            echo json_encode(['error' => 'Acción desconocida']);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
