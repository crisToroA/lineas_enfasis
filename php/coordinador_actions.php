<?php
header('Content-Type: application/json; charset=utf-8');

// Nuevo: leer config para DEBUG antes de intentar conectar
$DEBUG = (getenv('DEBUG') === '1');
if (file_exists(__DIR__ . '/config.php')) {
    include_once __DIR__ . '/config.php';
    if (defined('DEBUG') && DEBUG) $DEBUG = true;
}

// Intentar cargar conexion.php pero capturar excepción si falla (evita 500 sin JSON)
try {
    require_once 'conexion.php';
} catch (Throwable $ex) {
    error_log('conexion.php load error: ' . $ex->getMessage());
    http_response_code(500);
    $resp = ['success' => false, 'message' => 'No fue posible conectar con la base de datos. Revise la configuración del servidor.'];
    if (!empty($DEBUG)) $resp['detail'] = $ex->getMessage();
    echo json_encode($resp);
    exit;
}

// Asegurar charset (si no se definió en conexion.php)
if (isset($conn) && method_exists($conn, 'set_charset')) {
    $conn->set_charset('utf8mb4');
}

// Activar reporting para que errores mysqli lancen excepciones (útil para depuración controlada)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Nuevo: modo debug (activar con variable de entorno DEBUG=1 o definiendo DEBUG en config.php)
$DEBUG = (getenv('DEBUG') === '1');
if (!$DEBUG && file_exists(__DIR__ . '/config.php')) {
    include_once __DIR__ . '/config.php';
    if (defined('DEBUG') && DEBUG) $DEBUG = true;
}

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
	    // ahora devolvemos más campos (duracion, creditos, cupos, descripcion)
	    $sql = "SELECT id, nombre, duracion, creditos, cupos, descripcion FROM lineas_enfasis ORDER BY nombre ASC";
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
	    $aprobador_id = intval($_POST['aprobador_id'] ?? 0);

	    if (!in_array($estado, ['aprobada', 'rechazada'])) {
	        echo json_encode(['success' => false, 'message' => 'Estado inválido']);
	        exit;
	    }
	    if ($solicitud_id <= 0) {
	        echo json_encode(['success' => false, 'message' => 'ID de solicitud inválido']);
	        exit;
	    }

	    try {
	        if ($aprobador_id > 0) {
	            $sql = "UPDATE solicitudes SET estado = ?, comentario_coordinador = ?, aprobador_id = ?, fecha_procesamiento = NOW() WHERE id = ?";
	            $stmt = $conn->prepare($sql);
	            if ($stmt === false) {
	                error_log('validar_solicitud prepare (with approver) failed: ' . $conn->error);
	                http_response_code(500);
	                echo json_encode(['success' => false, 'message' => 'Error interno al preparar consulta.', 'detail' => $conn->error]);
	                exit;
	            }
	            $stmt->bind_param('siii', $estado, $comentario, $aprobador_id, $solicitud_id);
	        } else {
	            // usar NULL explícito para evitar violar FK con 0
	            $sql = "UPDATE solicitudes SET estado = ?, comentario_coordinador = ?, aprobador_id = NULL, fecha_procesamiento = NOW() WHERE id = ?";
	            $stmt = $conn->prepare($sql);
	            if ($stmt === false) {
	                error_log('validar_solicitud prepare (no approver) failed: ' . $conn->error);
	                http_response_code(500);
	                echo json_encode(['success' => false, 'message' => 'Error interno al preparar consulta.', 'detail' => $conn->error]);
	                exit;
	            }
	            $stmt->bind_param('ssi', $estado, $comentario, $solicitud_id);
	        }

	        $execOk = $stmt->execute();
	        if ($execOk === false) {
	            $err = $stmt->error ?: $conn->error;
	            error_log('validar_solicitud execute error: ' . $err . ' errno:' . $conn->errno);
	            http_response_code(500);
	            $resp = ['success' => false, 'message' => 'Error al actualizar la solicitud.'];
	            if (!empty($DEBUG)) $resp['detail'] = substr($err,0,512);
	            echo json_encode($resp);
	            exit;
	        }

	        // verificar filas afectadas
	        $affected = $stmt->affected_rows;
	        $stmt->close();

	        // si se aprobó, crear efectos secundarios (inscripción) - mantener este bloque existente
	        if ($estado === 'aprobada') {
	            $q2 = $conn->prepare("SELECT remitente_id, tipo, linea_enfasis_id FROM solicitudes WHERE id = ? LIMIT 1");
	            if ($q2) {
	                $q2->bind_param('i', $solicitud_id);
	                $q2->execute();
	                $row = $q2->get_result()->fetch_assoc();
	                if ($row && ($row['tipo'] ?? '') === 'Inscripción LE') {
	                    $usuario = intval($row['remitente_id']);
	                    $linea = intval($row['linea_enfasis_id'] ?? 0);
	                    if ($usuario > 0 && $linea > 0) {
	                        $check = $conn->prepare("SELECT id FROM inscripciones WHERE usuario_id = ? AND linea_enfasis_id = ? AND estado = 'confirmada' LIMIT 1");
	                        if ($check) {
	                            $check->bind_param('ii', $usuario, $linea);
	                            $check->execute();
	                            if ($check->get_result()->num_rows === 0) {
	                                $ins = $conn->prepare("INSERT INTO inscripciones (usuario_id, linea_enfasis_id, estado) VALUES (?, ?, 'confirmada')");
	                                if ($ins) {
	                                    $ins->bind_param('ii', $usuario, $linea);
	                                    $ins->execute();
	                                    $ins->close();
	                                }
	                            }
	                            $check->close();
	                        }
	                    }
	                }
	                $q2->close();
	            } else {
	                error_log('validar_solicitud q2 prepare failed: ' . $conn->error);
	            }
	        }

	        echo json_encode(['success' => true, 'affected_rows' => $affected]);
	        exit;
	    } catch (Exception $e) {
	        error_log('validar_solicitud exception: ' . $e->getMessage());
	        http_response_code(500);
	        $resp = ['success' => false, 'message' => 'Error interno del servidor.'];
	        if ($DEBUG) $resp['detail'] = $e->getMessage();
	        echo json_encode($resp);
	        exit;
	    }
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

	// Acción: obtener detalle completo de una línea de énfasis
	if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get_linea_detail' && isset($_GET['id'])) {
	    $id = intval($_GET['id']);
	    if ($id <= 0) {
	        echo json_encode(['success' => false, 'message' => 'ID inválido']);
	        exit;
	    }
	    $sql = "SELECT id, nombre, duracion, creditos, cupos, descripcion FROM lineas_enfasis WHERE id = ? LIMIT 1";
	    $stmt = $conn->prepare($sql);
	    if (!$stmt) {
	        // Registrar y devolver detalle cuando estemos en modo debug
	        error_log('get_linea_detail prepare error: ' . $conn->error . ' -- SQL: ' . $sql);
	        $resp = ['success' => false, 'message' => 'Error interno. Revise el log del servidor.'];
	        if ($DEBUG) $resp['detail'] = $conn->error;
	        echo json_encode($resp);
	        exit;
	    }
	    $stmt->bind_param('i', $id);
	    $stmt->execute();
	    $res = $stmt->get_result();
	    $detalle = $res->fetch_assoc();

	    // Si no hay datos completos, rellenar con ejemplos útiles para demo
	    if (!$detalle) {
	        // intentar obtener al menos el nombre si existe (otra consulta)
	        $nstmt = $conn->prepare("SELECT nombre FROM lineas_enfasis WHERE id = ? LIMIT 1");
	        if ($nstmt) {
	            $nstmt->bind_param('i', $id);
	            $nstmt->execute();
	            $nr = $nstmt->get_result()->fetch_assoc();
	            $nombre = $nr['nombre'] ?? ("Línea de Énfasis #" . $id);
	        } else {
	            $nombre = "Línea de Énfasis #" . $id;
	        }
	        $detalle = [
	            'id' => $id,
	            'nombre' => $nombre,
	            'duracion' => '4 semestres',            // <- cambiado
	            'creditos' => 16,
	            'cupos' => 30,
	            'descripcion' => "Descripción de ejemplo para {$nombre}. Contenido introductorio, prácticas y proyectos (ej.: encender LEDs, sensores, control de motores)."
	        ];
	    } else {
	        // completar campos vacíos con valores de ejemplo
	        if (empty($detalle['duracion'])) $detalle['duracion'] = '4 semestres';  // <- cambiado
	        if ($detalle['creditos'] === null || $detalle['creditos'] === '') $detalle['creditos'] = 16;
	        if ($detalle['cupos'] === null || $detalle['cupos'] === '') $detalle['cupos'] = 30;
	        if (empty($detalle['descripcion'])) {
	            // descripción contextual según nombre
	            $nombre = $detalle['nombre'] ?? '';
	            if (stripos($nombre, 'robot') !== false || stripos($nombre, 'arduino') !== false) {
	                $detalle['descripcion'] = "Introducción práctica a Arduino y Robótica: montaje de circuitos, programación básica y proyectos (ej.: LED, sensores, motores). Incluye guías paso a paso y ejercicios.";
	            } else {
	                $detalle['descripcion'] = "Descripción general de la línea de énfasis '{$nombre}'. Contenidos teóricos y prácticos, proyectos y evaluación por competencias.";
	            }
	        }
	    }

	    echo json_encode(['success' => true, 'data' => $detalle]);
	    exit;
	}

	// Acción: inscribir usuario en una línea de énfasis
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'inscribir_linea') {
	    $usuario_id = intval($_POST['usuario_id'] ?? 0);
	    $linea_id = intval($_POST['linea_id'] ?? 0);

	    if ($usuario_id <= 0 || $linea_id <= 0) {
	        echo json_encode(['success' => false, 'message' => 'Datos inválidos.']);
	        exit;
	    }

	    // Verificar existencia usuario
	    $v1 = $conn->prepare("SELECT id FROM usuarios WHERE id = ? LIMIT 1");
	    $v1->bind_param('i', $usuario_id);
	    $v1->execute();
	    if ($v1->get_result()->num_rows === 0) {
	        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado.']);
	        exit;
	    }

	    // Verificar existencia línea
	    $v2 = $conn->prepare("SELECT id, cupos FROM lineas_enfasis WHERE id = ? LIMIT 1");
	    $v2->bind_param('i', $linea_id);
	    $v2->execute();
	    $row = $v2->get_result()->fetch_assoc();
	    if (!$row) {
	        echo json_encode(['success' => false, 'message' => 'Línea de énfasis no encontrada.']);
	        exit;
	    }

	    // Opcional: comprobar cupos (si cupos > 0)
	    $cupos = intval($row['cupos']);
	    if ($cupos > 0) {
	        // contar inscripciones confirmadas
	        $cstmt = $conn->prepare("SELECT COUNT(*) AS c FROM inscripciones WHERE linea_enfasis_id = ? AND estado = 'confirmada'");
	        $cstmt->bind_param('i', $linea_id);
	        $cstmt->execute();
	        $count = $cstmt->get_result()->fetch_assoc()['c'] ?? 0;
	        if ($count >= $cupos) {
	            echo json_encode(['success' => false, 'message' => 'No hay cupos disponibles.']);
	            exit;
	        }
	    }

	    // Insertar inscripción
	    $ist = $conn->prepare("INSERT INTO inscripciones (usuario_id, linea_enfasis_id, estado) VALUES (?, ?, 'confirmada')");
	    if (!$ist) {
	        error_log('inscribir_linea prepare failed: ' . $conn->error);
	        echo json_encode(['success' => false, 'message' => 'Error al inscribir.']);
	        exit;
	    }
	    $ist->bind_param('ii', $usuario_id, $linea_id);
	    $ok = $ist->execute();
	    echo json_encode(['success' => $ok, 'message' => $ok ? 'Inscripción realizada.' : 'Error al inscribir.']);
	    exit;
	}

	// Acción: solicitar inscripción en una línea de énfasis (crea solicitud para que el coordinador la procese)
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'solicitar_inscripcion_linea') {
	    try {
	        $usuario_id = intval($_POST['usuario_id'] ?? 0);
	        $linea_id = intval($_POST['linea_id'] ?? 0);

	        if ($usuario_id <= 0 || $linea_id <= 0) {
	            echo json_encode(['success' => false, 'message' => 'Datos inválidos.']);
	            exit;
	        }

	        // verificar usuario
	        $v = $conn->prepare("SELECT id FROM usuarios WHERE id = ? LIMIT 1");
	        if (!$v) {
	            error_log('solicitar_inscripcion_linea prepare verificar usuario error: ' . $conn->error);
	            http_response_code(500);
	            $resp = ['success' => false, 'message' => 'Error interno al validar usuario.'];
	            if ($DEBUG) $resp['detail'] = $conn->error;
	            echo json_encode($resp);
	            exit;
	        }
	        $v->bind_param('i', $usuario_id);
	        $v->execute();
	        if ($v->get_result()->num_rows === 0) {
	            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado.']);
	            exit;
	        }

	        // verificar línea
	        $v2 = $conn->prepare("SELECT id, nombre FROM lineas_enfasis WHERE id = ? LIMIT 1");
	        if (!$v2) {
	            error_log('solicitar_inscripcion_linea prepare verificar linea error: ' . $conn->error);
	            http_response_code(500);
	            $resp = ['success' => false, 'message' => 'Error interno al validar línea.'];
	            if ($DEBUG) $resp['detail'] = $conn->error;
	            echo json_encode($resp);
	            exit;
	        }
	        $v2->bind_param('i', $linea_id);
	        $v2->execute();
	        $lineRow = $v2->get_result()->fetch_assoc();
	        if (!$lineRow) {
	            echo json_encode(['success' => false, 'message' => 'Línea no encontrada.']);
	            exit;
	        }

	        // Comprobar si la columna linea_enfasis_id existe en la tabla solicitudes
	        $colCheckRes = $conn->query("SHOW COLUMNS FROM solicitudes LIKE 'linea_enfasis_id'");
	        $hasColumn = ($colCheckRes && $colCheckRes->num_rows > 0);

	        // Preparar descripción legible
	        $desc = "Solicitud de inscripción en línea de énfasis: " . ($lineRow['nombre'] ?? $linea_id) . " (id: {$linea_id})";

	        if ($hasColumn) {
	            // Intentar insertar incluyendo la columna linea_enfasis_id
	            $ist = $conn->prepare("INSERT INTO solicitudes (remitente_id, curso_id, tipo, descripcion, linea_enfasis_id, estado) VALUES (?, NULL, 'Inscripción LE', ?, ?, 'pendiente')");
	            if (!$ist) {
	                $err = $conn->error;
	                error_log('solicitar_inscripcion_linea prepare insertar (con columna) error: ' . $err);
	                http_response_code(500);
	                $resp = ['success' => false, 'message' => 'Error al crear la solicitud.'];
	                if ($DEBUG) $resp['detail'] = $err;
	                echo json_encode($resp);
	                exit;
	            }
	            $ist->bind_param('isi', $usuario_id, $desc, $linea_id);
	            $ok = $ist->execute();
	            if ($ok) {
	                echo json_encode(['success' => true, 'message' => 'Solicitud enviada al coordinador.', 'solicitud_id' => $conn->insert_id]);
	            } else {
	                $errMsg = $ist->error ?: $conn->error;
	                error_log('solicitar_inscripcion_linea execute (con columna) error: ' . $errMsg . ' / errno: ' . $conn->errno);
	                http_response_code(500);
	                $resp = ['success' => false, 'message' => 'Error al crear la solicitud en la base de datos.'];
	                if ($DEBUG) $resp['detail'] = substr($errMsg, 0, 512);
	                else $resp['detail_code'] = 'DB_ERR_' . ($conn->errno ?: 'UNKNOWN');
	                echo json_encode($resp);
	            }
	            exit;
	        } else {
	            // Si no existe la columna, insertar sin ella (guardando info de la línea en la descripción)
	            $ist = $conn->prepare("INSERT INTO solicitudes (remitente_id, curso_id, tipo, descripcion, estado) VALUES (?, NULL, 'Inscripción LE', ?, 'pendiente')");
	            if (!$ist) {
	                $err = $conn->error;
	                error_log('solicitar_inscripcion_linea prepare insertar (sin columna) error: ' . $err);
	                http_response_code(500);
	                $resp = ['success' => false, 'message' => 'Error al crear la solicitud (estructura antigua de BD).'];
	                if ($DEBUG) $resp['detail'] = $err;
	                echo json_encode($resp);
	                exit;
	            }
	            $ist->bind_param('is', $usuario_id, $desc);
	            $ok = $ist->execute();
	            if ($ok) {
	                echo json_encode(['success' => true, 'message' => 'Solicitud enviada al coordinador (sin enlace directo a la línea). El coordinador deberá asociarla manualmente.', 'solicitud_id' => $conn->insert_id ]);
	            } else {
	                $errMsg = $ist->error ?: $conn->error;
	                error_log('solicitar_inscripcion_linea execute (sin columna) error: ' . $errMsg . ' / errno: ' . $conn->errno);
	                http_response_code(500);
	                $resp = ['success' => false, 'message' => 'Error al crear la solicitud en la base de datos.'];
	                if ($DEBUG) $resp['detail'] = substr($errMsg, 0, 512);
	                else $resp['detail_code'] = 'DB_ERR_' . ($conn->errno ?: 'UNKNOWN');
	                echo json_encode($resp);
	            }
	            exit;
	        }
	    } catch (Exception $ex) {
	        error_log('solicitar_inscripcion_linea exception: ' . $ex->getMessage());
	        http_response_code(500);
	        $resp = ['success' => false, 'message' => 'Error interno del servidor.'];
	        if ($DEBUG) $resp['detail'] = $ex->getMessage();
	        echo json_encode($resp);
	        exit;
	    }
	}

	// Nueva acción: obtener mis notas (estudiante)
	if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'mis_notas' && isset($_GET['usuario_id'])) {
	    $uid = intval($_GET['usuario_id']);
	    if ($uid <= 0) { echo json_encode(['success' => false, 'message' => 'ID de usuario inválido']); exit; }
	    $sql = "SELECT cal.id AS calificacion_id, cal.nota,
	                   c.id AS curso_id, c.nombre AS curso_nombre, c.codigo AS curso_codigo, c.semestre
	            FROM calificaciones cal
	            JOIN cursos c ON cal.curso_id = c.id
	            WHERE cal.estudiante_id = ?
	            ORDER BY c.semestre DESC, c.nombre ASC";
	    $stmt = $conn->prepare($sql);
	    if (!$stmt) { echo json_encode(['success'=>false,'message'=>'Error interno al preparar consulta','detail'=>$conn->error]); exit; }
	    $stmt->bind_param('i', $uid);
	    $stmt->execute();
	    $res = $stmt->get_result();
	    $out = [];
	    while ($r = $res->fetch_assoc()) $out[] = $r;
	    echo json_encode(['success'=>true,'data'=>$out]);
	    exit;
	}

	// Nueva acción: listar solicitudes de un remitente (útil para verificar desde UI estudiante)
	if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'mis_solicitudes' && isset($_GET['usuario_id'])) {
	    $uid = intval($_GET['usuario_id']);
	    if ($uid <= 0) { echo json_encode(['success' => false, 'message' => 'ID inválido']); exit; }
	    $sql = "SELECT id, tipo, descripcion, estado, fecha, linea_enfasis_id FROM solicitudes WHERE remitente_id = ? ORDER BY fecha DESC LIMIT 50";
	    $stmt = $conn->prepare($sql);
	    if (!$stmt) {
	        echo json_encode(['success' => false, 'message' => 'Error interno al preparar consulta.']);
	        exit;
	    }
	    $stmt->bind_param('i', $uid);
	    $stmt->execute();
	    $res = $stmt->get_result();
	    $list = [];
	    while ($r = $res->fetch_assoc()) $list[] = $r;
	    echo json_encode(['success' => true, 'data' => $list]);
	    exit;
	}

	// Acción: obtener la(s) línea(s) confirmadas de un usuario
	if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get_mi_linea' && isset($_GET['usuario_id'])) {
	    $uid = intval($_GET['usuario_id']);
	    if ($uid <= 0) { echo json_encode(['success' => false]); exit; }
	    $sql = "SELECT i.id AS inscripcion_id, le.id AS linea_id, le.nombre, le.duracion, le.creditos, le.cupos, le.descripcion
	            FROM inscripciones i
	            JOIN lineas_enfasis le ON i.linea_enfasis_id = le.id
	            WHERE i.usuario_id = ? AND i.estado = 'confirmada'
	            ORDER BY i.fecha DESC";
	    $stmt = $conn->prepare($sql);
	    $stmt->bind_param('i', $uid);
	    $stmt->execute();
	    $res = $stmt->get_result();
	    $lista = [];
	    while ($r = $res->fetch_assoc()) $lista[] = $r;
	    echo json_encode(['success' => true, 'data' => $lista]);
	    exit;
	}

	// Acción: listar cursos de la línea del usuario para un semestre dado
	if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list_cursos_mi_linea' && isset($_GET['usuario_id']) && isset($_GET['semestre'])) {
	    $uid = intval($_GET['usuario_id']);
	    $sem = trim($_GET['semestre']);
	    if ($uid <= 0 || $sem === '') { echo json_encode(['success' => false]); exit; }
	    // obtener la primera línea confirmada del usuario
	    $q = $conn->prepare("SELECT linea_enfasis_id FROM inscripciones WHERE usuario_id = ? AND estado = 'confirmada' ORDER BY fecha DESC LIMIT 1");
	    $q->bind_param('i', $uid);
	    $q->execute();
	    $row = $q->get_result()->fetch_assoc();
	    if (!$row) { echo json_encode(['success' => true, 'data' => []]); exit; }
	    $linea = intval($row['linea_enfasis_id']);
	    $stmt = $conn->prepare("SELECT c.id, c.nombre, c.codigo, c.semestre, u.nombre AS profesor_nombre FROM cursos c JOIN usuarios u ON c.profesor_id = u.id WHERE c.linea_enfasis_id = ? AND c.semestre = ? ORDER BY c.nombre ASC");
	    $stmt->bind_param('is', $linea, $sem);
	    $stmt->execute();
	    $res = $stmt->get_result();
	    $cursos = [];
	    while ($r = $res->fetch_assoc()) $cursos[] = $r;
	    echo json_encode(['success' => true, 'data' => $cursos]);
	    exit;
	}

	// Listar cursos asignados a un profesor (incluye dia y horario)
	if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list_mis_cursos_profesor' && isset($_GET['profesor_id'])) {
	    $pid = intval($_GET['profesor_id']);
	    if ($pid <= 0) { echo json_encode(['success' => false, 'message' => 'Profesor inválido']); exit; }
	    $sql = "SELECT id, nombre, codigo, semestre, linea_enfasis_id, dia, hora_inicio, hora_fin FROM cursos WHERE profesor_id = ? ORDER BY semestre DESC, nombre ASC";
	    $stmt = $conn->prepare($sql);
	    if (!$stmt) { echo json_encode(['success'=>false,'message'=>'Error interno','detail'=>$conn->error]); exit; }
	    $stmt->bind_param('i', $pid);
	    $stmt->execute();
	    $res = $stmt->get_result();
	    $out = [];
	    while ($r = $res->fetch_assoc()) $out[] = $r;
	    echo json_encode(['success'=>true,'data'=>$out]);
	    exit;
	}

	// Listar estudiantes inscritos en un curso y sus calificaciones (si existen)
	if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list_estudiantes_curso' && isset($_GET['curso_id'])) {
	    $cid = intval($_GET['curso_id']);
	    if ($cid <= 0) { echo json_encode(['success'=>false,'message'=>'Curso inválido']); exit; }
	    $sql = "SELECT u.id AS estudiante_id, u.nombre, u.documento,
	                   cals.nota
	            FROM inscripciones ins
	            JOIN usuarios u ON ins.usuario_id = u.id
	            LEFT JOIN calificaciones cals ON cals.curso_id = ? AND cals.estudiante_id = u.id
	            WHERE ins.linea_enfasis_id = (SELECT linea_enfasis_id FROM cursos WHERE id = ?) OR ins.usuario_id IN (
	                  SELECT usuario_id FROM inscripciones WHERE linea_enfasis_id = (SELECT linea_enfasis_id FROM cursos WHERE id = ?)
	            )
	            /* Fallback simple: también listar inscripciones por curso en caso de existir relación explícita */
	            ";
	    // Simplificar: obtener estudiantes que estén inscritos en la misma línea o, si existen inscripciones por curso, obtenerlas
	    // Ejecutar dos consultas: primero por inscripciones directas a inscripciones.linea_enfasis_id, si no hay, intentar otras fuentes.
	    try {
	        // Intento: obtener estudiantes relacionados por línea_enfasis
	        $q1 = $conn->prepare("SELECT le.id FROM cursos c JOIN lineas_enfasis le ON c.linea_enfasis_id = le.id WHERE c.id = ? LIMIT 1");
	        $q1->bind_param('i', $cid);
	        $q1->execute();
	        $lr = $q1->get_result()->fetch_assoc();
	        $linea_id = intval($lr['id'] ?? 0);
	        $students = [];
	        if ($linea_id > 0) {
	            $s1 = $conn->prepare("SELECT u.id AS estudiante_id, u.nombre, u.documento, cals.nota
	                                  FROM inscripciones ins
	                                  JOIN usuarios u ON ins.usuario_id = u.id
	                                  LEFT JOIN calificaciones cals ON cals.curso_id = ? AND cals.estudiante_id = u.id
	                                  WHERE ins.linea_enfasis_id = ?
	                                  ORDER BY u.nombre ASC");
	            $s1->bind_param('ii', $cid, $linea_id);
	            $s1->execute();
	            $res = $s1->get_result();
	            while ($r = $res->fetch_assoc()) $students[] = $r;
	        }
	        // Si no hay estudiantes por linea, intentar por inscripciones directas relacionadas al curso (si aplica)
	        if (empty($students)) {
	            $s2 = $conn->prepare("SELECT u.id AS estudiante_id, u.nombre, u.documento, cals.nota
	                                  FROM usuarios u
	                                  LEFT JOIN calificaciones cals ON cals.curso_id = ? AND cals.estudiante_id = u.id
	                                  WHERE u.rol = 'estudiante'
	                                  ORDER BY u.nombre ASC
	                                  LIMIT 200");
	            $s2->bind_param('i', $cid);
	            $s2->execute();
	            $res2 = $s2->get_result();
	            while ($r = $res2->fetch_assoc()) $students[] = $r;
	        }
	        echo json_encode(['success'=>true,'data'=>$students]);
	    } catch (Exception $e) {
	        error_log('list_estudiantes_curso exception: ' . $e->getMessage());
	        echo json_encode(['success'=>false,'message'=>'Error interno','detail'=>$e->getMessage()]);
	    }
	    exit;
	}

	// Guardar o actualizar una nota para un estudiante en un curso
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'guardar_nota') {
	    $curso_id = intval($_POST['curso_id'] ?? 0);
	    $estudiante_id = intval($_POST['estudiante_id'] ?? 0);
	    $nota = $_POST['nota'] ?? null;
	    if ($curso_id <= 0 || $estudiante_id <= 0) { echo json_encode(['success'=>false,'message'=>'Datos inválidos']); exit; }
	    // normalizar nota
	    $nota_val = is_numeric($nota) ? floatval($nota) : null;
	    try {
	        // usar INSERT ... ON DUPLICATE KEY UPDATE (calificaciones tiene unique)
	        $stmt = $conn->prepare("INSERT INTO calificaciones (curso_id, estudiante_id, nota) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nota = VALUES(nota), fecha_actualizacion = NOW()");
	        if (!$stmt) { error_log('guardar_nota prepare: ' . $conn->error); echo json_encode(['success'=>false,'message'=>'Error interno']); exit; }
	        // permitir NULL para nota: bind_param requiere tipos; usar 'd' para decimal y pasar null como null_value handling
	        if ($nota_val === null) {
	            $nullNota = null;
	            $stmt->bind_param('iid', $curso_id, $estudiante_id, $nota_val);
	        } else {
	            $stmt->bind_param('iid', $curso_id, $estudiante_id, $nota_val);
	        }
	        $ok = $stmt->execute();
	        if ($ok) echo json_encode(['success'=>true]);
	        else echo json_encode(['success'=>false,'message'=>$stmt->error]);
	    } catch (Exception $e) {
	        error_log('guardar_nota exception: ' . $e->getMessage());
	        echo json_encode(['success'=>false,'message'=>'Error interno']);
	    }
	    exit;
	}

	// Registrar inasistencia / reportar falta
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'registrar_inasistencia') {
	    $curso_id = intval($_POST['curso_id'] ?? 0);
	    $estudiante_id = intval($_POST['estudiante_id'] ?? 0);
	    $fecha = $_POST['fecha'] ?? null;
	    $motivo = trim($_POST['motivo'] ?? '');
	    $reportado_por = intval($_POST['reportado_por'] ?? 0);
	    if ($curso_id <= 0 || $estudiante_id <= 0 || !$fecha) { echo json_encode(['success'=>false,'message'=>'Datos inválidos']); exit; }
	    try {
	        $stmt = $conn->prepare("INSERT INTO asistencias (curso_id, estudiante_id, fecha, falta, motivo, reportado_por) VALUES (?, ?, ?, 1, ?, ?)");
	        if (!$stmt) { error_log('registrar_inasistencia prepare: ' . $conn->error); echo json_encode(['success'=>false,'message'=>'Error interno']); exit; }
	        $stmt->bind_param('iissi', $curso_id, $estudiante_id, $fecha, $motivo, $reportado_por);
	        $ok = $stmt->execute();
	        echo json_encode(['success'=> (bool)$ok]);
	    } catch (Exception $e) {
	        error_log('registrar_inasistencia exception: ' . $e->getMessage());
	        echo json_encode(['success'=>false,'message'=>'Error interno']);
	    }
	    exit;
	}

	// Enviar reporte formal al coordinador (inasistencias/ notas u otro)
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'enviar_reporte_coordinador') {
	    $curso_id = intval($_POST['curso_id'] ?? 0);
	    $tipo = $_POST['tipo'] ?? 'otro';
	    $contenido = trim($_POST['contenido'] ?? '');
	    $enviado_por = intval($_POST['enviado_por'] ?? 0);
	    if ($curso_id <= 0 || !$contenido || $enviado_por <= 0) { echo json_encode(['success'=>false,'message'=>'Datos inválidos']); exit; }
	    try {
	        $stmt = $conn->prepare("INSERT INTO reportes_coordinador (curso_id, tipo, contenido, enviado_por) VALUES (?, ?, ?, ?)");
	        if (!$stmt) { error_log('enviar_reporte_coordinador prepare: ' . $conn->error); echo json_encode(['success'=>false,'message'=>'Error interno']); exit; }
	        $stmt->bind_param('issi', $curso_id, $tipo, $contenido, $enviado_por);
	        $ok = $stmt->execute();
	        echo json_encode(['success'=> (bool)$ok]);
	    } catch (Exception $e) {
	        error_log('enviar_reporte_coordinador exception: ' . $e->getMessage());
	        echo json_encode(['success'=>false,'message'=>'Error interno']);
	    }
	    exit;
	}

	// Acción no reconocida
	echo json_encode(['success' => false, 'message' => 'Acción inválida.']);
	exit;
} catch (Exception $e) {
    // Registrar detalle para diagnóstico (no exponer todo al cliente)
    error_log('coordinador_actions exception: ' . $e->getMessage());
    // Responder JSON consistente; incluir mensaje de excepción cuando DEBUG=true
    http_response_code(500);
    $resp = ['success' => false, 'message' => 'Error interno del servidor. Revise la consola o contacte al administrador.'];
    if ($DEBUG) $resp['detail'] = $e->getMessage();
    echo json_encode($resp);
    exit;
}
?>
