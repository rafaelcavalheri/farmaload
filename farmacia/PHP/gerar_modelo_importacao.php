<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Criar nova planilha
$spreadsheet = new Spreadsheet();

// Remover a aba padrão
$spreadsheet->removeSheetByIndex(0);

// ===== ABA 1: FORMATO PADRÃO =====
$sheet1 = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Formato Padrão');
$spreadsheet->addSheet($sheet1, 0);

// Definir cabeçalhos
$headers = [
    'A1' => 'Nome do Medicamento',
    'B1' => 'Quantidade',
    'C1' => 'Lote',
    'D1' => 'Validade',
    'E1' => 'Nome do Paciente'
];

// Adicionar cabeçalhos
foreach ($headers as $cell => $value) {
    $sheet1->setCellValue($cell, $value);
}

// Estilizar cabeçalhos
$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF']
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '2196F3']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN
        ]
    ]
];

$sheet1->getStyle('A1:E1')->applyFromArray($headerStyle);

// Adicionar exemplos
$examples = [
    ['Paracetamol 500mg', 100, 'LOT001', '31/12/2024', 'João Silva'],
    ['Dipirona 500mg', 50, 'LOT002', '31/12/2024', 'Maria Santos'],
    ['Omeprazol 20mg', 30, 'LOT003', '31/12/2024', 'José Oliveira'],
    ['Amoxicilina 500mg', 40, 'LOT004', '31/12/2024', 'Ana Costa'],
    ['Losartana 50mg', 60, 'LOT005', '31/12/2024', 'Pedro Souza']
];

$row = 2;
foreach ($examples as $example) {
    $sheet1->setCellValue('A' . $row, $example[0]);
    $sheet1->setCellValue('B' . $row, $example[1]);
    $sheet1->setCellValue('C' . $row, $example[2]);
    $sheet1->setCellValue('D' . $row, $example[3]);
    $sheet1->setCellValue('E' . $row, $example[4]);
    $row++;
}

// Ajustar largura das colunas
$sheet1->getColumnDimension('A')->setWidth(30);
$sheet1->getColumnDimension('B')->setWidth(15);
$sheet1->getColumnDimension('C')->setWidth(15);
$sheet1->getColumnDimension('D')->setWidth(15);
$sheet1->getColumnDimension('E')->setWidth(30);

// ===== ABA 2: RELINI_FIM =====
$sheet2 = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'RELINI_FIM');
$spreadsheet->addSheet($sheet2, 1);

// Definir cabeçalhos RELINI_FIM
$headersRelini = [
    'A1' => 'LMES',
    'B1' => 'FIM VAL.',
    'C1' => 'PACIENTES',
    'D1' => 'DT ATEND.',
    'E1' => 'QTDE.',
    'F1' => 'MEDICAMENTOS',
    'G1' => 'CID'
];

// Adicionar cabeçalhos
foreach ($headersRelini as $cell => $value) {
    $sheet2->setCellValue($cell, $value);
}

// Estilizar cabeçalhos
$sheet2->getStyle('A1:G1')->applyFromArray($headerStyle);

// Adicionar exemplos RELINI_FIM
$examplesRelini = [
    ['LOT001', '31/12/2024', 'João Silva', '01/01/2024', 30, 'Paracetamol 500mg', 'J45.9'],
    ['LOT002', '31/12/2024', 'Maria Santos', '02/01/2024', 20, 'Dipirona 500mg', 'R50.9'],
    ['LOT003', '31/12/2024', 'José Oliveira', '03/01/2024', 15, 'Omeprazol 20mg', 'K21.9'],
    ['LOT004', '31/12/2024', 'Ana Costa', '04/01/2024', 25, 'Amoxicilina 500mg', 'J02.9'],
    ['LOT005', '31/12/2024', 'Pedro Souza', '05/01/2024', 40, 'Losartana 50mg', 'I10']
];

$row = 2;
foreach ($examplesRelini as $example) {
    $sheet2->setCellValue('A' . $row, $example[0]);
    $sheet2->setCellValue('B' . $row, $example[1]);
    $sheet2->setCellValue('C' . $row, $example[2]);
    $sheet2->setCellValue('D' . $row, $example[3]);
    $sheet2->setCellValue('E' . $row, $example[4]);
    $sheet2->setCellValue('F' . $row, $example[5]);
    $sheet2->setCellValue('G' . $row, $example[6]);
    $row++;
}

// Ajustar largura das colunas
$sheet2->getColumnDimension('A')->setWidth(15);
$sheet2->getColumnDimension('B')->setWidth(15);
$sheet2->getColumnDimension('C')->setWidth(30);
$sheet2->getColumnDimension('D')->setWidth(15);
$sheet2->getColumnDimension('E')->setWidth(15);
$sheet2->getColumnDimension('F')->setWidth(30);
$sheet2->getColumnDimension('G')->setWidth(15);

// ===== ABA 3: FORMATO LIVRE =====
$sheet3 = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, 'Formato Livre');
$spreadsheet->addSheet($sheet3, 2);

// Adicionar exemplos de formato livre
$examplesLivre = [
    ['Paracetamol 500mg', 'LOT001', '31/12/2024', 'João Silva', 30],
    ['', '', '', 'Maria Santos', 20],
    ['', '', '', 'José Oliveira', 15],
    ['Dipirona 500mg', 'LOT002', '31/12/2024', 'Ana Costa', 25],
    ['', '', '', 'Pedro Souza', 40],
    ['Total Dipirona 500mg', '', '', '', 85],
    ['Omeprazol 20mg', 'LOT003', '31/12/2024', 'Carlos Lima', 10],
    ['', '', '', 'Fernanda Santos', 5],
    ['Total Omeprazol 20mg', '', '', '', 15]
];

$row = 1;
foreach ($examplesLivre as $example) {
    $sheet3->setCellValue('A' . $row, $example[0]);
    $sheet3->setCellValue('B' . $row, $example[1]);
    $sheet3->setCellValue('C' . $row, $example[2]);
    $sheet3->setCellValue('D' . $row, $example[3]);
    $sheet3->setCellValue('E' . $row, $example[4]);
    $row++;
}

// Ajustar largura das colunas
$sheet3->getColumnDimension('A')->setWidth(30);
$sheet3->getColumnDimension('B')->setWidth(15);
$sheet3->getColumnDimension('C')->setWidth(15);
$sheet3->getColumnDimension('D')->setWidth(30);
$sheet3->getColumnDimension('E')->setWidth(15);

// Adicionar bordas em todas as células preenchidas
$sheet3->getStyle('A1:E' . ($row-1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Configurar cabeçalhos HTTP para download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="modelo_importacao.xlsx"');
header('Cache-Control: max-age=0');

// Criar o arquivo Excel
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit; 