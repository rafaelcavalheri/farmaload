<?php
require __DIR__ . '/config.php';
verificarAutenticacao(['admin', 'operador']);

$medicamentos_disponiveis = $pdo->query("SELECT id, nome FROM medicamentos ORDER BY nome")->fetchAll();
$medicos_disponiveis = $pdo->query("SELECT id, nome, CONCAT(crm_numero, ' ', crm_estado) as crm_completo FROM medicos WHERE ativo = 1 ORDER BY nome")->fetchAll();

$erros = [];
$valores = [
    'nome' => '',
    'cpf' => '',
    'sim' => '',
    'nascimento' => '',
    'telefone' => '',
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
        'cpf' => preg_replace('/\D/', '', $_POST['cpf'] ?? ''),
        'sim' => trim($_POST['sim'] ?? ''),
        'nascimento' => trim($_POST['nascimento'] ?? ''),
        'telefone' => preg_replace('/\D/', '', $_POST['telefone'] ?? ''),
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
    if (strlen($valores['cpf']) !== 11) {
        $erros['cpf'] = 'CPF inválido.';
    }
    if ($valores['nascimento'] === '') {
        $erros['nascimento'] = 'Data de nascimento é obrigatória.';
    }
    if (strlen($valores['telefone']) < 10) {
        $erros['telefone'] = 'Telefone inválido.';
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

            $valores['medicamentos'][] = [
                'medicamento_id' => $medId,
                'quantidade' => $qtd,
                'quantidade_solicitada' => $qtd_solicitada,
                'cid' => $_POST['cid'][$i] ?? '',
                'medico_id' => !empty($_POST['medico_id'][$i]) ? $_POST['medico_id'][$i] : null,
                'renovacao' => $_POST['renovacao'][$i] ?? null,
                'renovado' => isset($_POST['renovado'][$i]) && $_POST['renovado'][$i] == '1' ? 1 : 0
            ];
        }
    }

    if (empty($erros)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO pacientes (nome, cpf, sim, nascimento, telefone, observacao) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $valores['nome'],
                $valores['cpf'],
                $valores['sim'],
                $valores['nascimento'],
                $valores['telefone'],
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
                $stmtMed = $pdo->prepare("INSERT INTO paciente_medicamentos (paciente_id, medicamento_id, nome_medicamento, quantidade, quantidade_solicitada, cid, medico_id, renovacao, renovado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

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
                        $med['medicamento_id'],
                        $nomeMed,
                        $med['quantidade'],
                        $med['quantidade_solicitada'],
                        $med['cid'],
                        $med['medico_id'],
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
<link rel="stylesheet" href="style.css" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

<?php include __DIR__.'/header.php'; ?>

<main class="container">
    <h2><i class="fas fa-user-plus"></i> Cadastrar Novo Paciente</h2>

    <?php if (!empty($erros['geral'])): ?>
        <div class="alert erro"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($erros['geral']) ?></div>
    <?php endif; ?>

    <form method="post" action="cadastrar_paciente.php" id="form-paciente">
	
        <input type="hidden" name="csrf_token" value="<?= gerarTokenCsrf() ?>" />

        <fieldset>
            <legend>Dados Pessoais</legend>

            <label for="nome">Nome Completo *</label>
            <input type="text" id="nome" name="nome" required value="<?= htmlspecialchars($valores['nome'] ?? '') ?>" />
            <?php if (isset($erros['nome'])): ?><small class="erro"><?= $erros['nome'] ?></small><?php endif; ?>

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
                <!-- Campos de medicamento serão inseridos aqui via JS -->
            </div>
            <button type="button" id="btn-add-medicamento"><i class="fas fa-plus"></i> Adicionar Medicamento</button>
        </fieldset>

        <button type="submit" class="btn-submit">Salvar Paciente</button>
    </form>
</main>

<script src="form_paciente.js"></script>

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
    .medico-campos {
        display: grid;
        grid-template-columns: 1fr;
        gap: 5px;
    }
    .medico-campos input[type="text"] {
        margin-top: 5px;
        font-size: 0.9em;
        padding: 5px;
    }
    .medico-campos select {
        width: 100%;
    }
    @media (max-width: 768px) {
        .autorizado-campos {
            grid-template-columns: 1fr;
        }
    }
    .renovacao-container {
        margin-bottom: 15px;
    }
    .renovacao-data {
        display: flex;
        flex-direction: column;
    }
    .renovacao-linha {
        display: flex;
        align-items: center;
        gap: 20px;
    }
    .renovacao-data input {
        width: 150px;
        height: 35px;
        padding: 5px 10px;
        box-sizing: border-box;
    }
    .renovado-check {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .renovado-check input[type="checkbox"] {
        width: 16px;
        height: 16px;
        margin: 0;
        cursor: pointer;
    }
    .renovado-check label {
        margin: 0;
        font-weight: normal;
        white-space: nowrap;
        cursor: pointer;
    }
    @media (max-width: 768px) {
        .renovacao-linha {
            flex-direction: column;
            align-items: flex-start;
            gap: 10px;
        }
    }
</style>

<script>
$(document).ready(function() {
    const medicamentosDisponiveis = <?= json_encode($medicamentos_disponiveis); ?>;
    const medicosDisponiveis = <?= json_encode($medicos_disponiveis); ?>;
    const container = $('#medicamentos-container');

    function criarMedicamentoGroup(data = {}) {
        const index = container.children('.medicamento-group').length;

        const medicamentoOptions = medicamentosDisponiveis.map(med =>
            `<option value="${med.id}" ${med.id == data.medicamento_id ? 'selected' : ''}>${med.nome}</option>`
        ).join('');

        const medicoOptions = medicosDisponiveis.map(med =>
            `<option value="${med.id}" ${med.id == data.medico_id ? 'selected' : ''}>${med.nome} (${med.crm_completo})</option>`
        ).join('');

        const dataRenovacao = data.renovacao ? formatarDataRenovacao(data.renovacao) : '';
        const renovadoChecked = data.renovado === 1 ? 'checked' : '';

        const grupo = $(`
            <div class="medicamento-group" data-index="${index}">
                <label>Medicamento *</label>
                <select name="medicamento_id[]" required>
                    <option value="">Selecione...</option>
                    ${medicamentoOptions}
                </select>
                <small class="erro medicamento-erro"></small>

                <label>Quantidade Recebida *</label>
                <input type="number" name="quantidade[]" min="1" value="${data.quantidade || ''}" required />
                <small class="erro quantidade-erro"></small>

                <label>Quantidade Solicitada *</label>
                <input type="number" name="quantidade_solicitada[]" min="1" value="${data.quantidade_solicitada || data.quantidade || ''}" required />
                <small class="erro quantidade-solicitada-erro"></small>

                <label>CID</label>
                <input type="text" name="cid[]" value="${data.cid || ''}" />

                <label>Médico</label>
                <select name="medico_id[]">
                    <option value="">Selecione um médico...</option>
                    ${medicoOptions}
                </select>

                <div class="renovacao-container">
                    <div class="renovacao-data">
                        <label>Renovação (DD/MM/AAAA)</label>
                        <div class="renovacao-linha">
                            <input type="text" name="renovacao[]" class="input-data" placeholder="DD/MM/AAAA" value="${dataRenovacao}" />
                            <div class="renovado-check">
                                <input type="checkbox" name="renovado[]" id="renovado-${index}" value="1" ${renovadoChecked} />
                                <label for="renovado-${index}">Renovação em andamento</label>
                            </div>
                        </div>
                    </div>
                </div>

                <button type="button" class="btn-remove-medicamento" title="Remover"><i class="fas fa-trash-alt"></i></button>

                <hr/>
            </div>
        `);

        // Aplica máscara simples para MM/AAAA
        grupo.find('.input-mes-ano').on('input', function() {
            let val = $(this).val();
            val = val.replace(/[^\d]/g, '');
            if(val.length > 2) {
                val = val.slice(0, 2) + '/' + val.slice(2, 6);
            }
            $(this).val(val);
        });

        grupo.find('.btn-remove-medicamento').click(function() {
            grupo.remove();
        });

        container.append(grupo);
    }

    // Adicionar primeiro medicamento vazio ao abrir
    if(container.children().length === 0) {
        criarMedicamentoGroup();
    }

    $('#btn-add-medicamento').click(() => criarMedicamentoGroup());

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

</body>
</html>