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

// Debug information
error_log("Tipo de relatório: " . $tipo_relatorio);
error_log("Filtros: " . print_r($_GET, true));

if ($tipo_relatorio === 'dispensas') {
    // Construção da query de dispensas
    $sql = "SELECT t.*, m.nome as medicamento_nome, u.nome as operador_nome, 
                   p.nome as paciente_nome, p.cpf as paciente_cpf, p.telefone as paciente_telefone
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
} else {
    // Relatório de pacientes
    $sql = "SELECT p.id, p.nome, p.cpf, p.telefone, 
                   pm.renovacao as data_renovacao, 
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
    $sheet->setCellValue('G1', 'Telefone');
    $sheet->setCellValue('H1', 'Observações');

    // Estilo para o cabeçalho
    $headerStyle = [
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E9ECEF']
        ]
    ];
    $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);

    // Preencher dados
    $row = 2;
    foreach ($resultados as $dispensa) {
        $sheet->setCellValue('A' . $row, date('d/m/Y H:i', strtotime($dispensa['data'])));
        $sheet->setCellValue('B' . $row, $dispensa['medicamento_nome']);
        $sheet->setCellValue('C' . $row, $dispensa['quantidade']);
        $sheet->setCellValue('D' . $row, $dispensa['operador_nome']);
        $sheet->setCellValue('E' . $row, $dispensa['paciente_nome']);
        $sheet->setCellValue('F' . $row, $dispensa['paciente_cpf']);
        $sheet->setCellValue('G' . $row, $dispensa['paciente_telefone']);
        $sheet->setCellValue('H' . $row, $dispensa['observacoes'] ?? '');
        $row++;
    }

    // Ajustar largura das colunas
    foreach (range('A', 'H') as $col) {
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

    // Estilo para o cabeçalho
    $headerStyle = [
        'font' => ['bold' => true],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E9ECEF']
        ]
    ];
    $sheet->getStyle('A1:F1')->applyFromArray($headerStyle);

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
        
        // Aplicar cor ao status
        $sheet->getStyle('F' . $row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($cor_status));
        
        $row++;
    }

    // Ajustar largura das colunas
    foreach (range('A', 'F') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
}

// Configurar cabeçalhos HTTP para download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="relatorio_' . ($tipo_relatorio === 'dispensas' ? 'dispensas' : 'pacientes') . '_' . date('Y-m-d') . '.xlsx"');
header('Cache-Control: max-age=0');

// Criar arquivo Excel
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
