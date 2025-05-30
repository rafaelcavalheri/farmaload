<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$arquivo = 'MMIRIM.xls';
$spreadsheet = IOFactory::load($arquivo);
$sheetNames = $spreadsheet->getSheetNames();
echo "Abas encontradas em $arquivo:\n";
foreach ($sheetNames as $sheet) {
    echo "- $sheet\n";
} 