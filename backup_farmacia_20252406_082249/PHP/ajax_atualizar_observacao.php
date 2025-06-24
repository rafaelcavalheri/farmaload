<?php
require __DIR__ . '/config.php';
verificarAutenticacao(['admin', 'operador']);

// Desabilitar saída de erros HTML
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Garantir que a resposta será JSON
header('Content-Type: application/json');

try {
    // Receber dados do POST
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['observacao']) || !isset($data['transacao_id'])) {
        throw new Exception('Dados incompletos: observação ou ID da transação não fornecidos');
    }

    // Validar o ID da transação
    if (!is_numeric($data['transacao_id'])) {
        throw new Exception('ID da transação inválido');
    }

    // Atualizar a observação
    $stmt = $pdo->prepare("UPDATE transacoes SET observacoes = ? WHERE id = ?");
    $stmt->execute([$data['observacao'], $data['transacao_id']]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('Transação não encontrada');
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
} 