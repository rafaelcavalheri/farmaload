<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== INICIANDO TESTE ===\n";

try {
    echo "Carregando config.php...\n";
    require_once 'config.php';
    echo "config.php carregado com sucesso\n";
    
    // Definir a função diretamente aqui para evitar problemas com headers
    function registrarDetalhesImportacao($pdo, $logImportacaoId, $dados) {
        echo "[DEBUG] Entrou em registrarDetalhesImportacao\n";
        try {
            echo "[DEBUG] Dados recebidos: ";
            print_r($dados);
            // Registrar medicamentos importados
            if (isset($dados['medicamentos'])) {
                foreach ($dados['medicamentos'] as $medicamento) {
                    echo "[DEBUG] Inserindo medicamento: ";
                    print_r($medicamento);
                    $stmt = $pdo->prepare("
                        INSERT INTO logs_importacao_detalhes (
                            log_importacao_id, medicamento_nome, quantidade, lote, validade, observacao
                        ) VALUES (?, ?, ?, ?, STR_TO_DATE(?, '%d/%m/%Y'), ?)
                    ");
                    $stmt->execute([
                        $logImportacaoId,
                        $medicamento['nome'],
                        $medicamento['quantidade'],
                        $medicamento['lote'],
                        $medicamento['validade'],
                        'Código: ' . $medicamento['codigo'] . ', Apresentação: ' . $medicamento['apresentacao']
                    ]);
                }
            }
            
            // Registrar pacientes importados
            if (isset($dados['pacientes'])) {
                foreach ($dados['pacientes'] as $paciente) {
                    echo "[DEBUG] Inserindo paciente: ";
                    print_r($paciente);
                    $stmt = $pdo->prepare("
                        INSERT INTO logs_importacao_detalhes (
                            log_importacao_id, paciente_nome, observacao
                        ) VALUES (?, ?, ?)
                    ");
                    $stmt->execute([
                        $logImportacaoId,
                        $paciente['nome'],
                        'Paciente importado da linha ' . $paciente['linha']
                    ]);
                }
            }
            
            echo "[DEBUG] Finalizou registrarDetalhesImportacao\n";
            return true;
        } catch (Exception $e) {
            echo "[ERRO] Erro ao registrar detalhes da importação: " . $e->getMessage() . "\n";
            error_log("Erro ao registrar detalhes da importação: " . $e->getMessage());
            return false;
        }
    }
    
    echo "Função registrarDetalhesImportacao definida\n";
    echo "Testando função registrarDetalhesImportacao...\n";

    // Dados de teste
    $dados = [
        'medicamentos' => [
            [
                'nome' => 'Paracetamol 500mg',
                'quantidade' => 100,
                'lote' => 'LOT001',
                'validade' => '31/12/2025',
                'codigo' => 'PAR001',
                'apresentacao' => 'Comprimido'
            ],
            [
                'nome' => 'Dipirona 500mg',
                'quantidade' => 50,
                'lote' => 'LOT002',
                'validade' => '30/11/2025',
                'codigo' => 'DIP001',
                'apresentacao' => 'Comprimido'
            ]
        ],
        'pacientes' => [
            [
                'nome' => 'João Silva',
                'linha' => 5
            ],
            [
                'nome' => 'Maria Santos',
                'linha' => 6
            ]
        ]
    ];

    echo "Dados de teste criados\n";

    // Criar um log de teste
    $stmt = $pdo->prepare("INSERT INTO logs_importacao (usuario_id, usuario_nome, data_hora, arquivo_nome, quantidade_registros, status) VALUES (?, ?, NOW(), ?, ?, ?)");
    $stmt->execute([
        1,
        'Teste',
        'teste.xls',
        count($dados['medicamentos']),
        'TESTE'
    ]);
    
    $logImportacaoId = $pdo->lastInsertId();
    echo "Log de teste criado com ID: $logImportacaoId\n";
    
    // Testar a função
    echo "Chamando registrarDetalhesImportacao...\n";
    $resultado = registrarDetalhesImportacao($pdo, $logImportacaoId, $dados);
    
    if ($resultado) {
        echo "✅ Função executada com sucesso!\n";
        
        // Verificar se os detalhes foram salvos
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM logs_importacao_detalhes WHERE log_importacao_id = ?");
        $stmt->execute([$logImportacaoId]);
        $total = $stmt->fetch()['total'];
        
        echo "Total de detalhes salvos: $total\n";
        
        // Mostrar os detalhes salvos
        $stmt = $pdo->prepare("SELECT * FROM logs_importacao_detalhes WHERE log_importacao_id = ?");
        $stmt->execute([$logImportacaoId]);
        $detalhes = $stmt->fetchAll();
        
        echo "Detalhes salvos:\n";
        foreach ($detalhes as $detalhe) {
            print_r($detalhe);
        }
        
    } else {
        echo "❌ Erro na função!\n";
    }
    
    // Limpar dados de teste
    $stmt = $pdo->prepare("DELETE FROM logs_importacao_detalhes WHERE log_importacao_id = ?");
    $stmt->execute([$logImportacaoId]);
    
    $stmt = $pdo->prepare("DELETE FROM logs_importacao WHERE id = ?");
    $stmt->execute([$logImportacaoId]);
    
    echo "Dados de teste removidos.\n";
    echo "=== TESTE FINALIZADO ===\n";
    
} catch (Exception $e) {
    echo "ERRO FATAL: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?> 