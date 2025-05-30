<?php
include 'config.php';
include 'funcoes_estoque.php';

if (!isset($_SESSION['usuario'])) {
    die("Acesso negado");
}

$busca = $_GET['busca'] ?? '';
$sql = "SELECT 
            id,
            nome,
            quantidade,
            codigo,
            lote,
            apresentacao,
            validade,
            ativo
        FROM medicamentos";

$params = [];

if (!empty($busca)) {
    $sql .= " WHERE LOWER(nome) LIKE ? OR LOWER(codigo) LIKE ? OR LOWER(lote) LIKE ?";
    $params = ["%$busca%", "%$busca%", "%$busca%"];
}

$sql .= " ORDER BY nome ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

if ($stmt->rowCount() > 0) {
    while ($medicamento = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
        <tr>
            <td><?= htmlspecialchars($medicamento['nome'] ?? '') ?></td>
            <td><?= calcularEstoqueAtual($pdo, $medicamento['id']) ?></td>
            <?php $ultimaImport = getTotalUltimaImportacao($pdo, $medicamento['id']); ?>
            <td>
                <?= $ultimaImport ? $ultimaImport['total'] : '--' ?>
            </td>
            <td><?= htmlspecialchars($medicamento['codigo'] ?? '') ?></td>
            <td><?= htmlspecialchars($medicamento['lote'] ?? '') ?></td>
            <td><?= htmlspecialchars($medicamento['apresentacao'] ?? '') ?></td>
            <td>
                <?php if (!empty($medicamento['validade']) && $medicamento['validade'] != '0000-00-00'): ?>
                    <?= date('d/m/Y', strtotime($medicamento['validade'])) ?>
                <?php else: ?>
                    --
                <?php endif; ?>
            </td>
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
    <?php endwhile;
} else {
    echo '<tr><td colspan="8" class="no-results">' . 
         (empty($busca) ? 'Nenhum medicamento cadastrado.' : 'Nenhum resultado encontrado para sua busca.') . 
         '</td></tr>';
}