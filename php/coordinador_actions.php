<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'conexion.php';

// Asegurar charset (si no se definió en conexion.php)
if (method_exists($conn, 'set_charset')) {
    $conn->set_charset('utf8mb4');
}

// Activar reporting para que errores mysqli lancen excepciones (útil para depuración controlada)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
	// Determinar método / acción (mover aquí para que diagnóstico lo vea)
	$action = $_REQUEST['action'] ?? '';

	// Diagnóstico: verificar conexión, nombre de BD y conteos de tablas
	if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'diagnose_db') {
	    try {
	        $dbn = null;
	        $q = $conn->query("SELECT DATABASE() AS db");
	        if ($q) {
	            $row = $q->fetch_assoc();
	            $dbn = $row['db'] ?? null;
	        }
	        $tables = ['usuarios','lineas_enfasis','cursos','solicitudes','chat_history'];
	        $counts = [];
	        foreach ($tables as $t) {
	            try {
	                $r = $conn->query("SELECT COUNT(*) AS c FROM `$t`");
	                $counts[$t] = $r ? (int)$r->fetch_assoc()['c'] : null;
	            } catch (Exception $e) {
	                $counts[$t] = null;
	            }
	        }
	        echo json_encode(['success' => true, 'database' => $dbn, 'counts' => $counts]);
	    } catch (Exception $e) {
	        error_log('diagnose_db error: ' . $e->getMessage());
	        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
	    }
	    exit;
	}

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

	    // Si no hay profesores, insertar un conjunto por defecto y volver a consultar
	    if (empty($profes)) {
	        $samples = [
	            ['3001', 'prof3001', 'Carlos Pérez'],
	            ['3002', 'prof3002', 'María Gómez'],
	            ['3003', 'prof3003', 'Javier Martínez'],
	            ['3004', 'prof3004', 'Laura Ramírez'],
	            ['3005', 'prof3005', 'Andrés Torres'],
	            ['3006', 'prof3006', 'Sofía Morales'],
	        ];
	        $insStmt = $conn->prepare("INSERT IGNORE INTO usuarios (documento, password, rol, nombre) VALUES (?, MD5(?), 'profesor', ?)");
	        if ($insStmt) {
	            foreach ($samples as $s) {
	                $insStmt->bind_param('sss', $s[0], $s[1], $s[2]);
	                $insStmt->execute();
	            }
	            $insStmt->close();
	        }
	        // volver a consultar
	        $stmt = $conn->prepare($sql);
	        $stmt->execute();
	        $result = $stmt->get_result();
	        $profes = [];
	        while ($row = $result->fetch_assoc()) {
	            $profes[] = $row;
	        }
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
	    if (!$vstmt) {
	        error_log('prepare verificar profesor error: ' . $conn->error);
	        echo json_encode(['success' => false, 'message' => 'Error interno al validar profesor.']);
	        exit;
	    }
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
	    if (!$vstmt2) {
	        error_log('prepare verificar linea error: ' . $conn->error);
	        echo json_encode(['success' => false, 'message' => 'Error interno al validar línea de énfasis.']);
	        exit;
	    }
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
	        error_log('prepare insertar curso error: ' . $conn->error);
	        echo json_encode(['success' => false, 'message' => 'Error de preparación SQL al guardar curso.']);
	        exit;
	    }
	    $istmt->bind_param('sssii', $nombre, $codigo, $semestre, $profesor_id, $linea_enfasis_id);

	    if ($istmt->execute()) {
	        echo json_encode(['success' => true, 'course_id' => $conn->insert_id]);
	    } else {
	        // Log del error real para depuración
	        error_log('execute insertar curso error: ' . $istmt->error . ' / conn errno: ' . $conn->errno . ' / conn error: ' . $conn->error);
	        // si código duplicado
	        $msg = $conn->errno === 1062 ? 'El código del curso ya existe.' : 'Error al guardar el curso en la base de datos.';
	        echo json_encode(['success' => false, 'message' => $msg]);
	    }
	    exit;
	}

	if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list_cursos') {
	    try {
	        $linea = isset($_GET['linea_enfasis_id']) ? intval($_GET['linea_enfasis_id']) : 0;
	        if ($linea > 0) {
	            $sql = "SELECT c.id, c.nombre, c.codigo, c.semestre, u.nombre AS profesor_nombre, le.nombre AS linea_enfasis_nombre
	                    FROM cursos c
	                    JOIN usuarios u ON c.profesor_id = u.id
	                    JOIN lineas_enfasis le ON c.linea_enfasis_id = le.id
	                    WHERE c.linea_enfasis_id = ?
	                    ORDER BY c.semestre DESC, c.nombre ASC";
	            $stmt = $conn->prepare($sql);
	            if ($stmt === false) {
	                error_log('list_cursos prepare failed (with filtro): ' . $conn->error . ' -- SQL: ' . $sql);
	                echo json_encode(['success' => false, 'message' => 'Error interno al preparar la consulta de cursos.', 'detail' => $conn->error]);
	                exit;
	            }
	            $stmt->bind_param('i', $linea);
	        } else {
	            $sql = "SELECT c.id, c.nombre, c.codigo, c.semestre, u.nombre AS profesor_nombre, le.nombre AS linea_enfasis_nombre
	                    FROM cursos c
	                    JOIN usuarios u ON c.profesor_id = u.id
	                    JOIN lineas_enfasis le ON c.linea_enfasis_id = le.id
	                    ORDER BY c.semestre DESC, c.nombre ASC";
	            $stmt = $conn->prepare($sql);
	            if ($stmt === false) {
	                error_log('list_cursos prepare failed: ' . $conn->error . ' -- SQL: ' . $sql);
	                echo json_encode(['success' => false, 'message' => 'Error interno al preparar la consulta de cursos.', 'detail' => $conn->error]);
	                exit;
	            }
	        }
	
	        $stmt->execute();
	        $result = $stmt->get_result();
	        $cursos = [];
	        while ($row = $result->fetch_assoc()) {
	            $cursos[] = $row;
	        }
	        // devolver siempre objeto con success para que el frontend lo interprete fácilmente
	        echo json_encode(['success' => true, 'data' => $cursos]);
	        exit;
	    } catch (Exception $e) {
	        // registrar detalle para diagnóstico y devolverlo en JSON
	        error_log('list_cursos exception: ' . $e->getMessage());
	        echo json_encode([
	            'success' => false,
	            'message' => 'Error al obtener la lista de cursos. Revisa el log del servidor.',
	            'detail' => $e->getMessage()
	        ]);
	        exit;
	    }
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
	    // Solo pendientes para la sección de validación
	    $sql = "SELECT s.id, u.nombre AS remitente, u.rol, s.tipo, s.fecha, s.estado
	            FROM solicitudes s
	            JOIN usuarios u ON s.remitente_id = u.id
	            WHERE s.estado = 'pendiente'
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
	    $sql = "SELECT s.*, u.nombre AS remitente, u.rol,
	                   a.nombre AS aprobador_nombre, s.comentario_coordinador
	            FROM solicitudes s
	            JOIN usuarios u ON s.remitente_id = u.id
	            LEFT JOIN usuarios a ON s.aprobador_id = a.id
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
	    $comentario = trim($_POST['comentario'] ?? '');
	    // opcional: quien procesa (si no hay autenticación se puede enviar desde frontend)
	    $aprobador_id = intval($_POST['aprobador_id'] ?? 0);
	    if (!in_array($estado, ['aprobada', 'rechazada'])) {
	        echo json_encode(['success' => false, 'message' => 'Estado inválido']);
	        exit;
	    }
	    if ($solicitud_id <= 0) {
	        echo json_encode(['success' => false, 'message' => 'ID de solicitud inválido']);
	        exit;
	    }

	    $sql = "UPDATE solicitudes SET estado = ?, comentario_coordinador = ?, aprobador_id = ?, fecha_procesamiento = NOW() WHERE id = ?";
	    $stmt = $conn->prepare($sql);
	    if ($stmt === false) {
	        echo json_encode(['success' => false, 'message' => 'Error de preparación SQL.']);
	        exit;
	    }
	    // si aprobador_id es 0, bindarlo como NULL: usar nullificación manual
	    if ($aprobador_id > 0) {
	        $stmt->bind_param('siii', $estado, $comentario, $aprobador_id, $solicitud_id);
	    } else {
	        // bind con aprobador_id = NULL -> pasar 0; la columna permite NULL y ON UPDATE/DELETE ya está definida
	        $nullAprobador = null;
	        // Para bind_param no se puede pasar NULL directo con tipo 'i', así que pasar 0 y dejar que la columna acepte (o adaptar según políticas)
	        $stmt->bind_param('siii', $estado, $comentario, $aprobador_id, $solicitud_id);
	    }
	    $ok = $stmt->execute();
	    echo json_encode(['success' => $ok]);
	    exit;
	}

	// Nueva acción: listar solicitudes ya procesadas (seguimiento)
	if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list_solicitudes_procesadas') {
	    $sql = "SELECT s.id, u.nombre AS remitente, u.rol, s.tipo, s.fecha, s.estado,
	                   s.descripcion, s.comentario_coordinador, s.fecha_procesamiento,
	                   a.id AS aprobador_id, a.nombre AS aprobador_nombre
	            FROM solicitudes s
	            JOIN usuarios u ON s.remitente_id = u.id
	            LEFT JOIN usuarios a ON s.aprobador_id = a.id
	            WHERE s.estado <> 'pendiente'
	            ORDER BY s.fecha_procesamiento DESC, s.fecha DESC";
	    $stmt = $conn->prepare($sql);
	    $stmt->execute();
	    $result = $stmt->get_result();
	    $sols = [];
	    while ($row = $result->fetch_assoc()) {
	        $sols[] = $row;
	    }
	    echo json_encode($sols);
	    exit;
	}

	// Acción no reconocida
	echo json_encode(['success' => false, 'message' => 'Acción inválida.']);
	exit;
} catch (Exception $e) {
    // Registrar detalle para diagnóstico (no exponer todo al cliente)
    error_log('coordinador_actions exception: ' . $e->getMessage());
    // Responder JSON consistente para evitar errores de parseo en el frontend
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor. Revise la consola o contacte al administrador.']);
    exit;
}
?>
