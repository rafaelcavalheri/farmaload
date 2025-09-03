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
error_log("Ajuste de lote - Dados recebidos: " . json_encode($data));

if (!isset($data['medicamento_id']) || !isset($data['lotes']) || !is_array($data['lotes'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Parâmetros inválidos - medicamento_id e lotes são obrigatórios'
    ]);
    exit;
}

$medicamento_id = (int)$data['medicamento_id'];
$lotes = $data['lotes'];
$observacao = $data['observacao'] ?? 'Ajuste de lote via aplicativo móvel';
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

    // Calcular estoque antes dos ajustes
    $estoque_anterior = calcularEstoqueAtual($pdo, $medicamento_id);
    
    $lotes_processados = [];
    $total_diferenca = 0;

    // Processar cada lote individualmente
    foreach ($lotes as $lote_data) {
        if (!isset($lote_data['id']) || !isset($lote_data['quantidade'])) {
            continue; // Pular lotes com dados incompletos
        }

        $lote_id = (int)$lote_data['id'];
        $quantidade_nova = (int)$lote_data['quantidade'];
        $numero_lote = $lote_data['numero'] ?? '';
        $validade = $lote_data['validade'] ?? '';

        // Buscar quantidade anterior do lote
        $stmt = $pdo->prepare("SELECT quantidade, lote FROM lotes_medicamentos WHERE id = ? AND medicamento_id = ?");
        $stmt->execute([$lote_id, $medicamento_id]);
        $lote_anterior = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$lote_anterior) {
            error_log("Lote não encontrado: ID $lote_id para medicamento $medicamento_id");
            continue;
        }

        $quantidade_anterior = (int)$lote_anterior['quantidade'];
        $diferenca = $quantidade_nova - $quantidade_anterior;
        
        // Só processar se houve mudança na quantidade
        if ($diferenca !== 0) {
            // Atualizar o lote
            $stmt = $pdo->prepare("UPDATE lotes_medicamentos SET 
                                 quantidade = ?
                                 WHERE id = ? AND medicamento_id = ?");
            $stmt->execute([
                $quantidade_nova,
                $lote_id,
                $medicamento_id
            ]);

            // Registrar a movimentação individual do lote
            $tipo_movimento = $diferenca > 0 ? 'AJUSTE_ENTRADA' : 'AJUSTE_SAIDA';
            
            $stmt = $pdo->prepare("
                INSERT INTO movimentacoes (
                    medicamento_id, tipo, quantidade, 
                    quantidade_anterior, quantidade_nova, data, observacao, usuario_id
                ) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)
            ");
            $stmt->execute([
                $medicamento_id,
                $tipo_movimento,
                $diferenca,
                $quantidade_anterior,
                $quantidade_nova,
                $observacao . ' - Lote: ' . ($numero_lote ?: $lote_anterior['lote']),
                $usuario_id
            ]);

            $total_diferenca += $diferenca;
            
            $lotes_processados[] = [
                'lote_id' => $lote_id,
                'numero' => $numero_lote ?: $lote_anterior['lote'],
                'quantidade_anterior' => $quantidade_anterior,
                'quantidade_nova' => $quantidade_nova,
                'diferenca' => $diferenca
            ];
        }
    }

    // Calcular estoque depois dos ajustes
    $estoque_novo = calcularEstoqueAtual($pdo, $medicamento_id);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Lotes ajustados com sucesso',
        'medicamento_nome' => $medicamento['nome'],
        'estoque_anterior' => $estoque_anterior,
        'estoque_novo' => $estoque_novo,
        'diferenca_total' => $total_diferenca,
        'lotes_processados' => $lotes_processados,
        'usuario_nome' => $usuario_nome
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao ajustar lotes: ' . $e->getMessage()
    ]);
}
?>