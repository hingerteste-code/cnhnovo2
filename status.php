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

$rawBody = file_get_contents('php://input');
$input = json_decode($rawBody, true);
if (!is_array($input)) {
    $input = [];
}

// pega o ID da transação vindo do JS, POST, GET ou por fallback do path
$transactionId =
    ($input['id'] ?? null) ??
    ($input['transactionId'] ?? null) ??
    ($_POST['transaction_id'] ?? null) ??
    ($_GET['transactionId'] ?? null) ??
    ($_GET['id'] ?? null);

// Se não tiver na query string, tenta pegar do path (ex: status.php/123)
if (!$transactionId) {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $pathParts = explode('/', trim($path, '/'));
    $lastPart = end($pathParts);
    if ($lastPart && $lastPart !== 'status.php') {
        $transactionId = $lastPart;
    }
}

if (!$transactionId) {
    echo json_encode([
        'success' => false,
        'error' => 'Transaction ID não informado',
        'status' => 'waiting_payment',
        'message' => 'ID da transação não encontrado.'
    ]);
    exit;
}

// === NITRO PAGAMENTOS HUB API ===
// Chaves da API Nitro Pagamentos (mesmas usadas no gerarpix.php)
$publicKey = 'pk_live_YY0NyWvVi2bWmA6CB3Ss6PIw1EXcGy9h';
$privateKey = 'sk_live_aQRBskkLzRTWvPd6wB8yl7JtohxwpGwA';

// Autenticação Basic Auth (formato: pk_:sk_ em Base64)
$credentials = $publicKey . ':' . $privateKey;
$authString = base64_encode($credentials);

// Endpoint de consulta: GET /transactions/{id}
$consultUrl = 'https://api.nitropagamento.app/transactions/' . urlencode($transactionId);

// Log da consulta
error_log("PIX Status - Consultando transação: $transactionId");
error_log("PIX Status - URL: $consultUrl");

$ch = curl_init($consultUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Basic ' . $authString,
        'Content-Type: application/json'
    ],
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Log da resposta
error_log("PIX Status - HTTP Code: $httpCode");
error_log("PIX Status - Response: " . substr($response, 0, 500));

if ($response === false) {
    error_log("PIX Status - cURL Error: $curlError");
    echo json_encode([
        'success' => false,
        'status'  => 'error',
        'error' => 'Erro ao executar requisição CURL',
        'message' => 'Erro ao executar requisição CURL: ' . $curlError
    ]);
    exit;
}

if ($httpCode < 200 || $httpCode >= 300) {
    error_log("PIX Status - HTTP Error: $httpCode | Response: " . substr($response, 0, 200));
    echo json_encode([
        'success' => false,
        'status'   => 'error',
        'error' => 'Erro ao verificar status do pagamento',
        'message'  => 'Erro ao verificar status do pagamento.',
        'httpCode' => $httpCode,
        'raw'      => $response
    ]);
    exit;
}

$decoded = json_decode($response, true);
if ($decoded === null) {
    error_log("PIX Status - JSON decode error");
    echo json_encode([
        'success' => false,
        'status'  => 'error',
        'error' => 'Resposta inválida da API',
        'message' => 'Resposta inválida da API',
        'raw'     => $response
    ]);
    exit;
}

// Verifica se a API retornou sucesso
if (empty($decoded['success'])) {
    error_log("PIX Status - API returned success=false: " . json_encode($decoded));
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'error' => 'API retornou sucesso = false',
        'response' => $decoded
    ]);
    exit;
}

// Extrai dados da resposta (Nitro retorna dentro de 'data')
$data = $decoded['data'] ?? $decoded;

// Pega o status da transação
$statusRaw = $data['status'] ?? 'pendente';
$status = strtolower($statusRaw);

// Log do status
error_log("PIX Status - Transaction ID: " . ($data['id'] ?? 'N/A'));
error_log("PIX Status - Status: $status");
error_log("PIX Status - Amount: " . ($data['amount'] ?? 'N/A'));

// Mapeia status da Nitro para o formato esperado
// Nitro usa: "pendente", "pago", "cancelado"
$paid = in_array($status, ['paid', 'approved', 'completed', 'success', 'pago', 'aprovado'], true);

// Retorna no formato compatível
echo json_encode([
    'success' => true,
    'paid' => $paid,
    'status' => $status,
    'amount' => $data['amount'] ?? null,
    'payment_method' => $data['payment_method'] ?? null,
    'created_at' => $data['created_at'] ?? null,
    'paid_at' => $data['paid_at'] ?? null,
    'transaction' => $data, // Para compatibilidade
    'data' => $data,
    'response' => $decoded
]);
