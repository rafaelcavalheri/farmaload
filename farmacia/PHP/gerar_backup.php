<?php
require_once 'config.php';

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
    
    // Definir tabelas baseadas no tipo de backup
    $tabelas = [];
    switch ($tipo_backup) {
        case 'relatorios':
            $tabelas = [
                'transacoes',
                'pacientes',
                'medicamentos',
                'usuarios',
                'lotes_medicamentos',
                'movimentacoes'
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
        
        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $values = array_map(function($value) use ($pdo) {
                if ($value === null) return 'NULL';
                return $pdo->quote($value);
            }, $row);
            
            $backup .= "INSERT INTO `$table` VALUES (" . implode(', ', $values) . ");\n";
        }
        $backup .= "\n";
    }
    
    // Adicionar comando para reabilitar foreign keys
    $backup .= "SET FOREIGN_KEY_CHECKS=1;\n";
    
    return $backup;
}

try {
    $tipo_backup = $_POST['tipo_backup'] ?? 'completo';
    $tabelas_especificas = $_POST['tabelas_especificas'] ?? [];
    
    $backup = gerarBackup($pdo, $tipo_backup, $tabelas_especificas);
    
    // Configurar headers para download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="backup_' . $tipo_backup . '_' . date('Y-m-d_H-i-s') . '.sql"');
    header('Content-Length: ' . strlen($backup));
    
    // Limpar qualquer saída anterior
    ob_clean();
    flush();
    
    // Enviar o conteúdo
    echo $backup;
} catch (Exception $e) {
    header('Location: gerenciar_dados.php?erro=' . urlencode('Erro ao gerar backup: ' . $e->getMessage()));
} 