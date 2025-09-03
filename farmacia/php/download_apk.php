<?php
/**
 * Download direto do APK do Farmaload
 * 
 * Este arquivo serve o APK diretamente da pasta local
 * ao invés de redirecionar para o Dropbox
 */

// Verificar se o usuário está autenticado (opcional)
// require_once __DIR__ . '/config.php';
// verificarAutenticacao(['admin', 'operador']);

// Caminho para o arquivo APK
$apkPath = __DIR__ . '/apk/farmaload.apk';

// Verificar se o arquivo existe
if (!file_exists($apkPath)) {
    http_response_code(404);
    die('APK não encontrado');
}

// Obter informações do arquivo
$fileSize = filesize($apkPath);
$fileName = basename($apkPath);

// Configurar headers para download
header('Content-Type: application/vnd.android.package-archive');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Limpar buffer de saída
if (ob_get_level()) {
    ob_end_clean();
}

// Ler e enviar o arquivo
$handle = fopen($apkPath, 'rb');
if ($handle) {
    while (!feof($handle)) {
        echo fread($handle, 8192);
        flush();
    }
    fclose($handle);
} else {
    http_response_code(500);
    die('Erro ao ler o arquivo APK');
}
?> 