<?php
require __DIR__ . '/config.php';
include 'funcoes_estoque.php';
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
            m.id as medicamento_id,
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
            CONCAT(med.crm_numero, ' ', med.crm_estado) as crm_completo
        FROM paciente_medicamentos pm
        JOIN medicamentos m ON m.id = pm.medicamento_id
        JOIN pacientes p ON p.id = pm.paciente_id
        LEFT JOIN medicos med ON med.id = pm.medico_id
        WHERE pm.paciente_id = ?
        ORDER BY m.nome
    ");
    $stmtMedicamentos->execute([$idPaciente]);
    $medicamentos = $stmtMedicamentos->fetchAll(PDO::FETCH_ASSOC);

    // Calcular quantidade disponível para cada medicamento
    foreach ($medicamentos as &$med) {
        $estoque_atual = calcularEstoqueAtual($pdo, $med['medicamento_id']);
        $quantidade_disponivel = max(0, (int)$med['quantidade_solicitada'] - (int)$med['quantidade_entregue']);
        $med['quantidade_disponivel'] = min($quantidade_disponivel, $estoque_atual);
        $med['estoque_atual'] = $estoque_atual;
    }
    unset($med); // Limpar referência do foreach

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
    <link rel="icon" type="image/png" href="/images/fav.png">
    <link rel="stylesheet" href="/css/style.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <script>
        // Definir as funções globalmente antes de qualquer uso
        function editarObservacoes(button) {
            const observacaoCell = button.closest('td');
            const observacao = observacaoCell.querySelector('.observacao-texto').textContent;
            const transacaoId = observacaoCell.getAttribute('data-transacao-id');
            
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content">
                    <h3>Observação</h3>
                    <textarea id="observacaoEdit" rows="4">${observacao}</textarea>
                    <div class="modal-actions">
                        <button onclick="salvarObservacoes(this, ${transacaoId})" class="btn-primary">Salvar</button>
                        <button onclick="fecharModal()" class="btn-secondary">Fechar</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }

        function salvarObservacoes(button, transacaoId) {
            const observacao = document.getElementById('observacaoEdit').value;
            
            fetch('ajax_atualizar_observacao.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    observacao: observacao,
                    transacao_id: transacaoId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const observacaoCell = document.querySelector(`td[data-transacao-id="${transacaoId}"]`);
                    if (observacaoCell) {
                        observacaoCell.querySelector('.observacao-texto').textContent = observacao;
                    }
                    fecharModal();
                } else {
                    alert('Erro ao atualizar observação: ' + data.message);
                }
            })
            .catch(error => {
                alert('Erro ao atualizar observação: ' + error.message);
            });
        }

        function fecharModal() {
            const modal = document.querySelector('.modal');
            if (modal) {
                modal.remove();
            }
        }

        // Fechar modal ao clicar fora dele
        window.onclick = function(event) {
            const modal = document.getElementById('modalObservacoes');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
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
        .observacoes-cell {
            max-width: 300px;
            min-width: 200px;
            padding: 8px;
            position: relative;
        }
        .observacoes-cell .observacoes-content {
            font-size: 12px;
        }
        .observacoes-content {
            border: none;
            background: transparent;
            box-shadow: none;
            outline: none;
        }
        .btn-ver-mais {
            background: none;
            border: none;
            color: #007bff;
            cursor: pointer;
            padding: 2px 5px;
            font-size: 0.9em;
            margin-top: 5px;
        }
        .btn-ver-mais:hover {
            text-decoration: underline;
        }
        .modal-observacoes {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        .modal-content {
            position: relative;
            background-color: #fff;
            margin: 15% auto;
            padding: 20px;
            width: 50%;
            max-width: 600px;
            border-radius: 5px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .modal-content .observacoes-content {
            text-align: left;
            white-space: pre-wrap;
            margin-top: 10px;
        }
        .close-modal {
            position: absolute;
            right: 10px;
            top: 10px;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
        .close-modal:hover {
            color: #000;
        }
        .btn-editar {
            background: none;
            border: none;
            color: #28a745;
            cursor: pointer;
            padding: 2px 5px;
            font-size: 0.9em;
            margin-top: 5px;
            margin-left: 10px;
        }
        .btn-editar:hover {
            text-decoration: underline;
        }
        .observacoes-editor {
            width: 100%;
            min-height: 150px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 15px;
            font-family: inherit;
            font-size: inherit;
            resize: vertical;
        }
        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 15px;
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
        }
        .btn-primary {
            background-color: #28a745;
            color: white;
        }
        .btn-primary:hover {
            background-color: #218838;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        .modal {
            display: block;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: #fff;
            margin: 15% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 500px;
            position: relative;
        }
        .modal-actions {
            margin-top: 15px;
            text-align: right;
        }
        .modal-actions button {
            margin-left: 10px;
        }
        .observacao {
            position: relative;
        }
        .observacao-texto {
            margin-bottom: 5px;
        }
        .observacao-actions {
            display: flex;
            gap: 10px;
        }
        .btn-link {
            background: none;
            border: none;
            color: #0d6efd;
            cursor: pointer;
            padding: 0;
            font: inherit;
            text-decoration: underline;
        }
        .btn-link:hover {
            color: #0a58ca;
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
                                <span class="info-item">
                                    <i class="fas fa-check-circle"></i>
                                    Disponível: <?= sanitizar($med['quantidade_disponivel']) ?>
                                </span>
                                <span class="info-item">
                                    <i class="fas fa-warehouse"></i>
                                    Estoque: <?= sanitizar($med['estoque_atual']) ?>
                                </span>
                                <?php
                                $hoje = new DateTime();
                                $statusRenovacao = '';
                                
                                if ($med['renovado']) {
                                    $statusRenovacao = '<span class="badge badge-success"><i class="fas fa-check"></i> Renovado</span>';
                                } elseif (!empty($med['renovacao'])) {
                                    // Try to parse the date in different formats
                                    $dataRenovacao = null;
                                    if (strpos($med['renovacao'], '/') !== false) {
                                        // Brazilian format (DD/MM/YYYY)
                                        $dataRenovacao = DateTime::createFromFormat('d/m/Y', $med['renovacao']);
                                    } else {
                                        // ISO format (YYYY-MM-DD)
                                        $dataRenovacao = DateTime::createFromFormat('Y-m-d', $med['renovacao']);
                                    }
                                    
                                    if (!$dataRenovacao) {
                                        // If both formats fail, try direct DateTime constructor
                                        try {
                                            $dataRenovacao = new DateTime($med['renovacao']);
                                        } catch (Exception $e) {
                                            $statusRenovacao = '<span class="badge badge-secondary"><i class="fas fa-question"></i> Data inválida</span>';
                                            continue;
                                        }
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
                            <th>Observações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historico as $registro): ?>
                            <tr>
                                <td><?= sanitizar($registro['medicamento']) ?></td>
                                <td><?= sanitizar($registro['quantidade']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($registro['data'])) ?></td>
                                <td class="observacao" data-transacao-id="<?= $registro['id'] ?>">
                                    <div class="observacao-texto"><?= htmlspecialchars(trim(preg_replace('/\s+/', ' ', $registro['observacoes'] ?? ''))) ?></div>
                                    <div class="observacao-actions">
                                        <button onclick="editarObservacoes(this)" class="btn-link"><i class="fas fa-eye"></i> Ver mais</button>
                                    </div>
                                </td>
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

    <!-- Modal para observações -->
    <div id="modalObservacoes" class="modal-observacoes">
        <div class="modal-content">
            <span class="close-modal" onclick="fecharModal()">&times;</span>
            <h3>Observações</h3>
            <div class="observacoes-content"></div>
        </div>
    </div>

    <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>

