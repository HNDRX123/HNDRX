<?php
/**
 * ===================================================
 * CONFIGURACIÓN DE CONEXIÓN A BASE DE DATOS
 * ===================================================
 * Clase encargada de establecer la conexión PDO con MySQL.
 * Intenta conectar a la BD principal (bd_club); si no existe,
 * usa la BD de respaldo (tortu) como fallback.
 */
class conexion {

    // Credenciales y datos del servidor
    private $host = "localhost";
    private $baseDatos = "bd_club";
    private $baseDatosFallback = "tortu";
    private $user = "root";
    private $password = "";

    /**
     * Establece y retorna una conexión PDO.
     * Configura modo de errores por excepciones y fetch asociativo por defecto.
     * @return PDO Conexión activa a la base de datos
     * @throws PDOException Si ambas bases de datos fallan
     */
    public function conectar() {
        $opciones = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ];

        try {
            // Intentar conexión con la BD principal
            $dsn = "mysql:host={$this->host};dbname={$this->baseDatos};charset=utf8mb4";
            return new PDO($dsn, $this->user, $this->password, $opciones);

        } catch (PDOException $e) {
            // Si la BD principal no existe, usar la de respaldo
            if ($e->getCode() == 1049 || stripos($e->getMessage(), 'Unknown database') !== false) {
                $dsnFallback = "mysql:host={$this->host};dbname={$this->baseDatosFallback};charset=utf8mb4";
                return new PDO($dsnFallback, $this->user, $this->password, $opciones);
            }
            throw $e;
        }
    }
}
