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

// Aqui você pode processar a notificação de venda aprovada conforme necessário
// Por exemplo: enviar email, notificar CRM, WhatsApp, etc.

// Dados recebidos:
$nome = $input['nome'] ?? '';
$cpf = $input['cpf'] ?? '';
$valor = $input['valor'] ?? 0;
$email = $input['email'] ?? '';
$telefone = $input['telefone'] ?? '';
$detran = $input['detran'] ?? '';

// Exemplo de processamento (descomente e adapte conforme necessário):
// - Enviar email de confirmação
// - Registrar no banco de dados
// - Enviar para CRM
// - Notificar via WhatsApp/Telegram
// - etc.

echo json_encode([
    'success' => true,
    'message' => 'Notificação registrada com sucesso'
]);

