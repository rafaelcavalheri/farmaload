<?php
require __DIR__ . '/config.php';
include 'funcoes_estoque.php';
verificarAutenticacao(['admin', 'operador']);

$paciente_id = filter_input(INPUT_GET, 'paciente_id', FILTER_VALIDATE_INT);
if (!$paciente_id) {
    die("ID de paciente inválido");
}

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

// Adicionar campo de observação no início
?>
<div class="observacao-box">
    <div class="observacao-header">
        <strong>Observações:</strong>
    </div>
    <textarea id="observacao" class="observacao-editor" rows="4"><?= htmlspecialchars($paciente['observacao'] ?? '') ?></textarea>
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
            <label>Quantidade disponível: <?= $quantidade_disponivel ?></label>
            <label>Estoque atual: <?= $estoque_atual ?></label>
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
<?php endforeach; ?>

<script>
function dispensarMedicamento(pmId, pacienteId) {
    const quantidade = document.querySelector(`#quantidade-${pmId}`).value;
    const observacao = document.querySelector('#observacao').value;
    
    // Debug log
    console.log('Observação a ser enviada:', observacao);
    
    if (!quantidade || quantidade <= 0) {
        alert('Por favor, informe uma quantidade válida.');
        return;
    }

    const formData = new FormData();
    formData.append('medicamento_id', pmId);
    formData.append('paciente_id', pacienteId);
    formData.append('quantidade', quantidade);
    formData.append('observacao', observacao);

    // Debug log
    console.log('FormData observação:', formData.get('observacao'));

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
</script>

<style>
.observacao-box {
    margin: 15px 0;
    padding: 15px;
    background-color: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 4px;
}
.observacao-header {
    margin-bottom: 10px;
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
</style>
