<?php
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . '/../MODELO/asociado.php';

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        throw new Exception("Método HTTP no permitido. Debe usar POST.");
    }

    $json = file_get_contents('php://input');
    $datos = json_decode($json, true);


    if ((!is_array($datos) || empty($datos)) && !empty($_POST)) {
        $datos = $_POST;
    }

    if (!is_array($datos)) {
        http_response_code(400);
        throw new Exception("Datos inválidos o vacíos.");
    }

  
    if (isset($datos['nombre']) && !isset($datos['nombres'])) {
        $datos['nombres'] = $datos['nombre'];
    }
    if (isset($datos['apellido']) && !isset($datos['apellidos'])) {
        $datos['apellidos'] = $datos['apellido'];
    }
    if (isset($datos['direccionResidencial']) && !isset($datos['direccionResidencia'])) {
        $datos['direccionResidencia'] = $datos['direccionResidencial'];
    }
    if (isset($datos['genero']) && $datos['genero'] === 'Otros') {
        $datos['genero'] = 'Otro';
    }

    $camposRequeridos = ["documento", "nombres", "apellidos", "fechaNacimiento", "genero", "direccionResidencia", "ciudad", "telefono", "email", "password"];
    foreach ($camposRequeridos as $campo) {
        if (!isset($datos[$campo]) || trim((string)$datos[$campo]) === '') {
            http_response_code(400);
            throw new Exception("Falta el campo obligatorio: " . $campo);
        }
    }


    if (strlen((string)$datos['password']) < 8) {
        http_response_code(400);
        throw new Exception("La contraseña debe tener al menos 8 caracteres.");
    }


    if (isset($datos['confirmarPassword']) && $datos['password'] !== $datos['confirmarPassword']) {
        http_response_code(400);
        throw new Exception("Las contraseñas no coinciden.");
    }

    if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        throw new Exception("El correo electrónico no tiene un formato válido.");
    }

    $asociado = new asociado($datos);
    $resultado = $asociado->guardar();

    if ($resultado) {
        http_response_code(201);
        echo json_encode([
            "success" => true,
            "mensaje" => "Asociado guardado exitosamente.",
            "message" => "Asociado guardado exitosamente."
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "mensaje" => "Error al guardar el asociado.",
            "message" => "Error al guardar el asociado."
        ]);
    }

} catch (PDOException $e) {
    if ($e->getCode() === '23000' || (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062)) {
        http_response_code(409);
        echo json_encode([
            "success" => false,
            "mensaje" => "El documento o correo ya se encuentra registrado.",
            "message" => "El documento o correo ya se encuentra registrado."
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "mensaje" => "Error en la base de datos: " . $e->getMessage(),
            "message" => "Error en la base de datos: " . $e->getMessage()
        ]);
    }
} catch (Exception $e) {
    if (http_response_code() === 200) {
        http_response_code(400);
    }
    echo json_encode([
        "success" => false,
        "mensaje" => $e->getMessage(),
        "message" => $e->getMessage()
    ]);
}
