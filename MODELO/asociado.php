<?php

require_once __DIR__ . '/../config/conexion.php';

class asociado {
    private ?string $documento;
    private ?string $nombres;
    private ?string $apellidos;
    private ?string $fechaNacimiento;
    private string $genero;
    private ?string $direccionResidencia;
    private ?string $ciudad;
    private ?string $telefono;
    private ?string $email;
    private ?string $password;
    private string $rol;
    private int $rol_id;
    private ?PDO $conexion = null;

    public function __construct(array $datos) {
        $this->documento =$datos['documento'] ?? null;
        $this->nombres = $datos['nombres'] ?? $datos['nombre'] ?? null;
        $this->apellidos = $datos['apellidos'] ?? $datos['apellido'] ?? null;
        $this->fechaNacimiento =$datos['fechaNacimiento'] ?? null;
        $this->direccionResidencia = $datos['direccionResidencia'] ?? $datos['direccionResidencial'] ?? null;
        $this->ciudad =$datos['ciudad'] ?? null;
        $this->telefono =$datos['telefono'] ?? null;
        $this->email =$datos['email'] ?? null;

        $gen = ucfirst(strtolower(trim($datos['genero'] ?? '')));
        if ($gen === 'Otros') {$gen = 'Otro';
        }
        $this->genero = in_array($gen, ['Masculino', 'Femenino', 'Otro'], true) ?$gen : 'Otro';

        $rawPwd =$datos['password'] ?? $datos['contrasena'] ?? $datos['clave'] ?? null;
        if (!empty($rawPwd)) {
            $yaHasheada = str_starts_with($rawPwd, '$2y$')
                || str_starts_with($rawPwd, '$2a$')
                || str_starts_with($rawPwd, '$argon2');
            $this->password =$yaHasheada ? $rawPwd : password_hash($rawPwd, PASSWORD_DEFAULT);
        } else {
            $this->password = null;
        }

        $rolInput = strtolower(trim($datos['rol'] ?? 'asociado'));
        $this->rol = ($rolInput === 'admin') ? 'Admin' : 'Asociado';
        $this->rol_id = ($this->rol === 'Admin') ? 1 : 2;
    }

    private function obtenerConexion(): PDO {
        if ($this->conexion === null) {$this->conexion = (new conexion())->conectar();
        }
        return $this->conexion;
    }

    public function guardar(): bool {
        $db =$this->obtenerConexion();
        $columnas =$db->query("DESCRIBE asociados")->fetchAll(PDO::FETCH_COLUMN);
        $colClave = in_array('clave',$columnas, true) ? 'clave' : 'password';
        $tieneRolId = in_array('rol_id',$columnas, true);

        $campos = [
            "documento", "nombres", "apellidos", "fechaNacimiento", "genero",
            "direccionResidencia", "ciudad", "telefono", "email", $colClave, "rol"
        ];

        $params = [
            ':documento'           => $this->documento,
            ':nombres'             => $this->nombres,
            ':apellidos'           => $this->apellidos,
            ':fechaNacimiento'     => $this->fechaNacimiento,
            ':genero'              => $this->genero,
            ':direccionResidencia' => $this->direccionResidencia,
            ':ciudad'              => $this->ciudad,
            ':telefono'            => $this->telefono,
            ':email'               => $this->email,
            ':clave'               => $this->password,
            ':rol'                 => $this->rol
        ];

        if ($tieneRolId) {$campos[] = 'rol_id';
            $params[':rol_id'] =$this->rol_id;
        }

        $placeholders = array_map(fn($c) => ($c === $colClave) ? ':clave' : ':' .$c, $campos);$sql = "INSERT INTO asociados (" . implode(", ", $campos) . ") VALUES (" . implode(", ", $placeholders) . ")";

        return $db->prepare($sql)->execute($params);
    }
}