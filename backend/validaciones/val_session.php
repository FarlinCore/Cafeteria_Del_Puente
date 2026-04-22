<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (isset($_SESSION['id_usuario'])) {

    require_once '../config/conexion.php';

    // Contar items en carrito del usuario
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM carrito WHERE id_usuario = ?");
    $stmt->execute([$_SESSION['id_usuario']]);
    $cant_carrito = (int) $stmt->fetchColumn();

    echo json_encode([
        'logueado'     => true,
        'id'           => $_SESSION['id_usuario'],
        'usuario'      => $_SESSION['usuario'],
        'nombre'       => $_SESSION['nombre'] ?? '',
        'rol'          => $_SESSION['rol'],
        'cant_carrito' => $cant_carrito
    ]);

} else {
    echo json_encode([
        'logueado'     => false,
        'usuario'      => null,
        'rol'          => 'invitado',
        'cant_carrito' => 0
    ]);
}