<?php
require_once 'config.php';

try {
    // Iniciar transação
    $pdo->beginTransaction();
    
    // Atualizar quantidade de todos os medicamentos para 0
    $stmt = $pdo->prepare("UPDATE medicamentos SET quantidade = 0");
    $stmt->execute();
    
    // Registrar na tabela de movimentações
    $stmt = $pdo->prepare("
        INSERT INTO movimentacoes (
            medicamento_id,
            tipo,
            quantidade,
            quantidade_anterior,
            quantidade_nova,
            data,
            observacao
        )
        SELECT 
            id,
            'ZERAMENTO',
            quantidade,
            quantidade,
            0,
            NOW(),
            'Zeramento automático de estoque'
        FROM medicamentos
    ");
    $stmt->execute();
    
    // Confirmar transação
    $pdo->commit();
    
    echo "Quantidade de todos os medicamentos foi zerada com sucesso!";
} catch (Exception $e) {
    // Em caso de erro, desfazer transação
    $pdo->rollBack();
    echo "Erro ao zerar medicamentos: " . $e->getMessage();
}
?> 