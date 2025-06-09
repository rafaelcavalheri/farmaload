<?php
// Verificar se o arquivo existe
$template_file = __DIR__ . '/templates/modelo_importacao.xls';

if (!file_exists($template_file)) {
    die('Arquivo modelo não encontrado.');
}

// Configurar cabeçalhos para download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="modelo_importacao.xls"');
header('Content-Length: ' . filesize($template_file));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// Enviar o arquivo
readfile($template_file);
exit; 