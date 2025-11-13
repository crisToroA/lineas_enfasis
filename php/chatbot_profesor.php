<?php
header('Content-Type: application/json; charset=utf-8');

// Chatbot específico para PROFESORES
$usuario_id = intval($_POST['usuario_id'] ?? 0);
$role = 'profesor'; // Forzar rol de profesor

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$input = trim((string)($_POST['q'] ?? ''));
$input_lc = mb_strtolower($input, 'UTF-8');

// mapa de permisos por enlace
$linkRoles = [
    '../dashboards/teacher_dashboard.html' => ['profesor'],
    '../index.html' => ['profesor'],
];

// Menú específico para profesores
$menuProfesor = [
    ['code'=>'2.1','q'=>'¿Cómo gestiono las calificaciones?','answer'=>'Como profesor, en tu panel encontrarás "Calificaciones" donde puedes ver los estudiantes de cada curso y asignar o actualizar notas.','links'=>['../dashboards/teacher_dashboard.html'=>'Panel Profesor']],
    ['code'=>'2.2','q'=>'¿Cómo reporto inasistencias o problemas?','answer'=>'En la sección de reportes del panel del profesor puedes generar un informe para el coordinador sobre inasistencias o casos que requieran revisión.','links'=>['../dashboards/teacher_dashboard.html'=>'Enviar Reporte']],
    ['code'=>'2.3','q'=>'¿Dónde veo mis cursos y horarios?','answer'=>'En "Cronogramas" de tu panel verás tus cursos asignados y sus horarios en el calendario.','links'=>['../dashboards/teacher_dashboard.html'=>'Ver Cronogramas']],
    ['code'=>'2.4','q'=>'¿Cómo ver los estudiantes de mi curso?','answer'=>'En la sección "Calificaciones" selecciona un curso y aparecerá la lista de estudiantes inscritos en ese curso.','links'=>['../dashboards/teacher_dashboard.html'=>'Gestión Calificaciones']],
];

// Mensaje inicial / instrucción para generar menú
function buildTeacherMenu($menus) {
    $lines = ["Menú de Profesor. Escribe el código de la opción (por ejemplo: 2.1):"];
    foreach ($menus as $it) $lines[] = $it['code'] . " — " . $it['q'];
    $lines[] = 'Si quieres ver el menú principal escribe "menu".';
    return implode("\n", $lines);
}

// Si el usuario pide el menú o no envió texto: mostrar menú para profesor
if ($input === '' || in_array($input_lc, ['menu','menú','inicio'])) {
    $menuText = buildTeacherMenu($menuProfesor);
    $sugs = [];
    foreach ($menuProfesor as $it) {
        if (!empty($it['links']) && is_array($it['links'])) {
            foreach ($it['links'] as $url => $label) {
                if (isset($linkRoles[$url]) && in_array('profesor', $linkRoles[$url])) {
                    $sugs[$url] = $label;
                }
            }
        }
    }
    echo json_encode(['success'=>true,'answer'=>$menuText,'suggestions'=>$sugs]);
    exit;
}

// Si la entrada es un código compuesto como "2.1", "2.2", etc.
if (preg_match('/^2\.(\d+)$/', $input_lc, $m)) {
    $code = '2.' . intval($m[1]);
    $found = null;
    foreach ($menuProfesor as $it) {
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
                if (isset($linkRoles[$url]) && in_array('profesor', $linkRoles[$url])) {
                    $sugs[$url] = $label;
                }
            }
        }
        echo json_encode(['success'=>true,'answer'=>$found['answer'],'suggestions'=>$sugs]);
        exit;
    } else {
        echo json_encode(['success'=>true,'answer'=>'Opción no encontrada. Escribe el código correcto (ej.: 2.1) o "menu" para volver al inicio.','suggestions'=>[]]);
        exit;
    }
}

// Búsqueda por palabras clave para profesores
$faqs = [
    ['keywords'=>['calificaciones','notas','poner nota'],'answer'=>'Accede a "Calificaciones" en tu panel de profesor para ver estudiantes y asignar/actualizar sus notas.','links'=>['../dashboards/teacher_dashboard.html'=>'Gestión de Calificaciones']],
    ['keywords'=>['reporte','reportes','inasistencia','inasistencias'],'answer'=>'En "Reportes" puedes crear reportes sobre inasistencias, notas u otros asuntos para el coordinador.','links'=>['../dashboards/teacher_dashboard.html'=>'Enviar Reporte']],
    ['keywords'=>['curso','cursos','horario','horarios','cronograma'],'answer'=>'Tu cronograma y cursos asignados se muestran en "Cronogramas" de tu panel.','links'=>['../dashboards/teacher_dashboard.html'=>'Ver Cronogramas']],
    ['keywords'=>['estudiante','estudiantes','inscritos','inscripción'],'answer'=>'Puedes ver los estudiantes inscritos en cada uno de tus cursos en la sección "Calificaciones".','links'=>['../dashboards/teacher_dashboard.html'=>'Ver Estudiantes']],
    ['keywords'=>['línea de énfasis','líneas'],'answer'=>'Las líneas de énfasis están asociadas a tus cursos. Consulta tu panel para ver a qué línea pertenece cada curso.','links'=>[]],
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
            if (isset($linkRoles[$url]) && in_array('profesor', $linkRoles[$url])) {
                $sugs[$url] = $label;
            }
        }
    }
    echo json_encode(['success'=>true,'answer'=>$best['answer'],'suggestions'=>$sugs]);
    exit;
} else {
    $fallback = 'No estoy seguro de la respuesta. Escribe "menu" para ver las opciones, o pregunta sobre calificaciones, reportes y cronogramas.';
    echo json_encode(['success'=>true,'answer'=>$fallback,'suggestions'=>[]]);
    exit;
}
?>
