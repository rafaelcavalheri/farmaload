<?php
require __DIR__ . '/config.php';
include 'funcoes_estoque.php';
verificarAutenticacao(['admin', 'operador']);

$paciente_id = filter_input(INPUT_GET, 'paciente_id', FILTER_VALIDATE_INT);
if (!$paciente_id) {
    die("ID de paciente inválido");
}

// Array de observações padrão (mesmo da página dispensar.php)
$observacoes_padrao = [
    'Retirado pelo próprio paciente',
    'Retirado por pessoa autorizada',
    'Avisado para trazer renovação',
    'Cobrado renovação',
    'Não agendado. Aguardando renovação',
    'Trouxe renovação OK',
    'Trouxe renovação AT',
    'Trouxe renovação com Alteração',
    'Fornecido para 1 mês',
    'Fornecido para 2 meses',
    'Medicamento em falta',
    'Doação',
    'Vai pegar pela Farmácia Popular',
    'Suspenso',
    'Fora da data agendada, oriento.'
];

// Buscar dados do paciente para obter a observação
$stmt = $pdo->prepare("SELECT observacao FROM pacientes WHERE id = ?");
$stmt->execute([$paciente_id]);
$paciente = $stmt->fetch(PDO::FETCH_ASSOC);

// Buscar medicamentos do paciente
$stmt = $pdo->prepare("
    SELECT 
        pm.id, 
        m.id as medicamento_id,
        m.nome, 
        COALESCE(pm.quantidade_solicitada, pm.quantidade) AS quantidade_solicitada,
        m.quantidade AS quantidade_estoque,
        pm.renovado,
        DATE_FORMAT(p.validade, '%d/%m/%Y') as validade_formatada,
        COALESCE((
            SELECT SUM(quantidade) 
            FROM transacoes 
            WHERE medicamento_id = m.id 
            AND paciente_id = pm.paciente_id
        ), 0) as quantidade_entregue
    FROM paciente_medicamentos pm
    JOIN medicamentos m ON m.id = pm.medicamento_id
    JOIN pacientes p ON p.id = pm.paciente_id
    WHERE pm.paciente_id = ?
");
$stmt->execute([$paciente_id]);
$medicamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Adicionar campo de observação no início com select de observações padrão
?>
<div class="observacao-box">
    <div class="observacao-header">
        <strong>Observações:</strong>
    </div>
    
    <!-- Select para observações padrão -->
    <div class="observacao-padrao-container">
        <label for="observacao_padrao">Observações Padrão:</label>
        <select name="observacao_padrao" id="observacao_padrao" class="observacao-padrao-select">
            <option value="">Selecione uma observação padrão (opcional)</option>
            <?php foreach ($observacoes_padrao as $obs): ?>
                <option value="<?php echo htmlspecialchars($obs); ?>"><?php echo htmlspecialchars($obs); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <!-- Textarea para observações -->
    <div class="observacao-textarea-container">
        <label for="observacao">Observações (será aplicada na transação):</label>
        <textarea id="observacao" class="observacao-editor" rows="4" 
                  placeholder="Digite as observações ou selecione uma opção padrão acima..."><?= htmlspecialchars($paciente['observacao'] ?? '') ?></textarea>
    </div>
    
    <small style="color: #666; margin-top: 5px; display: block;">
        <i class="fas fa-info-circle"></i> 
        Dica: Selecione uma observação padrão para preenchimento automático, ou digite sua própria observação.
    </small>
</div>

<?php foreach ($medicamentos as $med): ?>
    <div class="medicamento-dispensar">
        <h4><?= htmlspecialchars($med['nome']) ?></h4>
        
        <div class="status-renovacao">
            <?php if ((int)$med['renovado'] === 1): ?>
                <span class="badge renovado">
                    <i class="fas fa-sync-alt"></i> Renovação em Andamento
                </span>
            <?php endif; ?>
            <?php if (!empty($med['validade_formatada'])): ?>
                <span class="data">
                    Validade: <?= htmlspecialchars($med['validade_formatada']) ?>
                </span>
            <?php endif; ?>
        </div>

        <div class="quantidade-dispensar">
            <?php 
            $estoque_atual = calcularEstoqueAtual($pdo, $med['medicamento_id']);
            $quantidade_disponivel = max(0, (int)$med['quantidade_solicitada'] - (int)$med['quantidade_entregue']);
            $max_disponivel = min($quantidade_disponivel, $estoque_atual);
            ?>
            <div class="quantidade-info-horizontal">
                <div class="info-item">
                    <i class="fas fa-pills"></i>
                    <span>Solicitado: <?= $med['quantidade_solicitada'] ?></span>
                </div>
                <div class="info-item">
                    <i class="fas fa-box"></i>
                    <span>Entregue: <?= $med['quantidade_entregue'] ?></span>
                </div>
                <div class="info-item">
                    <i class="fas fa-check-circle"></i>
                    <span>Disponível: <?= $quantidade_disponivel ?></span>
                </div>
                <div class="info-item">
                    <i class="fas fa-warehouse"></i>
                    <span>Estoque: <?= $estoque_atual ?></span>
                </div>
            </div>
            <div class="quantidade-input-container">
                <input type="number" 
                       id="quantidade-<?= $med['id'] ?>" 
                       class="quantidade-input"
                       min="0" 
                       max="<?= $max_disponivel ?>" 
                       value="0">
                <button type="button" 
                        class="btn-dispensar" 
                        onclick="dispensarMedicamento(<?= $med['id'] ?>, <?= $paciente_id ?>)"
                        <?= $quantidade_disponivel == 0 ? 'disabled' : '' ?>>
                    Dispensar
                </button>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<!-- Adicionar botão para dispensar vários -->
<div class="dispensar-varios-container">
    <button type="button" class="btn-dispensar-varios" onclick="dispensarVariosMedicamentos(<?= $paciente_id ?>)">
        <i class="fas fa-box"></i> Dispensar Medicamentos Selecionados
    </button>
</div>

<script>
function dispensarMedicamento(pmId, pacienteId) {
    const quantidade = document.querySelector(`#quantidade-${pmId}`).value;
    const observacao = document.querySelector('#observacao').value;
    
    if (!quantidade || quantidade <= 0) {
        alert('Por favor, informe uma quantidade válida.');
        return;
    }

    const formData = new FormData();
    formData.append('medicamento_id', pmId);
    formData.append('paciente_id', pacienteId);
    formData.append('quantidade', quantidade);
    formData.append('observacao', observacao);

    fetch('ajax_dispensar.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Medicamento dispensado com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + data.message);
        }
    })
    .catch(error => {
        alert('Erro ao dispensar medicamento: ' + error.message);
    });
}

function dispensarVariosMedicamentos(pacienteId) {
    const observacao = document.querySelector('#observacao').value;
    const medicamentos = document.querySelectorAll('.medicamento-dispensar');
    const medicamentosParaDispensar = [];

    medicamentos.forEach((med) => {
        const input = med.querySelector('.quantidade-input');
        const quantidade = parseInt(input.value);

        if (quantidade > 0) {
            const pmId = input.id.replace('quantidade-', '');
            medicamentosParaDispensar.push({
                medicamento_id: pmId,
                quantidade: quantidade
            });
        }
    });

    if (medicamentosParaDispensar.length === 0) {
        alert('Por favor, selecione pelo menos um medicamento para dispensar.');
        return;
    }

    const formData = new FormData();
    formData.append('paciente_id', pacienteId);
    formData.append('observacao', observacao);
    formData.append('medicamentos', JSON.stringify(medicamentosParaDispensar));

    fetch('ajax_dispensar_varios.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Medicamentos dispensados com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + data.message);
        }
    })
    .catch(error => {
        alert('Erro ao dispensar medicamentos: ' + error.message);
    });
}
</script>

<style>
.observacao-box {
    margin: 15px 0;
    padding: 15px;
    background-color: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 4px;
    max-width: 100%;
}
.observacao-header {
    margin-bottom: 15px;
}
.observacao-padrao-container {
    margin-bottom: 15px;
}
.observacao-padrao-container label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
    color: #495057;
}
.observacao-padrao-select {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background-color: #fff;
    font-family: inherit;
    font-size: inherit;
    margin-bottom: 10px;
}
.observacao-padrao-select:focus {
    border-color: #4a90e2;
    outline: none;
    box-shadow: 0 0 3px rgba(74, 144, 226, 0.3);
}
.observacao-textarea-container {
    margin-bottom: 10px;
}
.observacao-textarea-container label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
    color: #495057;
}
.observacao-editor {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background-color: #fff;
    font-family: inherit;
    font-size: inherit;
    resize: vertical;
    margin-top: 5px;
}
.observacao-editor:focus {
    border-color: #4a90e2;
    outline: none;
    box-shadow: 0 0 3px rgba(74, 144, 226, 0.3);
}
.medicamento-dispensar {
    background: white;
    padding: 15px;
    margin-bottom: 15px;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    max-width: 100%;
}
.medicamento-dispensar h4 {
    margin: 0 0 10px 0;
    color: #333;
    font-size: 1.1em;
}
.status-renovacao {
    display: flex;
    gap: 15px;
    margin-bottom: 10px;
    align-items: center;
}
.badge.renovado {
    background-color: #ffc107;
    color: #000;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.9em;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.data {
    color: #666;
    font-size: 0.9em;
}
.quantidade-info-horizontal {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 15px;
    padding: 15px;
    background-color: #f8f9fa;
    border-radius: 4px;
    align-items: center;
    justify-content: space-between;
}
.quantidade-info-horizontal .info-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 1em;
    white-space: nowrap;
    min-width: 150px;
}
.quantidade-info-horizontal .info-item i {
    color: #495057;
    width: 16px;
}
.quantidade-input-container {
    display: flex;
    gap: 15px;
    align-items: center;
    margin-top: 10px;
}
.quantidade-input {
    width: 120px;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 1em;
}
.quantidade-input:focus {
    border-color: #4a90e2;
    outline: none;
    box-shadow: 0 0 3px rgba(74, 144, 226, 0.3);
}
.btn-dispensar {
    background-color: #28a745;
    color: white;
    padding: 8px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 1em;
    min-width: 120px;
    justify-content: center;
}
.btn-dispensar:hover {
    background-color: #218838;
}
.btn-dispensar:disabled {
    background-color: #6c757d;
    cursor: not-allowed;
}
.dispensar-varios-container {
    margin-top: 20px;
    text-align: center;
    padding: 15px;
    border-top: 1px solid #e9ecef;
}
.btn-dispensar-varios {
    background-color: #28a745;
    color: white;
    padding: 12px 24px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 1.1em;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-width: 250px;
    justify-content: center;
}
.btn-dispensar-varios:hover {
    background-color: #218838;
}
.btn-dispensar-varios i {
    font-size: 1.1em;
}
</style>
