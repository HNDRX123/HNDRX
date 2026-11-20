<?php
/**
 * ===================================================
 * CLASE REGISTRO DE ACTIVIDAD - MODELO DE DATOS
 * ===================================================
 * Gestiona las operaciones CRUD sobre la tabla `actividades`.
 * Maneja compatibilidad de esquema (totalRecaudado/totalRecaudo)
 * mediante inspección dinámica de columnas.
 */
require_once __DIR__ . '/../CONFIG/conexion.php';

class RegistroActividad {

    private $conexion;

    /**
     * Constructor vacío: la conexión se obtiene bajo demanda
     * en cada operación mediante obtenerConexion().
     */
    public function __construct() {}

    /**
     * Obtiene la conexión a BD (lazy loading).
     * @return PDO Conexión activa
     */
    private function obtenerConexion() {
        if ($this->conexion === null) {
            $this->conexion = (new conexion())->conectar();
        }
        return $this->conexion;
    }

    /**
     * Detecta si la columna de recaudo se llama 'totalRecaudado' o 'totalRecaudo'.
     * Esto permite compatibilidad con diferentes versiones del esquema de BD.
     * @param PDO $db Conexión activa
     * @return string Nombre real de la columna
     */
    private function detectarColumnaRecaudo($db) {
        $columnas = $db->query("DESCRIBE actividades")->fetchAll(PDO::FETCH_COLUMN);
        return in_array('totalRecaudado', $columnas) ? 'totalRecaudado' : 'totalRecaudo';
    }

    /**
     * Obtiene todas las actividades ordenadas por fecha.
     * @return array Lista de actividades
     */
    public function obtenerActividades() {
        $db = $this->obtenerConexion();
        $consulta = $db->prepare("SELECT * FROM actividades ORDER BY fechaEvento ASC");
        $consulta->execute();
        return $consulta->fetchAll();
    }

    /**
     * Inserta una nueva actividad en la BD.
     * @param array $datos Datos validados de la actividad
     * @return bool true si se insertó correctamente
     */
    public function guardar($datos) {
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

    /**
     * Elimina una actividad por su ID.
     * @param int $id ID de la actividad
     * @return bool true si se eliminó al menos un registro
     */
    public function eliminar($id) {
        $db = $this->obtenerConexion();
        $consulta = $db->prepare("DELETE FROM actividades WHERE idActividad = :idActividad");
        $consulta->execute([':idActividad' => $id]);
        return $consulta->rowCount() > 0;
    }

    /**
     * Actualiza una actividad existente.
     * Si los datos no cambiaron, verifica que el registro exista para confirmar éxito.
     * @param array $datos Datos actualizados (debe incluir idActividad)
     * @return bool true si se actualizó o el registro existe
     */
    public function editar($datos) {
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

        if (!$ok) return false;
        if ($consulta->rowCount() > 0) return true;

        // Si rowCount es 0, verificar que el registro exista (datos iguales = sin cambios)
        $verificar = $db->prepare("SELECT 1 FROM actividades WHERE idActividad = :idActividad");
        $verificar->execute([':idActividad' => $datos['idActividad']]);
        return (bool)$verificar->fetchColumn();
    }
}
