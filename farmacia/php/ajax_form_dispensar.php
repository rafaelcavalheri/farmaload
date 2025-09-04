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

<!-- Estilos específicos desta página estão em /css/ajax_form_dispensar.css -->
<link rel="stylesheet" href="css/ajax_form_dispensar.css">

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
<div id="modalObservacoes" class="modal-observacoes" data-paciente-id="<?= (int)$paciente_id ?>">
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
function getCurrentPacienteId() {
    const el = document.getElementById('modalObservacoes');
    if (!el) return 0;
    const raw = el.getAttribute('data-paciente-id') || '0';
    const parsed = parseInt(raw, 10);
    return Number.isNaN(parsed) ? 0 : parsed;
}
window.abrirModalObservacoes = function() {
    document.getElementById('modalObservacoes').style.display = 'block';
    // Sempre começar sem seleções anteriores
    limparSelecoesCards();
    // Adicionar event listeners aos cards
    adicionarEventListenersCards();
};

window.fecharModalObservacoes = function() {
    document.getElementById('modalObservacoes').style.display = 'none';
};

// Removido: persistência de estado por paciente. O modal sempre abre limpo.

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
    
    if (contador) {
        contador.textContent = checkboxes.length;
    }
    
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
    sessionStorage.removeItem(`observacoesEstado_${getCurrentPacienteId()}`);
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


