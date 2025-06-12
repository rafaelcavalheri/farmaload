<?php
include 'config.php';
include 'funcoes_estoque.php';

// Validação de entrada
$paciente_id = filter_input(INPUT_GET, 'paciente_id', FILTER_VALIDATE_INT);
if (!$paciente_id) {
    http_response_code(400);
    exit("<p class='alert erro'>ID do paciente inválido.</p>");
}

try {
    // Consulta aos medicamentos do paciente
    $stmt = $pdo->prepare("
        SELECT 
            pm.*, 
            m.nome AS nome_medicamento_cadastrado,
            COALESCE(pm.quantidade_solicitada, pm.quantidade) as quantidade_solicitada,
            pm.renovacao as proxima_renovacao
        FROM paciente_medicamentos pm
        LEFT JOIN medicamentos m ON m.id = pm.medicamento_id
        WHERE pm.paciente_id = ?
        ORDER BY m.nome ASC
    ");
    $stmt->execute([$paciente_id]);
    $medicamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($medicamentos)) {
        echo "<p class='alert'>Nenhum medicamento cadastrado para este paciente.</p>";
        exit;
    }

    // Renderizar lista de medicamentos
    echo '<ul style="list-style-type: none; padding: 0; margin: 0;">';

    foreach ($medicamentos as $med) {
        $nome = !empty($med['medicamento_id']) 
            ? ($med['nome_medicamento_cadastrado'] ?? 'Medicamento não encontrado') 
            : ($med['nome_medicamento'] ?? 'Medicamento desconhecido');

        echo '<li style="margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid #eee;">';
        echo '<strong>' . htmlspecialchars($nome) . '</strong><br>';
        echo 'Quantidade Solicitada: ' . htmlspecialchars($med['quantidade_solicitada'] ?? '0') . '<br>';

        if (!empty($med['cid'])) {
            echo 'CID: ' . htmlspecialchars($med['cid']) . '<br>';
        }

        // Adicionar informação da próxima renovação
        if (!empty($med['proxima_renovacao'])) {
            // Converter data do formato brasileiro para DateTime
            $dataRenovacao = DateTime::createFromFormat('d/m/Y', $med['proxima_renovacao']);
            if (!$dataRenovacao) {
                // Se falhar, tenta converter do formato ISO
                $dataRenovacao = new DateTime($med['proxima_renovacao']);
            }
            
            if ($dataRenovacao) {
                $hoje = new DateTime();
                
                if ($dataRenovacao < $hoje) {
                    echo '<span style="color: #dc3545;">Próxima Renovação: ' . $dataRenovacao->format('d/m/Y') . ' (Atrasada)</span><br>';
                } elseif ($dataRenovacao->format('Y-m') === $hoje->format('Y-m')) {
                    echo '<span style="color: #ffc107;">Próxima Renovação: ' . $dataRenovacao->format('d/m/Y') . ' (Este mês)</span><br>';
                } else {
                    echo '<span style="color: #28a745;">Próxima Renovação: ' . $dataRenovacao->format('d/m/Y') . '</span><br>';
                }
            }
        }

        echo '</li>';
    }

    echo '</ul>';

} catch (PDOException $e) {
    http_response_code(500);
    echo '<div class="alert erro">Erro ao carregar medicamentos: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>
