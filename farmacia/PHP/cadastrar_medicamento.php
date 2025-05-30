<?php
include 'config.php';

// Verificação de sessão corrigida
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['perfil'] !== 'admin') {
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
    'apresentacao' => '',
    'codigo' => '',
    'miligramas' => '',
    'validade' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Coleta e sanitização dos dados
    $valores = [
        'nome' => trim($_POST['nome'] ?? ''),
        'quantidade' => trim($_POST['quantidade'] ?? ''),
        'lote' => trim($_POST['lote'] ?? ''),
        'apresentacao' => trim($_POST['apresentacao'] ?? ''),
        'codigo' => trim($_POST['codigo'] ?? ''),
        'miligramas' => trim($_POST['miligramas'] ?? ''),
        'validade' => trim($_POST['validade'] ?? '')
    ];

    // Validações
    $camposObrigatorios = ['nome', 'quantidade', 'lote', 'apresentacao', 'codigo'];
    foreach ($camposObrigatorios as $campo) {
        if (empty($valores[$campo])) {
            $erros[$campo] = "Campo obrigatório";
        }
    }

    if (!is_numeric($valores['quantidade']) || $valores['quantidade'] < 0) {
        $erros['quantidade'] = "Valor inválido";
    }

    if (empty($erros)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO medicamentos 
                                  (nome, quantidade, lote, apresentacao, codigo, miligramas, validade)
                                  VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $valores['nome'],
                $valores['quantidade'],
                $valores['lote'],
                $valores['apresentacao'],
                $valores['codigo'],
                $valores['miligramas'],
                !empty($valores['validade']) ? date('Y-m-d', strtotime($valores['validade'])) : null
            ]);
            
            header('Location: medicamentos.php?sucesso=Medicamento cadastrado com sucesso');
            exit();
        } catch (PDOException $e) {
            $erro = "Erro no cadastro: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Cadastrar Novo Medicamento</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="container">
        <h2>Cadastrar Novo Medicamento</h2>

        <?php if (!empty($erro)): ?>
            <div class="erro"><?= htmlspecialchars($erro) ?></div>
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
                <input type="number" name="quantidade" min="0" 
                       value="<?= htmlspecialchars($valores['quantidade']) ?>" required>
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
                <input type="text" name="codigo" 
                       value="<?= htmlspecialchars($valores['codigo']) ?>" required>
            </div>

            <div class="form-group">
                <label>Miligramas/Concentração</label>
                <input type="text" name="miligramas" 
                       value="<?= htmlspecialchars($valores['miligramas']) ?>">
            </div>

            <div class="form-group">
                <label>Data de Validade</label>
                <input type="date" name="validade" 
                       min="<?= date('Y-m-d') ?>" 
                       value="<?= htmlspecialchars($valores['validade']) ?>">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-secondary">Salvar Medicamento</button>
                <a href="medicamentos.php" class="btn-secondary">Cancelar</a>
            </div>
        </form>
    </main>
</body>
</html>