<?php
include 'config.php';

echo "Limpando dados do banco...\n";

try {
    $pdo->beginTransaction();
    
    $pdo->exec('DELETE FROM paciente_medicamentos');
    echo "Tabela paciente_medicamentos limpa\n";
    
    $pdo->exec('DELETE FROM transacoes');
    echo "Tabela transacoes limpa\n";
    
    $pdo->exec('DELETE FROM movimentacoes');
    echo "Tabela movimentacoes limpa\n";
    
    $pdo->exec('DELETE FROM lotes_medicamentos');
    echo "Tabela lotes_medicamentos limpa\n";
    
    $pdo->exec('DELETE FROM medicamentos');
    echo "Tabela medicamentos limpa\n";
    
    $pdo->exec('DELETE FROM pacientes');
    echo "Tabela pacientes limpa\n";
    
    $pdo->commit();
    echo "✓ Dados limpos com sucesso!\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Erro: " . $e->getMessage() . "\n";
}
?> 