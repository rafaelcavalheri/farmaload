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
            pm.renovacao,
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
            background: #fff;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 15px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95em;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.9em;
            font-weight: 500;
        }
        .badge-success {
            background-color: #28a745;
            color: white;
        }
        .badge-warning {
            background-color: #ffc107;
            color: #000;
        }
        .badge-danger {
            background-color: #dc3545;
            color: white;
        }
        .badge-secondary {
            background-color: #6c757d;
            color: white;
        }
        .badge i {
            font-size: 0.9em;
        }
        .medico-info {
            font-size: 0.9em;
            color: #666;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
        .observacao-box {
            margin: 15px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
        }
        .observacao-box h4 {
            margin: 0 0 10px 0;
            color: #495057;
            font-size: 1.1em;
        }
        .observacao-content {
            white-space: pre-wrap;
            margin: 0;
            color: #212529;
            line-height: 1.5;
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
            <?php if (!empty($paciente['observacao'])): ?>
            <div class="observacao-box">
                <h4><i class="fas fa-sticky-note"></i> Observações:</h4>
                <p class="observacao-content"><?= nl2br(sanitizar($paciente['observacao'])) ?></p>
            </div>
            <?php endif; ?>
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
                            <h4><?= sanitizar($med['medicamento']) ?></h4>
                            <div class="info-grid">
                                <span class="info-item">
                                    <i class="fas fa-pills"></i>
                                    Quantidade: <?= sanitizar($med['quantidade_recebida']) ?>
                                </span>
                                <span class="info-item">
                                    <i class="fas fa-box"></i>
                                    Entregue: <?= sanitizar($med['quantidade_entregue']) ?>
                                </span>
                                <?php
                                $hoje = new DateTime();
                                $statusRenovacao = '';
                                
                                if ($med['renovado']) {
                                    $statusRenovacao = '<span class="badge badge-success"><i class="fas fa-check"></i> Renovado</span>';
                                } elseif (!empty($med['renovacao'])) {
                                    $dataRenovacao = DateTime::createFromFormat('Y-m-d', $med['renovacao']);
                                    if (!$dataRenovacao) {
                                        $dataRenovacao = new DateTime($med['renovacao']);
                                    }
                                    
                                    if ($dataRenovacao < $hoje) {
                                        $statusRenovacao = '<span class="badge badge-danger"><i class="fas fa-exclamation-triangle"></i> ' . $dataRenovacao->format('d/m/Y') . ' (Atrasada)</span>';
                                    } elseif ($dataRenovacao->format('Y-m') === $hoje->format('Y-m')) {
                                        $statusRenovacao = '<span class="badge badge-warning"><i class="fas fa-clock"></i> ' . $dataRenovacao->format('d/m/Y') . ' (Este mês)</span>';
                                    } else {
                                        $statusRenovacao = '<span class="badge"><i class="fas fa-calendar"></i> ' . $dataRenovacao->format('d/m/Y') . '</span>';
                                    }
                                } else {
                                    $statusRenovacao = '<span class="badge badge-secondary"><i class="fas fa-question"></i> Sem data definida</span>';
                                }
                                ?>
                                <span class="info-item">
                                    <?= $statusRenovacao ?>
                                </span>
                            </div>
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

