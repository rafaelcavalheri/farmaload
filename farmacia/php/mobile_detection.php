<?php
/**
 * Funções para detectar dispositivos móveis
 */

/**
 * Detecta se o dispositivo é móvel baseado no User-Agent
 * @return bool
 */
function isMobileDevice() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Log para debug
    error_log("User-Agent: " . $userAgent);
    
    // Verificações simples e diretas
    if (preg_match('/Mobile|Android|iPhone|iPad|iPod|BlackBerry|Windows Phone|Opera Mini|IEMobile|webOS|Kindle|Silk/i', $userAgent)) {
        error_log("Dispositivo móvel detectado");
        return true;
    }
    
    // Verificação específica para Android (pode não ter "Mobile")
    if (preg_match('/Android/i', $userAgent) && !preg_match('/Windows NT/i', $userAgent)) {
        error_log("Android detectado");
        return true;
    }
    
    // Verificação para iPhone/iPad
    if (preg_match('/iPhone|iPad|iPod/i', $userAgent)) {
        error_log("iOS detectado");
        return true;
    }
    
    error_log("Dispositivo desktop detectado");
    return false;
}

/**
 * Detecta se é um tablet
 * @return bool
 */
function isTablet() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $tabletPatterns = [
        'iPad',
        'Android(?!.*Mobile)',
        'Tablet',
        'Kindle',
        'PlayBook'
    ];
    
    foreach ($tabletPatterns as $pattern) {
        if (preg_match('/' . $pattern . '/i', $userAgent)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Detecta o sistema operacional do dispositivo
 * @return string
 */
function getDeviceOS() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    if (stripos($userAgent, 'Android') !== false) {
        return 'android';
    } elseif (stripos($userAgent, 'iPhone') !== false || stripos($userAgent, 'iPad') !== false || stripos($userAgent, 'iPod') !== false) {
        return 'ios';
    } elseif (stripos($userAgent, 'Windows Phone') !== false) {
        return 'windows_phone';
    } elseif (stripos($userAgent, 'BlackBerry') !== false) {
        return 'blackberry';
    } else {
        return 'desktop';
    }
}

/**
 * Verifica se deve redirecionar para a página de download do app
 * @param bool $forceRedirect Força o redirecionamento mesmo em desktop (para testes)
 * @return bool
 */
function shouldRedirectToApp($forceRedirect = false) {
    // Se for forçado, sempre redireciona
    if ($forceRedirect) {
        return true;
    }
    
    // Verifica se é dispositivo móvel
    if (!isMobileDevice()) {
        return false;
    }
    
    // Verifica se não é tablet (opcional - você pode querer incluir tablets)
    if (isTablet()) {
        // Para tablets, você pode decidir se quer redirecionar ou não
        // Por padrão, não redirecionamos tablets
        return false;
    }
    
    // Verifica se já foi redirecionado nesta sessão (evita loop)
    if (isset($_SESSION['mobile_redirect_shown'])) {
        return false;
    }
    
    return true;
}

/**
 * Marca que o redirecionamento foi mostrado para esta sessão
 */
function markMobileRedirectShown() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['mobile_redirect_shown'] = true;
}

/**
 * Verifica se o usuário escolheu continuar no navegador
 * @return bool
 */
function userChoseBrowser() {
    return isset($_GET['continue_browser']) && $_GET['continue_browser'] === '1';
}

/**
 * Processa o redirecionamento para dispositivos móveis
 * @param string $redirectUrl URL para redirecionar (padrão: app_download.php)
 * @return bool True se redirecionou, False se não
 */
function processMobileRedirect($redirectUrl = 'app_download.php') {
    // Se o usuário escolheu continuar no navegador, não redireciona
    if (userChoseBrowser()) {
        markMobileRedirectShown();
        return false;
    }
    
    // Verifica se deve redirecionar
    if (shouldRedirectToApp()) {
        // Marca que o redirecionamento foi mostrado
        markMobileRedirectShown();
        
        // Redireciona para a página de download
        header('Location: ' . $redirectUrl);
        exit();
    }
    
    return false;
}
?> 