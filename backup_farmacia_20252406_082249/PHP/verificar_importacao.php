<?php
require __DIR__ . '/config.php';
verificarAutenticacao(['admin']);

// Dados para o teste
$testData = [
    'pacientes' => [
        [
            'nome' => 'Paciente Teste Via Script',
            'linha' => 999
        ]
    ],
    'medicamentos' => [
        [
            'nome' => 'Medicamento Teste',
            'quantidade' => 10,
            'lote' => 'LOT999',
            'validade' => '31/12/2024',
            'codigo' => 'MED99999',
            'apresentacao' => 'Comprimido'
        ]
    ]
];

$resultado = "Iniciando teste de importação...<br>";

try {
    // Incluir o arquivo que contém a função importarDados
    require_once 'processar_importacao_automatica.php';
    
    $resultado .= "Arquivo processar_importacao_automatica.php carregado com sucesso.<br>";
    
    // Testar importação
    $pacientesCount = importarDados($testData);
    
    $resultado .= "Importação concluída.<br>";
    $resultado .= "Pacientes processados: $pacientesCount<br>";
    
    // Verificar se o paciente de teste foi importado
    $stmt = $pdo->prepare("SELECT id, nome, cpf FROM pacientes WHERE nome = ?");
    $stmt->execute(['Paciente Teste Via Script']);
    $pacienteImportado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($pacienteImportado) {
        $resultado .= "Paciente encontrado no banco de dados:<br>";
        $resultado .= "ID: " . $pacienteImportado['id'] . "<br>";
        $resultado .= "Nome: " . $pacienteImportado['nome'] . "<br>";
        $resultado .= "CPF: " . $pacienteImportado['cpf'] . "<br>";
        
        // Adicionar link para visualizar todos os pacientes importados
        $resultado .= "<a href='listar_pacientes_importados.php' class='btn-primary'>Ver todos os pacientes importados</a>";
    } else {
        $resultado .= "ERRO: Paciente não encontrado no banco de dados após importação.<br>";
    }

} catch (Exception $e) {
    $resultado .= "ERRO: " . $e->getMessage() . "<br>";
    
    // Verificar logs
    $resultado .= "Verificando logs...<br>";
    
    $logFiles = [
        '/var/www/html/debug_logs/import_debug.log',
        '/tmp/import_debug.log',
        '/tmp/log_error.txt'
    ];
    
    foreach ($logFiles as $logFile) {
        if (file_exists($logFile)) {
            $resultado .= "Log encontrado: $logFile<br>";
            $resultado .= "<pre>" . htmlspecialchars(file_get_contents($logFile)) . "</pre>";
        } else {
            $resultado .= "Log não encontrado: $logFile<br>";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar Importação de Pacientes</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    
    <main class="container">
        <h2><i class="fas fa-bug"></i> Diagnóstico de Importação de Pacientes</h2>
        
        <div class="alert info">
            <i class="fas fa-info-circle"></i> Este script testa diretamente a função de importação de pacientes.
        </div>
        
        <div class="resultado">
            <?php echo $resultado; ?>
        </div>
        
        <div class="buttons">
            <a href="medicamentos.php" class="btn-secondary">Voltar para Medicamentos</a>
        </div>
    </main>
</body>
</html> 