<?php
require_once __DIR__ . '/../CONFIG/conexion.php';

class asociado {
    private $documento;
    private $nombres;
    private $apellidos;
    private $fechaNacimiento;
    private $genero;
    private $direccionResidencia;
    private $ciudad;
    private $telefono;
    private $email;
    private $password;
    private $rol;
    private $conexion;

    public function __construct($datos){
        $this->documento = $datos['documento'] ?? null;
        $this->nombres = $datos['nombres'] ?? $datos['nombre'] ?? null;
        $this->apellidos = $datos['apellidos'] ?? $datos['apellido'] ?? null;
        $this->fechaNacimiento = $datos['fechaNacimiento'] ?? null;
        $this->genero = $datos['genero'] ?? null;
        $this->direccionResidencia = $datos['direccionResidencia'] ?? $datos['direccionResidencial'] ?? null;
        $this->ciudad = $datos['ciudad'] ?? null;
        $this->telefono = $datos['telefono'] ?? null;
        $this->email = $datos['email'] ?? null;

        $rawPassword = $datos['password'] ?? '';
        $this->password = !empty($rawPassword) ? password_hash($rawPassword, PASSWORD_DEFAULT) : '';
        $this->rol = $datos['rol'] ?? 'asociado';

        $conexionDB = new conexion();
        $this->conexion = $conexionDB->conectar();
    }

    public function guardar(){
        $sql = "INSERT INTO asociados (documento, nombres, apellidos, fechaNacimiento, genero, direccionResidencia, ciudad, telefono, email, password, rol) 
                VALUES (:documento, :nombres, :apellidos, :fechaNacimiento, :genero, :direccionResidencia, :ciudad, :telefono, :email, :password, :rol)";
        $consulta = $this->conexion->prepare($sql);
        return $consulta->execute([
            ':documento'           => $this->documento,
            ':nombres'             => $this->nombres,
            ':apellidos'           => $this->apellidos,
            ':fechaNacimiento'     => $this->fechaNacimiento,
            ':genero'              => $this->genero,
            ':direccionResidencia' => $this->direccionResidencia,
            ':ciudad'              => $this->ciudad,
            ':telefono'            => $this->telefono,
            ':email'               => $this->email,
            ':password'            => $this->password,
            ':rol'                 => $this->rol
        ]);
    }
}
