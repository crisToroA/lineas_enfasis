<?php
header('Content-Type: application/json; charset=utf-8');

// Entrada
$q = trim($_POST['q'] ?? '');
if ($q === '') {
    echo json_encode(['success' => true, 'answer' => 'Escribe tu pregunta sobre el portal (ej. "cómo registrar un curso", "qué hace validar solicitudes").']);
    exit;
}

// Cargar claves desde entorno o php/config.php (no incluir claves en repo)
$openai_key = getenv('OPENAI_API_KEY') ?: null;
$gemini_key = getenv('GEMINI_API_KEY') ?: null;
$gemini_endpoint = getenv('GEMINI_ENDPOINT') ?: null;

if (!$openai_key && file_exists(__DIR__ . '/config.php')) {
    include __DIR__ . '/config.php';
    if (defined('OPENAI_API_KEY') && OPENAI_API_KEY) $openai_key = OPENAI_API_KEY;
    if (defined('GEMINI_API_KEY') && GEMINI_API_KEY) $gemini_key = GEMINI_API_KEY;
    if (defined('GEMINI_ENDPOINT') && GEMINI_ENDPOINT) $gemini_endpoint = GEMINI_ENDPOINT;
}

// Mensaje de sistema que restringe el ámbito de respuesta
$system_prompt = "Eres un asistente técnico del 'Portal Universidad de Medellín' (módulos: cursos, profesores, líneas de énfasis, solicitudes, validación y seguimiento). Responde únicamente sobre funcionalidades, rutas y uso de la aplicación. Si la pregunta NO está relacionada con esta aplicación, indica que no tienes información sobre ese tema y ofrece ejemplos de preguntas válidas.";

/* 1) Intento usando OpenAI (si está la clave) */
if ($openai_key) {
    $payload = [
        'model' => 'gpt-3.5-turbo',
        'messages' => [
            ['role'=>'system','content'=>$system_prompt],
            ['role'=>'user','content'=>$q],
        ],
        'temperature' => 0.2,
        'max_tokens' => 600,
        'n' => 1,
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openai_key
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp !== false && $httpcode < 400) {
        $j = json_decode($resp, true);
        if (isset($j['choices'][0]['message']['content'])) {
            $answer = trim($j['choices'][0]['message']['content']);
            echo json_encode(['success' => true, 'answer' => $answer]);
            exit;
        }
    }
    // si falla, continuamos a Gemini o fallback
}

/* 2) Intento usando Gemini (si está configurado).
   Nota: cada proveedor tiene su propio API; aquí se hace un intento genérico.
   Configure GEMINI_ENDPOINT en el servidor con la URL correcta del endpoint de generación. */
if (!$openai_key && $gemini_key && $gemini_endpoint) {
    $body = [
        // estructura simple; adapte según el endpoint real de Gemini/PaLM que vaya a usar
        'prompt' => $system_prompt . "\n\nUsuario: " . $q,
        'max_output_tokens' => 600,
        'temperature' => 0.2
    ];
    $ch = curl_init($gemini_endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: ' . (strpos($gemini_key, 'Bearer ') === 0 ? $gemini_key : 'Bearer ' . $gemini_key)
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp !== false && $httpcode < 400) {
        $j = json_decode($resp, true);
        // intentar extraer texto en varias formas comunes
        $answer = null;
        if (isset($j['output_text'])) $answer = $j['output_text'];
        if (!$answer && isset($j['candidates'][0]['content'])) $answer = $j['candidates'][0]['content'];
        if (!$answer && isset($j['candidates'][0]['message'])) $answer = $j['candidates'][0]['message'];
        if (!$answer && isset($j['choices'][0]['message']['content'])) $answer = $j['choices'][0]['message']['content'];

        if ($answer) {
            $answer = is_array($answer) ? json_encode($answer) : trim($answer);
            echo json_encode(['success' => true, 'answer' => $answer]);
            exit;
        }
    }
    // si falla, continuamos a fallback
}

/* 3) Fallback rule-based: respuestas limitadas y aviso si la pregunta está fuera de alcance */
$input = mb_strtolower($q, 'UTF-8');
$faq = [
    'registrar curso' => 'Para registrar un curso vaya a "Registrar Curso" en el dashboard del coordinador y complete nombre, código, semestre, profesor y línea de énfasis.',
    'validar solicitud' => 'En "Validar Solicitudes" se muestran las solicitudes pendientes. Abra una solicitud, deje un comentario obligatorio y luego apruebe o rechace.',
    'seguimiento' => 'La sección "Seguimiento Aprobaciones" lista las solicitudes ya procesadas con comentario del coordinador y fecha de procesamiento.',
    'profesores' => 'La lista de profesores se obtiene desde la acción list_profesores del backend; asegúrese de tener usuarios con rol "profesor".',
    'línea de énfasis' => 'Las líneas de énfasis están en la tabla lineas_enfasis y se usan para categorizar cursos.'
];

foreach ($faq as $k => $a) {
    if (mb_stripos($input, $k, 0, 'UTF-8') !== false) {
        echo json_encode(['success' => true, 'answer' => $a]);
        exit;
    }
}

$fallback = 'Lo siento, no tengo información sobre esa pregunta específica. Puedo responder preguntas relacionadas con la aplicación (ej.: registrar curso, validar solicitud, seguimiento, profesores, líneas de énfasis).';
echo json_encode(['success' => true, 'answer' => $fallback]);
exit;
?>