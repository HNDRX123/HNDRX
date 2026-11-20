<?php
/**
 * ===================================================
 * API REST - GESTIÓN DE PAGOS Y CUOTAS
 * ===================================================
 * Endpoint CRUD para administrar pagos de cuotas y abonos a préstamos.
 *
 * Métodos soportados:
 *   GET    → Listar pagos (Admin: todos | Asociado: solo los propios)
 *   POST   → Registrar nuevo pago (solo Admin)
 *   PUT    → Actualizar un pago existente (solo Admin)
 *   DELETE → Eliminar un pago (solo Admin)
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
        throw new Exception("Inicia sesión para consultar o gestionar pagos.");
    }

    $metodo = strtoupper($_SERVER["REQUEST_METHOD"] ?? 'GET');
    $raw = file_get_contents("php://input");
    $datos = json_decode($raw, true) ?? $_POST;

    // Solo Admin puede modificar pagos
    if ($metodo !== "GET" && $rolActual !== 'admin') {
        http_response_code(403);
        throw new Exception("No tienes permisos de administrador para realizar esta acción.");
    }

    // ─── Procesar según método HTTP ───
    switch ($metodo) {

        // ─── LISTAR PAGOS ───
        case "GET":
            $sqlBase = "SELECT pc.idPago, pc.valorCuota, pc.fechaPago, pc.idPrestamo,
                               p.valorPrestamo, a.documento, a.nombres, a.apellidos
                        FROM pagos_cuotas pc
                        %s JOIN prestamos p ON pc.idPrestamo = p.idPrestamo
                        %s JOIN asociados a ON p.idAsociado = a.idAsociado
                        %s ORDER BY pc.idPago DESC";

            if ($rolActual === 'admin') {
                // Admin ve todos los pagos (LEFT JOIN para incluir huérfanos)
                $sql = sprintf($sqlBase, 'LEFT', 'LEFT', '');
                $stmt = $conexion->query($sql);
            } else {
                // Asociado ve solo sus propios pagos
                $sql = sprintf($sqlBase, 'INNER', 'INNER', 'WHERE a.documento = :doc');
                $stmt = $conexion->prepare($sql);
                $stmt->execute([':doc' => $docSesion]);
            }

            echo json_encode(["success" => true, "rol" => $rolActual, "pagos" => $stmt->fetchAll()]);
            break;

        // ─── REGISTRAR PAGO ───
        case "POST":
            $monto     = filter_var($datos['valorCuota'] ?? $datos['monto'] ?? 0, FILTER_VALIDATE_FLOAT);
            $fecha     = !empty($datos['fechaPago']) ? $datos['fechaPago'] : date('Y-m-d');
            $idPrestamo = filter_var($datos['idPrestamo'] ?? $datos['numeroPrestamo'] ?? 0, FILTER_VALIDATE_INT);
            $documento = trim((string)($datos['documentoAsociado'] ?? $datos['documento'] ?? $docSesion));

            if (!$monto || $monto <= 0) {
                http_response_code(400);
                throw new Exception("El valor del pago debe ser mayor a 0.");
            }

            // Si no hay idPrestamo, buscar el préstamo activo más reciente del asociado
            if (!$idPrestamo && $documento) {
                $buscaPrestamo = $conexion->prepare("
                    SELECT p.idPrestamo FROM prestamos p
                    INNER JOIN asociados a ON p.idAsociado = a.idAsociado
                    WHERE a.documento = :doc ORDER BY p.idPrestamo DESC LIMIT 1");
                $buscaPrestamo->execute([':doc' => $documento]);
                $prestamoEncontrado = $buscaPrestamo->fetch();
                if ($prestamoEncontrado) $idPrestamo = (int)$prestamoEncontrado['idPrestamo'];
            }

            // Si aún no hay préstamo, vincular al más reciente de la tabla
            if (!$idPrestamo) {
                $primerPrestamo = $conexion->query("SELECT idPrestamo FROM prestamos ORDER BY idPrestamo DESC LIMIT 1")->fetch();
                if ($primerPrestamo) {
                    $idPrestamo = (int)$primerPrestamo['idPrestamo'];
                } else {
                    // Tabla vacía: crear un préstamo base para poder asentar el abono
                    $buscaAsoc = $conexion->query("SELECT idAsociado FROM asociados ORDER BY idAsociado ASC LIMIT 1")->fetch();
                    if ($buscaAsoc) {
                        $creaP = $conexion->prepare("INSERT INTO prestamos (valorPrestamo, tasaInteres, numeroCuotas, fechaPrestamo, idAsociado) VALUES (:v, 1.2, 12, :f, :a)");
                        $creaP->execute([':v' => $monto * 5, ':f' => $fecha, ':a' => $buscaAsoc['idAsociado']]);
                        $idPrestamo = (int)$conexion->lastInsertId();
                    } else {
                        http_response_code(400);
                        throw new Exception("Debe registrar un asociado antes de asentar pagos.");
                    }
                }
            }

            // Insertar el pago
            $stmt = $conexion->prepare("INSERT INTO pagos_cuotas (valorCuota, fechaPago, idPrestamo) VALUES (:valor, :fecha, :idPrestamo)");
            $stmt->execute([':valor' => $monto, ':fecha' => $fecha, ':idPrestamo' => $idPrestamo]);

            http_response_code(201);
            echo json_encode(["success" => true, "message" => "Pago registrado exitosamente.", "idPago" => $conexion->lastInsertId()]);
            break;

        // ─── ACTUALIZAR PAGO ───
        case "PUT":
            $id = filter_var($datos['idPago'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                http_response_code(400);
                throw new Exception("Identificador de pago no válido.");
            }

            $monto = filter_var($datos['valorCuota'] ?? 0, FILTER_VALIDATE_FLOAT);
            $fecha = $datos['fechaPago'] ?? date('Y-m-d');

            $conexion->prepare("UPDATE pagos_cuotas SET valorCuota = :valor, fechaPago = :fecha WHERE idPago = :id")
                     ->execute([':valor' => $monto, ':fecha' => $fecha, ':id' => $id]);

            echo json_encode(["success" => true, "message" => "Pago actualizado exitosamente."]);
            break;

        // ─── ELIMINAR PAGO ───
        case "DELETE":
            $id = filter_var($datos['idPago'] ?? ($_GET['idPago'] ?? null), FILTER_VALIDATE_INT);
            if (!$id) {
                http_response_code(400);
                throw new Exception("Identificador de pago no válido.");
            }

            $stmt = $conexion->prepare("DELETE FROM pagos_cuotas WHERE idPago = :id");
            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(["success" => true, "message" => "Pago eliminado exitosamente."]);
            } else {
                http_response_code(404);
                throw new Exception("No se encontró el registro de pago o ya fue eliminado.");
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
