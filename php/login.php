<?php
include 'conexion.php';

$documento = $_POST['documento'];
$password = $_POST['password'];
$rol = $_POST['rol'];

$sql = "SELECT * FROM usuarios WHERE documento = '$documento' AND password = MD5('$password') AND rol = '$rol'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo json_encode(["success" => true, "rol" => $rol]);
} else {
    echo json_encode(["success" => false]);
}

$conn->close();
?>
