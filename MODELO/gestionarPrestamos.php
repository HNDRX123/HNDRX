<?php

session_start();
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . '/../config/conexion.php';

function responderJson(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function obtenerIdAsociadoPorDocumento(PDO $conexion, string $documento): int {
    $stmt = $conexion->prepare("SELECT idAsociado FROM asociados WHERE documento = :doc");
    $stmt->execute([':doc' => $documento]);
    $asociado = $stmt->fetch();

    if (!$asociado) {
        throw new Exception("No se encontró ningún asociado con el documento: " . $documento, 404);
    }

    return (int)$asociado['idAsociado'];
}

try {
    $conexion = (new conexion())->conectar();

    $rolActual = strtolower($_SESSION["rol"] ?? '');
    if (empty($rolActual) && isset($_SERVER['HTTP_X_USER_ROLE'])) {
        $rolActual = strtolower($_SERVER['HTTP_X_USER_ROLE']);
    }

    $docSesion = $_SESSION["documento"] ?? ($_SESSION["document"] ?? null);
    if (empty($docSesion) && isset($_SERVER['HTTP_X_USER_DOCUMENT'])) {
        $docSesion = trim($_SERVER['HTTP_X_USER_DOCUMENT']);
    }

    if (empty($rolActual) && empty($docSesion)) {
        throw new Exception("Inicia sesión para consultar o gestionar préstamos.", 401);
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
            $sqlCampos = "SELECT p.idPrestamo, p.valorPrestamo, p.tasaInteres, p.numeroCuotas, p.fechaPrestamo,
                                 a.idAsociado, a.documento, a.nombres, a.apellidos, a.email
                          FROM prestamos p";

            if ($rolActual === 'admin') {
                $stmt = $conexion->query("$sqlCampos LEFT JOIN asociados a ON p.idAsociado = a.idAsociado ORDER BY p.idPrestamo DESC");
            } else {
                $stmt = $conexion->prepare("$sqlCampos INNER JOIN asociados a ON p.idAsociado = a.idAsociado WHERE a.documento = :doc ORDER BY p.idPrestamo DESC");
                $stmt->execute([':doc' => $docSesion]);
            }

            responderJson(200, ["success" => true, "rol" => $rolActual, "prestamos" => $stmt->fetchAll()]);

        case "POST":
            $documento = trim((string)($datos['documentoAsociado'] ?? $datos['documento'] ?? $docSesion ?? ''));
            $monto = filter_var($datos['valorPrestamo'] ?? $datos['montoSolicitado'] ?? 0, FILTER_VALIDATE_FLOAT);
            $tasa = filter_var($datos['tasaInteres'] ?? 1.2, FILTER_VALIDATE_FLOAT);
            $cuotas = filter_var($datos['numeroCuotas'] ?? $datos['plazoMeses'] ?? 12, FILTER_VALIDATE_INT);
            $fecha = !empty($datos['fechaPrestamo'])
                ? $datos['fechaPrestamo']
                : (!empty($datos['fechaSolicitud']) ? $datos['fechaSolicitud'] : date('Y-m-d'));

            if (!$monto || $monto <= 0) {
                throw new Exception("El monto del préstamo debe ser mayor a 0.", 400);
            }
            if ($documento === '') {
                throw new Exception("El documento del asociado es obligatorio.", 400);
            }

            $idAsociado = obtenerIdAsociadoPorDocumento($conexion, $documento);

            $stmt = $conexion->prepare("
                INSERT INTO prestamos (valorPrestamo, tasaInteres, numeroCuotas, fechaPrestamo, idAsociado)
                VALUES (:valor, :tasa, :cuotas, :fecha, :idAsoc)
            ");
            $stmt->execute([
                ':valor'  => $monto,
                ':tasa'   => $tasa,
                ':cuotas' => $cuotas,
                ':fecha'  => $fecha,
                ':idAsoc' => $idAsociado
            ]);

            responderJson(201, [
                "success"    => true,
                "message"    => "Préstamo registrado exitosamente.",
                "idPrestamo" => $conexion->lastInsertId()
            ]);

        case "PUT":
            $id = filter_var($datos['idPrestamo'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new Exception("Identificador de préstamo no válido.", 400);
            }

            $monto = filter_var($datos['valorPrestamo'] ?? 0, FILTER_VALIDATE_FLOAT);
            $tasa = filter_var($datos['tasaInteres'] ?? 0, FILTER_VALIDATE_FLOAT);
            $cuotas = filter_var($datos['numeroCuotas'] ?? 0, FILTER_VALIDATE_INT);
            $fecha = $datos['fechaPrestamo'] ?? date('Y-m-d');

            if (!$monto || $monto <= 0 || $tasa === false || !$cuotas || $cuotas <= 0) {
                throw new Exception("Verifique el monto, tasa y número de cuotas del préstamo.", 400);
            }

            $conexion->prepare("
                UPDATE prestamos
                SET valorPrestamo = :valor, tasaInteres = :tasa, numeroCuotas = :cuotas, fechaPrestamo = :fecha
                WHERE idPrestamo = :id
            ")->execute([
                ':valor'  => $monto,
                ':tasa'   => $tasa,
                ':cuotas' => $cuotas,
                ':fecha'  => $fecha,
                ':id'     => $id
            ]);

            responderJson(200, ["success" => true, "message" => "Préstamo actualizado exitosamente."]);

        case "DELETE":
            $id = filter_var($datos['idPrestamo'] ?? ($_GET['idPrestamo'] ?? null), FILTER_VALIDATE_INT);
            if (!$id) {
                throw new Exception("Identificador de préstamo no válido.", 400);
            }

            $stmt = $conexion->prepare("DELETE FROM prestamos WHERE idPrestamo = :id");
            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount() <= 0) {
                throw new Exception("No se encontró el préstamo o ya fue eliminado.", 404);
            }

            responderJson(200, ["success" => true, "message" => "Préstamo eliminado exitosamente."]);

        default:
            throw new Exception("Método HTTP no permitido.", 405);
    }
} catch (PDOException $e) {
    responderJson(500, ["success" => false, "message" => "Error de base de datos: " . $e->getMessage()]);
} catch (Exception $e) {
    $codigo = ($e->getCode() >= 400 && $e->getCode() < 600) ? (int)$e->getCode() : 400;
    responderJson($codigo, ["success" => false, "message" => $e->getMessage()]);
}