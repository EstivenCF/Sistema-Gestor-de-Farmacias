<?php
$host = "localhost";
$port = "5432";
$dbname = "Farmacia";
$user = "postgres";
$password = "379123"; // 379123 - 2003 //

date_default_timezone_set('America/Santo_Domingo');

try {
    $conexion = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Mostrar errores claros
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC // Devuelve arrays asociativos
        ]
    );
    // Forzar codificación UTF-8
    $conexion->exec("SET NAMES 'UTF8'");

    $conexion->exec("SET TIME ZONE 'America/Santo_Domingo'");

} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>