<?php
include 'config.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    echo '<span style="color:red;">ID inválido.</span>';
    exit;
}

$stmt = $pdo->prepare("SELECT lote, quantidade, validade FROM lotes_medicamentos WHERE medicamento_id = ? ORDER BY validade ASC, lote ASC");
$stmt->execute([$id]);
$lotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($lotes)) {
    echo '<em>Nenhum lote encontrado para este medicamento.</em>';
    exit;
}

?>
<table style="width:100%;border-collapse:collapse;">
    <thead>
        <tr style="background:#e9ecef;">
            <th>Lote</th>
            <th>Quantidade</th>
            <th>Validade</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($lotes as $lote): ?>
        <tr>
            <td><?= htmlspecialchars($lote['lote']) ?></td>
            <td><?= (int)$lote['quantidade'] ?></td>
            <td><?= $lote['validade'] && $lote['validade'] != '0000-00-00' ? date('d/m/Y', strtotime($lote['validade'])) : '--' ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table> 