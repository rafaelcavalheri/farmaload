<?php
// Função para vincular medicamento ao paciente durante a importação
function vincularMedicamentoPaciente($pdo, $pacienteId, $medicamentoId, $nomeMedicamento, $quantidade, $logFile = null) {
    try {
        // Verificar se o vínculo já existe
        $stmt = $pdo->prepare("
            SELECT id FROM paciente_medicamentos 
            WHERE paciente_id = ? AND medicamento_id = ?
        ");
        $stmt->execute([$pacienteId, $medicamentoId]);
        $vinculoExistente = $stmt->fetch();
        
        if ($vinculoExistente) {
            // Atualizar quantidade se o vínculo já existir
            $stmt = $pdo->prepare("
                UPDATE paciente_medicamentos 
                SET quantidade = quantidade + ?,
                    data_atualizacao = NOW(),
                    observacoes = CONCAT(IFNULL(observacoes, ''), ' | Atualizado via importação em ', NOW())
                WHERE id = ?
            ");
            $stmt->execute([$quantidade, $vinculoExistente['id']]);
            
            if ($logFile) {
                fwrite($logFile, "Vínculo paciente-medicamento atualizado: Paciente ID $pacienteId, Medicamento ID $medicamentoId, Nova Quantidade +$quantidade\n");
            }
        } else {
            // Criar novo vínculo
            $stmt = $pdo->prepare("
                INSERT INTO paciente_medicamentos (
                    paciente_id,
                    medicamento_id,
                    nome_medicamento,
                    quantidade,
                    observacoes
                ) VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $pacienteId,
                $medicamentoId,
                $nomeMedicamento,
                $quantidade,
                'Vínculo criado automaticamente durante importação em ' . date('Y-m-d H:i:s')
            ]);
            
            if ($logFile) {
                fwrite($logFile, "Novo vínculo paciente-medicamento criado: Paciente ID $pacienteId, Medicamento ID $medicamentoId, Quantidade $quantidade\n");
            }
        }
        
        return true;
    } catch (Exception $e) {
        if ($logFile) {
            fwrite($logFile, "ERRO ao vincular medicamento ao paciente: " . $e->getMessage() . "\n");
        }
        return false;
    }
}
?> 