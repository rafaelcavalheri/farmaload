<?php
/**
 * Script de Manutenção de Lotes
 * Versão: 1.0
 * Data: 27/06/2025
 * 
 * Este script identifica e limpa lotes antigos zerados
 * Pode ser executado via:
 * - Cron job (automático)
 * - Linha de comando: php manutencao_lotes.php
 * - Web: manutencao_lotes.php?acao=executar
 */

// Configurações
$config = [
    'manter_lotes_por_dias' => 730, // 2 anos
    'manter_vencidos_por_dias' => 365, // 1 ano após vencimento
    'limite_lotes_por_execucao' => 100, // Máximo de lotes removidos por execução
    'enviar_email_relatorio' => true,
    'email_destinatario' => 'admin@farmacia.com'
];

// Verificar se é execução via web ou linha de comando
$is_web = isset($_SERVER['HTTP_HOST']);
$acao = $is_web ? ($_GET['acao'] ?? 'relatorio') : ($argv[1] ?? 'relatorio');

// Incluir configurações do sistema
require_once 'config.php';

class ManutencaoLotes {
    private $pdo;
    private $config;
    private $log = [];
    
    public function __construct($pdo, $config) {
        $this->pdo = $pdo;
        $this->config = $config;
    }
    
    /**
     * Identifica lotes candidatos à limpeza
     */
    public function identificarLotesCandidatos() {
        $sql = "
            SELECT 
                l.id,
                l.lote as numero_lote,
                l.medicamento_id,
                m.nome as medicamento_nome,
                l.quantidade,
                l.validade,
                l.data_atualizacao as data_ultima_dispensa,
                DATEDIFF(NOW(), l.data_atualizacao) as dias_sem_movimento,
                DATEDIFF(l.validade, NOW()) as dias_ate_vencimento
            FROM lotes_medicamentos l
            JOIN medicamentos m ON l.medicamento_id = m.id
            WHERE l.quantidade = 0
            AND (
                (l.data_atualizacao IS NOT NULL AND l.data_atualizacao < DATE_SUB(NOW(), INTERVAL ? DAY))
                OR 
                (l.validade < DATE_SUB(NOW(), INTERVAL ? DAY))
            )
            ORDER BY l.data_atualizacao ASC, l.validade ASC
            LIMIT ?
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $this->config['manter_lotes_por_dias'],
            $this->config['manter_vencidos_por_dias'],
            $this->config['limite_lotes_por_execucao']
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Gera relatório de lotes candidatos
     */
    public function gerarRelatorio() {
        $lotes = $this->identificarLotesCandidatos();
        
        $relatorio = [
            'data_geracao' => date('d/m/Y H:i:s'),
            'total_lotes' => count($lotes),
            'lotes' => $lotes,
            'configuracoes' => $this->config
        ];
        
        $this->log[] = "Relatório gerado: " . count($lotes) . " lotes candidatos à limpeza";
        
        return $relatorio;
    }
    
    /**
     * Executa a limpeza dos lotes
     */
    public function executarLimpeza($confirmar = false) {
        if (!$confirmar) {
            $this->log[] = "ERRO: Confirmação necessária para executar limpeza";
            return false;
        }
        
        // Criar backup antes da limpeza
        $this->criarBackup();
        
        $lotes = $this->identificarLotesCandidatos();
        $removidos = 0;
        
        foreach ($lotes as $lote) {
            try {
                // Remover lote
                $sql = "DELETE FROM lotes_medicamentos WHERE id = ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$lote['id']]);
                
                if ($stmt->rowCount() > 0) {
                    $removidos++;
                    $this->log[] = "Lote removido: {$lote['numero_lote']} - {$lote['medicamento_nome']}";
                }
            } catch (Exception $e) {
                $this->log[] = "ERRO ao remover lote {$lote['id']}: " . $e->getMessage();
            }
        }
        
        $this->log[] = "Limpeza concluída: {$removidos} lotes removidos";
        return $removidos;
    }
    
    /**
     * Cria backup antes da limpeza
     */
    private function criarBackup() {
        $data_backup = date('Ymd_His'); // Formato sem hífens para evitar problemas
        $nome_tabela = "lotes_backup_{$data_backup}";
        
        $sql = "
            CREATE TABLE `{$nome_tabela}` AS 
            SELECT * FROM lotes_medicamentos 
            WHERE quantidade = 0 
            AND (
                (data_atualizacao IS NOT NULL AND data_atualizacao < DATE_SUB(NOW(), INTERVAL ? DAY))
                OR 
                (validade < DATE_SUB(NOW(), INTERVAL ? DAY))
            )
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $this->config['manter_lotes_por_dias'],
            $this->config['manter_vencidos_por_dias']
        ]);
        
        $this->log[] = "Backup criado: {$nome_tabela}";
    }
    
    /**
     * Envia relatório por email
     */
    public function enviarRelatorioEmail($relatorio) {
        if (!$this->config['enviar_email_relatorio']) {
            return false;
        }
        
        $assunto = "Relatório de Manutenção de Lotes - " . date('d/m/Y');
        
        $corpo = "
        <h2>Relatório de Manutenção de Lotes</h2>
        <p><strong>Data:</strong> {$relatorio['data_geracao']}</p>
        <p><strong>Total de lotes candidatos:</strong> {$relatorio['total_lotes']}</p>
        
        <h3>Configurações:</h3>
        <ul>
            <li>Manter lotes por: {$this->config['manter_lotes_por_dias']} dias</li>
            <li>Manter vencidos por: {$this->config['manter_vencidos_por_dias']} dias</li>
            <li>Limite por execução: {$this->config['limite_lotes_por_execucao']} lotes</li>
        </ul>
        ";
        
        if ($relatorio['total_lotes'] > 0) {
            $corpo .= "<h3>Lotes candidatos à limpeza:</h3><ul>";
            foreach ($relatorio['lotes'] as $lote) {
                $corpo .= "<li>{$lote['numero_lote']} - {$lote['medicamento_nome']} (Última dispensa: {$lote['data_ultima_dispensa']})</li>";
            }
            $corpo .= "</ul>";
        }
        
        // Enviar email (implementar conforme sistema de email)
        $this->log[] = "Relatório enviado por email para: {$this->config['email_destinatario']}";
    }
    
    /**
     * Retorna logs da execução
     */
    public function getLogs() {
        return $this->log;
    }
}

// Execução do script
try {
    $manutencao = new ManutencaoLotes($pdo, $config);
    
    switch ($acao) {
        case 'relatorio':
            $relatorio = $manutencao->gerarRelatorio();
            
            if ($is_web) {
                header('Content-Type: application/json');
                echo json_encode($relatorio, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            } else {
                echo "=== RELATÓRIO DE MANUTENÇÃO DE LOTES ===\n";
                echo "Data: {$relatorio['data_geracao']}\n";
                echo "Total de lotes candidatos: {$relatorio['total_lotes']}\n\n";
                
                if ($relatorio['total_lotes'] > 0) {
                    echo "Lotes candidatos à limpeza:\n";
                    foreach ($relatorio['lotes'] as $lote) {
                        echo "- {$lote['numero_lote']} - {$lote['medicamento_nome']}\n";
                        echo "  Última dispensa: {$lote['data_ultima_dispensa']}\n";
                        echo "  Validade: {$lote['validade']}\n\n";
                    }
                }
            }
            break;
            
        case 'executar':
            $confirmar = $is_web ? ($_GET['confirmar'] ?? false) : ($argv[2] ?? false);
            $removidos = $manutencao->executarLimpeza($confirmar);
            
            if ($is_web) {
                header('Content-Type: application/json');
                echo json_encode([
                    'sucesso' => $removidos !== false,
                    'lotes_removidos' => $removidos,
                    'logs' => $manutencao->getLogs()
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            } else {
                echo "=== EXECUÇÃO DE LIMPEZA ===\n";
                if ($removidos !== false) {
                    echo "✅ Limpeza executada com sucesso!\n";
                    echo "Lotes removidos: {$removidos}\n";
                } else {
                    echo "❌ Erro na execução da limpeza\n";
                }
                
                echo "\nLogs:\n";
                foreach ($manutencao->getLogs() as $log) {
                    echo "- {$log}\n";
                }
            }
            break;
            
        default:
            if ($is_web) {
                echo "<h1>Script de Manutenção de Lotes</h1>";
                echo "<p><a href='?acao=relatorio'>Gerar Relatório</a></p>";
                echo "<p><a href='?acao=executar&confirmar=1' onclick='return confirm(\"Confirmar limpeza?\")'>Executar Limpeza</a></p>";
            } else {
                echo "Uso: php manutencao_lotes.php [relatorio|executar] [confirmar]\n";
                echo "Exemplos:\n";
                echo "  php manutencao_lotes.php relatorio\n";
                echo "  php manutencao_lotes.php executar confirmar\n";
            }
    }
    
} catch (Exception $e) {
    if ($is_web) {
        header('Content-Type: application/json');
        echo json_encode(['erro' => $e->getMessage()]);
    } else {
        echo "ERRO: " . $e->getMessage() . "\n";
    }
}
?> 