<?php
require __DIR__ . '/config.php';
verificarAutenticacao(['admin', 'operador']);

$medicamentos_disponiveis = $pdo->query("SELECT id, nome FROM medicamentos ORDER BY nome")->fetchAll();
$medicos_disponiveis = $pdo->query("
    SELECT id, nome, CONCAT(crm_numero, ' ', crm_estado) as identificacao, 'medico' as tipo 
    FROM medicos 
    WHERE ativo = 1 
    UNION ALL 
    SELECT id + 10000, nome, cnes as identificacao, 'instituicao' as tipo 
    FROM instituicoes 
    WHERE ativo = 1 
    ORDER BY nome
")->fetchAll();

$erros = [];
$valores = [
    'nome' => '',
    'codigo_paciente' => '',
    'cpf' => '',
    'sim' => '',
    'nascimento' => '',
    'telefone' => '',
    'telefone2' => '',
    'observacao' => '',
    'medicamentos' => [],
    'autorizados' => [
        ['nome' => '', 'cpf' => ''],
        ['nome' => '', 'cpf' => ''],
        ['nome' => '', 'cpf' => '']
    ]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validarTokenCsrf($_POST['csrf_token'])) {
        die("Token CSRF inválido!");
    }

    // Sanitização e normalização
    $valores = [
        'nome' => trim($_POST['nome'] ?? ''),
        'codigo_paciente' => trim($_POST['codigo_paciente'] ?? ''),
        'cpf' => preg_replace('/\D/', '', $_POST['cpf'] ?? ''),
        'sim' => trim($_POST['sim'] ?? ''),
        'nascimento' => trim($_POST['nascimento'] ?? ''),
        'telefone' => preg_replace('/\D/', '', $_POST['telefone'] ?? ''),
        'telefone2' => preg_replace('/\D/', '', $_POST['telefone2'] ?? ''),
        'observacao' => trim($_POST['observacao'] ?? ''),
        'medicamentos' => [],
        'autorizados' => []
    ];

    // Processa pessoas autorizadas
    $nomes_autorizados = $_POST['autorizado_nome'] ?? [];
    $cpfs_autorizados = $_POST['autorizado_cpf'] ?? [];
    
    for ($i = 0; $i < 3; $i++) {
        $nome_auth = trim($nomes_autorizados[$i] ?? '');
        $cpf_auth = preg_replace('/\D/', '', $cpfs_autorizados[$i] ?? '');
        
        if (!empty($nome_auth) || !empty($cpf_auth)) {
            if (empty($nome_auth)) {
                $erros["autorizado_nome_$i"] = 'Nome da pessoa autorizada é obrigatório quando CPF é fornecido.';
            }
            if (empty($cpf_auth) || strlen($cpf_auth) !== 11) {
                $erros["autorizado_cpf_$i"] = 'CPF inválido para pessoa autorizada.';
            }
            
            $valores['autorizados'][] = [
                'nome' => $nome_auth,
                'cpf' => $cpf_auth
            ];
        }
    }

    // Validações básicas
    if ($valores['nome'] === '') {
        $erros['nome'] = 'Nome é obrigatório.';
    }
    if (empty($valores['codigo_paciente'])) {
        $erros['codigo_paciente'] = 'Código do paciente é obrigatório.';
    }
    if (strlen($valores['cpf']) !== 11) {
        $erros['cpf'] = 'CPF inválido.';
    }
    if ($valores['nascimento'] === '') {
        $erros['nascimento'] = 'Data de nascimento é obrigatória.';
    }
    if (strlen($valores['telefone']) < 10) {
        $erros['telefone'] = 'Telefone inválido.';
    }
    if (!empty($valores['telefone2']) && strlen($valores['telefone2']) < 10) {
        $erros['telefone2'] = 'Telefone 2 inválido.';
    }

    // Validações dos medicamentos
    if (isset($_POST['medicamento_id']) && is_array($_POST['medicamento_id'])) {
        foreach ($_POST['medicamento_id'] as $i => $medId) {
            if (empty($medId)) {
                $erros["medicamento_$i"] = 'Medicamento obrigatório.';
                continue;
            }
            $qtd = $_POST['quantidade'][$i] ?? 0;
            $qtd_solicitada = $_POST['quantidade_solicitada'][$i] ?? $qtd;
            
            if (!is_numeric($qtd) || $qtd < 1) {
                $erros["quantidade_$i"] = 'Quantidade inválida.';
            }
            if (!is_numeric($qtd_solicitada) || $qtd_solicitada < 1) {
                $erros["quantidade_solicitada_$i"] = 'Quantidade solicitada inválida.';
            }

            // Determinar tipo de prescritor e IDs
            $medico_id = !empty($_POST['medico_id'][$i]) ? $_POST['medico_id'][$i] : null;
            $tipo_prescritor = null;
            $instituicao_id = null;
            
            if (!empty($medico_id)) {
                if ($medico_id >= 10000) {
                    // É uma instituição (ID com offset)
                    $instituicao_id_real = $medico_id - 10000;
                    $stmt = $pdo->prepare("SELECT id FROM instituicoes WHERE id = ?");
                    $stmt->execute([$instituicao_id_real]);
                    if ($stmt->fetch()) {
                        $tipo_prescritor = 'instituicao';
                        $instituicao_id = $instituicao_id_real;
                        $medico_id = null; // Limpar medico_id se for instituição
                    }
                } else {
                    // É um médico (ID normal)
                    $stmt = $pdo->prepare("SELECT id FROM medicos WHERE id = ?");
                    $stmt->execute([$medico_id]);
                    if ($stmt->fetch()) {
                        $tipo_prescritor = 'medico';
                    }
                }
            }

            $valores['medicamentos'][] = [
                'medicamento_id' => $medId,
                'quantidade' => $qtd,
                'quantidade_solicitada' => $qtd_solicitada,
                'cid' => $_POST['cid'][$i] ?? '',
                'medico_id' => $medico_id,
                'tipo_prescritor' => $tipo_prescritor,
                'instituicao_id' => $instituicao_id,
                'renovacao' => $_POST['renovacao'][$i] ?? null,
                'renovado' => isset($_POST['renovado'][$i]) && $_POST['renovado'][$i] == '1' ? 1 : 0,
                'observacoes' => $_POST['observacoes'][$i] ?? ''
            ];
        }
    }

    if (empty($erros)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO pacientes (nome, codigo_paciente, cpf, sim, nascimento, telefone, telefone2, observacao) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $valores['nome'],
                $valores['codigo_paciente'],
                $valores['cpf'],
                $valores['sim'],
                $valores['nascimento'],
                $valores['telefone'],
                $valores['telefone2'],
                $valores['observacao']
            ]);
            $pacienteId = $pdo->lastInsertId();

            // Insere pessoas autorizadas
            if (!empty($valores['autorizados'])) {
                $stmtAuth = $pdo->prepare("INSERT INTO pessoas_autorizadas (paciente_id, nome, cpf) VALUES (?, ?, ?)");
                foreach ($valores['autorizados'] as $autorizado) {
                    if (!empty($autorizado['nome']) && !empty($autorizado['cpf'])) {
                        $stmtAuth->execute([$pacienteId, $autorizado['nome'], $autorizado['cpf']]);
                    }
                }
            }

            if (!empty($valores['medicamentos'])) {
                $stmtMed = $pdo->prepare("INSERT INTO paciente_medicamentos (paciente_id, codigo_paciente, medicamento_id, nome_medicamento, quantidade, quantidade_solicitada, cid, observacoes, medico_id, tipo_prescritor, instituicao_id, renovacao, renovado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                foreach ($valores['medicamentos'] as $med) {
                    $nomeMed = '';
                    foreach ($medicamentos_disponiveis as $m) {
                        if ($m['id'] == $med['medicamento_id']) {
                            $nomeMed = $m['nome'];
                            break;
                        }
                    }

                    $stmtMed->execute([
                        $pacienteId,
                        $valores['codigo_paciente'],
                        $med['medicamento_id'],
                        $nomeMed,
                        $med['quantidade'],
                        $med['quantidade_solicitada'],
                        $med['cid'],
                        $med['observacoes'],
                        $med['medico_id'],
                        $med['tipo_prescritor'],
                        $med['instituicao_id'],
                        $med['renovacao'],
                        $med['renovado']
                    ]);
                }
            }

            $pdo->commit();

            header('Location: pacientes.php?sucesso=Paciente cadastrado com sucesso');
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $erros['geral'] = "Erro ao cadastrar: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Cadastrar Paciente</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
<link rel="stylesheet" href="/css/style.css" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

<?php include __DIR__.'/header.php'; ?>

<main class="container">
    <h2><i class="fas fa-user-plus"></i> Cadastrar Novo Paciente</h2>

    <?php if (!empty($erros['geral'])): ?>
        <div class="alert erro"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($erros['geral']) ?></div>
    <?php endif; ?>

    <form method="POST" action="" id="form-paciente">
        <input type="hidden" name="csrf_token" value="<?= gerarTokenCsrf() ?>" />

        <fieldset>
            <legend>Dados Pessoais</legend>

            <label for="nome">Nome Completo *</label>
            <input type="text" id="nome" name="nome" required value="<?= htmlspecialchars($valores['nome'] ?? '') ?>" />
            <?php if (isset($erros['nome'])): ?><small class="erro"><?= $erros['nome'] ?></small><?php endif; ?>

            <label for="codigo_paciente">Código do Paciente *</label>
            <input type="text" id="codigo_paciente" name="codigo_paciente" required value="<?= htmlspecialchars($valores['codigo_paciente'] ?? '') ?>" />
            <?php if (isset($erros['codigo_paciente'])): ?><small class="erro"><?= $erros['codigo_paciente'] ?></small><?php endif; ?>

            <label for="cpf">CPF *</label>
            <input type="text" id="cpf" name="cpf" maxlength="14" required value="<?= htmlspecialchars($valores['cpf'] ?? '') ?>" />
            <?php if (isset($erros['cpf'])): ?><small class="erro"><?= $erros['cpf'] ?></small><?php endif; ?>

            <label for="nascimento">Data de Nascimento *</label>
            <input type="date" id="nascimento" name="nascimento" max="<?= date('Y-m-d') ?>" required value="<?= htmlspecialchars($valores['nascimento'] ?? '') ?>" />
            <?php if (isset($erros['nascimento'])): ?><small class="erro"><?= $erros['nascimento'] ?></small><?php endif; ?>
        </fieldset>

        <fieldset>
            <legend>Contato e Observações</legend>

            <label for="telefone">Telefone *</label>
            <input type="tel" id="telefone" name="telefone" maxlength="15" required value="<?= htmlspecialchars($valores['telefone'] ?? '') ?>" />
            <?php if (isset($erros['telefone'])): ?><small class="erro"><?= $erros['telefone'] ?></small><?php endif; ?>

            <label for="telefone2">Telefone 2</label>
            <input type="tel" id="telefone2" name="telefone2" maxlength="15" value="<?= htmlspecialchars($valores['telefone2'] ?? '') ?>" />
            <?php if (isset($erros['telefone2'])): ?><small class="erro"><?= $erros['telefone2'] ?></small><?php endif; ?>

            <label for="sim">Número do SIM</label>
            <input type="text" id="sim" name="sim" value="<?= htmlspecialchars($valores['sim'] ?? '') ?>" />

            <label for="observacao">Observações</label>
            <textarea id="observacao" name="observacao"><?= htmlspecialchars($valores['observacao'] ?? '') ?></textarea>
        </fieldset>

        <fieldset>
            <legend>Pessoas Autorizadas</legend>
            <p class="field-info">Cadastre até 3 pessoas autorizadas a retirar medicamentos</p>
            
            <?php for ($i = 0; $i < 3; $i++): ?>
                <div class="autorizado-group">
                    <div class="autorizado-campos">
                        <div class="campo-grupo">
                            <label for="autorizado_nome_<?= $i ?>">Nome da Pessoa Autorizada <?= $i + 1 ?></label>
                            <input type="text" 
                                   id="autorizado_nome_<?= $i ?>" 
                                   name="autorizado_nome[]" 
                                   value="<?= htmlspecialchars($valores['autorizados'][$i]['nome'] ?? '') ?>" />
                            <?php if (isset($erros["autorizado_nome_$i"])): ?>
                                <small class="erro"><?= $erros["autorizado_nome_$i"] ?></small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="campo-grupo">
                            <label for="autorizado_cpf_<?= $i ?>">CPF da Pessoa Autorizada <?= $i + 1 ?></label>
                            <input type="text" 
                                   id="autorizado_cpf_<?= $i ?>" 
                                   name="autorizado_cpf[]" 
                                   class="cpf-mask"
                                   maxlength="14" 
                                   value="<?= htmlspecialchars($valores['autorizados'][$i]['cpf'] ?? '') ?>" />
                            <?php if (isset($erros["autorizado_cpf_$i"])): ?>
                                <small class="erro"><?= $erros["autorizado_cpf_$i"] ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endfor; ?>
        </fieldset>

        <fieldset>
            <legend>Medicamentos</legend>
            <div id="medicamentos-container">
                <!-- Aqui vamos carregar os medicamentos via JS -->
            </div>
            <button type="button" id="btn-add-medicamento"><i class="fas fa-plus"></i> Adicionar Medicamento</button>
        </fieldset>

        <button type="submit" class="btn-submit">Salvar Paciente</button>
    </form>
</main>

<script>
$(document).ready(function() {
    const medicamentosDisponiveis = <?= json_encode($medicamentos_disponiveis); ?>;
    const medicosDisponiveis = <?= json_encode($medicos_disponiveis); ?>;
    const container = $('#medicamentos-container');

    function formatarDataRenovacao(data) {
        if (!data) return '';
        
        // Se a data já está no formato DD/MM/YYYY
        if (data.match(/^\d{2}\/\d{2}\/\d{4}$/)) {
            return data;
        }
        
        // Se a data está no formato YYYY-MM-DD
        if (data.match(/^\d{4}-\d{2}-\d{2}$/)) {
            const [ano, mes, dia] = data.split('-');
            return `${dia}/${mes}/${ano}`;
        }

        // Se a data está no formato antigo MM/YYYY
        if (data.match(/^\d{2}\/\d{4}$/)) {
            const [mes, ano] = data.split('/');
            return `01/${mes}/${ano}`;
        }

        return data;
    }

    // Função para aplicar máscara de data DD/MM/YYYY
    function aplicarMascaraData(input) {
        input.on('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 8) {
                value = value.substr(0, 8);
            }
            if (value.length >= 2) {
                const dia = value.substr(0, 2);
                const resto = value.substr(2);
                if (parseInt(dia) > 31) {
                    value = '31' + resto;
                }
                if (value.length >= 4) {
                    const mes = value.substr(2, 2);
                    if (parseInt(mes) > 12) {
                        value = dia + '12' + value.substr(4);
                    }
                }
                if (value.length > 4) {
                    value = value.substr(0, 2) + '/' + value.substr(2, 2) + '/' + value.substr(4);
                } else if (value.length > 2) {
                    value = value.substr(0, 2) + '/' + value.substr(2);
                }
            }
            e.target.value = value.replace(/^(\d{2})(\d{2})(\d{4}).*/, '$1/$2/$3');
        });

        // Validação ao perder o foco
        input.on('blur', function(e) {
            const value = e.target.value;
            if (value && !value.match(/^\d{2}\/\d{2}\/\d{4}$/)) {
                e.target.value = '';
            } else if (value) {
                const [dia, mes, ano] = value.split('/');
                const data = new Date(ano, mes - 1, dia);
                if (data.getDate() != parseInt(dia) || 
                    (data.getMonth() + 1) != parseInt(mes) || 
                    data.getFullYear() != parseInt(ano) ||
                    parseInt(dia) < 1 || parseInt(dia) > 31 ||
                    parseInt(mes) < 1 || parseInt(mes) > 12) {
                    e.target.value = '';
                }
            }
        });
    }

    function adicionarMedicamento(data = {}) {
        const index = document.querySelectorAll('.medicamento-item').length;
        
        console.log('Adicionando medicamento:', data);
        console.log('Renovado:', data.renovado, 'Tipo:', typeof data.renovado);
        
        // Formatar a data de renovação se existir
        let dataRenovacao = '';
        if (data.renovacao) {
            // Tenta converter do formato DD/MM/YYYY
            const partes = data.renovacao.split('/');
            if (partes.length === 3) {
                // Formato DD/MM/YYYY
                const dataObj = new Date(partes[2], partes[1] - 1, partes[0]);
                if (!isNaN(dataObj.getTime())) {
                    dataRenovacao = dataObj.toISOString().split('T')[0];
                }
            } else {
                // Tenta converter do formato ISO
                const dataObj = new Date(data.renovacao);
                if (!isNaN(dataObj.getTime())) {
                    dataRenovacao = dataObj.toISOString().split('T')[0];
                }
            }
        }

        const optionsMedicamentos = medicamentosDisponiveis.map(med =>
            `<option value="${med.id}" ${med.id == data.medicamento_id ? 'selected' : ''}>
                ${med.nome}
            </option>`
        ).join('');

        // Determinar o valor correto para o select de médico/instituição
        let medicoSelected = '';
        if (data.tipo_prescritor === 'medico' && data.medico_id) {
            medicoSelected = data.medico_id;
        } else if (data.tipo_prescritor === 'instituicao' && data.instituicao_id) {
            medicoSelected = data.instituicao_id + 10000; // Aplicar offset para instituições
        }

        const optionsMedicos = medicosDisponiveis.map(med =>
            `<option value="${med.id}" ${med.id == medicoSelected ? 'selected' : ''}>
                ${med.nome} (${med.tipo === 'medico' ? 'CRM: ' : 'CNES: '}${med.identificacao})
            </option>`
        ).join('');

        const isChecked = data.renovado == 1 || data.renovado === true;

        const template = `
            <div class="medicamento-item">
                <div class="medicamento-header">
                    <h4>Medicamento ${index + 1}</h4>
                    <button type="button" class="btn-remove" onclick="removerMedicamento(this)">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="medicamento-body">
                    <div class="campo-form">
                        <label>Medicamento</label>
                        <select name="medicamento_id[]" required>
                            <option value="">Selecione um medicamento...</option>
                            ${optionsMedicamentos}
                        </select>
                    </div>
                    <div class="campo-form">
                        <label>Médico/Instituição</label>
                        <select name="medico_id[]" class="medico-select">
                            <option value="">Selecione...</option>
                            ${optionsMedicos}
                        </select>
                    </div>
                    <div class="campo-form">
                        <label>Quantidade</label>
                        <input type="number" name="quantidade[]" value="${data.quantidade || 1}" min="1" required>
                    </div>
                    <div class="campo-form">
                        <label>Quantidade Solicitada</label>
                        <input type="number" name="quantidade_solicitada[]" value="${data.quantidade_solicitada || data.quantidade || 1}" min="1" required>
                    </div>
                    <div class="campo-form">
                        <label>CID</label>
                        <input type="text" name="cid[]" value="${data.cid || ''}">
                    </div>
                    <div class="campo-form">
                        <label>Observações</label>
                        <textarea name="observacoes[]" rows="3" placeholder="Observações sobre este medicamento...">${data.observacoes || ''}</textarea>
                    </div>
                    <div class="renovacao-group">
                        <div class="campo-form">
                            <label>Data de Renovação</label>
                            <input type="date" name="renovacao[]" value="${dataRenovacao}">
                        </div>
                        <div class="campo-form checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="renovado[${data.medicamento_id}]" value="1" ${isChecked ? 'checked' : ''}>
                                <span>Renovado</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        `;

        $('#medicamentos-container').append(template);
    }

    // Se já tem medicamentos, carrega eles, senão adiciona um vazio
    if(<?= json_encode(count($valores['medicamentos'])) ?> > 0) {
        const meds = <?= json_encode($valores['medicamentos']) ?>;
        console.log('Medicamentos carregados:', meds);
        meds.forEach((med, index) => {
            adicionarMedicamento(med);
        });
    } else {
        adicionarMedicamento();
    }

    $('#btn-add-medicamento').click(() => adicionarMedicamento());

    // Função para remover medicamento
    window.removerMedicamento = function(button) {
        if (confirm('Tem certeza que deseja remover este medicamento?')) {
            $(button).closest('.medicamento-item').remove();
        }
    };

    // Formatação do CPF para pessoas autorizadas
    $('.cpf-mask').on('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 11) value = value.slice(0, 11);
        
        if (value.length >= 9) {
            value = value.replace(/^(\d{3})(\d{3})(\d{3})(\d{0,2}).*/, '$1.$2.$3-$4');
        } else if (value.length >= 6) {
            value = value.replace(/^(\d{3})(\d{3})(\d{0,3}).*/, '$1.$2.$3');
        } else if (value.length >= 3) {
            value = value.replace(/^(\d{3})(\d{0,3}).*/, '$1.$2');
        }
        
        e.target.value = value;
    });
});
</script>

<style>
    .autorizado-group {
        margin-bottom: 15px;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        background-color: #f9f9f9;
    }
    .autorizado-campos {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }
    .campo-grupo {
        display: flex;
        flex-direction: column;
    }
    .field-info {
        margin-bottom: 15px;
        color: #666;
        font-style: italic;
    }
    @media (max-width: 768px) {
        .autorizado-campos {
            grid-template-columns: 1fr;
        }
    }
    .validade-group {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    .renovado-check {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .renovado-check input[type="checkbox"] {
        width: auto;
    }
    .renovado-check label {
        margin: 0;
        font-weight: normal;
    }
    .medico-group {
        margin-bottom: 15px;
    }
    .medico-group select {
        width: 100%;
    }
    @media (max-width: 768px) {
        .autorizado-campos {
            grid-template-columns: 1fr;
        }
    }
    .renovacao-group {
        display: flex;
        gap: 20px;
        align-items: flex-end;
    }
    .checkbox-group {
        margin-bottom: 0;
    }
    .checkbox-label {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
    }
    .checkbox-label input[type="checkbox"] {
        width: auto;
        margin: 0;
    }
    .medicamento-body {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
    .medicamento-body .campo-form:last-child {
        grid-column: 1 / -1;
    }
    .medicamento-body .renovacao-group {
        grid-column: 1 / -1;
    }
    .medicamento-item {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        position: relative;
    }
    .medicamento-item:not(:last-child)::after {
        content: '';
        position: absolute;
        bottom: -10px;
        left: 0;
        right: 0;
        height: 1px;
        background: linear-gradient(to right, transparent, #ddd, transparent);
    }
    .medicamento-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }
    .medicamento-header h4 {
        margin: 0;
        color: #333;
        font-size: 1.1em;
    }
    .btn-remove {
        background: none;
        border: none;
        color: #dc3545;
        cursor: pointer;
        padding: 5px;
        border-radius: 4px;
        transition: background-color 0.2s;
    }
    .btn-remove:hover {
        background-color: #fff5f5;
    }
</style>

<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>