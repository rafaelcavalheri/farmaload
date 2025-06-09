<?php
require __DIR__ . '/config.php';
verificarAutenticacao(['admin', 'operador']);

function safe_strtotime($datetime) {
    if (empty($datetime)) return false;
    return strtotime($datetime);
}

$idPaciente = $_GET['id'] ?? null;
if (!$idPaciente) {
    header('Location: lista_pacientes.php');
    exit;
}

try {
    // Carregar dados do paciente
    $stmtPaciente = $pdo->prepare("SELECT * FROM pacientes WHERE id = ?");
    $stmtPaciente->execute([$idPaciente]);
    $paciente = $stmtPaciente->fetch(PDO::FETCH_ASSOC);

    if (!$paciente) {
        throw new Exception("Paciente não encontrado.");
    }

    // Calcular idade
    $dataNascimento = new DateTime($paciente['nascimento']);
    $hoje = new DateTime();
    $idade = $hoje->diff($dataNascimento)->y;

    // Carregar histórico de retiradas de medicamentos (transações)
    $stmtHistorico = $pdo->prepare(
        "SELECT t.id, m.nome AS medicamento, t.quantidade, t.data, t.observacoes 
        FROM transacoes t 
        JOIN medicamentos m ON t.medicamento_id = m.id 
        WHERE t.paciente_id = ? 
        ORDER BY t.data DESC"
    );
    $stmtHistorico->execute([$idPaciente]);
    $historico = $stmtHistorico->fetchAll(PDO::FETCH_ASSOC);

    // Carregar medicamentos atuais do paciente
    $stmtMedicamentos = $pdo->prepare("
        SELECT 
            pm.id,
            m.nome AS medicamento,
            pm.quantidade as quantidade_recebida,
            COALESCE(pm.quantidade_solicitada, pm.quantidade) as quantidade_solicitada,
            COALESCE((
                SELECT SUM(quantidade) 
                FROM transacoes 
                WHERE medicamento_id = pm.medicamento_id 
                AND paciente_id = pm.paciente_id
            ), 0) as quantidade_entregue,
            pm.renovado,
            DATE_FORMAT(pm.renovacao, '%d/%m/%Y') as renovacao_formatada,
            med.nome AS medico,
            med.crm_completo
        FROM paciente_medicamentos pm
        JOIN medicamentos m ON m.id = pm.medicamento_id
        JOIN pacientes p ON p.id = pm.paciente_id
        LEFT JOIN medicos med ON med.id = pm.medico_id
        WHERE pm.paciente_id = ?
        ORDER BY m.nome
    ");
    $stmtMedicamentos->execute([$idPaciente]);
    $medicamentos = $stmtMedicamentos->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $erro = "Erro ao carregar dados: " . sanitizar($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Detalhes do Paciente</title>
    <link rel="stylesheet" href="/css/style.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        .medicamentos-atuais {
            margin: 20px 0;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 4px;
        }
        .medicamento-item {
            padding: 15px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: #fff;
        }
        .medicamento-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .medicamento-nome {
            font-weight: bold;
            font-size: 1.1em;
            color: #333;
        }
        .medicamento-info {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin: 10px 0;
        }
        .info-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .badge {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.9em;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }
        .badge.renovado {
            background-color: #28a745;
            color: white;
        }
        .badge.quantidade {
            background-color: #17a2b8;
            color: white;
            transition: background-color 0.3s;
        }
        .badge.quantidade:hover {
            background-color: #138496;
        }
        .badge i {
            font-size: 1em;
            margin-right: 3px;
        }
        .medico-info {
            font-size: 0.9em;
            color: #666;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }
        /* Ajuste para telas menores */
        @media (max-width: 768px) {
            .medicamento-info {
                gap: 10px;
            }
            .badge {
                font-size: 0.85em;
                padding: 4px 8px;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>

    <main class="container">
        <h2>Detalhes do Paciente</h2>

        <?php if (isset($erro)): ?>
            <div class="alert erro">
                <i class="fas fa-exclamation-circle"></i> <?= $erro ?>
            </div>
        <?php endif; ?>

        <div class="paciente-dados">
            <h3>Informações do Paciente</h3>
            <p><strong>Nome:</strong> <?= sanitizar($paciente['nome']) ?></p>
            <p><strong>CPF:</strong> <?= sanitizar($paciente['cpf']) ?></p>
            <p><strong>Telefone:</strong> <?= sanitizar($paciente['telefone']) ?></p>
            <p><strong>Idade:</strong> <?= $idade ?> anos</p>
            <p><strong>Número do SIM:</strong> <?= sanitizar($paciente['sim'] ?? 'Não informado') ?></p>
            <p><strong>Validade:</strong>
                <?php 
                    $ts = safe_strtotime($paciente['validade'] ?? null);
                    if ($ts !== false) {
                        echo date('d/m/Y', $ts);
                    } else {
                        echo 'Não informado';
                    }
                ?>
            </p>
        </div>

        <div class="medicamentos-atuais">
            <h3>Medicamentos Atuais</h3>
            <?php if (count($medicamentos) > 0): ?>
                <?php foreach ($medicamentos as $med): ?>
                    <div class="medicamento-item">
                        <div class="medicamento-header">
                            <span class="medicamento-nome"><?= sanitizar($med['medicamento']) ?></span>
                        </div>
                        <div class="medicamento-info">
                            <span class="badge quantidade">
                                <i class="fas fa-pills"></i>
                                Qtde Recebida: <?= (int)$med['quantidade_recebida'] ?>
                            </span>
                            <span class="badge quantidade">
                                <i class="fas fa-file-medical"></i>
                                Qtde Solicitada: <?= (int)$med['quantidade_solicitada'] ?>
                            </span>
                            <span class="badge quantidade">
                                <i class="fas fa-check"></i>
                                Qtde Entregue: <?= (int)$med['quantidade_entregue'] ?>
                            </span>
                            <span class="badge quantidade">
                                <i class="fas fa-box-open"></i>
                                Qtde Disponível: <?= max(0, (int)$med['quantidade_solicitada'] - (int)$med['quantidade_entregue']) ?>
                            </span>
                            <?php if ((int)$med['renovado'] === 1): ?>
                                <span class="badge renovado">
                                    <i class="fas fa-sync-alt"></i>
                                    Renovação em Andamento
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($med['renovacao_formatada'])): ?>
                                <span class="info-item">
                                    <i class="fas fa-calendar"></i>
                                    Renovação: <?= htmlspecialchars($med['renovacao_formatada']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($med['medico'])): ?>
                            <div class="medico-info">
                                <i class="fas fa-user-md"></i>
                                Médico: <?= sanitizar($med['medico']) ?> (<?= sanitizar($med['crm_completo']) ?>)
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Não há medicamentos cadastrados para este paciente.</p>
            <?php endif; ?>
        </div>

        <div class="historico">
            <h3>Histórico de Retiradas de Medicamentos</h3>

            <?php if (count($historico) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Medicamento</th>
                            <th>Quantidade</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historico as $registro): ?>
                            <tr>
                                <td><?= sanitizar($registro['medicamento']) ?></td>
                                <td><?= sanitizar($registro['quantidade']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($registro['data'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Não há histórico de retiradas de medicamentos para este paciente.</p>
            <?php endif; ?>
        </div>

        <div class="acoes">
             <a href="pacientes.php" class="btn btn-secondary">Voltar para a Lista</a>
        </div>
    </main>

    <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>

