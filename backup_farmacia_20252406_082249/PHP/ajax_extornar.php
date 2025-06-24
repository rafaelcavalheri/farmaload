<?php
include 'config.php';
header('Content-Type: application/json');
ob_start();

try {
    verificarAutenticacao(['admin', 'operador']);

    if (!isset($_SESSION['usuario']) || !isset($_SESSION['usuario']['id'])) {
        throw new Exception('Sessão do usuário inválida. Por favor, faça login novamente.');
    }

    $usuario_id = (int)$_SESSION['usuario']['id'];

    // Verificar parâmetros necessários
    if (empty($_POST['medicamento_id']) || !is_numeric($_POST['medicamento_id'])) {
        throw new Exception('ID do medicamento inválido');
    }
    if (empty($_POST['paciente_id']) || !is_numeric($_POST['paciente_id'])) {
        throw new Exception('ID do paciente inválido');
    }
    if (!isset($_POST['quantidade']) || !is_numeric($_POST['quantidade']) || $_POST['quantidade'] <= 0) {
        throw new Exception('Quantidade inválida');
    }

    $pm_id = (int)$_POST['medicamento_id'];
    $paciente_id = (int)$_POST['paciente_id'];
    $quantidade = (int)$_POST['quantidade'];
    $observacao = trim($_POST['observacao'] ?? '');

    $pdo->beginTransaction();

    try {
        // Atualizar observação do paciente se necessário
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

        // Buscar medicamento vinculado ao paciente
        $stmt = $pdo->prepare("
            SELECT 
                pm.id,
                pm.medicamento_id,
                COALESCE(pm.quantidade_solicitada, pm.quantidade) as quantidade_solicitada,
                COALESCE((
                    SELECT SUM(quantidade) 
                    FROM transacoes 
                    WHERE medicamento_id = pm.medicamento_id 
                    AND paciente_id = pm.paciente_id
                ), 0) as quantidade_entregue,
                m.quantidade as estoque
            FROM paciente_medicamentos pm
            JOIN medicamentos m ON m.id = pm.medicamento_id
            WHERE pm.id = ? AND pm.paciente_id = ?
        ");
        $stmt->execute([$pm_id, $paciente_id]);
        $medicamento = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$medicamento) {
            throw new Exception("Medicamento não encontrado ou não vinculado ao paciente.");
        }

        // O extorno não pode ultrapassar o que já foi entregue
        if ($quantidade > $medicamento['quantidade_entregue']) {
            throw new Exception("Quantidade de extorno maior que a quantidade entregue.");
        }

        // Registrar o extorno como transação negativa
        $stmt = $pdo->prepare("
            INSERT INTO transacoes (paciente_id, medicamento_id, quantidade, usuario_id, data, observacoes)
            VALUES (?, ?, ?, ?, NOW(), ?)
        ");
        $stmt->execute([
            $paciente_id,
            $medicamento['medicamento_id'],
            -$quantidade,
            $usuario_id,
            '[EXTORNO] ' . $observacao
        ]);

        $pdo->commit();
        $resposta = [
            'success' => true,
            'message' => "Extorno realizado com sucesso!"
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
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