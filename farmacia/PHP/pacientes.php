<?php
include 'config.php';
verificarAutenticacao(['admin', 'operador']);

// Processar ativação/desativação
if (isset($_GET['toggle'])) {
    try {
        $csrfToken = $_GET['csrf'] ?? '';
        if (!validarTokenCsrf($csrfToken)) {
            throw new Exception('Token CSRF inválido.');
        }

        $id = intval($_GET['toggle']);
        $stmt = $pdo->prepare("UPDATE pacientes SET ativo = NOT ativo WHERE id = ?");
        $stmt->execute([$id]);

        header('Location: pacientes.php?sucesso=Status+do+paciente+atualizado+com+sucesso');
        exit();
    } catch (Exception $e) {
        header('Location: pacientes.php?erro=' . urlencode($e->getMessage()));
        exit();
    }
}

$busca = $_GET['busca'] ?? '';
$ordem = $_GET['ordem'] ?? 'nome';
$direcao = $_GET['direcao'] ?? 'ASC';

$sql = "SELECT p.id, p.nome, p.cpf, p.sim, p.nascimento, p.ativo, 
               COUNT(pm.id) AS total_medicamentos, 
               (SELECT MAX(data) FROM transacoes WHERE paciente_id = p.id) as ultima_coleta
        FROM pacientes p
        LEFT JOIN paciente_medicamentos pm ON pm.paciente_id = p.id";
$params = [];
if (!empty($busca)) {
    $sql .= " WHERE p.nome LIKE ? OR p.cpf LIKE ? OR p.sim LIKE ?";
    $params = array_fill(0, 3, "%$busca%");
}
$sql .= " GROUP BY p.id";

// Adicionar ordenação
$colunas_ordenacao = [
    'nome' => 'p.nome',
    'cpf' => 'p.cpf',
    'sim' => 'p.sim',
    'nascimento' => 'p.nascimento',
    'medicamentos' => 'total_medicamentos',
    'ultima_coleta' => 'ultima_coleta',
    'status' => 'p.ativo'
];

if (isset($colunas_ordenacao[$ordem])) {
    $sql .= " ORDER BY " . $colunas_ordenacao[$ordem] . " " . ($direcao === 'DESC' ? 'DESC' : 'ASC');
} else {
    $sql .= " ORDER BY p.nome ASC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Gerenciar Pacientes</title>
    <link rel="icon" type="image/png" href="/images/fav.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <link rel="stylesheet" href="/css/style.css" />
    <style>
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .search-container {
            flex: 1;
            min-width: 300px;
            display: flex;
            gap: 0.5rem;
        }
        .actions {
            display: flex;
            gap: 0.5rem;
        }
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
            min-width: 140px;
            justify-content: center;
            font-size: 0.95rem;
        }
        .btn-primary:hover {
            background-color: var(--primary-dark);
        }
        .btn-primary i {
            font-size: 1rem;
        }
        .btn-primary span {
            white-space: nowrap; /* Garante que o texto não quebre */
            display: inline-block; /* Melhor controle de espaço */
        }
        /* Estilos adicionados para garantir que as colunas e ações sejam exibidas corretamente */
        table {
            table-layout: fixed;
            width: 100%;
            margin-bottom: 20px;
        }
        table th, table td {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            padding: 8px 4px;
        }
        .actions {
            display: flex;
            gap: 4px;
            min-width: 160px;
            width: 160px;
        }
        td.actions {
            white-space: nowrap;
            position: sticky;
            right: 0;
            background-color: #fff;
            z-index: 10;
            box-shadow: -5px 0 5px rgba(0,0,0,0.1);
            padding: 6px;
        }
        td.actions .btn-secondary {
            padding: 4px 6px;
            margin: 0;
            min-width: auto;
        }
        .action-buttons {
            display: flex;
            gap: 3px;
            justify-content: flex-start;
            flex-wrap: nowrap;
            width: 100%;
        }
        /* Definir larguras máximas para colunas específicas */
        th:nth-child(1), td:nth-child(1) { max-width: 180px; } /* Nome */
        th:nth-child(2), td:nth-child(2) { width: 100px; } /* CPF */
        th:nth-child(3), td:nth-child(3) { width: 60px; } /* SIM */
        th:nth-child(4), td:nth-child(4) { width: 60px; } /* Idade */
        th:nth-child(5), td:nth-child(5) { width: 90px; } /* Nascimento */
        th:nth-child(6), td:nth-child(6) { width: 90px; } /* Medicamentos */
        th:nth-child(7), td:nth-child(7) { width: 80px; } /* Próx. Renovação */
        th:nth-child(8), td:nth-child(8) { width: 70px; } /* Última Coleta */
        th:nth-child(9), td:nth-child(9) { width: 160px; } /* Status */
        th:nth-child(10), td:nth-child(10) { width: 160px; } /* Ações */
        /* Responsividade */
        @media (max-width: 768px) {
            .header-actions {
                flex-direction: column;
            }
            .search-container {
                width: 100%;
            }
            .actions {
                width: 100%;
                justify-content: flex-end;
            }
            /* Ajustar tabela para mobile */
            table {
                display: block;
                overflow-x: auto;
            }
        }
        
        /* Modal de Dispensação */
        .modal-dispensar {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content-dispensar {
            background-color: #fff;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 800px;
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }
        .close-dispensar {
            position: absolute;
            right: 20px;
            top: 10px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: var(--secondary-color);
        }
        .close-dispensar:hover {
            color: var(--danger-color);
        }
        .medicamento-dispensar {
            border: 1px solid var(--border-color);
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .medicamento-dispensar h4 {
            margin: 0 0 10px 0;
            color: var(--primary-color);
        }
        .quantidade-dispensar {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }
        .quantidade-dispensar input {
            width: 100px;
        }
        .btn-dispensar {
            background-color: var(--success-color);
            color: white;
            padding: 5px 15px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
        }
        .btn-dispensar:hover {
            background-color: #219a52;
        }
        .btn-dispensar:disabled {
            background-color: var(--secondary-color);
            cursor: not-allowed;
        }
        .medicamento-info {
            display: none;
            margin-top: 10px;
            padding: 10px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
        }
        .medicamento-info ul {
            margin: 0;
            padding: 0;
        }
        .medicamento-info li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .medicamento-info li:last-child {
            border-bottom: none;
        }
        .btn-link {
            background: none;
            border: none;
            color: #0d6efd;
            cursor: pointer;
            padding: 0;
            font: inherit;
            text-decoration: underline;
        }
        .btn-link:hover {
            color: #0a58ca;
        }
        .status-renovacao {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        .status-renovacao .badge {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.9em;
        }
        .status-renovacao .badge.renovado {
            background-color: #28a745;
            color: white;
        }
        .status-renovacao .data {
            font-weight: normal;
        }
        th.sortable {
            cursor: pointer;
            position: relative;
            padding-right: 20px;
        }
        th.sortable:after {
            content: '↕';
            position: absolute;
            right: 5px;
            color: #999;
        }
        th.sortable.asc:after {
            content: '↑';
            color: #333;
        }
        th.sortable.desc:after {
            content: '↓';
            color: #333;
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
        }
        .btn-clear-observacao:hover {
            background-color: #c82333;
        }
        .modal-observacoes {
            display: none;
            position: fixed;
            z-index: 1050; /* Z-index maior que o modal principal */
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.6);
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
        .observacao-checkbox {
            display: flex;
            align-items: center;
            background-color: #fff;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            transition: all 0.2s;
            cursor: pointer;
        }
        .observacao-checkbox:hover {
            border-color: #4a90e2;
            background-color: #f8f9fa;
        }
        .observacao-checkbox input[type="checkbox"] {
            margin-right: 12px;
            transform: scale(1.1);
        }
        .observacao-checkbox label {
            cursor: pointer;
            flex-grow: 1;
            color: #495057;
            font-size: 0.9em;
            line-height: 1.4;
        }
        .modal-observacoes-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            border-top: 1px solid #dee2e6;
            padding-top: 15px;
        }
    </style>
    <script>
        function dispensarMedicamento(medicamentoId, pacienteId) {
            const quantidade = document.querySelector(`#quantidade-${medicamentoId}`).value;
            const observacao = document.querySelector('#observacao').value;
            
            if (!quantidade || quantidade <= 0) {
                alert('Por favor, informe uma quantidade válida.');
                return;
            }

            const formData = new FormData();
            formData.append('medicamento_id', medicamentoId);
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

        function extornarMedicamento(pmId, pacienteId) {
            const quantidade = document.querySelector(`#quantidade-${pmId}`).value;
            const observacao = document.querySelector('#observacao').value;
            
            if (!quantidade || quantidade <= 0) {
                alert('Por favor, informe uma quantidade válida para extornar.');
                return;
            }

            if (!confirm('Tem certeza que deseja extornar esta quantidade?')) {
                return;
            }

            const formData = new FormData();
            formData.append('medicamento_id', pmId);
            formData.append('paciente_id', pacienteId);
            formData.append('quantidade', quantidade);
            formData.append('observacao', observacao);

            fetch('ajax_extornar.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Extorno realizado com sucesso!');
                    location.reload();
                } else {
                    alert('Erro: ' + data.message);
                }
            })
            .catch(error => {
                alert('Erro ao extornar medicamento: ' + error.message);
            });
        }

        function dispensarVariosMedicamentos(pacienteId) {
            const observacao = document.querySelector('#observacao').value;
            const medicamentos = document.querySelectorAll('.medicamento-dispensar');
            const medicamentosParaDispensar = [];

            console.log('Iniciando coleta de medicamentos...');
            console.log('Total de medicamentos encontrados:', medicamentos.length);

            medicamentos.forEach((med, index) => {
                const input = med.querySelector('.quantidade-input');
                const quantidade = parseInt(input.value);
                console.log(`Medicamento ${index + 1}:`, {
                    inputId: input.id,
                    quantidade: quantidade
                });

                if (quantidade > 0) {
                    const pmId = input.id.replace('quantidade-', '');
                    console.log(`Adicionando medicamento ${index + 1} para dispensação:`, {
                        pmId: pmId,
                        quantidade: quantidade
                    });
                    
                    medicamentosParaDispensar.push({
                        medicamento_id: pmId,
                        quantidade: quantidade
                    });
                }
            });

            console.log('Medicamentos para dispensar:', medicamentosParaDispensar);

            if (medicamentosParaDispensar.length === 0) {
                alert('Por favor, selecione pelo menos um medicamento para dispensar.');
                return;
            }

            const formData = new FormData();
            formData.append('paciente_id', pacienteId);
            formData.append('observacao', observacao);
            formData.append('medicamentos', JSON.stringify(medicamentosParaDispensar));

            console.log('Enviando dados para o servidor:', {
                paciente_id: pacienteId,
                observacao: observacao,
                medicamentos: medicamentosParaDispensar
            });

            fetch('ajax_dispensar_varios.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Resposta do servidor:', data);
                if (data.success) {
                    alert('Medicamentos dispensados com sucesso!');
                    location.reload();
                } else {
                    alert('Erro: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro na requisição:', error);
                alert('Erro ao dispensar medicamentos: ' + error.message);
            });
        }

        function abrirModalDispensar(pacienteId, pacienteNome) {
            document.getElementById('modalDispensar').style.display = 'block';
            document.getElementById('pacienteNome').textContent = 'Paciente: ' + pacienteNome;
            
            // Carregar medicamentos do paciente
            fetch(`ajax_form_dispensar.php?paciente_id=${pacienteId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('medicamentosDispensar').innerHTML = html;
                    
                    // Configurar evento para fechar o modal de observações ao clicar fora
                    window.onclick = function(event) {
                        const modalObs = document.getElementById('modalObservacoes');
                        if (event.target == modalObs) {
                            fecharModalObservacoes();
                        }
                    };
                })
                .catch(error => {
                    document.getElementById('medicamentosDispensar').innerHTML = 
                        `<div class='alert erro'>Erro ao carregar medicamentos: ${error.message}</div>`;
                });
        }

        // Função para inicializar observação padrão no modal
        function inicializarObservacaoPadraoModal() {
            console.log('Inicializando observação padrão no modal...');
            
            // Função para tentar inicializar
            function tentarInicializar() {
                const modalContainer = document.getElementById('medicamentosDispensar');
                const textarea = modalContainer.querySelector('#observacao');
                const select = modalContainer.querySelector('#observacao_padrao');
                
                console.log('Procurando elementos no modal:');
                console.log('Modal container:', modalContainer);
                console.log('Textarea encontrado:', textarea);
                console.log('Select encontrado:', select);
                
                if (textarea && select) {
                    console.log('Elementos de observação encontrados no modal, configurando eventos...');
                    
                    // Adicionar evento de mudança ao select
                    select.addEventListener('change', function() {
                        console.log('Select alterado para:', this.value);
                        atualizarObservacaoModal(this.value);
                    });
                    
                    // Permitir edição manual do textarea
                    textarea.addEventListener('input', function() {
                        console.log('Textarea editado manualmente:', this.value);
                        if (this.value !== select.value) {
                            select.value = '';
                        }
                    });
                    
                    console.log('Eventos de observação configurados no modal');
                    return true;
                } else {
                    console.log('Elementos ainda não encontrados, tentando novamente...');
                    return false;
                }
            }
            
            // Tentar inicializar com retry
            let tentativas = 0;
            const maxTentativas = 10;
            
            function tentarComRetry() {
                if (tentarInicializar() || tentativas >= maxTentativas) {
                    if (tentativas >= maxTentativas) {
                        console.error('Falha ao inicializar observação padrão após', maxTentativas, 'tentativas');
                    }
                    return;
                }
                
                tentativas++;
                setTimeout(tentarComRetry, 200);
            }
            
            // Iniciar tentativas
            setTimeout(tentarComRetry, 100);
        }

        // Função para atualizar observação no modal
        function atualizarObservacaoModal(valor) {
            console.log('Atualizando observação no modal com valor:', valor);
            const modalContainer = document.getElementById('medicamentosDispensar');
            const textarea = modalContainer.querySelector('#observacao');
            if (textarea) {
                textarea.value = valor || '';
                textarea.style.backgroundColor = valor ? '#e8f5e8' : '#fff';
                console.log('Textarea do modal atualizado com sucesso');
            } else {
                console.error('Textarea do modal não encontrado');
            }
        }

        function fecharModalDispensar() {
            document.getElementById('modalDispensar').style.display = 'none';
            document.getElementById('medicamentosDispensar').innerHTML = '';
        }

        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            const modal = document.getElementById('modalDispensar');
            if (event.target == modal) {
                fecharModalDispensar();
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Função para ordenar a tabela
            function ordenarTabela(coluna) {
                const urlParams = new URLSearchParams(window.location.search);
                const ordemAtual = urlParams.get('ordem') || 'nome';
                const direcaoAtual = urlParams.get('direcao') || 'ASC';
                
                // Alternar direção se clicar na mesma coluna
                const novaDirecao = (ordemAtual === coluna && direcaoAtual === 'ASC') ? 'DESC' : 'ASC';
                
                // Atualizar parâmetros da URL
                urlParams.set('ordem', coluna);
                urlParams.set('direcao', novaDirecao);
                
                // Manter o parâmetro de busca se existir
                const busca = urlParams.get('busca');
                if (busca) {
                    urlParams.set('busca', busca);
                }
                
                // Redirecionar com os novos parâmetros
                window.location.href = window.location.pathname + '?' + urlParams.toString();
            }

            // Adicionar eventos de clique nos cabeçalhos
            document.querySelectorAll('th.sortable').forEach(th => {
                th.addEventListener('click', () => {
                    ordenarTabela(th.dataset.ordem);
                });
            });

            // Marcar coluna atual como ordenada
            const urlParams = new URLSearchParams(window.location.search);
            const ordemAtual = urlParams.get('ordem') || 'nome';
            const direcaoAtual = urlParams.get('direcao') || 'ASC';
            
            const thAtual = document.querySelector(`th[data-ordem="${ordemAtual}"]`);
            if (thAtual) {
                thAtual.classList.add(direcaoAtual.toLowerCase());
            }

            // Adicionar evento de clique para o botão "Ver"
            document.querySelectorAll('.show-medicamentos').forEach(button => {
                button.addEventListener('click', function() {
                    const pacienteId = this.getAttribute('data-paciente');
                    const medicamentosDiv = document.getElementById('medicamentos-' + pacienteId);
                    
                    if (medicamentosDiv.style.display === 'none' || !medicamentosDiv.style.display) {
                        // Carregar medicamentos
                        fetch('ajax_medicamentos_paciente.php?paciente_id=' + pacienteId)
                            .then(response => response.text())
                            .then(html => {
                                medicamentosDiv.innerHTML = html;
                                medicamentosDiv.style.display = 'block';
                            })
                            .catch(error => {
                                medicamentosDiv.innerHTML = '<p class="alert erro">Erro ao carregar medicamentos: ' + error.message + '</p>';
                                medicamentosDiv.style.display = 'block';
                            });
                    } else {
                        medicamentosDiv.style.display = 'none';
                    }
                });
            });
        });

        // Funções do modal de observação
        function abrirModalObservacoes() {
            const modal = document.getElementById('modalObservacoes');
            if (modal) {
                modal.style.display = 'block';
                document.querySelectorAll('#modalObservacoes .observacao-checkbox input[type="checkbox"]').forEach(cb => {
                    cb.checked = false;
                });
            }
        }

        function fecharModalObservacoes() {
            const modal = document.getElementById('modalObservacoes');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        function adicionarObservacoesSelecionadas() {
            const textarea = document.getElementById('observacao');
            const checkboxes = document.querySelectorAll('#modalObservacoes .observacao-checkbox input[type="checkbox"]:checked');

            if (checkboxes.length === 0) {
                alert('Selecione pelo menos uma observação.');
                return;
            }

            const observacoesSelecionadas = Array.from(checkboxes).map(cb => cb.value);
            const textoAtual = textarea.value.trim();

            const novoTexto = textoAtual ?
                textoAtual + ', ' + observacoesSelecionadas.join(', ') :
                observacoesSelecionadas.join(', ');

            textarea.value = novoTexto;
            fecharModalObservacoes();
        }

        function limparObservacoes() {
            const textarea = document.getElementById('observacao');
            if (textarea && confirm('Tem certeza que deseja limpar as observações?')) {
                textarea.value = '';
            }
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                fecharModalObservacoes();
            }
        });
    </script>
</head>
<body>
<?php include 'header.php'; ?>
<main class="container">
    <h2 style="margin-bottom: 2rem;"><i class="fas fa-users"></i> Gerenciamento de Pacientes</h2>

    <?php if (isset($_GET['sucesso'])): ?>
        <div class="alert sucesso"><?= htmlspecialchars($_GET['sucesso']) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['erro'])): ?>
        <div class="alert erro"><?= htmlspecialchars($_GET['erro']) ?></div>
    <?php endif; ?>

    <div class="header-actions">
        <form method="GET" class="form-group">
            <div class="search-container">
                <input type="text" name="busca" placeholder="Buscar pacientes..." value="<?= htmlspecialchars($busca) ?>" />
                <button type="submit" class="btn-secondary"><i class="fas fa-search"></i> Buscar</button>
            </div>
        </form>
        <div class="actions">
            <a href="cadastrar_paciente.php" class="btn-secondary">+ Novo Paciente</a>
        </div>
    </div>

    <?php if ($stmt->rowCount() > 0): ?>
    <table>
        <thead>
            <tr>
                <th class="sortable" data-ordem="nome">Nome</th>
                <th class="sortable" data-ordem="cpf">CPF</th>
                <th class="sortable" data-ordem="sim">SIM</th>
                <th class="sortable" data-ordem="nascimento">Nascimento</th>
                <th class="sortable" data-ordem="medicamentos">Medicamentos</th>
                <th class="sortable" data-ordem="ultima_coleta">Última Coleta</th>
                <th class="sortable" data-ordem="status">Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($paciente = $stmt->fetch()): ?>
            <?php
                $nasc = new DateTime($paciente['nascimento']);
                $idade = (new DateTime())->diff($nasc)->y;
                $renAlert = '';

                if (!empty($paciente['proxima_renovacao'])) {
                    $dataRaw = trim($paciente['proxima_renovacao']);
                    $ren = DateTime::createFromFormat('Y-m-d', $dataRaw);
                    
                    if ($ren !== false) {
                        $hoje = new DateTime();
                        
                        if ($ren < $hoje) {
                            $renAlert = '<span class="badge badge-danger">' . $ren->format('d/m/Y') . ' (Atrasada)</span>';
                        } elseif ($ren->format('Y-m') === $hoje->format('Y-m')) {
                            $renAlert = '<span class="badge badge-warning">' . $ren->format('d/m/Y') . ' (Este mês)</span>';
                        } else {
                            $renAlert = '<span class="badge">' . $ren->format('d/m/Y') . '</span>';
                        }
                    } else {
                        $renAlert = '<span class="badge badge-secondary">Sem data definida</span>';
                    }
                } else {
                    $renAlert = '<span class="badge badge-secondary">Sem data definida</span>';
                }
            ?>
            <tr class="<?= !$paciente['ativo'] ? 'inativo' : '' ?>">
                <td><?= htmlspecialchars($paciente['nome']) ?></td>
                <td><span id="cpf-<?= $paciente['id'] ?>"><?= formatarCPF($paciente['cpf']) ?></span></td>
                <td><?= htmlspecialchars($paciente['sim'] ?? '--') ?></td>
                <td><?= $nasc->format('d/m/Y') ?> (<?= $idade ?> anos)</td>
                <td>
                    <?php if ($paciente['total_medicamentos'] > 0): ?>
                        <span class="badge"><?= $paciente['total_medicamentos'] ?></span>
                        <button type="button" class="btn-link show-medicamentos" data-paciente="<?= $paciente['id'] ?>">
                            <i class="fas fa-pills"></i> Ver
                        </button>
                        <div id="medicamentos-<?= $paciente['id'] ?>" class="medicamento-info"></div>
                    <?php else: ?>
                        -- 
                    <?php endif; ?>
                </td>
                <td>
                    <?php 
                    if (!empty($paciente['ultima_coleta'])) {
                        echo date('d/m/Y H:i', strtotime($paciente['ultima_coleta']));
                    } else {
                        echo '--';
                    }
                    ?>
                </td>
                <td><?= $paciente['ativo'] ? '<span class="badge badge-success">Ativo</span>' : '<span class="badge badge-danger">Inativo</span>' ?></td>
                <td class="actions">
                    <div class="action-buttons">
                        <?php if ($paciente['ativo']): ?>
                            <button onclick="abrirModalDispensar(<?= $paciente['id'] ?>, '<?= htmlspecialchars($paciente['nome']) ?>')" 
                                    class="btn-secondary" 
                                    title="Dispensar Medicamentos"
                                    <?= $paciente['total_medicamentos'] == 0 ? 'disabled' : '' ?>>
                                <i class="fas fa-pills"></i>
                            </button>
                        <?php endif; ?>
                        <a href="editar_paciente.php?id=<?= $paciente['id'] ?>" class="btn-secondary" title="Editar">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="pacientes.php?toggle=<?= $paciente['id'] ?>&csrf=<?= gerarTokenCsrf() ?>"
                          class="btn-secondary"
                          title="<?= $paciente['ativo'] ? 'Desativar' : 'Ativar' ?>"
                          onclick="return confirm('Deseja realmente <?= $paciente['ativo'] ? 'desativar' : 'ativar' ?> este paciente?');">
                            <i class="fas fa-power-off"></i>
                        </a>
                        <a href="detalhes_paciente.php?id=<?= $paciente['id'] ?>" class="btn-secondary" title="Detalhes">
                            <i class="fas fa-eye"></i>
                        </a>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php else: ?>
        <div class="alert" style="margin-top:2rem;"><i class="fas fa-info-circle"></i> Nenhum paciente encontrado</div>
    <?php endif; ?>
</main>

<!-- Modal de Dispensação -->
<div id="modalDispensar" class="modal-dispensar">
    <div class="modal-content-dispensar">
        <span class="close-dispensar" onclick="fecharModalDispensar()">&times;</span>
        <h3>Dispensar Medicamentos</h3>
        <p id="pacienteNome" style="margin-bottom: 20px; font-size: 1.1em;"></p>
        <div id="medicamentosDispensar"></div>
    </div>
</div>

<!-- Modal de Observações -->
<div id="modalObservacoes" class="modal-observacoes">
    <div class="modal-observacoes-content">
        <div class="modal-observacoes-header">
            <h3>Adicionar Observações</h3>
            <button class="close-modal" onclick="fecharModalObservacoes()">&times;</button>
        </div>
        <div class="observacoes-grid">
            <!-- Observações disponíveis -->
        </div>
        <div class="modal-observacoes-footer">
            <button class="btn-add-observacao" onclick="adicionarObservacoesSelecionadas()">Adicionar</button>
            <button class="btn-clear-observacao" onclick="limparObservacoes()">Limpar</button>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?> 