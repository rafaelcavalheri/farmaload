<?php
require __DIR__ . '/config.php';

header('Content-Type: application/json');

if (!isset($_GET['cpf'])) {
    echo json_encode(['existe' => false, 'erro' => 'CPF não fornecido']);
    exit;
}

$cpf = preg_replace('/\D/', '', $_GET['cpf']);
$ordem = $_GET['ordem'] ?? 'nome';
$direcao = $_GET['direcao'] ?? 'ASC';

// Validação numérica
if (strlen($cpf) !== 11 || !ctype_digit($cpf)) {
    echo json_encode(['existe' => false, 'erro' => 'CPF inválido']);
    exit;
}

try {
    // Definir colunas de ordenação
    $colunas_ordenacao = [
        'nome' => 'nome',
        'cpf' => 'cpf',
        'sim' => 'sim',
        'nascimento' => 'nascimento'
    ];

    // Construir a consulta SQL
    $sql = "SELECT id, nome, cpf, sim, nascimento, 
            (SELECT MAX(data) FROM transacoes WHERE paciente_id = pacientes.id) as ultima_coleta
            FROM pacientes WHERE cpf = :cpf";
    
    // Adicionar ordenação
    if (isset($colunas_ordenacao[$ordem])) {
        $sql .= " ORDER BY " . $colunas_ordenacao[$ordem] . " " . ($direcao === 'DESC' ? 'DESC' : 'ASC');
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['cpf' => $cpf]);
    $paciente = $stmt->fetch(PDO::FETCH_ASSOC);

    // Formatar a data da última coleta
    if ($paciente && !empty($paciente['ultima_coleta'])) {
        $paciente['ultima_coleta_formatada'] = date('d/m/Y H:i', strtotime($paciente['ultima_coleta']));
    }

    echo json_encode([
        'existe' => !empty($paciente),
        'paciente' => $paciente ?: null
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['existe' => false, 'erro' => 'Erro de banco de dados']);
}