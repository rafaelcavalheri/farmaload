<?php
require __DIR__ . '/config.php';
verificarAutenticacao(['admin']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido']);
    exit;
}

$acao = $_POST['acao'] ?? '';

switch ($acao) {
    case 'buscar_agendamentos':
        buscarAgendamentos();
        break;
    case 'buscar_agendamentos_dia':
        buscarAgendamentosDia();
        break;
    case 'salvar_agendamento':
        salvarAgendamento();
        break;
    case 'editar_agendamento':
        editarAgendamento();
        break;
    case 'cancelar_agendamento':
        cancelarAgendamento();
        break;
    case 'verificar_disponibilidade':
        verificarDisponibilidade();
        break;
    case 'bloquear_agenda':
        bloquearAgenda();
        break;
    case 'desbloquear_agenda':
        desbloquearAgenda();
        break;
    default:
        http_response_code(400);
        echo json_encode(['erro' => 'Ação não reconhecida']);
        break;
}

function buscarAgendamentos() {
    global $pdo;
    
    $mes = $_POST['mes'] ?? date('Y-m');
    error_log("Buscando agendamentos para mês: $mes");
    
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, p.nome as paciente_nome, p.telefone, p.telefone2
            FROM agenda a 
            JOIN pacientes p ON a.paciente_id = p.id 
            WHERE DATE_FORMAT(a.data, '%Y-%m') = ? 
            AND a.status != 'cancelado'
            ORDER BY a.data, a.horario
        ");
        $stmt->execute([$mes]);
        $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Agendamentos encontrados: " . count($agendamentos));
        echo json_encode(['sucesso' => true, 'agendamentos' => $agendamentos]);
    } catch (Exception $e) {
        error_log("Erro ao buscar agendamentos: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['erro' => 'Erro ao buscar agendamentos: ' . $e->getMessage()]);
    }
}

function buscarAgendamentosDia() {
    global $pdo;
    
    $data = $_POST['data'] ?? date('Y-m-d');
    error_log("=== BUSCANDO AGENDAMENTOS DO DIA ===");
    error_log("Data recebida via POST: " . ($_POST['data'] ?? 'NÃO FORNECIDA'));
    error_log("Data final usada na busca: $data");
    error_log("Data atual PHP: " . date('Y-m-d'));
    
    try {
        // Primeiro, vamos verificar se há agendamentos na tabela
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM agenda");
        $stmt->execute();
        $totalAgendamentos = $stmt->fetch()['total'];
        error_log("Total de agendamentos na tabela: $totalAgendamentos");
        
        // Verificar todos os agendamentos para debug
        $stmt = $pdo->prepare("SELECT * FROM agenda ORDER BY data DESC LIMIT 10");
        $stmt->execute();
        $todosAgendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Últimos 10 agendamentos: " . json_encode($todosAgendamentos));
        
        // Agora buscar os agendamentos do dia específico
        $stmt = $pdo->prepare("
            SELECT a.*, p.nome as paciente_nome, p.telefone, p.telefone2
            FROM agenda a 
            JOIN pacientes p ON a.paciente_id = p.id 
            WHERE a.data = ? 
            AND a.status != 'cancelado'
            ORDER BY a.horario
        ");
        $stmt->execute([$data]);
        $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Se não encontrou agendamentos, vamos verificar se há agendamentos sem JOIN
        if (empty($agendamentos)) {
            error_log("Nenhum agendamento encontrado com JOIN. Verificando sem JOIN...");
            $stmt = $pdo->prepare("
                SELECT * FROM agenda 
                WHERE data = ? 
                AND status != 'cancelado'
                ORDER BY horario
            ");
            $stmt->execute([$data]);
            $agendamentosSemJoin = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Agendamentos sem JOIN: " . json_encode($agendamentosSemJoin));
        }
        
        error_log("Agendamentos encontrados para o dia: " . count($agendamentos));
        error_log("Dados dos agendamentos: " . json_encode($agendamentos));
        
        // Verificar se os agendamentos têm a data correta
        foreach ($agendamentos as $index => $agendamento) {
            error_log("Agendamento " . ($index + 1) . ": data=" . $agendamento['data'] . ", horario=" . $agendamento['horario']);
        }
        
        // Verificar se o dia está bloqueado
        $stmt = $pdo->prepare("SELECT * FROM agenda_bloqueada WHERE data = ?");
        $stmt->execute([$data]);
        $bloqueado = $stmt->fetch() ? true : false;
        
        echo json_encode(['sucesso' => true, 'agendamentos' => $agendamentos, 'bloqueado' => $bloqueado]);
    } catch (Exception $e) {
        error_log("Erro ao buscar agendamentos do dia: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['erro' => 'Erro ao buscar agendamentos do dia: ' . $e->getMessage()]);
    }
}

function salvarAgendamento() {
    global $pdo;
    
    if (!isset($_POST['csrf_token']) || !validarTokenCsrf($_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['erro' => 'Token CSRF inválido']);
        return;
    }
    
    $data = $_POST['data'] ?? '';
    $horario = $_POST['horario'] ?? '';
    $paciente_id = $_POST['paciente_id'] ?? '';
    $observacoes = trim($_POST['observacoes'] ?? '');
    $encaixe = isset($_POST['encaixe']) ? (int)$_POST['encaixe'] : 0;
    
    error_log("Salvando agendamento: data=$data, horario=$horario, paciente_id=$paciente_id, encaixe=$encaixe");
    
    // Validações
    if (empty($data) || empty($horario) || empty($paciente_id)) {
        http_response_code(400);
        echo json_encode(['erro' => 'Todos os campos obrigatórios devem ser preenchidos']);
        return;
    }
    
    // Verificar se a data não é passada
    $dataAtual = date('Y-m-d');
    if ($data < $dataAtual) {
        error_log("Tentativa de agendar para data passada: $data");
        http_response_code(400);
        echo json_encode(['erro' => 'Não é possível agendar para datas passadas']);
        return;
    }
    
    // Verificar se o paciente já tem agendamento para este horário
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total FROM agenda 
            WHERE data = ? AND horario = ? AND paciente_id = ? AND status != 'cancelado'
        ");
        $stmt->execute([$data, $horario, $paciente_id]);
        $result = $stmt->fetch();
        
        if ($result['total'] > 0) {
            error_log("Paciente já possui agendamento para este horário: data=$data, horario=$horario, paciente_id=$paciente_id");
            http_response_code(400);
            echo json_encode(['erro' => 'Este paciente já possui agendamento para este horário']);
            return;
        }
    } catch (Exception $e) {
        error_log("Erro ao verificar duplicidade: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['erro' => 'Erro ao verificar duplicidade: ' . $e->getMessage()]);
        return;
    }
    
    // Verificar disponibilidade do horário
    try {
        // Determinar o limite baseado no horário (21 pacientes por hora, 11 por 30 minutos)
        $limite = 21; // padrão para horários de 1 hora
        
        // Verificar se é o horário de 7:30 (único horário de 30 minutos)
        if ($horario === '07:30') {
            $limite = 11; // limite para horário de 30 minutos
        }
        
        // Contar agendamentos normais e encaixes
        $stmt = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN encaixe = 0 THEN 1 ELSE 0 END) as normais,
                SUM(CASE WHEN encaixe = 1 THEN 1 ELSE 0 END) as encaixes
            FROM agenda 
            WHERE data = ? AND horario = ? AND status != 'cancelado'
        ");
        $stmt->execute([$data, $horario]);
        $result = $stmt->fetch();
        $normais = (int)($result['normais'] ?? 0);
        $encaixes = (int)($result['encaixes'] ?? 0);
        
        error_log("Verificando disponibilidade: normais=$normais/$limite, encaixes=$encaixes/3");
        
        // Se é encaixe, verificar se há vagas de encaixe
        if ($encaixe == 1) {
            if ($encaixes >= 3) {
                error_log("Limite de encaixes atingido: $encaixes/3");
                http_response_code(400);
                echo json_encode(['erro' => 'Limite de encaixes extras atingido (3 encaixes)']);
                return;
            }
        } else {
            // Se é normal, verificar se há vagas normais
            if ($normais >= $limite) {
                error_log("Limite normal atingido: $normais/$limite");
                http_response_code(400);
                echo json_encode(['erro' => "Este horário já possui o máximo de pacientes permitidos ({$limite} pacientes)"]);
                return;
            }
        }
    } catch (Exception $e) {
        error_log("Erro ao verificar disponibilidade: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['erro' => 'Erro ao verificar disponibilidade: ' . $e->getMessage()]);
        return;
    }
    
    // Verificar se o dia está bloqueado
    $stmt = $pdo->prepare("SELECT * FROM agenda_bloqueada WHERE data = ?");
    $stmt->execute([$data]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['erro' => 'A agenda deste dia está bloqueada para novos agendamentos.']);
        return;
    }
    // Se for solicitação de bloqueio, inserir na tabela
    if (!empty($_POST['bloquear_agenda']) && $_POST['bloquear_agenda'] == '1') {
        $motivo = trim($_POST['motivo_bloqueio'] ?? '');
        $usuario_id = $_SESSION['usuario']['id'] ?? null;
        $stmt = $pdo->prepare("INSERT IGNORE INTO agenda_bloqueada (data, motivo, usuario_id) VALUES (?, ?, ?)");
        $stmt->execute([$data, $motivo, $usuario_id]);
        echo json_encode(['sucesso' => true, 'mensagem' => 'Agenda bloqueada para o dia selecionado.']);
        return;
    }
    
    // Salvar agendamento
    try {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['usuario']['id'])) {
            http_response_code(401);
            echo json_encode(['erro' => 'Usuário não identificado. Faça login novamente.']);
            return;
        }
        
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
        
        error_log("Agendamento salvo com sucesso. Encaixe: $encaixe");
        echo json_encode(['sucesso' => true, 'mensagem' => '']);
    } catch (Exception $e) {
        error_log("Erro ao salvar agendamento: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['erro' => 'Erro ao salvar agendamento: ' . $e->getMessage()]);
    }
}

function editarAgendamento() {
    global $pdo;
    
    if (!isset($_POST['csrf_token']) || !validarTokenCsrf($_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['erro' => 'Token CSRF inválido']);
        return;
    }
    
    $id = $_POST['id'] ?? '';
    $data = $_POST['data'] ?? '';
    $horario = $_POST['horario'] ?? '';
    $paciente_id = $_POST['paciente_id'] ?? '';
    $observacoes = trim($_POST['observacoes'] ?? '');
    $status = $_POST['status'] ?? 'agendado';
    $encaixe = isset($_POST['encaixe']) ? (int)$_POST['encaixe'] : 0;
    
    error_log("Editando agendamento: id=$id, data=$data, horario=$horario, paciente_id=$paciente_id, encaixe=$encaixe");
    
    // Validações
    if (empty($id) || empty($data) || empty($horario) || empty($paciente_id)) {
        http_response_code(400);
        echo json_encode(['erro' => 'Todos os campos obrigatórios devem ser preenchidos']);
        return;
    }
    
    // Verificar se a data não é passada
    $dataAtual = date('Y-m-d');
    error_log("Verificando data: agendamento=$data, atual=$dataAtual");
    if ($data < $dataAtual) {
        error_log("Tentativa de agendar para data passada: $data");
        http_response_code(400);
        echo json_encode(['erro' => 'Não é possível agendar para datas passadas']);
        return;
    }
    
    // Verificar se o horário está disponível (permitindo múltiplos pacientes por hora)
    try {
        // Determinar o limite baseado no horário (21 pacientes por hora, 11 por 30 minutos)
        $limite = 21; // padrão para horários de 1 hora
        
        // Verificar se é o horário de 7:30 (único horário de 30 minutos)
        if ($horario === '07:30') {
            $limite = 11; // limite para horário de 30 minutos
        }
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total FROM agenda 
            WHERE data = ? AND horario = ? AND status != 'cancelado' AND id != ?
        ");
        $stmt->execute([$data, $horario, $id]);
        $result = $stmt->fetch();
        
        if ($result['total'] >= $limite) {
            error_log("Horário lotado para edição: data=$data, horario=$horario, id=$id, total={$result['total']}, limite=$limite");
            http_response_code(400);
            echo json_encode(['erro' => "Este horário já possui o máximo de pacientes permitidos ({$limite} pacientes)"]);
            return;
        }
    } catch (Exception $e) {
        error_log("Erro ao verificar disponibilidade para edição: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['erro' => 'Erro ao verificar disponibilidade: ' . $e->getMessage()]);
        return;
    }
    
    // Atualizar agendamento
    try {
        $stmt = $pdo->prepare("
            UPDATE agenda 
            SET data = ?, horario = ?, paciente_id = ?, observacoes = ?, status = ?, encaixe = ?
            WHERE id = ?
        ");
        $stmt->execute([$data, $horario, $paciente_id, $observacoes, $status, $encaixe, $id]);
        
        error_log("Agendamento atualizado com sucesso. ID: $id");
        echo json_encode(['sucesso' => true, 'mensagem' => '']);
    } catch (Exception $e) {
        error_log("Erro ao atualizar agendamento: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['erro' => 'Erro ao atualizar agendamento: ' . $e->getMessage()]);
    }
}

function cancelarAgendamento() {
    global $pdo;
    
    if (!isset($_POST['csrf_token']) || !validarTokenCsrf($_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['erro' => 'Token CSRF inválido']);
        return;
    }
    
    $id = $_POST['id'] ?? '';
    error_log("Cancelando agendamento ID: $id");
    
    if (empty($id)) {
        http_response_code(400);
        echo json_encode(['erro' => 'ID do agendamento é obrigatório']);
        return;
    }
    
    try {
        // Primeiro, verificar se o agendamento existe e obter seus dados
        $stmt = $pdo->prepare("SELECT id, data, horario, status FROM agenda WHERE id = ?");
        $stmt->execute([$id]);
        $agendamento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$agendamento) {
            error_log("Agendamento não encontrado. ID: $id");
            http_response_code(404);
            echo json_encode(['erro' => 'Agendamento não encontrado']);
            return;
        }
        
        error_log("Agendamento encontrado: ID=$id, data={$agendamento['data']}, horario={$agendamento['horario']}, status={$agendamento['status']}");
        
        // Se já está cancelado, não fazer nada
        if ($agendamento['status'] === 'cancelado') {
            error_log("Agendamento já está cancelado. ID: $id");
            echo json_encode(['sucesso' => true, 'mensagem' => 'Agendamento já estava cancelado']);
            return;
        }
        
        // Atualizar o status para cancelado
        $stmt = $pdo->prepare("UPDATE agenda SET status = 'cancelado' WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            error_log("Agendamento cancelado com sucesso. ID: $id");
            echo json_encode(['sucesso' => true, 'mensagem' => '', 'data_agendamento' => $agendamento['data']]);
        } else {
            error_log("Nenhuma linha foi atualizada. ID: $id");
            http_response_code(500);
            echo json_encode(['erro' => 'Erro ao cancelar agendamento']);
        }
    } catch (Exception $e) {
        error_log("Erro ao cancelar agendamento: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['erro' => 'Erro ao cancelar agendamento: ' . $e->getMessage()]);
    }
}

function verificarDisponibilidade() {
    global $pdo;
    
    $data = $_POST['data'] ?? '';
    $horario = $_POST['horario'] ?? '';
    
    if (empty($data) || empty($horario)) {
        http_response_code(400);
        echo json_encode(['erro' => 'Data e horário são obrigatórios']);
        return;
    }
    
    try {
        // Verificar se o dia está bloqueado
        $stmt = $pdo->prepare("SELECT * FROM agenda_bloqueada WHERE data = ?");
        $stmt->execute([$data]);
        if ($stmt->fetch()) {
            echo json_encode(['disponivel' => false, 'motivo' => 'Dia bloqueado']);
            return;
        }
        
        // Verificar disponibilidade do horário
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM agenda 
            WHERE data = ? AND horario = ? AND status != 'cancelado'
        ");
        $stmt->execute([$data, $horario]);
        $total = $stmt->fetchColumn();
        
        // Determinar limite baseado no horário
        $limite = 21;
        if ($horario === '07:30') {
            $limite = 11;
        } else if ($horario === '11:00') {
            $limite = 9;
        }
        
        $disponivel = $total < $limite;
        echo json_encode([
            'disponivel' => $disponivel,
            'ocupacao' => $total,
            'limite' => $limite
        ]);
    } catch (Exception $e) {
        error_log("Erro ao verificar disponibilidade: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['erro' => 'Erro ao verificar disponibilidade']);
    }
}

function bloquearAgenda() {
    global $pdo;
    
    if (!isset($_POST['csrf_token']) || !validarTokenCsrf($_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['erro' => 'Token CSRF inválido']);
        return;
    }
    
    $data = $_POST['data'] ?? '';
    $motivo = trim($_POST['motivo_bloqueio'] ?? '');
    
    if (empty($data)) {
        http_response_code(400);
        echo json_encode(['erro' => 'Data é obrigatória']);
        return;
    }
    
    // Verificar se a data não é passada
    $dataAtual = date('Y-m-d');
    if ($data < $dataAtual) {
        http_response_code(400);
        echo json_encode(['erro' => 'Não é possível bloquear datas passadas']);
        return;
    }
    
    try {
        // Verificar se já está bloqueada
        $stmt = $pdo->prepare("SELECT * FROM agenda_bloqueada WHERE data = ?");
        $stmt->execute([$data]);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['erro' => 'Esta data já está bloqueada']);
            return;
        }
        
        // Inserir bloqueio
        $stmt = $pdo->prepare("INSERT INTO agenda_bloqueada (data, motivo, usuario_id) VALUES (?, ?, ?)");
        $stmt->execute([$data, $motivo, $_SESSION['usuario']['id'] ?? null]);
        
        echo json_encode(['sucesso' => true, 'mensagem' => '', 'motivo' => $motivo]);
    } catch (Exception $e) {
        error_log("Erro ao bloquear agenda: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['erro' => 'Erro ao bloquear agenda: ' . $e->getMessage()]);
    }
}

function desbloquearAgenda() {
    global $pdo;
    
    if (!isset($_POST['csrf_token']) || !validarTokenCsrf($_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['erro' => 'Token CSRF inválido']);
        return;
    }
    
    $data = $_POST['data'] ?? '';
    
    if (empty($data)) {
        http_response_code(400);
        echo json_encode(['erro' => 'Data é obrigatória']);
        return;
    }
    
    try {
        // Verificar se está bloqueada
        $stmt = $pdo->prepare("SELECT * FROM agenda_bloqueada WHERE data = ?");
        $stmt->execute([$data]);
        if (!$stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['erro' => 'Esta data não está bloqueada']);
            return;
        }
        
        // Remover bloqueio
        $stmt = $pdo->prepare("DELETE FROM agenda_bloqueada WHERE data = ?");
        $stmt->execute([$data]);
        
        echo json_encode(['sucesso' => true, 'mensagem' => '']);
    } catch (Exception $e) {
        error_log("Erro ao desbloquear agenda: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['erro' => 'Erro ao desbloquear agenda: ' . $e->getMessage()]);
    }
}
?> 