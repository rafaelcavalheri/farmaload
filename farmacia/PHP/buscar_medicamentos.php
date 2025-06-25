<?php
include 'config.php';
include 'funcoes_estoque.php';

if (!isset($_SESSION['usuario'])) {
    die("Acesso negado");
}

$busca = $_GET['busca'] ?? '';
$ordem = $_GET['ordem'] ?? 'nome';
$direcao = $_GET['direcao'] ?? 'ASC';
$colunas_ordenacao = [
    'nome' => 'm.nome',
    'quantidade' => null, // será tratado manualmente
    'total_recebido' => null, // será tratado manualmente
    'codigo' => 'm.codigo',
    'lote' => 'MIN(lm.lote)',
    'apresentacao' => 'm.apresentacao',
    'validade' => 'MIN(lm.validade)'
];

$sql = "SELECT 
            m.id,
            m.nome,
            m.codigo,
            m.apresentacao,
            m.ativo,
            GROUP_CONCAT(DISTINCT CONCAT(lm.lote, ' (', lm.quantidade, ')') ORDER BY lm.validade ASC SEPARATOR '<br>') as lotes,
            MIN(lm.validade) as validade
        FROM medicamentos m
        LEFT JOIN lotes_medicamentos lm ON m.id = lm.medicamento_id
        WHERE EXISTS (
            SELECT 1 
            FROM lotes_medicamentos lm2 
            WHERE lm2.medicamento_id = m.id 
            AND lm2.quantidade > 0
        )";

$params = [];

if (!empty($busca)) {
    $sql .= " AND (LOWER(m.nome) LIKE ? OR LOWER(m.codigo) LIKE ? OR LOWER(lm.lote) LIKE ?)";
    $params = ["%$busca%", "%$busca%", "%$busca%"];
}

// Sempre adicionar GROUP BY
$sql .= " GROUP BY m.id, m.nome, m.codigo, m.apresentacao, m.ativo";

if ($ordem === 'total_recebido' || $ordem === 'quantidade') {
    // Para ordenações especiais, buscar todos e ordenar no PHP
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $medicamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($ordem === 'total_recebido') {
        foreach ($medicamentos as &$medicamento) {
            $ultimaImport = getTotalUltimaImportacao($pdo, $medicamento['id']);
            $medicamento['total_recebido'] = $ultimaImport ? $ultimaImport['total'] : 0;
        }
        unset($medicamento);
        usort($medicamentos, function($a, $b) use ($direcao) {
            if ($a['total_recebido'] == $b['total_recebido']) return 0;
            if ($direcao === 'DESC') {
                return $a['total_recebido'] < $b['total_recebido'] ? 1 : -1;
            } else {
                return $a['total_recebido'] > $b['total_recebido'] ? 1 : -1;
            }
        });
    } else if ($ordem === 'quantidade') {
        foreach ($medicamentos as &$medicamento) {
            $medicamento['quantidade'] = calcularEstoqueAtual($pdo, $medicamento['id']);
        }
        unset($medicamento);
        usort($medicamentos, function($a, $b) use ($direcao) {
            if ($a['quantidade'] == $b['quantidade']) return 0;
            if ($direcao === 'DESC') {
                return $a['quantidade'] < $b['quantidade'] ? 1 : -1;
            } else {
                return $a['quantidade'] > $b['quantidade'] ? 1 : -1;
            }
        });
    }
} else {
    // Para outras ordenações, usar ORDER BY no SQL
    if (isset($colunas_ordenacao[$ordem]) && $colunas_ordenacao[$ordem]) {
        $sql .= " ORDER BY " . $colunas_ordenacao[$ordem] . " " . ($direcao === 'DESC' ? 'DESC' : 'ASC');
    } else {
        $sql .= " ORDER BY m.nome ASC";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $medicamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ESTRUTURA UNIFICADA DE EXIBIÇÃO
if (!empty($medicamentos)) {
    foreach ($medicamentos as $medicamento): ?>
        <tr class="med-row" data-id="<?= $medicamento['id'] ?>">
            <td class="med-nome">
                <?= htmlspecialchars($medicamento['nome']) ?>
            </td>
            <td><?= calcularEstoqueAtual($pdo, $medicamento['id']) ?></td>
            <?php $ultimaImport = getTotalUltimaImportacao($pdo, $medicamento['id']); ?>
            <td>
                <?= $ultimaImport ? $ultimaImport['total'] : '--' ?>
            </td>
            <td><?= htmlspecialchars($medicamento['codigo']) ?></td>
            <td>
                <?php
                // Buscar lotes ativos com validade
                $stmtLotes = $pdo->prepare("
                    SELECT lote, validade, quantidade
                    FROM lotes_medicamentos 
                    WHERE medicamento_id = ? AND quantidade > 0 
                    ORDER BY validade ASC
                ");
                $stmtLotes->execute([$medicamento['id']]);
                $lotes = $stmtLotes->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($lotes)) {
                    if (count($lotes) == 1) {
                        // Se há apenas um lote, mostrar diretamente
                        $lote = $lotes[0];
                        echo '<div class="lote-single">';
                        echo '<strong>' . htmlspecialchars($lote['lote']) . '</strong><br>';
                        echo '<span class="lote-info">' . $lote['quantidade'] . ' un - ';
                        echo ($lote['validade'] && $lote['validade'] != '0000-00-00') ? date('d/m/Y', strtotime($lote['validade'])) : '--';
                        echo '</span>';
                        echo '</div>';
                    } else {
                        // Se há múltiplos lotes, mostrar o primeiro e um botão para expandir
                        $primeiroLote = $lotes[0];
                        echo '<div class="lotes-container">';
                        echo '<div class="lote-principal">';
                        echo '<strong>' . htmlspecialchars($primeiroLote['lote']) . '</strong><br>';
                        echo '<span class="lote-info">' . $primeiroLote['quantidade'] . ' un - ';
                        echo ($primeiroLote['validade'] && $primeiroLote['validade'] != '0000-00-00') ? date('d/m/Y', strtotime($primeiroLote['validade'])) : '--';
                        echo '</span>';
                        echo '</div>';
                        echo '<button class="btn-lotes-toggle" onclick="toggleLotes(' . $medicamento['id'] . ')">';
                        echo '<i class="fas fa-chevron-down"></i> Ver mais (' . (count($lotes) - 1) . ')';
                        echo '</button>';
                        echo '<div class="lotes-adicionais" id="lotes-' . $medicamento['id'] . '" style="display:none;">';
                        for ($i = 1; $i < count($lotes); $i++) {
                            $lote = $lotes[$i];
                            echo '<div class="lote-adicional">';
                            echo '<strong>' . htmlspecialchars($lote['lote']) . '</strong><br>';
                            echo '<span class="lote-info">' . $lote['quantidade'] . ' un - ';
                            echo ($lote['validade'] && $lote['validade'] != '0000-00-00') ? date('d/m/Y', strtotime($lote['validade'])) : '--';
                            echo '</span>';
                            echo '</div>';
                        }
                        echo '</div>';
                        echo '</div>';
                    }
                } else {
                    echo "--";
                }
                ?>
            </td>
            <td><?= htmlspecialchars($medicamento['apresentacao']) ?></td>
            <td class="actions">
                <div style="display: flex; gap: 6px;">
                    <a href="editar_medicamento.php?id=<?= $medicamento['id'] ?>" class="btn-secondary btn-small">
                        <i class="fas fa-edit"></i>
                    </a>
                    <?php if ($medicamento['ativo']): ?>
                        <a href="medicamentos.php?inativar=<?= $medicamento['id'] ?>"
                            class="btn-secondary btn-small"
                            onclick="return confirm('Tem certeza que deseja inativar este medicamento?')">
                            <i class="fas fa-power-off"></i>
                        </a>
                    <?php else: ?>
                        <a href="medicamentos.php?ativar=<?= $medicamento['id'] ?>"
                            class="btn-secondary btn-small btn-inativo"
                            onclick="return confirm('Tem certeza que deseja ativar este medicamento?')">
                            <i class="fas fa-power-off"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
    <?php endforeach;
} else {
    echo '<tr><td colspan="8" class="no-results">' . 
         (empty($busca) ? 'Nenhum medicamento cadastrado.' : 'Nenhum resultado encontrado para sua busca.') . 
         '</td></tr>';
}
?>
