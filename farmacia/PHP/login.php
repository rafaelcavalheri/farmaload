<?php
session_start();
include 'config.php';

$erro = '';

// Carregar configurações LDAP
$ldapConfig = null;
$configFile = __DIR__ . '/ldap_settings.php';
if (file_exists($configFile)) {
    $ldapConfig = include $configFile;
}

// Verificar se as configurações LDAP existem
if (!$ldapConfig) {
    throw new Exception("Configurações LDAP não encontradas. Por favor, configure o LDAP primeiro.");
}

// Configurações LDAP
$ldapServer = $ldapConfig['ldap_server'];
$ldapDomain = $ldapConfig['ldap_domain'];
$ldapBaseDn = $ldapConfig['ldap_base_dn'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $loginType = $_POST['login_type'] ?? 'local';
    
    try {
        if ($loginType === 'ldap') {
            // Autenticação LDAP
            error_log("Tentando autenticação LDAP para usuário: " . $email);
            $ldapResult = authenticateADUser($email, $senha, $ldapServer, $ldapDomain, $ldapBaseDn);
            
            if ($ldapResult === true) {
                error_log("Autenticação LDAP bem-sucedida para: " . $email);
                // Extrai o sAMAccountName do email
                $sAMAccountName = strstr($email, '@', true) ?: $email;
                
                // Verifica se o usuário LDAP já existe no banco usando o sAMAccountName
                $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email LIKE ? AND auth_type = 'ldap'");
                $stmt->execute([$sAMAccountName . '%']);
                $usuario = $stmt->fetch();

                if ($usuario) {
                    error_log("Usuário encontrado no banco: " . $usuario['email']);
                    $_SESSION['usuario'] = [
                        'id'      => $usuario['id'],
                        'nome'    => $usuario['nome'],
                        'email'   => $usuario['email'],
                        'perfil'  => $usuario['perfil']
                    ];
                    header('Location: index.php');
                    exit();
                } else {
                    error_log("Usuário LDAP não encontrado no banco: " . $sAMAccountName);
                    $erro = "Usuário LDAP não cadastrado no sistema. Entre em contato com o administrador.";
                }
            } else {
                error_log("Falha na autenticação LDAP para: " . $email);
                $erro = "Falha na autenticação LDAP. Verifique suas credenciais.";
            }
        } else {
            // Autenticação local
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
        $erro = "Erro no sistema. Tente novamente mais tarde.";
        error_log($e->getMessage());
    }
}

/**
 * Autentica usuário no Active Directory
 */
function authenticateADUser($username, $password, $ldapServer, $ldapDomain, $ldapBaseDn) {
    error_log("=== Iniciando autenticação LDAP ===");
    error_log("Usuário: " . $username);
    error_log("Servidor: " . $ldapServer);
    error_log("Domínio: " . $ldapDomain);
    error_log("Base DN: " . $ldapBaseDn);

    // Se o usuário não digitou @, assume domínio interno
    if (strpos($username, '@') === false) {
        $userDn = $username . '@' . $ldapDomain;
        $sAMAccountName = $username;
    } else {
        $userDn = $username;
        $sAMAccountName = strstr($username, '@', true) ?: $username;
    }

    error_log("UserDN: " . $userDn);
    error_log("sAMAccountName: " . $sAMAccountName);
    
    $ldapConn = ldap_connect($ldapServer);
    if (!$ldapConn) {
        error_log("Falha ao conectar em " . $ldapServer);
        return false;
    }
    
    error_log("Conexão estabelecida com " . $ldapServer);
    
    ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldapConn, LDAP_OPT_REFERRALS, 0);
    
    // Tentar autenticação direta com diferentes formatos
    $bindFormats = [
        $userDn,                                    // user@domain
        $sAMAccountName . '@' . $ldapDomain,        // user@domain
        strtoupper($ldapDomain) . '\\' . $sAMAccountName, // DOMAIN\user
        $sAMAccountName                             // just username
    ];
    
    foreach ($bindFormats as $format) {
        error_log("Tentando autenticar com: " . $format);
        if (@ldap_bind($ldapConn, $format, $password)) {
            error_log("Autenticação bem-sucedida com: " . $format);
            
            // Buscar informações do usuário
            $searchFilter = "(sAMAccountName=" . ldap_escape($sAMAccountName, "", LDAP_ESCAPE_FILTER) . ")";
            $search = @ldap_search($ldapConn, $ldapBaseDn, $searchFilter, [
                "cn", 
                "sAMAccountName", 
                "userPrincipalName", 
                "distinguishedName", 
                "displayName", 
                "userAccountControl",
                "memberOf"
            ]);
            
            if ($search) {
                $entries = ldap_get_entries($ldapConn, $search);
                if ($entries['count'] > 0) {
                    // Verificar status da conta
                    if (isset($entries[0]['useraccountcontrol'][0])) {
                        $uac = $entries[0]['useraccountcontrol'][0];
                        if (($uac & 2) || ($uac & 16)) { // Conta desativada ou bloqueada
                            error_log("Conta desativada ou bloqueada");
                            ldap_unbind($ldapConn);
                            return false;
                        }
                    }
                    
                    error_log("Usuário encontrado e autenticado com sucesso");
                    ldap_unbind($ldapConn);
                    return true;
                }
            }
        } else {
            $ldapError = ldap_error($ldapConn);
            $ldapErrno = ldap_errno($ldapConn);
            error_log("Falha na autenticação com " . $format . ". Erro: " . $ldapError . " (Código: " . $ldapErrno . ")");
        }
    }
    
    error_log("Todas as tentativas de autenticação falharam");
    ldap_unbind($ldapConn);
    return false;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Login - FarmAlto</title>
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
