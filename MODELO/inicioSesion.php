<?php
/**
 * ===================================================
 * ENDPOINT - INICIO DE SESIÓN
 * ===================================================
 * Autentica al usuario verificando email y contraseña
 * contra la tabla `asociados`.
 *
 * Soporta dos modos de respuesta:
 *   - JSON (para peticiones AJAX desde login.js)
 *   - HTML con redirección (para envío directo de formulario)
 *
 * Si la contraseña estaba en texto plano, la migra a hash seguro.
 */
session_start();

// Detectar si la petición espera JSON
$contentType    = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
$accept         = $_SERVER['HTTP_ACCEPT'] ?? '';
$xRequestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

$isJsonRequest = (
    stripos($contentType, 'application/json') !== false ||
    stripos($accept, 'application/json') !== false ||
    strtolower($xRequestedWith) === 'xmlhttprequest'
);

try {
    // Solo aceptar POST
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        throw new Exception("Método HTTP no permitido. Debe usar POST.");
    }

    // ─── Leer datos de la petición ───
    $rawInput = file_get_contents('php://input');
    $datos = json_decode($rawInput, true);

    if ((!is_array($datos) || empty($datos)) && !empty($_POST)) {
        $datos = $_POST;
    }

    if (!is_array($datos)) {
        http_response_code(400);
        throw new Exception("Datos inválidos.");
    }

    // Extraer credenciales
    $email    = trim($datos['email'] ?? '');
    $password = (string)($datos['contrasena'] ?? $datos['password'] ?? '');

    if ($email === "" || $password === "") {
        http_response_code(400);
        throw new Exception("Email y contraseña son requeridos.");
    }

    // ─── Buscar asociado por email ───
    require_once __DIR__ . '/../CONFIG/conexion.php';
    $conexion = (new conexion())->conectar();

    $consulta = $conexion->prepare("SELECT * FROM asociados WHERE email = :email");
    $consulta->execute([':email' => $email]);
    $asociado = $consulta->fetch();

    if (!$asociado) {
        http_response_code(401);
        throw new Exception("Email o contraseña incorrectos.");
    }

    // ─── Verificar contraseña ───
    $dbPassword    = (string)($asociado['clave'] ?? $asociado['password'] ?? '');
    $passwordValida = false;

    if (!empty($dbPassword)) {
        if (password_verify($password, $dbPassword)) {
            // Contraseña hasheada verificada correctamente
            $passwordValida = true;
        } elseif ($password === $dbPassword) {
            // Contraseña en texto plano: validar y migrar a hash seguro
            $passwordValida = true;
            try {
                $colClave  = isset($asociado['clave']) ? 'clave' : 'password';
                $nuevoHash = password_hash($password, PASSWORD_DEFAULT);
                $conexion->prepare("UPDATE asociados SET {$colClave} = :hash WHERE documento = :doc")
                         ->execute([':hash' => $nuevoHash, ':doc' => $asociado['documento']]);
            } catch (\Throwable $th) {
                // No bloquear el login si falla la migración de contraseña
            }
        }
    }

    if (!$passwordValida) {
        http_response_code(401);
        throw new Exception("Email o contraseña incorrectos.");
    }

    // ─── Crear sesión del usuario ───
    $rol    = !empty($asociado['rol']) ? strtolower($asociado['rol']) : 'asociado';
    $nombre = trim(($asociado['nombres'] ?? '') . ' ' . ($asociado['apellidos'] ?? ''));

    $_SESSION["rol"]        = $rol;
    $_SESSION["documento"]  = $asociado['documento'];
    $_SESSION["document"]   = $asociado['documento'];
    $_SESSION["nombre"]     = $nombre;
    $_SESSION["email"]      = $asociado['email'];
    $_SESSION["idAsociado"] = $asociado['idAsociado'] ?? null;

    // ─── Responder según el tipo de petición ───
    if ($isJsonRequest) {
        // Respuesta JSON para AJAX
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode([
            "success"   => true,
            "rol"       => $rol,
            "nombre"    => $nombre,
            "email"     => $asociado['email'] ?? $email,
            "documento" => $asociado['documento'] ?? ''
        ]);
        exit;
    }

    // Respuesta HTML con redirección (envío directo de formulario)
    header("Content-Type: text/html; charset=utf-8");
    $docStr   = (string)($asociado['documento'] ?? '');
    $emailStr = (string)($asociado['email'] ?? $email);
    echo "<!DOCTYPE html>
<html lang='es'>
<head><meta charset='UTF-8'><title>Iniciando sesión...</title></head>
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
    <p>Iniciando sesión... Si no es redirigido, <a href='../VISTA/dashboard.html'>haga clic aquí</a>.</p>
</body>
</html>";
    exit;

} catch (PDOException $e) {
    if ($isJsonRequest) {
        http_response_code(500);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(["success" => false, "message" => "Error de base de datos: " . $e->getMessage()]);
    } else {
        echo "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body><script>alert('Error de base de datos'); window.location.href='../VISTA/Login.html';</script></body></html>";
    }
} catch (Exception $e) {
    if ($isJsonRequest) {
        if (http_response_code() === 200) http_response_code(400);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    } else {
        $msg = addslashes($e->getMessage());
        echo "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body><script>alert('$msg'); window.location.href='../VISTA/Login.html';</script></body></html>";
    }
}
