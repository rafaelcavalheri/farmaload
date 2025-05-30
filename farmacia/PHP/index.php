<?php
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
    <link rel="stylesheet" href="style.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>
    <main class="main-content">
        <div class="welcome-text">
            <h2>Bem-vindo ao Sistema de Controle da Farmácia Municipal de Alto Custo de Mogi Mirim</h2>
            <p>Este sistema permite gerenciar medicamentos, pacientes e dispensações de forma eficiente e segura.</p>
            <p>Selecione uma opção no menu acima para começar.</p>
        </div>

        <!-- Imagem centralizada -->
        <div class="image-container">
            <img src="/images/brasao.png" alt="Brasão Mogi Mirim">
        </div>
    </main>
</body>
</html>
