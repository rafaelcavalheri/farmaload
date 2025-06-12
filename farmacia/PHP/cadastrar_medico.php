<?php
require __DIR__ . '/config.php';
verificarAutenticacao(['admin']);

$erros = [];
$valores = [
    'nome' => '',
    'crm_numero' => '',
    'crm_estado' => '',
    'cns' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validarTokenCsrf($_POST['csrf_token'])) {
        die("Token CSRF inválido!");
    }

    // Sanitização
    $valores = [
        'nome' => trim($_POST['nome'] ?? ''),
        'crm_numero' => preg_replace('/\D/', '', $_POST['crm_numero'] ?? ''),
        'crm_estado' => strtoupper(trim($_POST['crm_estado'] ?? '')),
        'cns' => preg_replace('/\D/', '', $_POST['cns'] ?? '')
    ];

    // Validação
    if (empty($valores['nome'])) {
        $erros['nome'] = 'Nome é obrigatório.';
    }

    if (empty($valores['crm_numero']) || strlen($valores['crm_numero']) !== 6) {
        $erros['crm_numero'] = 'CRM deve conter exatamente 6 números.';
    }

    if (empty($valores['crm_estado']) || strlen($valores['crm_estado']) !== 2) {
        $erros['crm_estado'] = 'Estado inválido.';
    }

    // Validação do CNS
    if (!empty($valores['cns']) && strlen($valores['cns']) !== 15) {
        $erros['cns'] = 'CNS deve conter exatamente 15 números.';
    }

    // Verificar se CRM já existe
    if (empty($erros)) {
        $stmt = $pdo->prepare("SELECT id FROM medicos WHERE crm_numero = ? AND crm_estado = ?");
        $stmt->execute([$valores['crm_numero'], $valores['crm_estado']]);
        if ($stmt->fetch()) {
            $erros['crm_numero'] = 'Este CRM já está cadastrado.';
        }
    }

    // Salvar no banco
    if (empty($erros)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO medicos (nome, crm_numero, crm_estado, cns)
                VALUES (?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $valores['nome'],
                $valores['crm_numero'],
                $valores['crm_estado'],
                $valores['cns'] ?: null
            ]);

            header('Location: medicos.php?sucesso=Médico cadastrado com sucesso');
            exit();
        } catch (Exception $e) {
            $erros['geral'] = 'Erro ao cadastrar médico: ' . $e->getMessage();
        }
    }
}

$estados = [
    'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 
    'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 
    'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Médico - FarmAlto</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <main class="container">
        <h2><i class="fas fa-user-md"></i> Cadastrar Médico</h2>

        <?php if (isset($erros['geral'])): ?>
            <div class="alert erro"><?= htmlspecialchars($erros['geral']) ?></div>
        <?php endif; ?>

        <form method="POST" class="form-padrao">
            <input type="hidden" name="csrf_token" value="<?= gerarTokenCsrf() ?>">

            <div class="campo-form">
                <label for="nome">Nome Completo *</label>
                <input type="text" 
                       id="nome" 
                       name="nome" 
                       value="<?= htmlspecialchars($valores['nome']) ?>" 
                       required>
                <?php if (isset($erros['nome'])): ?>
                    <span class="erro"><?= $erros['nome'] ?></span>
                <?php endif; ?>
            </div>

            <div class="grupo-form">
                <div class="campo-form">
                    <label for="crm_numero">Número do CRM *</label>
                    <input type="text" 
                           id="crm_numero" 
                           name="crm_numero" 
                           value="<?= htmlspecialchars($valores['crm_numero']) ?>" 
                           maxlength="6" 
                           required>
                    <?php if (isset($erros['crm_numero'])): ?>
                        <span class="erro"><?= $erros['crm_numero'] ?></span>
                    <?php endif; ?>
                </div>

                <div class="campo-form">
                    <label for="crm_estado">Estado *</label>
                    <select id="crm_estado" name="crm_estado" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($estados as $estado): ?>
                            <option value="<?= $estado ?>" 
                                    <?= $valores['crm_estado'] === $estado ? 'selected' : '' ?>>
                                <?= $estado ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($erros['crm_estado'])): ?>
                        <span class="erro"><?= $erros['crm_estado'] ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="campo-form">
                <label for="cns">CNS (opcional)</label>
                <input type="text" 
                       id="cns" 
                       name="cns" 
                       value="<?= htmlspecialchars($valores['cns']) ?>" 
                       maxlength="15">
                <?php if (isset($erros['cns'])): ?>
                    <span class="erro"><?= $erros['cns'] ?></span>
                <?php endif; ?>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Cadastrar Médico
                </button>
                <a href="medicos.php" class="btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </form>
    </main>

    <?php include 'footer.php'; ?>

    <script>
        // Formatar CRM (apenas números)
        document.getElementById('crm_numero').addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '').slice(0, 6);
        });

        // Formatar CNS (apenas números)
        document.getElementById('cns').addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '').slice(0, 15);
        });
    </script>

    <style>
        .form-padrao {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .campo-form {
            margin-bottom: 15px;
        }
        .campo-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .campo-form input,
        .campo-form select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1em;
        }
        .campo-form input:focus,
        .campo-form select:focus {
            border-color: #4a90e2;
            outline: none;
            box-shadow: 0 0 0 2px rgba(74,144,226,0.2);
        }
        .grupo-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .btn-primary, .btn-secondary {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 1em;
            text-decoration: none;
        }
        .btn-primary {
            background-color: #4a90e2;
            color: white;
        }
        .btn-primary:hover {
            background-color: #357abd;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        .erro {
            color: #dc3545;
            font-size: 0.9em;
            margin-top: 5px;
            display: block;
        }
        .alert {
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .alert.erro {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</body>
</html> 