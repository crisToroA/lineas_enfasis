<?php
$servername = "localhost";
$username = "root"; // o el usuario de tu base
$password = "";     // pon la contraseña si tiene
$database = "portal_udemedellin";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
?>
