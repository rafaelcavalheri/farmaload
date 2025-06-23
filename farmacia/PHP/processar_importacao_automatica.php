<?php
// Incluir o autoloader do Composer primeiro
require_once __DIR__ . '/vendor/autoload.php';
require_once 'config.php';

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}

if ($_SESSION['usuario']['perfil'] !== 'admin') {
    die("Acesso negado! Apenas administradores podem acessar esta página.");
}

// A função vincularMedicamentoPaciente já está definida no final do arquivo

// Adicionar namespace para PhpSpreadsheet
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

// Adicionar no início do arquivo:
function converterDataExcel($data) {
    // Se for null ou vazio, retorna data padrão
    if ($data === null || $data === '') {
        return '31/12/2024';
    }
    
    // Converter para string se não for
    $data = (string)$data;
    
    // Se for uma string no formato dd/mm/yyyy, retorna como está
    if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $data)) {
        return $data;
    }
    
    // Se for um número (formato Excel), converte para data
    if (is_numeric($data)) {
        $timestamp = ($data - 25569) * 86400;
        return date('d/m/Y', $timestamp);
    }
    
    // Se não conseguir converter, retorna data padrão
    return '31/12/2024';
}

// Adicionar função para validar linha do template
function validarLinha($row, $linha) {
    $nome = trim($row[0]);
    $quantidade = intval($row[1]);
    $lote = $row[2] ?? '';
    $validade = $row[3] ?? '31/12/2024';
    
    // Validar dados básicos
    if (empty($nome)) {
        throw new Exception("Linha $linha: Nome do medicamento é obrigatório");
    }
    
    if ($quantidade <= 0) {
        throw new Exception("Linha $linha: Quantidade deve ser maior que zero");
    }
    
    // Gerar código automático se não existir
    $codigo = 'MED' . str_pad($linha, 5, '0', STR_PAD_LEFT);
    
    // Gerar lote se não tiver
    if (empty($lote)) {
        $lote = 'LOT' . str_pad($linha, 3, '0', STR_PAD_LEFT);
    }
    
    // Tentar extrair apresentação do nome
    $apresentacao = extrairApresentacao($nome);
    
    return [
        'nome' => $nome,
        'quantidade' => $quantidade,
        'lote' => $lote,
        'validade' => $validade,
        'codigo' => $codigo,
        'apresentacao' => $apresentacao
    ];
}

// Adicionar função para extrair apresentação do medicamento
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

// Verificar se a coluna apresentacao existe na tabela medicamentos
function verificarColunaApresentacao($pdo) {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM medicamentos LIKE 'apresentacao'");
        $stmt->execute();
        $exists = $stmt->rowCount() > 0;
        
        return ['exists' => $exists];
    } catch (Exception $e) {
        // Se der erro (como tabela não existir), retorna que não existe
        return ['exists' => false];
    }
}

function processarTemplate($spreadsheet) {
    $worksheet = $spreadsheet->getActiveSheet();
    $dados = [];
    $pacientes = [];
    
    // Criar log para debug
    $logFile = fopen('/var/www/html/debug_logs/import_debug.log', 'a');
    
    if ($logFile) {
        fwrite($logFile, "Processando template...\n");
    } else {
        // Falha ao criar o log - tentar criar um arquivo de erro
        file_put_contents('/tmp/log_error.txt', date('Y-m-d H:i:s') . ": Não foi possível criar o arquivo de log\n", FILE_APPEND);
    }
    
    $linha = 2; // Começar da linha 2 (após cabeçalho)
    while ($worksheet->getCell('A' . $linha)->getValue() !== null) {
        $row = [
            $worksheet->getCell('A' . $linha)->getValue(),
            $worksheet->getCell('B' . $linha)->getValue(),
            $worksheet->getCell('C' . $linha)->getValue(),
            $worksheet->getCell('D' . $linha)->getValue()
        ];
        
        // Verificar e extrair nome do paciente se existir (coluna E)
        $nomePaciente = $worksheet->getCell('E' . $linha)->getValue();
        
        if ($logFile) {
            fwrite($logFile, "Linha $linha - Coluna E: " . ($nomePaciente ? $nomePaciente : "vazio") . "\n");
        }
        
        if (!empty($nomePaciente)) {
            $nomePaciente = trim($nomePaciente);
            
            // Ignorar valores que não parecem ser nomes de pacientes
            if (strlen($nomePaciente) > 3 && !is_numeric($nomePaciente) && 
                strpos(strtolower($nomePaciente), 'total') === false) {
                if ($logFile) {
                    fwrite($logFile, "Paciente encontrado: $nomePaciente\n");
                }
                
                $pacientes[] = [
                    'nome' => $nomePaciente,
                    'linha' => $linha
                ];
            }
        }
        
        $dados[] = validarLinha($row, $linha);
        $linha++;
    }
    
    if ($logFile) {
        fwrite($logFile, "Total de pacientes encontrados no template: " . count($pacientes) . "\n");
        fclose($logFile);
    }
    
    return ['medicamentos' => $dados, 'pacientes' => $pacientes];
}

function converterFormatoLivre($spreadsheet) {
    $worksheet = $spreadsheet->getActiveSheet();
    $dados = [];
    $pacientes = [];

    // Encontrar índices das colunas necessárias
    $highestColumn = $worksheet->getHighestColumn();
    $colCount = Coordinate::columnIndexFromString($highestColumn);
    $lastRow = $worksheet->getHighestDataRow();

    // Para o formato específico do relatório, vamos procurar a última coluna
    // que geralmente contém a quantidade
    $quantidadeCol = null;
    for ($col = 1; $col <= $colCount; $col++) {
        $colLetter = Coordinate::stringFromColumnIndex($col);
        // Verificar algumas linhas para encontrar números que parecem ser quantidade
        for ($row = 1; $row <= $lastRow; $row++) {
            $valor = $worksheet->getCell($colLetter . $row)->getValue();
            if (is_numeric($valor) && $valor > 0 && $valor < 1000) { // Assumindo que quantidades são menores que 1000
                $quantidadeCol = $colLetter;
                break 2;
            }
        }
    }

    // Procurar por linhas que começam com medicamentos
    for ($row = 1; $row <= $lastRow; $row++) {
        $nome = $worksheet->getCell('A' . $row)->getValue();
        if (empty($nome)) continue;
        
        // Ignorar linha de Total Geral
        if (stripos($nome, 'Total') !== false) {
            continue;
        }
        
        // Capturar linhas que contêm a palavra "Total" para usar o total de cada medicamento
        $usarTotal = (stripos($nome, 'Total') !== false);
        
        // Se for linha de total, extrair o nome do medicamento
        if ($usarTotal) {
            // Remove a palavra "Total" e espaços extras
            $nome = trim(str_ireplace('Total', '', $nome));
            // Remove possíveis caracteres extras como ":" ou "-"
            $nome = trim($nome, " :-");
        }

        // Pegar a quantidade da última coluna
        $quantidade = $quantidadeCol ? $worksheet->getCell($quantidadeCol . $row)->getValue() : null;
        
        // Se não encontrou quantidade na última coluna, procura por um número no final da linha
        if (empty($quantidade) || !is_numeric($quantidade)) {
            for ($col = $colCount; $col >= 1; $col--) {
                $colLetter = Coordinate::stringFromColumnIndex($col);
                $valor = $worksheet->getCell($colLetter . $row)->getValue();
                if (is_numeric($valor) && $valor > 0 && $valor < 1000) {
                    $quantidade = $valor;
                    break;
                }
            }
        }

        // Processar somente linhas de total ou linhas normais se não for uma linha de total
        if (!empty($nome) && !empty($quantidade) && is_numeric($quantidade)) {
            // Limpar e formatar dados
            $nome = trim(preg_replace('/\s+/', ' ', $nome));
            $quantidade = (int)$quantidade;
            
            if ($quantidade <= 0) continue;

            // Tentar extrair a apresentação do nome do medicamento
            $apresentacao = extrairApresentacao($nome);

            // Procurar por um número que parece ser lote (geralmente com 7 dígitos)
            $lote = '';
            for ($col = 1; $col <= $colCount; $col++) {
                $colLetter = Coordinate::stringFromColumnIndex($col);
                $valor = $worksheet->getCell($colLetter . $row)->getValue();
                if (is_numeric($valor) && strlen((string)$valor) >= 7) {
                    $lote = $valor;
                    break;
                }
            }

            // Procurar por uma data válida para validade
            $validade = '';
            for ($col = 1; $col <= $colCount; $col++) {
                $colLetter = Coordinate::stringFromColumnIndex($col);
                $valor = $worksheet->getCell($colLetter . $row)->getValue();
                // Adicionar verificação de null e converter para string se necessário
                if ($valor !== null) {
                    $valor = (string)$valor;
                    if (preg_match('/\d{2}\/\d{2}\/\d{4}/', $valor)) {
                        $validade = $valor;
                        break;
                    }
                }
            }

            // Gerar lote se necessário
            if (empty($lote)) {
                $lote = 'LOT' . str_pad($row, 3, '0', STR_PAD_LEFT);
            }

            // Usar data padrão se não encontrou
            if (empty($validade)) {
                $validade = '31/12/2024';
            }

            // Gerar código
            $codigo = 'MED' . str_pad($row, 5, '0', STR_PAD_LEFT);

            $dados[] = [
                'nome' => $nome,
                'quantidade' => $quantidade,
                'lote' => $lote,
                'validade' => $validade,
                'codigo' => $codigo,
                'apresentacao' => $apresentacao
            ];
        }

        // Procurar por nome de paciente na linha
        $nomePaciente = $worksheet->getCell('E' . $row)->getValue();
        if (!empty($nomePaciente)) {
            $nomePaciente = trim($nomePaciente);
            // Ignorar valores que não parecem ser nomes de pacientes
            if (strlen($nomePaciente) > 3 && !is_numeric($nomePaciente) && 
                strpos(strtolower($nomePaciente), 'total') === false) {
                $pacientes[] = [
                    'nome' => $nomePaciente,
                    'linha' => $row
                ];
            }
        }
    }
    
    if (empty($dados)) {
        throw new Exception('Nenhum dado válido encontrado no arquivo.');
    }
    
    return ['medicamentos' => $dados, 'pacientes' => $pacientes];
}

function converterFormatoLivre_Modificado($spreadsheet) {
    $worksheet = $spreadsheet->getActiveSheet();
    $pacientes = [];
    $associacoes = []; // Nova estrutura para armazenar associações
    $medicamentosUnicos = []; // Para controlar todos os medicamentos já processados
    $totalProWh = []; // Para armazenar linhas de total encontradas

    // Encontrar índices das colunas necessárias
    $highestColumn = $worksheet->getHighestColumn();
    $colCount = Coordinate::columnIndexFromString($highestColumn);
    $lastRow = $worksheet->getHighestDataRow();

    // Criar log para debug
    $logFile = fopen('/var/www/html/debug_logs/import_debug.log', 'a');
    if ($logFile) {
        fwrite($logFile, "=== INICIANDO CONVERSÃO FORMATO LIVRE MODIFICADO ===\n");
    }

    // Variáveis para controlar o medicamento atual
    $medicamentoAtual = null;
    $codigoAtual = null;
    $loteAtual = null;
    $validadeAtual = null;
    $apresentacaoAtual = null;

    // PRIMEIRA PASSAGEM: Identificar todas as linhas de total
    for ($row = 1; $row <= $lastRow; $row++) {
        $nome = $worksheet->getCell('A' . $row)->getValue();
        $quantidade = $worksheet->getCell('E' . $row)->getValue();
        
        if (!empty($nome) && is_numeric($quantidade) && (int)$quantidade > 0) {
            // Verificar se é uma linha de total
            $isTotal = (stripos($nome, 'Total') !== false);
            
            if ($isTotal) {
                // Extrair o nome do medicamento da linha de total
                $nomeMedicamento = trim(str_ireplace('Total', '', $nome));
                $nomeMedicamento = trim($nomeMedicamento, " :-");
                
                if (!empty($nomeMedicamento)) {
                    $totalProWh[$nomeMedicamento] = (int)$quantidade;
                    if ($logFile) {
                        fwrite($logFile, "LINHA TOTAL: $nomeMedicamento = $quantidade\n");
                    }
                }
            }
        }
    }

    // SEGUNDA PASSAGEM: Processar os medicamentos e pacientes
    for ($row = 1; $row <= $lastRow; $row++) {
        $nome = $worksheet->getCell('A' . $row)->getValue();
        $lote = $worksheet->getCell('B' . $row)->getValue();
        $validade = $worksheet->getCell('C' . $row)->getValue();
        $nomePaciente = $worksheet->getCell('D' . $row)->getValue();
        $quantidade = $worksheet->getCell('E' . $row)->getValue();
        
        // Pular linhas de total na segunda passagem
        if (!empty($nome) && stripos($nome, 'Total') !== false) {
            continue;
        }
        
        // Se tiver nome de medicamento, atualiza o medicamento atual e suas informações
        if (!empty($nome)) {
            $medicamentoAtual = trim(preg_replace('/\s+/', ' ', $nome));
            
            // Tentar extrair a apresentação do nome do medicamento
            $apresentacaoAtual = extrairApresentacao($medicamentoAtual);
            
            // Atualizar lote e validade apenas se forem fornecidos
            if (!empty($lote)) {
                $loteAtual = $lote;
            } else {
                $loteAtual = 'LOT' . str_pad($row, 3, '0', STR_PAD_LEFT);
            }
            
            if (!empty($validade) && preg_match('/\d{2}\/\d{2}\/\d{4}/', $validade)) {
                $validadeAtual = $validade;
            } else {
                $validadeAtual = '31/12/2024';
            }
            
            $codigoAtual = 'MED' . str_pad($row, 5, '0', STR_PAD_LEFT);
        }
        
        // Se tiver paciente e medicamento atual, cria associação
        if (!empty($nomePaciente) && $nomePaciente != 'NM_PACIENTE' && !empty($medicamentoAtual)) {
            // Verificar se o nome do paciente é válido
            $nomePaciente = trim($nomePaciente);
            
            if (strlen($nomePaciente) > 3 && !is_numeric($nomePaciente) && 
                strpos(strtolower((string)$nomePaciente), 'total') === false) {
                
                // Adicionar paciente à lista de pacientes
                $pacienteJaExiste = false;
                foreach ($pacientes as $paciente) {
                    if ($paciente['nome'] === $nomePaciente) {
                        $pacienteJaExiste = true;
                        break;
                    }
                }
                
                if (!$pacienteJaExiste) {
                    $pacientes[] = [
                        'nome' => $nomePaciente,
                        'linha' => $row
                    ];
                }
                
                // Cria associação entre paciente e medicamento usando o lote e validade atuais
                $qtd = is_numeric($quantidade) ? (int)$quantidade : 0;
                if ($qtd > 0) {
                    $associacoes[] = [
                        'paciente' => $nomePaciente,
                        'medicamento' => $medicamentoAtual,
                        'lote' => $loteAtual,
                        'validade' => $validadeAtual,
                        'codigo' => $codigoAtual,
                        'apresentacao' => $apresentacaoAtual,
                        'quantidade' => $qtd,
                        'linha' => $row
                    ];
                }
            }
        }
        
        // Adicionar o medicamento na lista de medicamentos se for uma linha com medicamento e quantidade
        // sem estar associado a um paciente específico
        if (!empty($medicamentoAtual) && is_numeric($quantidade) && (int)$quantidade > 0 
            && empty($nomePaciente)) {
            
            $chave = $medicamentoAtual . '|' . $loteAtual;
            
            // Verifica se este medicamento tem uma linha de total
            $usarTotal = isset($totalProWh[$medicamentoAtual]);
            
            // Se não temos registro deste medicamento ainda
            if (!isset($medicamentosUnicos[$chave])) {
                // Se temos uma linha de total, usamos esse valor
                if ($usarTotal) {
                    $quantidadeAUsar = $totalProWh[$medicamentoAtual];
                    if ($logFile) {
                        fwrite($logFile, "Usando quantidade da linha TOTAL para $medicamentoAtual: $quantidadeAUsar\n");
                    }
                } else {
                    $quantidadeAUsar = (int)$quantidade;
                }
                
                $medicamentosUnicos[$chave] = [
                    'nome' => $medicamentoAtual,
                    'quantidade' => $quantidadeAUsar,
                    'lote' => $loteAtual,
                    'validade' => $validadeAtual,
                    'codigo' => $codigoAtual,
                    'apresentacao' => $apresentacaoAtual
                ];
                
                if ($logFile) {
                    fwrite($logFile, "Novo medicamento: $medicamentoAtual, Lote: $loteAtual, Qtd: $quantidadeAUsar\n");
                }
            }
        }
    }
    
    // Registra medicamentos de pacientes que não foram encontrados no estoque geral
    foreach ($associacoes as $associacao) {
        $chave = $associacao['medicamento'] . '|' . $associacao['lote'];
        
        // Se o medicamento não está na lista de medicamentos, adiciona-o
        if (!isset($medicamentosUnicos[$chave])) {
            // Verifica se existe uma linha de total para este medicamento
            $quantidade = isset($totalProWh[$associacao['medicamento']]) 
                ? $totalProWh[$associacao['medicamento']] 
                : $associacao['quantidade'];
                
            $medicamentosUnicos[$chave] = [
                'nome' => $associacao['medicamento'],
                'quantidade' => $quantidade,
                'lote' => $associacao['lote'],
                'validade' => $associacao['validade'],
                'codigo' => $associacao['codigo'],
                'apresentacao' => $associacao['apresentacao']
            ];
            
            if ($logFile) {
                fwrite($logFile, "Criando medicamento a partir de associação: {$associacao['medicamento']}, Qtd: $quantidade\n");
            }
        }
    }
    
    if ($logFile) {
        fwrite($logFile, "=== RESUMO DA CONVERSÃO ===\n");
        fwrite($logFile, "Total de medicamentos únicos: " . count($medicamentosUnicos) . "\n");
        fwrite($logFile, "Total de pacientes: " . count($pacientes) . "\n");
        fwrite($logFile, "Total de associações: " . count($associacoes) . "\n");
        
        // Log detalhado de medicamentos para diagnóstico
        fwrite($logFile, "\n=== MEDICAMENTOS PROCESSADOS ===\n");
        foreach ($medicamentosUnicos as $chave => $med) {
            fwrite($logFile, "$chave: {$med['nome']} - Qtd: {$med['quantidade']}\n");
        }
        
        fclose($logFile);
    }
    
    // Garantir que temos dados mínimos
    if (empty($medicamentosUnicos) && empty($associacoes)) {
        throw new Exception('Nenhum dado válido encontrado no arquivo.');
    }
    
    // Converter medicamentos únicos para o array de dados
    $dados = array_values($medicamentosUnicos);
    
    return [
        'medicamentos' => $dados, 
        'pacientes' => $pacientes,
        'associacoes' => $associacoes
    ];
}

// Função para registrar detalhes da importação
function registrarDetalhesImportacao($pdo, $logImportacaoId, $dados) {
    try {
        // Registrar medicamentos importados
        if (isset($dados['medicamentos'])) {
            foreach ($dados['medicamentos'] as $medicamento) {
                $stmt = $pdo->prepare("
                    INSERT INTO logs_importacao_detalhes (
                        log_importacao_id, tipo, nome, quantidade, lote, validade, observacoes
                    ) VALUES (?, 'medicamento', ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $logImportacaoId,
                    $medicamento['nome'],
                    $medicamento['quantidade'],
                    $medicamento['lote'],
                    $medicamento['validade'],
                    'Código: ' . ($medicamento['codigo'] ?? 'N/A') . ', Apresentação: ' . ($medicamento['apresentacao'] ?? 'N/A')
                ]);
            }
        }
        
        // Registrar pacientes importados
        if (isset($dados['pacientes'])) {
            foreach ($dados['pacientes'] as $paciente) {
                $stmt = $pdo->prepare("
                    INSERT INTO logs_importacao_detalhes (
                        log_importacao_id, tipo, nome, cpf, observacoes
                    ) VALUES (?, 'paciente', ?, ?, ?)
                ");
                $stmt->execute([
                    $logImportacaoId,
                    $paciente['nome'],
                    $paciente['cpf'] ?? null,
                    'Paciente importado da linha ' . ($paciente['linha'] ?? 'N/A')
                ]);
            }
        }
        
        return true;
    } catch (Exception $e) {
        // Se falhar ao registrar detalhes, apenas loga o erro mas não interrompe a importação
        error_log("Erro ao registrar detalhes da importação: " . $e->getMessage());
        return false;
    }
}

function importarDados($dados) {
    global $pdo;
    
    // Criar log para debug
    $logFile = fopen('/var/www/html/debug_logs/import_debug.log', 'a');
    
    if ($logFile) {
        fwrite($logFile, "--- NOVA IMPORTAÇÃO: " . date('Y-m-d H:i:s') . " ---\n");
        fwrite($logFile, "Total de medicamentos a importar: " . count($dados['medicamentos']) . "\n");
    }
    
    try {
        $pdo->beginTransaction();
        
        // Verificar se a coluna 'apresentacao' existe na tabela medicamentos
        $colunaApresentacaoInfo = verificarColunaApresentacao($pdo);
        
        // Processar pacientes primeiro (se existirem)
        $pacientesProcessados = [];
        
        if (isset($dados['pacientes']) && !empty($dados['pacientes'])) {
            if ($logFile) {
                fwrite($logFile, "Processando " . count($dados['pacientes']) . " pacientes...\n");
            }
            
            foreach ($dados['pacientes'] as $paciente) {
                // Verificar se o paciente já existe pelo nome
                $stmt = $pdo->prepare("SELECT id FROM pacientes WHERE LOWER(TRIM(nome)) = LOWER(TRIM(?))");
                $stmt->execute([$paciente['nome']]);
                $pacienteExistente = $stmt->fetch();
                
                if (!$pacienteExistente) {
                    // Gerar CPF temporário único
                    do {
                        $cpfTemp = gerarCpfTemporario();
                        
                        $stmt = $pdo->prepare("SELECT id FROM pacientes WHERE cpf = ?");
                        $stmt->execute([$cpfTemp]);
                        $cpfExiste = $stmt->fetch();
                    } while ($cpfExiste);
                    
                    $nomePaciente = trim($paciente['nome']);
                    if (empty($nomePaciente)) {
                        if ($logFile) {
                            fwrite($logFile, "ERRO: Nome do paciente vazio. Pulando registro da linha " . $paciente['linha'] . "\n");
                        }
                        continue;
                    }
                    
                    if ($logFile) {
                        fwrite($logFile, "Inserindo novo paciente: " . $nomePaciente . " (CPF: " . $cpfTemp . ")\n");
                    }
                    
                    try {
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
                            $nomePaciente,
                            $cpfTemp,
                            'Paciente importado automaticamente. Dados precisam ser atualizados.'
                        ]);
                        $novoId = $pdo->lastInsertId();
                        if ($logFile) {
                            fwrite($logFile, "Paciente inserido com ID: " . $novoId . "\n");
                        }
                        $pacientesProcessados[$nomePaciente] = $novoId;
                    } catch (Exception $e) {
                        if ($logFile) {
                            fwrite($logFile, "ERRO ao inserir paciente: " . $e->getMessage() . "\n");
                        }
                    }
                } else {
                    // Não atualize mais a validade do paciente!
                    if ($logFile) {
                        fwrite($logFile, "Paciente já existe: " . $paciente['nome'] . " (ID: " . $pacienteExistente['id'] . ")\n");
                    }
                    $pacientesProcessados[$paciente['nome']] = $pacienteExistente['id'];
                }
            }
        }
        
        // Processar medicamentos
        foreach ($dados['medicamentos'] as $item) {
            if ($logFile) {
                fwrite($logFile, "Processando medicamento: " . $item['nome'] . " (Lote: " . $item['lote'] . ")\n");
            }
            
            // Verificar se o medicamento já existe
            $stmt = $pdo->prepare("SELECT id FROM medicamentos WHERE LOWER(TRIM(nome)) = LOWER(TRIM(?))");
            $stmt->execute([trim($item['nome'])]);
            $medicamentoExistente = $stmt->fetch();

            if ($medicamentoExistente) {
                $medicamentoId = $medicamentoExistente['id'];
                
                // Verificar se o lote já existe para este medicamento
                $stmt = $pdo->prepare("
                    SELECT id, quantidade 
                    FROM lotes_medicamentos 
                    WHERE medicamento_id = ? AND lote = ?
                ");
                $stmt->execute([$medicamentoId, $item['lote']]);
                $loteExistente = $stmt->fetch();

                if ($loteExistente) {
                    // Atualizar quantidade do lote existente
                    $novaQuantidade = $loteExistente['quantidade'] + $item['quantidade'];
                    if ($logFile) {
                        fwrite($logFile, "Atualizando lote existente. Quantidade anterior: " . $loteExistente['quantidade'] . 
                                        ", Nova quantidade: " . $novaQuantidade . "\n");
                    }
                    
                    $stmt = $pdo->prepare("
                        UPDATE lotes_medicamentos 
                        SET quantidade = ?,
                            validade = STR_TO_DATE(?, '%d/%m/%Y')
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $novaQuantidade,
                        $item['validade'],
                        $loteExistente['id']
                    ]);
                } else {
                    // Inserir novo lote
                    if ($logFile) {
                        fwrite($logFile, "Inserindo novo lote para medicamento existente. Quantidade: " . $item['quantidade'] . "\n");
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO lotes_medicamentos (
                            medicamento_id,
                            lote,
                            quantidade,
                            validade
                        ) VALUES (?, ?, ?, STR_TO_DATE(?, '%d/%m/%Y'))
                    ");
                    $stmt->execute([
                        $medicamentoId,
                        $item['lote'],
                        $item['quantidade'],
                        $item['validade']
                    ]);
                }

                // Registrar movimentação
                $stmt = $pdo->prepare("
                    INSERT INTO movimentacoes (
                        medicamento_id, 
                        tipo, 
                        quantidade, 
                        quantidade_anterior,
                        quantidade_nova,
                        data,
                        observacao
                    ) VALUES (?, 'IMPORTACAO', ?, ?, ?, NOW(), ?)
                ");
                $stmt->execute([
                    $medicamentoId,
                    $item['quantidade'],
                    $loteExistente ? $loteExistente['quantidade'] : 0,
                    $loteExistente ? ($loteExistente['quantidade'] + $item['quantidade']) : $item['quantidade'],
                    'Importação automática - Lote: ' . $item['lote']
                ]);

            } else {
                // Inserir novo medicamento
                if ($logFile) {
                    fwrite($logFile, "Inserindo novo medicamento: " . $item['nome'] . "\n");
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO medicamentos (
                        nome, 
                        apresentacao, 
                        codigo
                    ) VALUES (?, ?, ?)
                ");
                $stmt->execute([
                    $item['nome'],
                    $item['apresentacao'],
                    $item['codigo']
                ]);
                
                $novoId = $pdo->lastInsertId();
                
                // Inserir o lote
                $stmt = $pdo->prepare("
                    INSERT INTO lotes_medicamentos (
                        medicamento_id,
                        lote,
                        quantidade,
                        validade
                    ) VALUES (?, ?, ?, STR_TO_DATE(?, '%d/%m/%Y'))
                ");
                $stmt->execute([
                    $novoId,
                    $item['lote'],
                    $item['quantidade'],
                    $item['validade']
                ]);

                // Registrar movimentação inicial
                $stmt = $pdo->prepare("
                    INSERT INTO movimentacoes (
                        medicamento_id, 
                        tipo, 
                        quantidade, 
                        quantidade_anterior,
                        quantidade_nova,
                        data,
                        observacao
                    ) VALUES (?, 'IMPORTACAO', ?, 0, ?, NOW(), ?)
                ");
                $stmt->execute([
                    $novoId,
                    $item['quantidade'],
                    $item['quantidade'],
                    'Importação automática - Cadastro inicial - Lote: ' . $item['lote']
                ]);
            }
        }
        
        // Processar associações entre pacientes e medicamentos
        if (isset($dados['associacoes'])) {
            $associacoesProcessadas = processarAssociacoes($dados, $pdo, $pacientesProcessados, $logFile);
            
            if ($logFile) {
                fwrite($logFile, "Total de associações paciente-medicamento processadas: " . $associacoesProcessadas . "\n");
            }
        }

        $pdo->commit();
        if ($logFile) {
            fwrite($logFile, "Transação concluída com sucesso!\n");
            fclose($logFile);
        }

        // CÓDIGO REMOVIDO PARA EVITAR DUPLICAÇÃO - O LOG É REGISTRADO NO CÓDIGO PRINCIPAL
        // // Registrar o log da importação
        // $stmt = $pdo->prepare("INSERT INTO logs_importacao (usuario_id, usuario_nome, data_hora, arquivo_nome, quantidade_registros, status) VALUES (?, ?, NOW(), ?, ?, ?)");
        // $stmt->execute([
        //     $_SESSION['usuario']['id'],
        //     $_SESSION['usuario']['nome'],
        //     $_FILES['arquivo']['name'],
        //     count($dados['medicamentos']),
        //     'SUCESSO'
        // ]);
        
        // // Capturar o ID do log e registrar detalhes
        // $logImportacaoId = $pdo->lastInsertId();
        // registrarDetalhesImportacao($pdo, $logImportacaoId, $dados);

        return count($pacientesProcessados);
    } catch (Exception $e) {
        $pdo->rollBack();
        if ($logFile) {
            fwrite($logFile, "ERRO: " . $e->getMessage() . "\n");
            fclose($logFile);
        }
        
        // Registrar erro em um arquivo de log alternativo
        file_put_contents('/tmp/import_error.log', date('Y-m-d H:i:s') . ': ' . $e->getMessage() . "\n", FILE_APPEND);
        
        // Registrar o erro na tabela de logs
        try {
            $stmt = $pdo->prepare("INSERT INTO logs_importacao (usuario_id, usuario_nome, data_hora, arquivo_nome, quantidade_registros, status) VALUES (?, ?, NOW(), ?, ?, ?)");
            $stmt->execute([
                $_SESSION['usuario']['id'],
                $_SESSION['usuario']['nome'],
                $_FILES['arquivo']['name'] ?? 'N/A',
                0,
                'ERRO'
            ]);
        } catch (Exception $logError) {
            // Se falhar ao registrar o log, apenas ignora
        }
        
        if ($isAjax) {
            echo json_encode(['success' => false, 'message' => 'Erro ao importar dados: ' . $e->getMessage()]);
        } else {
            header('Location: relatorios.php?erro=' . urlencode($e->getMessage()) . '&aba=importacoes');
        }
        exit();
    }
}

// Adicionar função para imprimir a estrutura do arquivo importado no log
function logEstruturaPlanilha($spreadsheet, $logFile) {
    if (!$logFile) return;
    
    $worksheet = $spreadsheet->getActiveSheet();
    $highestRow = $worksheet->getHighestDataRow();
    $highestColumn = $worksheet->getHighestColumn();
    $colCount = Coordinate::columnIndexFromString($highestColumn);
    
    fwrite($logFile, "=== ESTRUTURA DO ARQUIVO IMPORTADO ===\n");
    fwrite($logFile, "Nome da Planilha: " . $worksheet->getTitle() . "\n");
    fwrite($logFile, "Número de Linhas: $highestRow, Número de Colunas: $colCount\n");
    
    // Imprimir primeiras linhas para diagnóstico
    fwrite($logFile, "===  PRIMEIRAS 5 LINHAS  ===\n");
    for ($row = 1; $row <= min(5, $highestRow); $row++) {
        $linha = "Linha $row: ";
        for ($col = 1; $col <= min(7, $colCount); $col++) {
            $colLetter = Coordinate::stringFromColumnIndex($col);
            $value = $worksheet->getCell($colLetter . $row)->getValue();
            $linha .= "$colLetter=$value | ";
        }
        fwrite($logFile, "$linha\n");
    }
    
    // Verificar especificamente coluna D (que deve ter os nomes dos pacientes)
    fwrite($logFile, "=== VERIFICAÇÃO DA COLUNA D ===\n");
    for ($row = 1; $row <= min(20, $highestRow); $row++) {
        $value = $worksheet->getCell('D' . $row)->getValue();
        if (!empty($value)) {
            fwrite($logFile, "Linha $row, Coluna D: '$value'\n");
        }
    }
    
    fwrite($logFile, "===============================\n");
}

// Função para vincular medicamento ao paciente durante a importação
function vincularMedicamentoPaciente($pdo, $pacienteId, $medicamentoId, $nomeMedicamento, $quantidade, $cid = null, $logFile = null, $renovacao = null) {
    try {
        // Converter data para formato YYYY-MM-DD se vier como dd/mm/yyyy
        if ($renovacao && preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $renovacao, $m)) {
            $renovacao = $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        // Verificar se o vínculo já existe
        $stmt = $pdo->prepare("
            SELECT id FROM paciente_medicamentos 
            WHERE paciente_id = ? AND medicamento_id = ?
        ");
        $stmt->execute([$pacienteId, $medicamentoId]);
        $vinculoExistente = $stmt->fetch();
        
        if ($vinculoExistente) {
            // Substituir quantidade e renovacao se o vínculo já existir (em vez de incrementar)
            $stmt = $pdo->prepare("
                UPDATE paciente_medicamentos 
                SET quantidade = ?,
                    cid = ?,
                    renovacao = ?,
                    data_atualizacao = NOW(),
                    observacoes = CONCAT(IFNULL(observacoes, ''), ' | Atualizado via importação em ', NOW())
                WHERE id = ?
            ");
            $stmt->execute([$quantidade, $cid, $renovacao, $vinculoExistente['id']]);
            
            if ($logFile) {
                fwrite($logFile, "Vínculo paciente-medicamento atualizado: Paciente ID $pacienteId, Medicamento ID $medicamentoId, Nova Quantidade definida para $quantidade, CID: $cid, Renovacao: $renovacao\n");
            }
        } else {
            // Criar novo vínculo
            $stmt = $pdo->prepare("
                INSERT INTO paciente_medicamentos (
                    paciente_id,
                    medicamento_id,
                    nome_medicamento,
                    quantidade,
                    cid,
                    renovacao,
                    observacoes
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $pacienteId,
                $medicamentoId,
                $nomeMedicamento,
                $quantidade,
                $cid,
                $renovacao,
                'Vínculo criado automaticamente durante importação em ' . date('Y-m-d H:i:s')
            ]);
            
            if ($logFile) {
                fwrite($logFile, "Novo vínculo paciente-medicamento criado: Paciente ID $pacienteId, Medicamento ID $medicamentoId, Quantidade $quantidade, CID: $cid, Renovacao: $renovacao\n");
            }
        }
        
        return true;
    } catch (Exception $e) {
        if ($logFile) {
            fwrite($logFile, "ERRO ao vincular medicamento ao paciente: " . $e->getMessage() . "\n");
        }
        return false;
    }
}

function processarAssociacoes($dados, $pdo, $pacientesProcessados, $logFile) {
    // Verificar se há associações para processar
    if (!isset($dados['associacoes']) || empty($dados['associacoes'])) {
        if ($logFile) {
            fwrite($logFile, "Nenhuma associação paciente-medicamento para processar.\n");
        }
        return 0;
    }

    // Filtrar para garantir que só arrays válidos sejam processados
    $associacoes = array_filter(
        $dados['associacoes'],
        function($item) use ($logFile) {
            $valido = is_array($item)
                && isset($item['paciente'], $item['medicamento'], $item['quantidade']);
            if (!$valido && $logFile) {
                fwrite($logFile, "Associação ignorada por formato inválido: " . print_r($item, true) . "\n");
            }
            return $valido;
        }
    );

    $associacoesProcessadas = 0;

    if ($logFile) {
        fwrite($logFile, "Processando " . count($associacoes) . " associações entre pacientes e medicamentos...\n");
    }

    foreach ($associacoes as $associacao) {
        // Verificar se o paciente foi processado
        if (!isset($pacientesProcessados[$associacao['paciente']])) {
            if ($logFile) {
                fwrite($logFile, "AVISO: Paciente '" . $associacao['paciente'] . "' não encontrado para associação. Linha: " . $associacao['linha'] . "\n");
            }
            continue;
        }
        
        $pacienteId = $pacientesProcessados[$associacao['paciente']];
        
        // Buscar o medicamento pelo nome
        $stmt = $pdo->prepare("
            SELECT id, nome 
            FROM medicamentos 
            WHERE LOWER(TRIM(nome)) = LOWER(TRIM(?))
        ");
        $stmt->execute([trim($associacao['medicamento'])]);
        $medicamento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($medicamento) {
            // Buscar o lote específico
            $stmt = $pdo->prepare("
                SELECT id 
                FROM lotes_medicamentos 
                WHERE medicamento_id = ? AND lote = ?
            ");
            $stmt->execute([$medicamento['id'], $associacao['lote']]);
            $lote = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($lote) {
                // Vincular paciente ao medicamento
                $resultado = vincularMedicamentoPaciente(
                    $pdo, 
                    $pacienteId, 
                    $medicamento['id'], 
                    $medicamento['nome'], 
                    $associacao['quantidade'],
                    $associacao['cid'] ?? null,
                    $logFile,
                    $associacao['validade_processo'] ?? null // Passa a data de renovação individual
                );
                
                if ($resultado) {
                    $associacoesProcessadas++;
                }
            } else {
                if ($logFile) {
                    fwrite($logFile, "AVISO: Lote '" . $associacao['lote'] . "' não encontrado para o medicamento '" . $associacao['medicamento'] . "'. Linha: " . $associacao['linha'] . "\n");
                }
            }
        } else {
            if ($logFile) {
                fwrite($logFile, "AVISO: Medicamento '" . $associacao['medicamento'] . "' não encontrado para associação. Linha: " . $associacao['linha'] . "\n");
            }
        }
    }
    
    if ($logFile) {
        fwrite($logFile, "Total de associações processadas: " . $associacoesProcessadas . "\n");
    }
    
    return $associacoesProcessadas;
}

function mapearValidadesMedicamentos($spreadsheet) {
    $worksheet = $spreadsheet->getSheetByName('MEDICAMENTOS');
    if (!$worksheet) {
        return ['validades' => [], 'lotes' => []];
    }
    
    $validades = [];
    $lotes = [];
    $lastRow = $worksheet->getHighestRow();
    
    for ($row = 2; $row <= $lastRow; $row++) {
        $nome = $worksheet->getCell('A' . $row)->getValue();
        $lote = $worksheet->getCell('B' . $row)->getValue();
        $validade = $worksheet->getCell('C' . $row)->getValue();
        
        // Padronizar nome para evitar problemas de busca
        if ($nome !== null) {
            $nomePadronizado = trim(mb_strtoupper((string)$nome));
            if ($nomePadronizado) {
                $validades[$nomePadronizado] = converterDataExcel($validade);
                $lotes[$nomePadronizado] = !empty($lote) ? trim((string)$lote) : 'LOT' . str_pad($row, 3, '0', STR_PAD_LEFT);
            }
        }
    }
    
    return ['validades' => $validades, 'lotes' => $lotes];
}

function importarReliniFim($spreadsheet) {
    $worksheet = $spreadsheet->getSheetByName('RELINI_FIM');
    if (!$worksheet) {
        throw new Exception('Aba RELINI_FIM não encontrada na planilha.');
    }
    $highestCol = $worksheet->getHighestColumn();
    $lastRow = $worksheet->getHighestRow();
    $colCount = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);

    $logDir = __DIR__ . '/debug_logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $logFile = fopen($logDir . '/import_debug.log', 'a');
    if ($logFile) {
        fwrite($logFile, "\n=== INICIANDO IMPORTAÇÃO EXCLUSIVA ABA RELINI_FIM (MAPEAMENTO CONFIRMADO) ===\n");
        fwrite($logFile, "Última linha: $lastRow\n");
        fwrite($logFile, "Número de colunas: $colCount\n");
    }

    // Mapear validades e lotes dos medicamentos
    $medicamentosInfo = mapearValidadesMedicamentos($spreadsheet);
    $validadesMedicamentos = $medicamentosInfo['validades'];
    $lotesMedicamentos = $medicamentosInfo['lotes'];
    
    if ($logFile) {
        fwrite($logFile, "Total de validades de medicamentos mapeadas: " . count($validadesMedicamentos) . "\n");
        fwrite($logFile, "Total de lotes de medicamentos mapeados: " . count($lotesMedicamentos) . "\n");
    }

    $medicamentosUnicos = [];
    $pacientes = [];
    $associacoes = [];

    // Variáveis para controlar o medicamento atual
    $medicamentoAtual = null;
    $loteAtual = null;
    $validadeAtual = null;
    $apresentacaoAtual = null;

    for ($row = 2; $row <= $lastRow; $row++) {
        $validade_paciente = converterDataExcel($worksheet->getCell('D' . $row)->getValue()); // FIM VAL. convertido
        $paciente = trim($worksheet->getCell('E' . $row)->getValue() ?? ''); // PACIENTES
        $dataAtend = trim($worksheet->getCell('F' . $row)->getValue() ?? ''); // DT ATEND. (opcional)
        $quantidade = trim($worksheet->getCell('G' . $row)->getValue() ?? ''); // QTDE.
        $medicamento = trim($worksheet->getCell('H' . $row)->getValue() ?? ''); // MEDICAMENTOS
        $cid = trim($worksheet->getCell('I' . $row)->getValue() ?? ''); // CID
        $codigo = 'MED' . str_pad($row, 5, '0', STR_PAD_LEFT); // Gera código único

        if ($logFile) {
            fwrite($logFile, "Linha $row: Paciente: $paciente | Medicamento: $medicamento | Qtd: $quantidade | ValidadePaciente: $validade_paciente | CID: $cid | DataAtend: $dataAtend | Codigo: $codigo\n");
        }

        // Se tiver nome de medicamento, atualiza o medicamento atual e suas informações
        if (!empty($medicamento)) {
            $medicamentoAtual = trim(mb_strtoupper($medicamento));
            $apresentacaoAtual = extrairApresentacao($medicamentoAtual);
            
            // Usar lote da aba MEDICAMENTOS (padronizado)
            $loteAtual = $lotesMedicamentos[$medicamentoAtual] ?? 'LOT' . str_pad($row, 3, '0', STR_PAD_LEFT);
            $validadeAtual = $validadesMedicamentos[$medicamentoAtual] ?? '31/12/2024';
            
            if ($logFile) {
                fwrite($logFile, "Medicamento atualizado: $medicamentoAtual | Lote: $loteAtual | Validade: $validadeAtual\n");
            }
        }

        if ($paciente && $medicamentoAtual && is_numeric($quantidade) && $quantidade > 0) {
            // Padronizar pacientes como array ['nome'=>..., 'linha'=>..., 'validade'=>...]
            $nomesExistentes = array_column($pacientes, 'nome');
            if (!in_array($paciente, $nomesExistentes)) {
                $pacientes[] = [
                    'nome'  => $paciente,
                    'linha' => $row,
                    'validade' => $validade_paciente
                ];
            }

            // Verificar se já existe um medicamento com o mesmo nome e lote
            $medicamentoEncontrado = false;
            foreach ($medicamentosUnicos as $chave => $med) {
                if ($med['nome'] === $medicamentoAtual && $med['lote'] === $loteAtual) {
                    // Somar a quantidade ao lote existente
                    $medicamentosUnicos[$chave]['quantidade'] += (int)$quantidade;
                    $medicamentoEncontrado = true;
                    if ($logFile) {
                        fwrite($logFile, "Somando quantidade ao lote existente: $medicamentoAtual | Lote: $loteAtual | Nova quantidade: {$medicamentosUnicos[$chave]['quantidade']}\n");
                    }
                    break;
                }
            }

            // Se não encontrou um lote existente, criar novo
            if (!$medicamentoEncontrado) {
                $chave = $medicamentoAtual . '|' . $loteAtual;
                $medicamentosUnicos[$chave] = [
                    'nome' => $medicamentoAtual,
                    'quantidade' => (int)$quantidade,
                    'lote' => $loteAtual,
                    'validade' => $validadeAtual,
                    'codigo' => $codigo,
                    'apresentacao' => $apresentacaoAtual,
                    'cid' => $cid
                ];
                if ($logFile) {
                    fwrite($logFile, "Criando novo lote: $medicamentoAtual | Lote: $loteAtual | Quantidade: $quantidade\n");
                }
            }

            $associacoes[] = [
                'paciente' => $paciente,
                'medicamento' => $medicamentoAtual,
                'lote' => $loteAtual,
                'validade_processo' => $validade_paciente,
                'validade_medicamento' => $validadeAtual,
                'codigo' => $codigo,
                'apresentacao' => $apresentacaoAtual,
                'quantidade' => (int)$quantidade,
                'cid' => $cid,
                'linha' => $row
            ];
        }
    }

    if ($logFile) {
        fwrite($logFile, "\n=== RESUMO DA IMPORTAÇÃO RELINI_FIM ===\n");
        fwrite($logFile, "Total de medicamentos: " . count($medicamentosUnicos) . "\n");
        fwrite($logFile, "Total de pacientes: " . count($pacientes) . "\n");
        fwrite($logFile, "Total de associações: " . count($associacoes) . "\n");
        fclose($logFile);
    }

    // Filtro final: garantir que só arrays válidos estejam em associacoes
    $associacoes = array_values(array_filter($associacoes, function($item) {
        return is_array($item) && isset($item['paciente'], $item['medicamento'], $item['quantidade']);
    }));

    return [
        'medicamentos' => array_values($medicamentosUnicos),
        'pacientes' => $pacientes,
        'associacoes' => $associacoes
    ];
}

function importarReliniInicio($spreadsheet) {
    $worksheet = $spreadsheet->getSheetByName('RELINI_INICIO');
    if (!$worksheet) {
        throw new Exception('Aba RELINI_INICIO não encontrada na planilha.');
    }
    $highestCol = $worksheet->getHighestColumn();
    $lastRow = $worksheet->getHighestRow();
    $colCount = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);

    $logDir = __DIR__ . '/debug_logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $logFile = fopen($logDir . '/import_debug.log', 'a');
    if ($logFile) {
        fwrite($logFile, "\n=== INICIANDO IMPORTAÇÃO EXCLUSIVA ABA RELINI_INICIO (MAPEAMENTO CONFIRMADO) ===\n");
        fwrite($logFile, "Última linha: $lastRow\n");
        fwrite($logFile, "Número de colunas: $colCount\n");
    }

    // Mapear validades e lotes dos medicamentos
    $medicamentosInfo = mapearValidadesMedicamentos($spreadsheet);
    $validadesMedicamentos = $medicamentosInfo['validades'];
    $lotesMedicamentos = $medicamentosInfo['lotes'];
    
    if ($logFile) {
        fwrite($logFile, "Total de validades de medicamentos mapeadas: " . count($validadesMedicamentos) . "\n");
        fwrite($logFile, "Total de lotes de medicamentos mapeados: " . count($lotesMedicamentos) . "\n");
    }

    $medicamentosUnicos = [];
    $pacientes = [];
    $associacoes = [];

    // Variáveis para controlar o medicamento atual
    $medicamentoAtual = null;
    $loteAtual = null;
    $validadeAtual = null;
    $apresentacaoAtual = null;

    for ($row = 2; $row <= $lastRow; $row++) {
        $validade_paciente = converterDataExcel($worksheet->getCell('D' . $row)->getValue()); // INICIO VAL. convertido
        $paciente = trim($worksheet->getCell('E' . $row)->getValue() ?? ''); // PACIENTES
        $dataAtend = trim($worksheet->getCell('F' . $row)->getValue() ?? ''); // DT ATEND. (opcional)
        $quantidade = trim($worksheet->getCell('G' . $row)->getValue() ?? ''); // QTDE.
        $medicamento = trim($worksheet->getCell('H' . $row)->getValue() ?? ''); // MEDICAMENTOS
        $cid = trim($worksheet->getCell('I' . $row)->getValue() ?? ''); // CID
        $codigo = 'MED' . str_pad($row, 5, '0', STR_PAD_LEFT); // Gera código único

        if ($logFile) {
            fwrite($logFile, "Linha $row: Paciente: $paciente | Medicamento: $medicamento | Qtd: $quantidade | ValidadePaciente: $validade_paciente | CID: $cid | DataAtend: $dataAtend | Codigo: $codigo\n");
        }

        // Se tiver nome de medicamento, atualiza o medicamento atual e suas informações
        if (!empty($medicamento)) {
            $medicamentoAtual = trim(mb_strtoupper($medicamento));
            $apresentacaoAtual = extrairApresentacao($medicamentoAtual);
            
            // Usar lote da aba MEDICAMENTOS (padronizado)
            $loteAtual = $lotesMedicamentos[$medicamentoAtual] ?? 'LOT' . str_pad($row, 3, '0', STR_PAD_LEFT);
            $validadeAtual = $validadesMedicamentos[$medicamentoAtual] ?? '31/12/2024';
            
            if ($logFile) {
                fwrite($logFile, "Medicamento atualizado: $medicamentoAtual | Lote: $loteAtual | Validade: $validadeAtual\n");
            }
        }

        if ($paciente && $medicamentoAtual && is_numeric($quantidade) && $quantidade > 0) {
            // Padronizar pacientes como array ['nome'=>..., 'linha'=>..., 'validade'=>...]
            $nomesExistentes = array_column($pacientes, 'nome');
            if (!in_array($paciente, $nomesExistentes)) {
                $pacientes[] = [
                    'nome'  => $paciente,
                    'linha' => $row,
                    'validade' => $validade_paciente
                ];
            }

            $chave = $medicamentoAtual . '|' . $loteAtual;
            if (!isset($medicamentosUnicos[$chave])) {
                $medicamentosUnicos[$chave] = [
                    'nome' => $medicamentoAtual,
                    'quantidade' => (int)$quantidade,
                    'lote' => $loteAtual,
                    'validade' => $validadeAtual,
                    'codigo' => $codigo,
                    'apresentacao' => $apresentacaoAtual,
                    'cid' => $cid
                ];
            } else {
                $medicamentosUnicos[$chave]['quantidade'] += (int)$quantidade;
            }

            $associacoes[] = [
                'paciente' => $paciente,
                'medicamento' => $medicamentoAtual,
                'lote' => $loteAtual,
                'validade_processo' => $validade_paciente,
                'validade_medicamento' => $validadeAtual,
                'codigo' => $codigo,
                'apresentacao' => $apresentacaoAtual,
                'quantidade' => (int)$quantidade,
                'cid' => $cid,
                'linha' => $row
            ];
        }
    }

    if ($logFile) {
        fwrite($logFile, "\n=== RESUMO DA IMPORTAÇÃO RELINI_INICIO ===\n");
        fwrite($logFile, "Total de medicamentos: " . count($medicamentosUnicos) . "\n");
        fwrite($logFile, "Total de pacientes: " . count($pacientes) . "\n");
        fwrite($logFile, "Total de associações: " . count($associacoes) . "\n");
        fclose($logFile);
    }

    // Filtro final: garantir que só arrays válidos estejam em associacoes
    $associacoes = array_values(array_filter($associacoes, function($item) {
        return is_array($item) && isset($item['paciente'], $item['medicamento'], $item['quantidade']);
    }));

    return [
        'medicamentos' => array_values($medicamentosUnicos),
        'pacientes' => $pacientes,
        'associacoes' => $associacoes
    ];
}

// Funções para gerar CPF válido
function gerarCpfTemporario() {
    // Gera os 6 primeiros dígitos (já que vamos adicionar '000' no início)
    $cpf = '';
    for ($i = 0; $i < 6; $i++) {
        $cpf .= mt_rand(0, 9);
    }
    
    // Calcula o primeiro dígito verificador
    $soma = 0;
    for ($i = 0; $i < 6; $i++) {
        $soma += intval($cpf[$i]) * (10 - $i);
    }
    $resto = $soma % 11;
    $dv1 = ($resto < 2) ? 0 : 11 - $resto;
    $cpf .= $dv1;
    
    // Calcula o segundo dígito verificador
    $soma = 0;
    for ($i = 0; $i < 7; $i++) {
        $soma += intval($cpf[$i]) * (11 - $i);
    }
    $resto = $soma % 11;
    $dv2 = ($resto < 2) ? 0 : 11 - $resto;
    $cpf .= $dv2;
    
    // Adiciona o prefixo '000' para identificar CPF genérico
    return '000' . $cpf;
}

// Verificar se é uma requisição AJAX
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($isAjax) {
    header('Content-Type: application/json');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['arquivo'])) {
    $arquivo = $_FILES['arquivo'];
    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    
    // Criar log para debug dentro do container
    $logDir = '/var/www/html/debug_logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $logFile = fopen($logDir . '/import_debug.log', 'a');
    
    // Registrar início da importação com mais detalhes
    if ($logFile) {
        fwrite($logFile, "\n\n=================================================\n");
        fwrite($logFile, "INÍCIO DE IMPORTAÇÃO: " . date('Y-m-d H:i:s') . "\n");
        fwrite($logFile, "Arquivo: " . $arquivo['name'] . " (" . $arquivo['size'] . " bytes)\n");
        fwrite($logFile, "Extensão: " . $extensao . "\n");
        fwrite($logFile, "Tipo MIME: " . $arquivo['type'] . "\n");
        fwrite($logFile, "=================================================\n\n");
    } else {
        // Falha ao criar o log - tentar criar um arquivo de erro
        file_put_contents('/var/www/html/import_error.log', date('Y-m-d H:i:s') . ": Não foi possível criar o arquivo de log\n", FILE_APPEND);
    }
    
    try {
        // Adicionar log antes de carregar o arquivo
        if ($logFile) {
            fwrite($logFile, "Tentando carregar arquivo...\n");
        }
        
        $spreadsheet = IOFactory::load($arquivo['tmp_name']);
        
        if ($logFile) {
            fwrite($logFile, "Arquivo carregado com sucesso!\n");
            fwrite($logFile, "Abas encontradas: " . implode(', ', $spreadsheet->getSheetNames()) . "\n");
        }
        
        // Tentar encontrar a aba medicamentos
        $worksheet = null;
        foreach ($spreadsheet->getSheetNames() as $sheetName) {
            if ($logFile) {
                fwrite($logFile, "Verificando aba: " . $sheetName . "\n");
            }
            if (strtoupper($sheetName) === 'MEDICAMENTOS') {
                $worksheet = $spreadsheet->getSheetByName($sheetName);
                if ($logFile) {
                    fwrite($logFile, "Aba MEDICAMENTOS encontrada!\n");
                }
                break;
            }
        }
        
        // Se não encontrou a aba medicamentos, usa a aba ativa
        if (!$worksheet) {
            $worksheet = $spreadsheet->getActiveSheet();
            if ($logFile) {
                fwrite($logFile, "Usando aba ativa: " . $worksheet->getTitle() . "\n");
            }
        }

        // Tentar identificar o formato do arquivo
        $isTemplateFormat = false;
        $cabecalhoEsperado = ['Nome do Medicamento', 'Quantidade', 'Lote', 'Validade'];
        
        // Verificar se é o formato do template
        $primeiraLinha = [];
        for ($col = 'A'; $col <= 'D'; $col++) {
            $valor = $worksheet->getCell($col . '1')->getValue();
            $primeiraLinha[] = $valor;
            if ($logFile) {
                fwrite($logFile, "Cabeçalho coluna $col: " . $valor . "\n");
            }
        }
        
        if ($primeiraLinha === $cabecalhoEsperado) {
            $isTemplateFormat = true;
            if ($logFile) {
                fwrite($logFile, "Formato identificado: Template\n");
            }
        } else {
            if ($logFile) {
                fwrite($logFile, "Formato não é Template. Primeira linha encontrada: " . implode(', ', $primeiraLinha) . "\n");
            }
        }

        if ($spreadsheet->sheetNameExists('RELINI_FIM')) {
            if ($logFile) {
                fwrite($logFile, "Formato identificado: RELINI_FIM\n");
            }
            $dados = importarReliniFim($spreadsheet);
        } else if ($isTemplateFormat) {
            $dados = processarTemplate($spreadsheet);
        } else {
            if ($logFile) {
                fwrite($logFile, "Formato identificado: Livre\n");
            }
            $dados = converterFormatoLivre_Modificado($spreadsheet);
        }

        // Importar os dados
        $pacientesCount = importarDados($dados);
        
        // Registrar o log da importação
        $stmt = $pdo->prepare("INSERT INTO logs_importacao (usuario_id, usuario_nome, data_hora, arquivo_nome, quantidade_registros, status) VALUES (?, ?, NOW(), ?, ?, ?)");
        $stmt->execute([
            $_SESSION['usuario']['id'],
            $_SESSION['usuario']['nome'],
            $_FILES['arquivo']['name'],
            count($dados['medicamentos']),
            'SUCESSO'
        ]);
        
        // Capturar o ID do log e registrar detalhes
        $logImportacaoId = $pdo->lastInsertId();
        registrarDetalhesImportacao($pdo, $logImportacaoId, $dados);
        
        // Preparar mensagem de sucesso
        $mensagem = count($dados['medicamentos']) . " medicamentos importados com sucesso!";
        if ($pacientesCount > 0) {
            $mensagem .= " " . $pacientesCount . " pacientes também foram importados.";
        }
        
        if ($spreadsheet->sheetNameExists('RELINI_FIM')) {
            $cidMap = importarReliniFim($spreadsheet);
            if (!empty($cidMap)) {
                foreach ($cidMap as $chave => $cid) {
                    list($paciente, $medicamento) = explode('|', $chave, 2);
                    // Atualizar o CID na tabela paciente_medicamentos
                    $stmt = $pdo->prepare("UPDATE paciente_medicamentos pm JOIN pacientes p ON pm.paciente_id = p.id JOIN medicamentos m ON pm.medicamento_id = m.id SET pm.cid = ? WHERE LOWER(TRIM(p.nome)) = ? AND LOWER(TRIM(m.nome)) = ?");
                    $stmt->execute([$cid, $paciente, $medicamento]);
                }
            }
        }
        
        if ($isAjax) {
            echo json_encode(['success' => true, 'message' => $mensagem]);
        } else {
            header('Location: relatorios.php?sucesso=' . urlencode($mensagem) . '&aba=importacoes');
        }
        exit();

    } catch (Exception $e) {
        if ($logFile) {
            fwrite($logFile, "ERRO: " . $e->getMessage() . "\n");
            fclose($logFile);
        }
        
        // Registrar erro em um arquivo de log alternativo
        file_put_contents('/tmp/import_error.log', date('Y-m-d H:i:s') . ': ' . $e->getMessage() . "\n", FILE_APPEND);
        
        // Registrar o erro na tabela de logs
        try {
            $stmt = $pdo->prepare("INSERT INTO logs_importacao (usuario_id, usuario_nome, data_hora, arquivo_nome, quantidade_registros, status) VALUES (?, ?, NOW(), ?, ?, ?)");
            $stmt->execute([
                $_SESSION['usuario']['id'],
                $_SESSION['usuario']['nome'],
                $_FILES['arquivo']['name'] ?? 'N/A',
                0,
                'ERRO'
            ]);
        } catch (Exception $logError) {
            // Se falhar ao registrar o log, apenas ignora
        }
        
        if ($isAjax) {
            echo json_encode(['success' => false, 'message' => 'Erro ao importar dados: ' . $e->getMessage()]);
        } else {
            header('Location: relatorios.php?erro=' . urlencode($e->getMessage()) . '&aba=importacoes');
        }
        exit();
    }
} else {
    if ($isAjax) {
        echo json_encode(['success' => false, 'message' => 'Nenhum arquivo enviado']);
    } else {
        header('Location: relatorios.php?erro=' . urlencode('Nenhum arquivo enviado') . '&aba=importacoes');
    }
    exit();
} 