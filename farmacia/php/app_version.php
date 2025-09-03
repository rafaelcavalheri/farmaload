<?php
/**
 * Endpoint para verificação de versão do APK
 * 
 * Retorna informações sobre a versão atual do APK disponível para download
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Incluir arquivo de versão do sistema
require_once __DIR__ . '/version.php';

// Caminho para o arquivo APK
$apkPath = __DIR__ . '/apk/farmaload.apk';

// Informações da versão atual do APK
$appVersion = [
    'version_code' => 24,
    'version_name' => '1.1.24',
    'build_date' => '2025-09-02',
    'download_url' => 'download_apk.php',
    'file_size' => 0,
    'file_exists' => false
];

// Verificar se o arquivo APK existe e obter informações
if (file_exists($apkPath)) {
    $appVersion['file_exists'] = true;
    $appVersion['file_size'] = filesize($apkPath);
    $appVersion['last_modified'] = date('Y-m-d H:i:s', filemtime($apkPath));
}

// Retornar resposta JSON
echo json_encode([
    'success' => true,
    'app_version' => $appVersion,
    'system_version' => SYSTEM_VERSION,
    'system_version_date' => SYSTEM_VERSION_DATE
]);
?> 