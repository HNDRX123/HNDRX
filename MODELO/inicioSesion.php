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

function responderJson(int $status, array $payload): never {
    http_response_code($status);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($payload);
    exit;
}

function responderRedireccion(string $mensaje, string $url): never {
    header("Content-Type: text/html; charset=utf-8");
    $msgEscapado = json_encode($mensaje);
    $urlEscapada = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'><title>Redirigiendo...</title></head><body><script>alert({$msgEscapado}); window.location.href='{$urlEscapada}';</script></body></html>";
    exit;
}

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new Exception("Método HTTP no permitido. Debe usar POST.", 405);
    }

    $rawInput = file_get_contents('php://input');
    $datos = json_decode($rawInput, true);

    if ((!is_array($datos) || empty($datos)) && !empty($_POST)) {
        $datos = $_POST;
    }

    if (!is_array($datos)) {
        throw new Exception("Datos inválidos.", 400);
    }

    $email = trim($datos['email'] ?? '');
    $password = (string)($datos['contrasena'] ?? $datos['password'] ?? '');

    if ($email === "" || $password === "") {
        throw new Exception("Email y contraseña son requeridos.", 400);
    }

    require_once __DIR__ . '/../config/conexion.php';
    $conexion = (new conexion())->conectar();

    $consulta = $conexion->prepare("SELECT * FROM asociados WHERE email = :email");
    $consulta->execute([':email' => $email]);
    $asociado = $consulta->fetch();

    if (!$asociado) {
        throw new Exception("Email o contraseña incorrectos.", 401);
    }

    $dbPassword = (string)($asociado['clave'] ?? $asociado['password'] ?? '');
    $passwordValida = false;

    if (!empty($dbPassword)) {
        if (password_verify($password, $dbPassword)) {
            $passwordValida = true;
        } elseif ($password === $dbPassword) {
            $passwordValida = true;
            try {
                $colClave = isset($asociado['clave']) ? 'clave' : 'password';
                $nuevoHash = password_hash($password, PASSWORD_DEFAULT);
                $conexion->prepare("UPDATE asociados SET {$colClave} = :hash WHERE documento = :doc")
                         ->execute([':hash' => $nuevoHash, ':doc' => $asociado['documento']]);
            } catch (\Throwable $th) {
            }
        }
    }

    if (!$passwordValida) {
        throw new Exception("Email o contraseña incorrectos.", 401);
    }

    session_regenerate_id(true);

    $rol = !empty($asociado['rol']) ? strtolower($asociado['rol']) : 'asociado';
    $nombre = trim(($asociado['nombres'] ?? '') . ' ' . ($asociado['apellidos'] ?? ''));
    $docStr = (string)($asociado['documento'] ?? '');
    $emailStr = (string)($asociado['email'] ?? $email);

    $_SESSION["rol"] = $rol;
    $_SESSION["documento"] = $docStr;
    $_SESSION["document"] = $docStr;
    $_SESSION["nombre"] = $nombre;
    $_SESSION["email"] = $emailStr;
    $_SESSION["idAsociado"] = $asociado['idAsociado'] ?? null;

    if ($isJsonRequest) {
        responderJson(200, [
            "success"   => true,
            "rol"       => $rol,
            "nombre"    => $nombre,
            "email"     => $emailStr,
            "documento" => $docStr
        ]);
    }

    header("Content-Type: text/html; charset=utf-8");
    $rolJson = json_encode($rol);
    $nombreJson = json_encode($nombre);
    $emailJson = json_encode($emailStr);
    $docJson = json_encode($docStr);

    echo "<!DOCTYPE html>
<html lang='es'>
<head><meta charset='UTF-8'><title>Iniciando sesión...</title></head>
<body>
    <script>
        localStorage.setItem('logueado', 'true');
        localStorage.setItem('rol', {$rolJson});
        localStorage.setItem('nombre', {$nombreJson});
        localStorage.setItem('email', {$emailJson});
        localStorage.setItem('documento', {$docJson});
        window.location.href = '../vista/dashboard.html';
    </script>
    <p>Iniciando sesión... Si no es redirigido, <a href='../vista/dashboard.html'>haga clic aquí</a>.</p>
</body>
</html>";
    exit;

} catch (PDOException $e) {
    if ($isJsonRequest) {
        responderJson(500, ["success" => false, "message" => "Error de base de datos: " . $e->getMessage()]);
    }
    responderRedireccion("Error de base de datos", "../vista/Login.html");
} catch (Exception $e) {
    $codigo = ($e->getCode() >= 400 && $e->getCode() < 600) ? (int)$e->getCode() : 400;
    if ($isJsonRequest) {
        responderJson($codigo, ["success" => false, "message" => $e->getMessage()]);
    }
    responderRedireccion($e->getMessage(), "../vista/Login.html");
}