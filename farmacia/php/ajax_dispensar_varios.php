<?php
// Desabilitar exibição de erros para garantir que apenas JSON seja retornado
error_reporting(0);
ini_set('display_errors', 0);

require __DIR__ . '/config.php';
include 'funcoes_estoque.php';

// Definir cabeçalho para JSON
header('Content-Type: application/json');

try {
    verificarAutenticacao(['admin', 'operador']);

    if (!isset($_SESSION['usuario']) || !isset($_SESSION['usuario']['id'])) {
        throw new Exception('Sessão do usuário inválida. Por favor, faça login novamente.');
    }

    $usuario_id = (int)$_SESSION['usuario']['id'];

    // Validar dados de entrada
    $paciente_id = filter_input(INPUT_POST, 'paciente_id', FILTER_VALIDATE_INT);
    if (!$paciente_id) {
        throw new Exception('ID do paciente inválido');
    }

    $medicamentos = json_decode($_POST['medicamentos'] ?? '[]', true);
    if (empty($medicamentos)) {
        throw new Exception('Nenhum medicamento selecionado');
    }

    $observacao = trim($_POST['observacao'] ?? '');

    $pdo->beginTransaction();

    try {
        // Verificar se a observação foi modificada e atualizar se necessário
        $stmt = $pdo->prepare("SELECT observacao FROM pacientes WHERE id = ?");
        $stmt->execute([$paciente_id]);
        $paciente = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($paciente) {
            $observacao_atual = trim($paciente['observacao'] ?? '');
            if ($observacao_atual !== $observacao) {
                $stmt = $pdo->prepare("UPDATE pacientes SET observacao = ? WHERE id = ?");
                $stmt->execute([$observacao, $paciente_id]);
            }
        }

        // Processar cada medicamento
        $todos_lotes_utilizados = [];
        $avisos_dispensacao_recente = [];
        
        foreach ($medicamentos as $med) {
            $medicamento_id = $med['medicamento_id'];
            $quantidade = (int)$med['quantidade'];

            if ($quantidade <= 0) {
                continue;
            }

            // Verificar se o medicamento está associado ao paciente
            $stmt = $pdo->prepare("
                SELECT 
                    pm.id, 
                    pm.medicamento_id,
                    pm.quantidade,
                    COALESCE(pm.quantidade_solicitada, pm.quantidade) AS quantidade_solicitada,
                    COALESCE((
                        SELECT SUM(quantidade) 
                        FROM transacoes 
                        WHERE medicamento_id = pm.medicamento_id 
                        AND paciente_id = pm.paciente_id
                    ), 0) as quantidade_entregue,
                    m.nome as nome_medicamento
                FROM paciente_medicamentos pm
                JOIN medicamentos m ON m.id = pm.medicamento_id
                WHERE pm.id = ? AND pm.paciente_id = ?
            ");
            $stmt->execute([$medicamento_id, $paciente_id]);
            $medicamento = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$medicamento) {
                throw new Exception('Medicamento não encontrado para este paciente');
            }

            // Verificar se foi dispensado há menos de 20 dias para notificação
            $stmt = $pdo->prepare("
                SELECT 
                    MAX(data) as ultima_dispensacao,
                    CASE 
                        WHEN MAX(data) IS NOT NULL THEN DATEDIFF(NOW(), MAX(data))
                        ELSE NULL
                    END as dias_desde_ultima
                FROM transacoes 
                WHERE medicamento_id = ? AND paciente_id = ?
            ");
            $stmt->execute([$medicamento['medicamento_id'], $paciente_id]);
            $ultima_dispensacao = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($ultima_dispensacao['ultima_dispensacao'] && $ultima_dispensacao['dias_desde_ultima'] !== NULL && $ultima_dispensacao['dias_desde_ultima'] < 20) {
                $avisos_dispensacao_recente[] = "Medicamento '{$medicamento['nome_medicamento']}' foi dispensado há {$ultima_dispensacao['dias_desde_ultima']} dias.";
                error_log("AVISO GERADO MÚLTIPLO: " . "Medicamento '{$medicamento['nome_medicamento']}' foi dispensado há {$ultima_dispensacao['dias_desde_ultima']} dias.");
            }
            


            // Calcular estoque atual usando a função correta
            $estoque_atual = calcularEstoqueAtual($pdo, $medicamento['medicamento_id']);

            // Verificar quantidade disponível
            // LÓGICA FINAL: quantidade_disponivel = quantidade_solicitada
            $quantidade_disponivel = (int)$medicamento['quantidade_solicitada'];
            if ($quantidade > $quantidade_disponivel) {
                throw new Exception('Quantidade solicitada maior que a disponível');
            }

            // Verificar estoque
            if ($quantidade > $estoque_atual) {
                throw new Exception('Quantidade solicitada maior que o estoque disponível');
            }

            // NOVA FUNCIONALIDADE: Dispensar dos lotes (FIFO)
            $lotes_utilizados = dispensarDosLotes($pdo, $medicamento['medicamento_id'], $quantidade);
            
            // Registrar movimentação de saída
            $observacao_movimentacao = "Dispensação múltipla para paciente ID: $paciente_id";
            if (!empty($observacao)) {
                $observacao_movimentacao .= " - " . $observacao;
            }
            registrarMovimentacaoSaida($pdo, $medicamento['medicamento_id'], $quantidade, $observacao_movimentacao);

            // Registrar transação
            $stmt = $pdo->prepare("
                INSERT INTO transacoes (
                    paciente_id, 
                    medicamento_id, 
                    quantidade, 
                    observacoes,
                    usuario_id,
                    data
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $paciente_id,
                $medicamento['medicamento_id'],
                $quantidade,
                $observacao,
                $usuario_id
            ]);
            
            // Armazenar informações dos lotes utilizados
            $todos_lotes_utilizados[$medicamento['nome_medicamento']] = $lotes_utilizados;
        }

        $pdo->commit();
        
        // Preparar resposta com informações dos lotes utilizados
        $lotes_info = [];
        foreach ($todos_lotes_utilizados as $nome_medicamento => $lotes) {
            $med_info = [];
            foreach ($lotes as $lote) {
                $med_info[] = "Lote {$lote['lote_nome']}: {$lote['quantidade_utilizada']} unidades";
            }
            $lotes_info[] = "$nome_medicamento: " . implode(", ", $med_info);
        }
        
        $resposta = [
            'success' => true,
            'message' => 'Medicamentos dispensados com sucesso',
            'lotes_utilizados' => $lotes_info,
            'avisos' => $avisos_dispensacao_recente
        ];
        
        error_log("RESPOSTA FINAL MÚLTIPLA: " . json_encode($resposta));

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $resposta = [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }

    ob_end_clean();
    echo json_encode($resposta);
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $resposta = [
        'success' => false,
        'message' => 'Erro no banco de dados: ' . $e->getMessage()
    ];
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $resposta = [
        'success' => false,
        'message' => $e->getMessage()
    ];
}

ob_end_clean();
echo json_encode($resposta);
exit; 