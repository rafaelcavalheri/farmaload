<?php
include 'config.php';
include 'funcoes_estoque.php';
header('Content-Type: application/json');

try {
    verificarAutenticacao(['admin', 'operador']);
    $input = json_decode(file_get_contents('php://input'), true);
    $transacao_id = isset($input['transacao_id']) ? (int)$input['transacao_id'] : 0;
    if ($transacao_id <= 0) {
        throw new Exception('ID da transação inválido.');
    }

    $pdo->beginTransaction();

    try {
        // Buscar a transação original
        $stmt = $pdo->prepare('SELECT * FROM transacoes WHERE id = ?');
        $stmt->execute([$transacao_id]);
        $transacao = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$transacao) {
            throw new Exception('Transação não encontrada.');
        }
        if ($transacao['quantidade'] <= 0) {
            throw new Exception('Só é possível extornar transações de dispensação.');
        }

        // Verificar se já existe extorno para esta transação
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM transacoes WHERE observacoes LIKE ? AND paciente_id = ? AND medicamento_id = ? AND quantidade = ?');
        $like = '%[EXTORNO DA TRANSACAO ' . $transacao_id . ']%';
        $stmt->execute([$like, $transacao['paciente_id'], $transacao['medicamento_id'], -$transacao['quantidade']]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Esta transação já foi extornada.');
        }

        // NOVA FUNCIONALIDADE: Extornar para os lotes (LIFO)
        $lotes_utilizados = extornarParaLotes($pdo, $transacao['medicamento_id'], $transacao['quantidade']);
        
        // Registrar movimentação de entrada
        $observacao_movimentacao = "Extorno da transação ID: $transacao_id";
        registrarMovimentacaoEntrada($pdo, $transacao['medicamento_id'], $transacao['quantidade'], $observacao_movimentacao);

        // Inserir transação negativa (extorno)
        $stmt = $pdo->prepare('INSERT INTO transacoes (paciente_id, medicamento_id, quantidade, usuario_id, data, observacoes) VALUES (?, ?, ?, ?, NOW(), ?)');
        $obs = '[EXTORNO DA TRANSACAO ' . $transacao_id . '] ' . ($transacao['observacoes'] ?? '');
        $stmt->execute([
            $transacao['paciente_id'],
            $transacao['medicamento_id'],
            -$transacao['quantidade'],
            $_SESSION['usuario']['id'],
            $obs
        ]);

        $pdo->commit();
        
        // Preparar resposta com informações dos lotes utilizados
        $lotes_info = [];
        foreach ($lotes_utilizados as $lote) {
            if ($lote['novo_lote']) {
                $lotes_info[] = "Novo lote {$lote['lote_nome']}: {$lote['quantidade_extornada']} unidades";
            } else {
                $lotes_info[] = "Lote {$lote['lote_nome']}: +{$lote['quantidade_extornada']} unidades";
            }
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Extorno realizado com sucesso!',
            'lotes_utilizados' => $lotes_info
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 