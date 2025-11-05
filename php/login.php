<?php
require_once 'conexion.php';

$documento = $_POST['documento'] ?? '';
$password = $_POST['password'] ?? '';
$rol = $_POST['rol'] ?? '';

// consulta segura
$sql = "SELECT id, nombre, rol FROM usuarios WHERE documento = ? AND password = MD5(?) AND rol = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('sss', $documento, $password, $rol);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    echo json_encode(["success" => true, "rol" => $row['rol'], "id" => $row['id'], "nombre" => $row['nombre']]);
} else {
    echo json_encode(["success" => false]);
}
$stmt->close();
$conn->close();
?>
