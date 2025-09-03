<?php
require __DIR__ . '/config.php';
verificarAutenticacao(['admin']);

// Verificar configuração de timezone
error_log("Timezone PHP: " . date_default_timezone_get());
error_log("Data atual PHP: " . date('Y-m-d H:i:s'));

$mensagem = '';
$erros = [];

// Processar agendamento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validarTokenCsrf($_POST['csrf_token'])) {
        die("Token CSRF inválido!");
    }

    $agendamento_id = $_POST['agendamento_id'] ?? null;
    $data = $_POST['data'] ?? '';
    $horario = $_POST['horario'] ?? '';
    $paciente_id = $_POST['paciente_id'] ?? '';
    $observacoes = trim($_POST['observacoes'] ?? '');
    $encaixe = isset($_POST['encaixe']) ? 1 : 0;

    // Validações
    if (empty($data)) {
        $erros[] = 'Data é obrigatória.';
    }
    if (empty($horario)) {
        $erros[] = 'Horário é obrigatório.';
    }
    if (empty($paciente_id)) {
        $erros[] = 'Paciente é obrigatório.';
    }

    // Verificar se a data e horário não são passados
    if (!empty($data) && !empty($horario)) {
        $data_hora_agendamento = $data . ' ' . $horario . ':00';
        $data_hora_atual = date('Y-m-d H:i:s');
        
        if ($data_hora_agendamento < $data_hora_atual) {
            $erros[] = 'Não é possível agendar para datas e horários passados.';
        }
    }

    // Verificar se o horário está disponível (permitindo múltiplos pacientes por hora)
    if (empty($erros)) {
        // Determinar o limite baseado no horário (21 pacientes por hora, 11 por 30 minutos, 10 para 11:00)
        $limite = 21; // padrão para horários de 1 hora
        
        // Verificar se é o horário de 7:30 (único horário de 30 minutos)
        if ($horario === '07:30') {
            $limite = 11; // limite para horário de 30 minutos
        }
        // Verificar se é o horário de 11:00 (reduzido para metade)
        if ($horario === '11:00') {
            $limite = 9; // limite reduzido para horário de 11:00
        }
        
        // Contar agendamentos normais e encaixes
        $sql = "SELECT SUM(CASE WHEN encaixe=0 THEN 1 ELSE 0 END) as normais, SUM(CASE WHEN encaixe=1 THEN 1 ELSE 0 END) as encaixes FROM agenda WHERE data = ? AND horario = ? AND status != 'cancelado'";
        $params = [$data, $horario];
        if ($agendamento_id) {
            $sql .= " AND id != ?";
            $params[] = $agendamento_id;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        $normais = (int)($result['normais'] ?? 0);
        $encaixes = (int)($result['encaixes'] ?? 0);
        if (!$encaixe && $normais >= $limite) {
            $erros[] = "Este horário já possui o máximo de pacientes permitidos ({$limite} pacientes). Se necessário, utilize a opção de Encaixe.";
        }
        if ($encaixe && $encaixes >= 3) {
            $erros[] = "Limite de encaixes extras atingido para este horário (3 extras).";
        }
        if ($encaixe && $normais < $limite) {
            $erros[] = "Só é permitido marcar como Encaixe se o limite normal já estiver cheio.";
        }
    }

    // Verificar se o paciente já possui agendamento para o mesmo dia e horário (exceto cancelados e exceto o próprio agendamento em edição)
    if (empty($erros)) {
        $sql = "SELECT COUNT(*) FROM agenda WHERE data = ? AND horario = ? AND paciente_id = ? AND status != 'cancelado'";
        $params = [$data, $horario, $paciente_id];
        if ($agendamento_id) {
            $sql .= " AND id != ?";
            $params[] = $agendamento_id;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $existe = $stmt->fetchColumn();
        if ($existe > 0) {
            $erros[] = "Este paciente já possui agendamento para este horário.";
        }
    }

    // Salvar ou atualizar agendamento
    if (empty($erros)) {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['usuario']['id'])) {
            $erros[] = 'Usuário não identificado. Faça login novamente.';
        } else {
            try {
                if ($agendamento_id) {
                    // Atualizar agendamento existente
                    $stmt = $pdo->prepare("
                        UPDATE agenda 
                        SET data = ?, horario = ?, paciente_id = ?, observacoes = ?, encaixe = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$data, $horario, $paciente_id, $observacoes, $encaixe, $agendamento_id]);
                    $mensagem = '';
                } else {
                    // Inserir novo agendamento
                    error_log("Inserindo novo agendamento: data=$data, horario=$horario, paciente_id=$paciente_id");
                    
                    // Verificar se já existe um agendamento cancelado para este horário
                    $stmt = $pdo->prepare("SELECT id FROM agenda WHERE data = ? AND horario = ? AND status = 'cancelado'");
                    $stmt->execute([$data, $horario]);
                    $agendamentoCancelado = $stmt->fetch();
                    
                    if ($agendamentoCancelado) {
                        error_log("Encontrado agendamento cancelado, atualizando: ID=" . $agendamentoCancelado['id']);
                        // Atualizar o agendamento cancelado existente
                        $stmt = $pdo->prepare("
                            UPDATE agenda 
                            SET paciente_id = ?, observacoes = ?, status = 'agendado', usuario_id = ?, encaixe = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$paciente_id, $observacoes, $_SESSION['usuario']['id'], $encaixe, $agendamentoCancelado['id']]);
                    } else {
                        error_log("Inserindo novo registro de agendamento");
                        // Inserir novo agendamento
                        $stmt = $pdo->prepare("
                            INSERT INTO agenda (data, horario, paciente_id, observacoes, status, usuario_id, encaixe) 
                            VALUES (?, ?, ?, ?, 'agendado', ?, ?)
                        ");
                        $stmt->execute([$data, $horario, $paciente_id, $observacoes, $_SESSION['usuario']['id'], $encaixe]);
                    }
                    $mensagem = '';
                }
            } catch (Exception $e) {
                $erros[] = 'Erro ao salvar agendamento: ' . $e->getMessage();
            }
        }
    }
}

// Buscar pacientes para o select
$pacientes = $pdo->query("
    SELECT p.id, p.nome
    FROM pacientes p 
    WHERE p.ativo = 1 
    ORDER BY p.nome
")->fetchAll();

// Buscar agendamentos do dia atual
// Verificar timezone do banco
$stmt = $pdo->query("SELECT NOW() as agora, @@time_zone as timezone");
$result = $stmt->fetch();
error_log("Banco - Agora: " . $result['agora'] . ", Timezone: " . $result['timezone']);

// Usar a data atual do PHP
$data_atual = date('Y-m-d');
error_log("Data atual definida como: $data_atual");
$mes_atual = date('Y-m');

// Buscar agendamentos do mês atual para permitir visualização de qualquer dia
$agendamentos = $pdo->query("
    SELECT a.*, p.nome as paciente_nome, p.telefone, p.telefone2
    FROM agenda a 
    JOIN pacientes p ON a.paciente_id = p.id 
    WHERE DATE_FORMAT(a.data, '%Y-%m') = '$mes_atual' 
    AND a.status != 'cancelado'
    ORDER BY a.data, a.horario
")->fetchAll();

// Buscar agendamentos do mês inteiro para o calendário
$agendamentos_mes = $pdo->query("
    SELECT a.data, COUNT(*) as total
    FROM agenda a 
    WHERE DATE_FORMAT(a.data, '%Y-%m') = '$mes_atual' 
    AND a.status != 'cancelado'
    GROUP BY a.data
    ORDER BY a.data
")->fetchAll();

// Buscar datas bloqueadas do mês com motivo
$datas_bloqueadas_completo = $pdo->query("
    SELECT data, motivo FROM agenda_bloqueada 
    WHERE DATE_FORMAT(data, '%Y-%m') = '$mes_atual'
")->fetchAll(PDO::FETCH_ASSOC);

// Converter para arrays separados para compatibilidade
$datas_bloqueadas = array_column($datas_bloqueadas_completo, 'data');
$motivos_bloqueio = [];
foreach ($datas_bloqueadas_completo as $item) {
    $motivos_bloqueio[$item['data']] = $item['motivo'];
}

// Converter para um array associativo para fácil acesso
$agendamentos_por_dia = [];
foreach ($agendamentos_mes as $item) {
    $agendamentos_por_dia[$item['data']] = $item['total'];
}

// Função para formatar mês em português
function formatarMes($data) {
    $meses = [
        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
        5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
        9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
    ];
    
    $mes = (int)date('n', strtotime($data));
    $ano = date('Y', strtotime($data));
    
    error_log("Formatando mês: data=$data, mes=$mes, ano=$ano");
    
    return $meses[$mes] . ' de ' . $ano;
}

// Horários disponíveis - Atendimento das 7:30 às 14:00 (7:30-8:00 de 30 min, demais de hora em hora, fechado para almoço das 11:30 às 13:00)
$horarios = [
    '07:30', '08:00', '09:00', '10:00', '11:00', 
    '13:00', '14:00'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda - FarmAlto</title>
    <link rel="icon" type="image/png" href="/images/fav.png">
    <link rel="stylesheet" href="/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="/css/agenda.css?v=<?= time() ?>&v2=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</head>
<body>
    <?php include 'header.php'; ?>
    
    <main class="container">
        <div class="page-header">
            <h1><i class="fas fa-calendar-alt"></i> Agenda de Retiradas</h1>
            <div class="actions">
                <a href="javascript:void(0)" onclick="abrirModalAgendamento()" class="btn-secondary">
                    <i class="fas fa-calendar-plus"></i> Novo Agendamento
                </a>
            </div>
        </div>

        <?php if (!empty($erros)): ?>
            <div class="alert erro">
                <ul>
                    <?php foreach ($erros as $erro): ?>
                        <li><?= htmlspecialchars($erro) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>



        <!-- Calendário -->
        <div class="calendar-container">
            <div class="calendar-header">
                <div class="calendar-navigation">
                    <button type="button" class="btn-nav btn-prev" onclick="navegarMes('anterior')" title="Mês Anterior">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <h2 id="mes-atual"><?= formatarMes($mes_atual . '-01') ?></h2>
                    <button type="button" class="btn-nav btn-next" onclick="navegarMes('proximo')" title="Próximo Mês">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
            
            <div class="calendar">
                <div class="calendar-weekdays">
                    <div>Dom</div>
                    <div>Seg</div>
                    <div>Ter</div>
                    <div>Qua</div>
                    <div>Qui</div>
                    <div>Sex</div>
                    <div>Sáb</div>
                </div>
                <div class="calendar-days" id="calendar-days">
                    <!-- Dias serão inseridos via JavaScript -->
                </div>
            </div>
        </div>

        <!-- Lista de Agendamentos do Dia Selecionado -->
        <div class="agendamentos-container" id="agendamentos-dia-selecionado" style="display: none;">
            <div class="agendamentos-header">
                <h3><i class="fas fa-calendar-day"></i> <span id="titulo-agendamentos-dia">Agendamentos do Dia</span></h3>
                <button type="button" class="btn-close" onclick="fecharAgendamentosDia()" title="Fechar">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="agendamentos-list" id="agendamentos-dia-list">
                <!-- Agendamentos do dia selecionado serão inseridos aqui -->
            </div>
        </div>
    </main>

    <!-- Modal de Agendamento -->
    <div id="modal-agendamento" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-calendar-plus"></i> Novo Agendamento</h3>
                <button type="button" class="btn-close" onclick="fecharModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" id="form-agendamento">
                <input type="hidden" name="csrf_token" value="<?= gerarTokenCsrf() ?>">
                
                <div class="form-group">
                    <label for="data">Data *</label>
                    <input type="date" id="data" name="data" required min="<?= date('Y-m-d') ?>">
                </div>
                
                <div class="form-group form-group-horario-encaixe horario-encaixe-box">
                    <div style="flex:1; min-width: 0;">
                        <label for="horario">Horário *</label>
                        <select id="horario" name="horario" required>
                            <option value="">Selecione um horário...</option>
                            <?php foreach ($horarios as $horario): ?>
                                <option value="<?= $horario ?>"><?= $horario ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="encaixe-group" style="display:none; margin-bottom:0;">
                        <span id="encaixe-info" class="encaixe-info-tip"></span>
                    </div>
                </div>
                
                <div class="form-group paciente-search">
                    <label for="paciente_id">Paciente *</label>
                    <select id="paciente_id" name="paciente_id" required>
                        <option value="">Digite o nome do paciente...</option>
                        <?php foreach ($pacientes as $paciente): ?>
                            <option value="<?= $paciente['id'] ?>">
                                <?= htmlspecialchars($paciente['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="help-text">💡 Digite o nome para buscar rapidamente o paciente</div>
                </div>
                
                <div class="form-group">
                    <label for="observacoes">Observações</label>
                    <textarea id="observacoes" name="observacoes" rows="3" placeholder="Observações sobre o agendamento..."></textarea>
                </div>
                
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="fecharModal()">Cancelar</button>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Salvar Agendamento
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Usar data atual do PHP
        let dataAtual = '<?= $data_atual ?>';
        let agendamentos = <?= json_encode($agendamentos) ?>; // Agendamentos do mês atual
        let agendamentosHoje = <?= json_encode(array_filter($agendamentos, function($agendamento) use ($data_atual) {
            return $agendamento['data'] === $data_atual;
        })) ?>; // Agendamentos apenas do dia atual
        let agendamentosDiaSelecionado = [];
        let agendamentosPorDia = <?= json_encode($agendamentos_por_dia) ?>;
        let datasBloqueadas = <?= json_encode($datas_bloqueadas) ?>;
        let motivosBloqueio = <?= json_encode($motivos_bloqueio) ?>;
        
        // Variáveis para navegação dos meses
        let mesAtual = '<?= $mes_atual ?>';
        let anoAtual = parseInt(mesAtual.split('-')[0]);
        let mesNumAtual = parseInt(mesAtual.split('-')[1]);
        
        console.log('Inicialização - Data atual PHP:', '<?= $data_atual ?>');
        console.log('Inicialização - Data atual JS:', dataAtual);
        console.log('Inicialização - Agendamentos carregados:', agendamentos.length);
        console.log('Inicialização - Agendamentos por dia:', agendamentosPorDia);
        console.log('Inicialização - Mês atual:', mesAtual);
        
        // Funções do calendário
        function gerarCalendario(mes) {
            console.log('=== GERANDO CALENDÁRIO ===');
            console.log('Mês solicitado:', mes);
            console.log('Data atual antes de gerar calendário:', dataAtual);
            
            let ano, mesNum;
            if (!mes || mes === 'undefined-undefined') {
                const hoje = new Date();
                ano = hoje.getFullYear();
                mesNum = hoje.getMonth() + 1;
                mes = ano + '-' + String(mesNum).padStart(2, '0');
                console.log('Mês inválido, usando mês atual:', mes);
            } else {
                [ano, mesNum] = mes.split('-');
                ano = Number(ano);
                mesNum = Number(mesNum);
            }
            const data = new Date(ano, mesNum - 1, 1);
            const primeiroDia = data.getDay();
            const ultimoDia = new Date(data.getFullYear(), data.getMonth() + 1, 0).getDate();
            console.log('ano:', ano, 'mesNum:', mesNum, 'data:', data, 'primeiroDia:', primeiroDia, 'ultimoDia:', ultimoDia);
            // Usar a data atual do PHP em vez de JavaScript para evitar problemas de timezone
            const hojeFormatado = '<?= $data_atual ?>';
            console.log('Data de referência:', data);
            console.log('Hoje formatado (PHP):', hojeFormatado);
            
            let html = '';
            
            // Dias vazios no início
            for (let i = 0; i < primeiroDia; i++) {
                html += '<div class="calendar-day empty"></div>';
            }
            
            // Dias do mês
            for (let dia = 1; dia <= ultimoDia; dia++) {
                const dataCompleta = mes + '-' + String(dia).padStart(2, '0');
                console.log(`Gerando dia ${dia}: dataCompleta = ${dataCompleta}, hojeFormatado = ${hojeFormatado}`);
                
                // Usar os dados dos agendamentos por dia
                const totalAgendamentos = agendamentosPorDia[dataCompleta] || 0;
                const temAgendamento = totalAgendamentos > 0;
                const ehHoje = dataCompleta === hojeFormatado;
                
                // Verificar se a dataCompleta está no formato correto
                if (dataCompleta && dataCompleta.match(/^\d{4}-\d{2}-\d{2}$/)) {
                    console.log('✅ Formato da data está correto:', dataCompleta);
                } else {
                    console.error('❌ Formato da data está incorreto:', dataCompleta);
                    return;
                }
                
                // Verificar se o dia é passado
                const dataAtual = new Date();
                const dataDia = new Date(dataCompleta + ' 00:00:00');
                const hoje = new Date(dataAtual.getFullYear(), dataAtual.getMonth(), dataAtual.getDate());
                const ehPassado = dataDia < hoje;
                
                // Verificar se o dia está bloqueado
                const ehBloqueado = datasBloqueadas.includes(dataCompleta);
                
                // Log adicional para o dia de hoje
                if (ehHoje) {
                    console.log(`*** DIA DE HOJE DETECTADO ***`);
                    console.log(`dataCompleta: ${dataCompleta}`);
                    console.log(`hojeFormatado: ${hojeFormatado}`);
                    console.log(`São iguais? ${dataCompleta === hojeFormatado}`);
                }
                
                // Criar classe CSS baseada no status do dia
                let classeDia = 'calendar-day';
                if (ehHoje) classeDia += ' today';
                if (ehPassado) classeDia += ' past-day';
                if (ehBloqueado) classeDia += ' blocked-day';
                
                // Criar conteúdo do dia
                let conteudoDia = `<div class="day-number">${dia}</div>`;
                
                // Adicionar indicador de bloqueio se estiver bloqueado (lado esquerdo)
                if (ehBloqueado) {
                    conteudoDia += `<div class="day-lock" onclick="desbloquearData('${dataCompleta}', event); event.stopPropagation();"><i class="fas fa-lock"></i></div>`;
                }
                
                // Adicionar indicador de agendamentos se houver (lado direito)
                if (temAgendamento) {
                    conteudoDia += `<div class="day-indicator" onclick="mostrarAgendamentosDia('${dataCompleta}', event); event.stopPropagation();" title="${totalAgendamentos} agendamento(s) - Clique para ver detalhes">${totalAgendamentos}</div>`;
                }
                
                // Adicionar indicador de cadeado no hover (centralizado)
                const lockIcon = ehBloqueado ? 'fa-unlock' : 'fa-lock';
                const lockTitle = ehBloqueado ? 'Clique para desbloquear este dia' : 'Clique para bloquear este dia';
                conteudoDia += `<div class="day-lock-hover" onclick="toggleBloqueioData('${dataCompleta}', event); event.stopPropagation();" title="${lockTitle}"><i class="fas ${lockIcon}"></i></div>`;
                
                const onclick = ehPassado ? '' : `onclick="selecionarData('${dataCompleta}')"`;
                let dayTitle = '';
                
                if (ehPassado) {
                    dayTitle = '';
                } else if (ehBloqueado) {
                    const motivo = motivosBloqueio[dataCompleta] || '';
                    dayTitle = motivo ? `Dia bloqueado: ${motivo}` : 'Dia bloqueado';
                } else {
                    dayTitle = 'Clique na data para criar novo agendamento';
                }
                

                
                html += `<div class="${classeDia}" ${onclick} data-date="${dataCompleta}" title="${dayTitle}">${conteudoDia}</div>`;
            }
            
            document.getElementById('calendar-days').innerHTML = html;
            // Função para formatar mês em português no JavaScript
            const meses = [
                'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
                'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
            ];
            
            // Verificação extra para evitar 'undefined de NaN'
            if (isNaN(data.getMonth()) || isNaN(data.getFullYear())) {
                console.error('Data inválida para título do mês:', data);
                document.getElementById('mes-atual').textContent = '';
                return;
            }
            const mesNome = meses[data.getMonth()];
            const anoTitulo = data.getFullYear();
            console.log('Atualizando título do mês:', mesNome, anoTitulo);
            document.getElementById('mes-atual').textContent = `${mesNome} de ${anoTitulo}`;
            
            console.log('Data atual após gerar calendário (deve permanecer a mesma):', dataAtual);
        }
        
        function carregarAgendamentosMes(mes, callback = null) {
            console.log('=== CARREGANDO AGENDAMENTOS DO MÊS ===');
            console.log('Mês solicitado:', mes);
            
            try {
                const formData = new FormData();
                formData.append('acao', 'buscar_agendamentos');
                formData.append('mes', mes);
                
                fetch('ajax_agenda.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.sucesso) {
                        // Atualizar a variável global de agendamentos
                        agendamentos = data.agendamentos;
                        
                        // Calcular agendamentos por dia
                        agendamentosPorDia = {};
                        agendamentos.forEach(agendamento => {
                            const data = agendamento.data;
                            agendamentosPorDia[data] = (agendamentosPorDia[data] || 0) + 1;
                        });
                        
                        console.log('Agendamentos do mês carregados:', agendamentos.length);
                        console.log('Agendamentos por dia:', agendamentosPorDia);
                        
                        if (callback) callback();
                    } else {
                        console.error('Erro ao carregar agendamentos do mês:', data.erro);
                    }
                })
                .catch(error => {
                    console.error('Erro na requisição:', error);
                });
            } catch (error) {
                console.error('Erro ao carregar agendamentos do mês:', error);
            }
        }
        
        function mudarMes(direcao) {
            console.log('=== MUDANDO MÊS ===');
            console.log('Direção:', direcao);
            console.log('Data atual antes da mudança:', dataAtual);
            
            // Usar a data atual do PHP para extrair o mês, não a dataAtual que pode ter sido alterada
            const dataReferencia = '<?= $data_atual ?>';
            const [ano, mes] = dataReferencia.split('-');
            let novoMes = parseInt(mes) + direcao;
            let novoAno = parseInt(ano);
            
            if (novoMes > 12) {
                novoMes = 1;
                novoAno++;
            } else if (novoMes < 1) {
                novoMes = 12;
                novoAno--;
            }
            
            const novoMesStr = novoAno + '-' + String(novoMes).padStart(2, '0');
            console.log('Novo mês calculado:', novoMesStr);
            
            // Carregar agendamentos do novo mês e depois gerar o calendário
            carregarAgendamentosMes(novoMesStr, function() {
                // IMPORTANTE: Não alterar dataAtual aqui, apenas gerar o calendário
                // A dataAtual deve permanecer como a data atual do PHP
                gerarCalendario(novoMesStr);
            });
            
            console.log('Data atual após mudança de mês (deve permanecer a mesma):', dataAtual);
        }
        
        function selecionarData(data) {
            // Verificar se o dia está bloqueado
            if (datasBloqueadas.includes(data)) {
                const opcao = confirm(`A agenda do dia ${data.split('-').reverse().join('/')} está bloqueada.\n\nDeseja desbloquear?`);
                if (opcao) {
                    desbloquearData(data);
                }
                return;
            }
            
            console.log('=== SELEÇÃO DE DATA ===');
            console.log('Data recebida na função:', data);
            
            // Verificar se a data está no formato correto
            if (data && data.match(/^\d{4}-\d{2}-\d{2}$/)) {
                console.log('✅ Formato da data está correto:', data);
            } else {
                console.error('❌ Formato da data está incorreto:', data);
                return;
            }
            
            // Verificar se a data não é passada
            const dataAtualAgora = new Date();
            const dataSelecionada = new Date(data + ' 00:00:00');
            const hoje = new Date(dataAtualAgora.getFullYear(), dataAtualAgora.getMonth(), dataAtualAgora.getDate());
            
            if (dataSelecionada < hoje) {
                alert('Não é possível agendar para datas passadas.');
                return;
            }
            
            dataAtual = data;
            console.log('Data atual após mudança:', dataAtual);
            

            
            // Remover seleção anterior
            document.querySelectorAll('.calendar-day.selected').forEach(el => {
                el.classList.remove('selected');
            });
            
            // Adicionar seleção ao dia clicado
            const diaElement = document.querySelector(`[data-date="${data}"]`);
            if (diaElement) {
                diaElement.classList.add('selected');
            }
            
            // Limpar formulário e definir como novo agendamento
            document.getElementById('form-agendamento').reset();
            
            // Limpar o campo Select2 do paciente especificamente
            if (window.jQuery && $('#paciente_id').length) {
                $('#paciente_id').val(null).trigger('change');
            }
            
            document.querySelector('.modal-header h3').innerHTML = '<i class="fas fa-calendar-plus"></i> Novo Agendamento';
            
            // Remover campo hidden se existir
            const hiddenId = document.getElementById('agendamento_id');
            if (hiddenId) {
                hiddenId.remove();
            }
            
            // Definir a data selecionada
            document.getElementById('data').value = data;
            console.log('Data definida no campo input:', document.getElementById('data').value);
            
            // Abrir modal primeiro
            document.getElementById('modal-agendamento').style.display = 'flex';
            
            // Focar no campo de paciente após abrir o modal
            setTimeout(function() {
                if (window.jQuery && $('#paciente_id').length) {
                    $('#paciente_id').focus();
                }
            }, 100);
            
            // Carregar agendamentos do dia selecionado e depois atualizar horários
            console.log('Chamando carregarAgendamentosDia para data:', data);
            carregarAgendamentosDia(data, function() {
                // Callback executado após carregar os agendamentos
                console.log('Callback executado, atualizando horários para data:', data);
                atualizarHorariosDisponiveis(data);
                atualizarEncaixeDisponivel(data, document.getElementById('horario').value);
            });
        }
        

        
        function carregarAgendamentosDia(data, callback = null) {
            console.log('=== CARREGANDO AGENDAMENTOS ===');
            console.log('Data solicitada:', data);
            console.log('Data atual antes do carregamento:', dataAtual);
            
            try {
                // Fazer requisição AJAX para carregar os agendamentos do dia
                const formData = new FormData();
                formData.append('acao', 'buscar_agendamentos_dia');
                formData.append('data', data);
                
                console.log('Enviando requisição para ajax_agenda.php com data:', data);
                console.log('FormData contents:');
                for (let [key, value] of formData.entries()) {
                    console.log(`  ${key}: ${value}`);
                }
                
                fetch('ajax_agenda.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Resposta recebida:', data);
                    if (data.sucesso) {
                        agendamentosDiaSelecionado = data.agendamentos;
                        
                        // Atualizar também a variável global de agendamentos
                        // para garantir que os dados estejam disponíveis
                        if (data.agendamentos && data.agendamentos.length > 0) {
                            // Adicionar os agendamentos do dia à lista global
                            data.agendamentos.forEach(agendamento => {
                                // Verificar se já existe na lista global
                                const existe = agendamentos.find(a => a.id === agendamento.id);
                                if (!existe) {
                                    agendamentos.push(agendamento);
                                }
                            });
                        }
                        
                        console.log('Agendamentos de hoje carregados:', agendamentosDiaSelecionado.length);
                        console.log('Dados dos agendamentos de hoje:', agendamentosDiaSelecionado);
                        console.log('Data atual após carregar agendamentos:', dataAtual);
                        console.log('Total de agendamentos na lista global:', agendamentos.length);
                        
                        // Verificar se os agendamentos têm a data correta
                        agendamentosDiaSelecionado.forEach((ag, index) => {
                            console.log(`Agendamento ${index + 1}: data=${ag.data}, horario=${ag.horario}`);
                        });
                        
                        // A lista de agendamentos é atualizada diretamente pela função mostrarAgendamentosDia
                        // quando o usuário clica no indicador
                        
                        // Executar callback se fornecido
                        if (callback && typeof callback === 'function') {
                            console.log('Executando callback após carregar agendamentos');
                            callback();
                        }
                        
                        // Se não há callback, mas estamos atualizando após salvar, forçar atualização da exibição
                        if (!callback && data === dataAtual) {
                            console.log('Forçando atualização da exibição após carregar agendamentos...');
                            setTimeout(() => {
                                mostrarAgendamentosDiaComDados(data, agendamentosDiaSelecionado);
                            }, 100); // Pequeno delay para garantir que tudo foi atualizado
                        }
                        
                        // Adicionar após carregarAgendamentosDia e na resposta AJAX:
                        if (typeof data !== 'undefined' && data.bloqueado) {
                            const horarioSelect = document.getElementById('horario');
                            if (horarioSelect) {
                                horarioSelect.disabled = true;
                            }
                            const btnSalvar = document.querySelector('#form-agendamento button[type="submit"]');
                            if (btnSalvar) btnSalvar.style.display = 'none';
                            let aviso = document.getElementById('aviso-bloqueio-agenda');
                            if (!aviso) {
                                aviso = document.createElement('div');
                                aviso.id = 'aviso-bloqueio-agenda';
                                aviso.className = 'alert erro';
                                aviso.innerHTML = 'A agenda deste dia está <b>bloqueada</b> para novos agendamentos.';
                                document.querySelector('#form-agendamento').prepend(aviso);
                            }
                        } else {
                            const horarioSelect = document.getElementById('horario');
                            if (horarioSelect) horarioSelect.disabled = false;
                            const btnSalvar = document.querySelector('#form-agendamento button[type="submit"]');
                            if (btnSalvar) btnSalvar.style.display = '';
                            const aviso = document.getElementById('aviso-bloqueio-agenda');
                            if (aviso) aviso.remove();
                        }
                        
                        // Remover código antigo do checkbox de bloqueio que não existe mais
                        
                    } else {
                        console.error('Erro ao carregar agendamentos:', data.erro);
                        // Executar callback mesmo com erro para não travar o modal
                        if (callback && typeof callback === 'function') {
                            console.log('Executando callback mesmo com erro');
                            callback();
                        }
                    }
                })
                .catch(error => {
                    console.error('Erro na requisição:', error);
                    // Executar callback mesmo com erro para não travar o modal
                    if (callback && typeof callback === 'function') {
                        console.log('Executando callback mesmo com erro de requisição');
                        callback();
                    }
                });
            } catch (error) {
                console.error('Erro geral na função carregarAgendamentosDia:', error);
                // Executar callback mesmo com erro para não travar o modal
                if (callback && typeof callback === 'function') {
                    console.log('Executando callback mesmo com erro geral');
                    callback();
                }
            }
        }
        
        function atualizarListaAgendamentos() {
            // Esta função não é mais necessária pois a exibição dos agendamentos
            // é feita diretamente pela função mostrarAgendamentosDia
            console.log('=== ATUALIZAR LISTA DE AGENDAMENTOS (DEPRECIADA) ===');
            console.log('Esta função não deve ser chamada diretamente');
        }
        
        // Funções de navegação dos meses
        function navegarMes(direcao) {
            console.log('=== NAVEGANDO MÊS ===');
            console.log('Direção:', direcao);
            console.log('Mês atual antes da navegação:', mesAtual);
            
            let novoAno = anoAtual;
            let novoMes = mesNumAtual;
            
            if (direcao === 'anterior') {
                novoMes--;
                if (novoMes < 1) {
                    novoMes = 12;
                    novoAno--;
                }
            } else if (direcao === 'proximo') {
                novoMes++;
                if (novoMes > 12) {
                    novoMes = 1;
                    novoAno++;
                }
            }
            
            // Atualizar variáveis globais
            anoAtual = novoAno;
            mesNumAtual = novoMes;
            mesAtual = novoAno + '-' + String(novoMes).padStart(2, '0');
            
            console.log('Novo mês calculado:', mesAtual);
            
            // Carregar agendamentos do novo mês
            carregarAgendamentosMes(mesAtual, function() {
                // Atualizar o calendário com os novos dados
                gerarCalendario(mesAtual);
                
                // Fechar seção de agendamentos do dia se estiver aberta
                fecharAgendamentosDia();
                
                console.log('Navegação concluída - Mês atual:', mesAtual);
            });
        }
        
        // Funções do modal
        function abrirModalAgendamento() {
            // Limpar formulário se não for edição
            if (!document.getElementById('agendamento_id') || !document.getElementById('agendamento_id').value) {
                document.getElementById('form-agendamento').reset();
                
                // Limpar o campo Select2 do paciente especificamente
                if (window.jQuery && $('#paciente_id').length) {
                    $('#paciente_id').val(null).trigger('change');
                }
                
                document.querySelector('.modal-header h3').innerHTML = '<i class="fas fa-calendar-plus"></i> Novo Agendamento';
                // Usar a data atual do PHP em vez de JavaScript
                const hojeFormatado = '<?= $data_atual ?>';
                console.log('Abrindo modal com data de hoje (PHP):', hojeFormatado);
                document.getElementById('data').value = hojeFormatado;
                // Recarregar agendamentos do dia antes de atualizar horários e encaixe
                carregarAgendamentosDia(hojeFormatado, function() {
                    atualizarHorariosDisponiveis(hojeFormatado);
                    atualizarEncaixeDisponivel(hojeFormatado, document.getElementById('horario').value);
                });
                // Remover campo hidden se existir
                const hiddenId = document.getElementById('agendamento_id');
                if (hiddenId) {
                    hiddenId.remove();
                }
            }
            document.getElementById('modal-agendamento').style.display = 'flex';
            
            // Focar no campo de paciente após abrir o modal
            setTimeout(function() {
                if (window.jQuery && $('#paciente_id').length) {
                    $('#paciente_id').focus();
                }
            }, 100);
        }
        
        function fecharModal() {
            document.getElementById('modal-agendamento').style.display = 'none';
            document.getElementById('form-agendamento').reset();
            
            // Limpar o campo Select2 do paciente especificamente
            if (window.jQuery && $('#paciente_id').length) {
                $('#paciente_id').val(null).trigger('change');
            }
        }
        
        function editarAgendamento(id) {
            console.log('=== EDITANDO AGENDAMENTO ===');
            console.log('ID recebido:', id);
            console.log('Tipo do ID:', typeof id);
            console.log('Agendamentos disponíveis:', agendamentos);
            
            const agendamento = agendamentos.find(a => a.id == id);
            if (!agendamento) {
                console.error('Agendamento não encontrado para ID:', id);
                alert('Agendamento não encontrado!');
                return;
            }
            console.log('Dados do agendamento encontrado:', agendamento);
            
            // Preencher o formulário com os dados do agendamento
            console.log('Preenchendo formulário...');
            console.log('Data:', agendamento.data);
            console.log('Paciente ID:', agendamento.paciente_id);
            console.log('Observações:', agendamento.observacoes);
            console.log('Encaixe:', agendamento.encaixe);
            
            document.getElementById('data').value = agendamento.data;
            document.getElementById('paciente_id').value = agendamento.paciente_id;
            document.getElementById('observacoes').value = agendamento.observacoes || '';
            // Campo encaixe é gerenciado automaticamente pelo sistema
            
            // Atualizar Select2 se estiver disponível
            if (window.jQuery && $('#paciente_id').length) {
                console.log('Atualizando Select2...');
                $('#paciente_id').trigger('change');
            }
            
            // Adicionar campo hidden para o ID
            let hiddenId = document.getElementById('agendamento_id');
            if (!hiddenId) {
                hiddenId = document.createElement('input');
                hiddenId.type = 'hidden';
                hiddenId.id = 'agendamento_id';
                hiddenId.name = 'agendamento_id';
                document.getElementById('form-agendamento').appendChild(hiddenId);
            }
            hiddenId.value = agendamento.id;
            
            // Atualizar horários disponíveis (incluindo o horário atual do agendamento)
            atualizarHorariosDisponiveis(agendamento.data, agendamento.horario);
            atualizarEncaixeDisponivel(agendamento.data, agendamento.horario); // Atualizar encaixe disponível
            
            // Alterar título do modal
            document.querySelector('.modal-header h3').innerHTML = '<i class="fas fa-edit"></i> Editar Agendamento';
            
            console.log('Abrindo modal...');
            const modal = document.getElementById('modal-agendamento');
            if (modal) {
                modal.style.display = 'flex';
                console.log('Modal aberto com sucesso');
            } else {
                console.error('Modal não encontrado!');
            }
        }
        
        function cancelarAgendamento(id) {
            console.log('Cancelando agendamento ID:', id);
            if (confirm('Tem certeza que deseja cancelar este agendamento?')) {
                const formData = new FormData();
                formData.append('acao', 'cancelar_agendamento');
                formData.append('id', id);
                formData.append('csrf_token', '<?= gerarTokenCsrf() ?>');
                
                fetch('ajax_agenda.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.sucesso) {
                        // Pega a data do agendamento cancelado
                        let dataCancelada = dataAtual;
                        
                        // Se temos a data do agendamento cancelado na resposta, usar ela
                        if (data.data_agendamento) {
                            dataCancelada = data.data_agendamento;
                        }
                        
                        if (!dataCancelada || !dataCancelada.includes('-')) {
                            console.error('Data inválida ao tentar atualizar o calendário (cancelamento):', dataCancelada);
                            dataCancelada = dataAtual;
                        }
                        carregarAgendamentosDia(dataCancelada, function() {
                            // Fecha a seção de agendamentos do dia após cancelar
                            fecharAgendamentosDia();
                            // Atualiza agendamentos do mês e o calendário
                            const [ano, mes] = dataCancelada.split('-');
                            if (!ano || !mes) {
                                console.error('Ano ou mês inválido ao atualizar calendário (cancelamento):', ano, mes, dataCancelada);
                                return;
                            }
                            const mesAtual = ano + '-' + mes;
                            carregarAgendamentosMes(mesAtual, function() {
                                gerarCalendario(mesAtual);
                                
                                // Se a data cancelada for a mesma que está sendo exibida, atualizar também a exibição
                                if (dataCancelada === dataAtual) {
                                    console.log('Data cancelada é a mesma da exibição atual, atualizando exibição...');
                                    setTimeout(() => {
                                        // Usar os agendamentos já atualizados em memória
                                        mostrarAgendamentosDiaComDados(dataCancelada, agendamentosDiaSelecionado);
                                    }, 500); // Pequeno delay para garantir que tudo foi atualizado
                                }
                            });
                        });
                    } else {
                        alert('Erro: ' + data.erro);
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao cancelar agendamento');
                });
            }
        }
        
        // Verificar disponibilidade de horário
        document.getElementById('data').addEventListener('change', function() {
            const data = this.value;
            if (data) {
                atualizarHorariosDisponiveis(data);
                atualizarEncaixeDisponivel(data, document.getElementById('horario').value); // Atualizar encaixe disponível
            }
        });
        
        function atualizarHorariosDisponiveis(data, horarioAtual = null) {
            console.log('=== ATUALIZANDO HORÁRIOS ===');
            console.log('Data solicitada:', data);
            console.log('Horário atual (para edição):', horarioAtual);
            console.log('Data atual global:', dataAtual);
            console.log('Agendamentos carregados:', agendamentos);
            
            const horarioSelect = document.getElementById('horario');
            const encaixeGroup = document.getElementById('encaixe-group');
            const encaixeInfo = document.getElementById('encaixe-info');

            const agendamentoId = document.getElementById('agendamento_id')?.value;
            
            // Buscar agendamentos da data (excluindo o agendamento atual se for edição)
            const agendamentosDaData = agendamentos.filter(a => {
                console.log(`Comparando: agendamento.data="${a.data}" com data="${data}"`);
                return a.data === data && (!agendamentoId || a.id != agendamentoId);
            });
            
            // Contar quantos pacientes há em cada horário
            const contagemHorarios = {};
            agendamentosDaData.forEach(a => {
                const horario = normalizarHorario(a.horario);
                contagemHorarios[horario] = (contagemHorarios[horario] || 0) + 1;
            });
            
            console.log('Contagem de pacientes por horário:', contagemHorarios);
            
            // Limpar opções atuais
            horarioSelect.innerHTML = '<option value="">Selecione um horário...</option>';
            encaixeGroup.style.display = 'none'; // Esconder o grupo de encaixe
            encaixeInfo.textContent = ''; // Limpar o texto de informação
            
            // Adicionar horários disponíveis - Atendimento das 7:30 às 14:00 (7:30-8:00 de 30 min, demais de hora em hora, fechado para almoço das 11:30 às 13:00)
            const horariosDisponiveis = [
                '07:30', '08:00', '09:00', '10:00', '11:00', 
                '13:00', '14:00'
            ];
            
            let horariosDisponiveisCount = 0;
            let horariosLotadosCount = 0;
            
            horariosDisponiveis.forEach(horario => {
                const option = document.createElement('option');
                option.value = horario;
                const pacientesNoHorario = contagemHorarios[horario] || 0;
                // Determinar o limite baseado no horário (21 pacientes por hora, 11 por 30 minutos, 10 para 11:00)
                let limite = 21; // padrão para horários de 1 hora
                if (horario === '07:30') {
                    limite = 11; // limite para horário de 30 minutos
                } else if (horario === '11:00') {
                    limite = 9; // limite reduzido para horário de 11:00
                }
                // Contar encaixes já agendados
                const ags = agendamentos.filter(a => a.data === data && a.horario === horario);
                const encaixes = ags.filter(a => a.encaixe).length;
                const isLotado = pacientesNoHorario >= limite;
                const encaixeDisponivel = encaixes < 3;

                // Verificar se o horário é passado
                const dataHoraAtual = new Date();
                const dataHoraAgendamento = new Date(data + ' ' + horario + ':00');
                const isHorarioPassado = dataHoraAgendamento < dataHoraAtual;

                if (isHorarioPassado) {
                    option.textContent = `${horario}`;
                    option.disabled = true;
                    option.style.color = '#6c757d';
                    option.style.fontWeight = 'bold';
                    console.log(`Marcando ${horario} como horário passado`);
                } else if (isLotado && !encaixeDisponivel) {
                    option.textContent = `${horario} - Lotado (${pacientesNoHorario}/${limite})`;
                    option.disabled = true;
                    option.style.color = '#dc3545';
                    option.style.fontWeight = 'bold';
                    horariosLotadosCount++;
                    console.log(`Marcando ${horario} como lotado e desabilitado (sem encaixe extra)`);
                } else if (isLotado && encaixeDisponivel) {
                    option.textContent = `${horario} - Lotado (${pacientesNoHorario}/${limite}) (Encaixe disponível)`;
                    option.style.color = '#d35400';
                    option.style.fontWeight = 'bold';
                    horariosLotadosCount++;
                    console.log(`Marcando ${horario} como lotado, mas com encaixe disponível`);
                } else {
                    option.textContent = `${horario} (${pacientesNoHorario}/${limite} pacientes)`;
                    option.style.color = pacientesNoHorario > 0 ? '#ffc107' : '#28a745';
                    horariosDisponiveisCount++;
                }
                // Se for edição, selecionar o horário atual
                if (horarioAtual && horario === horarioAtual) {
                    option.selected = true;
                }
                horarioSelect.appendChild(option);
            });
            
            console.log(`Horários atualizados: ${horariosDisponiveisCount} disponíveis, ${horariosLotadosCount} lotados`);

            // Atualizar disponibilidade do encaixe após carregar horários
            atualizarEncaixeDisponivel(data, horarioAtual);
        }
        
        function verificarDisponibilidade(data, horario) {
            console.log('Verificando disponibilidade para:', data, horario);
            const agendamentosNoHorario = agendamentos.filter(a => 
                a.data === data && a.horario === horario
            );
            
            // Determinar o limite baseado no horário (21 pacientes por hora, 11 por 30 minutos, 10 para 11:00)
            let limite = 21; // padrão para horários de 1 hora
            if (horario === '07:30') {
                limite = 11; // limite para horário de 30 minutos
            } else if (horario === '11:00') {
                limite = 9; // limite reduzido para horário de 11:00
            }
            
            if (agendamentosNoHorario.length >= limite) {
                console.log('Horário lotado:', agendamentosNoHorario.length, 'pacientes');
                document.getElementById('horario').classList.add('unavailable');
                alert(`Este horário já possui o máximo de pacientes permitidos (${limite} pacientes)!`);
            } else {
                console.log('Horário disponível:', agendamentosNoHorario.length, 'pacientes');
                document.getElementById('horario').classList.remove('unavailable');
            }
        }
        
        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            const modal = document.getElementById('modal-agendamento');
            if (event.target === modal) {
                fecharModal();
            }
        }
        

        
        function normalizarHorario(horario) {
            // Garante que o horário sempre terá o formato HH:MM
            return horario.slice(0, 5);
        }
        
        // Inicializar calendário
        document.addEventListener('DOMContentLoaded', function() {
            console.log('=== INICIALIZAÇÃO ===');
            console.log('Data atual PHP:', '<?= $data_atual ?>');
            console.log('Data atual JS:', dataAtual);
            console.log('Agendamentos carregados no PHP:', <?= json_encode($agendamentos) ?>);
            console.log('Mês atual para calendário:', mesAtual);
            
            gerarCalendario(mesAtual);
            
            // Aguardar um pouco antes de carregar os agendamentos do horário atual
            setTimeout(function() {
                carregarAgendamentosHorarioAtual();
            }, 500);
            
            // Inicializar Select2 para o campo de paciente
            if (window.jQuery && $('#paciente_id').length) {
                $('#paciente_id').select2({
                    width: '100%',
                    placeholder: 'Digite o nome do paciente...',
                    allowClear: true,
                    minimumInputLength: 0,
                    delay: 300,
                    language: {
                        noResults: function() { return "Nenhum paciente encontrado"; },
                        searching: function() { return "Buscando..."; },
                        inputTooShort: function() { return "Digite para buscar"; }
                    }
                });
                
                // Abrir o dropdown automaticamente quando o campo receber foco
                $('#paciente_id').on('select2:open', function() {
                    // Focar no campo de busca do Select2
                    setTimeout(function() {
                        $('.select2-search__field').focus();
                    }, 10);
                });
                
                // Abrir o dropdown quando clicar no campo
                $('#paciente_id').on('focus', function() {
                    if (!$(this).hasClass('select2-hidden-accessible')) {
                        $(this).select2('open');
                    }
                });
            }
        });

        // Função para carregar agendamentos do horário atual
        function carregarAgendamentosHorarioAtual() {
            console.log('=== CARREGANDO AGENDAMENTOS DO HORÁRIO ATUAL ===');
            
            // Obter horário atual
            const agora = new Date();
            const horaAtual = agora.getHours();
            const minutoAtual = agora.getMinutes();
            const minutosTotais = horaAtual * 60 + minutoAtual;
            const horarioAtual = `${String(horaAtual).padStart(2, '0')}:${String(minutoAtual).padStart(2, '0')}`;
            
            console.log('Horário atual:', horarioAtual);
            console.log('Data atual:', dataAtual);
            
            // Definir horários disponíveis e seus intervalos finais
            const horariosDisponiveis = [
                '07:30', '08:00', '09:00', '10:00', '11:00', 
                '13:00', '14:00'
            ];
            const intervalos = [
                {inicio: '07:30', fim: '08:00'},
                {inicio: '08:00', fim: '09:00'},
                {inicio: '09:00', fim: '10:00'},
                {inicio: '10:00', fim: '11:00'},
                {inicio: '11:00', fim: '11:30'}, // 11:00 até 11:30 (meia hora)
                {inicio: '13:00', fim: '14:00'},
                {inicio: '14:00', fim: '15:00'}
            ];
            
            // Definir horário de almoço
            const horarioAlmocoInicio = 11 * 60 + 30; // 11:30
            const horarioAlmocoFim = 13 * 60; // 13:00
            
            // Encontrar o horário correspondente ao horário atual
            let proximoHorario = null;
            for (let i = 0; i < intervalos.length; i++) {
                const [hIni, mIni] = intervalos[i].inicio.split(':').map(Number);
                const [hFim, mFim] = intervalos[i].fim.split(':').map(Number);
                const minIni = hIni * 60 + mIni;
                const minFim = hFim * 60 + mFim;
                if (minutosTotais >= minIni && minutosTotais < minFim) {
                    proximoHorario = intervalos[i].inicio;
                    break;
                }
            }
            
            // Se não encontrou horário no dia atual, determinar o próximo horário disponível
            if (!proximoHorario) {
                // Verificar se já passou de todos os horários do dia
                const ultimoHorario = '14:00'; // Último horário disponível
                const [hUltimo, mUltimo] = ultimoHorario.split(':').map(Number);
                const minUltimo = hUltimo * 60 + mUltimo;
                
                if (minutosTotais >= minUltimo) {
                    // Se já passou do último horário, mostrar a partir do último horário
                    proximoHorario = ultimoHorario;
                    console.log('Já passou do último horário, mostrando a partir de:', proximoHorario);
                } else if (minutosTotais >= horarioAlmocoInicio) {
                    // Se passou do horário de almoço, mostrar a partir de 13:00
                    proximoHorario = '13:00';
                    console.log('Passou do horário de almoço, mostrando a partir de:', proximoHorario);
                } else {
                    // Se ainda não chegou no primeiro horário, mostrar a partir do primeiro
                    proximoHorario = '07:30';
                    console.log('Ainda não chegou no primeiro horário, mostrando a partir de:', proximoHorario);
                }
            }
            
            // Verificar se estamos no horário de almoço (11:30 às 13:00)
            if (minutosTotais >= horarioAlmocoInicio && minutosTotais < horarioAlmocoFim) {
                // Se estamos no horário de almoço, mostrar a partir de 13:00
                proximoHorario = '13:00';
                console.log('Estamos no horário de almoço, mostrando a partir de:', proximoHorario);
            }
            
            console.log('Horário correspondente ao horário atual:', proximoHorario);
            
            // Carregar agendamentos do dia atual de forma síncrona
            carregarAgendamentosDia(dataAtual, function() {
                // Aguardar um pouco para garantir que os dados foram carregados
                setTimeout(function() {
                    mostrarAgendamentosAPartirDoHorario(proximoHorario);
                }, 100);
            });
        }

        // Função para mostrar agendamentos a partir de um horário específico
        function mostrarAgendamentosAPartirDoHorario(horarioInicial, dataRef = null) {
            console.log('=== MOSTRANDO AGENDAMENTOS A PARTIR DO HORÁRIO ===');
            console.log('Horário inicial:', horarioInicial);
            const dataParaMostrar = dataRef || dataAtual;
            const container = document.getElementById('agendamentos-dia-selecionado');
            const listaContainer = document.getElementById('agendamentos-dia-list');
            const tituloElement = document.getElementById('titulo-agendamentos-dia');
            if (!container || !listaContainer || !tituloElement) {
                console.error('Elementos não encontrados');
                return;
            }
            
            console.log('Data para mostrar:', dataParaMostrar);
            console.log('Agendamentos globais disponíveis:', agendamentos);
            
            // Atualizar título
            const dataFormatada = dataParaMostrar.split('-').reverse().join('/');
            tituloElement.textContent = `Agendamentos do dia ${dataFormatada} (a partir das ${horarioInicial})`;
            
            // Filtrar agendamentos do dia selecionado
            const agendamentosDoDia = agendamentos.filter(a => a.data === dataParaMostrar);
            console.log('Agendamentos do dia:', agendamentosDoDia);
            
            // Se não há agendamentos carregados, mostrar mensagem em vez de tentar carregar novamente
            if (agendamentos.length === 0) {
                console.log('Nenhum agendamento carregado, mostrando mensagem de nenhum agendamento');
                listaContainer.innerHTML = '<div class="no-appointments">Nenhum agendamento para este dia.</div>';
                container.style.display = 'block';
                return;
            }
            
            if (agendamentosDoDia.length === 0) {
                listaContainer.innerHTML = '<div class="no-appointments">Nenhum agendamento para este dia.</div>';
                container.style.display = 'block';
                return;
            }
            
            // Organizar agendamentos por horário
            const agendamentosPorHorario = {};
            const horariosDisponiveis = [
                '07:30', '08:00', '09:00', '10:00', '11:00', 
                '13:00', '14:00'
            ];
            horariosDisponiveis.forEach(horario => {
                agendamentosPorHorario[horario] = [];
            });
            agendamentosDoDia.forEach(agendamento => {
                const horarioNormalizado = agendamento.horario.substring(0, 5);
                if (agendamentosPorHorario[horarioNormalizado]) {
                    agendamentosPorHorario[horarioNormalizado].push(agendamento);
                }
            });
            // Filtrar apenas horários a partir do horário inicial
            const horariosFiltrados = horariosDisponiveis.filter(horario => horario >= horarioInicial);
            console.log('Horários filtrados:', horariosFiltrados);
            console.log('Horário inicial:', horarioInicial);
            console.log('Horários disponíveis:', horariosDisponiveis);
            
            if (horariosFiltrados.length === 0) {
                listaContainer.innerHTML = '<div class="no-appointments">Nenhum horário disponível a partir das ' + horarioInicial + '.</div>';
                container.style.display = 'block';
                return;
            }
            let html = '';
            html += '<div class="horarios-tabs">';
            let primeiraAba = true;
            horariosFiltrados.forEach(horario => {
                const agendamentosHorario = agendamentosPorHorario[horario];
                const temAgendamentos = agendamentosHorario.length > 0;
                let limite = 21;
                if (horario === '07:30') {
                    limite = 11;
                } else if (horario === '11:00') {
                    limite = 9;
                }
                const ocupacao = agendamentosHorario.length;
                html += `
                    <button class="tab-button ${primeiraAba ? 'active' : ''}" 
                            onclick="trocarAba('${horario}')" 
                            data-horario="${horario}">
                        <span class="horario-tab">${horario}</span>
                        <span class="contador-tab">${ocupacao}/${limite}</span>
                        ${temAgendamentos ? '<span class="indicador-agendamentos">●</span>' : ''}
                    </button>
                `;
                primeiraAba = false;
            });
            html += '</div>';
            html += '<div class="tab-content">';
            primeiraAba = true;
            horariosFiltrados.forEach(horario => {
                const agendamentosHorario = agendamentosPorHorario[horario];
                let limite = 21;
                if (horario === '07:30') {
                    limite = 11;
                } else if (horario === '11:00') {
                    limite = 9;
                }
                const ocupacao = agendamentosHorario.length;
                html += `
                    <div class="tab-pane ${primeiraAba ? 'active' : ''}" id="tab-${horario}">
                        <div class="horario-header">
                            <h4><i class="fas fa-clock"></i> Horário: ${horario}</h4>
                            <div class="horario-info">
                                <span class="ocupacao ${ocupacao >= limite ? 'lotado' : ''}">
                                    ${ocupacao}/${limite} pacientes
                                </span>
                                ${ocupacao >= limite ? '<span class="status-lotado">LOTADO</span>' : ''}
                            </div>
                        </div>
                        <div class="agendamentos-horario">
                `;
                if (agendamentosHorario.length === 0) {
                    html += '<div class="no-appointments-horario">Nenhum agendamento para este horário.</div>';
                } else {
                    agendamentosHorario.forEach(agendamento => {
                        const telefone = agendamento.telefone + (agendamento.telefone2 ? ' / ' + agendamento.telefone2 : '');
                        const encaixeLabel = (agendamento.encaixe == 1 || agendamento.encaixe == '1') ? 
                            '<span class="encaixe-label" style="color:#d35400;font-weight:bold;">[Encaixe]</span>' : '';
                        html += `
                            <div class="agendamento-item" data-id="${agendamento.id}">
                                <div class="agendamento-info">
                                    <div class="agendamento-paciente">
                                        <i class="fas fa-user"></i>
                                        ${agendamento.paciente_nome}
                                    </div>
                                    <div class="agendamento-telefone">
                                        <i class="fas fa-phone"></i>
                                        ${telefone}
                                    </div>
                                    ${agendamento.observacoes ? `
                                        <div class="agendamento-obs">
                                            <i class="fas fa-comment"></i>
                                            ${agendamento.observacoes}
                                        </div>
                                    ` : ''}
                                    ${encaixeLabel}
                                </div>
                                <div class="agendamento-actions">
                                    <button type="button" class="btn-secondary" onclick="editarAgendamento(${agendamento.id})">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn-danger" onclick="cancelarAgendamento(${agendamento.id})">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        `;
                    });
                }
                html += `
                        </div>
                    </div>
                `;
                primeiraAba = false;
            });
            html += '</div>';
            listaContainer.innerHTML = html;
            container.style.display = 'block';
            container.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function fecharAgendamentosDia() {
            const container = document.getElementById('agendamentos-dia-selecionado');
            if (container) {
                container.style.display = 'none';
            }
        }

        function trocarAba(horario) {
            // Remover classe active de todas as abas
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            // Remover classe active de todos os painéis
            document.querySelectorAll('.tab-pane').forEach(pane => {
                pane.classList.remove('active');
            });
            
            // Adicionar classe active na aba clicada
            const buttonClicado = document.querySelector(`[data-horario="${horario}"]`);
            if (buttonClicado) {
                buttonClicado.classList.add('active');
            }
            
            // Mostrar o painel correspondente
            const painel = document.getElementById(`tab-${horario}`);
            if (painel) {
                painel.classList.add('active');
            }
        }

        // Função de teste para verificar agendamentos
        function testarAgendamentos() {
            console.log('=== TESTE DE AGENDAMENTOS ===');
            console.log('Testando busca de agendamentos para hoje...');
            console.log('Data atual:', dataAtual);
            
            const formData = new FormData();
            formData.append('acao', 'buscar_agendamentos_dia');
            formData.append('data', dataAtual);
            
            fetch('ajax_agenda.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Resposta do teste:', data);
                if (data.sucesso) {
                    console.log('Agendamentos encontrados:', data.agendamentos.length);
                    data.agendamentos.forEach((agendamento, index) => {
                        console.log(`Agendamento ${index + 1}:`, agendamento);
                    });
                    
                    // Testar mostrar agendamentos a partir do horário atual
                    const agora = new Date();
                    const horaAtual = agora.getHours();
                    const minutoAtual = agora.getMinutes();
                    const horarioAtual = `${String(horaAtual).padStart(2, '0')}:${String(minutoAtual).padStart(2, '0')}`;
                    console.log('Horário atual para teste:', horarioAtual);
                    
                    // Atualizar a lista global de agendamentos
                    agendamentos = data.agendamentos;
                    
                    // Chamar a função de mostrar agendamentos com o horário atual
                    const minutosTotais = horaAtual * 60 + minutoAtual;
                    
                    // Determinar o horário apropriado para teste
                    let horarioTeste = '07:30'; // padrão
                    if (minutosTotais >= 11 * 60 + 30 && minutosTotais < 13 * 60) {
                        horarioTeste = '13:00'; // horário de almoço
                    } else if (minutosTotais >= 13 * 60) {
                        horarioTeste = '13:00'; // após almoço
                    }
                    
                    mostrarAgendamentosAPartirDoHorario(horarioTeste);
                } else {
                    console.log('Erro:', data.erro);
                }
            })
            .catch(error => {
                console.error('Erro no teste:', error);
            });
        }



        // Função para atualizar a disponibilidade do encaixe
        function atualizarEncaixeDisponivel(data, horario) {
            console.log('[Encaixe] Atualizando encaixe para data:', data, 'horário:', horario);
            const encaixeGroup = document.getElementById('encaixe-group');
            const encaixeInfo = document.getElementById('encaixe-info');
            encaixeGroup.style.display = 'none';
            encaixeInfo.textContent = '';
            if (!data || !horario) return;
            
            const agendamentoId = document.getElementById('agendamento_id')?.value;
            
            // Contar normais e encaixes (excluindo o agendamento atual se for edição)
            const ags = agendamentos.filter(a => 
                a.data === data && 
                a.horario && 
                a.horario.startsWith(horario) &&
                (!agendamentoId || a.id != agendamentoId)
            );
            let normais = ags.filter(a => a.encaixe != 1 && a.encaixe != '1').length;
            let encaixes = ags.filter(a => a.encaixe == 1 || a.encaixe == '1').length;
            let limite = 21; // padrão para horários de 1 hora
            if (horario === '07:30') {
                limite = 11; // limite para horário de 30 minutos
            } else if (horario === '11:00') {
                limite = 9; // limite reduzido para horário de 11:00
            }
            console.log(`[Encaixe] normais: ${normais}, encaixes: ${encaixes}, limite: ${limite}, agendamentoId: ${agendamentoId}`);
            if (normais >= limite && encaixes < 3) {
                encaixeGroup.style.display = 'block';
                encaixeInfo.textContent = `Limite normal atingido (${normais}/${limite}). Você pode adicionar até ${3-encaixes} encaixe(s) extra(s).`;
                console.log('[Encaixe] Exibindo opção de encaixe extra!');
            }
        }

        // Ao abrir o modal, atualizar encaixe disponível
        const modalAgendamento = document.getElementById('modal-agendamento');
        if (modalAgendamento) {
            modalAgendamento.addEventListener('show', function() {
                const data = document.getElementById('data').value;
                const horario = document.getElementById('horario').value;
                atualizarEncaixeDisponivel(data, horario);
            });
            // Compatível com Bootstrap modal
            $(modalAgendamento).on('shown.bs.modal', function() {
                const data = document.getElementById('data').value;
                const horario = document.getElementById('horario').value;
                atualizarEncaixeDisponivel(data, horario);
            });
        }

        // Sempre que o horário for alterado, atualizar encaixe disponível
        const horarioSelect = document.getElementById('horario');
        if (horarioSelect) {
            horarioSelect.addEventListener('change', function() {
                const data = document.getElementById('data').value;
                atualizarEncaixeDisponivel(data, this.value);
            });
        }

        // Sempre que um novo agendamento for salvo, atualizar encaixe disponível
        // (adicione esta chamada após salvar agendamento via AJAX, se aplicável)
        // atualizarEncaixeDisponivel(data, horario);

        document.getElementById('form-agendamento').addEventListener('submit', function(e) {
            e.preventDefault(); // Prevenir submit normal
            
            const data = document.getElementById('data').value;
            const horario = document.getElementById('horario').value;
            const paciente_id = document.getElementById('paciente_id').value;
            const observacoes = document.getElementById('observacoes').value;
            const agendamento_id = document.getElementById('agendamento_id')?.value || '';
            
            // Contar normais e encaixes para o horário selecionado (excluindo o agendamento atual se for edição)
            const ags = agendamentos.filter(a => 
                a.data === data && 
                a.horario && 
                a.horario.startsWith(horario) &&
                (!agendamento_id || a.id != agendamento_id)
            );
            let normais = ags.filter(a => a.encaixe != 1 && a.encaixe != '1').length;
            let encaixes = ags.filter(a => a.encaixe == 1 || a.encaixe == '1').length;
            let limite = 21; // padrão para horários de 1 hora
            if (horario === '07:30') {
                limite = 11; // limite para horário de 30 minutos
            } else if (horario === '11:00') {
                limite = 9; // limite reduzido para horário de 11:00
            }
            
            // Determinar se é encaixe
            let encaixe = 0;
            
            if (agendamento_id) {
                // Se for edição, buscar o agendamento original para manter o tipo
                const agendamentoOriginal = agendamentos.find(a => a.id == agendamento_id);
                if (agendamentoOriginal) {
                    encaixe = agendamentoOriginal.encaixe == 1 || agendamentoOriginal.encaixe == '1' ? 1 : 0;
                    console.log('Editando agendamento - mantendo tipo original:', encaixe ? 'encaixe' : 'normal');
                }
            } else {
                // Se for novo agendamento, aplicar lógica de encaixe automático
                if (normais >= limite && encaixes < 3) {
                    encaixe = 1;
                    console.log('Novo agendamento - aplicando encaixe automático');
                }
            }
            
            // Validar campos obrigatórios
            if (!data || !horario || !paciente_id) {
                alert('Por favor, preencha todos os campos obrigatórios.');
                return;
            }
            
            // Validar se a data e horário não são passados
            const dataHoraAtual = new Date();
            const dataHoraAgendamento = new Date(data + ' ' + horario + ':00');
            
            if (dataHoraAgendamento < dataHoraAtual) {
                alert('Não é possível agendar para datas e horários passados.');
                return;
            }
            
            // Enviar via AJAX
            const formData = new FormData();
            formData.append('acao', agendamento_id ? 'editar_agendamento' : 'salvar_agendamento');
            formData.append('data', data);
            formData.append('horario', horario);
            formData.append('paciente_id', paciente_id);
            formData.append('observacoes', observacoes);
            formData.append('encaixe', encaixe);
            formData.append('csrf_token', '<?= gerarTokenCsrf() ?>');
            
            if (agendamento_id) {
                formData.append('id', agendamento_id);
            }
            
            fetch('ajax_agenda.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.sucesso) {
                    fecharModal();
                    // Pega a data do agendamento salvo
                    let dataSalva = document.getElementById('data').value || dataAtual;
                    if (!dataSalva || !dataSalva.includes('-')) {
                        console.error('Data inválida ao tentar atualizar o calendário:', dataSalva);
                        dataSalva = dataAtual;
                    }
                    
                    console.log('=== ATUALIZANDO APÓS SALVAR AGENDAMENTO ===');
                    console.log('Data do agendamento salvo:', dataSalva);
                    
                    // Atualiza agendamentos do dia salvo
                    carregarAgendamentosDia(dataSalva, function() {
                        console.log('Agendamentos do dia atualizados, agora atualizando mês...');
                        // Atualiza agendamentos do mês e o calendário
                        const [ano, mes] = dataSalva.split('-');
                        if (!ano || !mes) {
                            console.error('Ano ou mês inválido ao atualizar calendário:', ano, mes, dataSalva);
                            return;
                        }
                        const mesAtual = ano + '-' + mes;
                        console.log('Mês para atualizar:', mesAtual);
                        carregarAgendamentosMes(mesAtual, function() {
                            console.log('Agendamentos do mês atualizados, gerando calendário...');
                            gerarCalendario(mesAtual);
                            
                            // Se a data salva for a mesma que está sendo exibida, atualizar também a exibição
                            if (dataSalva === dataAtual) {
                                console.log('Data salva é a mesma da exibição atual, atualizando exibição...');
                                setTimeout(() => {
                                    // Usar os agendamentos já atualizados em memória
                                    mostrarAgendamentosDiaComDados(dataSalva, agendamentosDiaSelecionado);
                                }, 500); // Pequeno delay para garantir que tudo foi atualizado
                            }
                        });
                        

                    });
                } else {
                    alert('Erro: ' + data.erro);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao salvar agendamento');
            });
        });

        function bloquearData(data) {
            if (confirm(`Deseja bloquear a agenda do dia ${data.split('-').reverse().join('/')}?`)) {
                const motivo = prompt('Motivo do bloqueio (opcional):');
                const formData = new FormData();
                formData.append('acao', 'bloquear_agenda');
                formData.append('data', data);
                formData.append('motivo_bloqueio', motivo || '');
                formData.append('csrf_token', '<?= gerarTokenCsrf() ?>');
                
                fetch('ajax_agenda.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(response => {
                    if (response.sucesso) {
                        datasBloqueadas.push(data);
                        // Adicionar motivo ao array de motivos (se houver)
                        if (response.motivo) {
                            motivosBloqueio[data] = response.motivo;
                        }
                        // Calcular o mês atual baseado na data bloqueada
                        const [ano, mes] = data.split('-');
                        const mesAtual = ano + '-' + mes;
                        gerarCalendario(mesAtual);
                    } else {
                        alert('Erro: ' + response.erro);
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao bloquear agenda');
                });
            }
        }
        
        function desbloquearData(data, event = null) {
            if (event) {
                event.stopPropagation();
            }
            
            if (confirm(`Deseja desbloquear a agenda do dia ${data.split('-').reverse().join('/')}?`)) {
                const formData = new FormData();
                formData.append('acao', 'desbloquear_agenda');
                formData.append('data', data);
                formData.append('csrf_token', '<?= gerarTokenCsrf() ?>');
                
                fetch('ajax_agenda.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(response => {
                    if (response.sucesso) {
                        datasBloqueadas = datasBloqueadas.filter(d => d !== data);
                        // Remover motivo do array de motivos
                        delete motivosBloqueio[data];
                        // Calcular o mês atual baseado na data desbloqueada
                        const [ano, mes] = data.split('-');
                        const mesAtual = ano + '-' + mes;
                        gerarCalendario(mesAtual);
                    } else {
                        alert('Erro: ' + response.erro);
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao desbloquear agenda');
                });
            }
        }

        function toggleBloqueioData(data, event = null) {
            if (event) {
                event.stopPropagation();
            }
            
            // Verificar se o dia está bloqueado
            const ehBloqueado = datasBloqueadas.includes(data);
            
            if (ehBloqueado) {
                // Se está bloqueado, desbloquear
                desbloquearData(data, event);
            } else {
                // Se não está bloqueado, bloquear
                bloquearData(data);
            }
        }

        function mostrarAgendamentosDia(data, event = null) {
            if (event) event.stopPropagation();
            fecharAgendamentosDia();
            carregarAgendamentosDia(data, function() {
                mostrarAgendamentosDiaComDados(data, agendamentosDiaSelecionado);
            });
        }

        function mostrarAgendamentosDiaComDados(data, agendamentosDia) {
            console.log('=== MOSTRANDO AGENDAMENTOS DO DIA (COM DADOS) ===');
            console.log('Data selecionada:', data);
            console.log('Agendamentos fornecidos:', agendamentosDia);
            
            // Formatar a data para exibição
            const dataFormatada = data.split('-').reverse().join('/');
            const tituloElement = document.getElementById('titulo-agendamentos-dia');
            if (tituloElement) {
                tituloElement.textContent = `Agendamentos do dia ${dataFormatada}`;
            }
            
            // Usar os dados fornecidos diretamente
            if (agendamentosDia && agendamentosDia.length >= 0) {
                console.log('Agendamentos encontrados para o dia', data, ':', agendamentosDia.length);
                console.log('Dados dos agendamentos:', agendamentosDia);
                
                // Debug adicional para verificar a estrutura dos dados
                if (agendamentosDia && agendamentosDia.length > 0) {
                    console.log('Primeiro agendamento:', agendamentosDia[0]);
                    console.log('Campos disponíveis:', Object.keys(agendamentosDia[0]));
                }
                
                // Atualizar a lista HTML com abas por horário
                const listaContainer = document.getElementById('agendamentos-dia-list');
                const container = document.getElementById('agendamentos-dia-selecionado');
                
                if (listaContainer && container) {
                    let html = '';
                    
                    if (agendamentosDia.length === 0) {
                        html = '<div class="no-appointments">Nenhum agendamento para este dia.</div>';
                    } else {
                        // Organizar agendamentos por horário
                        const agendamentosPorHorario = {};
                        const horariosDisponiveis = [
                            '07:30', '08:00', '09:00', '10:00', '11:00', 
                            '13:00', '14:00'
                        ];
                        
                        console.log('=== ORGANIZANDO AGENDAMENTOS ===');
                        console.log('Horários disponíveis:', horariosDisponiveis);
                        console.log('Agendamentos recebidos:', agendamentosDia);
                        
                        // Inicializar todos os horários
                        horariosDisponiveis.forEach(horario => {
                            agendamentosPorHorario[horario] = [];
                        });
                        
                        // Agrupar agendamentos por horário
                        agendamentosDia.forEach(agendamento => {
                            console.log('Processando agendamento:', agendamento);
                            console.log('Horário do agendamento:', agendamento.horario);
                            
                            // Normalizar o horário removendo os segundos
                            const horarioNormalizado = agendamento.horario.substring(0, 5);
                            console.log('Horário normalizado:', horarioNormalizado);
                            console.log('Horário existe no array?', horariosDisponiveis.includes(horarioNormalizado));
                            
                            if (agendamentosPorHorario[horarioNormalizado]) {
                                agendamentosPorHorario[horarioNormalizado].push(agendamento);
                                console.log('Agendamento adicionado ao horário:', horarioNormalizado);
                            } else {
                                console.log('Horário não encontrado:', horarioNormalizado);
                            }
                        });
                        
                        console.log('Agendamentos organizados por horário:', agendamentosPorHorario);
                        
                        // Criar abas
                        html += '<div class="horarios-tabs">';
                        let primeiraAba = true;
                        
                        horariosDisponiveis.forEach(horario => {
                            const agendamentosHorario = agendamentosPorHorario[horario];
                            const temAgendamentos = agendamentosHorario.length > 0;
                            let limite = 21; // padrão para horários de 1 hora
                            if (horario === '07:30') {
                                limite = 11; // limite para horário de 30 minutos
                            } else if (horario === '11:00') {
                                limite = 9; // limite reduzido para horário de 11:00
                            }
                            const ocupacao = agendamentosHorario.length;
                            
                            html += `
                                <button class="tab-button ${primeiraAba ? 'active' : ''}" 
                                        onclick="trocarAba('${horario}')" 
                                        data-horario="${horario}">
                                    <span class="horario-tab">${horario}</span>
                                    <span class="contador-tab">${ocupacao}/${limite}</span>
                                    ${temAgendamentos ? '<span class="indicador-agendamentos">●</span>' : ''}
                                </button>
                            `;
                            primeiraAba = false;
                        });
                        
                        html += '</div>';
                        
                        // Criar conteúdo das abas
                        html += '<div class="tab-content">';
                        primeiraAba = true;
                        
                        horariosDisponiveis.forEach(horario => {
                            const agendamentosHorario = agendamentosPorHorario[horario];
                            let limite = 21; // padrão para horários de 1 hora
                            if (horario === '07:30') {
                                limite = 11; // limite para horário de 30 minutos
                            } else if (horario === '11:00') {
                                limite = 9; // limite reduzido para horário de 11:00
                            }
                            const ocupacao = agendamentosHorario.length;
                            
                            html += `
                                <div class="tab-pane ${primeiraAba ? 'active' : ''}" id="tab-${horario}">
                                    <div class="horario-header">
                                        <h4><i class="fas fa-clock"></i> Horário: ${horario}</h4>
                                        <div class="horario-info">
                                            <span class="ocupacao ${ocupacao >= limite ? 'lotado' : ''}">
                                                ${ocupacao}/${limite} pacientes
                                            </span>
                                            ${ocupacao >= limite ? '<span class="status-lotado">LOTADO</span>' : ''}
                                        </div>
                                    </div>
                                    <div class="agendamentos-horario">
                            `;
                            
                            if (agendamentosHorario.length === 0) {
                                html += '<div class="no-appointments-horario">Nenhum agendamento para este horário.</div>';
                            } else {
                                agendamentosHorario.forEach(agendamento => {
                                    const telefone = agendamento.telefone + (agendamento.telefone2 ? ' / ' + agendamento.telefone2 : '');
                                    const encaixeLabel = (agendamento.encaixe == 1 || agendamento.encaixe == '1') ? 
                                        '<span class="encaixe-label" style="color:#d35400;font-weight:bold;">[Encaixe]</span>' : '';
                                    
                                    // Normalizar o horário para exibição
                                    const horarioExibicao = agendamento.horario.substring(0, 5);
                                    
                                    html += `
                                        <div class="agendamento-item" data-id="${agendamento.id}">
                                            <div class="agendamento-info">
                                                <div class="agendamento-paciente">
                                                    <i class="fas fa-user"></i>
                                                    ${agendamento.paciente_nome}
                                                </div>
                                                <div class="agendamento-telefone">
                                                    <i class="fas fa-phone"></i>
                                                    ${telefone}
                                                </div>
                                                ${agendamento.observacoes ? `
                                                    <div class="agendamento-obs">
                                                        <i class="fas fa-comment"></i>
                                                        ${agendamento.observacoes}
                                                    </div>
                                                ` : ''}
                                                ${encaixeLabel}
                                            </div>
                                            <div class="agendamento-actions">
                                                <button type="button" class="btn-secondary" onclick="editarAgendamento(${agendamento.id})">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn-danger" onclick="cancelarAgendamento(${agendamento.id})">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                    `;
                                });
                            }
                            
                            html += `
                                    </div>
                                </div>
                            `;
                            primeiraAba = false;
                        });
                        
                        html += '</div>';
                    }
                    
                    console.log('=== HTML GERADO ===');
                    console.log('HTML final:', html);
                    console.log('Container encontrado:', container);
                    console.log('Lista container encontrado:', listaContainer);
                    
                    listaContainer.innerHTML = html;
                    container.style.display = 'block';
                    
                    console.log('HTML inserido no container');
                    
                    // Scroll suave para a seção de agendamentos
                    container.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            } else {
                console.error('Dados de agendamentos inválidos:', agendamentosDia);
            }
        }
    </script>
</body>
</html> 