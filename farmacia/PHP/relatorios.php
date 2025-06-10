<?php
include 'config.php';

verificarAutenticacao(['admin', 'operador']);

// Validação das datas
$data_inicio = new DateTime();
$data_fim = new DateTime();
try {
    $data_inicio = new DateTime($_GET['data_inicio'] ?? 'first day of january this year');
    $data_fim = new DateTime($_GET['data_fim'] ?? 'last day of this month');
} catch (Exception $e) {
    $_SESSION['erro'] = "Formato de data inválido";
    header('Location: relatorios.php');
    exit();
}

// Busca medicamentos para o filtro
try {
    $stmt_medicamentos = $pdo->query("SELECT id, nome FROM medicamentos ORDER BY nome");
    $medicamentos = $stmt_medicamentos->fetchAll();
} catch (PDOException $e) {
    die("Erro ao carregar medicamentos: " . $e->getMessage());
}

// Busca operadores para o filtro
try {
    $stmt_operadores = $pdo->query("SELECT id, nome, perfil FROM usuarios ORDER BY nome");
    $operadores = $stmt_operadores->fetchAll();
} catch (PDOException $e) {
    die("Erro ao carregar usuários: " . $e->getMessage());
}

// Busca pacientes para o filtro
try {
    $stmt_pacientes = $pdo->query("SELECT id, nome, cpf FROM pacientes WHERE ativo = 1 ORDER BY nome");
    $pacientes = $stmt_pacientes->fetchAll();
} catch (PDOException $e) {
    die("Erro ao carregar pacientes: " . $e->getMessage());
}

// Parâmetros dos filtros
$medicamento_id = $_GET['medicamento_id'] ?? '';
$operador_id = $_GET['operador_id'] ?? '';
$paciente_id = $_GET['paciente_id'] ?? '';
$tipo_relatorio = $_GET['tipo_relatorio'] ?? 'dispensas';
$status_paciente = $_GET['status_paciente'] ?? '';

if ($tipo_relatorio === 'dispensas') {
    // Construção dinâmica da query de dispensas
    $sql = "SELECT t.*, m.nome as medicamento_nome, u.nome as operador_nome, 
                   p.nome as paciente_nome, p.cpf as paciente_cpf, p.telefone as paciente_telefone
            FROM transacoes t
            JOIN medicamentos m ON t.medicamento_id = m.id
            JOIN usuarios u ON t.usuario_id = u.id
            JOIN pacientes p ON t.paciente_id = p.id
            WHERE DATE(t.data) BETWEEN :data_inicio AND :data_fim";

    $params = [
        ':data_inicio' => $data_inicio->format('Y-m-d'),
        ':data_fim' => $data_fim->format('Y-m-d')
    ];

    if (!empty($medicamento_id)) {
        $sql .= " AND t.medicamento_id = :medicamento_id";
        $params[':medicamento_id'] = $medicamento_id;
    }
    if (!empty($operador_id)) {
        $sql .= " AND t.usuario_id = :operador_id";
        $params[':operador_id'] = $operador_id;
    }
    if (!empty($paciente_id)) {
        $sql .= " AND t.paciente_id = :paciente_id";
        $params[':paciente_id'] = $paciente_id;
    }
    $sql .= " ORDER BY t.data DESC";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $resultados = $stmt->fetchAll();
    } catch (PDOException $e) {
        die("Erro na consulta: " . $e->getMessage());
    }
} else {
    // Relatório de pacientes
    $sql = "SELECT p.id, p.nome, p.cpf, p.telefone, 
                   pm.renovacao as data_renovacao, pm.medicamento_id, m.nome as medicamento_nome
            FROM pacientes p
            LEFT JOIN paciente_medicamentos pm ON p.id = pm.paciente_id
            LEFT JOIN medicamentos m ON pm.medicamento_id = m.id
            WHERE p.ativo = 1";
    $params = [];
    // Filtro de status
    if (!empty($status_paciente)) {
        if ($status_paciente === 'vencido') {
            $sql .= " AND pm.renovacao < CURDATE()";
        } elseif ($status_paciente === 'a_vencer') {
            $sql .= " AND pm.renovacao >= CURDATE() AND pm.renovacao <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
        } elseif ($status_paciente === 'renovado') {
            $sql .= " AND pm.renovacao > CURDATE()";
        }
    }
    $sql .= " ORDER BY p.nome, m.nome";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $resultados_pacientes = $stmt->fetchAll();
    } catch (PDOException $e) {
        die("Erro na consulta de pacientes: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Relatórios</title>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" href="/images/fav.png">
    <link rel="stylesheet" href="/css/style.css">
    
</head>
<body>
    <?php include 'header.php'; ?>
    
    <main class="container">
        <h2>Relatórios</h2>
        <div class="card">
            <h3>Filtros</h3>
            <form method="GET" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="tipo_relatorio">Tipo de Relatório:</label>
                        <select id="tipo_relatorio" name="tipo_relatorio" onchange="this.form.submit()">
                            <option value="dispensas" <?= $tipo_relatorio === 'dispensas' ? 'selected' : '' ?>>Dispensas de Medicamentos</option>
                            <option value="pacientes" <?= $tipo_relatorio === 'pacientes' ? 'selected' : '' ?>>Situação dos Pacientes</option>
                        </select>
                    </div>
                </div>
                <?php if ($tipo_relatorio === 'dispensas'): ?>
                <!-- Filtros de dispensas -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="data_inicio">Data Início:</label>
                        <input type="date" id="data_inicio" name="data_inicio" 
                               value="<?= $data_inicio->format('Y-m-d') ?>" 
                               max="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label for="data_fim">Data Fim:</label>
                        <input type="date" id="data_fim" name="data_fim" 
                               value="<?= $data_fim->format('Y-m-d') ?>"
                               max="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="medicamento_id">Medicamento:</label>
                        <select id="medicamento_id" name="medicamento_id">
                            <option value="">Todos</option>
                            <?php foreach ($medicamentos as $med): ?>
                                <option value="<?= $med['id'] ?>" 
                                    <?= $med['id'] == $medicamento_id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($med['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="operador_id">Usuário:</label>
                        <select id="operador_id" name="operador_id">
                            <option value="">Todos</option>
                            <?php foreach ($operadores as $op): ?>
                                <option value="<?= $op['id'] ?>" 
                                    <?= $op['id'] == $operador_id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($op['nome']) ?> (<?= ucfirst($op['perfil']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="paciente_id">Paciente:</label>
                        <select id="paciente_id" name="paciente_id">
                            <option value="">Todos</option>
                            <?php foreach ($pacientes as $pac): ?>
                                <option value="<?= $pac['id'] ?>" 
                                    <?= $pac['id'] == $paciente_id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($pac['nome']) ?> (<?= formatarCPF($pac['cpf']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php else: ?>
                <!-- Filtros de pacientes -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="status_paciente">Status:</label>
                        <select id="status_paciente" name="status_paciente">
                            <option value="">Todos</option>
                            <option value="vencido" <?= $status_paciente === 'vencido' ? 'selected' : '' ?>>Vencido</option>
                            <option value="a_vencer" <?= $status_paciente === 'a_vencer' ? 'selected' : '' ?>>A vencer (30 dias)</option>
                            <option value="renovado" <?= $status_paciente === 'renovado' ? 'selected' : '' ?>>Renovado</option>
                        </select>
                    </div>
                </div>
                <?php endif; ?>
                <div class="form-actions">
                    <button type="submit" class="btn-secondary">Aplicar Filtros</button>
                    <a href="relatorios.php" class="btn-secondary">Limpar Filtros</a>
                    <?php if ($tipo_relatorio === 'dispensas' && !empty($resultados)): ?>
                        <a href="exportar_relatorio.php?<?= http_build_query($_GET) ?>" 
                           class="btn-secondary" target="_blank">
                            <i class="fas fa-file-excel"></i> Exportar Excel
                        </a>
                        <button type="button" class="btn-secondary" onclick="window.print()">
                            <i class="fas fa-print"></i> Imprimir
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php if ($tipo_relatorio === 'dispensas'): ?>
        <div class="card">
            <h3>Resultados (<?= count($resultados) ?> registros)</h3>
            <?php if (!empty($resultados)): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Medicamento</th>
                                <th>Quantidade</th>
                                <th>Operador</th>
                                <th>Paciente</th>
                                <th>CPF</th>
                                <th>Telefone</th>
                                <th>Observações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resultados as $dispensa): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($dispensa['data'])) ?></td>
                                    <td><?= htmlspecialchars($dispensa['medicamento_nome']) ?></td>
                                    <td><?= $dispensa['quantidade'] ?></td>
                                    <td><?= htmlspecialchars($dispensa['operador_nome']) ?></td>
                                    <td><?= htmlspecialchars($dispensa['paciente_nome']) ?></td>
                                    <td><?= htmlspecialchars($dispensa['paciente_cpf']) ?></td>
                                    <td><?= htmlspecialchars($dispensa['paciente_telefone']) ?></td>
                                    <td class="observacoes-cell">
                                        <input type="text" class="observacoes-content" value="<?= htmlspecialchars(trim(preg_replace('/\s+/', ' ', $dispensa['observacoes'] ?? ''))) ?>" readonly>
                                        <?php 
                                            $obs = $dispensa['observacoes'] ?? '';
                                            if (!empty($obs)):
                                        ?>
                                            <button class="btn-ver-mais" onclick="mostrarObservacoes(this)">Ver mais</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-results">
                    Nenhum resultado encontrado com os filtros selecionados
                </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="card">
            <h3>Resultados (<?= count($resultados_pacientes ?? []) ?> pacientes)</h3>
            <?php if (!empty($resultados_pacientes)): ?>
                <div class="form-actions" style="margin-bottom: 15px;">
                    <a href="exportar_relatorio_pacientes.php?<?= http_build_query($_GET) ?>" 
                       class="btn-secondary" target="_blank">
                        <i class="fas fa-file-excel"></i> Exportar Excel
                    </a>
                    <button type="button" class="btn-secondary" onclick="window.print()">
                        <i class="fas fa-print"></i> Imprimir
                    </button>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>CPF</th>
                                <th>Telefone</th>
                                <th>Medicamento</th>
                                <th>Data Renovação</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resultados_pacientes as $pac): ?>
                                <?php
                                    $hoje = new DateTime();
                                    $data_renovacao = null;
                                    if ($pac['data_renovacao']) {
                                        if (strpos($pac['data_renovacao'], '/') !== false) {
                                            // Formato brasileiro
                                            $data_renovacao = DateTime::createFromFormat('d/m/Y', $pac['data_renovacao']);
                                        } else {
                                            // Formato ISO
                                            $data_renovacao = new DateTime($pac['data_renovacao']);
                                        }
                                    }
                                    $status = '';
                                    $cor_status = '';
                                    
                                    if ($data_renovacao) {
                                        if ($data_renovacao < $hoje) {
                                            $status = 'Vencido';
                                            $cor_status = '#dc3545'; // Vermelho
                                        } elseif ($data_renovacao <= (clone $hoje)->modify('+30 days')) {
                                            $status = 'A vencer';
                                            $cor_status = '#ffc107'; // Amarelo
                                        } else {
                                            $status = 'Válido';
                                            $cor_status = '#28a745'; // Verde
                                        }
                                    } else {
                                        $status = 'Sem renovação';
                                        $cor_status = '#6c757d'; // Cinza
                                    }
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($pac['nome']) ?></td>
                                    <td><?= htmlspecialchars($pac['cpf']) ?></td>
                                    <td><?= htmlspecialchars($pac['telefone']) ?></td>
                                    <td><?= htmlspecialchars($pac['medicamento_nome'] ?? '-') ?></td>
                                    <td><?= $data_renovacao ? $data_renovacao->format('d/m/Y') : '-' ?></td>
                                    <td style="color: <?= $cor_status ?>; font-weight: bold;"><?= $status ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-results">
                    Nenhum paciente encontrado com os filtros selecionados
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </main>

    <style>
        @media print {
            header, .form-actions, .card:first-of-type {
                display: none !important;
            }
            .container {
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            table {
                width: 100% !important;
                font-size: 12px !important;
            }
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .form-actions .btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .form-actions .btn-secondary i {
            font-size: 1.1em;
        }

        /* Estilos para a coluna de observações */
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

        /* Modal de observações */
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

        /* Ajusta o tamanho das outras colunas */
        table th, table td {
            padding: 8px;
        }
        table th:nth-child(1) { width: 120px; } /* Data */
        table th:nth-child(2) { width: 200px; } /* Medicamento */
        table th:nth-child(3) { width: 100px; } /* Quantidade */
        table th:nth-child(4) { width: 150px; } /* Operador */
        table th:nth-child(5) { width: 200px; } /* Paciente */
        table th:nth-child(6) { width: 120px; } /* CPF */
        table th:nth-child(7) { width: 120px; } /* Telefone */
    </style>

    <!-- Modal para observações -->
    <div id="modalObservacoes" class="modal-observacoes">
        <div class="modal-content">
            <span class="close-modal" onclick="fecharModal()">&times;</span>
            <h3>Observações</h3>
            <div class="observacoes-content"></div>
        </div>
    </div>

    <script>
        function mostrarObservacoes(button) {
            const cell = button.closest('.observacoes-cell');
            let content = cell.querySelector('.observacoes-content').value || '';
            content = content.replace(/^\s+/, ''); // Remove espaços extras no início
            const modal = document.getElementById('modalObservacoes');
            const modalContent = modal.querySelector('.observacoes-content');
            modalContent.textContent = content;
            modal.style.display = 'block';
        }

        function fecharModal() {
            const modal = document.getElementById('modalObservacoes');
            modal.style.display = 'none';
        }

        // Fechar modal ao clicar fora dele
        window.onclick = function(event) {
            const modal = document.getElementById('modalObservacoes');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
