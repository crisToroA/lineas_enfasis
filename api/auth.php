<?php
session_start();
require_once '../database/db_config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $documento = trim($_POST['documento']);
    $password = trim($_POST['password']);
    $rol = trim($_POST['rol']);

    try {
        // Buscar usuario
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE documento = ? AND rol = ?");
        $stmt->execute([$documento, $rol]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        // Para pruebas - imprimir información
        error_log("Intento de login - Documento: $documento, Rol: $rol");
        error_log("Usuario encontrado: " . ($usuario ? "Sí" : "No"));

        if ($usuario) {
            // La contraseña para todos los usuarios de prueba es: admin123
            if (password_verify($password, $usuario['password'])) {
                $_SESSION['user_id'] = $usuario['id'];
                $_SESSION['rol'] = $usuario['rol'];
                $_SESSION['nombre'] = $usuario['nombre'];

                echo json_encode([
                    'success' => true,
                    'redirect' => determinarRedireccion($usuario['rol'])
                ]);
                exit;
            }
        }

        echo json_encode([
            'success' => false,
            'message' => 'Documento o contraseña incorrectos'
        ]);

    } catch (PDOException $e) {
        error_log("Error DB: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error en el servidor'
        ]);
    }
}

function determinarRedireccion($rol) {
    switch ($rol) {
        case 'coordinador':
            return 'dashboards/coordinador_dashboard.html';
        case 'profesor':
            return 'dashboards/teacher_dashboard.html';
        case 'estudiante':
            return 'dashboards/student_dashboard.html';
        default:
            return 'index.html';
    }
}
?>
