<?php
/**
 * ===================================================
 * CERRAR SESIÓN
 * ===================================================
 * Destruye la sesión actual del usuario y limpia la cookie.
 * Responde en JSON (POST) o redirige al Login (GET).
 */
session_start();

// Vaciar todas las variables de sesión
$_SESSION = [];

// Eliminar la cookie de sesión del navegador
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}

// Destruir la sesión en el servidor
session_destroy();

$metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($metodo === 'POST') {
    // Respuesta JSON para peticiones AJAX
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(["success" => true, "message" => "Sesión cerrada exitosamente."]);
    exit;
} else {
    // Redirección directa si se accede por enlace GET
    header("Location: ../VISTA/Login.html");
    exit;
}
