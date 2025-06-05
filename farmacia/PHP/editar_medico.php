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

// Recebe o ID do médico para edição
$medicoId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$medicoId) {
    die("ID de médico inválido.");
}

// Carregar dados do médico
$stmt = $pdo->prepare("SELECT * FROM medicos WHERE id = ?");
$stmt->execute([$medicoId]);
$medico = $stmt->fetch();
if (!$medico) {
    die("Médico não encontrado.");
}

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

    // Verificar se CRM já existe (exceto para o próprio registro)
    if (empty($erros)) {
        $stmt = $pdo->prepare("
            SELECT id 
            FROM medicos 
            WHERE crm_numero = ? 
            AND crm_estado = ? 
            AND id != ?
        ");
        $stmt->execute([
            $valores['crm_numero'],
            $valores['crm_estado'],
            $medicoId
        ]);
        if ($stmt->fetch()) {
            $erros['crm_numero'] = 'Este CRM já está cadastrado para outro médico.';
        }
    }

    // Atualizar no banco
    if (empty($erros)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE medicos 
                SET nome = ?, 
                    crm_numero = ?, 
                    crm_estado = ?,
                    cns = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $valores['nome'],
                $valores['crm_numero'],
                $valores['crm_estado'],
                $valores['cns'] ?: null,
                $medicoId
            ]);

            header('Location: medicos.php?sucesso=Médico atualizado com sucesso');
            exit();
        } catch (Exception $e) {
            $erros['geral'] = 'Erro ao atualizar médico: ' . $e->getMessage();
        }
    }
} else {
    // Preenche valores para o formulário com dados do banco
    $valores['nome'] = $medico['nome'];
    $valores['crm_numero'] = $medico['crm_numero'];
    $valores['crm_estado'] = $medico['crm_estado'];
    $valores['cns'] = $medico['cns'];
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
    <title>Editar Médico - FarmAlto</title>
    <link rel="icon" type="image/png" href="/images/fav.png">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <main class="container">
        <h2><i class="fas fa-user-md"></i> Editar Médico</h2>

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
                <label for="cns">CNS (Cartão Nacional de Saúde)</label>
                <input type="text" 
                       id="cns" 
                       name="cns" 
                       value="<?= htmlspecialchars($valores['cns']) ?>" 
                       maxlength="15"
                       placeholder="Digite apenas números">
                <?php if (isset($erros['cns'])): ?>
                    <span class="erro"><?= $erros['cns'] ?></span>
                <?php endif; ?>
            </div>

            <div class="acoes-form">
                <a href="medicos.php" class="btn-secondary">Cancelar</a>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Salvar Alterações
                </button>
            </div>
        </form>
    </main>

    <?php include 'footer.php'; ?>

    <script>
        // Formatação do número do CRM
        document.getElementById('crm_numero').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 6) value = value.slice(0, 6);
            e.target.value = value;
        });

        // Formatação do CNS
        document.getElementById('cns').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 15) value = value.slice(0, 15);
            e.target.value = value;
        });
    </script>

    <style>
        .form-padrao {
            max-width: 600px;
            margin: 0 auto;
        }
        .grupo-form {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 15px;
        }
        .campo-form {
            margin-bottom: 15px;
        }
        .campo-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .campo-form input,
        .campo-form select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .campo-form input:focus,
        .campo-form select:focus {
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
        @media (max-width: 600px) {
            .grupo-form {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html> 