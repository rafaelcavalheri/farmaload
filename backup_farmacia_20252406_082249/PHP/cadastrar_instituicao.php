<?php
require __DIR__ . '/config.php';
verificarAutenticacao(['admin']);

$erros = [];
$valores = [
    'nome' => '',
    'cnes' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validarTokenCsrf($_POST['csrf_token'])) {
        die("Token CSRF inválido!");
    }

    // Sanitização
    $valores = [
        'nome' => trim($_POST['nome'] ?? ''),
        'cnes' => preg_replace('/\D/', '', $_POST['cnes'] ?? '')
    ];

    // Validação
    if (empty($valores['nome'])) {
        $erros['nome'] = 'Nome é obrigatório.';
    }

    if (empty($valores['cnes']) || strlen($valores['cnes']) !== 7) {
        $erros['cnes'] = 'CNES deve conter exatamente 7 números.';
    }

    // Verificar se CNES já existe
    if (empty($erros)) {
        $stmt = $pdo->prepare("SELECT id FROM instituicoes WHERE cnes = ?");
        $stmt->execute([$valores['cnes']]);
        if ($stmt->fetch()) {
            $erros['cnes'] = 'Este CNES já está cadastrado.';
        }
    }

    // Salvar no banco
    if (empty($erros)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO instituicoes (nome, cnes)
                VALUES (?, ?)
            ");
            
            $stmt->execute([
                $valores['nome'],
                $valores['cnes']
            ]);

            header('Location: medicos.php?sucesso=Instituição cadastrada com sucesso');
            exit();
        } catch (Exception $e) {
            $erros['geral'] = 'Erro ao cadastrar instituição: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Instituição - FarmAlto</title>
    <link rel="icon" type="image/png" href="/images/fav.png">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <main class="container">
        <h2><i class="fas fa-hospital"></i> Cadastrar Instituição de Saúde</h2>

        <?php if (isset($erros['geral'])): ?>
            <div class="alert erro"><?= htmlspecialchars($erros['geral']) ?></div>
        <?php endif; ?>

        <form method="POST" class="form-padrao">
            <input type="hidden" name="csrf_token" value="<?= gerarTokenCsrf() ?>">

            <div class="campo-form">
                <label for="nome">Nome da Instituição *</label>
                <input type="text" 
                       id="nome" 
                       name="nome" 
                       value="<?= htmlspecialchars($valores['nome']) ?>" 
                       required>
                <?php if (isset($erros['nome'])): ?>
                    <span class="erro"><?= $erros['nome'] ?></span>
                <?php endif; ?>
            </div>

            <div class="campo-form">
                <label for="cnes">CNES (Código Nacional de Estabelecimento de Saúde) *</label>
                <input type="text" 
                       id="cnes" 
                       name="cnes" 
                       value="<?= htmlspecialchars($valores['cnes']) ?>" 
                       maxlength="7" 
                       required>
                <?php if (isset($erros['cnes'])): ?>
                    <span class="erro"><?= $erros['cnes'] ?></span>
                <?php endif; ?>
            </div>

            <div class="acoes-form">
                <a href="medicos.php" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Salvar
                </button>
            </div>
        </form>
    </main>

    <?php include 'footer.php'; ?>

    <script>
        // Formatação do CNES
        document.getElementById('cnes').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 7) value = value.slice(0, 7);
            e.target.value = value;
        });
    </script>

    <style>
        .form-padrao {
            max-width: 600px;
            margin: 0 auto;
        }
        .campo-form {
            margin-bottom: 15px;
        }
        .campo-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .campo-form input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .campo-form input:focus {
            border-color: #4a90e2;
            outline: none;
            box-shadow: 0 0 3px rgba(74, 144, 226, 0.3);
        }
        .erro {
            color: #dc3545;
            font-size: 0.9em;
            margin-top: 5px;
            display: block;
        }
        .acoes-form {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
    </style>
</body>
</html> 