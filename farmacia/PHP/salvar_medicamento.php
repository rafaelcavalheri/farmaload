<?php
include 'config.php';

if (!isset($_SESSION['usuario']) {
    header('Location: login.php');
    exit();
}

if ($_SESSION['usuario']['perfil'] !== 'admin') {
    die("Acesso negado! Apenas administradores podem acessar esta página.");
}

$erros = [];
$valores = [
    'nome' => '',
    'quantidade' => '',
    'lote' => '',
    'cid' => '',
    'apresentacao' => '',
    'codigo' => '',
    'miligramas' => '',
    'validade' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitização e validação dos dados
    $campos = ['nome', 'quantidade', 'lote', 'cid', 'apresentacao', 'codigo'];
    
    foreach ($campos as $campo) {
        $valores[$campo] = trim($_POST[$campo] ?? '');
        if (empty($valores[$campo])) {
            $erros[$campo] = "Campo obrigatório";
        }
    }

    // Validações específicas
    if (!is_numeric($valores['quantidade']) || $valores['quantidade'] < 0) {
        $erros['quantidade'] = "Quantidade inválida";
    }

    if (!preg_match('/^[A-Z0-9]{4,10}$/', $valores['cid'])) {
        $erros['cid'] = "CID inválido (Ex: A000)";
    }

    if (empty($erros)) {
        try {
            // Buscar por nome+lote
            $stmt = $pdo->prepare("SELECT id, quantidade, validade FROM medicamentos WHERE LOWER(TRIM(nome)) = LOWER(TRIM(?)) AND lote = ?");
            $stmt->execute([trim($valores['nome']), $valores['lote']]);
            $medicamentoExistente = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($medicamentoExistente) {
                // Somar quantidade
                $novaQuantidade = $medicamentoExistente['quantidade'] + $valores['quantidade'];
                // Atualizar validade se a nova for maior
                $dataExistente = DateTime::createFromFormat('Y-m-d', $medicamentoExistente['validade']);
                $dataNovo = !empty($valores['validade']) ? DateTime::createFromFormat('Y-m-d', date('Y-m-d', strtotime($valores['validade']))) : false;
                if ($dataExistente && $dataNovo && $dataNovo > $dataExistente) {
                    $validade = $dataNovo->format('Y-m-d');
                } else {
                    $validade = $dataExistente ? $dataExistente->format('Y-m-d') : (!empty($valores['validade']) ? date('Y-m-d', strtotime($valores['validade'])) : null);
                }
                $stmt = $pdo->prepare("UPDATE medicamentos SET quantidade = ?, validade = ? WHERE id = ?");
                $stmt->execute([$novaQuantidade, $validade, $medicamentoExistente['id']]);
                header('Location: medicamentos.php?sucesso=Medicamento atualizado com sucesso');
                exit();
            } else {
                $stmt = $pdo->prepare("INSERT INTO medicamentos (nome, quantidade, lote, cid, apresentacao, codigo, miligramas, validade) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $valores['nome'],
                    $valores['quantidade'],
                    $valores['lote'],
                    $valores['cid'],
                    $valores['apresentacao'],
                    $valores['codigo'],
                    $_POST['miligramas'] ?? null,
                    !empty($_POST['validade']) ? date('Y-m-d', strtotime($_POST['validade'])) : null
                ]);
                header('Location: medicamentos.php?sucesso=Medicamento cadastrado com sucesso');
                exit();
            }
        } catch (PDOException $e) {
            $erro_banco = "Erro ao cadastrar: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Cadastrar Medicamento</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="container">
        <h2>Cadastrar Novo Medicamento</h2>
        
        <?php if (!empty($erro_banco)): ?>
            <div class="erro"><?= $erro_banco ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Nome do Medicamento*</label>
                <input type="text" name="nome" value="<?= htmlspecialchars($valores['nome']) ?>" required>
                <?php if (isset($erros['nome'])): ?>
                    <span class="erro-campo"><?= $erros['nome'] ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label>Quantidade em Estoque*</label>
                <input type="number" name="quantidade" min="0" value="<?= htmlspecialchars($valores['quantidade']) ?>" required>
                <?php if (isset($erros['quantidade'])): ?>
                    <span class="erro-campo"><?= $erros['quantidade'] ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label>Número do Lote*</label>
                <input type="text" name="lote" value="<?= htmlspecialchars($valores['lote']) ?>" required>
                <?php if (isset($erros['lote'])): ?>
                    <span class="erro-campo"><?= $erros['lote'] ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label>Código CID*</label>
                <input type="text" name="cid" placeholder="Ex: A000" 
                       pattern="[A-Z][0-9]{3}" 
                       title="Formato: Letra seguida de 3 dígitos (Ex: A000)"
                       value="<?= htmlspecialchars($valores['cid']) ?>" required>
                <?php if (isset($erros['cid'])): ?>
                    <span class="erro-campo"><?= $erros['cid'] ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label>Apresentação*</label>
                <select name="apresentacao" required>
                    <option value="">Selecione...</option>
                    <?php
                    $opcoes = [
                        'Liquido', 'Comprimido', 'Injetável', 
                        'Solução oral', 'Pomada', 'Creme', 
                        'Adesivo', 'Frasco'
                    ];
                    foreach ($opcoes as $opcao) {
                        $selected = ($valores['apresentacao'] === $opcao) ? 'selected' : '';
                        echo "<option value='$opcao' $selected>$opcao</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label>Código Interno*</label>
                <input type="text" name="codigo" value="<?= htmlspecialchars($valores['codigo']) ?>" required>
            </div>

            <div class="form-group">
                <label>Miligramas/Concentração</label>
                <input type="text" name="miligramas" value="<?= htmlspecialchars($_POST['miligramas'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Data de Validade</label>
                <input type="date" name="validade" 
                       min="<?= date('Y-m-d') ?>" 
                       value="<?= htmlspecialchars($_POST['validade'] ?? '') ?>">
            </div>

            <div class="form-buttons">
                <button type="submit" class="btn-save">Salvar</button>
                <a href="medicamentos.php" class="btn-cancel">Cancelar</a>
            </div>
        </form>
    </main>
</body>
</html>