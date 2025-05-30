<?php
require 'config.php';

$email = 'admin';
$senha = 'HakETodLEfRe';

try {
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND auth_type = 'local'");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();

    echo "Usuário encontrado: " . ($usuario ? "SIM" : "NÃO") . "\n";
    
    if ($usuario) {
        echo "ID: " . $usuario['id'] . "\n";
        echo "Nome: " . $usuario['nome'] . "\n";
        echo "Email: " . $usuario['email'] . "\n";
        echo "Perfil: " . $usuario['perfil'] . "\n";
        echo "Auth Type: " . $usuario['auth_type'] . "\n";
        echo "Hash da senha: " . $usuario['senha'] . "\n";
        
        $verificacao = password_verify($senha, $usuario['senha']);
        echo "Verificação da senha: " . ($verificacao ? "SUCESSO" : "FALHA") . "\n";
    }
} catch (PDOException $e) {
    echo "Erro: " . $e->getMessage() . "\n";
} 