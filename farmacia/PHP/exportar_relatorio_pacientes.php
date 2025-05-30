<?php
require 'vendor/autoload.php';
include 'config.php';

verificarAutenticacao(['admin']);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Filtros
$status_paciente = $_GET['status_paciente'] ?? '';

// Query de pacientes
$sql = "SELECT nome, cpf, telefone, validade, renovado FROM pacientes WHERE ativo = 1";
if (!empty($status_paciente)) {
    if ($status_paciente === 'vencido') {
        $sql .= " AND validade < CURDATE() AND renovado = 0";
    } elseif ($status_paciente === 'a_vencer') {
        $sql .= " AND validade >= CURDATE() AND validade <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND renovado = 0";
    } elseif ($status_paciente === 'renovado') {
        $sql .= " AND renovado = 1";
    }
}
$sql .= " ORDER BY nome";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $pacientes = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Erro na consulta: " . $e->getMessage());
}

// Criar nova planilha
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Definir cabeçalhos
$sheet->setCellValue('A1', 'Nome');
$sheet->setCellValue('B1', 'CPF');
$sheet->setCellValue('C1', 'Telefone');
$sheet->setCellValue('D1', 'Validade');
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
$hoje = new DateTime();
foreach ($pacientes as $pac) {
    $validade = $pac['validade'] ? new DateTime($pac['validade']) : null;
    if ($pac['renovado']) {
        $status = 'Renovado';
    } elseif ($validade) {
        if ($validade < $hoje) {
            $status = 'Vencido';
        } elseif ($validade <= (clone $hoje)->modify('+30 days')) {
            $status = 'A vencer';
        } else {
            $status = 'Válido';
        }
    } else {
        $status = 'Sem validade';
    }
    $sheet->setCellValue('A' . $row, $pac['nome']);
    $sheet->setCellValue('B' . $row, $pac['cpf']);
    $sheet->setCellValue('C' . $row, $pac['telefone']);
    $sheet->setCellValue('D' . $row, $validade ? $validade->format('d/m/Y') : '-');
    $sheet->setCellValue('E' . $row, $status);
    $row++;
}

// Ajustar largura das colunas
foreach (range('A', 'E') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Configurar cabeçalhos HTTP para download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="relatorio_pacientes_' . date('Y-m-d') . '.xlsx"');
header('Cache-Control: max-age=0');

// Criar arquivo Excel
$writer = new Xlsx($spreadsheet);
$writer->save('php://output'); 