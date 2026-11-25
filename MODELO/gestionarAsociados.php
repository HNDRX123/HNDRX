<?php

session_start();
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . '/../config/conexion.php';

function responderJson(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function clavePorDefecto(string $documento): string {
    $doc = trim($documento);
    return strlen($doc) >= 8 ? $doc : str_pad($doc, 8, '0', STR_PAD_RIGHT);
}

try {
    $conexion = (new conexion())->conectar();

    $rolActual = strtolower($_SESSION["rol"] ?? '');
    if (empty($rolActual) && isset($_SERVER['HTTP_X_USER_ROLE'])) {
        $rolActual = strtolower($_SERVER['HTTP_X_USER_ROLE']);
    }

    if (empty($rolActual) && !isset($_SESSION["documento"]) && !isset($_SESSION["document"])) {
        throw new Exception("Inicia sesión para consultar o gestionar los asociados.", 401);
    }

    $metodo = strtoupper($_SERVER["REQUEST_METHOD"] ?? 'GET');
    $raw = file_get_contents("php://input");
    $datos = json_decode($raw, true);
    if (!is_array($datos) || empty($datos)) {
        $datos = $_POST;
    }

    if ($metodo !== "GET" && $rolActual !== 'admin') {
        throw new Exception("No tienes permisos de administrador para realizar esta acción.", 403);
    }

    switch ($metodo) {
        case "GET":
            $sql = "SELECT idAsociado, documento, nombres, apellidos, fechaNacimiento,
                           genero, direccionResidencia, ciudad, telefono, email, rol
                    FROM asociados ORDER BY idAsociado DESC";
            $asociados = $conexion->query($sql)->fetchAll();
            responderJson(200, ["success" => true, "rol" => $rolActual, "asociados" => $asociados]);

        case "POST":
            require_once __DIR__ . '/asociado.php';
            if ((!isset($datos['password']) || trim((string)$datos['password']) === '') && !empty($datos['documento'])) {
                $datos['password'] = clavePorDefecto((string)$datos['documento']);
            }

            $asoc = new asociado($datos);
            if (!$asoc->guardar()) {
                throw new Exception("Error al guardar el asociado.", 500);
            }
            responderJson(201, ["success" => true, "message" => "Asociado registrado correctamente."]);

        case "PUT":
            $id = filter_var($datos['idAsociado'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new Exception("Identificador de asociado no válido.", 400);
            }

            $nombres = trim($datos['nombres'] ?? $datos['nombre'] ?? '');
            $apellidos = trim($datos['apellidos'] ?? $datos['apellido'] ?? '');
            $telefono = trim($datos['telefono'] ?? '');
            $email = trim($datos['email'] ?? '');
            $ciudad = trim($datos['ciudad'] ?? '');
            $direccion = trim($datos['direccionResidencia'] ?? '');
            $rol = (strtolower($datos['rol'] ?? '') === 'admin') ? 'Admin' : 'Asociado';

            if ($nombres === '' || $apellidos === '' || $email === '') {
                throw new Exception("Nombres, apellidos y correo son obligatorios.", 400);
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

            responderJson(200, ["success" => true, "message" => "Asociado actualizado correctamente."]);

        case "DELETE":
            $id = filter_var($datos['idAsociado'] ?? ($_GET['idAsociado'] ?? null), FILTER_VALIDATE_INT);
            if (!$id) {
                throw new Exception("Identificador de asociado no válido.", 400);
            }

            $stmt = $conexion->prepare("DELETE FROM asociados WHERE idAsociado = :id");
            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount() <= 0) {
                throw new Exception("No se encontró el asociado o ya fue eliminado.", 404);
            }

            responderJson(200, ["success" => true, "message" => "Asociado eliminado correctamente."]);

        default:
            throw new Exception("Método HTTP no permitido.", 405);
    }
} catch (PDOException $e) {
    responderJson(500, ["success" => false, "message" => "Error de base de datos: " . $e->getMessage()]);
} catch (Exception $e) {
    $codigo = ($e->getCode() >= 400 && $e->getCode() < 600) ? (int)$e->getCode() : 400;
    responderJson($codigo, ["success" => false, "message" => $e->getMessage()]);
}