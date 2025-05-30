<?php
$senha = 'HakETodLEfRe';
$hash_atual = '$2y$10$5/RjfGxPhekTP3ewR/RCX.ryG7Ja3PIwaIID2Q7XcCByTKQRQ60DS';

// Gerar novo hash
$novo_hash = password_hash($senha, PASSWORD_DEFAULT);

echo "Hash atual: " . $hash_atual . "\n";
echo "Novo hash gerado: " . $novo_hash . "\n";

// Testar verificação com hash atual
$verificacao_atual = password_verify($senha, $hash_atual);
echo "Verificação com hash atual: " . ($verificacao_atual ? "SUCESSO" : "FALHA") . "\n";

// Testar verificação com novo hash
$verificacao_novo = password_verify($senha, $novo_hash);
echo "Verificação com novo hash: " . ($verificacao_novo ? "SUCESSO" : "FALHA") . "\n"; 