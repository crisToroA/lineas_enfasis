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
	            // CORRECCIÓN: tipos correctos: estado (s), comentario (s), aprobador_id (i), solicitud_id (i)
	            $sql = "UPDATE solicitudes SET estado = ?, comentario_coordinador = ?, aprobador_id = ?, fecha_procesamiento = NOW() WHERE id = ?";
	            $stmt = $conn->prepare($sql);
	            if ($stmt === false) {
	                error_log('validar_solicitud prepare (with approver) failed: ' . $conn->error);
	                http_response_code(500);
	                echo json_encode(['success' => false, 'message' => 'Error interno al preparar consulta.', 'detail' => $conn->error]);
	                exit;
	            }
	            $stmt->bind_param('ssii', $estado, $comentario, $aprobador_id, $solicitud_id);
	        } else {
	            // tipos: estado (s), comentario (s), solicitud_id (i)
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

	        // si se aprobó, crear efectos secundarios según tipo
	        $applied = [];
	        if ($estado === 'aprobada') {
	            $q2 = $conn->prepare("SELECT remitente_id, tipo, linea_enfasis_id, curso_id FROM solicitudes WHERE id = ? LIMIT 1");
	            if ($q2) {
	                $q2->bind_param('i', $solicitud_id);
	                $q2->execute();
	                $row = $q2->get_result()->fetch_assoc();
	                $q2->close();
	            } else {
	                $row = null;
	                error_log('validar_solicitud q2 prepare failed: ' . $conn->error);
	            }

	            if ($row) {
	                $usuario = intval($row['remitente_id'] ?? 0);
	                $tipo = strtolower(trim($row['tipo'] ?? ''));
	                $linea = intval($row['linea_enfasis_id'] ?? 0);
	                $curso = intval($row['curso_id'] ?? 0);

	                // 1) Inscripción en línea de énfasis
	                if (strpos($tipo, 'inscrip') !== false || $tipo === 'inscripción le') {
	                    if ($usuario > 0 && $linea > 0) {
	                        $check = $conn->prepare("SELECT id FROM inscripciones WHERE usuario_id = ? AND linea_enfasis_id = ? AND estado = 'confirmada' LIMIT 1");
	                        if ($check) {
	                            $check->bind_param('ii', $usuario, $linea);
	                            $check->execute();
	                            if ($check->get_result()->num_rows === 0) {
	                                $ins = $conn->prepare("INSERT INTO inscripciones (usuario_id, linea_enfasis_id, estado) VALUES (?, ?, 'confirmada')");
	                                if ($ins) { $ins->bind_param('ii', $usuario, $linea); $ins->execute(); $ins->close(); $applied[] = 'inscripcion_confirmada'; }
	                            } else {
	                                $applied[] = 'inscripcion_existente';
	                            }
	                            $check->close();
	                        }
	                    }
	                }

	                // 2) Cambio de línea / carrera: cancelar inscripciones previas y crear nueva confirmada
	                if (strpos($tipo, 'cambio') !== false && $linea > 0 && $usuario > 0) {
	                    // cancelar otras inscripciones confirmadas
	                    $upd = $conn->prepare("UPDATE inscripciones SET estado = 'cancelada' WHERE usuario_id = ? AND estado = 'confirmada'");
	                    if ($upd) { $upd->bind_param('i', $usuario); $upd->execute(); $upd->close(); }
	                    // insertar nueva confirmada si no existe
	                    $chk2 = $conn->prepare("SELECT id FROM inscripciones WHERE usuario_id = ? AND linea_enfasis_id = ? LIMIT 1");
	                    if ($chk2) {
	                        $chk2->bind_param('ii', $usuario, $linea);
	                        $chk2->execute();
	                        if ($chk2->get_result()->num_rows === 0) {
	                            $ins2 = $conn->prepare("INSERT INTO inscripciones (usuario_id, linea_enfasis_id, estado) VALUES (?, ?, 'confirmada')");
	                            if ($ins2) { $ins2->bind_param('ii', $usuario, $linea); $ins2->execute(); $ins2->close(); $applied[] = 'cambio_linea_aplicado'; }
	                        } else {
	                            $applied[] = 'cambio_linea_existente';
	                        }
	                        $chk2->close();
	                    }
	                }

	                // 3) Homologación: si está asociado a un curso, registrar nota máxima (o marca de homologación)
	                if (strpos($tipo, 'homolog') !== false && $curso > 0 && $usuario > 0) {
	                    // insertar/actualizar calificación con nota 100 como evidencia de homologación
	                    $stmtCal = $conn->prepare("INSERT INTO calificaciones (curso_id, estudiante_id, nota) VALUES (?, ?, 100) ON DUPLICATE KEY UPDATE nota = VALUES(nota), fecha_actualizacion = NOW()");
	                    if ($stmtCal) {
	                        $stmtCal->bind_param('ii', $curso, $usuario);
	                        $stmtCal->execute();
	                        $stmtCal->close();
	                        $applied[] = 'homologacion_registrada';
	                    }
	                }

	                // 4) Aplazamiento: si existe curso asociado, registrar un reporte y una entrada en asistencias (motivo: aplazamiento aprobado)
	                if (strpos($tipo, 'aplaz') !== false && $curso > 0 && $usuario > 0) {
	                    // crear reporte para coordinación (registro histórico)
	                    $content = "Aplazamiento aprobado para usuario_id={$usuario} en curso_id={$curso}. Comentario: {$comentario}";
	                    $rstmt = $conn->prepare("INSERT INTO reportes_coordinador (curso_id, tipo, contenido, enviado_por) VALUES (?, 'otro', ?, ?)");
	                    if ($rstmt) { $rstmt->bind_param('isi', $curso, $content, $aprobador_id); $rstmt->execute(); $rstmt->close(); $applied[] = 'aplazamiento_reportado'; }
	                    // opcional: registrar entrada en asistencias con falta=0 y motivo
	                    $ast = $conn->prepare("INSERT INTO asistencias (curso_id, estudiante_id, fecha, falta, motivo, reportado_por) VALUES (?, ?, CURDATE(), 0, ?, ?)");
	                    if ($ast) { $mot = "Aplazamiento aprobado"; $ast->bind_param('iisi', $curso, $usuario, $mot, $aprobador_id); $ast->execute(); $ast->close(); $applied[] = 'aplazamiento_registrado'; }
	                }

	                // 5) Suplemento de créditos: registrar reporte para seguimiento por coordinación
	                if ((strpos($tipo, 'suplemento') !== false || strpos($tipo, 'suplemento de credit') !== false) && $usuario > 0) {
	                    $content = "Suplemento de créditos aprobado para usuario_id={$usuario}. Comentario: {$comentario}";
	                    $rs = $conn->prepare("INSERT INTO reportes_coordinador (curso_id, tipo, contenido, enviado_por) VALUES (NULL, 'otro', ?, ?)");
	                    if ($rs) { $rs->bind_param('si', $content, $aprobador_id); $rs->execute(); $rs->close(); $applied[] = 'suplemento_reportado'; }
	                }
	            }
	        }

	        echo json_encode(['success' => true, 'affected_rows' => $affected, 'applied' => $applied]);
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

	// Nueva acción: obtener actividades pendientes del estudiante (por línea o curso)
	if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'mis_actividades' && isset($_GET['usuario_id'])) {
	    $uid = intval($_GET['usuario_id']);
	    if ($uid <= 0) { echo json_encode(['success'=>false,'message'=>'ID inválido']); exit; }

	    // obtener líneas confirmadas del usuario
	    $q = $conn->prepare("SELECT linea_enfasis_id FROM inscripciones WHERE usuario_id = ? AND estado = 'confirmada'");
	    $q->bind_param('i', $uid);
	    $q->execute();
	    $res = $q->get_result();
	    $lineas = [];
	    while ($r = $res->fetch_assoc()) $lineas[] = intval($r['linea_enfasis_id']);
	    $q->close();

	    // obtener cursos relacionados (opcional)
	    $q2 = $conn->prepare("SELECT c.id FROM cursos c WHERE c.linea_enfasis_id IN (" . (count($lineas)? implode(',', array_map('intval',$lineas)) : "0") . ")");
	    // si no hay líneas, devolvemos vacío
	    $activities = [];
	    try {
	        if (count($lineas) === 0) {
	            echo json_encode(['success'=>true,'data'=>[]]);
	            exit;
	        }
	        $q2->execute();
	        $q2->close();

	        // Consultar actividades por línea o por curso
	        $inLineas = implode(',', array_map('intval',$lineas));
	        $sql = "SELECT id, titulo, descripcion, fecha_entrega, linea_enfasis_id, curso_id, prioridad FROM actividades
	                WHERE (linea_enfasis_id IN ($inLineas)) 
	                   OR (curso_id IN (SELECT id FROM cursos WHERE linea_enfasis_id IN ($inLineas)))
	                ORDER BY fecha_entrega ASC";
	        $stmt = $conn->prepare($sql);
	        $stmt->execute();
	        $rset = $stmt->get_result();
	        while ($a = $rset->fetch_assoc()) $activities[] = $a;
	        echo json_encode(['success'=>true,'data'=>$activities]);
	    } catch (Exception $e) {
	        error_log('mis_actividades exception: ' . $e->getMessage());
	        echo json_encode(['success'=>false,'message'=>'Error interno','detail'=>$e->getMessage()]);
	    }
	    exit;
	}

	// Nueva acción: crear solicitud genérica desde UI estudiante (tipo + descripcion + opcional linea/curso)
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'crear_solicitud') {
	    $remitente = intval($_POST['remitente_id'] ?? 0);
	    $tipo = trim($_POST['tipo'] ?? '');
	    $descripcion = trim($_POST['descripcion'] ?? '');
	    $linea_id = isset($_POST['linea_enfasis_id']) ? intval($_POST['linea_enfasis_id']) : null;
	    $curso_id = isset($_POST['curso_id']) ? intval($_POST['curso_id']) : null;

	    if ($remitente <= 0 || $tipo === '') {
	        echo json_encode(['success'=>false,'message'=>'Datos inválidos']);
	        exit;
	    }

	    try {
	        $sql = "INSERT INTO solicitudes (remitente_id, curso_id, tipo, descripcion, linea_enfasis_id, estado) VALUES (?, ?, ?, ?, ?, 'pendiente')";
	        $stmt = $conn->prepare($sql);
	        if (!$stmt) { error_log('crear_solicitud prepare: '.$conn->error); echo json_encode(['success'=>false,'message'=>'Error interno']); exit; }
	        // permitir NULLs para curso/linea
	        $ci = $curso_id > 0 ? $curso_id : null;
	        $li = $linea_id > 0 ? $linea_id : null;
	        $stmt->bind_param('iisss', $remitente, $ci, $tipo, $descripcion, $li);
	        $ok = $stmt->execute();
	        if ($ok) echo json_encode(['success'=>true,'solicitud_id'=>$conn->insert_id]);
	        else echo json_encode(['success'=>false,'message'=>$stmt->error]);
	    } catch (Exception $e) {
	        error_log('crear_solicitud exception: ' . $e->getMessage());
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
