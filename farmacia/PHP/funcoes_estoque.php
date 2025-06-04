<?php
// Função para calcular o estoque atual de um medicamento
function calcularEstoqueAtual($pdo, $medicamento_id) {
    // Buscar a soma das quantidades dos lotes ativos
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(quantidade), 0) 
        FROM lotes_medicamentos 
        WHERE medicamento_id = ?
    ");
    $stmt->execute([$medicamento_id]);
    $estoque_lotes = (int)$stmt->fetchColumn();

    // Buscar a soma das saídas (transações)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(quantidade), 0) 
        FROM transacoes 
        WHERE medicamento_id = ?
    ");
    $stmt->execute([$medicamento_id]);
    $saidas = (int)$stmt->fetchColumn();

    // Retorna o estoque atual (lotes - saídas)
    return max(0, $estoque_lotes - $saidas);
}

// Retorna a soma das quantidades da última importação de um medicamento
function getTotalUltimaImportacao($pdo, $medicamento_id) {
    // Descobrir a data (apenas dia) da última importação
    $stmt = $pdo->prepare("SELECT DATE(data) as data_dia FROM movimentacoes WHERE medicamento_id = ? AND tipo = 'IMPORTACAO' ORDER BY data DESC LIMIT 1");
    $stmt->execute([$medicamento_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    $data_ultima = $row['data_dia'];
    // Somar todas as importações desse medicamento nesse mesmo dia
    $stmt = $pdo->prepare("SELECT SUM(quantidade) as total, MAX(data) as data FROM movimentacoes WHERE medicamento_id = ? AND tipo = 'IMPORTACAO' AND DATE(data) = ?");
    $stmt->execute([$medicamento_id, $data_ultima]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
} 