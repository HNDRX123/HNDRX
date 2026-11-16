<?php
session_start();

$contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
$xRequestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

$isJsonRequest = (
    stripos($contentType, 'application/json') !== false ||
    stripos($accept, 'application/json') !== false ||
    strtolower($xRequestedWith) === 'xmlhttprequest'
);

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        throw new Exception("Método HTTP no permitido. Debe usar POST.");
    }

    $rawInput = file_get_contents('php://input');
    $datos = json_decode($rawInput, true);

    if ((!is_array($datos) || empty($datos)) && !empty($_POST)) {
        $datos = $_POST;
    }

    if (!is_array($datos)) {
        http_response_code(400);
        throw new Exception("Datos inválidos.");
    }

    $email = trim($datos['email'] ?? '');
    $password = (string)($datos['contrasena'] ?? $datos['password'] ?? '');

    if ($email === "" || $password === "") {
        http_response_code(400);
        throw new Exception("Email y contraseña son requeridos.");
    }

    require_once __DIR__ . '/../CONFIG/conexion.php';
    
    $conexion = (new conexion())->conectar();

    $consulta = $conexion->prepare("SELECT * FROM asociados WHERE email = :email");
    $consulta->execute([':email' => $email]);
    $asociado = $consulta->fetch();

    if (!$asociado) {
        http_response_code(401);
        throw new Exception("Email o contraseña incorrectos.");
    }

    $dbPassword = (string)($asociado['password'] ?? '');
    $passwordValida = false;

    if (!empty($dbPassword)) {
        if (password_verify($password, $dbPassword)) {
            $passwordValida = true;
        } elseif ($password === $dbPassword) {
            $passwordValida = true;
            // Migrar a hash seguro si estaba en texto plano
            try {
                $nuevoHash = password_hash($password, PASSWORD_DEFAULT);
                $update = $conexion->prepare("UPDATE asociados SET password = :hash WHERE documento = :doc");
                $update->execute([':hash' => $nuevoHash, ':doc' => $asociado['documento']]);
            } catch (\Throwable $th) {
                // No bloquear el login si falla la actualización
            }
        }
    }

    if (!$passwordValida) {
        http_response_code(401);
        throw new Exception("Email o contraseña incorrectos.");
    }

    $rol = !empty($asociado['rol']) ? strtolower($asociado['rol']) : 'asociado';
    $nombre = trim(($asociado['nombres'] ?? '') . ' ' . ($asociado['apellidos'] ?? ''));

    $_SESSION["rol"] = $rol;
    $_SESSION["documento"] = $asociado['documento'];
    $_SESSION["nombre"] = $nombre;
    $_SESSION["email"] = $asociado['email'];

    if ($isJsonRequest) {
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode([
            "success" => true,
            "rol" => $rol,
            "nombre" => $nombre,
            "email" => $asociado['email'] ?? $email,
            "documento" => $asociado['documento'] ?? ''
        ]);
        exit;
    }

    // Si fue envío tradicional por formulario HTML directo
    header("Content-Type: text/html; charset=utf-8");
    $docStr = (string)($asociado['documento'] ?? '');
    $emailStr = (string)($asociado['email'] ?? $email);
    echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <title>Iniciando sesión...</title>
</head>
<body>
    <script>
        localStorage.setItem('logeado', 'true');
        localStorage.setItem('logueado', 'true');
        localStorage.setItem('rol', " . json_encode($rol) . ");
        localStorage.setItem('nombre', " . json_encode($nombre) . ");
        localStorage.setItem('email', " . json_encode($emailStr) . ");
        localStorage.setItem('documento', " . json_encode($docStr) . ");
        window.location.href = '../VISTA/dashboard.html';
    </script>
    <p>Iniciando sesión... Si no es redirigido automáticamente, <a href='../VISTA/dashboard.html'>haga clic aquí</a>.</p>
</body>
</html>";
    exit;

} catch (PDOException $e) {
    if ($isJsonRequest) {
        http_response_code(500);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode([
            "success" => false,
            "message" => "Error de base de datos: " . $e->getMessage(),
            "mensaje" => "Error de base de datos: " . $e->getMessage()
        ]);
    } else {
        echo "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body><script>alert('Error de base de datos'); window.location.href='../VISTA/Login.html';</script></body></html>";
    }
} catch (Exception $e) {
    if ($isJsonRequest) {
        if (http_response_code() === 200) {
            http_response_code(400);
        }
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode([
            "success" => false,
            "message" => $e->getMessage(),
            "mensaje" => $e->getMessage()
        ]);
    } else {
        $msg = addslashes($e->getMessage());
        echo "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body><script>alert('$msg'); window.location.href='../VISTA/Login.html';</script></body></html>";
    }
}
