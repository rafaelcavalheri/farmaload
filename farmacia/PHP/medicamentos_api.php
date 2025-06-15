<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';
require_once 'funcoes_estoque.php';

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        try {
            $sql = "SELECT id, nome, apresentacao, codigo, miligramas, ativo FROM medicamentos WHERE ativo = 1 ORDER BY nome";
            $stmt = $pdo->query($sql);
            $medicamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate current stock for each medication
            foreach ($medicamentos as &$medicamento) {
                $medicamento['quantidade'] = calcularEstoqueAtual($pdo, $medicamento['id']);
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
        try {
            $sql = "UPDATE medicamentos SET quantidade = :quantidade WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':quantidade', $quantidade, PDO::PARAM_INT);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Medicamento atualizado com sucesso'
                ]);
                exit;
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Erro ao atualizar medicamento'
                ]);
                exit;
            }
        } catch (PDOException $e) {
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