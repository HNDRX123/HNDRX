<?php
/**
 * ===================================================
 * ENDPOINT - REGISTRO PÚBLICO DE ASOCIADO
 * ===================================================
 * Recibe los datos del formulario de registro (Registro.html)
 * y guarda un nuevo asociado en la base de datos.
 *
 * Solo acepta peticiones POST.
 * Valida todos los campos obligatorios antes de guardar.
 * Si no se envía contraseña, usa el documento como clave por defecto.
 */
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . '/../MODELO/asociado.php';

try {
    // Solo aceptar POST
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        throw new Exception("Método HTTP no permitido. Debe usar POST.");
    }

    // ─── Leer datos de la petición (JSON o formulario) ───
    $json = file_get_contents('php://input');
    $datos = json_decode($json, true);

    if ((!is_array($datos) || empty($datos)) && !empty($_POST)) {
        $datos = $_POST;
    }

    if (!is_array($datos)) {
        http_response_code(400);
        throw new Exception("Datos inválidos o vacíos.");
    }

    // ─── Normalizar campos alternativos (capa de defensa extra) ───
    // La clase asociado también normaliza, pero aquí se hace antes de validar
    if (isset($datos['nombre']) && !isset($datos['nombres']))           $datos['nombres'] = $datos['nombre'];
    if (isset($datos['apellido']) && !isset($datos['apellidos']))       $datos['apellidos'] = $datos['apellido'];
    if (isset($datos['direccionResidencial']) && !isset($datos['direccionResidencia'])) $datos['direccionResidencia'] = $datos['direccionResidencial'];
    if (isset($datos['genero']) && $datos['genero'] === 'Otros')        $datos['genero'] = 'Otro';

    // ─── Contraseña por defecto: usar el número de documento ───
    if ((!isset($datos['password']) || trim((string)$datos['password']) === '') && !empty($datos['documento'])) {
        $doc = trim((string)$datos['documento']);
        $datos['password'] = strlen($doc) >= 8 ? $doc : str_pad($doc, 8, '0', STR_PAD_RIGHT);
        $datos['confirmarPassword'] = $datos['password'];
    }

    // ─── Validar campos obligatorios ───
    $camposRequeridos = ["documento", "nombres", "apellidos", "fechaNacimiento", "genero",
                         "direccionResidencia", "ciudad", "telefono", "email", "password"];
    foreach ($camposRequeridos as $campo) {
        if (!isset($datos[$campo]) || trim((string)$datos[$campo]) === '') {
            http_response_code(400);
            throw new Exception("Falta el campo obligatorio: " . $campo);
        }
    }

    // Validar longitud mínima de contraseña
    if (strlen((string)$datos['password']) < 8) {
        http_response_code(400);
        throw new Exception("La contraseña debe tener al menos 8 caracteres.");
    }

    // Validar que las contraseñas coincidan
    if (isset($datos['confirmarPassword']) && $datos['password'] !== $datos['confirmarPassword']) {
        http_response_code(400);
        throw new Exception("Las contraseñas no coinciden.");
    }

    // Validar formato de email
    if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        throw new Exception("El correo electrónico no tiene un formato válido.");
    }

    // ─── Guardar en la base de datos ───
    $asociado = new asociado($datos);

    if ($asociado->guardar()) {
        http_response_code(201);
        echo json_encode(["success" => true, "message" => "Asociado guardado exitosamente."]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error al guardar el asociado."]);
    }

} catch (PDOException $e) {
    // Error de clave duplicada (documento o email ya registrado)
    if ($e->getCode() === '23000' || (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062)) {
        http_response_code(409);
        echo json_encode(["success" => false, "message" => "El documento o correo ya se encuentra registrado."]);
    } else {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error en la base de datos: " . $e->getMessage()]);
    }
} catch (Exception $e) {
    if (http_response_code() === 200) http_response_code(400);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
