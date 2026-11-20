<?php
/**
 * ===================================================
 * API REST - GESTIÓN DE ASOCIADOS
 * ===================================================
 * Endpoint CRUD para administrar los asociados del club.
 *
 * Métodos soportados:
 *   GET    → Listar todos los asociados (cualquier usuario autenticado)
 *   POST   → Registrar nuevo asociado (solo Admin)
 *   PUT    → Actualizar datos de un asociado (solo Admin)
 *   DELETE → Eliminar un asociado (solo Admin)
 */
session_start();
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . '/../CONFIG/conexion.php';

try {
    $conexion = (new conexion())->conectar();

    // ─── Autenticación: obtener rol desde sesión o header ───
    $rolActual = strtolower($_SESSION["rol"] ?? '');
    if (empty($rolActual) && isset($_SERVER['HTTP_X_USER_ROLE'])) {
        $rolActual = strtolower($_SERVER['HTTP_X_USER_ROLE']);
    }

    if (empty($rolActual) && !isset($_SESSION["documento"]) && !isset($_SESSION["document"])) {
        http_response_code(401);
        throw new Exception("Inicia sesión para consultar o gestionar los asociados.");
    }

    $metodo = strtoupper($_SERVER["REQUEST_METHOD"] ?? 'GET');
    $raw = file_get_contents("php://input");
    $datos = json_decode($raw, true) ?? $_POST;

    // Solo Admin puede crear, editar o eliminar
    if ($metodo !== "GET" && $rolActual !== 'admin') {
        http_response_code(403);
        throw new Exception("No tienes permisos de administrador para realizar esta acción.");
    }

    // ─── Procesar según método HTTP ───
    switch ($metodo) {

        // ─── LISTAR ASOCIADOS ───
        case "GET":
            $sql = "SELECT idAsociado, documento, nombres, apellidos, fechaNacimiento,
                           genero, direccionResidencia, ciudad, telefono, email, rol
                    FROM asociados ORDER BY idAsociado DESC";
            $asociados = $conexion->query($sql)->fetchAll();

            echo json_encode(["success" => true, "rol" => $rolActual, "asociados" => $asociados]);
            break;

        // ─── CREAR ASOCIADO ───
        case "POST":
            require_once __DIR__ . '/asociado.php';

            // Si no se proporcionó contraseña, usar el documento como clave por defecto
            if ((!isset($datos['password']) || trim((string)$datos['password']) === '') && !empty($datos['documento'])) {
                $doc = trim((string)$datos['documento']);
                $datos['password'] = strlen($doc) >= 8 ? $doc : str_pad($doc, 8, '0', STR_PAD_RIGHT);
            }

            $asoc = new asociado($datos);
            if ($asoc->guardar()) {
                http_response_code(201);
                echo json_encode(["success" => true, "message" => "Asociado registrado correctamente."]);
            } else {
                http_response_code(500);
                throw new Exception("Error al guardar el asociado.");
            }
            break;

        // ─── ACTUALIZAR ASOCIADO ───
        case "PUT":
            $id = filter_var($datos['idAsociado'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                http_response_code(400);
                throw new Exception("Identificador de asociado no válido.");
            }

            // Extraer y validar campos editables
            $nombres   = trim($datos['nombres'] ?? $datos['nombre'] ?? '');
            $apellidos = trim($datos['apellidos'] ?? $datos['apellido'] ?? '');
            $telefono  = trim($datos['telefono'] ?? '');
            $email     = trim($datos['email'] ?? '');
            $ciudad    = trim($datos['ciudad'] ?? '');
            $direccion = trim($datos['direccionResidencia'] ?? '');
            $rol       = (strtolower($datos['rol'] ?? '') === 'admin') ? 'Admin' : 'Asociado';

            if ($nombres === '' || $apellidos === '' || $email === '') {
                http_response_code(400);
                throw new Exception("Nombres, apellidos y correo son obligatorios.");
            }

            $sql = "UPDATE asociados
                    SET nombres = :nombres, apellidos = :apellidos, telefono = :telefono,
                        email = :email, ciudad = :ciudad, direccionResidencia = :direccion, rol = :rol
                    WHERE idAsociado = :id";
            $conexion->prepare($sql)->execute([
                ':nombres'   => $nombres,
                ':apellidos' => $apellidos,
                ':telefono'  => $telefono,
                ':email'     => $email,
                ':ciudad'    => $ciudad,
                ':direccion' => $direccion,
                ':rol'       => $rol,
                ':id'        => $id
            ]);

            echo json_encode(["success" => true, "message" => "Asociado actualizado correctamente."]);
            break;

        // ─── ELIMINAR ASOCIADO ───
        case "DELETE":
            $id = filter_var($datos['idAsociado'] ?? ($_GET['idAsociado'] ?? null), FILTER_VALIDATE_INT);
            if (!$id) {
                http_response_code(400);
                throw new Exception("Identificador de asociado no válido.");
            }

            $stmt = $conexion->prepare("DELETE FROM asociados WHERE idAsociado = :id");
            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(["success" => true, "message" => "Asociado eliminado correctamente."]);
            } else {
                http_response_code(404);
                throw new Exception("No se encontró el asociado o ya fue eliminado.");
            }
            break;

        default:
            http_response_code(405);
            throw new Exception("Método HTTP no permitido.");
    }

} catch (PDOException $e) {
    if (http_response_code() === 200) http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error de base de datos: " . $e->getMessage()]);
} catch (Exception $e) {
    if (http_response_code() === 200) http_response_code(400);
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
