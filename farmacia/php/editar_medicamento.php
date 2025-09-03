<?php
include 'config.php';
include 'funcoes_estoque.php';

if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['perfil'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// Adicionar função calcularEstoqueAtual se não estiver definida
if (!function_exists('calcularEstoqueAtual')) {
    function calcularEstoqueAtual($pdo, $medicamentoId) {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(quantidade), 0) as total 
            FROM lotes_medicamentos 
            WHERE medicamento_id = ? AND quantidade > 0
        ");
        $stmt->execute([$medicamentoId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['total'];
    }
}

if (!isset($_GET['id'])) {
    header('Location: medicamentos.php');
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM medicamentos WHERE id = ?");
$stmt->execute([$_GET['id']]);
$medicamento = $stmt->fetch();

if (!$medicamento) {
    header('Location: medicamentos.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_medicamento'])) {
    try {
        $stmt = $pdo->prepare("UPDATE medicamentos SET 
                             nome = ?, apresentacao = ?, 
                             codigo = ?
                             WHERE id = ?");
        $stmt->execute([
            $_POST['nome'],
            $_POST['apresentacao'],
            $_POST['codigo'],
            $_GET['id']
        ]);

        // Atualizar todos os lotes
        if (isset($_POST['lotes']) && is_array($_POST['lotes'])) {
            foreach ($_POST['lotes'] as $lote_data) {
                if (isset($lote_data['id']) && !empty($lote_data['id'])) {
                    // Buscar quantidade anterior do lote para registrar movimentação
                    $stmt = $pdo->prepare("SELECT quantidade FROM lotes_medicamentos WHERE id = ? AND medicamento_id = ?");
                    $stmt->execute([$lote_data['id'], $_GET['id']]);
                    $lote_anterior = $stmt->fetch(PDO::FETCH_ASSOC);
                    $quantidade_anterior = $lote_anterior ? (int)$lote_anterior['quantidade'] : 0;
                    $quantidade_nova = (int)$lote_data['quantidade'];
                    
                    // Atualizar o lote
                    $stmt = $pdo->prepare("UPDATE lotes_medicamentos SET 
                                         lote = ?, 
                                         quantidade = ?,
                                         validade = ?
                                         WHERE id = ? AND medicamento_id = ?");
                    $stmt->execute([
                        $lote_data['lote'],
                        $quantidade_nova,
                        $lote_data['validade'],
                        $lote_data['id'],
                        $_GET['id']
                    ]);
                    
                    // Registrar movimentação se houve alteração na quantidade
                    if ($quantidade_anterior !== $quantidade_nova) {
                        $diferenca = $quantidade_nova - $quantidade_anterior;
                        // Calcular estoque total antes e depois da alteração
                        $estoque_total_anterior = calcularEstoqueAtual($pdo, $_GET['id']) - $diferenca;
                        $estoque_total_novo = calcularEstoqueAtual($pdo, $_GET['id']);
                        
                        $tipo_movimentacao = $diferenca > 0 ? 'AJUSTE_ENTRADA' : 'AJUSTE_SAIDA';
                        $observacao = "Edição manual do lote {$lote_data['lote']} - Quantidade alterada de {$quantidade_anterior} para {$quantidade_nova}";
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO movimentacoes (
                                medicamento_id, tipo, quantidade, quantidade_anterior, quantidade_nova, data, observacao, usuario_id
                            ) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)
                        ");
                        $stmt->execute([
                            $_GET['id'],
                            $tipo_movimentacao,
                            $diferenca,
                            $estoque_total_anterior,
                            $estoque_total_novo,
                            $observacao,
                            $_SESSION['usuario']['id']
                        ]);
                    }
                }
            }
        }

        header('Location: medicamentos.php?sucesso=Medicamento atualizado com sucesso');
    } catch (PDOException $e) {
        header('Location: editar_medicamento.php?id=' . $_GET['id'] . '&erro=' . urlencode($e->getMessage()));
    }
    exit();
}

// Buscar todos os lotes do medicamento
$stmt = $pdo->prepare("
    SELECT lm.*, 
           DATE_FORMAT(lm.validade, '%Y-%m-%d') as validade_formatada
    FROM lotes_medicamentos lm 
    WHERE lm.medicamento_id = ? 
    ORDER BY lm.validade ASC
");
$stmt->execute([$_GET['id']]);
$lotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Processar edição individual de lote
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_lote_individual'])) {
    try {
        $lote_id = (int)$_POST['lote_id'];
        $lote = $_POST['lote'];
        $quantidade_nova = (int)$_POST['quantidade'];
        $validade = $_POST['validade'];
        
        // Buscar quantidade anterior do lote
        $stmt = $pdo->prepare("SELECT quantidade FROM lotes_medicamentos WHERE id = ? AND medicamento_id = ?");
        $stmt->execute([$lote_id, $_GET['id']]);
        $lote_anterior = $stmt->fetch(PDO::FETCH_ASSOC);
        $quantidade_anterior = $lote_anterior ? (int)$lote_anterior['quantidade'] : 0;
        
        // Atualizar o lote
        $stmt = $pdo->prepare("UPDATE lotes_medicamentos SET 
                             lote = ?, 
                             quantidade = ?,
                             validade = ?
                             WHERE id = ? AND medicamento_id = ?");
        $stmt->execute([
            $lote,
            $quantidade_nova,
            $validade,
            $lote_id,
            $_GET['id']
        ]);
        
        // Registrar movimentação se a quantidade mudou
        if ($quantidade_nova !== $quantidade_anterior) {
            $diferenca = $quantidade_nova - $quantidade_anterior;
            $tipo_movimento = $diferenca > 0 ? 'AJUSTE_ENTRADA' : 'AJUSTE_SAIDA';
            
            $estoque_total_novo = calcularEstoqueAtual($pdo, $_GET['id']);
            $estoque_total_anterior = $estoque_total_novo - $diferenca;
            
            $stmt = $pdo->prepare("
                INSERT INTO movimentacoes (
                    medicamento_id, tipo, quantidade, 
                    quantidade_anterior, quantidade_nova, data, observacao, usuario_id
                ) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)
            ");
            $stmt->execute([
                $_GET['id'],
                $tipo_movimento,
                $diferenca,
                $estoque_total_anterior,
                $estoque_total_novo,
                'Edição individual do lote ' . $lote,
                $_SESSION['usuario']['id']
            ]);
        }
        
        header('Location: editar_medicamento.php?id=' . $_GET['id'] . '&lote_sucesso=1');
        exit();
    } catch (PDOException $e) {
        header('Location: editar_medicamento.php?id=' . $_GET['id'] . '&erro=' . urlencode($e->getMessage()));
        exit();
    }
}

// Processar adição de novos lotes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar_novo_lote'])) {
    try {
        if (isset($_POST['novo_lote']) && is_array($_POST['novo_lote'])) {
            foreach ($_POST['novo_lote'] as $novo_lote_data) {
                if (!empty($novo_lote_data['lote']) && !empty($novo_lote_data['quantidade'])) {
                    $lote = trim($novo_lote_data['lote']);
                    $quantidade = (int)$novo_lote_data['quantidade'];
                    $validade = !empty($novo_lote_data['validade']) ? $novo_lote_data['validade'] : null;
                    
                    // Verificar se já existe um lote com o mesmo número para este medicamento
                    $stmt = $pdo->prepare("SELECT id FROM lotes_medicamentos WHERE medicamento_id = ? AND lote = ?");
                    $stmt->execute([$_GET['id'], $lote]);
                    
                    if ($stmt->fetch()) {
                        throw new Exception("Já existe um lote com o número '{$lote}' para este medicamento.");
                    }
                    
                    // Inserir novo lote
                    $stmt = $pdo->prepare("INSERT INTO lotes_medicamentos (medicamento_id, lote, quantidade, validade) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$_GET['id'], $lote, $quantidade, $validade]);
                    
                    // Calcular estoque total após a adição
                    $estoque_total_anterior = calcularEstoqueAtual($pdo, $_GET['id']) - $quantidade;
                    $estoque_total_novo = calcularEstoqueAtual($pdo, $_GET['id']);
                    
                    // Registrar movimentação
                    $observacao = "Adição de novo lote '{$lote}' com quantidade {$quantidade}";
                    $stmt = $pdo->prepare("
                        INSERT INTO movimentacoes (
                            medicamento_id, tipo, quantidade, quantidade_anterior, quantidade_nova, data, observacao, usuario_id
                        ) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)
                    ");
                    $stmt->execute([
                        $_GET['id'],
                        'AJUSTE_ENTRADA',
                        $quantidade,
                        $estoque_total_anterior,
                        $estoque_total_novo,
                        $observacao,
                        $_SESSION['usuario']['id']
                    ]);
                }
            }
        }
        
        header('Location: editar_medicamento.php?id=' . $_GET['id'] . '&lote_sucesso=1');
        exit();
    } catch (Exception $e) {
        header('Location: editar_medicamento.php?id=' . $_GET['id'] . '&erro=' . urlencode($e->getMessage()));
        exit();
    } catch (PDOException $e) {
        header('Location: editar_medicamento.php?id=' . $_GET['id'] . '&erro=' . urlencode($e->getMessage()));
        exit();
    }
}


?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Editar Medicamento</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/editar_medicamento.css">
</head>
<body class="editar-medicamento">
<?php include 'header.php'; ?>
<main class="container">
    <div class="edit-card">
        <h2><i class="fas fa-edit"></i> Editar Medicamento</h2>

        <?php if (isset($_GET['erro'])): ?>
            <div class="erro"><i class="fas fa-exclamation-triangle"></i> Erro: <?= htmlspecialchars($_GET['erro'] ?? '') ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['ajuste']) && $_GET['ajuste'] === 'ok'): ?>
            <div class="sucesso">Ajuste de estoque realizado com sucesso!</div>
        <?php endif; ?>
        <?php if (isset($_GET['lote_sucesso'])): ?>
            <div class="sucesso">Lote atualizado com sucesso!</div>
        <?php endif; ?>
        <?php if (isset($ajuste_msg)) echo $ajuste_msg; ?>

        <!-- Seção de Informações Básicas -->
        <div class="medicamento-info">
            <h3><i class="fas fa-info-circle"></i> Informações do Medicamento</h3>
            <form method="POST">
                <input type="hidden" name="editar_medicamento" value="1">
                <div class="info-grid">
                    <div class="field-group">
                        <label for="nome">Nome do Medicamento:</label>
                        <textarea id="nome" name="nome" rows="2" required style="resize: vertical; min-height: 38px; padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 0.95em;"><?= htmlspecialchars($medicamento['nome'] ?? '') ?></textarea>
                    </div>
                    <div class="field-group">
                        <label for="apresentacao">Apresentação:</label>
                        <select id="apresentacao" name="apresentacao" required style="padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 0.95em;">
                            <?php
                            $stmt = $pdo->query("SHOW COLUMNS FROM medicamentos WHERE Field = 'apresentacao'");
                            $enumDef = $stmt->fetch(PDO::FETCH_ASSOC)['Type'];
                            preg_match_all("/'([^']+)'/", $enumDef, $matches);
                            $opcoes = $matches[1];
                            foreach ($opcoes as $opcao) {
                                $selected = ($medicamento['apresentacao'] === $opcao) ? 'selected' : '';
                                echo "<option value=\"$opcao\" $selected>$opcao</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="field-group">
                        <label for="codigo">Código:</label>
                        <input type="text" id="codigo" name="codigo" value="<?= htmlspecialchars($medicamento['codigo'] ?? '') ?>" required style="padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 0.95em;">
                    </div>
                </div>
            </form>
        </div>

        <!-- Seção de Lotes -->
        <div class="lotes-section">
            <h3><i class="fas fa-boxes"></i> Gerenciar Lotes</h3>
            <div class="lotes-container">
                <?php foreach ($lotes as $index => $lote): ?>
                <div class="lote-item">
                    <div class="lote-header">
                        <span class="lote-numero">Lote #<?= $index + 1 ?></span>
                    </div>
                    <form method="POST" class="lote-fields">
                        <input type="hidden" name="editar_lote_individual" value="1">
                        <input type="hidden" name="lote_id" value="<?= $lote['id'] ?>">
                        <div class="field-group">
                            <label>Número do Lote:</label>
                            <input type="text" name="lote" value="<?= htmlspecialchars($lote['lote']) ?>" required>
                        </div>
                        <div class="field-group">
                            <label>Quantidade:</label>
                            <input type="number" name="quantidade" value="<?= $lote['quantidade'] ?>" min="0" required>
                        </div>
                        <div class="field-group">
                            <label>Data de Validade:</label>
                            <input type="date" name="validade" value="<?= $lote['validade_formatada'] ?>">
                        </div>
                        <div class="field-group">
                            <button type="submit" class="btn-save-lote"><i class="fas fa-save"></i> Salvar Lote</button>
                        </div>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Botão para adicionar novo lote -->
            <div class="add-lote-section">
                <button type="button" id="addLoteBtn" class="btn-add-lote">
                    <i class="fas fa-plus"></i> Adicionar Novo Lote
                </button>
            </div>
        </div>
        
        <!-- Seção de Resumo -->
        <div class="resumo-section">
            <div class="resumo-grid">
                <div class="estoque-total">
                    <i class="fas fa-warehouse"></i>
                    <span>Estoque Total:</span>
                    <span class="estoque-valor"><?= htmlspecialchars(calcularEstoqueAtual($pdo, $medicamento['id'])) ?></span>
                </div>

            </div>
        </div>
    </div>
</main>


<script>
// Função para calcular e atualizar a quantidade total em tempo real
function atualizarQuantidadeTotal() {
    let quantidadeTotal = 0;
    
    // Somar quantidades dos lotes existentes
    const inputsQuantidade = document.querySelectorAll('input[name="quantidade"]');
    inputsQuantidade.forEach(function(input) {
        const valor = parseInt(input.value) || 0;
        quantidadeTotal += valor;
    });
    
    // Somar quantidades dos novos lotes
    const inputsNovoLote = document.querySelectorAll('input[name*="[quantidade]"]');
    inputsNovoLote.forEach(function(input) {
        if (input.name.includes('novo_lote')) {
            const valor = parseInt(input.value) || 0;
            quantidadeTotal += valor;
        }
    });
    
    // Atualizar o valor exibido na tela
    const estoqueValor = document.querySelector('.estoque-valor');
    if (estoqueValor) {
        estoqueValor.textContent = quantidadeTotal;
    }
}

// Contador para novos lotes
let novoLoteCounter = 0;

// Função para adicionar novo lote
function adicionarNovoLote() {
    novoLoteCounter++;
    const lotesContainer = document.querySelector('.lotes-container');
    
    const novoLoteHtml = `
        <div class="lote-item novo-lote" data-novo-lote="${novoLoteCounter}">
            <div class="lote-header">
                <span class="lote-numero">Novo Lote #${novoLoteCounter}</span>
                <button type="button" class="btn-remove-lote" onclick="removerLote(this)">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            <form method="POST" class="lote-fields">
                <input type="hidden" name="adicionar_novo_lote" value="1">
                <div class="field-group">
                    <label>Número do Lote:</label>
                    <input type="text" name="novo_lote[${novoLoteCounter}][lote]" required>
                </div>
                <div class="field-group">
                    <label>Quantidade:</label>
                    <input type="number" name="novo_lote[${novoLoteCounter}][quantidade]" min="0" required>
                </div>
                <div class="field-group">
                    <label>Data de Validade:</label>
                    <input type="date" name="novo_lote[${novoLoteCounter}][validade]">
                </div>
                <div class="field-group">
                    <button type="submit" class="btn-save-lote"><i class="fas fa-save"></i> Salvar Novo Lote</button>
                </div>
            </form>
        </div>
    `;
    
    lotesContainer.insertAdjacentHTML('beforeend', novoLoteHtml);
    
    // Adicionar event listener ao novo campo de quantidade
    const novoInput = lotesContainer.querySelector(`input[name="novo_lote[${novoLoteCounter}][quantidade]"]`);
    if (novoInput) {
        novoInput.addEventListener('input', atualizarQuantidadeTotal);
        novoInput.addEventListener('change', atualizarQuantidadeTotal);
    }
}

// Função para remover lote
function removerLote(button) {
    const loteItem = button.closest('.lote-item');
    if (loteItem && loteItem.classList.contains('novo-lote')) {
        loteItem.remove();
        atualizarQuantidadeTotal();
    }
}

// Adicionar event listeners aos campos de quantidade quando a página carregar
document.addEventListener('DOMContentLoaded', function() {
    const inputsQuantidade = document.querySelectorAll('input[name="quantidade"]');
    
    inputsQuantidade.forEach(function(input) {
        // Atualizar quando o valor mudar
        input.addEventListener('input', atualizarQuantidadeTotal);
        input.addEventListener('change', atualizarQuantidadeTotal);
    });
    
    // Adicionar event listener ao botão de adicionar lote
    const addLoteBtn = document.getElementById('addLoteBtn');
    if (addLoteBtn) {
        addLoteBtn.addEventListener('click', adicionarNovoLote);
    }
    
    // Calcular total inicial
    atualizarQuantidadeTotal();
});
</script>

</body>
</html>
