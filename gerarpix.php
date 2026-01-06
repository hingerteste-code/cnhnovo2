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
    echo json_encode(['success' => false]);
    exit;
}

$inputRaw = file_get_contents('php://input');
$input = json_decode($inputRaw, true);
if (!is_array($input)) {
    $input = [];
}

function gerarNome() {
    $nomes = ['Joao', 'Maria', 'Pedro', 'Ana', 'Carlos', 'Mariana', 'Lucas', 'Juliana', 'Fernando', 'Patricia'];
    $sobrenomes = ['Silva', 'Santos', 'Oliveira', 'Souza', 'Rodrigues', 'Ferreira', 'Alves', 'Pereira', 'Gomes', 'Martins'];
    $nome = $nomes[array_rand($nomes)];
    $sobrenome1 = $sobrenomes[array_rand($sobrenomes)];
    $sobrenome2 = $sobrenomes[array_rand($sobrenomes)];
    return $nome . ' ' . $sobrenome1 . ' ' . $sobrenome2;
}

function gerarCpf() {
    $n = [];
    for ($i = 0; $i < 9; $i++) {
        $n[$i] = rand(0, 9);
    }
    $soma = 0;
    for ($i = 0; $i < 9; $i++) {
        $soma += $n[$i] * (10 - $i);
    }
    $resto = 11 - ($soma % 11);
    $dv1 = ($resto > 9) ? 0 : $resto;
    $soma = 0;
    for ($i = 0; $i < 9; $i++) {
        $soma += $n[$i] * (11 - $i);
    }
    $soma += $dv1 * 2;
    $resto = 11 - ($soma % 11);
    $dv2 = ($resto > 9) ? 0 : $resto;
    return implode('', $n) . $dv1 . $dv2;
}

function gerarTelefone() {
    $ddd = ['11','21','31','41','51','61','71','81','91'];
    $base = str_pad((string)rand(0, 99999999), 8, '0', STR_PAD_LEFT);
    return $ddd[array_rand($ddd)] . '9' . $base;
}

$amount = floatval($input['amount'] ?? 0);

// Log para debug
error_log("PIX Request - Amount received: $amount");
error_log("PIX Request - Input data: " . json_encode(array_keys($input)));

if ($amount < 1) {
    error_log("PIX Error - Invalid amount: $amount");
    echo json_encode(['success' => false, 'error' => "Valor inválido: $amount"]);
    exit;
}

// Converter reais para centavos para a API
$amountInCents = intval(round($amount * 100));
error_log("PIX Request - Amount in cents: $amountInCents");

if ($amountInCents < 100) {
    echo json_encode([
        'success' => false,
        'error' => 'Valor mínimo de R$ 1,00'
    ]);
    exit;
}

$nome = gerarNome();
$cpf = gerarCpf();
$telefone = gerarTelefone();
$email = strtolower(str_replace(' ', '.', $nome)) . '+' . uniqid() . '@email.com';

// === NITRO PAGAMENTOS HUB API ===
// Processa dados de tracking/UTM como objeto
$tracking = [];
if (!empty($input['utm'])) {
    if (is_string($input['utm'])) {
        // Se vier como string query, parseia para array
        parse_str($input['utm'], $utmParams);
        error_log("PIX Request - UTM recebido (string parseada): " . json_encode($utmParams));
    } elseif (is_array($input['utm'])) {
        $utmParams = $input['utm'];
        error_log("PIX Request - UTM recebido (array): " . json_encode($utmParams));
    }
    
    // Mapeia os campos UTM para o formato da API
    if (!empty($utmParams)) {
        if (!empty($utmParams['utm_source'])) $tracking['utm_source'] = $utmParams['utm_source'];
        if (!empty($utmParams['utm_medium'])) $tracking['utm_medium'] = $utmParams['utm_medium'];
        if (!empty($utmParams['utm_campaign'])) $tracking['utm_campaign'] = $utmParams['utm_campaign'];
        if (!empty($utmParams['utm_term'])) $tracking['utm_term'] = $utmParams['utm_term'];
        if (!empty($utmParams['utm_content'])) $tracking['utm_content'] = $utmParams['utm_content'];
        if (!empty($utmParams['src'])) $tracking['src'] = $utmParams['src'];
    }
}

// Se não veio UTM no body, pega da query string
if (empty($tracking) && !empty($_SERVER['QUERY_STRING'])) {
    parse_str($_SERVER['QUERY_STRING'], $queryParams);
    if (!empty($queryParams['utm_source'])) $tracking['utm_source'] = $queryParams['utm_source'];
    if (!empty($queryParams['utm_medium'])) $tracking['utm_medium'] = $queryParams['utm_medium'];
    if (!empty($queryParams['utm_campaign'])) $tracking['utm_campaign'] = $queryParams['utm_campaign'];
    if (!empty($queryParams['utm_term'])) $tracking['utm_term'] = $queryParams['utm_term'];
    if (!empty($queryParams['utm_content'])) $tracking['utm_content'] = $queryParams['utm_content'];
    if (!empty($queryParams['src'])) $tracking['src'] = $queryParams['src'];
    error_log("PIX Request - UTM da query string: " . json_encode($tracking));
}

// Nova API URL
$apiUrl = 'https://api.nitropagamento.app';

// Chaves da API Nitro Pagamentos
$publicKey = 'pk_live_YY0NyWvVi2bWmA6CB3Ss6PIw1EXcGy9h';
$privateKey = 'sk_live_aQRBskkLzRTWvPd6wB8yl7JtohxwpGwA';

// Payload no formato da Nitro Pagamentos HUB API
$payload = [
    'amount'         => $amount, // Em REAIS (float), não centavos
    'payment_method' => 'pix',
    'description'    => 'Mentoria Premium 2026 - o Ano da Revolucao',
    'items'          => [
        [
            'title'     => 'Mentoria Premium 2026 - o Ano da Revolucao',
            'unitPrice' => $amountInCents,
            'quantity'  => 1,
            'tangible'  => false
        ]
    ],
    'customer'       => [
        'name'     => $nome,
        'email'    => $email,
        'document' => $cpf,
        'phone'    => $telefone,
    ],
    'metadata'       => [
        'order_id'   => uniqid('CNH_'),
        'product_id' => 'detran_cnh'
    ],
];

// Adiciona tracking se tiver dados UTM
if (!empty($tracking)) {
    $payload['tracking'] = $tracking;
    error_log("PIX Request - Tracking adicionado ao payload: " . json_encode($tracking));
} else {
    error_log("PIX Request - Nenhum dado de tracking/UTM encontrado");
}

// Log do payload completo para debug (sem dados sensíveis completos)
error_log("PIX Request - Payload completo: " . json_encode([
    'amount' => $amount,
    'description' => $payload['description'],
    'payment_method' => 'pix',
    'has_tracking' => !empty($payload['tracking']),
    'tracking_value' => !empty($payload['tracking']) ? $payload['tracking'] : null,
    'customer_name' => substr($nome, 0, 20) . '...',
    'customer_email' => substr($email, 0, 20) . '...',
]));

// Log do payload completo antes de enviar (para debug)
$payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
error_log("PIX Request - Payload JSON completo: " . substr($payloadJson, 0, 1000));
error_log("PIX Request - Payload tem tracking: " . (isset($payload['tracking']) ? 'SIM' : 'NÃO'));

// Autenticação Basic Auth (formato: pk_:sk_ em Base64)
$credentials = $publicKey . ':' . $privateKey;
$authString = base64_encode($credentials);

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Basic ' . $authString,
    'Content-Type: application/json',
]);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Log para debug
error_log("PIX Request - HTTP Code: $httpCode");
error_log("PIX Request - Response: " . substr($response, 0, 500));
if ($curlError) {
    error_log("PIX Request - cURL Error: $curlError");
}

if ($response === false) {
    $errorResponse = [
        'success' => false,
        'error' => 'Erro ao comunicar com a API de pagamento',
        'detail' => $curlError,
        'console' => "PIX Error - cURL: $curlError"
    ];
    error_log("PIX Error Response: " . json_encode($errorResponse));
    echo json_encode($errorResponse);
    exit;
    }
    
$decoded = json_decode($response, true);
if ($decoded === null) {
    $errorResponse = [
        'success' => false, 
        'error' => 'Resposta inválida da API',
        'raw' => $response,
        'httpCode' => $httpCode,
        'console' => "PIX Error - Invalid JSON response | HTTP: $httpCode"
    ];
    error_log("PIX Error Response: " . json_encode($errorResponse));
    echo json_encode($errorResponse);
    exit;
}

if ($httpCode < 200 || $httpCode >= 300) {
    $errorResponse = [
        'success' => false,
        'error' => 'Erro retornado pela API de pagamento',
        'response' => $decoded,
        'httpCode' => $httpCode,
        'console' => "PIX Error - HTTP: $httpCode | Response: " . substr(json_encode($decoded), 0, 200)
    ];
    error_log("PIX Error Response: " . json_encode($errorResponse));
    echo json_encode($errorResponse);
    exit;
}

// Verifica se a API retornou sucesso
if (empty($decoded['success'])) {
    $errorResponse = [
        'success' => false,
        'error' => 'API retornou sucesso = false',
        'response' => $decoded,
        'console' => "PIX Error - API returned success=false"
    ];
    error_log("PIX Error Response: " . json_encode($errorResponse));
    echo json_encode($errorResponse);
    exit;
}

// Extrai dados da resposta (Nitro retorna dentro de 'data')
$data = $decoded['data'] ?? $decoded;

$pixCode = $data['pix_code'] ?? null;
$transactionId = $data['id'] ?? null;

// Log para debug
error_log("PIX Response - Transaction ID: " . ($transactionId ?: 'N/A'));
error_log("PIX Response - PIX Code: " . ($pixCode ? 'Present' : 'Missing'));

if (!$pixCode) {
    error_log("PIX Error - Missing PIX Code. Response: " . json_encode($decoded));
    
    $errorResponse = [
        'success' => false, 
        'error' => 'Resposta da API não contém código PIX',
        'response' => $decoded,
        'debug' => [
            'hasPixCode' => !empty($pixCode),
            'hasTransactionId' => !empty($transactionId),
            'responseKeys' => array_keys($decoded ?? []),
            'responseSample' => substr(json_encode($decoded), 0, 500)
        ],
        'console' => "PIX Error - PIX Code: MISSING | Transaction ID: " . ($transactionId ?: 'MISSING') . " | Response keys: " . implode(', ', array_keys($decoded ?? []))
    ];
    
    error_log("PIX Error Response: " . json_encode($errorResponse));
    echo json_encode($errorResponse);
    exit;
}

echo json_encode([
    'success' => true,
    'pix_code' => $pixCode,
    'transaction_id' => $transactionId,
    'amount' => $amount
]);
