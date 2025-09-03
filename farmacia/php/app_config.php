<?php
/**
 * Configurações dos aplicativos móveis
 * 
 * Configure aqui os links para download dos aplicativos Android e iOS
 * quando eles estiverem disponíveis nas lojas.
 * 
 * IMPORTANTE: 
 * - Para mostrar os botões de download, configure 'available' => true
 * - Para ocultar os botões, configure 'available' => false
 * - Atualize as URLs das lojas quando os apps estiverem prontos
 */

// Links para download dos aplicativos
// 
// IMPORTANTE: O APK agora é servido diretamente da pasta local (farmacia/apk/)
// ao invés do Dropbox. O arquivo download_apk.php gerencia o download.
$appConfig = [
    'android' => [
        'store_url' => 'https://play.google.com/store/apps/details?id=com.farmalto.app',
        'direct_download' => 'download_apk.php', // Download direto do APK local
        'available' => true // Ativado para mostrar o botão
    ],
    'ios' => [
        'store_url' => 'https://apps.apple.com/app/farmalto/id123456789',
        'direct_download' => null, // URL para download direto do IPA (se disponível)
        'available' => true // Ativado para mostrar o botão
    ],
    'web_app' => [
        'url' => 'https://farmalto.mogimirim.sp.gov.br',
        'available' => true
    ]
];

/**
 * Obtém o link de download para um sistema operacional específico
 * @param string $os Sistema operacional ('android', 'ios', 'web')
 * @return string|null
 */
function getAppDownloadLink($os) {
    global $appConfig;
    
    if (!isset($appConfig[$os])) {
        return null;
    }
    
    $config = $appConfig[$os];
    
    if (!$config['available']) {
        return null;
    }
    
    // Prioriza download direto se disponível, senão usa a loja
    return $config['direct_download'] ?? $config['store_url'];
}

/**
 * Verifica se o aplicativo está disponível para um sistema operacional
 * @param string $os Sistema operacional ('android', 'ios', 'web')
 * @return bool
 */
function isAppAvailable($os) {
    global $appConfig;
    
    return isset($appConfig[$os]) && $appConfig[$os]['available'];
}

/**
 * Obtém informações sobre o aplicativo
 * @param string $os Sistema operacional
 * @return array|null
 */
function getAppInfo($os) {
    global $appConfig;
    
    if (!isset($appConfig[$os])) {
        return null;
    }
    
    return $appConfig[$os];
}

/**
 * Obtém todos os aplicativos disponíveis
 * @return array
 */
function getAvailableApps() {
    global $appConfig;
    
    $available = [];
    
    foreach ($appConfig as $os => $config) {
        if ($config['available']) {
            $available[$os] = $config;
        }
    }
    
    return $available;
}
?> 