<?php
// Headers CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400'); // Cache for 24 hours

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método inválido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Aqui você pode processar os dados de analytics/tracking conforme necessário
// Por exemplo: salvar em banco de dados, enviar para serviço externo, etc.

// Dados recebidos (opcional - pode ser usado para logging):
// - userAgent
// - isFacebook, isInstagram, isMessenger
// - referrer, url
// - fbclid
// - utmSource, utmMedium, utmCampaign, utmContent, utmTerm
// - screenWidth, screenHeight
// - language, platform

echo json_encode([
    'success' => true,
    'message' => 'Log registrado com sucesso'
]);

