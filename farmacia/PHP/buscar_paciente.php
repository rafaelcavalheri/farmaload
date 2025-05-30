<?php
require __DIR__ . '/config.php';

header('Content-Type: application/json');

if (!isset($_GET['cpf'])) {
    echo json_encode(['existe' => false, 'erro' => 'CPF não fornecido']);
    exit;
}

$cpf = preg_replace('/\D/', '', $_GET['cpf']);

// Validação numérica
if (strlen($cpf) !== 11 || !ctype_digit($cpf)) {
    echo json_encode(['existe' => false, 'erro' => 'CPF inválido']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, nome FROM pacientes WHERE cpf = :cpf");
    $stmt->execute(['cpf' => $cpf]);
    $paciente = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'existe' => !empty($paciente),
        'paciente' => $paciente ?: null
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['existe' => false, 'erro' => 'Erro de banco de dados']);
}