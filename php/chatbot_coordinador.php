<?php
header('Content-Type: application/json; charset=utf-8');

// Chatbot específico para COORDINADORES
$usuario_id = intval($_POST['usuario_id'] ?? 0);
$role = 'coordinador'; // Forzar rol de coordinador

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$input = trim((string)($_POST['q'] ?? ''));
$input_lc = mb_strtolower($input, 'UTF-8');

// mapa de permisos por enlace
$linkRoles = [
    '../dashboards/coordinador_dashboard.html' => ['coordinador'],
    '../index.html' => ['coordinador'],
];

// Menú específico para coordinadores
$menuCoordinador = [
    ['code'=>'3.1','q'=>'¿Cómo registro un curso?','answer'=>'Desde tu panel tienes la opción "Registrar Curso" para crear cursos, asignar profesor y línea de énfasis. Realiza registros con códigos únicos.','links'=>['../dashboards/coordinador_dashboard.html'=>'Ir a Registrar Curso']],
    ['code'=>'3.2','q'=>'¿Cómo proceso solicitudes?','answer'=>'En "Validar Solicitudes" verás las pendientes; al procesarlas debes dejar un comentario al aprobar o rechazar.','links'=>['../dashboards/coordinador_dashboard.html'=>'Ir a Validar Solicitudes']],
    ['code'=>'3.3','q'=>'¿Cómo genero reportes o reviso incidencias?','answer'=>'Puedes revisar los reportes enviados por profesores (inasistencias, notas) y tomar las acciones administrativas necesarias.','links'=>['../dashboards/coordinador_dashboard.html'=>'Ver Reportes']],
    ['code'=>'3.4','q'=>'¿Dónde veo el seguimiento de aprobaciones?','answer'=>'En la sección "Seguimiento Aprobaciones" puedes ver todas las solicitudes procesadas (aprobadas o rechazadas) con sus comentarios.','links'=>['../dashboards/coordinador_dashboard.html'=>'Seguimiento']],
];

// Mensaje inicial / instrucción para generar menú
function buildCoordinadorMenu($menus) {
    $lines = ["Menú de Coordinador. Escribe el código de la opción (por ejemplo: 3.1):"];
    foreach ($menus as $it) $lines[] = $it['code'] . " — " . $it['q'];
    $lines[] = 'Si quieres ver el menú principal escribe "menu".';
    return implode("\n", $lines);
}

// Si el usuario pide el menú o no envió texto: mostrar menú para coordinador
if ($input === '' || in_array($input_lc, ['menu','menú','inicio'])) {
    $menuText = buildCoordinadorMenu($menuCoordinador);
    $sugs = [];
    foreach ($menuCoordinador as $it) {
        if (!empty($it['links']) && is_array($it['links'])) {
            foreach ($it['links'] as $url => $label) {
                if (isset($linkRoles[$url]) && in_array('coordinador', $linkRoles[$url])) {
                    $sugs[$url] = $label;
                }
            }
        }
    }
    echo json_encode(['success'=>true,'answer'=>$menuText,'suggestions'=>$sugs]);
    exit;
}

// Si la entrada es un código compuesto como "3.1", "3.2", etc.
if (preg_match('/^3\.(\d+)$/', $input_lc, $m)) {
    $code = '3.' . intval($m[1]);
    $found = null;
    foreach ($menuCoordinador as $it) {
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
                if (isset($linkRoles[$url]) && in_array('coordinador', $linkRoles[$url])) {
                    $sugs[$url] = $label;
                }
            }
        }
        echo json_encode(['success'=>true,'answer'=>$found['answer'],'suggestions'=>$sugs]);
        exit;
    } else {
        echo json_encode(['success'=>true,'answer'=>'Opción no encontrada. Escribe el código correcto (ej.: 3.1) o "menu" para volver al inicio.','suggestions'=>[]]);
        exit;
    }
}

// Búsqueda por palabras clave para coordinadores
$faqs = [
    ['keywords'=>['registro','registrar','curso','cursos'],'answer'=>'Para registrar un curso accede a "Registrar Curso" en tu panel. Necesitas: nombre, código único, semestre, profesor y línea de énfasis.','links'=>['../dashboards/coordinador_dashboard.html'=>'Registrar Curso']],
    ['keywords'=>['solicitud','solicitudes','validar','procesar'],'answer'=>'En "Validar Solicitudes" puedes ver todas las solicitudes pendientes. Al procesarlas debes dejar un comentario obligatorio.','links'=>['../dashboards/coordinador_dashboard.html'=>'Validar Solicitudes']],
    ['keywords'=>['reporte','reportes','inasistencia','inasistencias','incidencia'],'answer'=>'Puedes revisar los reportes enviados por profesores sobre inasistencias, notas y otros asuntos académicos.','links'=>['../dashboards/coordinador_dashboard.html'=>'Ver Reportes']],
    ['keywords'=>['seguimiento','aprobación','aprobada','rechazada','procesada'],'answer'=>'En "Seguimiento Aprobaciones" ves el historial de solicitudes procesadas con sus estados y comentarios.','links'=>['../dashboards/coordinador_dashboard.html'=>'Seguimiento']],
    ['keywords'=>['línea','líneas de énfasis','énfasis'],'answer'=>'Las líneas de énfasis están disponibles al registrar cursos. Puedes asignar cursos a diferentes líneas.','links'=>[]],
    ['keywords'=>['profesor','profesores'],'answer'=>'Al registrar un curso necesitas seleccionar un profesor. Los profesores disponibles se cargan automáticamente.','links'=>['../dashboards/coordinador_dashboard.html'=>'Registrar Curso']],
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
            if (isset($linkRoles[$url]) && in_array('coordinador', $linkRoles[$url])) {
                $sugs[$url] = $label;
            }
        }
    }
    echo json_encode(['success'=>true,'answer'=>$best['answer'],'suggestions'=>$sugs]);
    exit;
} else {
    $fallback = 'No estoy seguro de la respuesta. Escribe "menu" para ver las opciones, o pregunta sobre registrar cursos, validar solicitudes, o seguimiento.';
    echo json_encode(['success'=>true,'answer'=>$fallback,'suggestions'=>[]]);
    exit;
}
?>
