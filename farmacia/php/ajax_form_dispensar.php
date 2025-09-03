<?php
// Estilos específicos desta página estão em /css/ajax_form_dispensar.css .
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
        pm.quantidade,
        COALESCE(pm.quantidade_solicitada, pm.quantidade) AS quantidade_solicitada,
        pm.renovado,
        pm.observacoes,
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

?>

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

/* Estilo para feedback visual quando observações são adicionadas */
.observacao-textarea.observacao-adicionada {
    border-color: #28a745;
    background-color: #f8fff9;
    box-shadow: 0 0 8px rgba(40, 167, 69, 0.3);
    transition: all 0.3s ease;
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
.observacao-container {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 10px;
}
.observacao-textarea {
    flex: 1;
}
.btn-add-observacao {
    background-color: #28a745;
    color: white;
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    font-size: 18px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.2s;
    flex-shrink: 0;
    margin-top: 5px;
}
.btn-add-observacao:hover {
    background-color: #218838;
}
.btn-clear-observacao {
    background-color: #dc3545;
    color: white;
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    font-size: 16px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.2s;
    flex-shrink: 0;
    margin-top: 5px;
}
.btn-clear-observacao:hover {
    background-color: #c82333;
}
.modal-observacoes {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}
.modal-observacoes-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 25px;
    border-radius: 8px;
    width: 90%;
    max-width: 600px;
    max-height: 80vh;
    overflow-y: auto;
}
.modal-observacoes-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    border-bottom: 1px solid #dee2e6;
    padding-bottom: 15px;
}
.modal-observacoes-header h3 {
    margin: 0;
    color: #495057;
}
.close-modal {
    color: #aaa;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    background: none;
    border: none;
    padding: 0;
}
.close-modal:hover {
    color: #000;
}
.observacoes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}
.observacao-card {
    background-color: #fff;
    padding: 15px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
    overflow: hidden;
}
.observacao-card:hover {
    border-color: #4a90e2;
    background-color: #f8f9fa;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
.observacao-card.selecionado {
    border-color: #28a745;
    background-color: #d4edda;
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
}
.observacao-card.selecionado::before {
    content: '✓';
    position: absolute;
    top: 8px;
    right: 8px;
    background-color: #28a745;
    color: white;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 14px;
}
.observacao-card label {
    cursor: pointer;
    color: #495057;
    font-size: 0.95em;
    line-height: 1.4;
    margin: 0;
    display: block;
    user-select: none;
}
.observacao-card input[type="checkbox"] {
    display: none;
}

/* Estilos adicionais para melhorar a experiência */
.observacao-card:focus {
    outline: 2px solid #4a90e2;
    outline-offset: 2px;
}

.observacao-card:active {
    transform: translateY(0);
    transition: transform 0.1s ease;
}

/* Indicador visual de que o card é clicável */
.observacao-card::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 0;
    height: 3px;
    background-color: #4a90e2;
    transition: width 0.3s ease;
}

.observacao-card:hover::after {
    width: 100%;
}

.observacao-card.selecionado::after {
    background-color: #28a745;
    width: 100%;
}

/* Melhorar a responsividade dos cards */
@media (max-width: 768px) {
    .observacoes-grid {
        grid-template-columns: 1fr;
        gap: 10px;
    }
    
    .observacao-card {
        padding: 12px;
    }
}

/* Estilos para o contador de observações */
.observacoes-counter {
    background-color: #e3f2fd;
    border: 1px solid #bbdefb;
    border-radius: 6px;
    padding: 10px 15px;
    margin-bottom: 20px;
    text-align: center;
    font-size: 0.95em;
    color: #1976d2;
}

.observacoes-counter span {
    font-weight: bold;
    color: #1565c0;
}

/* Estilos para o tooltip informativo */
.observacoes-tip {
    background-color: #e7f3ff;
    border: 1px solid #b3d9ff;
    border-radius: 6px;
    padding: 12px 15px;
    margin-bottom: 20px;
    font-size: 0.9em;
    color: #0066cc;
    display: flex;
    align-items: center;
    gap: 8px;
}

.observacoes-tip i {
    color: #0066cc;
    font-size: 1.1em;
}

.observacoes-tip strong {
    color: #004499;
}

.modal-observacoes-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    border-top: 1px solid #dee2e6;
    padding-top: 15px;
}
.btn-selecionar-observacoes {
    background-color: #4a90e2;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
}
.btn-selecionar-observacoes:hover {
    background-color: #357abd;
}
.btn-cancelar {
    background-color: #6c757d;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
}
.btn-cancelar:hover {
    background-color: #5a6268;
}

.observacao-buttons {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

/* Estilos para a exibição das observações dos medicamentos */
.observacoes-medicamento {
    margin: 10px 0;
    padding: 10px;
    background-color: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 4px;
}

.observacoes-medicamento strong {
    color: #495057;
    font-size: 0.9em;
    margin-bottom: 5px;
    display: block;
}

.observacoes-medicamento .observacoes-content {
    font-size: 0.85em;
    line-height: 1.3;
    color: #495057;
    background-color: #fff;
    padding: 6px 8px;
    border-radius: 4px;
    border-left: 3px solid #007bff;
    cursor: help;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}

.observacoes-medicamento .observacoes-content:hover {
    background-color: #e3f2fd;
    border-left-color: #0056b3;
}

.observacoes-more {
    color: #007bff;
    font-weight: bold;
}
</style>

<!-- Novo sistema de observação com botão e modal -->
<div class="form-group">
    <label for="observacao">Observações:</label>
    <div class="observacao-container">
        <textarea name="observacao" id="observacao" rows="3" class="form-control observacao-textarea"
                  placeholder="Digite ou clique em + para adicionar observações..."><?= htmlspecialchars($paciente['observacao'] ?? '') ?></textarea>
        <div class="observacao-buttons">
            <button type="button" class="btn-add-observacao" onclick="abrirModalObservacoes()" title="Adicionar observação padrão">
                <i class="fas fa-plus"></i>
            </button>
            <button type="button" class="btn-clear-observacao" onclick="limparObservacoes()" title="Limpar observações">
                <i class="fas fa-eraser"></i>
            </button>
        </div>
    </div>
</div>

<!-- Modal de Observações (escondido por padrão) -->
<div id="modalObservacoes" class="modal-observacoes">
    <div class="modal-observacoes-content">
        <div class="modal-observacoes-header">
            <h3><i class="fas fa-list"></i> Selecionar Observações Padrão</h3>
            <button type="button" class="close-modal" onclick="fecharModalObservacoes()">&times;</button>
        </div>
        

        
        <div class="observacoes-grid">
            <?php foreach ($observacoes_padrao as $obs): ?>
                <div class="observacao-card">
                    <input type="checkbox" id="modal_obs_<?php echo md5($obs); ?>" value="<?php echo htmlspecialchars($obs); ?>">
                    <label for="modal_obs_<?php echo md5($obs); ?>"><?php echo htmlspecialchars($obs); ?></label>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="modal-observacoes-footer">
            <button type="button" class="btn-cancelar" onclick="fecharModalObservacoes()">Cancelar</button>
            <button type="button" class="btn-selecionar-observacoes" onclick="adicionarObservacoesSelecionadas()">Adicionar</button>
        </div>
    </div>
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

        <?php if (!empty($med['observacoes'])): ?>
            <div class="observacoes-medicamento">
                <strong>Observações:</strong>
                <div class="observacoes-content" title="<?= htmlspecialchars($med['observacoes'], ENT_QUOTES) ?>">
                    <?= htmlspecialchars(mb_substr($med['observacoes'], 0, 100)) ?>
                    <?php if (mb_strlen($med['observacoes']) > 100): ?>
                        <span class="observacoes-more">...</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="quantidade-dispensar">
            <?php 
            $estoque_atual = calcularEstoqueAtual($pdo, $med['medicamento_id']);
            // LÓGICA FINAL: quantidade_disponivel = quantidade_solicitada
            $quantidade_disponivel = (int)$med['quantidade_solicitada'];
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
// Definir funções globalmente para evitar erros de referência
window.abrirModalObservacoes = function() {
    document.getElementById('modalObservacoes').style.display = 'block';
    // Adicionar event listeners aos cards
    adicionarEventListenersCards();
    // Restaurar estado anterior se existir
    restaurarEstadoObservacoes();
};

window.fecharModalObservacoes = function() {
    // Salvar estado atual antes de fechar
    salvarEstadoObservacoes();
    document.getElementById('modalObservacoes').style.display = 'none';
};

// Função para salvar o estado das observações selecionadas
function salvarEstadoObservacoes() {
    const checkboxes = document.querySelectorAll('.observacao-card input[type="checkbox"]');
    const estado = Array.from(checkboxes).map(cb => ({
        id: cb.id,
        checked: cb.checked
    }));
    sessionStorage.setItem('observacoesEstado', JSON.stringify(estado));
}

// Função para restaurar o estado das observações selecionadas
function restaurarEstadoObservacoes() {
    const estadoSalvo = sessionStorage.getItem('observacoesEstado');
    if (estadoSalvo) {
        const estado = JSON.parse(estadoSalvo);
        estado.forEach(item => {
            const checkbox = document.getElementById(item.id);
            if (checkbox) {
                checkbox.checked = item.checked;
                const card = checkbox.closest('.observacao-card');
                if (item.checked) {
                    card.classList.add('selecionado');
                } else {
                    card.classList.remove('selecionado');
                }
            }
        });
        atualizarContadorObservacoes();
    }
}

// Função para adicionar event listeners aos cards de observação
function adicionarEventListenersCards() {
    const cards = document.querySelectorAll('.observacao-card');
    cards.forEach(card => {
        // Remover event listeners existentes para evitar duplicação
        card.removeEventListener('click', toggleCardSelection);
        // Adicionar novo event listener
        card.addEventListener('click', toggleCardSelection);
    });
}

// Função para alternar a seleção do card
function toggleCardSelection(event) {
    console.log('Card clicado!');
    const card = event.currentTarget;
    const checkbox = card.querySelector('input[type="checkbox"]');
    
    // Alternar o estado do checkbox
    checkbox.checked = !checkbox.checked;
    
    // Alternar a classe CSS para mudar a aparência
    if (checkbox.checked) {
        card.classList.add('selecionado');
    } else {
        card.classList.remove('selecionado');
    }
    
    // Atualizar o contador
    atualizarContadorObservacoes();
}

// Função para atualizar o contador de observações selecionadas
function atualizarContadorObservacoes() {
    const checkboxes = document.querySelectorAll('.observacao-card input[type="checkbox"]:checked');
    const contador = document.getElementById('observacoesSelecionadas');
    const total = document.querySelectorAll('.observacao-card input[type="checkbox"]').length;
    
    contador.textContent = checkboxes.length;
    
    // Mudar a cor do contador baseado na quantidade selecionada
    const counterElement = document.querySelector('.observacoes-counter');
    if (counterElement) {
        if (checkboxes.length === 0) {
            counterElement.style.backgroundColor = '#e3f2fd';
            counterElement.style.borderColor = '#bbdefb';
            counterElement.style.color = '#1976d2';
        } else if (checkboxes.length === total) {
            counterElement.style.backgroundColor = '#d4edda';
            counterElement.style.borderColor = '#c3e6cb';
            counterElement.style.color = '#155724';
        } else {
            counterElement.style.backgroundColor = '#fff3cd';
            counterElement.style.borderColor = '#ffeaa7';
            counterElement.style.color = '#856404';
        }
    }
}

// Função para adicionar observações selecionadas ao textarea
window.adicionarObservacoesSelecionadas = function() {
    const checkboxes = document.querySelectorAll('.observacao-card input[type="checkbox"]:checked');
    const textarea = document.getElementById('observacao');
    let observacoesAtuais = textarea.value.trim();
    
    if (checkboxes.length === 0) {
        alert('Por favor, selecione pelo menos uma observação.');
        return;
    }
    
    const novasObservacoes = Array.from(checkboxes).map(cb => cb.value);
    
    // Preparar prefixo com vírgula e espaço se já houver conteúdo
    if (observacoesAtuais) {
        // Garantir que termine com vírgula e espaço
        if (!/[,\s]$/.test(observacoesAtuais)) {
            observacoesAtuais += ', ';
        }
    }
    
    // Concatenar observações separadas por vírgula e espaço
    const resultado = observacoesAtuais + novasObservacoes.join(', ');
    
    // Atualizar o textarea
    textarea.value = resultado;
    
    // Fechar o modal
    fecharModalObservacoes();
    
    // Limpar seleções
    limparSelecoesCards();
    
    // Mostrar feedback visual
    mostrarFeedbackObservacoes(novasObservacoes.length);
};

// Função para mostrar feedback visual após adicionar observações
function mostrarFeedbackObservacoes(quantidade) {
    const textarea = document.getElementById('observacao');
    
    // Adicionar classe temporária para highlight
    textarea.classList.add('observacao-adicionada');
    
    // Remover a classe após 2 segundos
    setTimeout(() => {
        textarea.classList.remove('observacao-adicionada');
    }, 2000);
    
    // Scroll para o textarea
    textarea.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

// Função para limpar as seleções dos cards
function limparSelecoesCards() {
    const cards = document.querySelectorAll('.observacao-card');
    cards.forEach(card => {
        const checkbox = card.querySelector('input[type="checkbox"]');
        checkbox.checked = false;
        card.classList.remove('selecionado');
    });
    
    // Resetar o contador
    atualizarContadorObservacoes();
    
    // Limpar estado salvo
    sessionStorage.removeItem('observacoesEstado');
}

// Função para limpar observações do textarea
window.limparObservacoes = function() {
    if (confirm('Tem certeza que deseja limpar todas as observações?')) {
        document.getElementById('observacao').value = '';
    }
};

// Fechar modal ao clicar fora dele
window.onclick = function(event) {
    const modal = document.getElementById('modalObservacoes');
    if (event.target === modal) {
        fecharModalObservacoes();
    }
};

// Inicializar quando o script for carregado
document.addEventListener('DOMContentLoaded', function() {
    console.log('Script de observações carregado');
    // Adicionar event listeners aos cards se existirem
    setTimeout(() => {
        if (document.querySelector('.observacao-card')) {
            adicionarEventListenersCards();
            atualizarContadorObservacoes();
        }
    }, 100);
});

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
        console.log('Resposta do AJAX:', data);
        console.log('Tipo de data.aviso:', typeof data.aviso);
        console.log('Valor de data.aviso:', data.aviso);
        
        if (data.success) {
            let mensagem = 'Medicamento dispensado com sucesso!';
            let temAviso = false;
            
            // Verificar se há aviso de forma mais robusta
            if (data.aviso && data.aviso !== null && data.aviso !== 'null' && data.aviso.trim() !== '') {
                mensagem += '\n\nAtenção: ' + data.aviso;
                temAviso = true;
                console.log('Aviso encontrado:', data.aviso);
            } else {
                console.log('Nenhum aviso encontrado');
            }
            
            console.log('Mensagem final:', mensagem);
            console.log('Tem aviso:', temAviso);
            
            // Sempre mostrar a mensagem, com ou sem aviso
            if (temAviso) {
                if (confirm(mensagem + '\n\nClique OK para continuar.')) {
                    location.reload();
                }
            } else {
                alert(mensagem);
                location.reload();
            }
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
        console.log('Resposta do AJAX múltiplo:', data);
        console.log('Tipo de data.avisos:', typeof data.avisos);
        console.log('Valor de data.avisos:', data.avisos);
        
        if (data.success) {
            let mensagem = 'Medicamentos dispensados com sucesso!';
            let temAviso = false;
            
            // Verificar se há avisos de forma mais robusta
            if (data.avisos && Array.isArray(data.avisos) && data.avisos.length > 0) {
                mensagem += '\n\nAtenção:\n' + data.avisos.join('\n');
                temAviso = true;
                console.log('Avisos encontrados:', data.avisos);
            } else {
                console.log('Nenhum aviso encontrado');
            }
            
            console.log('Mensagem final:', mensagem);
            console.log('Tem aviso:', temAviso);
            
            // Sempre mostrar a mensagem, com ou sem aviso
            if (temAviso) {
                if (confirm(mensagem + '\n\nClique OK para continuar.')) {
                    location.reload();
                }
            } else {
                alert(mensagem);
                location.reload();
            }
        } else {
            alert('Erro: ' + data.message);
        }
    })
    .catch(error => {
        alert('Erro ao dispensar medicamentos: ' + error.message);
    });
}
</script>


