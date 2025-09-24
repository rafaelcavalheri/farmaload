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
        "SELECT t.id, m.nome AS medicamento, t.quantidade, t.data, t.observacoes, u.nome AS operador
        FROM transacoes t 
        JOIN medicamentos m ON t.medicamento_id = m.id 
        JOIN usuarios u ON t.usuario_id = u.id
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
            pm.observacoes,
            med.nome AS medico,
            CONCAT(med.crm_numero, ' ', med.crm_estado) as crm_completo
        FROM paciente_medicamentos pm
        JOIN medicamentos m ON m.id = pm.medicamento_id
        JOIN pacientes p ON p.id = pm.paciente_id
        LEFT JOIN medicos med ON med.id = pm.medico_id
        WHERE pm.paciente_id = ?
        ORDER BY pm.data_cadastro ASC, pm.id ASC
    ");
    $stmtMedicamentos->execute([$idPaciente]);
    $medicamentos = $stmtMedicamentos->fetchAll(PDO::FETCH_ASSOC);

    // Calcular quantidade disponível para cada medicamento
    foreach ($medicamentos as &$med) {
        $estoque_atual = calcularEstoqueAtual($pdo, $med['medicamento_id']);
        // LÓGICA FINAL: quantidade_disponivel = quantidade_solicitada
        $quantidade_disponivel = (int)$med['quantidade_solicitada'];
        $med['quantidade_disponivel'] = min($quantidade_disponivel, $estoque_atual);
        $med['estoque_atual'] = $estoque_atual;
    }
    unset($med); // Limpar referência do foreach

    // Buscar último agendamento válido do paciente
    $stmtUltimoAgendamento = $pdo->prepare("
        SELECT a.data, a.horario, a.status, a.encaixe
        FROM agenda a 
        WHERE a.paciente_id = ? 
        AND a.status IN ('agendado', 'confirmado', 'realizado')
        ORDER BY a.data DESC, a.horario DESC 
        LIMIT 1
    ");
    $stmtUltimoAgendamento->execute([$idPaciente]);
    $ultimoAgendamento = $stmtUltimoAgendamento->fetch(PDO::FETCH_ASSOC);

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
    <link rel="stylesheet" href="/css/detalhes_paciente.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <script>
        // Definir as funções globalmente antes de qualquer uso
        function editarObservacoes(button) {
            const observacaoCell = button.closest('td');
            const observacao = observacaoCell.getAttribute('data-observacao-completo');
            const transacaoId = observacaoCell.getAttribute('data-transacao-id');
            
            const modal = document.createElement('div');
            modal.className = 'modal';
            modal.innerHTML = `
                <div class="modal-content">
                    <h3>Observação</h3>
                    <textarea id="observacaoEdit" rows="10">${observacao}</textarea>
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





        function extornarTransacao(transacaoId, medicamentoNome, quantidade) {
            if (!confirm(`Tem certeza que deseja extornar ${quantidade} unidade(s) de ${medicamentoNome}?`)) {
                return;
            }
            fetch('ajax_extornar_transacao.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ transacao_id: transacaoId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Extorno realizado com sucesso!');
                    location.reload();
                } else {
                    alert('Erro: ' + data.message);
                }
            })
            .catch(error => {
                alert('Erro ao extornar: ' + error.message);
            });
        }
    </script>

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
            <p><strong>Código do Paciente:</strong> <?= sanitizar($paciente['codigo_paciente'] ?? 'Não informado') ?></p>
            <p><strong>CPF:</strong> <?= sanitizar($paciente['cpf']) ?></p>
            <p><strong>Telefone:</strong> <?= sanitizar($paciente['telefone']) ?></p>
            <?php if (!empty($paciente['telefone2'])): ?>
                <p><strong>Telefone 2:</strong> <?= sanitizar($paciente['telefone2']) ?></p>
            <?php endif; ?>
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
            <p><strong>Último Agendamento:</strong>
                <?php if ($ultimoAgendamento): ?>
                    <?php 
                        $dataAgendamento = new DateTime($ultimoAgendamento['data']);
                        $statusClass = '';
                        $statusIcon = '';
                        
                        switch ($ultimoAgendamento['status']) {
                            case 'agendado':
                                $statusClass = 'badge-warning';
                                $statusIcon = 'fas fa-calendar';
                                break;
                            case 'confirmado':
                                $statusClass = 'badge-info';
                                $statusIcon = 'fas fa-check-circle';
                                break;
                            case 'realizado':
                                $statusClass = 'badge-success';
                                $statusIcon = 'fas fa-check-double';
                                break;
                            default:
                                $statusClass = 'badge-secondary';
                                $statusIcon = 'fas fa-question';
                        }
                    ?>
                    <span class="badge <?= $statusClass ?>">
                        <i class="<?= $statusIcon ?>"></i>
                        <?= $dataAgendamento->format('d/m/Y') ?> às <?= $ultimoAgendamento['horario'] ?>
                        <?php if ($ultimoAgendamento['encaixe']): ?>
                            <i class="fas fa-plus-circle" title="Encaixe"></i>
                        <?php endif; ?>
                    </span>
                <?php else: ?>
                    <span class="badge badge-secondary">Nenhum agendamento encontrado</span>
                <?php endif; ?>
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
                        <?php if (!empty($med['observacoes'])): ?>
                            <div class="observacoes-info">
                                <div class="observacoes-header">
                                    <i class="fas fa-sticky-note"></i>
                                    <strong>Observações:</strong>
                                </div>
                                <div class="observacoes-content">
                                    <?= sanitizar($med['observacoes']) ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>Não há medicamentos cadastrados para este paciente.</p>
            <?php endif; ?>
        </div>

        <div class="historico">
            <h3>Histórico de Transações de Medicamentos</h3>

            <?php if (count($historico) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Medicamento</th>
                            <th>Tipo</th>
                            <th>Quantidade</th>
                            <th>Data</th>
                            <th>Operador</th>
                            <th>Ações</th>
                            <th>Observações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historico as $registro): ?>
                            <tr class="<?= $registro['quantidade'] > 0 ? 'dispensacao' : 'extorno' ?>">
                                <td><?= sanitizar($registro['medicamento']) ?></td>
                                <td>
                                    <?php if ($registro['quantidade'] > 0): ?>
                                        <span class="badge badge-success"><i class="fas fa-arrow-down"></i> Dispensação</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger"><i class="fas fa-undo"></i> Extorno</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= abs($registro['quantidade']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($registro['data'])) ?></td>
                                <td><?= sanitizar($registro['operador']) ?></td>
                                <td class="acoes">
                                    <?php if ($registro['quantidade'] > 0): ?>
                                        <button onclick="extornarTransacao(<?= $registro['id'] ?>, '<?= htmlspecialchars($registro['medicamento'], ENT_QUOTES) ?>', <?= $registro['quantidade'] ?>)" class="btn-extornar"><i class="fas fa-undo"></i> Extornar</button>
                                    <?php endif; ?>
                                </td>
                                <td class="observacao" data-transacao-id="<?= $registro['id'] ?>" data-observacao-completo="<?= htmlspecialchars($registro['observacoes'] ?? '', ENT_QUOTES) ?>">
                                    <?php
                                    $texto_observacao = trim(preg_replace('/\s+/', ' ', $registro['observacoes'] ?? ''));
                                    $limite = 40;
                                    $observacao_resumida = mb_strlen($texto_observacao) > $limite
                                        ? mb_substr($texto_observacao, 0, $limite) . '…'
                                        : $texto_observacao;
                                    ?>
                                    <div class="observacao-texto" style="display: flex; justify-content: space-between; align-items: center; gap: 10px;">
                                        <span><?= htmlspecialchars($observacao_resumida) ?></span>
                                        <button onclick="editarObservacoes(this)" class="btn-secondary" title="Ver mais"><i class="fas fa-eye"></i></button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Não há histórico de transações de medicamentos para este paciente.</p>
            <?php endif; ?>
        </div>

        <div class="acoes">
             <a href="pacientes.php" class="btn btn-secondary">Voltar para a Lista</a>
        </div>
    </main>



    <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>

