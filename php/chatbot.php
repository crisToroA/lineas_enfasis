<?php
header('Content-Type: application/json; charset=utf-8');

// Intentar conocer rol del usuario (si se proporciona usuario_id)
$usuario_id = intval($_POST['usuario_id'] ?? 0);
$role = 'guest';
try {
    if (file_exists(__DIR__ . '/conexion.php')) {
        require_once __DIR__ . '/conexion.php';
        if (isset($conn) && $usuario_id > 0) {
            $q = $conn->prepare("SELECT rol FROM usuarios WHERE id = ? LIMIT 1");
            if ($q) {
                $q->bind_param('i', $usuario_id);
                $q->execute();
                $r = $q->get_result()->fetch_assoc();
                if ($r && !empty($r['rol'])) $role = $r['rol'];
                $q->close();
            }
        }
    }
} catch (Throwable $e) {
    $role = 'guest';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$input = trim((string)($_POST['q'] ?? ''));
$input_lc = mb_strtolower($input, 'UTF-8');

// mapa de permisos por enlace (conservador)
$linkRoles = [
    '../dashboards/coordinador_dashboard.html' => ['coordinador'],
    '../dashboards/teacher_dashboard.html' => ['profesor'],
    '../dashboards/explorar_LE_estudiante.html' => ['estudiante','profesor','coordinador'],
    '../index.html' => ['guest','estudiante','profesor','coordinador'],
];

// Menús por rol (código, pregunta, respuesta y enlaces opcionales)
$menus = [
    'estudiante' => [
        ['code'=>'1.1','q'=>'¿Cómo veo mis notas?','answer'=>'Puedes ver tus notas en "Mis Notas" dentro del portal. Ahí se muestran las calificaciones registradas por tus profesores.'],
        ['code'=>'1.2','q'=>'¿Cómo solicitar una inscripción o cambio de línea?','answer'=>'Para solicitar inscripción o cambios debes crear una solicitud desde la sección correspondiente; el coordinador la revisará y responderá. Revisa "Mis Solicitudes" para ver el estado.'],
        ['code'=>'1.3','q'=>'¿Dónde veo el estado de mis solicitudes?','answer'=>'En la opción "Mis Solicitudes" encontrarás el estado (pendiente/aprobada/rechazada) y el comentario del coordinador cuando se procese.'],
        ['code'=>'1.4','q'=>'¿Cómo recuperar mi contraseña?','answer'=>'Usa la opción "¿Olvidó su contraseña?" en la pantalla de inicio y sigue las instrucciones. Si no funciona, contacta al coordinador o al administrador.'],
        ['code'=>'1.5','q'=>'¿Qué es una línea de énfasis?','answer'=>'Una línea de énfasis es un conjunto de asignaturas y proyectos enfocados en un área temática. Consulta "Explorar Líneas de Énfasis" para más detalles.','links'=>['../dashboards/explorar_LE_estudiante.html'=>'Explorar Líneas de Énfasis']],
    ],
    'profesor' => [
        ['code'=>'2.1','q'=>'¿Cómo gestiono las calificaciones?','answer'=>'Como profesor, en tu panel encontrarás "Calificaciones" donde puedes ver los estudiantes de cada curso y asignar o actualizar notas.'],
        ['code'=>'2.2','q'=>'¿Cómo reporto inasistencias o problemas?','answer'=>'En la sección de reportes del panel del profesor puedes generar un informe para el coordinador sobre inasistencias o casos que requieran revisión.'],
        ['code'=>'2.3','q'=>'¿Dónde veo mis cursos y horarios?','answer'=>'En "Cronogramas" de tu panel verás tus cursos asignados y sus horarios en el calendario.'],
    ],
    'coordinador' => [
        ['code'=>'3.1','q'=>'¿Cómo registro un curso?','answer'=>'Desde tu panel tienes la opción "Registrar Curso" para crear cursos, asignar profesor y línea de énfasis. Realiza registros con códigos únicos.','links'=>['../dashboards/coordinador_dashboard.html'=>'Ir a Registrar Curso']],
        ['code'=>'3.2','q'=>'¿Cómo proceso solicitudes?','answer'=>'En "Validar Solicitudes" verás las pendientes; al procesarlas debes dejar un comentario al aprobar o rechazar.','links'=>['../dashboards/coordinador_dashboard.html'=>'Ir a Validar Solicitudes']],
        ['code'=>'3.3','q'=>'¿Cómo genero reportes o reviso incidencias?','answer'=>'Puedes revisar los reportes enviados por profesores (inasistencias, notas) y tomar las acciones administrativas necesarias.'],
    ],
];

// Mensaje inicial / instrucción para generar menú
function buildRoleMenu($role, $menus, $linkRoles) {
    $items = $menus[$role] ?? [];
    $lines = ["Menú para rol: " . ucfirst($role) . ". Escribe el código de la opción (por ejemplo: 1.1):"];
    foreach ($items as $it) $lines[] = $it['code'] . " — " . $it['q'];
    $lines[] = 'Si quieres ver el menú principal escribe "menu".';
    return implode("\n", $lines);
}

// Si el usuario pide el menú o no envió texto: mostrar menú acorde al rol (si es guest mostrar menú principal)
if ($input === '' || in_array($input_lc, ['menu','menú','inicio'])) {
    if ($role === 'guest') {
        $top = "Menú principal:\n1 — Soy Estudiante\n2 — Soy Profesor\n3 — Soy Coordinador\nEscribe el número para ver las dudas frecuentes de ese rol (ej.: 1).";
        echo json_encode(['success'=>true,'answer'=>$top,'suggestions'=>[]]);
        exit;
    } else {
        $menuText = buildRoleMenu($role, $menus, $linkRoles);
        // construir sugerencias seguras para la lista si hay links en items
        $sugs = [];
        foreach ($menus[$role] as $it) {
            if (!empty($it['links']) && is_array($it['links'])) {
                foreach ($it['links'] as $url => $label) {
                    if (isset($linkRoles[$url]) && in_array($role, $linkRoles[$url])) $sugs[$url] = $label;
                }
            }
        }
        echo json_encode(['success'=>true,'answer'=>$menuText,'suggestions'=>$sugs]);
        exit;
    }
}

// Si usuario guest elige 1/2/3 para seleccionar rol del menú principal
if ($role === 'guest' && preg_match('/^[123]$/', $input_lc)) {
    $map = ['1'=>'estudiante','2'=>'profesor','3'=>'coordinador'];
    $sel = $map[$input_lc];
    $menuText = buildRoleMenu($sel, $menus, $linkRoles);
    echo json_encode(['success'=>true,'answer'=>$menuText,'suggestions'=>[]]);
    exit;
}

// Si la entrada es un código compuesto como "1.2" o "2.1"
if (preg_match('/^(\d)\.(\d+)$/', $input_lc, $m)) {
    $major = intval($m[1]);
    $minor = intval($m[2]);
    $roleMap = [1=>'estudiante',2=>'profesor',3=>'coordinador'];
    $r = $roleMap[$major] ?? null;
    if (!$r) {
        echo json_encode(['success'=>true,'answer'=>'Código no reconocido. Escribe "menu" para ver las opciones.','suggestions'=>[]]);
        exit;
    }
    // buscar ítem con code matching
    $code = $major . '.' . $minor;
    $found = null;
    foreach ($menus[$r] as $it) {
        if ($it['code'] === $code) { $found = $it; break; }
    }
    if ($found) {
        // filtrar enlaces por permisos
        $sugs = [];
        if (!empty($found['links']) && is_array($found['links'])) {
            foreach ($found['links'] as $url => $label) {
                if (isset($linkRoles[$url]) && in_array($role, $linkRoles[$url])) $sugs[$url] = $label;
            }
        }
        echo json_encode(['success'=>true,'answer'=>$found['answer'],'suggestions'=>$sugs]);
        exit;
    } else {
        echo json_encode(['success'=>true,'answer'=>'Opción no encontrada en ese menú. Escribe el código correcto (ej.: 1.1) o "menu" para volver al inicio.','suggestions'=>[]]);
        exit;
    }
}

// Si no es un número de menú, proceder con búsqueda normal por palabras clave (segura y por rol)
$faqs = [
    ['keywords'=>['mis notas','notas','calificaciones'],'answer'=>'Los estudiantes ven sus notas en "Mis Notas". Los profesores las gestionan desde su panel.','roles'=>['estudiante','profesor','coordinador'],'links'=>['../dashboards/explorar_LE_estudiante.html'=>'Mis Notas','../dashboards/teacher_dashboard.html'=>'Panel Profesor']],
    ['keywords'=>['solicitud','solicitudes','inscripción','inscribir'],'answer'=>'Las solicitudes se crean desde la sección correspondiente y el coordinador las procesará. Revisa "Mis Solicitudes" para ver estados y respuestas.','roles'=>['estudiante','profesor','coordinador'],'links'=>['../dashboards/explorar_LE_estudiante.html'=>'Mis Solicitudes']],
    ['keywords'=>['registrar curso','nuevo curso'],'answer'=>'La creación de cursos es una función del coordinador desde su panel. Si necesitas un curso contacta al coordinador.','roles'=>['coordinador'],'links'=>['../dashboards/coordinador_dashboard.html'=>'Panel Coordinador']],
    ['keywords'=>['profesor','profesores','lista de profesores'],'answer'=>'Si necesitas información sobre docentes contacta al coordinador.','roles'=>['estudiante','profesor','coordinador'],'links'=>[]],
];

// buscar coincidencia respetando rol
$best = null;
foreach ($faqs as $f) {
    if (!in_array($role, $f['roles'])) continue;
    foreach ($f['keywords'] as $kw) {
        if (mb_strpos($input_lc, $kw, 0, 'UTF-8') !== false) { $best = $f; break 2; }
    }
}

// respuesta final
if ($best !== null) {
    // construir sugerencias seguras
    $sugs = [];
    if (!empty($best['links']) && is_array($best['links'])) {
        foreach ($best['links'] as $url => $label) {
            if (isset($linkRoles[$url]) && in_array($role, $linkRoles[$url])) $sugs[$url] = $label;
            elseif (!isset($linkRoles[$url])) {
                if (strpos($url,'coordinador')===false && strpos($url,'teacher')===false) $sugs[$url]=$label;
            }
        }
    }
    echo json_encode(['success'=>true,'answer'=>$best['answer'],'suggestions'=>$sugs]);
    exit;
} else {
    $fallback = 'No estoy seguro de la respuesta. Escribe "menu" para ver las opciones numeradas o pregunta sobre inscripciones, mis notas o solicitudes. Si tu duda requiere permisos administrativos contacta al coordinador.';
    echo json_encode(['success'=>true,'answer'=>$fallback,'suggestions'=>[]]);
    exit;
}
?>
