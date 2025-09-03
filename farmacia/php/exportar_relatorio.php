<?php
require 'vendor/autoload.php';
include 'config.php';

verificarAutenticacao(['admin']);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Validação das datas
$data_inicio = new DateTime();
$data_fim = new DateTime();
try {
    $data_inicio = new DateTime($_GET['data_inicio'] ?? date('Y-m-d'));
    $data_fim = new DateTime($_GET['data_fim'] ?? date('Y-m-d'));
} catch (Exception $e) {
    die("Formato de data inválido");
}

// Debug information
error_log("Date range: " . $data_inicio->format('Y-m-d') . " to " . $data_fim->format('Y-m-d'));

// Parâmetros dos filtros
$medicamento_id = $_GET['medicamento_id'] ?? '';
$operador_id = $_GET['operador_id'] ?? '';
$paciente_id = $_GET['paciente_id'] ?? '';
$tipo_relatorio = $_GET['tipo_relatorio'] ?? 'dispensas';
$status_paciente = $_GET['status_paciente'] ?? '';

// Para ajuste de estoque, limpar filtros de paciente e operador
if ($tipo_relatorio === 'ajuste_estoque') {
    $paciente_id = '';
    $operador_id = '';
}

// Debug information
error_log("Tipo de relatório: " . $tipo_relatorio);
error_log("Filtros: " . print_r($_GET, true));

if ($tipo_relatorio === 'dispensas') {
    // Construção da query de dispensas
    $sql = "SELECT t.*, m.nome as medicamento_nome, u.nome as operador_nome, 
                   p.nome as paciente_nome, p.cpf as paciente_cpf, p.telefone as paciente_telefone, p.telefone2 as paciente_telefone2
            FROM transacoes t
            JOIN medicamentos m ON t.medicamento_id = m.id
            JOIN usuarios u ON t.usuario_id = u.id
            JOIN pacientes p ON t.paciente_id = p.id
            WHERE DATE(t.data) BETWEEN :data_inicio AND :data_fim";

    $params = [
        ':data_inicio' => $data_inicio->format('Y-m-d'),
        ':data_fim' => $data_fim->format('Y-m-d')
    ];

    if (!empty($medicamento_id)) {
        $sql .= " AND t.medicamento_id = :medicamento_id";
        $params[':medicamento_id'] = $medicamento_id;
    }

    if (!empty($operador_id)) {
        $sql .= " AND t.usuario_id = :operador_id";
        $params[':operador_id'] = $operador_id;
    }

    if (!empty($paciente_id)) {
        $sql .= " AND t.paciente_id = :paciente_id";
        $params[':paciente_id'] = $paciente_id;
    }

    $sql .= " ORDER BY t.data DESC";
} elseif ($tipo_relatorio === 'extornos') {
    // Construção da query de extornos (transações com quantidade negativa)
    $sql = "SELECT t.*, m.nome as medicamento_nome, u.nome as operador_nome, 
                   p.nome as paciente_nome, p.cpf as paciente_cpf, p.telefone as paciente_telefone, p.telefone2 as paciente_telefone2
            FROM transacoes t
            JOIN medicamentos m ON t.medicamento_id = m.id
            JOIN usuarios u ON t.usuario_id = u.id
            JOIN pacientes p ON t.paciente_id = p.id
            WHERE t.quantidade < 0 
            AND DATE(t.data) BETWEEN :data_inicio AND :data_fim";

    $params = [
        ':data_inicio' => $data_inicio->format('Y-m-d'),
        ':data_fim' => $data_fim->format('Y-m-d')
    ];

    if (!empty($medicamento_id)) {
        $sql .= " AND t.medicamento_id = :medicamento_id";
        $params[':medicamento_id'] = $medicamento_id;
    }

    if (!empty($operador_id)) {
        $sql .= " AND t.usuario_id = :operador_id";
        $params[':operador_id'] = $operador_id;
    }

    if (!empty($paciente_id)) {
        $sql .= " AND t.paciente_id = :paciente_id";
        $params[':paciente_id'] = $paciente_id;
    }

    $sql .= " ORDER BY t.data DESC";
} elseif ($tipo_relatorio === 'importacoes') {
    // Relatório de importações - sem filtro de data
    $sql = "SELECT li.*, u.nome as usuario_nome
            FROM logs_importacao li
            LEFT JOIN usuarios u ON li.usuario_id = u.id";
    
    $params = [];

    if (!empty($operador_id)) {
        $sql .= " WHERE li.usuario_id = :operador_id";
        $params[':operador_id'] = $operador_id;
    }

    $sql .= " ORDER BY li.data_hora DESC";
} elseif ($tipo_relatorio === 'agendamentos') {
    // Relatório de agendamentos
    $sql = "SELECT a.*, p.nome as paciente_nome, p.cpf as paciente_cpf, p.telefone as paciente_telefone, p.telefone2 as paciente_telefone2,
                   u.nome as operador_nome
            FROM agenda a
            JOIN pacientes p ON a.paciente_id = p.id
            JOIN usuarios u ON a.usuario_id = u.id
            WHERE a.data BETWEEN :data_inicio AND :data_fim";

    $params = [
        ':data_inicio' => $data_inicio->format('Y-m-d'),
        ':data_fim' => $data_fim->format('Y-m-d')
    ];

    if (!empty($operador_id)) {
        $sql .= " AND a.usuario_id = :operador_id";
        $params[':operador_id'] = $operador_id;
    }
    if (!empty($paciente_id)) {
        $sql .= " AND a.paciente_id = :paciente_id";
        $params[':paciente_id'] = $paciente_id;
    }
    
    $sql .= " ORDER BY a.data ASC, a.horario ASC";
} elseif ($tipo_relatorio === 'ajuste_estoque') {
    // Relatório de ajustes de estoque
    $sql = "SELECT m.*, med.nome as medicamento_nome, 
                   COALESCE(u.nome, 'Sistema') as responsavel_nome
            FROM movimentacoes m
            JOIN medicamentos med ON m.medicamento_id = med.id
            LEFT JOIN usuarios u ON m.usuario_id = u.id
            WHERE m.tipo = 'AJUSTE' 
            AND DATE(m.data) BETWEEN :data_inicio AND :data_fim";

    $params = [
        ':data_inicio' => $data_inicio->format('Y-m-d'),
        ':data_fim' => $data_fim->format('Y-m-d')
    ];

    if (!empty($medicamento_id)) {
        $sql .= " AND m.medicamento_id = :medicamento_id";
        $params[':medicamento_id'] = $medicamento_id;
    }
    
    $sql .= " ORDER BY m.data DESC";
} else {
    // Relatório de pacientes
    $sql = "SELECT p.id, p.nome, p.cpf, p.telefone, p.telefone2, 
                   pm.renovacao as data_renovacao, 
                   pm.renovado,
                   m.nome as medicamento_nome
            FROM pacientes p
            INNER JOIN paciente_medicamentos pm ON p.id = pm.paciente_id
            INNER JOIN medicamentos m ON pm.medicamento_id = m.id
            WHERE p.ativo = 1";
    
    $params = [];
    $today = (new DateTime())->format('Y-m-d');
    
    if (!empty($status_paciente)) {
        if ($status_paciente === 'vencido') {
            $sql .= " AND pm.renovacao < :hoje";
            $params[':hoje'] = $today;
        } elseif ($status_paciente === 'a_vencer') {
            $sql .= " AND pm.renovacao BETWEEN :hoje_inicio AND :hoje_fim";
            $params[':hoje_inicio'] = $today;
            $params[':hoje_fim'] = (new DateTime($today))->modify('+30 days')->format('Y-m-d');
        } elseif ($status_paciente === 'renovado') {
            $sql .= " AND pm.renovacao > DATE_ADD(:hoje, INTERVAL 30 DAY)";
            $params[':hoje'] = $today;
        } elseif ($status_paciente === 'renovacao_andamento') {
            $sql .= " AND pm.renovado = 1";
        }
    }
    
    if (!empty($paciente_id)) {
        $sql .= " AND p.id = :paciente_id";
        $params[':paciente_id'] = $paciente_id;
    }
    
    $sql .= " ORDER BY p.nome, m.nome";
}

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $resultados = $stmt->fetchAll();
    
    // Debug information
    error_log("SQL Query: " . $sql);
    error_log("Parameters: " . print_r($params, true));
    error_log("Number of results: " . count($resultados));

    if (empty($resultados)) {
        die("Nenhum resultado encontrado para os filtros selecionados");
    }
} catch (PDOException $e) {
    die("Erro na consulta: " . $e->getMessage());
}

// Criar nova planilha
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

if ($tipo_relatorio === 'dispensas') {
    // Definir cabeçalhos para dispensas
    $sheet->setCellValue('A1', 'Data');
    $sheet->setCellValue('B1', 'Medicamento');
    $sheet->setCellValue('C1', 'Quantidade');
    $sheet->setCellValue('D1', 'Operador');
    $sheet->setCellValue('E1', 'Paciente');
    $sheet->setCellValue('F1', 'CPF');
    $sheet->setCellValue('G1', 'Observações');

    // Estilo para o cabeçalho
    $headerStyle = [
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E9ECEF']
        ]
    ];
    $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);

    // Preencher dados
    $row = 2;
    foreach ($resultados as $dispensa) {
        $sheet->setCellValue('A' . $row, date('d/m/Y H:i', strtotime($dispensa['data'])));
        $sheet->setCellValue('B' . $row, $dispensa['medicamento_nome']);
        $sheet->setCellValue('C' . $row, $dispensa['quantidade']);
        $sheet->setCellValue('D' . $row, $dispensa['operador_nome']);
        $sheet->setCellValue('E' . $row, $dispensa['paciente_nome']);
        $sheet->setCellValue('F' . $row, $dispensa['paciente_cpf']);
        $sheet->setCellValue('G' . $row, $dispensa['observacoes'] ?? '');
        $row++;
    }

    // Ajustar largura das colunas
    foreach (range('A', 'G') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
} elseif ($tipo_relatorio === 'extornos') {
    // Definir cabeçalhos para extornos
    $sheet->setCellValue('A1', 'Data');
    $sheet->setCellValue('B1', 'Medicamento');
    $sheet->setCellValue('C1', 'Quantidade Extornada');
    $sheet->setCellValue('D1', 'Operador');
    $sheet->setCellValue('E1', 'Paciente');
    $sheet->setCellValue('F1', 'CPF');
    $sheet->setCellValue('G1', 'Observações');

    // Estilo para o cabeçalho
    $headerStyle = [
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E9ECEF']
        ]
    ];
    $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);

    // Preencher dados
    $row = 2;
    foreach ($resultados as $extorno) {
        $sheet->setCellValue('A' . $row, date('d/m/Y H:i', strtotime($extorno['data'])));
        $sheet->setCellValue('B' . $row, $extorno['medicamento_nome']);
        $sheet->setCellValue('C' . $row, abs($extorno['quantidade'])); // Usar valor absoluto
        $sheet->setCellValue('D' . $row, $extorno['operador_nome']);
        $sheet->setCellValue('E' . $row, $extorno['paciente_nome']);
        $sheet->setCellValue('F' . $row, $extorno['paciente_cpf']);
        $sheet->setCellValue('G' . $row, $extorno['observacoes'] ?? '');
        $row++;
    }

    // Ajustar largura das colunas
    foreach (range('A', 'G') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
} elseif ($tipo_relatorio === 'importacoes') {
    // Definir cabeçalhos para importações
    $sheet->setCellValue('A1', 'Data/Hora');
    $sheet->setCellValue('B1', 'Usuário');
    $sheet->setCellValue('C1', 'Arquivo');
    $sheet->setCellValue('D1', 'Quantidade de Registros');
    $sheet->setCellValue('E1', 'Status');

    // Estilo para o cabeçalho
    $headerStyle = [
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E9ECEF']
        ]
    ];
    $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);

    // Preencher dados
    $row = 2;
    foreach ($resultados as $importacao) {
        $sheet->setCellValue('A' . $row, date('d/m/Y H:i', strtotime($importacao['data_hora'])));
        $sheet->setCellValue('B' . $row, $importacao['usuario_nome'] ?? 'N/A');
        $sheet->setCellValue('C' . $row, $importacao['arquivo_nome'] ?? 'N/A');
        $sheet->setCellValue('D' . $row, $importacao['quantidade_registros'] ?? 'N/A');
        $sheet->setCellValue('E' . $row, $importacao['status'] ?? 'N/A');
        $row++;
    }

    // Ajustar largura das colunas
    foreach (range('A', 'E') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Adicionar detalhes das importações em abas separadas
    foreach ($resultados as $importacao) {
        if ($importacao['status'] === 'SUCESSO') {
            // Buscar detalhes dos medicamentos
            $stmt = $pdo->prepare("
                SELECT medicamento_nome as nome, quantidade, lote, validade, observacao as observacoes
                FROM logs_importacao_detalhes
                WHERE log_importacao_id = ? AND medicamento_nome IS NOT NULL
                ORDER BY medicamento_nome
            ");
            $stmt->execute([$importacao['id']]);
            $medicamentos = $stmt->fetchAll();
            
            // Buscar detalhes dos pacientes
            $stmt = $pdo->prepare("
                SELECT paciente_nome as nome, observacao as observacoes
                FROM logs_importacao_detalhes
                WHERE log_importacao_id = ? AND paciente_nome IS NOT NULL
                ORDER BY paciente_nome
            ");
            $stmt->execute([$importacao['id']]);
            $pacientes = $stmt->fetchAll();
            
            // Criar aba para medicamentos se houver
            if (!empty($medicamentos)) {
                $medSheet = $spreadsheet->createSheet();
                $medSheet->setTitle('Medicamentos_' . date('dmY_Hi', strtotime($importacao['data_hora'])));
                
                // Cabeçalhos
                $medSheet->setCellValue('A1', 'Nome do Medicamento');
                $medSheet->setCellValue('B1', 'Quantidade');
                $medSheet->setCellValue('C1', 'Lote');
                $medSheet->setCellValue('D1', 'Validade');
                $medSheet->setCellValue('E1', 'Observações');
                
                $medSheet->getStyle('A1:E1')->applyFromArray($headerStyle);
                
                // Dados
                $medRow = 2;
                foreach ($medicamentos as $med) {
                    $medSheet->setCellValue('A' . $medRow, $med['nome']);
                    $medSheet->setCellValue('B' . $medRow, $med['quantidade']);
                    $medSheet->setCellValue('C' . $medRow, $med['lote'] ?? '');
                    $medSheet->setCellValue('D' . $medRow, $med['validade'] ?? '');
                    $medSheet->setCellValue('E' . $medRow, $med['observacoes'] ?? '');
                    $medRow++;
                }
                
                foreach (range('A', 'E') as $col) {
                    $medSheet->getColumnDimension($col)->setAutoSize(true);
                }
            }
            
            // Criar aba para pacientes se houver
            if (!empty($pacientes)) {
                $pacSheet = $spreadsheet->createSheet();
                $pacSheet->setTitle('Pacientes_' . date('dmY_Hi', strtotime($importacao['data_hora'])));
                
                // Cabeçalhos
                $pacSheet->setCellValue('A1', 'Nome do Paciente');
                $pacSheet->setCellValue('B1', 'Observações');
                
                $pacSheet->getStyle('A1:B1')->applyFromArray($headerStyle);
                
                // Dados
                $pacRow = 2;
                foreach ($pacientes as $pac) {
                    $pacSheet->setCellValue('A' . $pacRow, $pac['nome']);
                    $pacSheet->setCellValue('B' . $pacRow, $pac['observacoes'] ?? '');
                    $pacRow++;
                }
                
                foreach (range('A', 'B') as $col) {
                    $pacSheet->getColumnDimension($col)->setAutoSize(true);
                }
            }
        }
    }
} elseif ($tipo_relatorio === 'agendamentos') {
    // Definir cabeçalhos para agendamentos
    $sheet->setCellValue('A1', 'Data');
    $sheet->setCellValue('B1', 'Horário');
    $sheet->setCellValue('C1', 'Paciente');
    $sheet->setCellValue('D1', 'CPF');
    $sheet->setCellValue('E1', 'Telefone');
    $sheet->setCellValue('F1', 'Status');
    $sheet->setCellValue('G1', 'Tipo');
    $sheet->setCellValue('H1', 'Operador');
    $sheet->setCellValue('I1', 'Observações');

    // Estilo para o cabeçalho
    $headerStyle = [
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E9ECEF']
        ]
    ];
    $sheet->getStyle('A1:I1')->applyFromArray($headerStyle);

    // Preencher dados
    $row = 2;
    foreach ($resultados as $agendamento) {
        $sheet->setCellValue('A' . $row, date('d/m/Y', strtotime($agendamento['data'])));
        $sheet->setCellValue('B' . $row, $agendamento['horario']);
        $sheet->setCellValue('C' . $row, $agendamento['paciente_nome']);
        $sheet->setCellValue('D' . $row, $agendamento['paciente_cpf']);
        $sheet->setCellValue('E' . $row, $agendamento['paciente_telefone'] . (!empty($agendamento['paciente_telefone2']) ? ' / ' . $agendamento['paciente_telefone2'] : ''));
        
        // Status com cores
        $status_labels = [
            'agendado' => 'Agendado',
            'confirmado' => 'Confirmado',
            'cancelado' => 'Cancelado',
            'realizado' => 'Realizado'
        ];
        $status = $status_labels[$agendamento['status']] ?? ucfirst($agendamento['status']);
        $sheet->setCellValue('F' . $row, $status);
        
        // Tipo (Normal/Encaixe)
        $tipo = ($agendamento['encaixe'] == 1) ? 'Encaixe' : 'Normal';
        $sheet->setCellValue('G' . $row, $tipo);
        
        $sheet->setCellValue('H' . $row, $agendamento['operador_nome']);
        $sheet->setCellValue('I' . $row, $agendamento['observacoes'] ?? '');
        
        $row++;
    }

    // Ajustar largura das colunas
    foreach (range('A', 'I') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
} elseif ($tipo_relatorio === 'ajuste_estoque') {
    // Definir cabeçalhos para ajustes de estoque
    $sheet->setCellValue('A1', 'Data/Hora');
    $sheet->setCellValue('B1', 'Medicamento');
    $sheet->setCellValue('C1', 'Quantidade Anterior');
    $sheet->setCellValue('D1', 'Quantidade Nova');
    $sheet->setCellValue('E1', 'Diferença');
    $sheet->setCellValue('F1', 'Responsável');
    $sheet->setCellValue('G1', 'Observações');

    // Estilo para o cabeçalho
    $headerStyle = [
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E9ECEF']
        ]
    ];
    $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);

    // Preencher dados
    $row = 2;
    foreach ($resultados as $ajuste) {
        $sheet->setCellValue('A' . $row, date('d/m/Y H:i', strtotime($ajuste['data'])));
        $sheet->setCellValue('B' . $row, $ajuste['medicamento_nome']);
        $sheet->setCellValue('C' . $row, $ajuste['quantidade_anterior']);
        $sheet->setCellValue('D' . $row, $ajuste['quantidade_nova']);
        
        // Diferença com sinal
        $diferenca = $ajuste['quantidade'] > 0 ? '+' . $ajuste['quantidade'] : $ajuste['quantidade'];
        $sheet->setCellValue('E' . $row, $diferenca);
        
        // Aplicar cor baseada no tipo de ajuste
        if ($ajuste['quantidade'] > 0) {
            $sheet->getStyle('E' . $row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('28a745'));
        } else {
            $sheet->getStyle('E' . $row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('dc3545'));
        }
        
        $sheet->setCellValue('F' . $row, $ajuste['responsavel_nome'] ?? 'Sistema');
        $sheet->setCellValue('G' . $row, $ajuste['observacao'] ?? '');
        
        $row++;
    }

    // Ajustar largura das colunas
    foreach (range('A', 'G') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
} else {
    // Definir cabeçalhos para pacientes
    $sheet->setCellValue('A1', 'Nome');
    $sheet->setCellValue('B1', 'CPF');
    $sheet->setCellValue('C1', 'Telefone');
    $sheet->setCellValue('D1', 'Medicamento');
    $sheet->setCellValue('E1', 'Data Renovação');
    $sheet->setCellValue('F1', 'Status');
    $sheet->setCellValue('G1', 'Renovação em Andamento');

    // Estilo para o cabeçalho
    $headerStyle = [
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E9ECEF']
        ]
    ];
    $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);

    // Preencher dados
    $row = 2;
    $hoje = new DateTime('today');
    foreach ($resultados as $pac) {
        $data_formatada = '-';
        $status = 'Sem renovação';
        $cor_status = '#6c757d';

        if (!empty($pac['data_renovacao'])) {
            try {
                $data_renovacao = preg_match('/^\d{4}-\d{2}-\d{2}$/', $pac['data_renovacao'])
                    ? new DateTime($pac['data_renovacao'])
                    : DateTime::createFromFormat('d/m/Y', $pac['data_renovacao']);
                
                if ($data_renovacao) {
                    $data_formatada = $data_renovacao->format('d/m/Y');
                    $data_renovacao->setTime(0,0,0);
                    
                    $diff = $hoje->diff($data_renovacao)->days;
                    $is_past = $data_renovacao < $hoje;

                    if ($is_past) {
                        $status = 'Vencido';
                        $cor_status = '#dc3545';
                    } elseif ($diff <= 30) {
                        $status = 'A vencer';
                        $cor_status = '#ffc107';
                    } else {
                        $status = 'Válido';
                        $cor_status = '#28a745';
                    }
                }
            } catch (Exception $e) {
                // Mantém status padrão em caso de erro
            }
        }

        $sheet->setCellValue('A' . $row, $pac['nome']);
        $sheet->setCellValue('B' . $row, $pac['cpf']);
        $sheet->setCellValue('C' . $row, $pac['telefone']);
        $sheet->setCellValue('D' . $row, $pac['medicamento_nome'] ?? '-');
        $sheet->setCellValue('E' . $row, $data_formatada);
        $sheet->setCellValue('F' . $row, $status);
        $sheet->setCellValue('G' . $row, ((int)$pac['renovado'] === 1) ? 'Sim' : 'Não');
        
        // Aplicar cor ao status
        $sheet->getStyle('F' . $row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($cor_status));
        
        $row++;
    }

    // Ajustar largura das colunas
    foreach (range('A', 'G') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
}

// Configurar cabeçalhos HTTP para download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="relatorio_' . ($tipo_relatorio === 'dispensas' ? 'dispensas' : ($tipo_relatorio === 'extornos' ? 'extornos' : ($tipo_relatorio === 'agendamentos' ? 'agendamentos' : ($tipo_relatorio === 'importacoes' ? 'importacoes' : ($tipo_relatorio === 'ajuste_estoque' ? 'ajuste_estoque' : 'pacientes'))))) . '_' . date('Y-m-d') . '.xlsx"');
header('Cache-Control: max-age=0');

// Criar arquivo Excel
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
