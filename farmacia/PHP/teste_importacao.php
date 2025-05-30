<?php
// Conexão direta com o banco de dados
$pdo = new PDO("mysql:host=localhost;dbname=farmacia", "admin", "HakETodLEfRe", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
]);

// Definir funções auxiliares
function extrairApresentacao($nome) {
    $apresentacoes = [
        'comprimido' => 'Comprimido',
        'comprimidos' => 'Comprimido',
        'comp' => 'Comprimido',
        'cápsula' => 'Cápsula',
        'capsulas' => 'Cápsula',
        'caps' => 'Cápsula',
        'ampola' => 'Ampola',
        'ampolas' => 'Ampola',
        'amp' => 'Ampola',
        'injetável' => 'Injetável',
        'injetavel' => 'Injetável',
        'inj' => 'Injetável',
        'frasco' => 'Frasco',
        'frascos' => 'Frasco',
        'fr' => 'Frasco',
        'solução' => 'Solução',
        'solucao' => 'Solução',
        'sol' => 'Solução',
        'suspensão' => 'Suspensão',
        'suspensao' => 'Suspensão',
        'susp' => 'Suspensão',
        'pomada' => 'Pomada',
        'pom' => 'Pomada',
        'creme' => 'Creme',
        'gel' => 'Gel',
        'xarope' => 'Xarope',
        'gotas' => 'Gotas',
        'gts' => 'Gotas'
    ];
    
    $nome = strtolower($nome);
    foreach ($apresentacoes as $termo => $apresentacao) {
        if (strpos($nome, $termo) !== false) {
            return $apresentacao;
        }
    }
    
    return 'Comprimido';
}

function verificarColunaApresentacao($pdo) {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM medicamentos LIKE 'apresentacao'");
        $stmt->execute();
        $exists = $stmt->rowCount() > 0;
        
        return ['exists' => $exists];
    } catch (Exception $e) {
        return ['exists' => false];
    }
}

// Criar log para debug
$logFile = fopen('/tmp/import_debug.log', 'a');
fwrite($logFile, "--- TESTE DE IMPORTAÇÃO: " . date('Y-m-d H:i:s') . " ---\n");

// Dados de teste
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

// Função para importar dados
function importarDados($dados, $pdo, $logFile) {
    fwrite($logFile, "--- NOVA IMPORTAÇÃO: " . date('Y-m-d H:i:s') . " ---\n");
    
    // Registrar pacientes no log
    if (isset($dados['pacientes'])) {
        fwrite($logFile, "Pacientes encontrados: " . count($dados['pacientes']) . "\n");
        foreach ($dados['pacientes'] as $p) {
            fwrite($logFile, "- Paciente: " . $p['nome'] . " (linha " . $p['linha'] . ")\n");
        }
    } else {
        fwrite($logFile, "ERRO: Array de pacientes não encontrado\n");
    }
    
    try {
        $pdo->beginTransaction();
        
        // Verificar se a coluna 'apresentacao' existe na tabela medicamentos
        $colunaApresentacaoInfo = verificarColunaApresentacao($pdo);
        
        // Processar pacientes primeiro (se existirem)
        $pacientesProcessados = [];
        
        if (isset($dados['pacientes']) && !empty($dados['pacientes'])) {
            fwrite($logFile, "Iniciando processamento de pacientes...\n");
            
            foreach ($dados['pacientes'] as $paciente) {
                // Verificar se o paciente já existe pelo nome
                $stmt = $pdo->prepare("SELECT id FROM pacientes WHERE nome = ?");
                $stmt->execute([$paciente['nome']]);
                $pacienteExistente = $stmt->fetch();
                
                if (!$pacienteExistente) {
                    // Gerar CPF temporário
                    $cpfTemp = '00000000000' . str_pad(count($pacientesProcessados) + 1, 3, '0', STR_PAD_LEFT);
                    $cpfTemp = substr($cpfTemp, -14); // Garantir que tenha no máximo 14 caracteres
                    
                    fwrite($logFile, "Inserindo novo paciente: " . $paciente['nome'] . " (CPF: " . $cpfTemp . ")\n");
                    
                    try {
                        // Inserir novo paciente
                        $stmt = $pdo->prepare("
                            INSERT INTO pacientes (
                                nome, 
                                cpf, 
                                nascimento, 
                                telefone, 
                                observacao
                            ) VALUES (?, ?, '1900-01-01', '00000000000', ?)
                        ");
                        $stmt->execute([
                            $paciente['nome'],
                            $cpfTemp,
                            'Paciente importado automaticamente. Dados precisam ser atualizados.'
                        ]);
                        
                        $novoId = $pdo->lastInsertId();
                        fwrite($logFile, "Paciente inserido com ID: " . $novoId . "\n");
                        
                        $pacientesProcessados[$paciente['nome']] = $novoId;
                    } catch (Exception $e) {
                        fwrite($logFile, "ERRO ao inserir paciente: " . $e->getMessage() . "\n");
                    }
                } else {
                    fwrite($logFile, "Paciente já existe: " . $paciente['nome'] . " (ID: " . $pacienteExistente['id'] . ")\n");
                    $pacientesProcessados[$paciente['nome']] = $pacienteExistente['id'];
                }
            }
        } else {
            fwrite($logFile, "Nenhum paciente para processar.\n");
        }
        
        fwrite($logFile, "Total de pacientes processados: " . count($pacientesProcessados) . "\n");
        
        // Processar medicamentos (código resumido para simplificar)
        foreach ($dados['medicamentos'] as $item) {
            // Verificar se o medicamento já existe
            $stmt = $pdo->prepare("
                SELECT id, quantidade FROM medicamentos 
                WHERE LOWER(TRIM(nome)) = LOWER(TRIM(?)) AND lote = ?
            ");
            $stmt->execute([trim($item['nome']), $item['lote']]);
            $medicamentoExistente = $stmt->fetch();

            if ($medicamentoExistente) {
                // Atualizar medicamento existente
                $stmt = $pdo->prepare("
                    UPDATE medicamentos 
                    SET quantidade = quantidade + ?
                    WHERE id = ?
                ");
                $stmt->execute([$item['quantidade'], $medicamentoExistente['id']]);
            } else {
                // Inserir novo medicamento
                $stmt = $pdo->prepare("
                    INSERT INTO medicamentos (
                        nome, 
                        quantidade, 
                        lote, 
                        validade, 
                        codigo,
                        apresentacao
                    ) VALUES (?, ?, ?, STR_TO_DATE(?, '%d/%m/%Y'), ?, ?)
                ");
                $stmt->execute([
                    $item['nome'],
                    $item['quantidade'],
                    $item['lote'],
                    $item['validade'],
                    $item['codigo'],
                    $item['apresentacao']
                ]);
            }
        }

        $pdo->commit();
        fwrite($logFile, "Transação concluída com sucesso!\n");
        
        return [
            'success' => true,
            'pacientes_count' => count($pacientesProcessados)
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        fwrite($logFile, "ERRO na transação: " . $e->getMessage() . "\n");
        
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Executar importação de teste
try {
    echo "Iniciando teste de importação...\n";
    
    $result = importarDados($testData, $pdo, $logFile);
    
    if ($result['success']) {
        echo "Importação concluída com sucesso!\n";
        echo "Pacientes processados: " . $result['pacientes_count'] . "\n";
        
        // Verificar se o paciente de teste foi importado
        $stmt = $pdo->prepare("SELECT id, nome, cpf FROM pacientes WHERE nome = ?");
        $stmt->execute(['Paciente Teste Via Script']);
        $pacienteImportado = $stmt->fetch();
        
        if ($pacienteImportado) {
            echo "Paciente encontrado no banco de dados:\n";
            echo "ID: " . $pacienteImportado['id'] . "\n";
            echo "Nome: " . $pacienteImportado['nome'] . "\n";
            echo "CPF: " . $pacienteImportado['cpf'] . "\n";
        } else {
            echo "ERRO: Paciente não encontrado no banco de dados após importação.\n";
        }
    } else {
        echo "ERRO na importação: " . $result['error'] . "\n";
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}

// Fechar arquivo de log
fwrite($logFile, "--- FIM DO TESTE ---\n\n");
fclose($logFile);

// Mostrar conteúdo do log
echo "\nConteúdo do arquivo de log:\n";
echo file_get_contents('/tmp/import_debug.log'); 