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

// Retorna a quantidade da última importação específica de um medicamento
function getTotalUltimaImportacao($pdo, $medicamento_id) {
    // Buscar a última importação específica (não a soma do dia)
    $stmt = $pdo->prepare("
        SELECT quantidade as total, data 
        FROM movimentacoes 
        WHERE medicamento_id = ? AND tipo = 'IMPORTACAO' 
        ORDER BY data DESC 
        LIMIT 1
    ");
    $stmt->execute([$medicamento_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
} 