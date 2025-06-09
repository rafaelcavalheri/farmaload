<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FarmAlto - Mogi Mirim</title>
    <link rel="icon" type="image/png" href="/images/fav.png">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<header>
    <div class="header-container">
        <div class="logo">
            <img src="/images/pil.png" alt="Logo FarmAlto" style="max-height: 50px;">
        </div>
        
        <nav class="main-nav">
            <?php if (isset($_SESSION['usuario'])): ?>
                <ul class="nav-list">
                    <li><a href="dispensar.php"><i class="fas fa-pills"></i> Dispensar</a></li>
                    <li><a href="pacientes.php"><i class="fas fa-users"></i> Pacientes</a></li>
                    <li><a href="relatorios.php"><i class="fas fa-chart-bar"></i> Relatórios</a></li>
                    <?php if ($_SESSION['usuario']['perfil'] === 'admin'): ?>
                        <li><a href="medicamentos.php"><i class="fas fa-capsules"></i> Medicamentos</a></li>
                        <li><a href="medicos.php"><i class="fas fa-user-md"></i> Médicos</a></li>
                        <li><a href="usuarios.php"><i class="fas fa-user-cog"></i> Usuários</a></li>
                        <li><a href="gerenciar_dados.php"><i class="fas fa-database"></i> Gerenciar Dados</a></li>
                    <?php endif; ?>
                </ul>
            <?php endif; ?>
        </nav>

        <div class="user-area">
            <?php if (isset($_SESSION['usuario'])): ?>
                <div class="user-info">
                    <span class="username"><?= htmlspecialchars($_SESSION['usuario']['nome']) ?></span>
                    <a href="logout.php" class="btn-secondary">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</header>
</body>
</html>