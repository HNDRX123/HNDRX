<?php
/**
 * ===================================================
 * API REST - GESTIÓN DE PRÉSTAMOS
 * ===================================================
 * Endpoint CRUD para administrar préstamos/créditos de asociados.
 *
 * Métodos soportados:
 *   GET    → Listar préstamos (Admin: todos | Asociado: solo los propios)
 *   POST   → Registrar nuevo préstamo (solo Admin)
 *   PUT    → Actualizar un préstamo existente (solo Admin)
 *   DELETE → Eliminar un préstamo (solo Admin)
 */
session_start();
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . '/../CONFIG/conexion.php';

try {
    $conexion = (new conexion())->conectar();

    // ─── Autenticación ───
    $rolActual = strtolower($_SESSION["rol"] ?? '');
    if (empty($rolActual) && isset($_SERVER['HTTP_X_USER_ROLE'])) {
        $rolActual = strtolower($_SERVER['HTTP_X_USER_ROLE']);
    }

    $docSesion = $_SESSION["documento"] ?? ($_SESSION["document"] ?? null);
    if (empty($docSesion) && isset($_SERVER['HTTP_X_USER_DOCUMENT'])) {
        $docSesion = trim($_SERVER['HTTP_X_USER_DOCUMENT']);
    }

    if (empty($rolActual) && empty($docSesion)) {
        http_response_code(401);
        throw new Exception("Inicia sesión para consultar o gestionar préstamos.");
    }

    $metodo = strtoupper($_SERVER["REQUEST_METHOD"] ?? 'GET');
    $raw = file_get_contents("php://input");
    $datos = json_decode($raw, true) ?? $_POST;

    // Solo Admin puede modificar préstamos
    if ($metodo !== "GET" && $rolActual !== 'admin') {
        http_response_code(403);
        throw new Exception("No tienes permisos de administrador para realizar esta acción.");
    }

    // ─── Procesar según método HTTP ───
    switch ($metodo) {

        // ─── LISTAR PRÉSTAMOS ───
        case "GET":
            $sqlCampos = "SELECT p.idPrestamo, p.valorPrestamo, p.tasaInteres, p.numeroCuotas, p.fechaPrestamo,
                                 a.idAsociado, a.documento, a.nombres, a.apellidos, a.email
                          FROM prestamos p";

            if ($rolActual === 'admin') {
                $sql = "$sqlCampos LEFT JOIN asociados a ON p.idAsociado = a.idAsociado ORDER BY p.idPrestamo DESC";
                $stmt = $conexion->query($sql);
            } else {
                $sql = "$sqlCampos INNER JOIN asociados a ON p.idAsociado = a.idAsociado WHERE a.documento = :doc ORDER BY p.idPrestamo DESC";
                $stmt = $conexion->prepare($sql);
                $stmt->execute([':doc' => $docSesion]);
            }

            echo json_encode(["success" => true, "rol" => $rolActual, "prestamos" => $stmt->fetchAll()]);
            break;

        // ─── REGISTRAR PRÉSTAMO ───
        case "POST":
            $documento = trim((string)($datos['documentoAsociado'] ?? $datos['documento'] ?? $docSesion));
            $monto     = filter_var($datos['valorPrestamo'] ?? $datos['montoSolicitado'] ?? 0, FILTER_VALIDATE_FLOAT);
            $tasa      = filter_var($datos['tasaInteres'] ?? 1.2, FILTER_VALIDATE_FLOAT);
            $cuotas    = filter_var($datos['numeroCuotas'] ?? $datos['plazoMeses'] ?? 12, FILTER_VALIDATE_INT);
            $fecha     = !empty($datos['fechaPrestamo']) ? $datos['fechaPrestamo']
                       : (!empty($datos['fechaSolicitud']) ? $datos['fechaSolicitud'] : date('Y-m-d'));

            if (!$monto || $monto <= 0) {
                http_response_code(400);
                throw new Exception("El monto del préstamo debe ser mayor a 0.");
            }
            if (!$documento) {
                http_response_code(400);
                throw new Exception("El documento del asociado es obligatorio.");
            }

            // Buscar el idAsociado a partir del documento
            $buscaAsoc = $conexion->prepare("SELECT idAsociado FROM asociados WHERE documento = :doc");
            $buscaAsoc->execute([':doc' => $documento]);
            $asociado = $buscaAsoc->fetch();

            if (!$asociado) {
                http_response_code(404);
                throw new Exception("No se encontró ningún asociado con el documento: " . $documento);
            }

            // Insertar el préstamo
            $stmt = $conexion->prepare("INSERT INTO prestamos (valorPrestamo, tasaInteres, numeroCuotas, fechaPrestamo, idAsociado)
                                        VALUES (:valor, :tasa, :cuotas, :fecha, :idAsoc)");
            $stmt->execute([
                ':valor'  => $monto,
                ':tasa'   => $tasa,
                ':cuotas' => $cuotas,
                ':fecha'  => $fecha,
                ':idAsoc' => $asociado['idAsociado']
            ]);

            http_response_code(201);
            echo json_encode(["success" => true, "message" => "Préstamo registrado exitosamente.", "idPrestamo" => $conexion->lastInsertId()]);
            break;

        // ─── ACTUALIZAR PRÉSTAMO ───
        case "PUT":
            $id = filter_var($datos['idPrestamo'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                http_response_code(400);
                throw new Exception("Identificador de préstamo no válido.");
            }

            $monto  = filter_var($datos['valorPrestamo'] ?? 0, FILTER_VALIDATE_FLOAT);
            $tasa   = filter_var($datos['tasaInteres'] ?? 0, FILTER_VALIDATE_FLOAT);
            $cuotas = filter_var($datos['numeroCuotas'] ?? 0, FILTER_VALIDATE_INT);
            $fecha  = $datos['fechaPrestamo'] ?? date('Y-m-d');

            $conexion->prepare("UPDATE prestamos SET valorPrestamo = :valor, tasaInteres = :tasa, numeroCuotas = :cuotas, fechaPrestamo = :fecha WHERE idPrestamo = :id")
                     ->execute([':valor' => $monto, ':tasa' => $tasa, ':cuotas' => $cuotas, ':fecha' => $fecha, ':id' => $id]);

            echo json_encode(["success" => true, "message" => "Préstamo actualizado exitosamente."]);
            break;

        // ─── ELIMINAR PRÉSTAMO ───
        case "DELETE":
            $id = filter_var($datos['idPrestamo'] ?? ($_GET['idPrestamo'] ?? null), FILTER_VALIDATE_INT);
            if (!$id) {
                http_response_code(400);
                throw new Exception("Identificador de préstamo no válido.");
            }

            $stmt = $conexion->prepare("DELETE FROM prestamos WHERE idPrestamo = :id");
            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(["success" => true, "message" => "Préstamo eliminado exitosamente."]);
            } else {
                http_response_code(404);
                throw new Exception("No se encontró el préstamo o ya fue eliminado.");
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
