<?php
require __DIR__ . '/config.php';
verificarAutenticacao(['admin']);

$mensagem = '';
$busca = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validarTokenCsrf($_POST['csrf_token'])) {
        die("Token CSRF inválido!");
    }

    // Alternar status (ativar/desativar)
    if (isset($_POST['alternar_status'])) {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id) {
            try {
                $stmt = $pdo->prepare("UPDATE medicos SET ativo = NOT ativo WHERE id = ?");
                $stmt->execute([$id]);
                $mensagem = '<div class="alert sucesso">Status do médico alterado com sucesso!</div>';
            } catch (Exception $e) {
                $mensagem = '<div class="alert erro">Erro ao alterar status do médico.</div>';
            }
        }
    }
}

// Busca
$where = "1=1";
$params = [];
if (isset($_GET['busca'])) {
    $busca = trim($_GET['busca']);
    if (strlen($busca) >= 3) {
        $where .= " AND (
            nome LIKE ? OR 
            crm_numero LIKE ? OR 
            crm_completo LIKE ? OR
            cns LIKE ?
        )";
        $params = [
            "%$busca%",
            "%$busca%",
            "%$busca%",
            "%$busca%"
        ];
    } elseif (!empty($busca)) {
        $mensagem = '<div class="alert erro">Digite pelo menos 3 caracteres para buscar.</div>';
    }
}

// Consulta paginada
$por_pagina = 10;
$pagina = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT) ?: 1;
$offset = ($pagina - 1) * $por_pagina;

// Total de registros
$stmt = $pdo->prepare("SELECT COUNT(*) FROM medicos WHERE $where");
$stmt->execute($params);
$total_registros = $stmt->fetchColumn();
$total_paginas = ceil($total_registros / $por_pagina);

// Buscar médicos
$stmt = $pdo->prepare("
    SELECT * 
    FROM medicos 
    WHERE $where 
    ORDER BY nome 
    LIMIT ? OFFSET ?
");
$params[] = $por_pagina;
$params[] = $offset;
$stmt->execute($params);
$medicos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Médicos - FarmAlto</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <main class="container">
        <div class="top-bar">
            <h2><i class="fas fa-user-md"></i> Gerenciar Médicos</h2>
            <a href="cadastrar_medico.php" class="btn-primary">
                <i class="fas fa-plus"></i> Novo Médico
            </a>
        </div>

        <?= $mensagem ?>

        <!-- Formulário de busca -->
        <form method="GET" class="form-busca">
            <div class="campo-busca">
                <input type="text" 
                       name="busca" 
                       value="<?= htmlspecialchars($busca) ?>" 
                       placeholder="Buscar por nome, CRM ou CNS..."
                       minlength="3">
                <button type="submit">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </form>

        <!-- Tabela de médicos -->
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>CRM</th>
                        <th>CNS</th>
                        <th>Status</th>
                        <th>Cadastro</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($medicos as $medico): ?>
                        <tr class="<?= $medico['ativo'] ? '' : 'inativo' ?>">
                            <td><?= htmlspecialchars($medico['nome']) ?></td>
                            <td><?= htmlspecialchars($medico['crm_completo']) ?></td>
                            <td><?= !empty($medico['cns']) ? htmlspecialchars($medico['cns']) : '<span class="nao-informado">Não informado</span>' ?></td>
                            <td>
                                <span class="status-badge <?= $medico['ativo'] ? 'ativo' : 'inativo' ?>">
                                    <?= $medico['ativo'] ? 'Ativo' : 'Inativo' ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y', strtotime($medico['data_cadastro'])) ?></td>
                            <td class="acoes">
                                <div class="action-buttons">
                                    <a href="editar_medico.php?id=<?= $medico['id'] ?>" 
                                       class="btn-secondary" 
                                       title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?= gerarTokenCsrf() ?>">
                                        <input type="hidden" name="id" value="<?= $medico['id'] ?>">
                                        <button type="submit" 
                                                name="alternar_status" 
                                                class="btn-secondary" 
                                                title="<?= $medico['ativo'] ? 'Desativar' : 'Ativar' ?>"
                                                onclick="return confirm('Tem certeza que deseja <?= $medico['ativo'] ? 'desativar' : 'ativar' ?> este médico?')">
                                            <i class="fas fa-<?= $medico['ativo'] ? 'times' : 'check' ?>"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($medicos)): ?>
                        <tr>
                            <td colspan="5" class="text-center">Nenhum médico encontrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginação -->
        <?php if ($total_paginas > 1): ?>
            <div class="paginacao">
                <?php if ($pagina > 1): ?>
                    <a href="?pagina=1<?= $busca ? '&busca=' . urlencode($busca) : '' ?>" class="btn-pagina">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    <a href="?pagina=<?= $pagina - 1 ?><?= $busca ? '&busca=' . urlencode($busca) : '' ?>" class="btn-pagina">
                        <i class="fas fa-angle-left"></i>
                    </a>
                <?php endif; ?>

                <?php
                $inicio = max(1, $pagina - 2);
                $fim = min($total_paginas, $pagina + 2);
                
                for ($i = $inicio; $i <= $fim; $i++):
                ?>
                    <a href="?pagina=<?= $i ?><?= $busca ? '&busca=' . urlencode($busca) : '' ?>" 
                       class="btn-pagina <?= $i === $pagina ? 'ativo' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($pagina < $total_paginas): ?>
                    <a href="?pagina=<?= $pagina + 1 ?><?= $busca ? '&busca=' . urlencode($busca) : '' ?>" class="btn-pagina">
                        <i class="fas fa-angle-right"></i>
                    </a>
                    <a href="?pagina=<?= $total_paginas ?><?= $busca ? '&busca=' . urlencode($busca) : '' ?>" class="btn-pagina">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <?php include 'footer.php'; ?>

    <style>
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        .status-badge.ativo {
            background-color: #28a745;
            color: white;
        }
        .status-badge.inativo {
            background-color: #dc3545;
            color: white;
        }
        tr.inativo {
            background-color: #f8f9fa;
            color: #6c757d;
        }
        .btn-primary {
            background-color: var(--primary-color, #007bff);
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 6px 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 1em;
            transition: background 0.2s;
        }
        .btn-primary:hover {
            background-color: var(--primary-dark, #0056b3);
            color: #fff;
        }
        .btn-secondary {
            background-color: var(--secondary-color, #6c757d);
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 4px 8px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.95em;
            transition: background 0.2s;
        }
        .btn-secondary:hover {
            background-color: var(--primary-color, #007bff);
            color: #fff;
        }
        .action-buttons {
            display: flex;
            gap: 4px;
            justify-content: flex-start;
            flex-wrap: nowrap;
            width: 100%;
        }
        .form-busca {
            margin-bottom: 20px;
        }
        .campo-busca {
            display: flex;
            gap: 10px;
            max-width: 500px;
        }
        .campo-busca input {
            flex: 1;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .campo-busca button {
            padding: 8px 15px;
            background-color: #4a90e2;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .campo-busca button:hover {
            background-color: #357abd;
        }

        /* Estilos para o CNS */
        td:nth-child(3) {
            font-family: monospace;
            letter-spacing: 1px;
        }
        .nao-informado {
            color: #6c757d;
            font-style: italic;
        }
        @media (max-width: 768px) {
            .table-responsive {
                overflow-x: auto;
            }
            table {
                min-width: 800px;
            }
        }
    </style>
</body>
</html> 