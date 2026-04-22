<?php

if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/conexion.php';

// Solo acepta POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../login.html');
    exit;
}

$identificador = trim($_POST['identifier']);
$password      = $_POST['password'];

// Validaciones básicas
if (empty($identificador) || empty($password)) {
    header('Location: ../../login.html?error=campos_vacios');
    exit;
}

// Buscar por correo O por usuario
$stmt = $pdo->prepare("
    SELECT id_usuario, nombre, apellido, usuario, correo, contrasena, rol, activo
    FROM usuarios
    WHERE correo = ? OR usuario = ?
    LIMIT 1
");
$stmt->execute([$identificador, $identificador]);
$usuario = $stmt->fetch();

// Usuario no encontrado
if (!$usuario) {
    header('Location: ../../login.html?error=credenciales_invalidas');
    exit;
}

// Cuenta desactivada (activo es tinytext en la BD, se compara como string)
if ($usuario['activo'] !== '1' && $usuario['activo'] != 1) {
    header('Location: ../../login.html?error=cuenta_inactiva');
    exit;
}

// Verificar contraseña
if (!password_verify($password, $usuario['contrasena'])) {
    header('Location: ../../login.html?error=credenciales_invalidas');
    exit;
}

// Todo correcto — guardar sesión
$_SESSION['id_usuario'] = $usuario['id_usuario'];
$_SESSION['usuario']    = $usuario['usuario'];
$_SESSION['nombre']     = $usuario['nombre'];
$_SESSION['rol']        = $usuario['rol'];
session_regenerate_id(true); // Prevenir fijación de sesión

// Redirigir según rol
if ($usuario['rol'] === 'admin') {
    header('Location: ../../panel-admin.php');
} else {
    header('Location: ../../panel-usuario.php');
}
exit;
