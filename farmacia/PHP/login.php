<?php
session_start();
include 'config.php';

$erro = '';

// Configurações do LDAP
$ldapServer = "ldap://192.168.10.224";
$ldapDomain = "mmirim.local";
$ldapBaseDn = "dc=mmirim,dc=local";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $loginType = $_POST['login_type'] ?? 'local';
    
    try {
        if ($loginType === 'ldap') {
            // Autenticação LDAP
            $ldapResult = authenticateADUser($email, $senha);
            
            if ($ldapResult === true) {
                // Extrai o sAMAccountName do email
                $sAMAccountName = strstr($email, '@', true) ?: $email;
                
                // Verifica se o usuário LDAP já existe no banco usando o sAMAccountName
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
function authenticateADUser($username, $password) {
    global $ldapServer, $ldapDomain, $ldapBaseDn;

    // Se o usuário não digitou @, assume domínio interno
    if (strpos($username, '@') === false) {
        $userDn = $username . '@' . $ldapDomain;
        $sAMAccountName = $username;
    } else {
        $userDn = $username;
        $sAMAccountName = strstr($username, '@', true) ?: $username;
    }

    error_log("LDAP: Tentando autenticar usuário: $userDn");
    error_log("LDAP: sAMAccountName: $sAMAccountName");
    
    $ldapConn = ldap_connect($ldapServer);
    if (!$ldapConn) {
        error_log("LDAP: Falha ao conectar em $ldapServer");
        return false;
    }
    
    error_log("LDAP: Conexão estabelecida com $ldapServer");
    
    ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldapConn, LDAP_OPT_REFERRALS, 0);
    
    error_log("LDAP: Tentando bind com: $userDn");
    $ldapBind = @ldap_bind($ldapConn, $userDn, $password);
    
    if (!$ldapBind) {
        $ldapError = ldap_error($ldapConn);
        $ldapErrno = ldap_errno($ldapConn);
        error_log("LDAP: Falha no bind. Erro: $ldapError (Código: $ldapErrno)");
        ldap_unbind($ldapConn);
        return false;
    }
    
    error_log("LDAP: Bind realizado com sucesso");
    
    // Busca usuário no AD usando o sAMAccountName
    $searchFilter = "(sAMAccountName=$sAMAccountName)";
    error_log("LDAP: Buscando usuário com filter: $searchFilter");
    $search = ldap_search($ldapConn, $ldapBaseDn, $searchFilter, ["memberof", "displayName", "distinguishedName"]);
    if (!$search) {
        $ldapError = ldap_error($ldapConn);
        error_log("LDAP: Falha na busca. Erro: $ldapError");
        ldap_unbind($ldapConn);
        return false;
    }
    $entries = ldap_get_entries($ldapConn, $search);
    error_log("LDAP: Número de entradas encontradas: " . $entries['count']);
    if ($entries['count'] == 0) {
        error_log("LDAP: Usuário não encontrado no AD");
        ldap_unbind($ldapConn);
        return false;
    }
    error_log("LDAP: Usuário encontrado no AD");
    ldap_unbind($ldapConn);
    return true;
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
