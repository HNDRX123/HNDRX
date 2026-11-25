<?php

session_start();
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(["success" => true, "message" => "Sesión cerrada exitosamente."]);
    exit;
}

header("Location: ../vista/Login.html");
exit;