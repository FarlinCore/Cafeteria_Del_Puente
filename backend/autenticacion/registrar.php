<?php 

session_start();
require_once '../config/conexion.php';

$nombre   = trim($_POST['nombre']);
$apellido = trim($_POST['apellido']);
$usuario  = trim($_POST['usuario']);
$correo   = trim($_POST['email']);
$password = $_POST['password'];
$password2 = $_POST['password2'];

if (empty($nombre) || empty($apellido) || empty($usuario) || empty($correo) || empty($password)) {
    header('Location: ../../registro.html?error=campos_vacios');
    exit;
}

if ($password !== $password2) {
    header('Location: ../../registro.html?error=passwords_no_coinciden');
    exit;
}

if (strlen($password) < 8) {
    header('Location: ../../registro.html?error=password_corta');
    exit;
}

$stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE correo = ? OR usuario = ?");
$stmt->execute([$correo, $usuario]);
if ($stmt->fetch()) {
    header('Location: ../../registro.html?error=ya_existe');
    exit;
}
$password_hash = password_hash($password, PASSWORD_BCRYPT);
$stmt = $pdo->prepare("
    INSERT INTO usuarios (nombre, apellido, usuario, correo, contrasena, rol, activo)
    VALUES (?, ?, ?, ?, ?, 'usuario', 1)
");
$stmt->execute([$nombre, $apellido, $usuario, $correo, $password_hash]);
$id_nuevo = $pdo->lastInsertId();
$_SESSION['id_usuario'] = $id_nuevo;
$_SESSION['usuario']    = $usuario;
$_SESSION['nombre']     = $nombre;
$_SESSION['rol']        = 'usuario';
header('Location: ../../panel-usuario.php');
exit;