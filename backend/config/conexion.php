<?php
$host = 'localhost';
$base_datos = 'cafeteria_el_puente';
$user = 'root';
$pass    = '';
$charset = 'utf8mb4';

// CONEXION
$conexion = "mysql:host=$host;dbname=$base_datos;charset=$charset";

// OPCIONES DE COMO SE COMPORTARA

$opciones = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // si algo falla, PHP te dice exactamente qué salió mal
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, //  los datos se recuperan como $fila['nombre'] en vez de $fila[0] 
    PDO::ATTR_EMULATE_PREPARES   => false, //  activa la protección real contra inyección SQL
];

// CREAR CONEXION
try {
    $pdo = new PDO($conexion, $user, $pass, $opciones);
} catch (PDOException $e) {
    die(json_encode(['error' => 'Error de conexión: ' . $e->getMessage()]));
}
