<?php
require 'vendor/autoload.php';
include 'config.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Verificar autenticação
$permitidos = ['admin'];
if (!in_array($_SESSION['usuario']['perfil'] ?? '', $permitidos)) {
    header('Location: login.php');
    exit();
}

$mensagem = '';
$sucesso = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['arquivo'])) {
    $arquivo = $_FILES['arquivo'];
    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    
    try {
        if ($extensao === 'csv') {
            $dados = processarCSV($arquivo['tmp_name']);
        } elseif (in_array($extensao, ['xls', 'xlsx'])) {
            $dados = processarExcel($arquivo['tmp_name']);
        } else {
            throw new Exception('Formato de arquivo não suportado. Use CSV, XLS ou XLSX.');
        }

        importarDados($dados);
        
        header('Location: medicamentos.php?sucesso=' . urlencode(count($dados) . " medicamentos importados com sucesso!"));
        exit();

    } catch (Exception $e) {
        header('Location: medicamentos.php?erro=' . urlencode($e->getMessage()));
        exit();
    }
} else {
    header('Location: medicamentos.php?erro=' . urlencode('Nenhum arquivo enviado'));
    exit();
}

function processarCSV($arquivo) {
    $dados = [];
    if (($handle = fopen($arquivo, 'r')) !== false) {
        // Pular cabeçalho
        $cabecalho = fgetcsv($handle);
        validarCabecalho($cabecalho);
        
        $linha = 2;
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === 4) {
                $dados[] = validarLinha($row, $linha);
            } else {
                throw new Exception("Linha $linha: número incorreto de campos");
            }
            $linha++;
        }
        fclose($handle);
    }
    return $dados;
}

function processarExcel($arquivo) {
    $dados = [];
    $spreadsheet = IOFactory::load($arquivo);
    $worksheet = $spreadsheet->getActiveSheet();
    
    // Validar cabeçalho
    $cabecalho = [];
    for ($col = 'A'; $col <= 'D'; $col++) {
        $cabecalho[] = $worksheet->getCell($col . '1')->getValue();
    }
    validarCabecalho($cabecalho);
    
    // Processar linhas
    $linha = 2;
    while ($worksheet->getCell('A' . $linha)->getValue() !== null) {
        $row = [
            $worksheet->getCell('A' . $linha)->getValue(),
            $worksheet->getCell('B' . $linha)->getValue(),
            $worksheet->getCell('C' . $linha)->getValue(),
            $worksheet->getCell('D' . $linha)->getValue()
        ];
        
        $dados[] = validarLinha($row, $linha);
        $linha++;
    }
    
    return $dados;
}

function validarCabecalho($cabecalho) {
    $cabecalhoEsperado = ['Nome do Medicamento', 'Quantidade', 'Lote', 'Validade'];
    if ($cabecalho !== $cabecalhoEsperado) {
        throw new Exception('Formato do arquivo inválido. Use o modelo fornecido.');
    }
}

function validarLinha($dados, $linha) {
    list($nome, $quantidade, $lote, $validade) = $dados;

    // Validar dados
    if (empty($nome) || empty($quantidade) || empty($lote) || empty($validade)) {
        throw new Exception("Linha $linha: todos os campos são obrigatórios");
    }

    // Validar e converter quantidade
    if (!is_numeric($quantidade) || $quantidade < 0) {
        throw new Exception("Linha $linha: quantidade inválida");
    }
    $quantidade = (int)$quantidade;

    // Validar e converter data
    if (!preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $validade)) {
        throw new Exception("Linha $linha: formato de data inválido (use DD/MM/AAAA)");
    }
    $validadeObj = DateTime::createFromFormat('d/m/Y', $validade);
    if (!$validadeObj) {
        throw new Exception("Linha $linha: data inválida");
    }
    
    return [
        'nome' => $nome,
        'quantidade' => $quantidade,
        'lote' => $lote,
        'validade' => $validadeObj->format('Y-m-d')
    ];
}

function importarDados($dados) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        foreach ($dados as $item) {
            // Verificar se o medicamento já existe
            $stmt = $pdo->prepare("SELECT id FROM medicamentos WHERE nome = ? AND lote = ?");
            $stmt->execute([$item['nome'], $item['lote']]);
            $medicamentoExistente = $stmt->fetch();

            if ($medicamentoExistente) {
                // Atualizar medicamento existente
                $stmt = $pdo->prepare("
                    UPDATE medicamentos 
                    SET quantidade = quantidade + ?, 
                        validade = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$item['quantidade'], $item['validade'], $medicamentoExistente['id']]);
            } else {
                // Inserir novo medicamento
                $stmt = $pdo->prepare("
                    INSERT INTO medicamentos (nome, quantidade, lote, validade) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$item['nome'], $item['quantidade'], $item['lote'], $item['validade']]);
            }
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
} 