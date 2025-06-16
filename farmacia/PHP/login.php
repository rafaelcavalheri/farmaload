<?php
session_start();
include 'config.php';

$erro = '';

// Detecta se é uma requisição JSON (API)
$isApi = (
    isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false
) || (
    isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false
);

if ($isApi) {
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['email']) || !isset($data['senha'])) {
        echo json_encode(['success' => false, 'message' => 'Email e senha são obrigatórios']);
        exit;
    }

    $email = trim($data['email']);
    $senha = $data['senha'];
    $loginType = $data['login_type'] ?? 'local';

    try {
        if ($loginType === 'ldap') {
            // Autenticação LDAP
            $ldapConfig = null;
            $configFile = __DIR__ . '/ldap_settings.php';
            if (file_exists($configFile)) {
                $ldapConfig = include $configFile;
            }
            if (!$ldapConfig) {
                echo json_encode(['success' => false, 'message' => 'Configurações LDAP não encontradas.']);
                exit;
            }
            $ldapServer = $ldapConfig['ldap_server'];
            $ldapDomain = $ldapConfig['ldap_domain'];
            $ldapBaseDn = $ldapConfig['ldap_base_dn'];
            $ldapResult = authenticateADUser($email, $senha, $ldapServer, $ldapDomain, $ldapBaseDn);
            if ($ldapResult === true) {
                $sAMAccountName = strstr($email, '@', true) ?: $email;
                $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email LIKE ? AND auth_type = 'ldap'");
                $stmt->execute([$sAMAccountName . '%']);
                $usuario = $stmt->fetch();
                if ($usuario) {
                    echo json_encode([
                        'success' => true,
                        'token' => bin2hex(random_bytes(32)),
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
                echo json_encode([
                    'success' => true,
                    'token' => bin2hex(random_bytes(32)),
                    'message' => 'Login realizado com sucesso'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Credenciais inválidas']);
            }
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erro no banco de dados: ' . $e->getMessage()]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
    }
    exit;
}

// --- Código tradicional para acesso via navegador (formulário HTML) ---

// Carregar configurações LDAP
$ldapConfig = null;
$configFile = __DIR__ . '/ldap_settings.php';
if (file_exists($configFile)) {
    $ldapConfig = include $configFile;
}
if (!$ldapConfig) {
    throw new Exception("Configurações LDAP não encontradas. Por favor, configure o LDAP primeiro.");
}
$ldapServer = $ldapConfig['ldap_server'];
$ldapDomain = $ldapConfig['ldap_domain'];
$ldapBaseDn = $ldapConfig['ldap_base_dn'];

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
    } catch (PDOException $e) {
        if (ENVIRONMENT === 'development') {
            $erro = "Erro no banco de dados: " . $e->getMessage();
        } else {
            $erro = "Erro no sistema. Tente novamente mais tarde.";
        }
    } catch (Exception $e) {
        if (ENVIRONMENT === 'development') {
            $erro = "Erro: " . $e->getMessage();
        } else {
            $erro = "Erro no sistema. Tente novamente mais tarde.";
        }
    }
}

function authenticateADUser($username, $password, $ldapServer, $ldapDomain, $ldapBaseDn) {
    // Remove o domínio do username se estiver presente
    $username = str_replace('@' . $ldapDomain, '', $username);
    
    // Conecta ao servidor LDAP
    $ldapConn = ldap_connect($ldapServer);
    if (!$ldapConn) {
        error_log("Falha ao conectar ao servidor LDAP: " . $ldapServer);
        return false;
    }

    // Configura opções LDAP
    ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldapConn, LDAP_OPT_REFERRALS, 0);

    try {
        // Tenta fazer bind com as credenciais do usuário
        $userDn = $username . '@' . $ldapDomain;
        if (@ldap_bind($ldapConn, $userDn, $password)) {
            // Busca informações do usuário
            $filter = "(sAMAccountName=$username)";
            $result = ldap_search($ldapConn, $ldapBaseDn, $filter);
            
            if ($result) {
                $entries = ldap_get_entries($ldapConn, $result);
                if ($entries['count'] > 0) {
                    ldap_unbind($ldapConn);
                    return true;
                }
            }
        }
        
        ldap_unbind($ldapConn);
        return false;
    } catch (Exception $e) {
        error_log("Erro na autenticação LDAP: " . $e->getMessage());
        if (isset($ldapConn)) {
            ldap_unbind($ldapConn);
        }
        return false;
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
