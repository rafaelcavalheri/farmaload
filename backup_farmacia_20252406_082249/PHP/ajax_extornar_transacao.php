<?php
include 'config.php';
header('Content-Type: application/json');

try {
    verificarAutenticacao(['admin', 'operador']);
    $input = json_decode(file_get_contents('php://input'), true);
    $transacao_id = isset($input['transacao_id']) ? (int)$input['transacao_id'] : 0;
    if ($transacao_id <= 0) {
        throw new Exception('ID da transação inválido.');
    }

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

    echo json_encode(['success' => true, 'message' => 'Extorno realizado com sucesso!']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 