<?php

session_start();
header("Content-Type: application/json; charset=utf-8");
require_once __DIR__ . '/../config/conexion.php';


function responderJson(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload);
    exit;
}


function resolverIdPrestamo(PDO $conexion, ?int $idPrestamo, string $documento, float $monto, string $fecha): int {
    if ($idPrestamo && $idPrestamo > 0) {
        return $idPrestamo;
    }

    if ($documento !== '') {
        $buscaPrestamo = $conexion->prepare("
            SELECT p.idPrestamo
            FROM prestamos p
            INNER JOIN asociados a ON p.idAsociado = a.idAsociado
            WHERE a.documento = :doc
            ORDER BY p.idPrestamo DESC
            LIMIT 1
        ");
        $buscaPrestamo->execute([':doc' => $documento]);
        $prestamoEncontrado = $buscaPrestamo->fetch();
        if ($prestamoEncontrado) {
            return (int)$prestamoEncontrado['idPrestamo'];
        }
    }

    $primerPrestamo = $conexion->query("SELECT idPrestamo FROM prestamos ORDER BY idPrestamo DESC LIMIT 1")->fetch();
    if ($primerPrestamo) {
        return (int)$primerPrestamo['idPrestamo'];
    }

    $buscaAsoc = $conexion->query("SELECT idAsociado FROM asociados ORDER BY idAsociado ASC LIMIT 1")->fetch();
    if (!$buscaAsoc) {
        throw new Exception("Debe registrar un asociado antes de asentar pagos.", 400);
    }

    $creaPrestamo = $conexion->prepare("
        INSERT INTO prestamos (valorPrestamo, tasaInteres, numeroCuotas, fechaPrestamo, idAsociado)
        VALUES (:valor, 1.2, 12, :fecha, :idAsociado)
    ");
    $creaPrestamo->execute([
        ':valor'      => $monto * 5,
        ':fecha'      => $fecha,
        ':idAsociado' => $buscaAsoc['idAsociado']
    ]);

    return (int)$conexion->lastInsertId();
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
        throw new Exception("Inicia sesión para consultar o gestionar pagos.", 401);
    }

 normal
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
            $sqlBase = "SELECT pc.idPago, pc.valorCuota, pc.fechaPago, pc.idPrestamo,
                               p.valorPrestamo, a.documento, a.nombres, a.apellidos
                        FROM pagos_cuotas pc
                        %s JOIN prestamos p ON pc.idPrestamo = p.idPrestamo
                        %s JOIN asociados a ON p.idAsociado = a.idAsociado
                        %s ORDER BY pc.idPago DESC";

            if ($rolActual === 'admin') {
                $stmt = $conexion->query(sprintf($sqlBase, 'LEFT', 'LEFT', ''));
            } else {
                $stmt = $conexion->prepare(sprintf($sqlBase, 'INNER', 'INNER', 'WHERE a.documento = :doc'));
                $stmt->execute([':doc' => $docSesion]);
            }

            responderJson(200, ["success" => true, "rol" => $rolActual, "pagos" => $stmt->fetchAll()]);

        case "POST":
            $monto = filter_var($datos['valorCuota'] ?? $datos['monto'] ?? 0, FILTER_VALIDATE_FLOAT);
            $fecha = !empty($datos['fechaPago']) ? $datos['fechaPago'] : date('Y-m-d');
            $idPrestamoInput = filter_var($datos['idPrestamo'] ?? $datos['numeroPrestamo'] ?? null, FILTER_VALIDATE_INT) ?: null;
            $documento = trim((string)($datos['documentoAsociado'] ?? $datos['documento'] ?? $docSesion ?? ''));

            if (!$monto || $monto <= 0) {
                throw new Exception("El valor del pago debe ser mayor a 0.", 400);
            }

            $idPrestamo = resolverIdPrestamo($conexion, $idPrestamoInput, $documento, (float)$monto, $fecha);

            $stmt = $conexion->prepare("
                INSERT INTO pagos_cuotas (valorCuota, fechaPago, idPrestamo)
                VALUES (:valor, :fecha, :idPrestamo)
            ");
            $stmt->execute([
                ':valor'      => $monto,
                ':fecha'      => $fecha,
                ':idPrestamo' => $idPrestamo
            ]);

            responderJson(201, [
                "success" => true,
                "message" => "Pago registrado exitosamente.",
                "idPago"  => $conexion->lastInsertId()
            ]);

        case "PUT":
            $id = filter_var($datos['idPago'] ?? null, FILTER_VALIDATE_INT);
            if (!$id) {
                throw new Exception("Identificador de pago no válido.", 400);
            }

            $monto = filter_var($datos['valorCuota'] ?? 0, FILTER_VALIDATE_FLOAT);
            if (!$monto || $monto <= 0) {
                throw new Exception("El valor del pago debe ser mayor a 0.", 400);
            }
            $fecha = $datos['fechaPago'] ?? date('Y-m-d');

            $conexion->prepare("UPDATE pagos_cuotas SET valorCuota = :valor, fechaPago = :fecha WHERE idPago = :id")
                     ->execute([':valor' => $monto, ':fecha' => $fecha, ':id' => $id]);

            responderJson(200, ["success" => true, "message" => "Pago actualizado exitosamente."]);

        case "DELETE":
            $id = filter_var($datos['idPago'] ?? ($_GET['idPago'] ?? null), FILTER_VALIDATE_INT);
            if (!$id) {
                throw new Exception("Identificador de pago no válido.", 400);
            }

            $stmt = $conexion->prepare("DELETE FROM pagos_cuotas WHERE idPago = :id");
            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount() <= 0) {
                throw new Exception("No se encontró el registro de pago o ya fue eliminado.", 404);
            }

            responderJson(200, ["success" => true, "message" => "Pago eliminado exitosamente."]);

        default:
            throw new Exception("Método HTTP no permitido.", 405);
    }
} catch (PDOException $e) {
    responderJson(500, ["success" => false, "message" => "Error de base de datos: " . $e->getMessage()]);
} catch (Exception $e) {
    $codigo = ($e->getCode() >= 400 && $e->getCode() < 600) ? (int)$e->getCode() : 400;
    responderJson($codigo, ["success" => false, "message" => $e->getMessage()]);
}