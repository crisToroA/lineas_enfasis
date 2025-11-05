<?php
$servername = "localhost";
$username = "root"; // o el usuario de tu base
$password = "";     // pon la contraseña si tiene
$database = "portal_udemedellin";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    // lanzar excepción para que el caller la capture y devuelva JSON consistente
    throw new Exception("Error de conexión: " . $conn->connect_error);
}

// Asegurar charset para evitar problemas con acentos o textos largos
$conn->set_charset('utf8mb4');
?>
