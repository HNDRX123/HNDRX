<?php

require_once __DIR__ . '/../config/conexion.php';

class RegistroActividad{

    private $conexion;

    public function __construct()
    {
        $conexion = new Conexion();
        $this->conexion = $conexionDB->conectar();
    }

    public function obtenerActividades(){
        $sql = "SELECT * FROM actividades ORDER BY fechaEvento ASC";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute();
        return $consulta->fetchAll();
    }

    public function guardar(){
        $sql = "INSERT INTO actividades (nombreActividad, fechaEvento, municipio) 
                VALUES (:nombreActividad, :fechaEvento, :municipio)";
        $consulta = $this->conexion->prepare($sql);
        return $consulta->execute([
            ':nombreActividad' => $datos['nombreActividad'],
            ':fechaEvento' => $datos['fechaEvento'],
            ':municipio' => $datos['municipio']
        ]);
    }

    public function eliminar($id){
        $sql = "DELETE FROM actividades WHERE idActividad = :idActividad";
        $consulta = $this->conexion->prepare($sql);
        $consulta->execute([':idActividad' => $idActividad]);
        return $consulta->rowCount() > 0;
    }

    public function editar($datos){
        $sql = "UPDATE actividades SET nombreActividad = :nombreActividad, fechaEvento = :fechaEvento, municipio = :municipio, totalRecaudo = :totalRecaudo WHERE idActividad = :idActividad";
        $consulta = $this->conexion->prepare($sql);
        return $consulta->execute([
            ':nombreActividad' => $datos['nombreActividad'],
            ':fechaEvento' => $datos['fechaEvento'],
            ':municipio' => $datos['municipio'],
            ':totalRecaudo' => $datos['totalRecaudo'],
            ':idActividad' => $datos['idActividad']
        ]);

        if($consulta->rowCount() > 0){
            return true;
        } 

        $verificar = $this->conexion->prepare("SELECT 1 FROM actividades WHERE idActividad = :idActividad");
        $verificar->execute([':idActividad' => $datos['idActividad']]);

        return(bool)$verificar->fetchColumn(); 
    }

}