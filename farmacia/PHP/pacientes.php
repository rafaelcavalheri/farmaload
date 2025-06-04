<?php
include 'config.php';
verificarAutenticacao(['admin', 'operador']);

// Processar ativação/desativação
if (isset($_GET['toggle'])) {
    try {
        $csrfToken = $_GET['csrf'] ?? '';
        if (!validarTokenCsrf($csrfToken)) {
            throw new Exception('Token CSRF inválido.');
        }

        $id = intval($_GET['toggle']);
        $stmt = $pdo->prepare("UPDATE pacientes SET ativo = NOT ativo WHERE id = ?");
        $stmt->execute([$id]);

        header('Location: pacientes.php?sucesso=Status+do+paciente+atualizado+com+sucesso');
        exit();
    } catch (Exception $e) {
        header('Location: pacientes.php?erro=' . urlencode($e->getMessage()));
        exit();
    }
}

$busca = $_GET['busca'] ?? '';
$ordem = $_GET['ordem'] ?? 'nome';
$direcao = $_GET['direcao'] ?? 'ASC';

$sql = "SELECT p.id, p.nome, p.cpf, p.sim, p.nascimento, p.ativo, 
               COUNT(pm.id) AS total_medicamentos, 
               (SELECT MAX(data) FROM transacoes WHERE paciente_id = p.id) as ultima_coleta
        FROM pacientes p
        LEFT JOIN paciente_medicamentos pm ON pm.paciente_id = p.id";
$params = [];
if (!empty($busca)) {
    $sql .= " WHERE p.nome LIKE ? OR p.cpf LIKE ? OR p.sim LIKE ?";
    $params = array_fill(0, 3, "%$busca%");
}
$sql .= " GROUP BY p.id";

// Adicionar ordenação
$colunas_ordenacao = [
    'nome' => 'p.nome',
    'cpf' => 'p.cpf',
    'sim' => 'p.sim',
    'nascimento' => 'p.nascimento',
    'medicamentos' => 'total_medicamentos',
    'ultima_coleta' => 'ultima_coleta',
    'status' => 'p.ativo'
];

if (isset($colunas_ordenacao[$ordem])) {
    $sql .= " ORDER BY " . $colunas_ordenacao[$ordem] . " " . ($direcao === 'DESC' ? 'DESC' : 'ASC');
} else {
    $sql .= " ORDER BY p.nome ASC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Gerenciar Pacientes</title>
    <link rel="icon" type="image/x-icon" href="/images/fav.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <link rel="stylesheet" href="/css/style.css" />
    <style>
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .search-container {
            flex: 1;
            min-width: 300px;
            display: flex;
            gap: 0.5rem;
        }
        .actions {
            display: flex;
            gap: 0.5rem;
        }
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
            min-width: 140px;
            justify-content: center;
            font-size: 0.95rem;
        }
        .btn-primary:hover {
            background-color: var(--primary-dark);
        }
        .btn-primary i {
            font-size: 1rem;
        }
        .btn-primary span {
            white-space: nowrap; /* Garante que o texto não quebre */
            display: inline-block; /* Melhor controle de espaço */
        }
        /* Estilos adicionados para garantir que as colunas e ações sejam exibidas corretamente */
        table {
            table-layout: fixed;
            width: 100%;
            margin-bottom: 20px;
        }
        table th, table td {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            padding: 8px 4px;
        }
        .actions {
            display: flex;
            gap: 4px;
            min-width: 160px;
            width: 160px;
        }
        td.actions {
            white-space: nowrap;
            position: sticky;
            right: 0;
            background-color: #fff;
            z-index: 10;
            box-shadow: -5px 0 5px rgba(0,0,0,0.1);
            padding: 6px;
        }
        td.actions .btn-secondary {
            padding: 4px 6px;
            margin: 0;
            min-width: auto;
        }
        .action-buttons {
            display: flex;
            gap: 3px;
            justify-content: flex-start;
            flex-wrap: nowrap;
            width: 100%;
        }
        /* Definir larguras máximas para colunas específicas */
        th:nth-child(1), td:nth-child(1) { max-width: 180px; } /* Nome */
        th:nth-child(2), td:nth-child(2) { width: 100px; } /* CPF */
        th:nth-child(3), td:nth-child(3) { width: 60px; } /* SIM */
        th:nth-child(4), td:nth-child(4) { width: 60px; } /* Idade */
        th:nth-child(5), td:nth-child(5) { width: 90px; } /* Nascimento */
        th:nth-child(6), td:nth-child(6) { width: 90px; } /* Medicamentos */
        th:nth-child(7), td:nth-child(7) { width: 80px; } /* Próx. Renovação */
        th:nth-child(8), td:nth-child(8) { width: 70px; } /* Última Coleta */
        th:nth-child(9), td:nth-child(9) { width: 160px; } /* Status */
        th:nth-child(10), td:nth-child(10) { width: 160px; } /* Ações */
        /* Responsividade */
        @media (max-width: 768px) {
            .header-actions {
                flex-direction: column;
            }
            .search-container {
                width: 100%;
            }
            .actions {
                width: 100%;
                justify-content: flex-end;
            }
            /* Ajustar tabela para mobile */
            table {
                display: block;
                overflow-x: auto;
            }
        }
        
        /* Modal de Dispensação */
        .modal-dispensar {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content-dispensar {
            background-color: #fff;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 800px;
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }
        .close-dispensar {
            position: absolute;
            right: 20px;
            top: 10px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: var(--secondary-color);
        }
        .close-dispensar:hover {
            color: var(--danger-color);
        }
        .medicamento-dispensar {
            border: 1px solid var(--border-color);
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .medicamento-dispensar h4 {
            margin: 0 0 10px 0;
            color: var(--primary-color);
        }
        .quantidade-dispensar {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }
        .quantidade-dispensar input {
            width: 100px;
        }
        .btn-dispensar {
            background-color: var(--success-color);
            color: white;
            padding: 5px 15px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
        }
        .btn-dispensar:hover {
            background-color: #219a52;
        }
        .btn-dispensar:disabled {
            background-color: var(--secondary-color);
            cursor: not-allowed;
        }
        .medicamento-info {
            display: none;
        }
        .status-renovacao {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        .status-renovacao .badge {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        .status-renovacao .badge.renovado {
            background-color: #28a745;
            color: white;
        }
        .status-renovacao .data {
            font-weight: normal;
        }
        th.sortable {
            cursor: pointer;
            position: relative;
            padding-right: 20px;
        }
        th.sortable:after {
            content: '↕';
            position: absolute;
            right: 5px;
            color: #999;
        }
        th.sortable.asc:after {
            content: '↑';
            color: #333;
        }
        th.sortable.desc:after {
            content: '↓';
            color: #333;
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>
<main class="container">
    <h2><i class="fas fa-users"></i> Gerenciamento de Pacientes</h2>

    <?php if (isset($_GET['sucesso'])): ?>
        <div class="alert sucesso"><?= htmlspecialchars($_GET['sucesso']) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['erro'])): ?>
        <div class="alert erro"><?= htmlspecialchars($_GET['erro']) ?></div>
    <?php endif; ?>

    <div class="header-actions">
        <form method="GET" class="form-group">
            <div class="search-container">
                <input type="text" name="busca" placeholder="Buscar pacientes..." value="<?= htmlspecialchars($busca) ?>" />
                <button type="submit" class="btn-secondary"><i class="fas fa-search"></i> Buscar</button>
            </div>
        </form>
        <div class="actions">
            <a href="cadastrar_paciente.php" class="btn-secondary">+ Novo Paciente</a>
        </div>
    </div>

    <?php if ($stmt->rowCount() > 0): ?>
    <table>
        <thead>
            <tr>
                <th class="sortable" data-ordem="nome">Nome</th>
                <th class="sortable" data-ordem="cpf">CPF</th>
                <th class="sortable" data-ordem="sim">SIM</th>
                <th class="sortable" data-ordem="nascimento">Nascimento</th>
                <th class="sortable" data-ordem="medicamentos">Medicamentos</th>
                <th class="sortable" data-ordem="ultima_coleta">Última Coleta</th>
                <th class="sortable" data-ordem="status">Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($paciente = $stmt->fetch()): ?>
            <?php
                $nasc = new DateTime($paciente['nascimento']);
                $idade = (new DateTime())->diff($nasc)->y;
                $renAlert = '';

                if (!empty($paciente['proxima_renovacao'])) {
                    $dataRaw = trim($paciente['proxima_renovacao']);
                    $ren = DateTime::createFromFormat('Y-m-d', $dataRaw);
                    
                    if ($ren !== false) {
                        $hoje = new DateTime();
                        
                        if ($ren < $hoje) {
                            $renAlert = '<span class="badge badge-danger">Atrasado</span>';
                        } elseif ($ren->format('Y-m') === $hoje->format('Y-m')) {
                            $renAlert = '<span class="badge badge-warning">Este mês</span>';
                        } else {
                            $renAlert = '<span class="badge">'. $ren->format('d/m/Y') .'</span>';
                        }
                    } else {
                        $renAlert = '<span class="badge badge-secondary">Data inválida</span>';
                    }
                } else {
                    $renAlert = '<span class="badge badge-secondary">Não informado</span>';
                }
            ?>
            <tr class="<?= !$paciente['ativo'] ? 'inativo' : '' ?>">
                <td><?= htmlspecialchars($paciente['nome']) ?></td>
                <td><span id="cpf-<?= $paciente['id'] ?>"><?= formatarCPF($paciente['cpf']) ?></span></td>
                <td><?= htmlspecialchars($paciente['sim'] ?? '--') ?></td>
                <td><?= $nasc->format('d/m/Y') ?> (<?= $idade ?> anos)</td>
                <td>
                    <?php if ($paciente['total_medicamentos'] > 0): ?>
                        <span class="badge"><?= $paciente['total_medicamentos'] ?></span>
                        <button type="button" class="btn-link show-medicamentos" data-paciente="<?= $paciente['id'] ?>">
                            <i class="fas fa-pills"></i> Ver
                        </button>
                        <div id="medicamentos-<?= $paciente['id'] ?>" class="medicamento-info"></div>
                    <?php else: ?>
                        -- 
                    <?php endif; ?>
                </td>
                <td>
                    <?php 
                    if (!empty($paciente['ultima_coleta'])) {
                        echo date('d/m/Y H:i', strtotime($paciente['ultima_coleta']));
                    } else {
                        echo '--';
                    }
                    ?>
                </td>
                <td><?= $paciente['ativo'] ? '<span class="badge badge-success">Ativo</span>' : '<span class="badge badge-danger">Inativo</span>' ?></td>
                <td class="actions">
                    <div class="action-buttons">
                        <?php if ($paciente['ativo']): ?>
                            <button onclick="abrirModalDispensar(<?= $paciente['id'] ?>, '<?= htmlspecialchars($paciente['nome']) ?>')" 
                                    class="btn-secondary" 
                                    title="Dispensar Medicamentos"
                                    <?= $paciente['total_medicamentos'] == 0 ? 'disabled' : '' ?>>
                                <i class="fas fa-pills"></i>
                            </button>
                        <?php endif; ?>
                        <a href="editar_paciente.php?id=<?= $paciente['id'] ?>" class="btn-secondary" title="Editar">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="pacientes.php?toggle=<?= $paciente['id'] ?>&csrf=<?= gerarTokenCsrf() ?>"
                          class="btn-secondary"
                          title="<?= $paciente['ativo'] ? 'Desativar' : 'Ativar' ?>"
                          onclick="return confirm('Deseja realmente <?= $paciente['ativo'] ? 'desativar' : 'ativar' ?> este paciente?');">
                            <i class="fas fa-power-off"></i>
                        </a>
                        <a href="detalhes_paciente.php?id=<?= $paciente['id'] ?>" class="btn-secondary" title="Detalhes">
                            <i class="fas fa-eye"></i>
                        </a>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php else: ?>
        <div class="alert" style="margin-top:2rem;"><i class="fas fa-info-circle"></i> Nenhum paciente encontrado</div>
    <?php endif; ?>
</main>

<!-- Modal de Dispensação -->
<div id="modalDispensar" class="modal-dispensar">
    <div class="modal-content-dispensar">
        <span class="close-dispensar" onclick="fecharModalDispensar()">&times;</span>
        <h3>Dispensar Medicamentos</h3>
        <p id="pacienteNome" style="margin-bottom: 20px; font-size: 1.1em;"></p>
        <div id="medicamentosDispensar"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Mostrar/ocultar medicamentos
    document.querySelectorAll('.show-medicamentos').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.paciente;
            const ctr = document.getElementById(`medicamentos-${id}`);
            if (!ctr) return;

            if (ctr.style.display === 'block') {
                ctr.style.display = 'none';
                return;
            }

            fetch(`ajax_medicamentos_paciente.php?paciente_id=${id}`)
                .then(response => response.text())
                .then(html => {
                    ctr.innerHTML = html;
                    ctr.style.display = 'block';
                })
                .catch(error => {
                    ctr.innerHTML = `<div class='alert erro'>Erro: ${error.message}</div>`;
                    ctr.style.display = 'block';
                });
        });
    });
});

function abrirModalDispensar(pacienteId, pacienteNome) {
    document.getElementById('modalDispensar').style.display = 'block';
    document.getElementById('pacienteNome').textContent = 'Paciente: ' + pacienteNome;
    
    // Carregar medicamentos do paciente
    fetch(`ajax_form_dispensar.php?paciente_id=${pacienteId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('medicamentosDispensar').innerHTML = html;
            
            // Adicionar eventos aos inputs de quantidade
            document.querySelectorAll('.quantidade-input').forEach(input => {
                input.addEventListener('change', function() {
                    const max = parseInt(this.getAttribute('max'));
                    const value = parseInt(this.value);
                    if (value > max) {
                        this.value = max;
                    } else if (value < 0) {
                        this.value = 0;
                    }
                });
            });
        })
        .catch(error => {
            document.getElementById('medicamentosDispensar').innerHTML = 
                `<div class='alert erro'>Erro ao carregar medicamentos: ${error.message}</div>`;
        });
}

function fecharModalDispensar() {
    document.getElementById('modalDispensar').style.display = 'none';
    document.getElementById('medicamentosDispensar').innerHTML = '';
}

function dispensarMedicamento(medicamentoId, pacienteId) {
    const quantidade = document.querySelector(`#quantidade-${medicamentoId}`).value;
    if (!quantidade || quantidade <= 0) {
        alert('Por favor, informe uma quantidade válida.');
        return;
    }

    const formData = new FormData();
    formData.append('medicamento_id', medicamentoId);
    formData.append('paciente_id', pacienteId);
    formData.append('quantidade', quantidade);

    fetch('ajax_dispensar.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Medicamento dispensado com sucesso!');
            location.reload(); // Recarrega a página para atualizar os dados
        } else {
            alert('Erro: ' + data.message);
        }
    })
    .catch(error => {
        alert('Erro ao dispensar medicamento: ' + error.message);
    });
}

// Fechar modal ao clicar fora
window.onclick = function(event) {
    const modal = document.getElementById('modalDispensar');
    if (event.target == modal) {
        fecharModalDispensar();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Função para ordenar a tabela
    function ordenarTabela(coluna) {
        const urlParams = new URLSearchParams(window.location.search);
        const ordemAtual = urlParams.get('ordem') || 'nome';
        const direcaoAtual = urlParams.get('direcao') || 'ASC';
        
        // Alternar direção se clicar na mesma coluna
        const novaDirecao = (ordemAtual === coluna && direcaoAtual === 'ASC') ? 'DESC' : 'ASC';
        
        // Atualizar parâmetros da URL
        urlParams.set('ordem', coluna);
        urlParams.set('direcao', novaDirecao);
        
        // Manter o parâmetro de busca se existir
        const busca = urlParams.get('busca');
        if (busca) {
            urlParams.set('busca', busca);
        }
        
        // Redirecionar com os novos parâmetros
        window.location.href = window.location.pathname + '?' + urlParams.toString();
    }

    // Adicionar eventos de clique nos cabeçalhos
    document.querySelectorAll('th.sortable').forEach(th => {
        th.addEventListener('click', () => {
            ordenarTabela(th.dataset.ordem);
        });
    });

    // Marcar coluna atual como ordenada
    const urlParams = new URLSearchParams(window.location.search);
    const ordemAtual = urlParams.get('ordem') || 'nome';
    const direcaoAtual = urlParams.get('direcao') || 'ASC';
    
    const thAtual = document.querySelector(`th[data-ordem="${ordemAtual}"]`);
    if (thAtual) {
        thAtual.classList.add(direcaoAtual.toLowerCase());
    }
});
</script>
<?php include 'footer.php'; ?> 