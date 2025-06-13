<?php
include 'config.php';

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}

if ($_SESSION['usuario']['perfil'] !== 'admin') {
    die("Acesso negado! Apenas administradores podem acessar esta página.");
}

// Função para gerar backup do banco de dados
function gerarBackup($pdo, $tipo_backup = 'completo', $tabelas_especificas = []) {
    $backup = "";
    
    // Adicionar comandos para desabilitar foreign keys
    $backup .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
    
    // Função auxiliar para obter colunas não geradas
    function getNonGeneratedColumns($pdo, $table) {
        $columns = [];
        $stmt = $pdo->query("SHOW FULL COLUMNS FROM `$table`");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (stripos($row['Extra'], 'VIRTUAL') === false && stripos($row['Extra'], 'STORED') === false) {
                $columns[] = "`" . $row['Field'] . "`";
            }
        }
        return $columns;
    }
    
    // Definir tabelas baseadas no tipo de backup
    $tabelas = [];
    switch ($tipo_backup) {
        case 'relatorios':
            // Incluindo tabelas relacionadas para manter a integridade dos dados
            $tabelas = [
                'transacoes',
                'pacientes',
                'medicamentos',
                'usuarios',
                'lotes_medicamentos',  // Necessário para quantidade de medicamentos
                'movimentacoes'        // Necessário para histórico de movimentações
            ];
            break;
        case 'pacientes':
            $tabelas = ['pacientes', 'paciente_medicamentos', 'pessoas_autorizadas'];
            break;
        case 'medicamentos':
            $tabelas = ['medicamentos', 'lotes_medicamentos', 'movimentacoes'];
            break;
        case 'personalizado':
            $tabelas = $tabelas_especificas;
            break;
        case 'completo':
        default:
            $tabelas = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            break;
    }
    
    foreach ($tabelas as $table) {
        $backup .= "DROP TABLE IF EXISTS `$table`;\n";
        
        $createTable = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        $backup .= $createTable['Create Table'] . ";\n\n";
        
        // Obter apenas colunas não geradas
        $columns = getNonGeneratedColumns($pdo, $table);
        $columnsStr = implode(', ', $columns);
        
        // Usar apenas colunas não geradas no SELECT
        $rows = $pdo->query("SELECT $columnsStr FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($rows as $row) {
            $values = array_map(function($value) use ($pdo) {
                if ($value === null) return 'NULL';
                return $pdo->quote($value);
            }, $row);
            
            // Especificar colunas no INSERT
            $backup .= "INSERT INTO `$table` ($columnsStr) VALUES (" . implode(', ', $values) . ");\n";
        }
        $backup .= "\n";
    }
    
    // Adicionar comando para reabilitar foreign keys
    $backup .= "SET FOREIGN_KEY_CHECKS=1;\n";
    
    return $backup;
}

// Processar backup
if (isset($_POST['backup'])) {
    $tipo_backup = $_POST['tipo_backup'] ?? 'completo';
    $tabelas_especificas = $_POST['tabelas_especificas'] ?? [];
    
    $backup = gerarBackup($pdo, $tipo_backup, $tabelas_especificas);
    $filename = 'backup_' . $tipo_backup . '_' . date('Y-m-d_H-i-s') . '.sql';
    
    // Enviar headers para download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($backup));
    
    // Limpar qualquer saída anterior
    ob_clean();
    flush();
    
    // Enviar o conteúdo
    echo $backup;
    
    // Encerrar a execução
    exit();
}

// Processar restauração
$mensagem = '';
if (isset($_POST['restore']) && isset($_FILES['sql_file'])) {
    try {
        $sql = file_get_contents($_FILES['sql_file']['tmp_name']);
        
        // Desabilitar foreign keys antes da restauração
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        
        // Executar o SQL do backup
        $pdo->exec($sql);
        
        // Reabilitar foreign keys após a restauração
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
        
        $mensagem = '<div class="alert sucesso">Backup restaurado com sucesso!</div>';
    } catch (Exception $e) {
        // Garantir que as foreign keys sejam reabilitadas mesmo em caso de erro
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
        $mensagem = '<div class="alert erro">Erro ao restaurar backup: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}

// Função para obter a versão do sistema
function getSystemVersion() {
    require_once __DIR__ . '/version.php';
    return SYSTEM_VERSION;
}

// Processar importação de dados
if (isset($_POST['import']) && isset($_FILES['arquivo'])) {
    try {
        require_once 'processar_importacao_automatica.php';
        $mensagem = '<div class="alert sucesso">Dados importados com sucesso!</div>';
    } catch (Exception $e) {
        $mensagem = '<div class="alert erro">Erro ao importar dados: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Dados</title>
    <link rel="icon" type="image/png" href="/images/fav.png">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="container">
        <h2><i class="fas fa-database"></i> Gerenciamento de Dados</h2>

        <?= $mensagem ?>

        <div class="card-grid">
            <!-- Backup do Banco -->
            <div class="card">
                <h3><i class="fas fa-download"></i> Backup do Banco</h3>
                <p>Gere um arquivo de backup do banco de dados.</p>
                <form method="POST">
                    <div class="form-group">
                        <label for="tipo_backup">Tipo de Backup:</label>
                        <select id="tipo_backup" name="tipo_backup" onchange="toggleTabelasEspecificas()">
                            <option value="completo">Backup Completo</option>
                            <option value="relatorios">Apenas Relatórios (inclui dados de estoque)</option>
                            <option value="pacientes">Apenas Pacientes</option>
                            <option value="medicamentos">Apenas Medicamentos</option>
                            <option value="personalizado">Personalizado</option>
                        </select>
                    </div>
                    
                    <div class="info-box">
                        <p><i class="fas fa-info-circle"></i> <strong>Nota:</strong> O backup de relatórios inclui automaticamente as tabelas relacionadas ao estoque para manter a integridade dos dados.</p>
                    </div>
                    
                    <div id="tabelasEspecificas" style="display: none;">
                        <div class="form-group">
                            <label>Tabelas para Backup:</label>
                            <div class="checkbox-group">
                                <?php
                                $tabelas = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                                foreach ($tabelas as $tabela) {
                                    echo "<label class='checkbox-label'>";
                                    echo "<input type='checkbox' name='tabelas_especificas[]' value='$tabela'>";
                                    echo htmlspecialchars($tabela);
                                    echo "</label>";
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="backup" class="btn-primary">
                        <i class="fas fa-download"></i> Gerar Backup
                    </button>
                </form>
            </div>

            <!-- Restauração do Banco -->
            <div class="card">
                <h3><i class="fas fa-upload"></i> Restaurar Backup</h3>
                <p>Restaure um backup anterior do banco de dados.</p>
                <form method="POST" enctype="multipart/form-data" id="restoreForm">
                    <div class="form-group">
                        <label for="sql_file">Arquivo SQL:</label>
                        <input type="file" id="sql_file" name="sql_file" accept=".sql" required>
                    </div>
                    <button type="submit" name="restore" class="btn-primary" 
                            onclick="return confirm('ATENÇÃO: Esta ação irá substituir todos os dados atuais. Deseja continuar?')">
                        <i class="fas fa-upload"></i> Restaurar Backup
                    </button>
                </form>
            </div>

            <!-- Importação de Dados -->
            <div class="card">
                <h3><i class="fas fa-file-import"></i> Importar Dados</h3>
                <p>Importe dados de medicamentos e pacientes através de planilha.</p>
                <form method="POST" enctype="multipart/form-data" id="importForm">
                    <div class="form-group">
                        <label for="arquivo">Arquivo Excel:</label>
                        <input type="file" id="arquivo" name="arquivo" accept=".xlsx,.xls" required>
                    </div>
                    <div class="button-group">
                        <button type="submit" name="import" class="btn-primary">
                            <i class="fas fa-file-import"></i> Importar Dados
                        </button>
                        <a href="gerar_modelo_importacao.php" class="btn-action">
                            <i class="fas fa-download"></i> Baixar Modelo
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Informações do Sistema -->
        <div class="card mt-4">
            <h3><i class="fas fa-info-circle"></i> Informações do Sistema</h3>
            <div class="info-grid">
                <?php
                // Contar pacientes
                $stmt = $pdo->query("SELECT COUNT(*) FROM pacientes");
                $total_pacientes = $stmt->fetchColumn();

                // Contar medicamentos
                $stmt = $pdo->query("SELECT COUNT(*) FROM medicamentos");
                $total_medicamentos = $stmt->fetchColumn();

                // Buscar informação da última importação
                $stmt = $pdo->query("SELECT usuario_nome, data_hora, quantidade_registros, arquivo_nome 
                                    FROM logs_importacao 
                                    ORDER BY data_hora DESC 
                                    LIMIT 1");
                $ultimaImportacao = $stmt->fetch(PDO::FETCH_ASSOC);

                // Obter versão do sistema
                $versao_sistema = getSystemVersion();
                ?>
                <div class="info-item">
                    <strong>Versão do Sistema:</strong>
                    <span><?= htmlspecialchars($versao_sistema) ?></span>
                </div>
                <div class="info-item">
                    <strong>Total de Pacientes:</strong>
                    <span><?= number_format($total_pacientes, 0, ',', '.') ?></span>
                </div>
                <div class="info-item">
                    <strong>Total de Medicamentos:</strong>
                    <span><?= number_format($total_medicamentos, 0, ',', '.') ?></span>
                </div>
                <?php if ($ultimaImportacao): ?>
                    <div class="info-item ultima-importacao">
                        <strong>Última Importação:</strong>
                        <div class="importacao-detalhes">
                            <p><i class="fas fa-user"></i> Por: <strong><?= htmlspecialchars($ultimaImportacao['usuario_nome']) ?></strong></p>
                            <p><i class="fas fa-calendar"></i> Em: <?= date('d/m/Y H:i', strtotime($ultimaImportacao['data_hora'])) ?></p>
                            <p><i class="fas fa-list"></i> Registros: <?= $ultimaImportacao['quantidade_registros'] ?></p>
                            <p><i class="fas fa-file"></i> Arquivo: <?= htmlspecialchars($ultimaImportacao['arquivo_nome']) ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Modal de Carregamento -->
    <div id="loadingModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="loading-container">
                <div class="spinner"></div>
                <h3>Processando...</h3>
                <p id="loadingMessage">Por favor, aguarde...</p>
                <p class="loading-text">Isso pode levar alguns minutos.</p>
            </div>
        </div>
    </div>

    <style>
        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: var(--shadow);
        }
        
        .card h3 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card p {
            margin-bottom: 1.5rem;
            color: var(--text-color);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .info-item {
            padding: 1rem;
            background: var(--light-gray);
            border-radius: 4px;
        }
        
        .info-item strong {
            display: block;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .info-item.ultima-importacao {
            grid-column: 1 / -1;
            background: #f8f9fa;
        }

        .importacao-detalhes {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .importacao-detalhes p {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9em;
        }

        .importacao-detalhes i {
            color: var(--primary-color);
            width: 16px;
        }
        
        .mt-4 {
            margin-top: 2rem;
        }

        /* Estilos do Modal de Carregamento */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 2rem;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            text-align: center;
        }

        .loading-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .loading-text {
            color: #666;
            font-size: 0.9em;
            margin-top: 0.5rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-action {
            background-color: #2196F3;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-action:hover {
            background-color: #1976D2;
        }

        .checkbox-group {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 4px;
            margin-top: 5px;
        }

        .checkbox-label {
            display: block;
            margin: 5px 0;
            cursor: pointer;
        }

        .checkbox-label input[type="checkbox"] {
            margin-right: 8px;
        }

        .info-box {
            background-color: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }

        .info-box p {
            margin: 0;
            color: #0d47a1;
            font-size: 0.9em;
        }

        .info-box i {
            margin-right: 5px;
        }
    </style>

    <script>
        // Função para mostrar o modal de carregamento
        function showLoadingModal(message) {
            document.getElementById('loadingMessage').textContent = message;
            document.getElementById('loadingModal').style.display = 'block';
        }

        // Manipulador para o formulário de restauração
        document.getElementById('restoreForm').addEventListener('submit', function(e) {
            if (confirm('ATENÇÃO: Esta ação irá substituir todos os dados atuais. Deseja continuar?')) {
                showLoadingModal('Por favor, aguarde enquanto o backup está sendo restaurado...');
                // Permitir que o formulário seja enviado normalmente
                return true;
            }
            e.preventDefault();
            return false;
        });

        // Manipulador para o formulário de importação
        document.getElementById('importForm').addEventListener('submit', function(e) {
            showLoadingModal('Por favor, aguarde enquanto os dados estão sendo importados...');
            // Permitir que o formulário seja enviado normalmente
            return true;
        });

        // Manipulador para o botão de backup - sem modal
        var backupBtn = document.querySelector('form button[name="backup"]');
        if (backupBtn) {
            backupBtn.addEventListener('click', function() {
                // Sem mostrar o modal para backup
                return true;
            });
        }

        // Adicionar evento para fechar o modal quando a página for carregada
        window.addEventListener('load', function() {
            document.getElementById('loadingModal').style.display = 'none';
        });

        function toggleTabelasEspecificas() {
            const tipoBackup = document.getElementById('tipo_backup').value;
            const tabelasEspecificas = document.getElementById('tabelasEspecificas');
            tabelasEspecificas.style.display = tipoBackup === 'personalizado' ? 'block' : 'none';
        }
    </script>
</body>
</html> 