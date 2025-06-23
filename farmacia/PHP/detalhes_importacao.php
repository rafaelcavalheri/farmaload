<?php
require_once 'config.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}

// Verificar se o log_id foi fornecido
if (!isset($_GET['log_id']) || !is_numeric($_GET['log_id'])) {
    die("ID do log inválido");
}

$log_id = intval($_GET['log_id']);

try {
    // Buscar informações da importação
    $stmt = $pdo->prepare("
        SELECT li.*, u.nome as usuario_nome
        FROM logs_importacao li
        LEFT JOIN usuarios u ON li.usuario_id = u.id
        WHERE li.id = ?
    ");
    $stmt->execute([$log_id]);
    $importacao = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$importacao) {
        die("Importação não encontrada");
    }
    
    // Buscar detalhes dos medicamentos importados
    $stmt = $pdo->prepare("
        SELECT nome, quantidade, lote, validade, observacoes
        FROM logs_importacao_detalhes
        WHERE log_importacao_id = ? AND tipo = 'medicamento'
        ORDER BY nome
    ");
    $stmt->execute([$log_id]);
    $medicamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar detalhes dos pacientes importados
    $stmt = $pdo->prepare("
        SELECT nome, cpf, observacoes
        FROM logs_importacao_detalhes
        WHERE log_importacao_id = ? AND tipo = 'paciente'
        ORDER BY nome
    ");
    $stmt->execute([$log_id]);
    $pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Erro ao buscar detalhes: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes da Importação - <?= htmlspecialchars($importacao['arquivo_nome']) ?></title>
    <link rel="icon" type="image/png" href="/images/fav.png">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .detalhes-header {
            background: var(--primary-color);
            color: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .detalhes-header h1 {
            margin: 0 0 1rem 0;
            font-size: 1.8rem;
        }
        
        .detalhes-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .detalhes-info-item {
            background: rgba(255, 255, 255, 0.1);
            padding: 0.8rem;
            border-radius: 4px;
        }
        
        .detalhes-info-item strong {
            display: block;
            margin-bottom: 0.3rem;
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .detalhes-section {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
        }
        
        .detalhes-section h2 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .detalhes-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .detalhes-table th,
        .detalhes-table td {
            padding: 0.8rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .detalhes-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .detalhes-table tr:hover {
            background: #f8f9fa;
        }
        
        .tipo-medicamento {
            background-color: #e8f5e8;
        }
        
        .tipo-paciente {
            background-color: #f3e5f5;
        }
        
        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-sucesso {
            background: #d4edda;
            color: #155724;
        }
        
        .status-erro {
            background: #f8d7da;
            color: #721c24;
        }
        
        .no-results {
            text-align: center;
            padding: 2rem;
            color: #666;
            font-style: italic;
        }
        
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.8rem 1.2rem;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .print-button:hover {
            background: var(--primary-dark);
        }
        
        @media print {
            .print-button {
                display: none;
            }
            
            .detalhes-header {
                background: #333 !important;
                color: white !important;
            }
        }
    </style>
</head>
<body>
    <button class="print-button" onclick="window.print()">
        <i class="fas fa-print"></i> Imprimir
    </button>
    
    <main class="container">
        <div class="detalhes-header">
            <h1><i class="fas fa-file-import"></i> Detalhes da Importação</h1>
            <div class="detalhes-info">
                <div class="detalhes-info-item">
                    <strong>Arquivo:</strong>
                    <?= htmlspecialchars($importacao['arquivo_nome']) ?>
                </div>
                <div class="detalhes-info-item">
                    <strong>Usuário:</strong>
                    <?= htmlspecialchars($importacao['usuario_nome'] ?? 'N/A') ?>
                </div>
                <div class="detalhes-info-item">
                    <strong>Data/Hora:</strong>
                    <?= date('d/m/Y H:i:s', strtotime($importacao['data_hora'])) ?>
                </div>
                <div class="detalhes-info-item">
                    <strong>Registros:</strong>
                    <?= number_format($importacao['quantidade_registros'], 0, ',', '.') ?>
                </div>
                <div class="detalhes-info-item">
                    <strong>Status:</strong>
                    <span class="status-badge <?= strtoupper(trim($importacao['status'] ?? '')) === 'SUCESSO' ? 'status-sucesso' : 'status-erro' ?>">
                        <?= htmlspecialchars($importacao['status'] ?? 'N/A') ?>
                    </span>
                </div>
            </div>
        </div>
        
        <?php if (!empty($medicamentos)): ?>
        <div class="detalhes-section">
            <h2><i class="fas fa-pills"></i> Medicamentos Importados (<?= count($medicamentos) ?>)</h2>
            <table class="detalhes-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Quantidade</th>
                        <th>Lote</th>
                        <th>Validade</th>
                        <th>Observações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($medicamentos as $med): ?>
                    <tr class="tipo-medicamento">
                        <td><?= htmlspecialchars($med['nome']) ?></td>
                        <td><?= htmlspecialchars($med['quantidade']) ?></td>
                        <td><?= htmlspecialchars($med['lote'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($med['validade'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($med['observacoes'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($pacientes)): ?>
        <div class="detalhes-section">
            <h2><i class="fas fa-user"></i> Pacientes Importados (<?= count($pacientes) ?>)</h2>
            <table class="detalhes-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>CPF</th>
                        <th>Observações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pacientes as $pac): ?>
                    <tr class="tipo-paciente">
                        <td><?= htmlspecialchars($pac['nome']) ?></td>
                        <td><?= htmlspecialchars($pac['cpf'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($pac['observacoes'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <?php if (empty($medicamentos) && empty($pacientes)): ?>
        <div class="detalhes-section">
            <div class="no-results">
                <i class="fas fa-info-circle"></i>
                Nenhum detalhe encontrado para esta importação.
            </div>
        </div>
        <?php endif; ?>
        
        <div class="form-actions">
            <a href="javascript:window.close()" class="btn-secondary">
                <i class="fas fa-times"></i> Fechar
            </a>
            <a href="relatorios.php" class="btn-primary" target="_parent">
                <i class="fas fa-arrow-left"></i> Voltar aos Relatórios
            </a>
        </div>
    </main>
</body>
</html> 