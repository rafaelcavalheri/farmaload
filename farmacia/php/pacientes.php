<?php
include 'config.php';
verificarAutenticacao(['admin', 'operador']);

// Processar ativação/desativação .
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
$filtro_alfabetico = $_GET['filtro_alfabetico'] ?? '';
$ordem = $_GET['ordem'] ?? 'nome';
$direcao = $_GET['direcao'] ?? 'ASC';
$agendados_hoje = isset($_GET['agendados_hoje']) && $_GET['agendados_hoje'] == 1;

// Configuração de paginação
$limite_padrao = 100;
$limite = isset($_GET['limite']) ? intval($_GET['limite']) : $limite_padrao;
$pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
$offset = ($pagina - 1) * $limite;

// Opções de limite disponíveis
$opcoes_limite = [100, 200, 300, 500, 1000];

$sql = "SELECT p.id, p.nome, p.codigo_paciente, p.sim, p.nascimento, p.ativo, 
               COUNT(pm.id) AS total_medicamentos, 
               (SELECT MAX(data) FROM transacoes WHERE paciente_id = p.id) as ultima_coleta
        FROM pacientes p
        LEFT JOIN paciente_medicamentos pm ON pm.paciente_id = p.id";

$params = [];
$where_conditions = [];

if ($agendados_hoje) {
    $data_hoje = date('Y-m-d');
    $sql .= " INNER JOIN agenda a ON a.paciente_id = p.id AND a.data = ? AND a.status != 'cancelado'";
    $params[] = $data_hoje;
}

if (!empty($busca)) {
    $where_conditions[] = "(p.nome LIKE ? OR p.codigo_paciente LIKE ? OR p.sim LIKE ?)";
    $params = array_merge($params, array_fill(0, 3, "%$busca%"));
}

if (!empty($filtro_alfabetico)) {
    $where_conditions[] = "p.nome LIKE ?";
    $params[] = $filtro_alfabetico . "%";
}

if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

$sql .= " GROUP BY p.id";

// Adicionar ordenação
$colunas_ordenacao = [
    'nome' => 'p.nome',
    'codigo_paciente' => 'p.codigo_paciente',
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

// Adicionar LIMIT e OFFSET para paginação
$sql .= " LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($sql);
// Adicionar os parâmetros de paginação ao array de parâmetros
$params[] = $limite;
$params[] = $offset;
$stmt->execute($params);

// Query para contar total de registros (sem LIMIT)
$sql_count = "SELECT COUNT(DISTINCT p.id) as total FROM pacientes p";
$params_count = [];
$where_conditions_count = [];

if ($agendados_hoje) {
    $data_hoje = date('Y-m-d');
    $sql_count .= " INNER JOIN agenda a ON a.paciente_id = p.id AND a.data = ? AND a.status != 'cancelado'";
    $params_count[] = $data_hoje;
}

if (!empty($busca)) {
    $where_conditions_count[] = "(p.nome LIKE ? OR p.codigo_paciente LIKE ? OR p.sim LIKE ?)";
    $params_count = array_merge($params_count, array_fill(0, 3, "%$busca%"));
}

if (!empty($filtro_alfabetico)) {
    $where_conditions_count[] = "p.nome LIKE ?";
    $params_count[] = $filtro_alfabetico . "%";
}

if (!empty($where_conditions_count)) {
    $sql_count .= " WHERE " . implode(" AND ", $where_conditions_count);
}

$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params_count);
$total_registros = $stmt_count->fetch()['total'];
$total_paginas = ceil($total_registros / $limite);
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
    <link rel="stylesheet" href="/css/pacientes.css" />
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
                console.log('Resposta do AJAX individual:', data);
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

                    // Executar scripts embutidos do conteúdo carregado
                    (function executeInlineScripts(container) {
                        const scripts = Array.from(container.querySelectorAll('script'));
                        scripts.forEach((oldScript) => {
                            const newScript = document.createElement('script');
                            if (oldScript.src) {
                                newScript.src = oldScript.src;
                            } else {
                                newScript.text = oldScript.text || oldScript.textContent;
                            }
                            document.body.appendChild(newScript);
                            oldScript.parentNode && oldScript.parentNode.removeChild(oldScript);
                        });
                    })(document.getElementById('medicamentosDispensar'));

                    // Se a função de adicionar listeners existir, inicializar
                    if (typeof adicionarEventListenersCards === 'function') {
                        adicionarEventListenersCards();
                    }

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
                
                // Manter parâmetros de paginação, busca e filtro alfabético
                const busca = urlParams.get('busca');
                const limite = urlParams.get('limite');
                const pagina = urlParams.get('pagina');
                const filtroAlfabetico = urlParams.get('filtro_alfabetico');
                
                if (busca) urlParams.set('busca', busca);
                if (limite) urlParams.set('limite', limite);
                if (pagina) urlParams.set('pagina', pagina);
                if (filtroAlfabetico) urlParams.set('filtro_alfabetico', filtroAlfabetico);
                
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

        // Função para alterar o limite de registros por página
        function alterarLimite(novoLimite) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('limite', novoLimite);
            urlParams.set('pagina', '1'); // Voltar para primeira página ao alterar limite
            
            // Manter outros parâmetros
            const busca = urlParams.get('busca');
            const ordem = urlParams.get('ordem');
            const direcao = urlParams.get('direcao');
            const filtroAlfabetico = urlParams.get('filtro_alfabetico');
            
            if (busca) urlParams.set('busca', busca);
            if (ordem) urlParams.set('ordem', ordem);
            if (direcao) urlParams.set('direcao', direcao);
            if (filtroAlfabetico) urlParams.set('filtro_alfabetico', filtroAlfabetico);
            
            window.location.href = window.location.pathname + '?' + urlParams.toString();
        }
    </script>
</head>
<body>
<?php include 'header.php'; ?>
<main class="container">
    <!-- Cabeçalho -->
    <div class="page-header">
        <h1><i class="fas fa-users"></i> Pacientes</h1>
        <div class="actions">
            <a href="cadastrar_paciente.php" class="btn-secondary">
                <i class="fas fa-user-plus"></i> Novo Paciente
            </a>
        </div>
    </div>

    <?php if (isset($_GET['sucesso'])): ?>
        <div class="alert sucesso"><?= htmlspecialchars($_GET['sucesso']) ?></div>
    <?php endif; ?>
    <?php if (isset($_GET['erro'])): ?>
        <div class="alert erro"><?= htmlspecialchars($_GET['erro']) ?></div>
    <?php endif; ?>

    <form method="GET" class="form-group">
        <div class="search-container">
            <div class="search-fields">
                <label for="busca">Buscar por Nome, Código do Paciente ou SIM:</label>
                <div class="search-row">
                    <input type="text" id="busca" name="busca" placeholder="Buscar pacientes..." value="<?= htmlspecialchars($busca) ?>" class="search-input" />
                    <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i> Buscar</button>
                </div>
            </div>
            <input type="hidden" name="limite" value="<?= $limite ?>" />
            <?php if (!empty($filtro_alfabetico)): ?>
                <input type="hidden" name="filtro_alfabetico" value="<?= htmlspecialchars($filtro_alfabetico) ?>" />
            <?php endif; ?>
        </div>
    </form>

    <!-- Filtro Alfabético -->
    <div class="filtro-alfabetico">
        <?php
        // Função para gerar URL com parâmetros
        function gerarUrlFiltro($letra, $limite, $busca, $ordem, $direcao) {
            $params = ['limite' => $limite];
            if (!empty($letra)) $params['filtro_alfabetico'] = $letra;
            if (!empty($busca)) $params['busca'] = $busca;
            if (!empty($ordem)) $params['ordem'] = $ordem;
            if (!empty($direcao)) $params['direcao'] = $direcao;
            return '?' . http_build_query($params);
        }
        
        // Botão "Hoje"
        $url_hoje = gerarUrlFiltro('', $limite, $busca, $ordem, $direcao);
        $classe_hoje = $agendados_hoje ? 'letra-ativa' : '';
        ?>
        <a href="pacientes.php?agendados_hoje=1" class="btn-primary" style="margin-right: 8px; vertical-align: middle;">
            <i class="fas fa-calendar-check"></i> Hoje
        </a>
        <a href="<?= $url_hoje ?>" class="todas <?= $classe_hoje ?>">
            Todas
        </a>
        <?php
        // Letras do alfabeto
        $alfabeto = range('A', 'Z');
        foreach ($alfabeto as $letra):
            $url_letra = gerarUrlFiltro($letra, $limite, $busca, $ordem, $direcao);
            $classe_letra = ($filtro_alfabetico === $letra) ? 'letra-ativa' : '';
        ?>
            <a href="<?= $url_letra ?>" class="<?= $classe_letra ?>">
                <?= $letra ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($stmt->rowCount() > 0): ?>
    <div class="card">
        <table>
        <thead>
            <tr>
                <th class="sortable" data-ordem="nome">Nome</th>
                <th class="sortable" data-ordem="codigo_paciente">Código do Paciente</th>
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
                <td><span id="codigo-paciente-<?= $paciente['id'] ?>"><?= htmlspecialchars($paciente['codigo_paciente'] ?? '--') ?></span></td>
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
                <td class="<?= $paciente['ativo'] ? 'status-ativo' : 'status-inativo' ?>"><?= $paciente['ativo'] ? 'Ativo' : 'Inativo' ?></td>
                <td class="actions">
                    <div class="action-buttons">
                        <?php if ($paciente['ativo']): ?>
                        <a href="javascript:void(0)"
                           onclick="abrirModalDispensar(<?= $paciente['id'] ?>, '<?= htmlspecialchars($paciente['nome']) ?>')"
                           class="btn-secondary<?= ($paciente['total_medicamentos'] == 0) ? ' disabled' : '' ?>"
                           title="Dispensar Medicamentos"
                           <?= ($paciente['total_medicamentos'] == 0) ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
                            <i class="fas fa-pills"></i>
                        </a>
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
    </div>
    <?php else: ?>
        <div class="alert" style="margin-top:2rem;"><i class="fas fa-info-circle"></i> Nenhum paciente encontrado</div>
    <?php endif; ?>
    
    <?php if ($total_registros > 0): ?>
    <!-- Controles de Paginação -->
    <div class="paginacao-container">
        <div class="paginacao-info">
            <span>
                <strong><?= $total_registros ?></strong> paciente<?= $total_registros > 1 ? 's' : '' ?> encontrado<?= $total_registros > 1 ? 's' : '' ?>
            </span>
            <span>
                Página <strong><?= $pagina ?></strong> de <strong><?= $total_paginas ?></strong>
            </span>
            <span>
                Mostrando <strong><?= min($offset + 1, $total_registros) ?></strong> a <strong><?= min($offset + $limite, $total_registros) ?></strong>
            </span>
        </div>
        
        <div class="paginacao-controles">
            <!-- Seletor de limite por página -->
            <div class="paginacao-limit">
                <label for="limite">Mostrar:</label>
                <select id="limite" onchange="alterarLimite(this.value)">
                    <?php foreach ($opcoes_limite as $opcao): ?>
                        <option value="<?= $opcao ?>" <?= $limite == $opcao ? 'selected' : '' ?>>
                            <?= $opcao ?> por página
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Navegação de páginas -->
            <div class="paginacao-numeros">
                <?php
                // Função para gerar URL com parâmetros
                function gerarUrlPagina($pagina, $limite, $busca, $filtro_alfabetico, $ordem, $direcao) {
                    $params = ['pagina' => $pagina, 'limite' => $limite];
                    if (!empty($busca)) $params['busca'] = $busca;
                    if (!empty($filtro_alfabetico)) $params['filtro_alfabetico'] = $filtro_alfabetico;
                    if (!empty($ordem)) $params['ordem'] = $ordem;
                    if (!empty($direcao)) $params['direcao'] = $direcao;
                    return '?' . http_build_query($params);
                }
                
                // Botão "Anterior"
                if ($pagina > 1): ?>
                    <a href="<?= gerarUrlPagina($pagina - 1, $limite, $busca, $filtro_alfabetico, $ordem, $direcao) ?>" title="Página anterior">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php else: ?>
                    <span class="desabilitado" title="Página anterior">
                        <i class="fas fa-chevron-left"></i>
                    </span>
                <?php endif; ?>
                
                <?php
                // Mostrar páginas numeradas
                $inicio = max(1, $pagina - 2);
                $fim = min($total_paginas, $pagina + 2);
                
                // Mostrar primeira página se não estiver no início
                if ($inicio > 1): ?>
                    <a href="<?= gerarUrlPagina(1, $limite, $busca, $filtro_alfabetico, $ordem, $direcao) ?>">1</a>
                    <?php if ($inicio > 2): ?>
                        <span>...</span>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $inicio; $i <= $fim; $i++): ?>
                    <?php if ($i == $pagina): ?>
                        <span class="pagina-atual"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= gerarUrlPagina($i, $limite, $busca, $filtro_alfabetico, $ordem, $direcao) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php
                // Mostrar última página se não estiver no fim
                if ($fim < $total_paginas): ?>
                    <?php if ($fim < $total_paginas - 1): ?>
                        <span>...</span>
                    <?php endif; ?>
                    <a href="<?= gerarUrlPagina($total_paginas, $limite, $busca, $filtro_alfabetico, $ordem, $direcao) ?>"><?= $total_paginas ?></a>
                <?php endif; ?>
                
                <?php
                // Botão "Próximo"
                if ($pagina < $total_paginas): ?>
                    <a href="<?= gerarUrlPagina($pagina + 1, $limite, $busca, $filtro_alfabetico, $ordem, $direcao) ?>" title="Próxima página">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="desabilitado" title="Próxima página">
                        <i class="fas fa-chevron-right"></i>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
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