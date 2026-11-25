<?php

header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . '/asociado.php';

function responderJson(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

try {
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new Exception("Método HTTP no permitido. Debe usar POST.", 405);
    }

    $raw = file_get_contents('php://input');
    $datos = json_decode($raw, true);
    if (!is_array($datos) || empty($datos)) {
        $datos = $_POST;
    }

    if (!is_array($datos)) {
        throw new Exception("Datos inválidos o vacíos.", 400);
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

    if ((!isset($datos['password']) || trim((string)$datos['password']) === '') && !empty($datos['documento'])) {
        $doc = trim((string)$datos['documento']);
        $claveDefecto = strlen($doc) >= 8 ? $doc : str_pad($doc, 8, '0', STR_PAD_RIGHT);
        $datos['password'] = $claveDefecto;
        $datos['confirmarPassword'] = $claveDefecto;
    }

    $camposRequeridos = [
        "documento", "nombres", "apellidos", "fechaNacimiento", "genero",
        "direccionResidencia", "ciudad", "telefono", "email", "password"
    ];
    foreach ($camposRequeridos as $campo) {
        if (!isset($datos[$campo]) || trim((string)$datos[$campo]) === '') {
            throw new Exception("Falta el campo obligatorio: " . $campo, 400);
        }
    }

    if (strlen((string)$datos['password']) < 8) {
        throw new Exception("La contraseña debe tener al menos 8 caracteres.", 400);
    }

    if (isset($datos['confirmarPassword']) && $datos['password'] !== $datos['confirmarPassword']) {
        throw new Exception("Las contraseñas no coinciden.", 400);
    }

    if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception("El correo electrónico no tiene un formato válido.", 400);
    }

    $asociado = new asociado($datos);
    if (!$asociado->guardar()) {
        responderJson(500, ["success" => false, "message" => "Error al guardar el asociado."]);
    }

    responderJson(201, ["success" => true, "message" => "Asociado guardado exitosamente."]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000' || (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062)) {
        responderJson(409, ["success" => false, "message" => "El documento o correo ya se encuentra registrado."]);
    }
    responderJson(500, ["success" => false, "message" => "Error en la base de datos: " . $e->getMessage()]);
} catch (Exception $e) {
    $codigo = ($e->getCode() >= 400 && $e->getCode() < 600) ? (int)$e->getCode() : 400;
    responderJson($codigo, ["success" => false, "message" => $e->getMessage()]);
}