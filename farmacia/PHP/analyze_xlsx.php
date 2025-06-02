<?php
require_once '/var/www/html/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

function analyzeFile($filename) {
    echo "Analisando arquivo: $filename\n";
    try {
        $spreadsheet = IOFactory::load($filename);
        echo "Abas encontradas: " . implode(', ', $spreadsheet->getSheetNames()) . "\n";
        
        foreach ($spreadsheet->getSheetNames() as $sheetName) {
            echo "\n=== Aba: $sheetName ===\n";
            $worksheet = $spreadsheet->getSheetByName($sheetName);
            $highestRow = $worksheet->getHighestRow();
            $highestColumn = $worksheet->getHighestColumn();
            
            echo "Última linha: $highestRow\n";
            echo "Última coluna: $highestColumn\n";
            
            // Analisar primeira linha (cabeçalho)
            echo "\nPrimeira linha (cabeçalho):\n";
            for ($col = 'A'; $col <= $highestColumn; $col++) {
                $value = $worksheet->getCell($col . '1')->getValue();
                echo "$col: $value\n";
            }
            
            // Analisar algumas linhas de dados
            echo "\nPrimeiras 3 linhas de dados:\n";
            for ($row = 2; $row <= min(4, $highestRow); $row++) {
                echo "Linha $row: ";
                for ($col = 'A'; $col <= $highestColumn; $col++) {
                    $value = $worksheet->getCell($col . $row)->getValue();
                    echo "$col=$value | ";
                }
                echo "\n";
            }
        }
        
    } catch (Exception $e) {
        echo "Erro ao analisar arquivo: " . $e->getMessage() . "\n";
    }
    echo "\n----------------------------------------\n";
}

// Analisar ambos os arquivos
$files = [
    '/var/www/html/MMIRIM.xlsx',
    '/var/www/html/MMIRIM_22abril.xlsx'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        analyzeFile($file);
    } else {
        echo "Arquivo não encontrado: $file\n";
    }
} 