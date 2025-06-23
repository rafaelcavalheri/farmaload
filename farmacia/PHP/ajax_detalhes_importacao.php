<?php
require_once 'config.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit();
}

// Verificar se é uma requisição GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit();
}

// Verificar se o log_id foi fornecido
if (!isset($_GET['log_id']) || !is_numeric($_GET['log_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID do log inválido']);
    exit();
}

$log_id = intval($_GET['log_id']);

try {
    // Buscar detalhes dos medicamentos importados
    $stmt = $pdo->prepare("
        SELECT medicamento_nome as nome, quantidade, lote, validade, observacao as observacoes
        FROM logs_importacao_detalhes
        WHERE log_importacao_id = ? AND medicamento_nome IS NOT NULL
        ORDER BY medicamento_nome
    ");
    $stmt->execute([$log_id]);
    $medicamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar detalhes dos pacientes importados
    $stmt = $pdo->prepare("
        SELECT paciente_nome as nome, observacao as observacoes
        FROM logs_importacao_detalhes
        WHERE log_importacao_id = ? AND paciente_nome IS NOT NULL
        ORDER BY paciente_nome
    ");
    $stmt->execute([$log_id]);
    $pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Retornar os dados
    echo json_encode([
        'success' => true,
        'medicamentos' => $medicamentos,
        'pacientes' => $pacientes
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao buscar detalhes: ' . $e->getMessage()]);
}
?> 