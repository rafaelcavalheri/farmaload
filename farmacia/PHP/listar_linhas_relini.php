<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$arquivo = 'MMIRIM.xls';
$spreadsheet = IOFactory::load($arquivo);
$sheet = $spreadsheet->getSheetByName('RELINI_FIM');
if (!$sheet) {
    echo "Aba RELINI_FIM não encontrada.\n";
    exit(1);
}
$highestCol = $sheet->getHighestColumn();
$highestRow = $sheet->getHighestRow();

// Mostrar cabeçalho
$header = [];
for ($col = 'A'; $col <= $highestCol; $col++) {
    $header[] = $sheet->getCell($col.'1')->getValue();
}
echo "Colunas: ".implode(' | ', $header)."\n";

// Mostrar as 10 primeiras linhas
for ($row = 2; $row <= min(11, $highestRow); $row++) {
    $linha = [];
    for ($col = 'A'; $col <= $highestCol; $col++) {
        $linha[] = $sheet->getCell($col.$row)->getValue();
    }
    echo "Linha $row: ".implode(' | ', $linha)."\n";
} 