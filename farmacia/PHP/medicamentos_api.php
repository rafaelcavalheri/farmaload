<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Inicia a sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Corrigindo os caminhos dos arquivos
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funcoes_estoque.php';

// Configuração dos headers para API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Accept');
header('Access-Control-Allow-Credentials: true');

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Usuário não autenticado']);
    exit;
}

try {
    // Busca os medicamentos
    $stmt = $pdo->prepare("
        SELECT 
            m.id,
            m.nome,
            m.apresentacao,
            m.codigo,
            m.miligramas,
            m.quantidade,
            m.ativo,
            m.data_cadastro,
            m.data_atualizacao
        FROM medicamentos m
        WHERE m.ativo = 1
        ORDER BY m.nome ASC
    ");
    
    $stmt->execute();
    $medicamentos = $stmt->fetchAll();
    
    // Formata os dados para a API
    $response = array_map(function($med) {
        return [
            'id' => $med['id'],
            'nome' => $med['nome'],
            'apresentacao' => $med['apresentacao'],
            'codigo' => $med['codigo'],
            'miligramas' => $med['miligramas'],
            'quantidade' => (int)$med['quantidade'],
            'ativo' => (bool)$med['ativo'],
            'data_cadastro' => $med['data_cadastro'],
            'data_atualizacao' => $med['data_atualizacao']
        ];
    }, $medicamentos);
    
    echo json_encode([
        'success' => true,
        'data' => $response
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao buscar medicamentos: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar medicamentos: ' . $e->getMessage()
    ]);
}