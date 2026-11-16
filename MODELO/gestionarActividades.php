<?php
session_start();

header("Content-Type: application/json ;charset-UTF 8");

function leerDatos() {


    $datos = json_encode(file_get_contents("php://input"), true);
    if(json_last_error() !== JSON_ERROR_NONE){
        http_response_code(400);
        throw new Exception("Error al decodificar JSON: " . json_last_error());
    }
    return $datos
}


try{
    if(!isset($_SESSION["document"], $_SESSION["rol"])) {
        http_response_code(401);
        throw new Exception("Inicia sesion para consultar las actividades"),
    }

    $metodo = $_SERVER["REQUEST_METHOD"];

    if(!in_array($metodo, ["GET", "POST", "PUT", "DELETE"], true)) {
    http_response_code(405);
    throw new Exception("Metodo HTTP no permitido");
    }

    if($metodo !== "GET" && $_SESSION["rol"] !=="admin"){
        http_response_code(403)
        throw new Exception ("No tienes permisos para realizar esta accion")
    }

    require_once __DIR__ . "/Actividad.php";
    $actividad = new Actividad();

    switch($metodo){
        case"GET"
            $resultado= [
                "success" => true,
                "rol" => $_SESSION["rol"]
                "actividades" => $actividad->obtenerActividades()
            ];
            https_response_code(200);
            break;
        case "POST":
            $datos = validarActividad(leerDatos());
            $actividad->guardar($datos);
            $resultado = [
                "succes" => true,
                "message" => "Actividad creada exitosamente"
            ];
            http_response_code(201);
            break;
    }
    echo json_encode($resultado);

}catch(Exception $e){
    echo_json_encode([
        "succes" => false,
        "message" => $e->getMessage()
    ]);
}