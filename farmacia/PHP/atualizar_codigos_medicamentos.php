<?php
require __DIR__ . '/config.php';
verificarAutenticacao(['admin']);

try {
    // Iniciar transação
    $pdo->beginTransaction();

    // Buscar todos os medicamentos que não seguem o padrão MED
    $stmt = $pdo->query("SELECT id, nome, codigo FROM medicamentos WHERE codigo NOT LIKE 'MED%' ORDER BY id");
    $medicamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Buscar o maior código MED existente
    $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(codigo, 4) AS UNSIGNED)) as ultimo_codigo FROM medicamentos WHERE codigo LIKE 'MED%'");
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    $proximoNumero = ($resultado['ultimo_codigo'] ?? 0) + 1;

    $atualizados = 0;
    $erros = [];

    foreach ($medicamentos as $med) {
        try {
            // Gerar novo código
            $novoCodigo = 'MED' . str_pad($proximoNumero, 5, '0', STR_PAD_LEFT);
            
            // Atualizar o código do medicamento
            $stmt = $pdo->prepare("UPDATE medicamentos SET codigo = ? WHERE id = ?");
            $stmt->execute([$novoCodigo, $med['id']]);
            
            $atualizados++;
            $proximoNumero++;
        } catch (PDOException $e) {
            $erros[] = "Erro ao atualizar medicamento {$med['nome']}: " . $e->getMessage();
        }
    }

    // Confirmar transação
    $pdo->commit();

    // Mensagem de sucesso
    $mensagem = "Atualização concluída!<br>";
    $mensagem .= "Total de medicamentos atualizados: $atualizados<br>";
    
    if (!empty($erros)) {
        $mensagem .= "<br>Erros encontrados:<br>";
        foreach ($erros as $erro) {
            $mensagem .= "- $erro<br>";
        }
    }

} catch (Exception $e) {
    // Em caso de erro, desfazer todas as alterações
    $pdo->rollBack();
    $mensagem = "Erro durante a atualização: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Atualização de Códigos de Medicamentos</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="container">
        <h2>Atualização de Códigos de Medicamentos</h2>
        
        <div class="card">
            <div class="<?= !empty($erros) ? 'erro' : 'sucesso' ?>">
                <?= $mensagem ?>
            </div>
            
            <div class="form-actions" style="margin-top: 20px;">
                <a href="medicamentos.php" class="btn-secondary">Voltar para Medicamentos</a>
            </div>
        </div>
    </main>
</body>
</html> 