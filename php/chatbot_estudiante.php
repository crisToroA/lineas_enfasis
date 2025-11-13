<?php
header('Content-Type: application/json; charset=utf-8');

// Chatbot específico para ESTUDIANTES
$usuario_id = intval($_POST['usuario_id'] ?? 0);
$role = 'estudiante'; // Forzar rol de estudiante

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$input = trim((string)($_POST['q'] ?? ''));
$input_lc = mb_strtolower($input, 'UTF-8');

// mapa de permisos por enlace (conservador)
$linkRoles = [
    '../dashboards/explorar_LE_estudiante.html' => ['estudiante'],
    '../index.html' => ['estudiante'],
];

// Menú específico para estudiantes
$menuEstudiante = [
    ['code'=>'1.1','q'=>'¿Cómo veo mis notas?','answer'=>'Puedes ver tus notas en "Mis Notas" dentro del portal. Ahí se muestran las calificaciones registradas por tus profesores.'],
    ['code'=>'1.2','q'=>'¿Cómo solicitar una inscripción o cambio de línea?','answer'=>'Para solicitar inscripción o cambios debes crear una solicitud desde la sección correspondiente; el coordinador la revisará y responderá. Revisa "Mis Solicitudes" para ver el estado.'],
    ['code'=>'1.3','q'=>'¿Dónde veo el estado de mis solicitudes?','answer'=>'En la opción "Mis Solicitudes" encontrarás el estado (pendiente/aprobada/rechazada) y el comentario del coordinador cuando se procese.'],
    ['code'=>'1.4','q'=>'¿Cómo recuperar mi contraseña?','answer'=>'Usa la opción "¿Olvidó su contraseña?" en la pantalla de inicio y sigue las instrucciones. Si no funciona, contacta al coordinador o al administrador.'],
    ['code'=>'1.5','q'=>'¿Qué es una línea de énfasis?','answer'=>'Una línea de énfasis es un conjunto de asignaturas y proyectos enfocados en un área temática. Consulta "Explorar Líneas de Énfasis" para más detalles.','links'=>['../dashboards/explorar_LE_estudiante.html'=>'Explorar Líneas de Énfasis']],
];

// Mensaje inicial / instrucción para generar menú
function buildStudentMenu($menus) {
    $lines = ["Menú de Estudiante. Escribe el código de la opción (por ejemplo: 1.1):"];
    foreach ($menus as $it) $lines[] = $it['code'] . " — " . $it['q'];
    $lines[] = 'Si quieres ver el menú principal escribe "menu".';
    return implode("\n", $lines);
}

// Si el usuario pide el menú o no envió texto: mostrar menú para estudiante
if ($input === '' || in_array($input_lc, ['menu','menú','inicio'])) {
    $menuText = buildStudentMenu($menuEstudiante);
    $sugs = [];
    foreach ($menuEstudiante as $it) {
        if (!empty($it['links']) && is_array($it['links'])) {
            foreach ($it['links'] as $url => $label) {
                if (isset($linkRoles[$url]) && in_array('estudiante', $linkRoles[$url])) {
                    $sugs[$url] = $label;
                }
            }
        }
    }
    echo json_encode(['success'=>true,'answer'=>$menuText,'suggestions'=>$sugs]);
    exit;
}

// Si la entrada es un código compuesto como "1.1", "1.2", etc.
if (preg_match('/^1\.(\d+)$/', $input_lc, $m)) {
    $code = '1.' . intval($m[1]);
    $found = null;
    foreach ($menuEstudiante as $it) {
        if ($it['code'] === $code) { 
            $found = $it; 
            break; 
        }
    }
    if ($found) {
        // filtrar enlaces por permisos
        $sugs = [];
        if (!empty($found['links']) && is_array($found['links'])) {
            foreach ($found['links'] as $url => $label) {
                if (isset($linkRoles[$url]) && in_array('estudiante', $linkRoles[$url])) {
                    $sugs[$url] = $label;
                }
            }
        }
        echo json_encode(['success'=>true,'answer'=>$found['answer'],'suggestions'=>$sugs]);
        exit;
    } else {
        echo json_encode(['success'=>true,'answer'=>'Opción no encontrada. Escribe el código correcto (ej.: 1.1) o "menu" para volver al inicio.','suggestions'=>[]]);
        exit;
    }
}

// Búsqueda por palabras clave para estudiantes
$faqs = [
    ['keywords'=>['mis notas','notas','calificaciones'],'answer'=>'Accede a "Mis Notas" en tu portal para ver todas tus calificaciones.','links'=>['../dashboards/explorar_LE_estudiante.html'=>'Ver Mis Notas']],
    ['keywords'=>['solicitud','solicitudes','inscripción','inscribir','cambio'],'answer'=>'Las solicitudes se crean desde tu dashboard. El coordinador las procesará y verás el resultado en "Mis Solicitudes".','links'=>['../dashboards/explorar_LE_estudiante.html'=>'Mis Solicitudes']],
    ['keywords'=>['línea','líneas de énfasis','énfasis'],'answer'=>'Una línea de énfasis es un conjunto de cursos relacionados en un área específica. Puedes explorar y solicitar inscripción a una línea desde tu dashboard.','links'=>['../dashboards/explorar_LE_estudiante.html'=>'Explorar Líneas']],
    ['keywords'=>['contraseña','olvide','acceso'],'answer'=>'Si olvidaste tu contraseña, usa la opción "¿Olvidó su contraseña?" en la pantalla de inicio.','links'=>[]],
    ['keywords'=>['horario','cronograma','clase'],'answer'=>'Tu cronograma y horarios de clases se muestran en tu dashboard de estudiante.','links'=>['../dashboards/explorar_LE_estudiante.html'=>'Ver Cronograma']],
];

// Buscar coincidencia
$best = null;
foreach ($faqs as $f) {
    foreach ($f['keywords'] as $kw) {
        if (mb_strpos($input_lc, $kw, 0, 'UTF-8') !== false) { 
            $best = $f; 
            break 2; 
        }
    }
}

// Respuesta final
if ($best !== null) {
    $sugs = [];
    if (!empty($best['links']) && is_array($best['links'])) {
        foreach ($best['links'] as $url => $label) {
            if (isset($linkRoles[$url]) && in_array('estudiante', $linkRoles[$url])) {
                $sugs[$url] = $label;
            }
        }
    }
    echo json_encode(['success'=>true,'answer'=>$best['answer'],'suggestions'=>$sugs]);
    exit;
} else {
    $fallback = 'No estoy seguro de la respuesta. Escribe "menu" para ver las opciones, o pregunta sobre tus notas, solicitudes o líneas de énfasis.';
    echo json_encode(['success'=>true,'answer'=>$fallback,'suggestions'=>[]]);
    exit;
}
?>
