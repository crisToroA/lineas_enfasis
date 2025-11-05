<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'conexion.php';

$documento = trim($_POST['documento'] ?? '');
if ($documento === '') {
    echo json_encode(['success' => false, 'message' => 'Documento requerido.']);
    exit;
}

$stmt = $conn->prepare("SELECT id, nombre FROM usuarios WHERE documento = ? LIMIT 1");
$stmt->bind_param('s', $documento);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    // En producción: generar token y enviar email. Aquí devolvemos éxito para demo.
    echo json_encode(['success' => true, 'message' => 'Si el documento está registrado, se enviaron instrucciones al correo asociado.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Documento no encontrado.']);
}
$stmt->close();
$conn->close();
?>
