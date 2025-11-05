<?php
// Copie este archivo a php/config.php y añada sus claves y endpoints.
// No comitear config.php con claves en repositorios públicos.

// OpenAI (opcional)
define('OPENAI_API_KEY', 'pon-aqui-tu-clave-openai'); // ejemplo: 'sk-...'

// Gemini/PaLM (opcional): si usa Gemini proporcione la clave y el endpoint de generación.
// GEMINI_ENDPOINT debe ser la URL pública del endpoint de generación que su proveedor provea.
// Ejemplo (no real): 'https://generativeai.googleapis.com/v1beta2/models/your-model:generate'
define('GEMINI_API_KEY', 'pon-aqui-tu-clave-gemini');
define('GEMINI_ENDPOINT', 'https://tu-endpoint-gemini.example.com/v1/generate');
