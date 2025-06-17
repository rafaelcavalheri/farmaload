<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'config.php';
require_once 'funcoes_estoque.php';
require_once 'jwt_auth.php';

// Verificar autenticação JWT
$auth = JWTAuth::requireAuth();
$userId = $auth->uid;

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        try {
            $sql = "SELECT id, nome, apresentacao, codigo, miligramas, ativo FROM medicamentos WHERE ativo = 1 ORDER BY nome";
            $stmt = $pdo->query($sql);
            $medicamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate current stock and get lots for each medication
            foreach ($medicamentos as &$medicamento) {
                $medicamento['quantidade'] = calcularEstoqueAtual($pdo, $medicamento['id']);
                
                // Get lots for this medication
                $sql_lotes = "SELECT id, lote as numero, quantidade, DATE_FORMAT(validade, '%Y-%m-%d') as validade 
                             FROM lotes_medicamentos 
                             WHERE medicamento_id = ? AND quantidade > 0 
                             ORDER BY validade ASC";
                $stmt_lotes = $pdo->prepare($sql_lotes);
                $stmt_lotes->execute([$medicamento['id']]);
                $medicamento['lotes'] = $stmt_lotes->fetchAll(PDO::FETCH_ASSOC);
            }
            unset($medicamento);
            
            echo json_encode($medicamentos);
            exit;
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao buscar medicamentos: ' . $e->getMessage()
            ]);
            exit;
        }
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['id']) || !isset($data['quantidade'])) {
            echo json_encode([
                'success' => false,
                'message' => 'ID e quantidade são obrigatórios'
            ]);
            exit;
        }
        $id = $data['id'];
        $quantidade = $data['quantidade'];
        $lotes = $data['lotes'] ?? [];
        
        try {
            $pdo->beginTransaction();
            
            // Update lots
            if (!empty($lotes)) {
                foreach ($lotes as $lote) {
                    if (isset($lote['id'])) {
                        // Update existing lot
                        $sql_lote = "UPDATE lotes_medicamentos 
                                   SET lote = :numero, 
                                       quantidade = :quantidade, 
                                       validade = :validade 
                                   WHERE id = :id AND medicamento_id = :medicamento_id";
                        $stmt_lote = $pdo->prepare($sql_lote);
                        $stmt_lote->execute([
                            ':numero' => $lote['numero'],
                            ':quantidade' => $lote['quantidade'],
                            ':validade' => $lote['validade'],
                            ':id' => $lote['id'],
                            ':medicamento_id' => $id
                        ]);
                    }
                }
            }
            
            $pdo->commit();
            echo json_encode([
                'success' => true,
                'message' => 'Medicamento atualizado com sucesso'
            ]);
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao atualizar medicamento: ' . $e->getMessage()
            ]);
            exit;
        }
    default:
        echo json_encode([
            'success' => false,
            'message' => 'Método não permitido'
        ]);
        exit;
}