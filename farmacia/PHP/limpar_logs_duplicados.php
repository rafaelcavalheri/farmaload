<?php
require_once 'config.php';

try {
    // As variáveis $pdo já está disponível do config.php
    echo "Iniciando limpeza de logs duplicados...\n";
    
    // Identificar registros duplicados
    $stmt = $pdo->prepare("
        SELECT 
            usuario_id, 
            usuario_nome, 
            data_hora, 
            arquivo_nome, 
            quantidade_registros, 
            status,
            COUNT(*) as total
        FROM logs_importacao 
        GROUP BY usuario_id, usuario_nome, data_hora, arquivo_nome, quantidade_registros, status
        HAVING COUNT(*) > 1
    ");
    $stmt->execute();
    $duplicados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($duplicados)) {
        echo "Nenhum registro duplicado encontrado.\n";
        exit;
    }
    
    echo "Encontrados " . count($duplicados) . " grupos de registros duplicados.\n";
    
    $totalRemovidos = 0;
    
    foreach ($duplicados as $grupo) {
        echo "Processando grupo: {$grupo['usuario_nome']} - {$grupo['arquivo_nome']} - {$grupo['data_hora']}\n";
        
        // Buscar todos os IDs para este grupo
        $stmt = $pdo->prepare("
            SELECT id 
            FROM logs_importacao 
            WHERE usuario_id = ? 
            AND usuario_nome = ? 
            AND data_hora = ? 
            AND arquivo_nome = ? 
            AND quantidade_registros = ? 
            AND status = ?
            ORDER BY id
        ");
        $stmt->execute([
            $grupo['usuario_id'],
            $grupo['usuario_nome'],
            $grupo['data_hora'],
            $grupo['arquivo_nome'],
            $grupo['quantidade_registros'],
            $grupo['status']
        ]);
        
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Manter o primeiro ID e remover os demais
        $primeiroId = array_shift($ids);
        echo "  Mantendo ID: $primeiroId\n";
        
        if (!empty($ids)) {
            // Remover detalhes dos logs duplicados
            $placeholders = str_repeat('?,', count($ids) - 1) . '?';
            $stmt = $pdo->prepare("DELETE FROM logs_importacao_detalhes WHERE log_importacao_id IN ($placeholders)");
            $stmt->execute($ids);
            
            // Remover os logs duplicados
            $stmt = $pdo->prepare("DELETE FROM logs_importacao WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            
            $removidos = count($ids);
            $totalRemovidos += $removidos;
            echo "  Removidos $removidos registros duplicados.\n";
        }
    }
    
    echo "\nLimpeza concluída! Total de registros removidos: $totalRemovidos\n";
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
?> 