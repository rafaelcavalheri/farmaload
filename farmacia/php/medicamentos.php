<?php
include 'config.php';
include 'funcoes_estoque.php';

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}

if ($_SESSION['usuario']['perfil'] !== 'admin') {
    die("Acesso negado! Apenas administradores podem acessar esta página.");
}

// Verificar e criar coluna 'ativo' se não existir
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM medicamentos LIKE 'ativo'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE medicamentos ADD COLUMN ativo TINYINT(1) DEFAULT 1");
    }
} catch (PDOException $e) {
    die("Erro ao verificar/criar coluna 'ativo': " . $e->getMessage());
}

if (isset($_GET['inativar'])) {
    try {
        $stmt = $pdo->prepare("UPDATE medicamentos SET ativo = 0 WHERE id = ?");
        $stmt->execute([$_GET['inativar']]);
        header('Location: medicamentos.php?sucesso=Medicamento inativado com sucesso');
        exit();
    } catch (PDOException $e) {
        header('Location: medicamentos.php?erro=' . urlencode($e->getMessage()));
        exit();
    }
}

if (isset($_GET['ativar'])) {
    try {
        $stmt = $pdo->prepare("UPDATE medicamentos SET ativo = 1 WHERE id = ?");
        $stmt->execute([$_GET['ativar']]);
        header('Location: medicamentos.php?sucesso=Medicamento ativado com sucesso');
        exit();
    } catch (PDOException $e) {
        header('Location: medicamentos.php?erro=' . urlencode($e->getMessage()));
        exit();
    }
}

// Buscar a última data/hora de importação global
$stmtUltimaImport = $pdo->query("SELECT MAX(data) as ultima_data FROM movimentacoes WHERE tipo = 'IMPORTACAO'");
$ultimaImportGlobal = $stmtUltimaImport->fetch(PDO::FETCH_ASSOC);

$busca = $_GET['busca'] ?? '';
$filtro_alfabetico = $_GET['filtro_alfabetico'] ?? '';
$ordem = $_GET['ordem'] ?? 'nome';
$direcao = $_GET['direcao'] ?? 'ASC';

// Configuração de paginação
$limite_padrao = 100;
$limite = isset($_GET['limite']) ? intval($_GET['limite']) : $limite_padrao;
$pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$offset = ($pagina - 1) * $limite;

// Opções de limite disponíveis
$opcoes_limite = [100, 200, 300, 500, 1000];

// Construir query base
$sql = "SELECT 
            m.id,
            m.nome,
            m.codigo,
            m.apresentacao,
            m.ativo
        FROM medicamentos m";

$params = [];
$where_conditions = [];

// Aplicar busca
if (!empty($busca)) {
    $where_conditions[] = "(m.nome LIKE ? OR m.codigo LIKE ? OR EXISTS (SELECT 1 FROM lotes_medicamentos lm2 WHERE lm2.medicamento_id = m.id AND lm2.lote LIKE ?))";
    $params = array_merge($params, array_fill(0, 3, "%$busca%"));
}

// Aplicar filtro alfabético
if (!empty($filtro_alfabetico)) {
    $where_conditions[] = "m.nome LIKE ?";
    $params[] = $filtro_alfabetico . "%";
}

if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

// Adicionar ordenação
$colunas_ordenacao = [
    'nome' => 'm.nome',
    'quantidade' => null, // será tratado manualmente
    'total_recebido' => null, // será tratado manualmente
    'codigo' => 'm.codigo',
    'lote' => null, // será tratado manualmente
    'apresentacao' => 'm.apresentacao',
    'validade' => null // será tratado manualmente
];

// Tratar ordenações especiais que precisam de subqueries
if ($ordem === 'quantidade') {
    // Ordenar por quantidade (estoque atual)
    $sql = "SELECT 
                m.id,
                m.nome,
                m.codigo,
                m.apresentacao,
                m.ativo,
                COALESCE(SUM(lm.quantidade), 0) as estoque_atual
            FROM medicamentos m
            LEFT JOIN lotes_medicamentos lm ON m.id = lm.medicamento_id";
    
    if (!empty($where_conditions)) {
        $sql .= " WHERE " . implode(" AND ", $where_conditions);
    }
    
    $sql .= " GROUP BY m.id, m.nome, m.codigo, m.apresentacao, m.ativo";
    $sql .= " ORDER BY estoque_atual " . ($direcao === 'DESC' ? 'DESC' : 'ASC');
} elseif ($ordem === 'total_recebido') {
    // Ordenar por total recebido (última importação)
    $sql = "SELECT 
                m.id,
                m.nome,
                m.codigo,
                m.apresentacao,
                m.ativo,
                (SELECT quantidade FROM movimentacoes 
                 WHERE medicamento_id = m.id AND tipo = 'IMPORTACAO' 
                 ORDER BY data DESC LIMIT 1) as ultima_importacao
            FROM medicamentos m";
    
    if (!empty($where_conditions)) {
        $sql .= " WHERE " . implode(" AND ", $where_conditions);
    }
    
    $sql .= " ORDER BY ultima_importacao " . ($direcao === 'DESC' ? 'DESC' : 'ASC') . ", m.nome ASC";
} elseif ($ordem === 'lote') {
    // Ordenar por lote (primeiro lote disponível)
    $sql = "SELECT 
                m.id,
                m.nome,
                m.codigo,
                m.apresentacao,
                m.ativo,
                (SELECT lote FROM lotes_medicamentos 
                 WHERE medicamento_id = m.id AND quantidade > 0 
                 ORDER BY validade ASC, id ASC LIMIT 1) as primeiro_lote
            FROM medicamentos m";
    
    if (!empty($where_conditions)) {
        $sql .= " WHERE " . implode(" AND ", $where_conditions);
    }
    
    $sql .= " ORDER BY primeiro_lote " . ($direcao === 'DESC' ? 'DESC' : 'ASC') . ", m.nome ASC";
} elseif ($ordem === 'validade') {
    // Ordenar por validade (primeira validade disponível)
    $sql = "SELECT 
                m.id,
                m.nome,
                m.codigo,
                m.apresentacao,
                m.ativo,
                (SELECT validade FROM lotes_medicamentos 
                 WHERE medicamento_id = m.id AND quantidade > 0 
                 ORDER BY validade ASC, id ASC LIMIT 1) as primeira_validade
            FROM medicamentos m";
    
    if (!empty($where_conditions)) {
        $sql .= " WHERE " . implode(" AND ", $where_conditions);
    }
    
    $sql .= " ORDER BY primeira_validade " . ($direcao === 'DESC' ? 'DESC' : 'ASC') . ", m.nome ASC";
} elseif (isset($colunas_ordenacao[$ordem]) && $colunas_ordenacao[$ordem] !== null) {
    $sql .= " ORDER BY " . $colunas_ordenacao[$ordem] . " " . ($direcao === 'DESC' ? 'DESC' : 'ASC');
} else {
    $sql .= " ORDER BY m.nome ASC";
}

// Adicionar LIMIT e OFFSET para paginação
$sql .= " LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($sql);
// Adicionar os parâmetros de paginação ao array de parâmetros
$params[] = $limite;
$params[] = $offset;

try {
    $stmt->execute($params);
} catch (PDOException $e) {
    // Log do erro para debug
    error_log("Erro na query de medicamentos: " . $e->getMessage());
    error_log("SQL: " . $sql);
    error_log("Parâmetros: " . print_r($params, true));
    die("Erro ao executar a consulta. Verifique os logs para mais detalhes.");
}

// Query para contar total de registros (sem LIMIT)
// Usar a mesma lógica de WHERE da query principal para manter consistência
$sql_count = "SELECT COUNT(DISTINCT m.id) as total FROM medicamentos m";
$params_count = [];
$where_conditions_count = [];

if (!empty($busca)) {
    $where_conditions_count[] = "(m.nome LIKE ? OR m.codigo LIKE ? OR EXISTS (SELECT 1 FROM lotes_medicamentos lm2 WHERE lm2.medicamento_id = m.id AND lm2.lote LIKE ?))";
    $params_count = array_merge($params_count, array_fill(0, 3, "%$busca%"));
}

if (!empty($filtro_alfabetico)) {
    $where_conditions_count[] = "m.nome LIKE ?";
    $params_count[] = $filtro_alfabetico . "%";
}

if (!empty($where_conditions_count)) {
    $sql_count .= " WHERE " . implode(" AND ", $where_conditions_count);
}

// Executar a query de contagem
try {
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params_count);
    $total_registros = $stmt_count->fetch()['total'];
    $total_paginas = ceil($total_registros / $limite);
} catch (PDOException $e) {
    // Log do erro para debug
    error_log("Erro na query de contagem: " . $e->getMessage());
    error_log("SQL Count: " . $sql_count);
    error_log("Parâmetros Count: " . print_r($params_count, true));
    die("Erro ao executar a consulta de contagem. Verifique os logs para mais detalhes.");
}

// Função para gerar URL com filtros
function gerarUrlFiltro($letra, $limite, $busca, $ordem, $direcao) {
    $params = [];
    if ($letra !== 'todas') {
        $params[] = 'filtro_alfabetico=' . urlencode($letra);
    }
    if ($limite != 100) {
        $params[] = 'limite=' . $limite;
    }
    if (!empty($busca)) {
        $params[] = 'busca=' . urlencode($busca);
    }
    if ($ordem !== 'nome') {
        $params[] = 'ordem=' . urlencode($ordem);
    }
    if ($direcao !== 'ASC') {
        $params[] = 'direcao=' . urlencode($direcao);
    }
    return '?' . implode('&', $params);
}

// Função para gerar URL de paginação
function gerarUrlPagina($pagina, $limite, $busca, $filtro_alfabetico, $ordem, $direcao) {
    $params = ['pagina=' . $pagina];
    if ($limite != 100) {
        $params[] = 'limite=' . $limite;
    }
    if (!empty($busca)) {
        $params[] = 'busca=' . urlencode($busca);
    }
    if (!empty($filtro_alfabetico)) {
        $params[] = 'filtro_alfabetico=' . urlencode($filtro_alfabetico);
    }
    if ($ordem !== 'nome') {
        $params[] = 'ordem=' . urlencode($ordem);
    }
    if ($direcao !== 'ASC') {
        $params[] = 'direcao=' . urlencode($direcao);
    }
    return '?' . implode('&', $params);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Medicamentos</title>
    <link rel="icon" type="image/png" href="/images/fav.png">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/medicamentos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const hoje = new Date();
            hoje.setHours(0, 0, 0, 0);

            document.querySelectorAll('tbody tr').forEach(tr => {
                const dataTd = tr.querySelector('td:nth-child(8)');
                if (dataTd && dataTd.textContent !== '--') {
                    const partes = dataTd.textContent.split('/');
                    const dataValidade = new Date(partes[2], partes[1] - 1, partes[0]);

                    if (dataValidade < hoje) {
                        tr.classList.add('vencido');
                        dataTd.innerHTML += ' <span class="vencido-badge">(Vencido)</span>';
                    }
                }
            });
        });
    </script>
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="container">
        <!-- Cabeçalho -->
        <div class="page-header">
            <h1><i class="fas fa-pills"></i> Medicamentos</h1>
            <div class="actions">
                <a href="cadastrar_medicamento.php" class="btn-secondary">
                    <i class="fas fa-pills"></i> Cadastrar Medicamento
                </a>
            </div>
        </div>

        <?php if (isset($_GET['sucesso'])): ?>
            <div class="sucesso"><?= htmlspecialchars($_GET['sucesso']) ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['erro'])): ?>
            <div class="erro">Erro: <?= htmlspecialchars($_GET['erro']) ?></div>
        <?php endif; ?>

        <!-- Formulário de busca -->
        <form method="GET">
            <div class="search-container">
                <input type="text" 
                       name="busca" 
                       value="<?= htmlspecialchars($busca) ?>" 
                       placeholder="Buscar medicamentos..."
                       minlength="3">
                <button type="submit" class="btn-secondary">
                    <i class="fas fa-search"></i> Buscar
                </button>
            </div>
        </form>

        <!-- Filtro Alfabético -->
        <div class="filtro-alfabetico">
            <a href="<?= gerarUrlFiltro('todas', $limite, $busca, $ordem, $direcao) ?>" 
               class="<?= empty($filtro_alfabetico) ? 'letra-ativa' : '' ?> todas">
                Todas
            </a>
            <?php foreach (range('A', 'Z') as $letra): ?>
                <a href="<?= gerarUrlFiltro($letra, $limite, $busca, $ordem, $direcao) ?>" 
                   class="<?= $filtro_alfabetico === $letra ? 'letra-ativa' : '' ?>">
                    <?= $letra ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Informações de paginação -->
        <div class="paginacao-info">
            <div class="info-registros">
                Total: <strong><?= number_format($total_registros, 0, ',', '.') ?></strong> registros
                <?php if ($total_registros > 0): ?>
                    (<?= number_format(($offset + 1), 0, ',', '.') ?> a <?= number_format(min($offset + $limite, $total_registros), 0, ',', '.') ?>)
                <?php endif; ?>
            </div>
            <div class="seletor-limite">
                <label for="limite">Mostrar:</label>
                <select id="limite" onchange="alterarLimite(this.value)">
                    <?php foreach ($opcoes_limite as $opcao): ?>
                        <option value="<?= $opcao ?>" <?= $limite == $opcao ? 'selected' : '' ?>>
                            <?= $opcao ?> por página
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="card">
            <table id="medicamentosTable">
                <thead>
                    <tr>
                        <th>
                            <a href="<?= gerarUrlPagina($pagina, $limite, $busca, $filtro_alfabetico, 'nome', $ordem === 'nome' && $direcao === 'ASC' ? 'DESC' : 'ASC') ?>" 
                               class="ordenacao <?= $ordem === 'nome' ? 'ativa' : '' ?>">
                                Nome
                                <?php if ($ordem === 'nome'): ?>
                                    <i class="fas fa-sort-<?= $direcao === 'ASC' ? 'up' : 'down' ?>"></i>
                                <?php else: ?>
                                    <i class="fas fa-sort"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?= gerarUrlPagina($pagina, $limite, $busca, $filtro_alfabetico, 'quantidade', $ordem === 'quantidade' && $direcao === 'ASC' ? 'DESC' : 'ASC') ?>" 
                               class="ordenacao <?= $ordem === 'quantidade' ? 'ativa' : '' ?>">
                                Quantidade
                                <?php if ($ordem === 'quantidade'): ?>
                                    <i class="fas fa-sort-<?= $direcao === 'ASC' ? 'up' : 'down' ?>"></i>
                                <?php else: ?>
                                    <i class="fas fa-sort"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?= gerarUrlPagina($pagina, $limite, $busca, $filtro_alfabetico, 'total_recebido', $ordem === 'total_recebido' && $direcao === 'ASC' ? 'DESC' : 'ASC') ?>" 
                               class="ordenacao <?= $ordem === 'total_recebido' ? 'ativa' : '' ?>">
                                <div class="header-content">
                                    <div class="header-title">
                                        Total Recebido
                                        <?php if ($ordem === 'total_recebido'): ?>
                                            <i class="fas fa-sort-<?= $direcao === 'ASC' ? 'up' : 'down' ?>"></i>
                                        <?php else: ?>
                                            <i class="fas fa-sort"></i>
                                        <?php endif; ?>
                                    </div>
                                    <?php if(!empty($ultimaImportGlobal['ultima_data'])): ?>
                                        <div class="header-subtitle">Última Importação: <?php echo date('d/m/Y H:i', strtotime($ultimaImportGlobal['ultima_data'])); ?></div>
                                    <?php endif; ?>
                                </div>
                            </a>
                        </th>
                        <th>
                            <a href="<?= gerarUrlPagina($pagina, $limite, $busca, $filtro_alfabetico, 'codigo', $ordem === 'codigo' && $direcao === 'ASC' ? 'DESC' : 'ASC') ?>" 
                               class="ordenacao <?= $ordem === 'codigo' ? 'ativa' : '' ?>">
                                Código
                                <?php if ($ordem === 'codigo'): ?>
                                    <i class="fas fa-sort-<?= $direcao === 'ASC' ? 'up' : 'down' ?>"></i>
                                <?php else: ?>
                                    <i class="fas fa-sort"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?= gerarUrlPagina($pagina, $limite, $busca, $filtro_alfabetico, 'lote', $ordem === 'lote' && $direcao === 'ASC' ? 'DESC' : 'ASC') ?>" 
                               class="ordenacao <?= $ordem === 'lote' ? 'ativa' : '' ?>">
                                Lote/Qtd/Validade
                                <?php if ($ordem === 'lote'): ?>
                                    <i class="fas fa-sort-<?= $direcao === 'ASC' ? 'up' : 'down' ?>"></i>
                                <?php else: ?>
                                    <i class="fas fa-sort"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?= gerarUrlPagina($pagina, $limite, $busca, $filtro_alfabetico, 'apresentacao', $ordem === 'apresentacao' && $direcao === 'ASC' ? 'DESC' : 'ASC') ?>" 
                               class="ordenacao <?= $ordem === 'apresentacao' ? 'ativa' : '' ?>">
                                Apresentação
                                <?php if ($ordem === 'apresentacao'): ?>
                                    <i class="fas fa-sort-<?= $direcao === 'ASC' ? 'up' : 'down' ?>"></i>
                                <?php else: ?>
                                    <i class="fas fa-sort"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="medicamentosTableBody">
                    <?php while ($medicamento = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                        <tr data-id="<?= $medicamento['id'] ?>">
                            <td>
                                <?= htmlspecialchars($medicamento['nome']) ?>
                            </td>
                            <td><?php echo calcularEstoqueAtual($pdo, $medicamento['id']); ?></td>
                            <?php $ultimaImport = getTotalUltimaImportacao($pdo, $medicamento['id']); ?>
                            <td>
                                <?= $ultimaImport ? $ultimaImport['total'] : '--' ?>
                            </td>
                            <td><?= htmlspecialchars($medicamento['codigo']) ?></td>
                            <td>
                                <?php
                                // Buscar lotes ativos com validade
                                $stmtLotes = $pdo->prepare("
                                    SELECT lote, validade, quantidade 
                                    FROM lotes_medicamentos 
                                    WHERE medicamento_id = ? AND quantidade > 0 
                                    ORDER BY validade ASC
                                ");
                                $stmtLotes->execute([$medicamento['id']]);
                                $lotes = $stmtLotes->fetchAll(PDO::FETCH_ASSOC);
                                
                                if (!empty($lotes)) {
                                    if (count($lotes) == 1) {
                                        // Se há apenas um lote, mostrar diretamente
                                        $lote = $lotes[0];
                                        echo '<div class="lote-single">';
                                        echo '<strong>' . htmlspecialchars($lote['lote']) . '</strong><br>';
                                        echo '<span class="lote-info">' . $lote['quantidade'] . ' un - ';
                                        echo ($lote['validade'] && $lote['validade'] != '0000-00-00') ? date('d/m/Y', strtotime($lote['validade'])) : '--';
                                        echo '</span>';
                                        echo '</div>';
                                    } else {
                                        // Se há múltiplos lotes, mostrar o primeiro e um botão para expandir
                                        $primeiroLote = $lotes[0];
                                        echo '<div class="lotes-container">';
                                        echo '<div class="lote-principal">';
                                        echo '<strong>' . htmlspecialchars($primeiroLote['lote']) . '</strong><br>';
                                        echo '<span class="lote-info">' . $primeiroLote['quantidade'] . ' un - ';
                                        echo ($primeiroLote['validade'] && $primeiroLote['validade'] != '0000-00-00') ? date('d/m/Y', strtotime($primeiroLote['validade'])) : '--';
                                        echo '</span>';
                                        echo '</div>';
                                        echo '<button class="btn-lotes-toggle" onclick="toggleLotes(' . $medicamento['id'] . ')">';
                                        echo '<i class="fas fa-chevron-down"></i> Ver mais (' . (count($lotes) - 1) . ')';
                                        echo '</button>';
                                        echo '<div class="lotes-adicionais" id="lotes-' . $medicamento['id'] . '" style="display:none;">';
                                        for ($i = 1; $i < count($lotes); $i++) {
                                            $lote = $lotes[$i];
                                            echo '<div class="lote-adicional">';
                                            echo '<strong>' . htmlspecialchars($lote['lote']) . '</strong><br>';
                                            echo '<span class="lote-info">' . $lote['quantidade'] . ' un - ';
                                            echo ($lote['validade'] && $lote['validade'] != '0000-00-00') ? date('d/m/Y', strtotime($lote['validade'])) : '--';
                                            echo '</span>';
                                            echo '</div>';
                                        }
                                        echo '</div>';
                                        echo '</div>';
                                    }
                                } else {
                                    echo "--";
                                }
                                ?>
                            </td>
                            <td><?= htmlspecialchars($medicamento['apresentacao']) ?></td>
                            <td class="actions">
                                <div class="action-buttons">
                                    <a href="editar_medicamento.php?id=<?= $medicamento['id'] ?>" 
                                       class="btn-secondary" 
                                       title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($medicamento['ativo']): ?>
                                        <a href="medicamentos.php?inativar=<?= $medicamento['id'] ?>"
                                            class="btn-secondary"
                                            title="Desativar"
                                            onclick="return confirm('Tem certeza que deseja inativar este medicamento?')">
                                            <i class="fas fa-power-off"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="medicamentos.php?ativar=<?= $medicamento['id'] ?>"
                                            class="btn-secondary"
                                            title="Ativar"
                                            onclick="return confirm('Tem certeza que deseja ativar este medicamento?')">
                                            <i class="fas fa-power-off"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    
                    <?php if ($total_registros == 0): ?>
                        <tr>
                            <td colspan="7" class="text-center">Nenhum medicamento encontrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginação -->
        <?php if ($total_paginas > 1): ?>
            <div class="paginacao">
                <?php if ($pagina > 1): ?>
                    <a href="<?= gerarUrlPagina(1, $limite, $busca, $filtro_alfabetico, $ordem, $direcao) ?>" class="btn-pagina">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    <a href="<?= gerarUrlPagina($pagina - 1, $limite, $busca, $filtro_alfabetico, $ordem, $direcao) ?>" class="btn-pagina">
                        <i class="fas fa-angle-left"></i>
                    </a>
                <?php endif; ?>

                <?php
                $inicio = max(1, $pagina - 2);
                $fim = min($total_paginas, $pagina + 2);
                
                if ($inicio > 1): ?>
                    <span class="paginacao-ellipsis">...</span>
                <?php endif;
                
                for ($i = $inicio; $i <= $fim; $i++): ?>
                    <a href="<?= gerarUrlPagina($i, $limite, $busca, $filtro_alfabetico, $ordem, $direcao) ?>" 
                       class="btn-pagina <?= $i === $pagina ? 'ativo' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; 
                
                if ($fim < $total_paginas): ?>
                    <span class="paginacao-ellipsis">...</span>
                <?php endif; ?>

                <?php if ($pagina < $total_paginas): ?>
                    <a href="<?= gerarUrlPagina($pagina + 1, $limite, $busca, $filtro_alfabetico, $ordem, $direcao) ?>" class="btn-pagina">
                        <i class="fas fa-angle-right"></i>
                    </a>
                    <a href="<?= gerarUrlPagina($total_paginas, $limite, $busca, $filtro_alfabetico, $ordem, $direcao) ?>" class="btn-pagina">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <script>
        function alterarLimite(novoLimite) {
            const url = new URL(window.location);
            url.searchParams.set('limite', novoLimite);
            url.searchParams.delete('pagina'); // Voltar para primeira página
            window.location.href = url.toString();
        }

        function toggleLotes(medicamentoId) {
            const lotesContainer = document.getElementById('lotes-' + medicamentoId);
            const btnToggle = event.target.closest('.btn-lotes-toggle');
            const icon = btnToggle.querySelector('i');
            
            if (lotesContainer.style.display === 'none') {
                lotesContainer.style.display = 'block';
                icon.className = 'fas fa-chevron-up';
                const currentText = btnToggle.innerHTML;
                btnToggle.innerHTML = currentText.replace('Ver mais', 'Ver menos');
            } else {
                lotesContainer.style.display = 'none';
                icon.className = 'fas fa-chevron-down';
                const currentText = btnToggle.innerHTML;
                btnToggle.innerHTML = currentText.replace('Ver menos', 'Ver mais');
            }
        }
        </script>
    </main>
</body>
</html> 