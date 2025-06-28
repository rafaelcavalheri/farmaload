<?php
// Função para calcular o estoque atual de um medicamento
function calcularEstoqueAtual($pdo, $medicamento_id) {
    // Com a nova implementação de controle de lotes, o estoque é calculado
    // diretamente dos lotes, já que eles são atualizados durante a dispensa
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(quantidade), 0) 
        FROM lotes_medicamentos 
        WHERE medicamento_id = ?
    ");
    $stmt->execute([$medicamento_id]);
    $estoque_lotes = (int)$stmt->fetchColumn();

    // Retorna o estoque atual baseado apenas nos lotes
    return max(0, $estoque_lotes);
}

// Função para calcular o estoque baseado no método antigo (lotes - transações)
// Mantida para compatibilidade e comparação
function calcularEstoqueAntigo($pdo, $medicamento_id) {
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

// Nova função para dispensar medicamentos dos lotes (FIFO - First In, First Out)
function dispensarDosLotes($pdo, $medicamento_id, $quantidade_dispensar) {
    // Buscar lotes ordenados por validade (mais antigos primeiro) e depois por ID
    $stmt = $pdo->prepare("
        SELECT id, lote, quantidade, validade 
        FROM lotes_medicamentos 
        WHERE medicamento_id = ? AND quantidade > 0 
        ORDER BY validade ASC, id ASC
    ");
    $stmt->execute([$medicamento_id]);
    $lotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($lotes)) {
        throw new Exception("Nenhum lote disponível para o medicamento.");
    }
    
    // Calcular estoque total disponível nos lotes
    $estoque_total = array_sum(array_column($lotes, 'quantidade'));
    
    if ($estoque_total < $quantidade_dispensar) {
        throw new Exception("Estoque insuficiente nos lotes. Disponível: $estoque_total, Solicitado: $quantidade_dispensar");
    }
    
    $quantidade_restante = $quantidade_dispensar;
    $lotes_utilizados = [];
    
    // Percorrer os lotes do mais antigo para o mais novo
    foreach ($lotes as $lote) {
        if ($quantidade_restante <= 0) break;
        
        $quantidade_disponivel_lote = (int)$lote['quantidade'];
        $quantidade_utilizar = min($quantidade_disponivel_lote, $quantidade_restante);
        
        // Atualizar a quantidade do lote
        $nova_quantidade = $quantidade_disponivel_lote - $quantidade_utilizar;
        $stmt = $pdo->prepare("
            UPDATE lotes_medicamentos 
            SET quantidade = ? 
            WHERE id = ?
        ");
        $stmt->execute([$nova_quantidade, $lote['id']]);
        
        // Registrar o lote utilizado
        $lotes_utilizados[] = [
            'lote_id' => $lote['id'],
            'lote_nome' => $lote['lote'],
            'quantidade_utilizada' => $quantidade_utilizar,
            'quantidade_anterior' => $quantidade_disponivel_lote,
            'quantidade_nova' => $nova_quantidade,
            'validade' => $lote['validade']
        ];
        
        $quantidade_restante -= $quantidade_utilizar;
    }
    
    return $lotes_utilizados;
}

// Nova função para extornar medicamentos para os lotes (LIFO - Last In, First Out)
function extornarParaLotes($pdo, $medicamento_id, $quantidade_extornar) {
    // Buscar lotes ordenados por validade (mais novos primeiro) e depois por ID (mais novos primeiro)
    // Isso garante que o extorno vá para os lotes mais recentes primeiro
    $stmt = $pdo->prepare("
        SELECT id, lote, quantidade, validade 
        FROM lotes_medicamentos 
        WHERE medicamento_id = ? 
        ORDER BY validade DESC, id DESC
    ");
    $stmt->execute([$medicamento_id]);
    $lotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($lotes)) {
        // Se não há lotes, criar um novo lote para o extorno
        $lote_nome = 'EXT' . date('Ymd') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        $validade_padrao = date('Y-m-d', strtotime('+1 year'));
        
        $stmt = $pdo->prepare("
            INSERT INTO lotes_medicamentos (medicamento_id, lote, quantidade, validade)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$medicamento_id, $lote_nome, $quantidade_extornar, $validade_padrao]);
        
        return [[
            'lote_id' => $pdo->lastInsertId(),
            'lote_nome' => $lote_nome,
            'quantidade_extornada' => $quantidade_extornar,
            'quantidade_anterior' => 0,
            'quantidade_nova' => $quantidade_extornar,
            'validade' => $validade_padrao,
            'novo_lote' => true
        ]];
    }
    
    $quantidade_restante = $quantidade_extornar;
    $lotes_utilizados = [];
    
    // Percorrer os lotes do mais novo para o mais antigo
    foreach ($lotes as $lote) {
        if ($quantidade_restante <= 0) break;
        
        $quantidade_atual_lote = (int)$lote['quantidade'];
        $quantidade_adicionar = min($quantidade_restante, $quantidade_extornar);
        
        // Atualizar a quantidade do lote
        $nova_quantidade = $quantidade_atual_lote + $quantidade_adicionar;
        $stmt = $pdo->prepare("
            UPDATE lotes_medicamentos 
            SET quantidade = ? 
            WHERE id = ?
        ");
        $stmt->execute([$nova_quantidade, $lote['id']]);
        
        // Registrar o lote utilizado
        $lotes_utilizados[] = [
            'lote_id' => $lote['id'],
            'lote_nome' => $lote['lote'],
            'quantidade_extornada' => $quantidade_adicionar,
            'quantidade_anterior' => $quantidade_atual_lote,
            'quantidade_nova' => $nova_quantidade,
            'validade' => $lote['validade'],
            'novo_lote' => false
        ];
        
        $quantidade_restante -= $quantidade_adicionar;
    }
    
    // Se ainda há quantidade para extornar, criar um novo lote
    if ($quantidade_restante > 0) {
        $lote_nome = 'EXT' . date('Ymd') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        $validade_padrao = date('Y-m-d', strtotime('+1 year'));
        
        $stmt = $pdo->prepare("
            INSERT INTO lotes_medicamentos (medicamento_id, lote, quantidade, validade)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$medicamento_id, $lote_nome, $quantidade_restante, $validade_padrao]);
        
        $lotes_utilizados[] = [
            'lote_id' => $pdo->lastInsertId(),
            'lote_nome' => $lote_nome,
            'quantidade_extornada' => $quantidade_restante,
            'quantidade_anterior' => 0,
            'quantidade_nova' => $quantidade_restante,
            'validade' => $validade_padrao,
            'novo_lote' => true
        ];
    }
    
    return $lotes_utilizados;
}

// Função para registrar movimentação de saída dos lotes
function registrarMovimentacaoSaida($pdo, $medicamento_id, $quantidade, $observacao = '') {
    // Calcular quantidade anterior
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(quantidade), 0) as quantidade_atual
        FROM lotes_medicamentos 
        WHERE medicamento_id = ?
    ");
    $stmt->execute([$medicamento_id]);
    $quantidade_anterior = (int)$stmt->fetchColumn();
    
    // Calcular quantidade nova
    $quantidade_nova = $quantidade_anterior - $quantidade;
    
    // Registrar movimentação
    $stmt = $pdo->prepare("
        INSERT INTO movimentacoes (
            medicamento_id, 
            tipo, 
            quantidade, 
            quantidade_anterior,
            quantidade_nova,
            data,
            observacao
        ) VALUES (?, 'SAIDA', ?, ?, ?, NOW(), ?)
    ");
    $stmt->execute([
        $medicamento_id,
        $quantidade,
        $quantidade_anterior,
        $quantidade_nova,
        $observacao
    ]);
}

// Função para registrar movimentação de entrada (extorno) dos lotes
function registrarMovimentacaoEntrada($pdo, $medicamento_id, $quantidade, $observacao = '') {
    // Calcular quantidade anterior
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(quantidade), 0) as quantidade_atual
        FROM lotes_medicamentos 
        WHERE medicamento_id = ?
    ");
    $stmt->execute([$medicamento_id]);
    $quantidade_anterior = (int)$stmt->fetchColumn();
    
    // Calcular quantidade nova
    $quantidade_nova = $quantidade_anterior + $quantidade;
    
    // Registrar movimentação
    $stmt = $pdo->prepare("
        INSERT INTO movimentacoes (
            medicamento_id, 
            tipo, 
            quantidade, 
            quantidade_anterior,
            quantidade_nova,
            data,
            observacao
        ) VALUES (?, 'ENTRADA', ?, ?, ?, NOW(), ?)
    ");
    $stmt->execute([
        $medicamento_id,
        $quantidade,
        $quantidade_anterior,
        $quantidade_nova,
        $observacao
    ]);
}

// Função para verificar lotes vencidos ou próximos do vencimento
function verificarLotesVencimento($pdo, $dias_para_vencimento = 30) {
    $data_limite = date('Y-m-d', strtotime("+$dias_para_vencimento days"));
    
    $stmt = $pdo->prepare("
        SELECT 
            lm.id,
            lm.lote,
            lm.quantidade,
            lm.validade,
            m.nome as medicamento_nome,
            m.id as medicamento_id
        FROM lotes_medicamentos lm
        JOIN medicamentos m ON m.id = lm.medicamento_id
        WHERE lm.quantidade > 0 
        AND lm.validade <= ?
        ORDER BY lm.validade ASC, m.nome ASC
    ");
    $stmt->execute([$data_limite]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Função para obter informações detalhadas dos lotes de um medicamento
function obterLotesMedicamento($pdo, $medicamento_id) {
    $stmt = $pdo->prepare("
        SELECT 
            id,
            lote,
            quantidade,
            validade,
            data_cadastro,
            data_atualizacao
        FROM lotes_medicamentos 
        WHERE medicamento_id = ? AND quantidade > 0
        ORDER BY validade ASC, id ASC
    ");
    $stmt->execute([$medicamento_id]);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
} 