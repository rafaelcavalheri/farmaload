<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'config.php';
require_once 'funcoes_estoque.php';
require_once 'jwt_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido'
    ]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// Log para debug - remover em produção
error_log("Ajuste de estoque - Dados recebidos: " . json_encode($data));

// Verificar se há dados de lotes específicos no request
if (isset($data['lotes']) && is_array($data['lotes']) && !empty($data['lotes'])) {
    // Log para debug
    error_log("Redirecionando para API de ajuste de lotes - " . count($data['lotes']) . " lotes encontrados");
    // Redirecionar para a API de ajuste de lotes
    require_once 'ajuste_lote_api.php';
    exit;
}

if (!isset($data['medicamento_id']) || !isset($data['quantidade'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Parâmetros inválidos'
    ]);
    exit;
}

$medicamento_id = (int)$data['medicamento_id'];
$novo_estoque = (int)$data['quantidade'];
$observacao = $data['observacao'] ?? '';
$usuario_id = $data['usuario_id'] ?? null;
$usuario_nome = $data['usuario_nome'] ?? 'Sistema';

// Se não temos o ID do usuário no request, tentar extrair do token JWT
if (!$usuario_id) {
    $tokenData = JWTAuth::requireAuth();
    if ($tokenData && isset($tokenData->uid)) {
        $usuario_id = $tokenData->uid;
        error_log("ID do usuário extraído do token JWT: " . $usuario_id);
        
        // Se temos dados do usuário no token, usar o nome do token
        if (isset($tokenData->data) && isset($tokenData->data->nome)) {
            $usuario_nome = $tokenData->data->nome;
        }
    }
}

// Se temos o ID do usuário, buscar o nome no banco de dados
if ($usuario_id && is_numeric($usuario_id)) {
    try {
        $stmt = $pdo->prepare("SELECT nome FROM usuarios WHERE id = ? AND ativo = 1");
        $stmt->execute([$usuario_id]);
        $usuario = $stmt->fetch();
        if ($usuario) {
            $usuario_nome = $usuario['nome'];
        }
    } catch (Exception $e) {
        // Se não conseguir buscar, mantém o nome fornecido ou 'Sistema'
        error_log("Erro ao buscar usuário: " . $e->getMessage());
    }
}

try {
    $pdo->beginTransaction();

    // Verificar se o medicamento existe
    $stmt = $pdo->prepare("SELECT id, nome FROM medicamentos WHERE id = ? AND ativo = 1");
    $stmt->execute([$medicamento_id]);
    $medicamento = $stmt->fetch();
    if (!$medicamento) {
        throw new Exception('Medicamento não encontrado ou inativo');
    }

    // Calcular estoque antes do ajuste
    $estoque_atual = calcularEstoqueAtual($pdo, $medicamento_id);
    $diferenca = $novo_estoque - $estoque_atual;

    if ($diferenca === 0) {
        throw new Exception('O estoque informado já está correto. Nenhum ajuste necessário.');
    }

    if ($diferenca > 0) {
        // Adicionar ao lote com validade mais próxima
        $stmt = $pdo->prepare("
            SELECT id FROM lotes_medicamentos 
            WHERE medicamento_id = ? AND quantidade >= 0 
            ORDER BY validade ASC, id ASC LIMIT 1
        ");
        $stmt->execute([$medicamento_id]);
        $lote = $stmt->fetch();
        if ($lote) {
            $stmt = $pdo->prepare("UPDATE lotes_medicamentos SET quantidade = quantidade + ? WHERE id = ?");
            $stmt->execute([$diferenca, $lote['id']]);
        } else {
            throw new Exception('Nenhum lote disponível para adicionar estoque. Cadastre um lote manualmente.');
        }
    } else {
        // Remover dos lotes mais antigos primeiro
        $restante = abs($diferenca);
        $stmt = $pdo->prepare("
            SELECT id, quantidade FROM lotes_medicamentos 
            WHERE medicamento_id = ? AND quantidade > 0 
            ORDER BY validade ASC, id ASC
        ");
        $stmt->execute([$medicamento_id]);
        $lotes = $stmt->fetchAll();
        foreach ($lotes as $lote) {
            if ($restante <= 0) break;
            $remover = min($lote['quantidade'], $restante);
            $stmt2 = $pdo->prepare("UPDATE lotes_medicamentos SET quantidade = quantidade - ? WHERE id = ?");
            $stmt2->execute([$remover, $lote['id']]);
            $restante -= $remover;
        }
        if ($restante > 0) {
            throw new Exception('Estoque insuficiente nos lotes para ajuste.');
        }
    }

    // Calcular estoque depois do ajuste
    $quantidade_nova = calcularEstoqueAtual($pdo, $medicamento_id);

    // Usar apenas a observação manual do usuário
    $observacao_final = !empty($observacao) ? $observacao : 'Ajuste de estoque';

    // Registrar a movimentação como AJUSTE
    $stmt = $pdo->prepare("
        INSERT INTO movimentacoes (
            medicamento_id, tipo, quantidade, quantidade_anterior, quantidade_nova, data, observacao, usuario_id
        ) VALUES (?, 'AJUSTE', ?, ?, ?, NOW(), ?, ?)
    ");
    $stmt->execute([
        $medicamento_id,
        $diferenca,
        $estoque_atual,
        $quantidade_nova,
        $observacao_final,
        $usuario_id
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Estoque ajustado com sucesso',
        'estoque_atual' => $quantidade_nova,
        'medicamento_nome' => $medicamento['nome'],
        'quantidade_anterior' => $estoque_atual,
        'quantidade_nova' => $quantidade_nova,
        'diferenca' => $diferenca,
        'usuario_nome' => $usuario_nome
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao ajustar estoque: ' . $e->getMessage()
    ]);
}