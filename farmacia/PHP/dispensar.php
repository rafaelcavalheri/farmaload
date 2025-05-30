<?php
ob_start(); // Evita erro de headers
include 'config.php'; // Supondo que inicia sessão e conecta ao banco
include 'funcoes_estoque.php';

$permitidos = ['admin', 'operador'];
if (!in_array($_SESSION['usuario']['perfil'] ?? '', $permitidos)) {
    header('Location: login.php');
    exit();
}

// Pegar ID do usuário logado da sessão
$usuario_id = $_SESSION['usuario']['id'] ?? null;
if (!$usuario_id) {
    die('Erro: Usuário não identificado.');
}

$mensagem = '';
$paciente = null;
$medicamentos = [];
$pacientes = []; // Array para armazenar resultados da busca por nome

// Buscar paciente
if (isset($_POST['buscar'])) {
    $busca = trim($_POST['busca'] ?? '');
    
    if (strlen($busca) >= 3) {
        $where = [];
        $params = [];
        
        // Busca por nome
        $where[] = "LOWER(nome) LIKE LOWER(?)";
        $params[] = '%' . $busca . '%';
        
        // Busca por SIM
        $where[] = "LOWER(sim) LIKE LOWER(?)";
        $params[] = '%' . $busca . '%';
        
        // Busca por CPF (remove caracteres não numéricos para comparação)
        $cpf_limpo = preg_replace('/\D/', '', $busca);
        if (!empty($cpf_limpo)) {
            $where[] = "REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '') LIKE ?";
            $params[] = '%' . $cpf_limpo . '%';
        }
        
        $whereClause = implode(" OR ", $where);
        $stmt = $pdo->prepare("SELECT *, DATE_FORMAT(validade, '%d/%m/%Y') as validade_formatada, renovado 
                              FROM pacientes 
                              WHERE " . $whereClause . " 
                              ORDER BY nome");
        $stmt->execute($params);
        $pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($pacientes)) {
            $mensagem = '<div class="alert erro">Nenhum paciente encontrado com os critérios informados</div>';
        } elseif (count($pacientes) == 1) {
            // Se encontrou apenas um paciente, já carrega os dados
            $paciente = $pacientes[0];
            $stmt = $pdo->prepare("
                SELECT DISTINCT
                    pm.id, 
                    m.id as medicamento_id,
                    m.nome, 
                    COALESCE(pm.quantidade, 0) as quantidade_recebida,
                    COALESCE(pm.quantidade_solicitada, pm.quantidade) as quantidade_solicitada,
                    m.quantidade AS quantidade_estoque, 
                    pm.renovado,
                    COALESCE((
                        SELECT SUM(quantidade) 
                        FROM transacoes 
                        WHERE medicamento_id = m.id 
                        AND paciente_id = pm.paciente_id
                    ), 0) as quantidade_entregue
                FROM paciente_medicamentos pm
                INNER JOIN medicamentos m ON m.id = pm.medicamento_id
                WHERE pm.paciente_id = ?
                ORDER BY m.nome ASC
            ");
            $stmt->execute([$paciente['id']]);
            $medicamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calcular quantidade disponível para cada medicamento
            foreach ($medicamentos as &$med) {
                $med['quantidade_disponivel'] = max(0, (int)$med['quantidade_solicitada'] - (int)$med['quantidade_entregue']);
            }
            unset($med); // Limpar referência do foreach
        }
    } else {
        $mensagem = '<div class="alert erro">Digite pelo menos 3 caracteres para buscar</div>';
    }
}

// Selecionar paciente específico após a busca
if (isset($_POST['paciente_id'])) {
    $paciente_id = (int)$_POST['paciente_id'];
    $stmt = $pdo->prepare("SELECT *, DATE_FORMAT(validade, '%d/%m/%Y') as validade_formatada FROM pacientes WHERE id = ?");
    $stmt->execute([$paciente_id]);
    $paciente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($paciente) {
        // Debug para verificar os dados do paciente
        error_log("Dados do paciente: " . print_r($paciente, true));

        // Buscar pessoas autorizadas
        $stmt = $pdo->prepare("SELECT nome, cpf FROM pessoas_autorizadas WHERE paciente_id = ? ORDER BY id");
        $stmt->execute([$paciente_id]);
        $pessoas_autorizadas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Buscar medicamentos do paciente
        $stmt = $pdo->prepare("
            SELECT DISTINCT
                pm.id, 
                m.id as medicamento_id,
                m.nome, 
                COALESCE(pm.quantidade, 0) as quantidade_recebida,
                COALESCE(pm.quantidade_solicitada, pm.quantidade) as quantidade_solicitada,
                m.quantidade AS quantidade_estoque,
                pm.renovado,
                COALESCE((
                    SELECT SUM(quantidade) 
                    FROM transacoes 
                    WHERE medicamento_id = m.id 
                    AND paciente_id = pm.paciente_id
                ), 0) as quantidade_entregue,
                :validade_formatada as validade_formatada
            FROM paciente_medicamentos pm
            INNER JOIN medicamentos m ON m.id = pm.medicamento_id
            WHERE pm.paciente_id = :paciente_id
            ORDER BY m.nome ASC
        ");
        $stmt->execute([
            ':paciente_id' => $paciente_id,
            ':validade_formatada' => $paciente['validade_formatada']
        ]);
        $medicamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calcular quantidade disponível para cada medicamento
        foreach ($medicamentos as &$med) {
            $med['quantidade_disponivel'] = max(0, (int)$med['quantidade_solicitada'] - (int)$med['quantidade_entregue']);
        }
        unset($med); // Limpar referência do foreach

        // Debug para verificar os medicamentos
        error_log("Medicamentos encontrados: " . print_r($medicamentos, true));
    }
}

// Processar dispensação
if (isset($_POST['dispensar'])) {
    $dispensas = $_POST['dispensa'] ?? [];
    $paciente_id = (int)$_POST['paciente_id'];
    $nova_observacao = trim($_POST['observacao'] ?? '');
    $observacao_original = trim($_POST['observacao_original'] ?? '');

    $stmt = $pdo->prepare("SELECT id, observacao FROM pacientes WHERE id = ?");
    $stmt->execute([$paciente_id]);
    $paciente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$paciente) {
        $mensagem = '<div class="alert erro">Paciente não encontrado.</div>';
    } else {
        try {
            $pdo->beginTransaction();

            // Se a observação foi modificada, atualiza
            if ($nova_observacao !== $observacao_original) {
                $stmt = $pdo->prepare("UPDATE pacientes SET observacao = ? WHERE id = ?");
                $stmt->execute([$nova_observacao, $paciente_id]);
                
                // Atualiza o valor na sessão atual
                if (isset($paciente)) {
                    $paciente['observacao'] = $nova_observacao;
                }
            }

            foreach ($dispensas as $pm_id => $qtd) {
                $qtd = (int)$qtd;
                if ($qtd <= 0) continue;

                // Verifica se quantidade disponível é suficiente
                $verifica = $pdo->prepare("
                    SELECT 
                        pm.quantidade as quantidade_recebida,
                        COALESCE(pm.quantidade_solicitada, pm.quantidade) as quantidade_solicitada,
                        COALESCE((
                            SELECT SUM(quantidade) 
                            FROM transacoes 
                            WHERE medicamento_id = pm.medicamento_id 
                            AND paciente_id = pm.paciente_id
                        ), 0) as quantidade_entregue,
                        pm.medicamento_id
                    FROM paciente_medicamentos pm
                    WHERE pm.id = ? AND pm.paciente_id = ?
                ");
                $verifica->execute([$pm_id, $paciente['id']]);
                $row = $verifica->fetch(PDO::FETCH_ASSOC);

                if (!$row) {
                    throw new Exception("Medicamento não encontrado ou não vinculado ao paciente.");
                }

                $quantidade_disponivel = max(0, (int)$row['quantidade_solicitada'] - (int)$row['quantidade_entregue']);
                if ($qtd > $quantidade_disponivel) {
                    throw new Exception("Quantidade solicitada maior que a disponível.");
                }

                // Atualiza apenas a tabela de transações e o estoque do medicamento
                // Não altera mais a quantidade na tabela paciente_medicamentos
                $stmt = $pdo->prepare("
                    INSERT INTO transacoes (medicamento_id, usuario_id, paciente_id, quantidade, observacoes)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $row['medicamento_id'],
                    $usuario_id,
                    $paciente['id'],
                    $qtd,
                    $nova_observacao
                ]);
            }

            $pdo->commit();
            $mensagem = '<div class="alert sucesso">Dispensação realizada com sucesso!</div>';
        } catch (Exception $e) {
            $pdo->rollBack();
            $mensagem = '<div class="alert erro">Erro ao dispensar: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// Processar atualização de observações
if (isset($_POST['atualizar_observacao'])) {
    $paciente_id = (int)$_POST['paciente_id'];
    $observacao = trim($_POST['observacao']);

    try {
        $stmt = $pdo->prepare("UPDATE pacientes SET observacao = ? WHERE id = ?");
        $stmt->execute([$observacao, $paciente_id]);
        $mensagem = '<div class="alert sucesso">Observações atualizadas com sucesso!</div>';
        
        // Atualiza os dados do paciente na sessão atual
        if (isset($paciente) && $paciente['id'] == $paciente_id) {
            $paciente['observacao'] = $observacao;
        }
    } catch (Exception $e) {
        $mensagem = '<div class="alert erro">Erro ao atualizar observações: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Dispensa de Medicamentos</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .search-container {
            margin-bottom: 20px;
        }
        .search-fields {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
            margin-bottom: 10px;
        }
        .search-field {
            display: flex;
            flex-direction: column;
        }
        .search-field label {
            margin-bottom: 5px;
            font-weight: bold;
        }
        .search-input {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            width: 100%;
            transition: border-color 0.3s ease;
        }
        .search-input:focus {
            border-color: #4a90e2;
            outline: none;
            box-shadow: 0 0 5px rgba(74, 144, 226, 0.3);
        }
        .btn-secondary {
            padding: 12px 24px;
            font-size: 16px;
        }
        .paciente-item {
            padding: 10px;
            margin: 5px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
        }
        .paciente-item:hover {
            background-color: #f5f5f5;
        }
        .observacao-box {
            margin: 10px 0;
            padding: 10px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .observacao-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .observacao-content {
            white-space: pre-wrap;
            margin-top: 5px;
        }
        .btn-edit {
            padding: 5px 10px;
            background-color: #f0f0f0;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-edit:hover {
            background-color: #e0e0e0;
        }
        /* Estilos do Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #888;
            border-radius: 5px;
            width: 80%;
            max-width: 600px;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover {
            color: #000;
        }
        .form-observacao textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
        }
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .observacao-editor {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: #fff;
            font-family: inherit;
            font-size: inherit;
            resize: vertical;
            margin-top: 5px;
        }
        .observacao-editor:focus {
            border-color: #4a90e2;
            outline: none;
            box-shadow: 0 0 3px rgba(74, 144, 226, 0.3);
        }
        .autorizados-box {
            margin: 15px 0;
            padding: 10px;
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
        }
        .autorizados-box h4 {
            margin: 0 0 10px 0;
            color: #495057;
            font-size: 1.1em;
        }
        .autorizados-lista {
            display: grid;
            gap: 10px;
        }
        .autorizado-item {
            padding: 8px;
            background-color: #fff;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .autorizado-nome {
            font-weight: 500;
            margin-right: 15px;
        }
        .autorizado-cpf {
            color: #6c757d;
        }
        @media (max-width: 576px) {
            .autorizado-item {
                flex-direction: column;
                align-items: flex-start;
            }
            .autorizado-nome {
                margin-bottom: 5px;
            }
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
            margin-left: 10px;
        }
        .badge.renovado {
            background-color: #28a745;
            color: white;
        }
        .badge i {
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>
    <main class="container">
        <h2><i class="fas fa-prescription-bottle-alt"></i> Dispensa de Medicamentos</h2>
        <?= $mensagem ?>

        <!-- Formulário de busca unificado -->
        <form method="POST" class="form-group">
            <div class="search-container">
                <div class="search-fields">
                    <div class="search-field">
                        <label for="busca">Buscar por Nome, CPF ou SIM:</label>
                        <input type="text" id="busca" name="busca" 
                               placeholder="Digite o nome, CPF ou SIM do paciente..." 
                               value="<?= htmlspecialchars($_POST['busca'] ?? '') ?>"
                               class="search-input">
                    </div>
                </div>
                <button type="submit" name="buscar" class="btn-secondary">
                    <i class="fas fa-search"></i> Buscar
                </button>
            </div>
        </form>

        <?php if (!empty($pacientes) && count($pacientes) > 1): ?>
            <!-- Exibir lista de pacientes quando encontrar mais de um -->
            <h3>Resultados da busca (selecione um paciente):</h3>
            <div class="pacientes-lista">
                <?php foreach ($pacientes as $p): ?>
                    <form method="POST">
                        <input type="hidden" name="paciente_id" value="<?= $p['id'] ?>">
                        <div class="paciente-item" onclick="this.closest('form').submit();">
                            <strong><?= htmlspecialchars($p['nome']) ?></strong><br>
                            CPF: <?= preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $p['cpf']) ?>
                            <?php if (!empty($p['telefone'])): ?>
                                | Tel: <?= htmlspecialchars($p['telefone']) ?>
                            <?php endif; ?>
                            <?php if (!empty($p['validade_formatada'])): ?>
                                <br>Validade do Processo: <?= htmlspecialchars($p['validade_formatada']) ?>
                            <?php endif; ?>
                        </div>
                    </form>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($paciente) && isset($paciente['nome'], $paciente['cpf'])): ?>
            <section class="paciente-info">
                <h3>Paciente: <?= htmlspecialchars($paciente['nome']) ?> 
                    (CPF: <?= preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $paciente['cpf']) ?>)
                </h3>
                <?php if (!empty($paciente['validade_formatada'])): ?>
                    <p>
                        <strong>Validade do Processo:</strong> <?= htmlspecialchars($paciente['validade_formatada']) ?>
                        <?php if ($paciente['renovado']): ?>
                            <span class="badge renovado"><i class="fas fa-sync-alt"></i> Renovação em Andamento</span>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>

                <?php if (!empty($pessoas_autorizadas)): ?>
                    <div class="autorizados-box">
                        <h4><i class="fas fa-users"></i> Pessoas Autorizadas:</h4>
                        <div class="autorizados-lista">
                            <?php foreach ($pessoas_autorizadas as $autorizado): ?>
                                <div class="autorizado-item">
                                    <span class="autorizado-nome"><?= htmlspecialchars($autorizado['nome']) ?></span>
                                    <span class="autorizado-cpf">CPF: <?= preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $autorizado['cpf']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </section>

            <?php if ($medicamentos): ?>
                <form method="POST">
                    <input type="hidden" name="paciente_id" value="<?= $paciente['id'] ?>">
                    <?php if (isset($paciente['observacao'])): ?>
                        <input type="hidden" name="observacao_original" value="<?= htmlspecialchars($paciente['observacao']) ?>">
                    <?php endif; ?>
                    
                    <?php if (!empty($paciente['observacao']) || true): ?>
                        <div class="observacao-box">
                            <div class="observacao-header">
                                <strong>Observações:</strong>
                            </div>
                            <textarea name="observacao" class="observacao-editor" rows="4"><?= htmlspecialchars($paciente['observacao'] ?? '') ?></textarea>
                        </div>
                    <?php endif; ?>

                    <table>
                        <thead>
                            <tr>
                                <th>Medicamento</th>
                                <th>Estoque Total</th>
                                <th>Qtde Recebida</th>
                                <th>Qtde Solicitada</th>
                                <th>Qtde Entregue</th>
                                <th>Qtde Disponível</th>
                                <th>Status Renovação</th>
                                <th>Data Renovação</th>
                                <th>Dispensar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($medicamentos as $med): ?>
                                <tr>
                                    <td><?= htmlspecialchars($med['nome']) ?></td>
                                    <td><?= calcularEstoqueAtual($pdo, $med['medicamento_id']) ?></td>
                                    <td><?= (int)$med['quantidade_recebida'] ?></td>
                                    <td><?= (int)$med['quantidade_solicitada'] ?></td>
                                    <td><?= (int)$med['quantidade_entregue'] ?></td>
                                    <td><?= (int)$med['quantidade_disponivel'] ?></td>
                                    <td>
                                        <?php if ((int)$med['renovado'] === 1): ?>
                                            <span class="badge renovado"><i class="fas fa-sync-alt"></i> Renovação em Andamento</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($med['validade_formatada'])): ?>
                                            <span class="<?= ((int)$med['renovado'] === 1) ? 'badge renovado' : '' ?>">
                                                <?= htmlspecialchars($med['validade_formatada']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $max_disponivel = min((int)$med['quantidade_disponivel'], calcularEstoqueAtual($pdo, $med['medicamento_id']));
                                        ?>
                                        <input type="number" name="dispensa[<?= $med['id'] ?>]"
                                               min="0" 
                                               max="<?= $max_disponivel ?>"
                                               value="0" required>
                                        <small>Máx: <?= $max_disponivel ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button type="submit" name="dispensar" class="btn-primary">
                        <i class="fas fa-check"></i> Dispensar
                    </button>
                </form>
            <?php else: ?>
                <div class="alert"><i class="fas fa-info-circle"></i> Nenhum medicamento cadastrado para este paciente.</div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
    <?php include 'footer.php'; ?>
    <script>
        // Removido o código AJAX que estava causando problemas
    </script>
</body>
</html>
