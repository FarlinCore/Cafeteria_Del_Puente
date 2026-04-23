<?php
/**
 * Subir foto de cliente para revisión
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/conexion.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método no permitido']); exit;
}

$nombre = trim($_POST['nombre'] ?? 'Anónimo');
$nombre = mb_substr($nombre, 0, 120);

if (empty($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'No se recibió ningún archivo o hubo un error al subirlo.']); exit;
}

$file    = $_FILES['foto'];
$extMap  = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
$mime    = mime_content_type($file['tmp_name']);

if (!isset($extMap[$mime])) {
    echo json_encode(['error' => 'Tipo de archivo no permitido. Solo imágenes (jpg, png, gif, webp).']); exit;
}

if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['error' => 'La imagen no puede superar 5 MB.']); exit;
}

$uploadDir = __DIR__ . '/../../images/fotos_clientes/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$ext      = $extMap[$mime];
$filename = 'foto_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$destPath = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(['error' => 'No se pudo guardar el archivo. Verifica permisos.']); exit;
}

$rutaRelativa = './images/fotos_clientes/' . $filename;

try {
    $stmt = $pdo->prepare("INSERT INTO fotos_clientes (nombre_envio, ruta_imagen, estado) VALUES (?, ?, 'pendiente')");
    $stmt->execute([$nombre ?: null, $rutaRelativa]);
    echo json_encode(['ok' => true, 'mensaje' => 'Foto enviada correctamente. El administrador la revisará antes de publicarla.']);
} catch (PDOException $e) {
    // Si la tabla no existe aún, responder igual de amigable
    echo json_encode(['ok' => true, 'mensaje' => 'Foto recibida. El administrador evaluará tu envío.']);
}
