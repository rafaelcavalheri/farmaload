<?php
require __DIR__ . '/config.php';
verificarAutenticacao(['admin']);

$mensagem = '';
$busca = '';
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

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validarTokenCsrf($_POST['csrf_token'])) {
        die("Token CSRF inválido!");
    }

    // Alternar status (ativar/desativar)
    if (isset($_POST['alternar_status'])) {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $tipo = $_POST['tipo'] ?? '';
        
        if ($id && $tipo) {
            try {
                if ($tipo === 'medico') {
                    $stmt = $pdo->prepare("UPDATE medicos SET ativo = NOT ativo WHERE id = ?");
                    $stmt->execute([$id]);
                } else {
                    // Para instituições, remover o offset do ID
                    $id_real = $id - 10000;
                    $stmt = $pdo->prepare("UPDATE instituicoes SET ativo = NOT ativo WHERE id = ?");
                    $stmt->execute([$id_real]);
                }
                $mensagem = '<div class="alert sucesso">Status alterado com sucesso!</div>';
            } catch (Exception $e) {
                $mensagem = '<div class="alert erro">Erro ao alterar status.</div>';
            }
        }
    }
}

// Busca
$where_medicos = "1=1";
$where_instituicoes = "1=1";
$params = [];

if (isset($_GET['busca'])) {
    $busca = trim($_GET['busca']);
    if (strlen($busca) >= 3) {
        $where_medicos .= " AND (
            m.nome LIKE ? OR 
            m.crm_numero LIKE ? OR 
            m.crm_estado LIKE ? OR
            m.cns LIKE ?
        )";
        $where_instituicoes .= " AND (
            i.nome LIKE ? OR
            i.cnes LIKE ?
        )";
        $params = [
            "%$busca%",
            "%$busca%",
            "%$busca%",
            "%$busca%",
            "%$busca%",
            "%$busca%"
        ];
    } elseif (!empty($busca)) {
        $mensagem = '<div class="alert erro">Digite pelo menos 3 caracteres para buscar.</div>';
    }
}

// Aplicar filtro alfabético
if (!empty($filtro_alfabetico)) {
    $where_medicos .= " AND m.nome LIKE ?";
    $where_instituicoes .= " AND i.nome LIKE ?";
    $params[] = $filtro_alfabetico . "%";
    $params[] = $filtro_alfabetico . "%";
}

// Total de registros
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM (
        SELECT m.id, m.nome, m.crm_numero, m.crm_estado, m.ativo, m.data_cadastro, 'medico' as tipo
        FROM medicos m 
        WHERE $where_medicos
        UNION ALL
        SELECT id + 10000, nome, cnes as crm_numero, '' as crm_estado, ativo, data_cadastro, 'instituicao' as tipo
        FROM instituicoes i
        WHERE $where_instituicoes
    ) as registros
");
$stmt->execute($params);
$total_registros = $stmt->fetchColumn();
$total_paginas = ceil($total_registros / $limite);

// Buscar registros com ordenação
$colunas_ordenacao = [
    'nome' => 'nome',
    'crm' => 'crm_numero',
    'status' => 'ativo',
    'cadastro' => 'data_cadastro'
];

$order_by = isset($colunas_ordenacao[$ordem]) ? $colunas_ordenacao[$ordem] : 'nome';
$order_direction = $direcao === 'DESC' ? 'DESC' : 'ASC';

$stmt = $pdo->prepare("
    SELECT * FROM (
        SELECT m.id, m.nome, m.crm_numero, m.crm_estado, m.ativo, m.data_cadastro, 'medico' as tipo
        FROM medicos m 
        WHERE $where_medicos
        UNION ALL
        SELECT id + 10000, nome, cnes as crm_numero, '' as crm_estado, ativo, data_cadastro, 'instituicao' as tipo
        FROM instituicoes i
        WHERE $where_instituicoes
    ) as registros
    ORDER BY $order_by $order_direction
    LIMIT ? OFFSET ?
");
$params[] = $limite;
$params[] = $offset;
$stmt->execute($params);
$registros = $stmt->fetchAll();

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Médicos - FarmAlto</title>
    <link rel="icon" type="image/png" href="/images/fav.png">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/medicos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <main class="container">
        <!-- Cabeçalho -->
        <div class="page-header">
            <h1><i class="fas fa-user-md"></i> Médicos e Instituições</h1>
            <div class="actions">
                <a href="cadastrar_medico.php" class="btn-secondary">
                    <i class="fas fa-user-md"></i> Novo Médico
                </a>
                <a href="cadastrar_instituicao.php" class="btn-secondary">
                    <i class="fas fa-hospital"></i> Nova Instituição
                </a>
            </div>
        </div>

        <?= $mensagem ?>

        <!-- Formulário de busca -->
        <form method="GET">
            <div class="search-container">
                <input type="text" 
                       name="busca" 
                       value="<?= htmlspecialchars($busca) ?>" 
                       placeholder="Buscar por nome, CRM, CNS, CNES ou instituição..."
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

        <!-- Tabela de médicos -->
        <div class="card">
            <table>
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
                            <a href="<?= gerarUrlPagina($pagina, $limite, $busca, $filtro_alfabetico, 'crm', $ordem === 'crm' && $direcao === 'ASC' ? 'DESC' : 'ASC') ?>" 
                               class="ordenacao <?= $ordem === 'crm' ? 'ativa' : '' ?>">
                                CRM/CNES
                                <?php if ($ordem === 'crm'): ?>
                                    <i class="fas fa-sort-<?= $direcao === 'ASC' ? 'up' : 'down' ?>"></i>
                                <?php else: ?>
                                    <i class="fas fa-sort"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?= gerarUrlPagina($pagina, $limite, $busca, $filtro_alfabetico, 'status', $ordem === 'status' && $direcao === 'ASC' ? 'DESC' : 'ASC') ?>" 
                               class="ordenacao <?= $ordem === 'status' ? 'ativa' : '' ?>">
                                Status
                                <?php if ($ordem === 'status'): ?>
                                    <i class="fas fa-sort-<?= $direcao === 'ASC' ? 'up' : 'down' ?>"></i>
                                <?php else: ?>
                                    <i class="fas fa-sort"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>
                            <a href="<?= gerarUrlPagina($pagina, $limite, $busca, $filtro_alfabetico, 'cadastro', $ordem === 'cadastro' && $direcao === 'ASC' ? 'DESC' : 'ASC') ?>" 
                               class="ordenacao <?= $ordem === 'cadastro' ? 'ativa' : '' ?>">
                                Cadastro
                                <?php if ($ordem === 'cadastro'): ?>
                                    <i class="fas fa-sort-<?= $direcao === 'ASC' ? 'up' : 'down' ?>"></i>
                                <?php else: ?>
                                    <i class="fas fa-sort"></i>
                                <?php endif; ?>
                            </a>
                        </th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($registros as $registro): ?>
                        <tr class="<?= $registro['ativo'] ? '' : 'inativo' ?>">
                            <td>
                                <?= htmlspecialchars($registro['nome']) ?>
                                <?php if ($registro['tipo'] === 'instituicao'): ?>
                                    <span class="badge badge-info">Instituição</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($registro['tipo'] === 'medico'): ?>
                                    <?= htmlspecialchars($registro['crm_numero'] . '/' . $registro['crm_estado']) ?>
                                <?php else: ?>
                                    <?= htmlspecialchars($registro['crm_numero']) ?> (CNES)
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge <?= $registro['ativo'] ? 'ativo' : 'inativo' ?>">
                                    <?= $registro['ativo'] ? 'Ativo' : 'Inativo' ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y', strtotime($registro['data_cadastro'])) ?></td>
                            <td class="actions">
                                <div class="action-buttons">
                                    <?php if ($registro['tipo'] === 'medico'): ?>
                                        <a href="editar_medico.php?id=<?= $registro['id'] ?>" 
                                           class="btn-secondary" 
                                           title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="editar_instituicao.php?id=<?= $registro['id'] - 10000 ?>" 
                                           class="btn-secondary" 
                                           title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    <?php endif; ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?= gerarTokenCsrf() ?>">
                                        <input type="hidden" name="id" value="<?= $registro['id'] ?>">
                                        <input type="hidden" name="tipo" value="<?= $registro['tipo'] ?>">
                                        <button type="submit" 
                                                name="alternar_status" 
                                                class="btn-secondary" 
                                                title="<?= $registro['ativo'] ? 'Desativar' : 'Ativar' ?>"
                                                onclick="return confirm('Tem certeza que deseja <?= $registro['ativo'] ? 'desativar' : 'ativar' ?> este registro?')">
                                            <i class="fas fa-<?= $registro['ativo'] ? 'power-off' : 'check' ?>"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($registros)): ?>
                        <tr>
                            <td colspan="5" class="text-center">Nenhum registro encontrado.</td>
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
    </main>

    <?php include 'footer.php'; ?>

    <script>
        function alterarLimite(novoLimite) {
            const url = new URL(window.location);
            url.searchParams.set('limite', novoLimite);
            url.searchParams.delete('pagina'); // Voltar para primeira página
            window.location.href = url.toString();
        }
    </script>
</body>
</html> 