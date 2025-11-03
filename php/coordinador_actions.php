<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'conexion.php';

// Determinar método / acción
$action = $_REQUEST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list_profesores') {
    // Listar usuarios con rol = 'profesor'
    $sql = "SELECT id, nombre, documento FROM usuarios WHERE rol = 'profesor' ORDER BY nombre ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $profes = [];
    while ($row = $result->fetch_assoc()) {
        $profes[] = $row;
    }
    echo json_encode($profes);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list_lineas_enfasis') {
    $sql = "SELECT id, nombre FROM lineas_enfasis ORDER BY nombre ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $lineas = [];
    while ($row = $result->fetch_assoc()) {
        $lineas[] = $row;
    }
    echo json_encode($lineas);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'registrar_curso') {
    $nombre = trim($_POST['nombre'] ?? '');
    $codigo = trim($_POST['codigo'] ?? '');
    $semestre = trim($_POST['semestre'] ?? '');
    $profesor_id = intval($_POST['profesor_id'] ?? 0);
    $linea_enfasis_id = intval($_POST['linea_enfasis_id'] ?? 0);

    if ($nombre === '' || $codigo === '' || $semestre === '' || $profesor_id <= 0 || $linea_enfasis_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Faltan datos requeridos.']);
        exit;
    }

    // Verificar que el profesor exista y sea rol profesor
    $vsql = "SELECT id FROM usuarios WHERE id = ? AND rol = 'profesor' LIMIT 1";
    $vstmt = $conn->prepare($vsql);
    $vstmt->bind_param('i', $profesor_id);
    $vstmt->execute();
    $vres = $vstmt->get_result();
    if ($vres->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Profesor no válido.']);
        exit;
    }

    // Verificar que la línea de énfasis exista
    $vsql2 = "SELECT id FROM lineas_enfasis WHERE id = ? LIMIT 1";
    $vstmt2 = $conn->prepare($vsql2);
    $vstmt2->bind_param('i', $linea_enfasis_id);
    $vstmt2->execute();
    $vres2 = $vstmt2->get_result();
    if ($vres2->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Línea de énfasis no válida.']);
        exit;
    }

    // Insertar curso
    $ins = "INSERT INTO cursos (nombre, codigo, semestre, profesor_id, linea_enfasis_id) VALUES (?, ?, ?, ?, ?)";
    $istmt = $conn->prepare($ins);
    if (!$istmt) {
        echo json_encode(['success' => false, 'message' => 'Error de preparación SQL.']);
        exit;
    }
    $istmt->bind_param('sssii', $nombre, $codigo, $semestre, $profesor_id, $linea_enfasis_id);

    if ($istmt->execute()) {
        echo json_encode(['success' => true, 'course_id' => $conn->insert_id]);
    } else {
        // si código duplicado
        $msg = $conn->errno === 1062 ? 'El código del curso ya existe.' : 'Error al guardar el curso.';
        echo json_encode(['success' => false, 'message' => $msg]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list_cursos') {
    $where = '';
    $params = [];
    $types = '';
    if (isset($_GET['linea_enfasis_id']) && intval($_GET['linea_enfasis_id']) > 0) {
        $where = 'WHERE c.linea_enfasis_id = ?';
        $params[] = intval($_GET['linea_enfasis_id']);
        $types .= 'i';
    }
    $sql = "SELECT c.id, c.nombre, c.codigo, c.semestre, u.nombre AS profesor_nombre, le.nombre AS linea_enfasis_nombre
            FROM cursos c
            JOIN usuarios u ON c.profesor_id = u.id
            JOIN lineas_enfasis le ON c.linea_enfasis_id = le.id
            $where
            ORDER BY c.semestre DESC, c.nombre ASC";
    $stmt = $conn->prepare($sql);
    if ($where) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $cursos = [];
    while ($row = $result->fetch_assoc()) {
        $cursos[] = $row;
    }
    echo json_encode($cursos);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'eliminar_curso') {
    $curso_id = intval($_POST['curso_id'] ?? 0);
    if ($curso_id <= 0) {
        echo json_encode(['success' => false]);
        exit;
    }
    // Eliminar curso (y en cascada solicitudes)
    $del = "DELETE FROM cursos WHERE id = ?";
    $stmt = $conn->prepare($del);
    $stmt->bind_param('i', $curso_id);
    $ok = $stmt->execute();
    echo json_encode(['success' => $ok]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'obtener_solicitudes') {
    $sql = "SELECT s.id, u.nombre AS remitente, u.rol, s.tipo, s.fecha, s.estado
            FROM solicitudes s
            JOIN usuarios u ON s.remitente_id = u.id
            ORDER BY s.fecha DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $solicitudes = [];
    while ($row = $result->fetch_assoc()) {
        $solicitudes[] = $row;
    }
    echo json_encode($solicitudes);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'detalle_solicitud' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $sql = "SELECT s.*, u.nombre AS remitente, u.rol
            FROM solicitudes s
            JOIN usuarios u ON s.remitente_id = u.id
            WHERE s.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $detalle = $result->fetch_assoc();
    echo json_encode($detalle);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'validar_solicitud') {
    $solicitud_id = intval($_POST['solicitud_id'] ?? 0);
    $estado = $_POST['estado'] ?? '';
    if (!in_array($estado, ['aprobada', 'rechazada'])) {
        echo json_encode(['success' => false, 'message' => 'Estado inválido']);
        exit;
    }
    $sql = "UPDATE solicitudes SET estado = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('si', $estado, $solicitud_id);
    $ok = $stmt->execute();
    echo json_encode(['success' => $ok]);
    exit;
}

// Acción no reconocida
echo json_encode(['success' => false, 'message' => 'Acción inválida.']);
exit;
?>
