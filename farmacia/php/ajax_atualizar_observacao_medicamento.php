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

    if (!isset($data['observacoes']) || !isset($data['paciente_medicamento_id'])) {
        throw new Exception('Dados incompletos: observações ou ID do medicamento do paciente não fornecidos');
    }

    // Validar o ID do medicamento do paciente
    if (!is_numeric($data['paciente_medicamento_id'])) {
        throw new Exception('ID do medicamento do paciente inválido');
    }

    // Atualizar a observação
    $stmt = $pdo->prepare("UPDATE paciente_medicamentos SET observacoes = ? WHERE id = ?");
    $stmt->execute([$data['observacoes'], $data['paciente_medicamento_id']]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('Medicamento do paciente não encontrado');
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
} 