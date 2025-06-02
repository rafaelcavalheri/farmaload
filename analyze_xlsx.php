<?php
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

function analyzeFile($filename) {
    echo "Analisando arquivo: $filename\n";
    try {
        $spreadsheet = IOFactory::load($filename);
        echo "Abas encontradas: " . implode(', ', $spreadsheet->getSheetNames()) . "\n";
        
        $worksheet = $spreadsheet->getActiveSheet();
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
        
    } catch (Exception $e) {
        echo "Erro ao analisar arquivo: " . $e->getMessage() . "\n";
    }
    echo "\n----------------------------------------\n";
}

// Analisar ambos os arquivos
analyzeFile('MMIRIM.xlsx');
analyzeFile('MMIRIM_22abril.xlsx'); 