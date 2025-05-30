<?php
include 'config.php';

verificarAutenticacao(['admin']);

// Validação das datas
$data_inicio = new DateTime();
$data_fim = new DateTime();
try {
    $data_inicio = new DateTime($_GET['data_inicio'] ?? 'first day of this month');
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
    $sql = "SELECT id, nome, cpf, telefone, validade, renovado FROM pacientes WHERE ativo = 1";
    $params = [];
    // Filtro de status
    if (!empty($status_paciente)) {
        if ($status_paciente === 'vencido') {
            $sql .= " AND validade < CURDATE() AND renovado = 0";
        } elseif ($status_paciente === 'a_vencer') {
            $sql .= " AND validade >= CURDATE() AND validade <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND renovado = 0";
        } elseif ($status_paciente === 'renovado') {
            $sql .= " AND renovado = 1";
        }
    }
    $sql .= " ORDER BY nome";
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
    <link rel="stylesheet" href="style.css">
    
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
                                    <td><?= htmlspecialchars($dispensa['observacoes'] ?? '') ?></td>
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
                                <th>Validade</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($resultados_pacientes as $pac): ?>
                                <?php
                                    $hoje = new DateTime();
                                    $validade = $pac['validade'] ? new DateTime($pac['validade']) : null;
                                    $status = '';
                                    if ($pac['renovado']) {
                                        $status = 'Renovado';
                                    } elseif ($validade) {
                                        if ($validade < $hoje) {
                                            $status = 'Vencido';
                                        } elseif ($validade <= (clone $hoje)->modify('+30 days')) {
                                            $status = 'A vencer';
                                        } else {
                                            $status = 'Válido';
                                        }
                                    } else {
                                        $status = 'Sem validade';
                                    }
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($pac['nome']) ?></td>
                                    <td><?= htmlspecialchars($pac['cpf']) ?></td>
                                    <td><?= htmlspecialchars($pac['telefone']) ?></td>
                                    <td><?= $validade ? $validade->format('d/m/Y') : '-' ?></td>
                                    <td><?= $status ?></td>
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
        table td:last-child {
            max-width: 300px;
            min-width: 200px;
            white-space: pre-wrap;
            overflow-x: auto;
            padding: 8px;
        }

        /* Estiliza a barra de rolagem */
        table td:last-child::-webkit-scrollbar {
            height: 8px;
        }

        table td:last-child::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        table td:last-child::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        table td:last-child::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        /* Garante que o texto quebre em palavras longas */
        table td:last-child {
            word-wrap: break-word;
            word-break: break-word;
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
        /* A última coluna (Observações) já está configurada acima */
    </style>
</body>
</html>
