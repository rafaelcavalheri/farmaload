<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

// Recebe os dados do CSV
$csvData = file_get_contents('php://input');

if (empty($csvData)) {
    echo json_encode([
        'success' => false,
        'message' => 'Dados CSV não fornecidos'
    ]);
    exit;
}

// Processa o CSV
$lines = explode("\n", $csvData);
$header = str_getcsv(array_shift($lines)); // Remove e processa o cabeçalho

// Verifica se o cabeçalho está correto
$requiredColumns = ['ID', 'Nome', 'Apresentação', 'Código', 'Quantidade'];
if (count(array_intersect($header, $requiredColumns)) !== count($requiredColumns)) {
    echo json_encode([
        'success' => false,
        'message' => 'Formato CSV inválido. Colunas necessárias: ' . implode(', ', $requiredColumns)
    ]);
    exit;
}

$registrosImportados = 0;
$erros = [];

// Prepara a query de atualização
$sql = "UPDATE medicamentos SET quantidade = ? WHERE id = ?";
$stmt = $conn->prepare($sql);

foreach ($lines as $line) {
    if (empty(trim($line))) continue;
    
    $data = str_getcsv($line);
    if (count($data) !== count($header)) continue;
    
    $row = array_combine($header, $data);
    
    try {
        $id = intval($row['ID']);
        $quantidade = intval($row['Quantidade']);
        
        if ($id > 0 && $quantidade >= 0) {
            $stmt->bind_param("ii", $quantidade, $id);
            if ($stmt->execute()) {
                $registrosImportados++;
            } else {
                $erros[] = "Erro ao atualizar ID {$id}: " . $stmt->error;
            }
        }
    } catch (Exception $e) {
        $erros[] = "Erro ao processar linha: " . $e->getMessage();
    }
}

$stmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'registrosImportados' => $registrosImportados,
    'message' => $registrosImportados . ' registros importados com sucesso' . 
                (count($erros) > 0 ? '. Erros: ' . implode(', ', $erros) : '')
]); 