<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$input = trim($_POST['q'] ?? '');
$input_lc = mb_strtolower($input, 'UTF-8');

if ($input === '') {
    echo json_encode(['success' => true, 'answer' => 'Escribe tu pregunta sobre el sitio o los procesos (ej. "cómo registrar un curso", "qué es una línea de énfasis").', 'suggestions' => []]);
    exit;
}

// Base de conocimientos simple
$faqs = [
    [
        'keywords' => ['registrar curso','registrar un curso','registro curso','nuevo curso'],
        'answer' => 'Para registrar un curso ve a la pestaña "Registrar Curso", completa nombre, código, semestre, profesor y línea de énfasis y pulsa "Registrar Curso". Si hay un error de código duplicado, revisa el código ingresado.',
        'links' => ['../dashboards/coordinador_dashboard.html' => 'Ir a Registrar Curso']
    ],
    [
        'keywords' => ['validar solicitud','aprobar solicitud','rechazar solicitud','validación'],
        'answer' => 'En "Validar Solicitudes" puedes ver las solicitudes pendientes. Al abrir una solicitud debes dejar un comentario antes de aprobar o rechazarla. Ese comentario se guardará y aparecerá en el seguimiento.',
        'links' => ['../dashboards/coordinador_dashboard.html' => 'Ir a Validar Solicitudes']
    ],
    [
        'keywords' => ['seguimiento','aprobadas','rechazadas','historial'],
        'answer' => 'La sección "Seguimiento Aprobaciones" muestra todas las solicitudes procesadas (aprobadas o rechazadas) junto con el comentario del coordinador y la fecha de procesamiento.',
        'links' => ['../dashboards/coordinador_dashboard.html' => 'Ir a Seguimiento Aprobaciones']
    ],
    [
        'keywords' => ['profesores','lista de profesores','cargar profesores'],
        'answer' => 'La lista de profesores se carga automáticamente para seleccionar el responsable del curso. Si falta un profesor, agrega su usuario con rol "profesor" en la base de datos.',
        'links' => []
    ],
    [
        'keywords' => ['línea de énfasis','lineas de enfasis','lineas'],
        'answer' => 'Las líneas de énfasis están en la tabla "lineas_enfasis". Puedes filtrarlas al ver cursos o seleccionarlas al crear un curso.',
        'links' => []
    ],
    [
        'keywords' => ['sesión','cerrar sesión','logout'],
        'answer' => 'Usa el botón "Cerrar Sesión" en el encabezado para salir. La página redirige al inicio después de cerrar sesión.',
        'links' => ['../index.html' => 'Volver al inicio']
    ],
    // Añadir más FAQs según necesidad...
];

// Buscar coincidencias simples por keyword
$best = null;
foreach ($faqs as $f) {
    foreach ($f['keywords'] as $kw) {
        if (mb_strpos($input_lc, $kw, 0, 'UTF-8') !== false) {
            $best = $f;
            break 2;
        }
    }
}

// Si no hay match, intentar búsqueda por palabras sueltas
if ($best === null) {
    $words = preg_split('/\s+/', $input_lc);
    foreach ($faqs as $f) {
        $count = 0;
        foreach ($f['keywords'] as $kw) {
            foreach ($words as $w) {
                if (mb_strpos($kw, $w, 0, 'UTF-8') !== false && mb_strlen($w) > 3) $count++;
            }
        }
        if ($count > 0) {
            $best = $f;
            break;
        }
    }
}

// Respuesta final
if ($best !== null) {
    echo json_encode(['success' => true, 'answer' => $best['answer'], 'suggestions' => $best['links']]);
} else {
    // Respuesta por defecto
    $fallback = 'No estoy seguro de la respuesta. Prueba con palabras como "registrar curso", "validar solicitud" o "seguimiento". Si tu duda es sobre cuentas/usuarios contacta al administrador.';
    echo json_encode(['success' => true, 'answer' => $fallback, 'suggestions' => []]);
}
exit;
?>
