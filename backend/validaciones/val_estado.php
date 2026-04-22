<?php if (session_status() === PHP_SESSION_NONE){
    session_start();
}

if(!isset($_SESSION['id_usuario'])){
     if(!empty($_SERVER['HTTP_X_REQUESTED_WITH'])){
        http_response_code(401);
        die(json_encode(['error' => 'No autenticado']));
     }

     header('location: ./login.html');
     exit;
} 