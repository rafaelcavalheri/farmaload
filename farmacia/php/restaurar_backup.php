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

$arquivo = $_FILES['sql_file'];

// Validação de segurança do upload
if ($arquivo['error'] !== UPLOAD_ERR_OK) {
    header('Location: gerenciar_dados.php?erro=' . urlencode('Erro no upload do arquivo'));
    exit();
}

// Verificar tamanho do arquivo (máximo 50MB para backups)
if ($arquivo['size'] > 50 * 1024 * 1024) {
    header('Location: gerenciar_dados.php?erro=' . urlencode('Arquivo muito grande. Tamanho máximo: 50MB'));
    exit();
}

// Verificar extensão
$extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
if ($extensao !== 'sql') {
    header('Location: gerenciar_dados.php?erro=' . urlencode('Apenas arquivos SQL são permitidos'));
    exit();
}

// Verificar tipo MIME
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $arquivo['tmp_name']);
finfo_close($finfo);

if ($mimeType !== 'text/plain' && $mimeType !== 'application/sql') {
    header('Location: gerenciar_dados.php?erro=' . urlencode('Tipo de arquivo não permitido'));
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