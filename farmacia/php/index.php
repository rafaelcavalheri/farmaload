<?php
session_start();
include 'config.php';

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>FarmAlto - Mogi Mirim</title>
    <link rel="icon" type="image/png" href="/images/fav.png">
    <link rel="stylesheet" href="/css/style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <main class="main-content">
        <div class="dashboard-container">
            <!-- Header da Dashboard -->
            <div class="dashboard-header">
                <div class="welcome-section">
                    <h1>Bem-vindo, <?= htmlspecialchars($_SESSION['usuario']['nome']) ?>!</h1>
                    <p>Sistema de Controle da Farmácia Municipal de Alto Custo</p>
                </div>
                <div class="current-time">
                    <i class="fas fa-clock"></i>
                    <span id="current-time"></span>
                </div>
            </div>

            <!-- Seção de Informações -->
            <div class="info-section">
                <div class="info-card">
                    <div class="info-header">
                        <i class="fas fa-info-circle"></i>
                        <h3>Informações do Sistema</h3>
                    </div>
                    <div class="info-content">
                        <p>Este sistema permite gerenciar medicamentos, pacientes e dispensações de forma eficiente e segura.</p>
                        <ul>
                            <li><i class="fas fa-boxes"></i> Controle de estoque em tempo real</li>
                            <li><i class="fas fa-users"></i> Gestão completa de pacientes</li>
                            <li><i class="fas fa-chart-bar"></i> Relatórios detalhados</li>
                            <li><i class="fas fa-desktop"></i> Interface moderna e responsiva</li>
                            <li><i class="fas fa-lock"></i> Autenticação segura</li>
                            <li><i class="fas fa-database"></i> Backup automático</li>
                            <li><i class="fas fa-user-shield"></i> Controle de acesso</li>
                            <li><i class="fas fa-history"></i> Log de atividades</li>
                        </ul>
                    </div>
                </div>

                <div class="info-card">
                    <div class="info-header">
                        <i class="fas fa-mobile-alt"></i>
                        <h3>Farmaload App</h3>
                    </div>
                    <div class="info-content">
                        <div class="app-description">
                            <p><strong>Aplicativo móvel para inventário de estoque</strong></p>
                            <p>O Farmaload App está disponível para dispositivos Android e permite aos farmacêuticos e técnicos realizarem ajustes de estoque diretamente no dispositivo móvel, sincronizando automaticamente com o sistema principal.</p>
                            <p><em>Para download do aplicativo, acesse a página de login em um dispositivo móvel.</em></p>
                        </div>                                                                  
                    </div>
                </div>
            </div>

            <!-- Logo centralizado -->
            <div class="brand-section">
                <img src="/images/brasao.png" alt="Brasão Mogi Mirim" class="brand-logo">
                <p>Prefeitura Municipal de Mogi Mirim</p>
            </div>
        </div>
    </main>

    <script>
        // Atualizar hora atual
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleString('pt-BR', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            document.getElementById('current-time').textContent = timeString;
        }

        // Atualizar a cada segundo
        updateTime();
        setInterval(updateTime, 1000);

        // Animações de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.info-card');
            
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease-out';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
    
    <?php include 'footer.php'; ?>
</body>
</html>
