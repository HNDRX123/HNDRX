<?php

class conexion {
    private string $host = "localhost";
    private string $baseDatos = "bd_club";
    private string $baseDatosFallback = "tortu";
    private string $user = "root";
    private string $password = "";

    public function conectar(): PDO {
        try {
            return $this->crearPdo($this->baseDatos);
        } catch (PDOException $e) {
            if ($e->getCode() == 1049 || stripos($e->getMessage(), 'Unknown database') !== false) {
                return $this->crearPdo($this->baseDatosFallback);
            }
            throw $e;
        }
    }

    private function crearPdo(string $nombreBd): PDO {
        $dsn = "mysql:host={$this->host};dbname={$nombreBd};charset=utf8mb4";
        return new PDO($dsn, $this->user, $this->password, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
}