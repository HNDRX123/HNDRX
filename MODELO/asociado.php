<?php
/**
 * ===================================================
 * CLASE ASOCIADO - MODELO DE DATOS
 * ===================================================
 * Representa a un asociado/socio del Club Acerías.
 * Se encarga de recibir datos del formulario, normalizarlos
 * y guardar el registro en la tabla `asociados`.
 */
require_once __DIR__ . '/../CONFIG/conexion.php';

class asociado {

    // --- Propiedades del asociado ---
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
    private $rol_id;
    private $conexion;

    /**
     * Constructor: recibe un arreglo de datos y los normaliza.
     * Acepta variantes de nombres de campos (nombre/nombres, apellido/apellidos, etc.)
     * para mantener compatibilidad con distintos formularios.
     * @param array $datos Datos del asociado desde formulario o API
     */
    public function __construct($datos) {
        $this->documento           = $datos['documento'] ?? null;
        $this->nombres             = $datos['nombres'] ?? $datos['nombre'] ?? null;
        $this->apellidos           = $datos['apellidos'] ?? $datos['apellido'] ?? null;
        $this->fechaNacimiento     = $datos['fechaNacimiento'] ?? null;
        $this->direccionResidencia = $datos['direccionResidencia'] ?? $datos['direccionResidencial'] ?? null;
        $this->ciudad              = $datos['ciudad'] ?? null;
        $this->telefono            = $datos['telefono'] ?? null;
        $this->email               = $datos['email'] ?? null;

        // Normalizar género: aceptar "Otros" como "Otro"
        $gen = ucfirst(strtolower(trim($datos['genero'] ?? '')));
        if ($gen === 'Otros') $gen = 'Otro';
        $this->genero = in_array($gen, ['Masculino', 'Femenino', 'Otro']) ? $gen : 'Otro';

        // Procesar contraseña: si ya viene hasheada, conservarla; si no, hashearla
        $rawPwd = $datos['password'] ?? $datos['contrasena'] ?? $datos['clave'] ?? null;
        if (!empty($rawPwd)) {
            $yaHasheada = str_starts_with($rawPwd, '$2y$')
                       || str_starts_with($rawPwd, '$2a$')
                       || str_starts_with($rawPwd, '$argon2');
            $this->password = $yaHasheada ? $rawPwd : password_hash($rawPwd, PASSWORD_DEFAULT);
        } else {
            $this->password = null;
        }

        // Normalizar rol: solo "Admin" o "Asociado"
        $rolInput    = strtolower(trim($datos['rol'] ?? 'asociado'));
        $this->rol   = ($rolInput === 'admin') ? 'Admin' : 'Asociado';
        $this->rol_id = ($this->rol === 'Admin') ? 1 : 2;
    }

    /**
     * Obtiene (o crea) la conexión a BD bajo demanda.
     * @return PDO Conexión activa
     */
    private function obtenerConexion() {
        if ($this->conexion === null) {
            $this->conexion = (new conexion())->conectar();
        }
        return $this->conexion;
    }

    /**
     * Inserta el asociado en la tabla `asociados`.
     * Inspecciona las columnas de la tabla para ser compatible con
     * diferentes versiones del esquema (clave/password, con/sin rol_id).
     * @return bool true si el INSERT fue exitoso
     */
    public function guardar() {
        $db = $this->obtenerConexion();

        // Detectar estructura real de la tabla para compatibilidad
        $columnas    = $db->query("DESCRIBE asociados")->fetchAll(PDO::FETCH_COLUMN);
        $colClave    = in_array('clave', $columnas) ? 'clave' : 'password';
        $tieneRolId  = in_array('rol_id', $columnas);

        // Armar lista de campos y parámetros dinámicamente
        $campos = ["documento", "nombres", "apellidos", "fechaNacimiento", "genero",
                    "direccionResidencia", "ciudad", "telefono", "email", $colClave, "rol"];

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

        // Si la tabla tiene columna rol_id, incluirla
        if ($tieneRolId) {
            $campos[]          = 'rol_id';
            $params[':rol_id'] = $this->rol_id;
        }

        // Construir placeholders (usar :clave para la columna de contraseña)
        $placeholders = array_map(fn($c) => ($c === $colClave) ? ':clave' : ':' . $c, $campos);

        $sql = "INSERT INTO asociados (" . implode(", ", $campos) . ") VALUES (" . implode(", ", $placeholders) . ")";
        return $db->prepare($sql)->execute($params);
    }
}
