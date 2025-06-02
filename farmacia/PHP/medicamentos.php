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

$ordem = $_GET['ordem'] ?? 'nome';
$direcao = $_GET['direcao'] ?? 'ASC';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Medicamentos</title>
    <link rel="stylesheet" href="style.css">
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
        <h2>Gerenciamento de Medicamentos</h2>

        <?php if (isset($_GET['sucesso'])): ?>
            <div class="sucesso"><?= htmlspecialchars($_GET['sucesso']) ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['erro'])): ?>
            <div class="erro">Erro: <?= htmlspecialchars($_GET['erro']) ?></div>
        <?php endif; ?>

        <div class="search-container">
            <input type="text" id="searchInput" placeholder="Buscar medicamentos...">
            <button onclick="searchMedicamentos()" class="btn-primary">
                <i class="fas fa-search"></i> Buscar
            </button>
        </div>

        <div class="actions">
            <a href="cadastrar_medicamento.php" class="btn-secondary">+ Novo Medicamento</a>
        </div>

        <div class="table-container">
            <table id="medicamentosTable">
                <thead>
                    <tr>
                        <th class="sortable" data-ordem="nome">Nome</th>
                        <th class="sortable" data-ordem="quantidade">Quantidade</th>
                        <th class="sortable" data-ordem="total_recebido">Total Recebido<br>
                            <?php if(!empty($ultimaImportGlobal['ultima_data'])): ?>
                                <span style="font-weight:normal;font-size:0.95em;">(Última Importação: <?php echo date('d/m/Y H:i', strtotime($ultimaImportGlobal['ultima_data'])); ?>)</span>
                            <?php endif; ?>
                        </th>
                        <th class="sortable" data-ordem="codigo">Código</th>
                        <th class="sortable" data-ordem="lote">Lote</th>
                        <th class="sortable" data-ordem="apresentacao">Apresentação</th>
                        <th class="sortable" data-ordem="validade">Validade</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="medicamentosTableBody">
                    <?php
                    $sql = "SELECT 
                                id,
                                nome,
                                quantidade,
                                codigo,
                                lote,
                                apresentacao,
                                validade,
                                ativo
                            FROM medicamentos
                            ORDER BY $ordem $direcao";
                    $stmt = $pdo->query($sql);
                    
                    while ($medicamento = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
                        <tr>
                            <td><?= htmlspecialchars($medicamento['nome']) ?></td>
                            <td><?php echo calcularEstoqueAtual($pdo, $medicamento['id']); ?></td>
                            <?php $ultimaImport = getTotalUltimaImportacao($pdo, $medicamento['id']); ?>
                            <td>
                                <?= $ultimaImport ? $ultimaImport['total'] : '--' ?>
                            </td>
                            <td><?= htmlspecialchars($medicamento['codigo']) ?></td>
                            <td><?= htmlspecialchars($medicamento['lote']) ?></td>
                            <td><?= htmlspecialchars($medicamento['apresentacao']) ?></td>
                            <td>
                                <?php if (!empty($medicamento['validade']) && $medicamento['validade'] != '0000-00-00'): ?>
                                    <?= date('d/m/Y', strtotime($medicamento['validade'])) ?>
                                <?php else: ?>
                                    --
                                <?php endif; ?>
                            </td>
                            <td class="actions">
                                <div style="display: flex; gap: 6px;">
                                    <a href="editar_medicamento.php?id=<?= $medicamento['id'] ?>" class="btn-secondary btn-small">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($medicamento['ativo']): ?>
                                        <a href="medicamentos.php?inativar=<?= $medicamento['id'] ?>"
                                            class="btn-secondary btn-small"
                                            onclick="return confirm('Tem certeza que deseja inativar este medicamento?')">
                                            <i class="fas fa-power-off"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="medicamentos.php?ativar=<?= $medicamento['id'] ?>"
                                            class="btn-secondary btn-small btn-inativo"
                                            onclick="return confirm('Tem certeza que deseja ativar este medicamento?')">
                                            <i class="fas fa-power-off"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.4);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 5px;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover,
        .close:hover {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }

        .template-download {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }

        .modal ul {
            margin-bottom: 20px;
            padding-left: 20px;
        }

        .modal .form-group {
            margin-bottom: 20px;
        }

        .ultima-importacao {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 10px 15px;
            margin-bottom: 20px;
            font-size: 0.9em;
            color: #666;
        }
        
        .ultima-importacao i {
            color: #0d6efd;
            margin-right: 5px;
        }
        
        .ultima-importacao strong {
            color: #333;
        }

        .btn-inativo {
            background-color: #dc3545 !important;
            border-color: #dc3545 !important;
            color: white !important;
        }

        .btn-inativo:hover {
            background-color: #bb2d3b !important;
            border-color: #b02a37 !important;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }

        tr:hover {
            background-color: #f5f5f5;
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

        <script>
        function showImportModal() {
            document.getElementById('importModal').style.display = 'block';
        }

        function closeImportModal() {
            document.getElementById('importModal').style.display = 'none';
        }

        // Fechar modal quando clicar fora dele
        window.onclick = function(event) {
            var modal = document.getElementById('importModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
        </script>

        <script>
        function searchMedicamentos() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const urlParams = new URLSearchParams(window.location.search);
            const ordem = urlParams.get('ordem') || 'nome';
            const direcao = urlParams.get('direcao') || 'ASC';
            fetch('buscar_medicamentos.php?busca=' + encodeURIComponent(searchInput) + '&ordem=' + ordem + '&direcao=' + direcao)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('medicamentosTableBody').innerHTML = html;
                })
                .catch(error => {
                    console.error('Erro ao buscar medicamentos:', error);
                });
        }

        document.addEventListener('DOMContentLoaded', function() {
            searchMedicamentos();
            // Ordenação ao clicar nos cabeçalhos
            document.querySelectorAll('th.sortable').forEach(th => {
                th.addEventListener('click', function() {
                    const urlParams = new URLSearchParams(window.location.search);
                    const ordemAtual = urlParams.get('ordem') || 'nome';
                    const direcaoAtual = urlParams.get('direcao') || 'ASC';
                    const coluna = th.dataset.ordem;
                    const novaDirecao = (ordemAtual === coluna && direcaoAtual === 'ASC') ? 'DESC' : 'ASC';
                    urlParams.set('ordem', coluna);
                    urlParams.set('direcao', novaDirecao);
                    history.replaceState(null, '', '?' + urlParams.toString());
                    searchMedicamentos();
                    // Atualizar setas visuais
                    document.querySelectorAll('th.sortable').forEach(th2 => th2.classList.remove('asc', 'desc'));
                    th.classList.add(novaDirecao.toLowerCase());
                });
            });
            // Marcar coluna ordenada ao carregar
            const urlParams = new URLSearchParams(window.location.search);
            const ordemAtual = urlParams.get('ordem') || 'nome';
            const direcaoAtual = urlParams.get('direcao') || 'ASC';
            const thAtual = document.querySelector(`th[data-ordem="${ordemAtual}"]`);
            if (thAtual) thAtual.classList.add(direcaoAtual.toLowerCase());
        });
        </script>
    </main>
</body>
</html> 