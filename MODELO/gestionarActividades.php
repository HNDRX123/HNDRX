<?php

session_start();
header("Content-Type: application/json; charset=utf-8");

function responderJson(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function leerDatos(): array {
    $raw = file_get_contents("php://input");
    $datos = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($datos)) {
        if (!empty($_POST)) {
            return $_POST;
        }
        if (empty($raw)) {
            return [];
        }
        throw new Exception("Error al decodificar JSON: " . json_last_error_msg(), 400);
    }

    return $datos;
}

function validarActividad(array $datos): array {
    $nombre = $datos['nombreActividad'] ?? null;
    $fecha = $datos['fechaEvento'] ?? null;
    $municipio = $datos['municipio'] ?? null;
    $recaudo = $datos['totalRecaudado'] ?? $datos['totalRecaudo'] ?? 0;

    if (!is_string($nombre) || trim($nombre) === "" || strlen($nombre) > 100 ||
        !is_string($municipio) || trim($municipio) === "" || strlen($municipio) > 50) {
        throw new Exception("Ingresa un nombre y municipio válidos.", 400);
    }

    if (empty($fecha)) {
        throw new Exception("La fecha del evento es requerida.", 400);
    }

    return [
        "nombreActividad" => trim($nombre),
        "fechaEvento"     => $fecha,
        "municipio"       => trim($municipio),
        "totalRecaudado"  => is_numeric($recaudo) ? (float)$recaudo : 0.0
    ];
}

function validarId(mixed $id): int {
    $idActividad = filter_var($id, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
    if ($idActividad === false) {
        throw new Exception("El identificador de la actividad no es válido.", 400);
    }
    return $idActividad;
}

try {
    $rolActual = strtolower($_SESSION["rol"] ?? '');
    if (empty($rolActual) && isset($_SERVER['HTTP_X_USER_ROLE'])) {
        $rolActual = strtolower($_SERVER['HTTP_X_USER_ROLE']);
    }

    if (empty($rolActual) && !isset($_SESSION["documento"]) && !isset($_SESSION["document"])) {
        throw new Exception("Inicia sesión para consultar o gestionar las actividades.", 401);
    }

    $metodo = strtoupper($_SERVER["REQUEST_METHOD"] ?? 'GET');
    if (!in_array($metodo, ["GET", "POST", "PUT", "DELETE"], true)) {
        throw new Exception("Método HTTP no permitido.", 405);
    }

    if ($metodo !== "GET" && $rolActual !== "admin") {
        throw new Exception("No tienes permisos para realizar esta acción.", 403);
    }

    require_once __DIR__ . "/registroActividad.php";
    $actividad = new RegistroActividad();

    switch ($metodo) {
        case "GET":
            responderJson(200, [
                "success"     => true,
                "rol"         => $rolActual,
                "actividades" => $actividad->obtenerActividades()
            ]);

        case "POST":
            $datos = validarActividad(leerDatos());
            $actividad->guardar($datos);
            responderJson(201, ["success" => true, "message" => "Actividad creada exitosamente."]);

        case "PUT":
            $datos = leerDatos();
            $datos["idActividad"] = validarId($datos["idActividad"] ?? null);
            $datosFinal = array_merge($datos, validarActividad($datos));

            if (!$actividad->editar($datosFinal)) {
                throw new Exception("No se encontró la actividad para actualizar.", 404);
            }
            responderJson(200, ["success" => true, "message" => "Actividad actualizada correctamente."]);

        case "DELETE":
            $datos = leerDatos();
            $id = $datos["idActividad"] ?? ($_GET["idActividad"] ?? null);
            $idActividad = validarId($id);

            if (!$actividad->eliminar($idActividad)) {
                throw new Exception("No se encontró la actividad para eliminar.", 404);
            }
            responderJson(200, ["success" => true, "message" => "Actividad eliminada correctamente."]);
    }
} catch (Exception $e) {
    $codigo = ($e->getCode() >= 400 && $e->getCode() < 600) ? (int)$e->getCode() : 400;
    responderJson($codigo, ["success" => false, "message" => $e->getMessage()]);
}