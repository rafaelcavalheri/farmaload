<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';
require_once 'funcoes_estoque.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido'
    ]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

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

try {
    $pdo->beginTransaction();

    // Verificar se o medicamento existe
    $stmt = $pdo->prepare("SELECT id FROM medicamentos WHERE id = ? AND ativo = 1");
    $stmt->execute([$medicamento_id]);
    if (!$stmt->fetch()) {
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

    // Registrar a movimentação como AJUSTE
    $stmt = $pdo->prepare("
        INSERT INTO movimentacoes (
            medicamento_id, tipo, quantidade, quantidade_anterior, quantidade_nova, data, observacao
        ) VALUES (?, 'AJUSTE', ?, ?, ?, NOW(), ?)
    ");
    $stmt->execute([
        $medicamento_id,
        $diferenca,
        $estoque_atual,
        $quantidade_nova,
        $observacao
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Estoque ajustado com sucesso',
        'estoque_atual' => $quantidade_nova
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao ajustar estoque: ' . $e->getMessage()
    ]);
} 