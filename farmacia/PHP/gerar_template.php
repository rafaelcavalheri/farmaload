<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
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

// Adicionar bordas em todas as células preenchidas
$sheet1->getStyle('A1:E' . ($row-1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

// Criar diretório templates se não existir
if (!file_exists(__DIR__ . '/../templates')) {
    mkdir(__DIR__ . '/../templates', 0777, true);
}

// Salvar o arquivo
$writer = new Xls($spreadsheet);
$writer->save(__DIR__ . '/../templates/modelo_importacao.xls');

echo "Arquivo modelo gerado com sucesso!\n"; 