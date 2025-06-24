<?php
require __DIR__ . '/config.php';
verificarAutenticacao(['admin']);

$acao = $_POST['acao'] ?? $_GET['acao'] ?? null;

try {
    if ($acao === 'criar') {
        $nome = trim($_POST['nome']);
        $email = trim($_POST['email']);
        $senha = $_POST['senha'] ?? '';
        $perfil = $_POST['perfil'];
        $auth_type = $_POST['auth_type'];

        // Validações básicas
        if (empty($nome) || empty($email)) {
            throw new Exception('Nome e email são obrigatórios.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Email inválido.');
        }

        // Validação específica para usuários locais
        if ($auth_type === 'local') {
            if (empty($senha)) {
                throw new Exception('Senha é obrigatória para usuários locais.');
            }
            if (strlen($senha) < 6) {
                throw new Exception('A senha deve ter no mínimo 6 caracteres.');
            }
        }

        // Verifica se o e-mail já está cadastrado
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception('Este e-mail já está em uso.');
        }

        try {
            $pdo->beginTransaction();

            $hash = $auth_type === 'local' ? password_hash($senha, PASSWORD_DEFAULT) : null;

            $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, perfil, auth_type, data_cadastro) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$nome, $email, $hash, $perfil, $auth_type]);

            $pdo->commit();
            header("Location: usuarios.php?sucesso=" . urlencode("Usuário criado com sucesso"));
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw new Exception('Erro ao criar usuário: ' . $e->getMessage());
        }

    } elseif ($acao === 'excluir') {
        $id = intval($_GET['id']);

        // Não permitir excluir a si mesmo
        if ($id == $_SESSION['usuario']['id']) {
            throw new Exception('Você não pode excluir seu próprio usuário.');
        }

        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);

        header("Location: usuarios.php?sucesso=" . urlencode("Usuário excluído com sucesso"));
        exit;

    } elseif ($acao === 'resetar') {
        $id = intval($_POST['id']);
        $novaSenha = $_POST['nova_senha'];
        $confirmarSenha = $_POST['confirmar_senha'];

        // Verificar se o usuário é local
        $stmt = $pdo->prepare("SELECT auth_type FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
        $usuario = $stmt->fetch();

        if ($usuario['auth_type'] === 'ldap') {
            throw new Exception('Não é possível resetar a senha de um usuário LDAP.');
        }

        if ($novaSenha !== $confirmarSenha) {
            throw new Exception('As senhas não coincidem.');
        }

        if (strlen($novaSenha) < 6) {
            throw new Exception('A nova senha deve ter no mínimo 6 caracteres.');
        }

        $hash = password_hash($novaSenha, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
        $stmt->execute([$hash, $id]);

        header("Location: usuarios.php?sucesso=" . urlencode("Senha resetada com sucesso"));
        exit;
    } elseif ($acao === 'alterar_perfil') {
        $id = intval($_POST['id']);
        $novo_perfil = $_POST['novo_perfil'] ?? '';
        if ($id == $_SESSION['usuario']['id']) {
            throw new Exception('Você não pode alterar o próprio perfil.');
        }
        if (!in_array($novo_perfil, ['admin', 'operador'])) {
            throw new Exception('Perfil inválido.');
        }
        $stmt = $pdo->prepare("UPDATE usuarios SET perfil = ? WHERE id = ?");
        $stmt->execute([$novo_perfil, $id]);
        header("Location: usuarios.php?sucesso=" . urlencode("Perfil alterado com sucesso"));
        exit;
    } elseif ($acao === 'desativar') {
        $id = intval($_GET['id']);
        if ($id == $_SESSION['usuario']['id']) {
            throw new Exception('Você não pode desativar seu próprio usuário.');
        }
        $stmt = $pdo->prepare("UPDATE usuarios SET ativo = 0 WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: usuarios.php?sucesso=" . urlencode("Usuário desativado com sucesso"));
        exit;
    } elseif ($acao === 'ativar') {
        $id = intval($_GET['id']);
        $stmt = $pdo->prepare("UPDATE usuarios SET ativo = 1 WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: usuarios.php?sucesso=" . urlencode("Usuário ativado com sucesso"));
        exit;
    } elseif ($acao === 'editar') {
        $id = intval($_POST['id']);
        if ($id == $_SESSION['usuario']['id']) {
            throw new Exception('Você não pode editar seu próprio usuário por aqui.');
        }
        $nome = trim($_POST['nome']);
        $email = trim($_POST['email']);
        $auth_type = $_POST['auth_type'];
        $perfil = $_POST['perfil'];
        if (empty($nome) || empty($email)) {
            throw new Exception('Nome e email são obrigatórios.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Email inválido.');
        }
        // Verifica se o e-mail já está em uso por outro usuário
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
        $stmt->execute([$email, $id]);
        if ($stmt->fetch()) {
            throw new Exception('Este e-mail já está em uso por outro usuário.');
        }
        if (!in_array($auth_type, ['local', 'ldap'])) {
            throw new Exception('Tipo de autenticação inválido.');
        }
        if (!in_array($perfil, ['admin', 'operador'])) {
            throw new Exception('Perfil inválido.');
        }
        $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ?, auth_type = ?, perfil = ? WHERE id = ?");
        $stmt->execute([$nome, $email, $auth_type, $perfil, $id]);
        header("Location: usuarios.php?sucesso=" . urlencode("Usuário editado com sucesso"));
        exit;
    } else {
        throw new Exception('Ação inválida.');
    }
} catch (Exception $e) {
    header("Location: usuarios.php?erro=" . urlencode($e->getMessage()));
    exit;
}
