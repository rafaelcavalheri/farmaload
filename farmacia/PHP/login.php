<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Detecta se é uma requisição da API
$isApi = (
    isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false
) || (
    isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false
);

if ($isApi) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, Accept');
    header('Access-Control-Allow-Credentials: true');
}

// Inicia a sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_log("Iniciando processo de login");
error_log("Session ID: " . session_id());
error_log("Conteúdo da sessão antes do login: " . print_r($_SESSION, true));

// Corrigindo os caminhos dos arquivos
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ldap_settings.php';

// Função para autenticar usuário no AD
function authenticateADUser($email, $senha, $ldapServer, $ldapDomain, $ldapBaseDn) {
    error_log("Tentando autenticar usuário LDAP: " . $email);
    error_log("Configurações LDAP: Server=$ldapServer, Domain=$ldapDomain, BaseDN=$ldapBaseDn");
    
    // Remove o domínio do email se existir
    $sAMAccountName = strstr($email, '@', true) ?: $email;
    $userPrincipalName = $sAMAccountName . '@' . $ldapDomain;
    
    error_log("Tentando conectar ao servidor LDAP: " . $ldapServer);
    $ldap = ldap_connect($ldapServer);
    
    if (!$ldap) {
        error_log("Falha ao conectar ao servidor LDAP");
        return false;
    }
    
    ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
    
    error_log("Tentando bind com: " . $userPrincipalName);
    $bind = @ldap_bind($ldap, $userPrincipalName, $senha);
    
    if (!$bind) {
        error_log("Falha no bind LDAP: " . ldap_error($ldap));
        return false;
    }
    
    error_log("Bind LDAP bem sucedido");
    ldap_close($ldap);
    return true;
}

// Se for uma requisição da API
if ($isApi) {
    $data = json_decode(file_get_contents('php://input'), true);
    error_log("Dados recebidos da API: " . print_r($data, true));

    if (!isset($data['email']) || !isset($data['senha'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Email e senha são obrigatórios'
        ]);
        exit;
    }

    $email = trim($data['email']);
    $senha = $data['senha'];
    $loginType = $data['login_type'] ?? 'local';

    try {
        if ($loginType === 'ldap') {
            // Autenticação LDAP
            $ldapResult = authenticateADUser($email, $senha, $ldapServer, $ldapDomain, $ldapBaseDn);
            if ($ldapResult === true) {
                $sAMAccountName = strstr($email, '@', true) ?: $email;
                $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email LIKE ? AND auth_type = 'ldap'");
                $stmt->execute([$sAMAccountName . '%']);
                $usuario = $stmt->fetch();
                
                if ($usuario) {
                    $token = bin2hex(random_bytes(32));
                    $_SESSION['api_tokens'][$token] = [
                        'user_id' => $usuario['id'],
                        'created_at' => time()
                    ];
                    
                    error_log("Token gerado para usuário LDAP: " . $usuario['id']);
                    error_log("Token armazenado: " . $token);
                    
                    echo json_encode([
                        'success' => true,
                        'token' => $token,
                        'message' => 'Login realizado com sucesso (LDAP)'
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Usuário LDAP não cadastrado no sistema.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Falha na autenticação LDAP.']);
            }
        } else {
            // Autenticação local
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND auth_type = 'local'");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();
            
            if ($usuario && password_verify($senha, $usuario['senha'])) {
                $token = bin2hex(random_bytes(32));
                $_SESSION['api_tokens'][$token] = [
                    'user_id' => $usuario['id'],
                    'created_at' => time()
                ];
                
                error_log("Token gerado para usuário local: " . $usuario['id']);
                error_log("Token armazenado: " . $token);
                
                echo json_encode([
                    'success' => true,
                    'token' => $token,
                    'message' => 'Login realizado com sucesso'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Credenciais inválidas']);
            }
        }
    } catch (Exception $e) {
        error_log("Erro durante login: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
    }
    exit;
}

// Se não for API, mostra o formulário HTML
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $loginType = $_POST['login_type'] ?? 'local';
    
    try {
        if ($loginType === 'ldap') {
            $ldapResult = authenticateADUser($email, $senha, $ldapServer, $ldapDomain, $ldapBaseDn);
            if ($ldapResult === true) {
                $sAMAccountName = strstr($email, '@', true) ?: $email;
                $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email LIKE ? AND auth_type = 'ldap'");
                $stmt->execute([$sAMAccountName . '%']);
                $usuario = $stmt->fetch();
                
                if ($usuario) {
                    $_SESSION['usuario'] = [
                        'id'      => $usuario['id'],
                        'nome'    => $usuario['nome'],
                        'email'   => $usuario['email'],
                        'perfil'  => $usuario['perfil']
                    ];
                    header('Location: index.php');
                    exit();
                } else {
                    $erro = "Usuário LDAP não cadastrado no sistema. Entre em contato com o administrador.";
                }
            } else {
                $erro = "Falha na autenticação LDAP. Verifique suas credenciais.";
            }
        } else {
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND auth_type = 'local'");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();
            
            if ($usuario && password_verify($senha, $usuario['senha'])) {
                $_SESSION['usuario'] = [
                    'id'      => $usuario['id'],
                    'nome'    => $usuario['nome'],
                    'email'   => $usuario['email'],
                    'perfil'  => $usuario['perfil']
                ];
                header('Location: index.php');
                exit();
            } else {
                $erro = "E-mail ou senha inválidos.";
            }
        }
    } catch (Exception $e) {
        $erro = "Erro no sistema. Tente novamente mais tarde.";
        error_log("Erro durante login: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Login - FarmAlto</title>
    <link rel="icon" type="image/png" href="/images/fav.png">
    <link rel="stylesheet" href="/css/style.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body>
    <div class="login-container">
        <h1><i class="fas fa-clinic-medical"></i> FarmAlto</h1>

        <?php if (!empty($erro)): ?>
            <div class="alert erro">
                <i class="fas fa-exclamation-triangle"></i>
                <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <div class="input-icon">
                <i class="fas fa-envelope"></i>
                <input id="email" type="email" name="email" placeholder="Digite seu e-mail" required autofocus />
            </div>

            <div class="input-icon">
                <i class="fas fa-lock"></i>
                <input id="senha" type="password" name="senha" placeholder="Digite sua senha" required />
            </div>

            <div class="form-group">
                <select name="login_type" id="login_type" class="login-type-selector">
                    <option value="ldap">Rede</option>
                    <option value="local">Local</option>
                </select>
            </div>

            <button type="submit" class="btn-primary">Entrar</button>
        </form>

        <p style="margin-top: 1rem; font-size: 0.9rem;">
            © 2025 Prefeitura de Mogi Mirim
        </p>
    </div>
</body>
</html>
