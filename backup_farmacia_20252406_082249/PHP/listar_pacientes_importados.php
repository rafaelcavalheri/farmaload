<?php
require __DIR__ . '/config.php';
verificarAutenticacao(['admin', 'operador']);

// Buscar os últimos pacientes importados
// (identificados pela observação específica)
$stmt = $pdo->prepare("
    SELECT id, nome, cpf, nascimento, telefone, observacao, data_cadastro
    FROM pacientes 
    WHERE observacao LIKE '%importado automaticamente%'
    ORDER BY id DESC
");
$stmt->execute();
$pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pacientes Importados</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    
    <main class="container">
        <h2><i class="fas fa-users"></i> Pacientes Importados Automaticamente</h2>
        
        <?php if (empty($pacientes)): ?>
            <div class="alert alerta">
                <i class="fas fa-info-circle"></i> Nenhum paciente importado automaticamente foi encontrado.
            </div>
        <?php else: ?>
            <div class="alert info">
                <i class="fas fa-info-circle"></i> Abaixo estão os pacientes importados automaticamente. 
                É importante atualizar esses registros com dados completos e corretos.
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>CPF (temporário)</th>
                        <th>Data de Cadastro</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pacientes as $paciente): ?>
                        <tr>
                            <td><?= $paciente['id'] ?></td>
                            <td><?= htmlspecialchars($paciente['nome']) ?></td>
                            <td><strong><?= htmlspecialchars($paciente['cpf']) ?></strong></td>
                            <td><?= date('d/m/Y H:i', strtotime($paciente['data_cadastro'])) ?></td>
                            <td>
                                <a href="editar_paciente.php?id=<?= $paciente['id'] ?>" class="btn-small">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <div class="buttons">
            <a href="pacientes.php" class="btn-secondary">Voltar para Lista de Pacientes</a>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>
</html>
