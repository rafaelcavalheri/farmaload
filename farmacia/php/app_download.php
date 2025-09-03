<?php
// Inclui configurações dos aplicativos
require_once __DIR__ . '/app_config.php';
require_once __DIR__ . '/mobile_detection.php';

// Inicia a sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Detecta o sistema operacional do dispositivo
$deviceOS = getDeviceOS();
$availableApps = getAvailableApps();

// Se não for dispositivo móvel e não for forçado, redireciona para login
if (!isMobileDevice() && !isset($_GET['force'])) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download App FarmAlto - Mogi Mirim</title>
    <link rel="icon" type="image/png" href="/images/fav.png">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .app-download-container {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            text-align: center;
        }

        .app-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 100%;
            margin-bottom: 20px;
        }

        .app-icon {
            width: 120px;
            height: 120px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            border-radius: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .app-icon i {
            font-size: 60px;
            color: white;
        }

        .app-title {
            font-size: 2.5rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }

        .app-subtitle {
            font-size: 1.2rem;
            color: #666;
            margin-bottom: 30px;
        }

        .app-info {
            margin-bottom: 30px;
        }

        .info-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 2px solid #dee2e6;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            margin: 20px 0;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }

        .info-card i {
            font-size: 2.5rem;
            color: #667eea;
            margin-bottom: 15px;
        }

        .info-card h3 {
            font-size: 1.3rem;
            color: #333;
            margin-bottom: 10px;
            font-weight: bold;
        }

        .info-card p {
            color: #666;
            font-size: 1rem;
            line-height: 1.5;
            margin: 0;
        }

        .info-card strong {
            color: #667eea;
            font-weight: bold;
        }

        .download-buttons {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 30px;
        }

        .download-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 15px 30px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: bold;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .download-btn.primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
        }

        .download-btn.primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }

        .download-btn.secondary {
            background: #f8f9fa;
            color: #333;
            border: 2px solid #e9ecef;
        }

        .download-btn.secondary:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }

        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }

        .feature {
            text-align: center;
            padding: 20px;
        }

        .feature i {
            font-size: 2rem;
            color: #667eea;
            margin-bottom: 10px;
        }

        .feature h3 {
            font-size: 1.1rem;
            margin-bottom: 5px;
            color: #333;
        }

        .feature p {
            color: #666;
            font-size: 0.9rem;
        }

        .back-link {
            color: white;
            text-decoration: none;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            padding: 15px 30px;
            background: rgba(255,255,255,0.1);
            border-radius: 50px;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        .back-link:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
        }

        .coming-soon {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
        }

        .coming-soon p {
            margin: 5px 0;
            color: #6c757d;
        }

        .coming-soon i {
            color: #ffc107;
            margin-right: 5px;
        }

        @media (max-width: 768px) {
            .app-card {
                padding: 30px 20px;
            }
            
            .app-title {
                font-size: 2rem;
            }
            
            .features {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="app-download-container">
        <div class="app-card">
            <div class="app-icon">
                <img src="/images/pil.png" alt="Logo FarmAlto" style="width: 60px; height: 60px; object-fit: contain;">
            </div>
            
            <h1 class="app-title">Farmaload</h1>
            <p class="app-subtitle">Aplicativo oficial da Farmácia Municipal de Alto Custo de Mogi Mirim</p>
            
            <div class="app-info">
                <div class="info-card">
                    <i class="fas fa-clipboard-list"></i>
                    
                    <p>Este aplicativo é destinado ao <strong>inventário de estoque</strong> da Farmácia Municipal, permitindo controle e gestão eficiente dos medicamentos disponíveis.</p>
                </div>
            </div>
            
            <div class="download-buttons">
                <?php if (isAppAvailable('android')): ?>
                <a href="<?= getAppDownloadLink('android') ?>" class="download-btn primary" id="download-android">
                    <i class="fab fa-android"></i>
                    Baixar aplicativo
                </a>
                <?php endif; ?>
                
                <?php if (isAppAvailable('ios')): ?>
                <a href="<?= getAppDownloadLink('ios') ?>" class="download-btn primary" id="download-ios" target="_blank">
                    <i class="fab fa-apple"></i>
                    Download para iOS
                </a>
                <?php endif; ?>
                
                <?php if (empty($availableApps)): ?>
                <div class="coming-soon">
                    <p><i class="fas fa-clock"></i> Aplicativo em desenvolvimento</p>
                    <p style="font-size: 0.9rem; color: #666;">Em breve disponível nas lojas oficiais</p>
                </div>
                <?php endif; ?>
                
                <a href="login.php?continue_browser=1" class="download-btn secondary">
                    <i class="fas fa-desktop"></i>
                    Continuar no navegador
                </a>
            </div>
            
                   
        <a href="login.php" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Voltar para o login
        </a>
    </div>

    <script>
        // Detectar sistema operacional e mostrar botão apropriado
        function detectOS() {
            const userAgent = navigator.userAgent || navigator.vendor || window.opera;
            const androidBtn = document.getElementById('download-android');
            const iosBtn = document.getElementById('download-ios');
            
            if (androidBtn && iosBtn) {
                if (/android/i.test(userAgent)) {
                    androidBtn.style.display = 'flex';
                    iosBtn.style.display = 'none';
                } else if (/iPad|iPhone|iPod/.test(userAgent) && !window.MSStream) {
                    androidBtn.style.display = 'none';
                    iosBtn.style.display = 'flex';
                } else {
                    // Desktop - mostrar ambos os botões
                    androidBtn.style.display = 'flex';
                    iosBtn.style.display = 'flex';
                }
            }
        }

        // Executar detecção quando a página carregar
        document.addEventListener('DOMContentLoaded', detectOS);
    </script>
</body>
</html> 