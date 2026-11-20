<?php
/**
 * ===================================================
 * API REST - GESTIÓN DE ACTIVIDADES
 * ===================================================
 * Endpoint que maneja el CRUD completo de actividades/eventos.
 * 
 * Métodos soportados:
 *   GET    → Listar todas las actividades (cualquier usuario autenticado)
 *   POST   → Crear nueva actividad (solo Admin)
 *   PUT    → Actualizar actividad existente (solo Admin)
 *   DELETE → Eliminar actividad (solo Admin)
 * 
 * Responde siempre en JSON con formato: { success, message, ... }
 */
session_start();
header("Content-Type: application/json; charset=utf-8");

// ─── Funciones auxiliares ─────────────────────────────

/**
 * Lee los datos del cuerpo de la petición (JSON o POST).
 * @return array Datos decodificados
 * @throws Exception Si el JSON es inválido
 */
function leerDatos() {
    $raw = file_get_contents("php://input");
    $datos = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($datos)) {
        if (!empty($_POST)) return $_POST;
        if (empty($raw)) return [];
        http_response_code(400);
        throw new Exception("Error al decodificar JSON: " . json_last_error_msg());
    }
    return $datos;
}

/**
 * Valida y sanitiza los campos obligatorios de una actividad.
 * @param array $datos Datos crudos de la petición
 * @return array Datos validados y limpios
 * @throws Exception Si falta algún campo obligatorio
 */
function validarActividad($datos) {
    $nombre    = $datos['nombreActividad'] ?? null;
    $fecha     = $datos['fechaEvento'] ?? null;
    $municipio = $datos['municipio'] ?? null;
    $recaudo   = $datos['totalRecaudado'] ?? $datos['totalRecaudo'] ?? 0;

    if (!is_string($nombre) || trim($nombre) === "" || strlen($nombre) > 100 ||
        !is_string($municipio) || trim($municipio) === "" || strlen($municipio) > 50) {
        http_response_code(400);
        throw new Exception("Ingresa un nombre y municipio válidos.");
    }

    if (empty($fecha)) {
        http_response_code(400);
        throw new Exception("La fecha del evento es requerida.");
    }

    return [
        "nombreActividad" => trim($nombre),
        "fechaEvento"     => $fecha,
        "municipio"       => trim($municipio),
        "totalRecaudado"  => is_numeric($recaudo) ? (float)$recaudo : 0.0
    ];
}

/**
 * Valida que un ID sea un entero positivo válido.
 * @param mixed $id Valor a validar
 * @return int ID validado
 * @throws Exception Si el ID no es válido
 */
function validarId($id) {
    $idActividad = filter_var($id, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
    if ($idActividad === false) {
        http_response_code(400);
        throw new Exception("El identificador de la actividad no es válido.");
    }
    return $idActividad;
}

// ─── Lógica principal ─────────────────────────────────

try {
    // Obtener rol del usuario (sesión o header)
    $rolActual = strtolower($_SESSION["rol"] ?? '');
    if (empty($rolActual) && isset($_SERVER['HTTP_X_USER_ROLE'])) {
        $rolActual = strtolower($_SERVER['HTTP_X_USER_ROLE']);
    }

    // Verificar autenticación
    if (empty($rolActual) && !isset($_SESSION["documento"]) && !isset($_SESSION["document"])) {
        http_response_code(401);
        throw new Exception("Inicia sesión para consultar o gestionar las actividades.");
    }

    $metodo = strtoupper($_SERVER["REQUEST_METHOD"] ?? 'GET');

    if (!in_array($metodo, ["GET", "POST", "PUT", "DELETE"], true)) {
        http_response_code(405);
        throw new Exception("Método HTTP no permitido.");
    }

    // Solo el Admin puede modificar datos
    if ($metodo !== "GET" && $rolActual !== "admin") {
        http_response_code(403);
        throw new Exception("No tienes permisos para realizar esta acción.");
    }

    require_once __DIR__ . "/RegistroActividad.php";
    $actividad = new RegistroActividad();

    // ─── Procesar según método HTTP ───
    switch ($metodo) {

        case "GET":
            echo json_encode([
                "success"     => true,
                "rol"         => $rolActual,
                "actividades" => $actividad->obtenerActividades()
            ]);
            break;

        case "POST":
            $datos = validarActividad(leerDatos());
            $actividad->guardar($datos);
            http_response_code(201);
            echo json_encode(["success" => true, "message" => "Actividad creada exitosamente."]);
            break;

        case "PUT":
            $datos = leerDatos();
            $datos["idActividad"] = validarId($datos["idActividad"] ?? null);
            $datosValidados = validarActividad($datos);
            $datosFinal = array_merge($datos, $datosValidados);

            if (!$actividad->editar($datosFinal)) {
                http_response_code(404);
                throw new Exception("No se encontró la actividad para actualizar.");
            }
            echo json_encode(["success" => true, "message" => "Actividad actualizada correctamente."]);
            break;

        case "DELETE":
            $datos = leerDatos();
            $id = $datos["idActividad"] ?? ($_GET["idActividad"] ?? null);
            $idActividad = validarId($id);

            if (!$actividad->eliminar($idActividad)) {
                http_response_code(404);
                throw new Exception("No se encontró la actividad para eliminar.");
            }
            echo json_encode(["success" => true, "message" => "Actividad eliminada correctamente."]);
            break;
    }

} catch (Exception $e) {
    if (http_response_code() === 200) http_response_code(400);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
