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

// Validar ID do médico
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: medicos.php?erro=ID do médico inválido');
    exit();
}

// Buscar dados do médico
try {
    $stmt = $pdo->prepare("SELECT * FROM medicos WHERE id = ?");
    $stmt->execute([$id]);
    $medico = $stmt->fetch();

    if (!$medico) {
        header('Location: medicos.php?erro=Médico não encontrado');
        exit();
    }

    $valores = [
        'nome' => $medico['nome'],
        'crm_numero' => $medico['crm_numero'],
        'crm_estado' => $medico['crm_estado'],
        'cns' => $medico['cns']
    ];
} catch (Exception $e) {
    header('Location: medicos.php?erro=Erro ao carregar dados do médico');
    exit();
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

    // Verificar se CRM já existe (exceto para o próprio médico)
    if (empty($erros)) {
        $stmt = $pdo->prepare("SELECT id FROM medicos WHERE crm_numero = ? AND crm_estado = ? AND id != ?");
        $stmt->execute([$valores['crm_numero'], $valores['crm_estado'], $id]);
        if ($stmt->fetch()) {
            $erros['crm_numero'] = 'Este CRM já está cadastrado para outro médico.';
        }
    }

    // Salvar no banco
    if (empty($erros)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE medicos 
                SET nome = ?, crm_numero = ?, crm_estado = ?, cns = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $valores['nome'],
                $valores['crm_numero'],
                $valores['crm_estado'],
                $valores['cns'] ?: null,
                $id
            ]);

            header('Location: medicos.php?sucesso=Médico atualizado com sucesso');
            exit();
        } catch (Exception $e) {
            $erros['geral'] = 'Erro ao atualizar médico: ' . $e->getMessage();
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
    <title>Editar Médico - FarmAlto</title>
    <link rel="icon" type="image/png" href="/images/fav.png">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/editar_medico.css">
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
                    <i class="fas fa-save"></i> Salvar Alterações
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
</body>
</html> 