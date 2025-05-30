<?php
include 'config.php';
include 'funcoes_estoque.php';

if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['perfil'] !== 'admin') {
    header('Location: index.php');
    exit();
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
                             nome = ?, lote = ?, apresentacao = ?, 
                             codigo = ?, validade = ? 
                             WHERE id = ?");
        $stmt->execute([
            $_POST['nome'],
            $_POST['lote'],
            $_POST['apresentacao'],
            $_POST['codigo'],
            $_POST['validade'],
            $_GET['id']
        ]);
        header('Location: medicamentos.php?sucesso=Medicamento atualizado com sucesso');
    } catch (PDOException $e) {
        header('Location: editar_medicamento.php?id=' . $_GET['id'] . '&erro=' . urlencode($e->getMessage()));
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajustar_estoque'])) {
    $estoque_correto = (int)$_POST['estoque_correto'];
    $estoque_atual = calcularEstoqueAtual($pdo, $medicamento['id']);
    $diferenca = $estoque_correto - $estoque_atual;
    if ($diferenca !== 0) {
        try {
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
            header('Location: editar_medicamento.php?id=' . $_GET['id'] . '&ajuste=ok');
            exit();
        } catch (PDOException $e) {
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
    <link rel="stylesheet" href="style.css">
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
                <label>Estoque Atual:</label>
                <input type="text" value="<?= calcularEstoqueAtual($pdo, $medicamento['id']) ?>" readonly>
            </div>

            <div class="form-group">
                <label for="lote">Lote:</label>
                <input type="text" id="lote" name="lote" value="<?= htmlspecialchars($medicamento['lote'] ?? '') ?>" required>
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

            <div class="form-group">
                <label for="validade">Validade:</label>
                <input type="date" id="validade" name="validade" value="<?= $medicamento['validade'] ?>">
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
            <label><strong>Estoque Atual:</strong> <span style="color: #007bff; font-weight: bold; font-size: 1.2em;"><?= $estoque_atual ?></span></label>
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
</body>
</html>
