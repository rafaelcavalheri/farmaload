<?php
require_once 'config.php';

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}

if ($_SESSION['usuario']['perfil'] !== 'admin') {
    die("Acesso negado! Apenas administradores podem acessar esta página.");
}

if (!isset($_FILES['sql_file'])) {
    header('Location: gerenciar_dados.php?erro=' . urlencode('Nenhum arquivo foi enviado'));
    exit();
}

try {
    $sql = file_get_contents($_FILES['sql_file']['tmp_name']);
    
    // Desabilitar foreign keys antes da restauração
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
    
    // Executar o SQL do backup
    $pdo->exec($sql);
    
    // Reabilitar foreign keys após a restauração
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
    
    header('Location: gerenciar_dados.php?sucesso=' . urlencode('Backup restaurado com sucesso!'));
} catch (Exception $e) {
    // Garantir que as foreign keys sejam reabilitadas mesmo em caso de erro
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
    header('Location: gerenciar_dados.php?erro=' . urlencode('Erro ao restaurar backup: ' . $e->getMessage()));
} 