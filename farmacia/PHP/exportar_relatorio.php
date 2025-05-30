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
    $data_inicio = new DateTime($_GET['data_inicio'] ?? 'first day of this month');
    $data_fim = new DateTime($_GET['data_fim'] ?? 'last day of this month');
} catch (Exception $e) {
    die("Formato de data inválido");
}

// Parâmetros dos filtros
$medicamento_id = $_GET['medicamento_id'] ?? '';
$operador_id = $_GET['operador_id'] ?? '';
$paciente_id = $_GET['paciente_id'] ?? '';

// Construção da query
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

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $resultados = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Erro na consulta: " . $e->getMessage());
}

// Criar nova planilha
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Definir cabeçalhos
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

// Configurar cabeçalhos HTTP para download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="relatorio_' . date('Y-m-d') . '.xlsx"');
header('Cache-Control: max-age=0');

// Criar arquivo Excel
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
