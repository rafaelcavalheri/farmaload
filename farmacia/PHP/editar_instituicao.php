<?php
require __DIR__ . '/config.php';
verificarAutenticacao(['admin']);

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: instituicoes.php');
    exit;
}

$mensagem = '';
$erros = [];
$valores = [];

// Buscar dados da instituição
try {
    $stmt = $pdo->prepare("SELECT * FROM instituicoes WHERE id = ?");
    $stmt->execute([$id]);
    $instituicao = $stmt->fetch();
    
    if (!$instituicao) {
        header('Location: instituicoes.php');
        exit;
    }
    
    $valores = $instituicao;
} catch (Exception $e) {
    $mensagem = '<div class="alert erro">Erro ao carregar dados da instituição.</div>';
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validarTokenCsrf($_POST['csrf_token'])) {
        die("Token CSRF inválido!");
    }

    // Sanitizar e validar dados
    $valores = [
        'nome' => filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_STRING),
        'cnes' => filter_input(INPUT_POST, 'cnes', FILTER_SANITIZE_STRING),
        'endereco' => filter_input(INPUT_POST, 'endereco', FILTER_SANITIZE_STRING),
        'telefone' => filter_input(INPUT_POST, 'telefone', FILTER_SANITIZE_STRING),
        'email' => filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL)
    ];

    // Validar campos obrigatórios
    if (empty($valores['nome'])) {
        $erros[] = "O nome da instituição é obrigatório.";
    }
    if (empty($valores['cnes'])) {
        $erros[] = "O CNES é obrigatório.";
    } elseif (!preg_match('/^\d{7}$/', $valores['cnes'])) {
        $erros[] = "O CNES deve conter exatamente 7 dígitos.";
    }

    // Verificar se CNES já existe (exceto para a própria instituição)
    if (empty($erros)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM instituicoes WHERE cnes = ? AND id != ?");
            $stmt->execute([$valores['cnes'], $id]);
            if ($stmt->fetch()) {
                $erros[] = "Este CNES já está cadastrado para outra instituição.";
            }
        } catch (Exception $e) {
            $erros[] = "Erro ao verificar CNES.";
        }
    }

    // Atualizar dados se não houver erros
    if (empty($erros)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE instituicoes 
                SET nome = ?, 
                    cnes = ?, 
                    endereco = ?, 
                    telefone = ?, 
                    email = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $valores['nome'],
                $valores['cnes'],
                $valores['endereco'],
                $valores['telefone'],
                $valores['email'],
                $id
            ]);

            $mensagem = '<div class="alert sucesso">Instituição atualizada com sucesso!</div>';
        } catch (Exception $e) {
            $erros[] = "Erro ao atualizar instituição.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Instituição - FarmAlto</title>
    <link rel="icon" type="image/png" href="/images/fav.png">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <main class="container">
        <div class="top-bar">
            <h2><i class="fas fa-hospital"></i> Editar Instituição</h2>
            <a href="instituicoes.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>

        <?php if (!empty($erros)): ?>
            <div class="alert erro">
                <ul>
                    <?php foreach ($erros as $erro): ?>
                        <li><?= htmlspecialchars($erro) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?= $mensagem ?>

        <form method="POST" class="form-cadastro">
            <input type="hidden" name="csrf_token" value="<?= gerarTokenCsrf() ?>">
            
            <div class="form-group">
                <label for="nome">Nome da Instituição *</label>
                <input type="text" 
                       id="nome" 
                       name="nome" 
                       value="<?= htmlspecialchars($valores['nome'] ?? '') ?>" 
                       required>
            </div>

            <div class="form-group">
                <label for="cnes">CNES (7 dígitos) *</label>
                <input type="text" 
                       id="cnes" 
                       name="cnes" 
                       value="<?= htmlspecialchars($valores['cnes'] ?? '') ?>" 
                       pattern="\d{7}" 
                       maxlength="7" 
                       required>
            </div>

            <div class="form-group">
                <label for="endereco">Endereço</label>
                <input type="text" 
                       id="endereco" 
                       name="endereco" 
                       value="<?= htmlspecialchars($valores['endereco'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="telefone">Telefone</label>
                <input type="tel" 
                       id="telefone" 
                       name="telefone" 
                       value="<?= htmlspecialchars($valores['telefone'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="email">E-mail</label>
                <input type="email" 
                       id="email" 
                       name="email" 
                       value="<?= htmlspecialchars($valores['email'] ?? '') ?>">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Salvar Alterações
                </button>
                <a href="instituicoes.php" class="btn-secondary">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </form>
    </main>

    <?php include 'footer.php'; ?>

    <script>
        // Formatar CNES (apenas números)
        document.getElementById('cnes').addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '').slice(0, 7);
        });

        // Formatar telefone
        document.getElementById('telefone').addEventListener('input', function(e) {
            let value = this.value.replace(/\D/g, '');
            if (value.length > 11) value = value.slice(0, 11);
            
            if (value.length > 2) {
                value = `(${value.slice(0, 2)}) ${value.slice(2)}`;
            }
            if (value.length > 9) {
                value = `${value.slice(0, 9)}-${value.slice(9)}`;
            }
            
            this.value = value;
        });
    </script>

    <style>
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .form-cadastro {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1em;
        }
        .form-group input:focus {
            border-color: #4a90e2;
            outline: none;
            box-shadow: 0 0 0 2px rgba(74,144,226,0.2);
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
        .alert.sucesso {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert ul {
            margin: 0;
            padding-left: 20px;
        }
    </style>
</body>
</html> 