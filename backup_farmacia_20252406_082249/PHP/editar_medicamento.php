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

        // Atualizar o lote específico
        if (isset($_POST['lote_id']) && !empty($_POST['lote_id'])) {
            $stmt = $pdo->prepare("UPDATE lotes_medicamentos SET 
                                 lote = ?, 
                                 quantidade = ?,
                                 validade = ?
                                 WHERE id = ? AND medicamento_id = ?");
            $stmt->execute([
                $_POST['lote'],
                $_POST['quantidade'],
                $_POST['validade'],
                $_POST['lote_id'],
                $_GET['id']
            ]);
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajustar_estoque'])) {
    $estoque_correto = (int)$_POST['estoque_correto'];
    $estoque_atual = calcularEstoqueAtual($pdo, $medicamento['id']);
    $diferenca = $estoque_correto - $estoque_atual;
    if ($diferenca !== 0) {
        try {
            // Iniciar transação
            $pdo->beginTransaction();

            // Registrar a movimentação
            $stmt = $pdo->prepare("
                INSERT INTO movimentacoes (
                    medicamento_id, tipo, quantidade, quantidade_anterior, quantidade_nova, data, observacao
                ) VALUES (?, 'AJUSTE', ?, ?, ?, NOW(), ?)
            ");
            $stmt->execute([
                $medicamento['id'],
                $diferenca,
                $estoque_atual,
                $estoque_correto,
                'Ajuste manual de estoque via edição do medicamento'
            ]);

            // Atualizar os lotes
            if ($diferenca > 0) {
                // Se a diferença é positiva, adicionar ao primeiro lote com validade mais próxima
                $stmt = $pdo->prepare("
                    UPDATE lotes_medicamentos 
                    SET quantidade = quantidade + ? 
                    WHERE medicamento_id = ? 
                    AND validade = (
                        SELECT validade 
                        FROM (
                            SELECT validade 
                            FROM lotes_medicamentos 
                            WHERE medicamento_id = ? 
                            AND quantidade > 0 
                            ORDER BY validade ASC 
                            LIMIT 1
                        ) as temp
                    )
                ");
                $stmt->execute([$diferenca, $medicamento['id'], $medicamento['id']]);
            } else {
                // Se a diferença é negativa, remover dos lotes mais antigos primeiro
                $stmt = $pdo->prepare("
                    UPDATE lotes_medicamentos 
                    SET quantidade = GREATEST(0, quantidade + ?) 
                    WHERE medicamento_id = ? 
                    AND validade = (
                        SELECT validade 
                        FROM (
                            SELECT validade 
                            FROM lotes_medicamentos 
                            WHERE medicamento_id = ? 
                            AND quantidade > 0 
                            ORDER BY validade ASC 
                            LIMIT 1
                        ) as temp
                    )
                ");
                $stmt->execute([$diferenca, $medicamento['id'], $medicamento['id']]);
            }

            // Confirmar transação
            $pdo->commit();
            header('Location: editar_medicamento.php?id=' . $_GET['id'] . '&ajuste=ok');
            exit();
        } catch (PDOException $e) {
            // Em caso de erro, desfazer a transação
            $pdo->rollBack();
            $ajuste_msg = '<div class="erro">Erro ao ajustar estoque: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    } else {
        $ajuste_msg = '<div class="alert">O estoque informado já está correto. Nenhum ajuste necessário.</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Editar Medicamento</title>
    <link rel="stylesheet" href="/css/style.css">
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
        <?php if (isset($ajuste_msg)) echo $ajuste_msg; ?>

        <form method="POST">
            <input type="hidden" name="editar_medicamento" value="1">
            <div class="form-group">
                <label for="nome">Nome:</label>
                <textarea id="nome" name="nome" rows="2" required style="resize: vertical; min-height: 38px;"><?= htmlspecialchars($medicamento['nome'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label for="apresentacao">Apresentação:</label>
                <select id="apresentacao" name="apresentacao" required>
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

            <div class="form-group">
                <label for="codigo">Código:</label>
                <input type="text" id="codigo" name="codigo" value="<?= htmlspecialchars($medicamento['codigo'] ?? '') ?>" required>
            </div>

            <h3>Lotes do Medicamento</h3>
            <div class="lotes-container">
                <?php foreach ($lotes as $lote): ?>
                <div class="lote-item">
                    <div class="form-group">
                        <label>Lote:</label>
                        <input type="text" name="lote" value="<?= htmlspecialchars($lote['lote']) ?>" required>
                        <input type="hidden" name="lote_id" value="<?= $lote['id'] ?>">
                    </div>
                    <div class="form-group">
                        <label>Quantidade:</label>
                        <input type="number" name="quantidade" value="<?= $lote['quantidade'] ?>" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Validade:</label>
                        <input type="date" name="validade" value="<?= $lote['validade_formatada'] ?>">
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Salvar Alterações</button>
                <a href="medicamentos.php" class="btn-secondary"><i class="fas fa-times"></i> Cancelar</a>
            </div>
        </form>

        <hr>
        <h3>Ajuste de Estoque</h3>
        <?php $estoque_atual = calcularEstoqueAtual($pdo, $medicamento['id']); ?>
        <div class="form-group">
            <label>Estoque Atual: <span class="estoque-valor"><?= htmlspecialchars($estoque_atual) ?></span></label>
        </div>
        <form method="POST" style="margin-top: 1em;">
            <input type="hidden" name="ajustar_estoque" value="1">
            <div class="form-group">
                <label for="estoque_correto">Estoque correto (após contagem física):</label>
                <input type="number" id="estoque_correto" name="estoque_correto" min="0" required value="<?= $estoque_atual ?>">
            </div>
            <button type="submit" class="btn-primary"><i class="fas fa-balance-scale"></i> Ajustar Estoque</button>
        </form>
    </div>
</main>

<style>
.lotes-container {
    margin: 20px 0;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 5px;
    background-color: #f9f9f9;
}

.lote-item {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    padding: 15px;
    margin-bottom: 10px;
    border: 1px solid #eee;
    border-radius: 4px;
    background-color: white;
}

.lote-item:last-child {
    margin-bottom: 0;
}

.estoque-valor {
    color: #007bff;
    font-weight: bold;
    font-size: 1.2em;
}
</style>
</body>
</html>
