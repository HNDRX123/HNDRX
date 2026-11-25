<?php

require_once __DIR__ . '/../config/conexion.php';

class RegistroActividad {
    private ?PDO $conexion = null;

    public function __construct() {}

    private function obtenerConexion(): PDO {
        if ($this->conexion === null) {
            $this->conexion = (new conexion())->conectar();
        }
        return $this->conexion;
    }

    private function detectarColumnaRecaudo(PDO $db): string {
        $columnas = $db->query("DESCRIBE actividades")->fetchAll(PDO::FETCH_COLUMN);
        return in_array('totalRecaudado', $columnas, true) ? 'totalRecaudado' : 'totalRecaudo';
    }

    public function obtenerActividades(): array {
        $db = $this->obtenerConexion();
        $consulta = $db->prepare("SELECT * FROM actividades ORDER BY fechaEvento ASC");
        $consulta->execute();
        return $consulta->fetchAll();
    }

    public function guardar(array $datos): bool {
        $db = $this->obtenerConexion();
        $colRecaudo = $this->detectarColumnaRecaudo($db);
        $recaudo = $datos['totalRecaudado'] ?? $datos['totalRecaudo'] ?? 0;

        $sql = "INSERT INTO actividades (nombreActividad, fechaEvento, municipio, {$colRecaudo})
                VALUES (:nombreActividad, :fechaEvento, :municipio, :recaudo)";

        return $db->prepare($sql)->execute([
            ':nombreActividad' => $datos['nombreActividad'],
            ':fechaEvento'     => $datos['fechaEvento'],
            ':municipio'       => $datos['municipio'],
            ':recaudo'         => $recaudo
        ]);
    }

    public function eliminar(int $id): bool {
        $db = $this->obtenerConexion();
        $consulta = $db->prepare("DELETE FROM actividades WHERE idActividad = :idActividad");
        $consulta->execute([':idActividad' => $id]);
        return $consulta->rowCount() > 0;
    }

    public function editar(array $datos): bool {
        $db = $this->obtenerConexion();
        $colRecaudo = $this->detectarColumnaRecaudo($db);
        $recaudo = $datos['totalRecaudado'] ?? $datos['totalRecaudo'] ?? 0;

        $sql = "UPDATE actividades
                SET nombreActividad = :nombreActividad,
                    fechaEvento = :fechaEvento,
                    municipio = :municipio,
                    {$colRecaudo} = :recaudo
                WHERE idActividad = :idActividad";

        $consulta = $db->prepare($sql);
        $ok = $consulta->execute([
            ':nombreActividad' => $datos['nombreActividad'],
            ':fechaEvento'     => $datos['fechaEvento'],
            ':municipio'       => $datos['municipio'],
            ':recaudo'         => $recaudo,
            ':idActividad'     => $datos['idActividad']
        ]);

        if (!$ok) {
            return false;
        }
        if ($consulta->rowCount() > 0) {
            return true;
        }

        $verificar = $db->prepare("SELECT 1 FROM actividades WHERE idActividad = :idActividad");
        $verificar->execute([':idActividad' => $datos['idActividad']]);
        return (bool)$verificar->fetchColumn();
    }
}