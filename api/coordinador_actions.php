<?php
require_once '../database/db_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    switch($action) {
        case 'registrar_curso':
            $nombre = $_POST['nombre'];
            $codigo = $_POST['codigo'];
            $semestre = $_POST['semestre'];
            
            $stmt = $pdo->prepare("INSERT INTO cursos (nombre, codigo, semestre) VALUES (?, ?, ?)");
            $stmt->execute([$nombre, $codigo, $semestre]);
            echo json_encode(['success' => true]);
            break;
            
        case 'validar_solicitud':
            $solicitud_id = $_POST['solicitud_id'];
            $estado = $_POST['estado'];
            
            $stmt = $pdo->prepare("UPDATE solicitudes SET estado = ? WHERE id = ?");
            $stmt->execute([$estado, $solicitud_id]);
            echo json_encode(['success' => true]);
            break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'];
    
    switch($action) {
        case 'obtener_solicitudes':
            $stmt = $pdo->query("SELECT * FROM solicitudes ORDER BY fecha DESC");
            $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($solicitudes);
            break;
    }
}
?>
