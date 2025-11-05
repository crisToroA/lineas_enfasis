<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'conexion.php';

try {
    $sqlFile = __DIR__ . '/../database/database.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception('No se encontró database.sql en /database');
    }
    $sql = file_get_contents($sqlFile);
    if ($sql === false || trim($sql) === '') throw new Exception('El archivo SQL está vacío o no se pudo leer.');

    // Separar por ; pero respetar posibles delimitadores; usar multi_query es más sencillo
    if ($conn->multi_query($sql)) {
        $messages = [];
        do {
            if ($res = $conn->store_result()) {
                $res->free();
            }
            if ($conn->more_results()) {
                $messages[] = "OK";
            }
        } while ($conn->more_results() && $conn->next_result());
        echo json_encode(['success' => true, 'message' => 'SQL ejecutado (revise logs si hay errores).']);
        exit;
    } else {
        throw new Exception('multi_query falló: ' . $conn->error);
    }
} catch (Exception $e) {
    error_log('install_db error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error instalando la base de datos.', 'detail' => $e->getMessage()]);
    exit;
}
?>
