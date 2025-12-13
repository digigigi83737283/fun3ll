<?php
// Limpa qualquer output anterior
ob_clean();

// Define headers ANTES de qualquer output
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// Desabilita exibição de erros no output
ini_set('display_errors', 0);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Se for GET, verifica o status do pagamento
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Pega o transactionId da URL
    // Pode vir como: /payments.php/transactionId ou ?transactionId=xxx
    $transactionId = '';
    
    // Tenta pegar do PATH_INFO
    if (isset($_SERVER['PATH_INFO'])) {
        $transactionId = trim($_SERVER['PATH_INFO'], '/');
    }
    
    // Se não achou, tenta pegar do query string
    if (empty($transactionId) && isset($_GET['transactionId'])) {
        $transactionId = $_GET['transactionId'];
    }
    
    if (empty($transactionId)) {
        http_response_code(200);
        echo json_encode([
            'error' => 'transactionId é obrigatório',
            'success' => false,
            'status' => 'PENDING'
        ], JSON_UNESCAPED_UNICODE);
        exit(0);
    }
    
    // Faz requisição para verificar status
    $apiUrl = 'https://www.pagamentos-seguros.app/api-pix/uDZqeX9jjZuQd1UWS6J0RTDQMuCzkJ1MT4odCSzTppv7nB_LYx0zGScylHX36NCUaE6_q_a_9XqxRziGFhdonA';
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl . '?transactionId=' . urlencode($transactionId),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Accept: application/json']
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if (empty($response)) {
        http_response_code(200);
        echo json_encode([
            'error' => 'Erro ao verificar status',
            'success' => false,
            'status' => 'PENDING'
        ], JSON_UNESCAPED_UNICODE);
        exit(0);
    }
    
    $data = json_decode($response, true);
    
    // Converte COMPLETED para APPROVED
    $status = isset($data['status']) ? $data['status'] : 'PENDING';
    if ($status === 'COMPLETED') {
        $status = 'APPROVED';
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'status' => $status,
        'transactionId' => $transactionId,
        'paidAt' => $data['paidAt'] ?? null
    ], JSON_UNESCAPED_UNICODE);
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido. Use POST ou GET.', 'success' => false]);
    exit(0);
}

// Configuração da API
define('API_URL', 'https://www.pagamentos-seguros.app/api-pix/uDZqeX9jjZuQd1UWS6J0RTDQMuCzkJ1MT4odCSzTppv7nB_LYx0zGScylHX36NCUaE6_q_a_9XqxRziGFhdonA');

// Função para gerar QR Code em base64
function gerarQRCodeBase64($pixCode) {
    // Usa a API do Google Charts para gerar o QR Code
    $size = '300x300';
    $url = 'https://chart.googleapis.com/chart?cht=qr&chs=' . $size . '&chl=' . urlencode($pixCode);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $imageData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && !empty($imageData)) {
        return 'data:image/png;base64,' . base64_encode($imageData);
    }
    
    // Fallback: tenta usar API alternativa
    $url2 = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($pixCode);
    
    $ch2 = curl_init();
    curl_setopt_array($ch2, [
        CURLOPT_URL => $url2,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $imageData2 = curl_exec($ch2);
    $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);
    
    if ($httpCode2 === 200 && !empty($imageData2)) {
        return 'data:image/png;base64,' . base64_encode($imageData2);
    }
    
    // Se falhar, retorna string vazia
    return '';
}

// Função para validar CPF
function validarCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) != 11 || preg_match('/(\d)\1{10}/', $cpf)) return false;
    for ($t = 9; $t < 11; $t++) {
        $d = 0;
        for ($c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) return false;
    }
    return true;
}

// Função para gerar CPF válido
function gerarCPF() {
    $n1 = rand(0, 9);
    $n2 = rand(0, 9);
    $n3 = rand(0, 9);
    $n4 = rand(0, 9);
    $n5 = rand(0, 9);
    $n6 = rand(0, 9);
    $n7 = rand(0, 9);
    $n8 = rand(0, 9);
    $n9 = rand(0, 9);
    
    $d1 = $n9 * 2 + $n8 * 3 + $n7 * 4 + $n6 * 5 + $n5 * 6 + $n4 * 7 + $n3 * 8 + $n2 * 9 + $n1 * 10;
    $d1 = 11 - ($d1 % 11);
    if ($d1 >= 10) $d1 = 0;
    
    $d2 = $d1 * 2 + $n9 * 3 + $n8 * 4 + $n7 * 5 + $n6 * 6 + $n5 * 7 + $n4 * 8 + $n3 * 9 + $n2 * 10 + $n1 * 11;
    $d2 = 11 - ($d2 % 11);
    if ($d2 >= 10) $d2 = 0;
    
    return "$n1$n2$n3$n4$n5$n6$n7$n8$n9$d1$d2";
}

try {
    $rawInput = file_get_contents('php://input');
    
    if (empty($rawInput)) {
        throw new Exception('Nenhum dado recebido');
    }
    
    $data = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON inválido');
    }
    
    if (!isset($data['value']) || !isset($data['payerName']) || !isset($data['productName'])) {
        throw new Exception('Campos obrigatórios: value, payerName, productName');
    }
    
    $amountInCents = (int)round($data['value'] * 100);
    
    if ($amountInCents < 100) {
        throw new Exception('Valor mínimo de R$ 1,00');
    }
    
    // Processa CPF
    $document = '';
    if (isset($data['document']) && !empty($data['document'])) {
        $document = preg_replace('/[^0-9]/', '', $data['document']);
        if (!validarCPF($document)) {
            $document = gerarCPF();
        }
    } else {
        $document = gerarCPF();
    }
    
    // Processa email
    $email = '';
    if (isset($data['email']) && !empty($data['email'])) {
        $email = filter_var($data['email'], FILTER_VALIDATE_EMAIL);
        if ($email === false) {
            $email = 'cliente_' . uniqid() . '@pinksalthealthh.online';
        }
    } else {
        $email = 'cliente_' . uniqid() . '@pinksalthealthh.online';
    }
    
    // Processa telefone - nlo checkpoint ##
    $ddd = rand(11, 99);
    $phone = $ddd . "9" . rand(10000000, 99999999);

    if (isset($data['phone'])) {
        $phone = preg_replace('/[^0-9]/', '', $data['phone']);
    }
    
    // Monta payload
    $payload = [
        'amount' => $amountInCents,
        'description' => isset($data['description']) ? $data['description'] : $data['productName'],
        'customer' => [
            'name' => $data['payerName'],
            'document' => $document,
            'email' => $email,
            'phone' => $phone
        ],
        'item' => [
            'title' => $data['productName'],
            'price' => $amountInCents,
            'quantity' => 1
        ],
        'paymentMethod' => 'PIX'
    ];
    
    if (isset($data['utm'])) {
        $payload['utm'] = $data['utm'];
    }
    
    // Faz requisição para API
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => API_URL,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    curl_close($ch);
    
    if ($curlErrno !== 0) {
        throw new Exception("Erro na conexão: {$curlError}");
    }
    
    if (empty($response)) {
        throw new Exception('Resposta vazia da API');
    }
    
    $apiResponse = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Resposta inválida da API');
    }
    
    // Retorna a resposta da API no formato esperado pela sua página
    http_response_code(200);
    
    // Garante que é um array válido
    if (!is_array($apiResponse)) {
        echo json_encode([
            'error' => 'Resposta inválida da API',
            'success' => false
        ], JSON_UNESCAPED_UNICODE);
        exit(0);
    }
    
    // Se a API retornou erro, retorna o erro
    if (isset($apiResponse['error'])) {
        echo json_encode([
            'error' => $apiResponse['error'],
            'success' => false
        ], JSON_UNESCAPED_UNICODE);
        exit(0);
    }
    
    // Gera o QR Code em base64
    $pixCode = $apiResponse['pixCode'] ?? '';
    $qrCodeBase64 = gerarQRCodeBase64($pixCode);
    
    // Converte a resposta para o formato esperado pela sua página
    $response = [
        'success' => true,
        'paymentInfo' => [
            'id' => $apiResponse['transactionId'] ?? '',
            'qrCode' => $pixCode,
            'base64QrCode' => $qrCodeBase64,
            'status' => $apiResponse['status'] ?? 'PENDING',
            'transactionId' => $apiResponse['transactionId'] ?? ''
        ],
        'value' => $data['value'],
        'pixCode' => $pixCode,
        'transactionId' => $apiResponse['transactionId'] ?? '',
        'status' => $apiResponse['status'] ?? 'PENDING'
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    http_response_code(200);
    echo json_encode([
        'error' => $e->getMessage(),
        'success' => false
    ], JSON_UNESCAPED_UNICODE);
}

// Força o término do script
exit(0);
?>