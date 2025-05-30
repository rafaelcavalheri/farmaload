<?php
require __DIR__ . '/config.php';
include 'funcoes_estoque.php';
verificarAutenticacao(['admin', 'operador']);

$paciente_id = filter_input(INPUT_GET, 'paciente_id', FILTER_VALIDATE_INT);
if (!$paciente_id) {
    die("ID de paciente inválido");
}

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

foreach ($medicamentos as $med): ?>
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
                   id="quantidade-<?= $med['medicamento_id'] ?>" 
                   class="quantidade-input"
                   min="0" 
                   max="<?= $max_disponivel ?>" 
                   value="0">
            <button type="button" 
                    class="btn-dispensar" 
                    onclick="dispensarMedicamento(<?= $med['medicamento_id'] ?>, <?= $paciente_id ?>)"
                    <?= $quantidade_disponivel == 0 ? 'disabled' : '' ?>>
                Dispensar
            </button>
        </div>
    </div>
<?php endforeach; ?>

<script>
document.getElementById('formDispensar').addEventListener('submit', async function(e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);

    try {
        const response = await fetch('ajax_dispensar.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        if (result.success) {
            alert(result.message);
            location.reload();
        } else {
            alert("Erro: " + result.message);
        }
    } catch (error) {
        alert("Erro na comunicação com o servidor.");
        console.error(error);
    }
});
</script>
