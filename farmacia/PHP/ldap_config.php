<?php
require __DIR__ . '/config.php';
verificarAutenticacao(['admin']);

$erro = '';
$sucesso = '';

// Carregar configurações atuais
try {
    $stmt = $pdo->query("SELECT * FROM ldap_config ORDER BY id DESC LIMIT 1");
    $config = $stmt->fetch();
} catch (PDOException $e) {
    $erro = "Erro ao carregar configurações: " . $e->getMessage();
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ldapServer = trim($_POST['ldap_server'] ?? '');
    $ldapDomain = trim($_POST['ldap_domain'] ?? '');
    $ldapBaseDn = trim($_POST['ldap_base_dn'] ?? '');
    
    if (empty($ldapServer) || empty($ldapDomain) || empty($ldapBaseDn)) {
        $erro = "Todos os campos são obrigatórios.";
    } else {
        try {
            // Testar conexão LDAP antes de salvar
            $ldapConn = ldap_connect($ldapServer);
            if (!$ldapConn) {
                throw new Exception("Não foi possível conectar ao servidor LDAP.");
            }
            
            ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($ldapConn, LDAP_OPT_REFERRALS, 0);
            
            // Salvar configurações
            $stmt = $pdo->prepare("INSERT INTO ldap_config (ldap_server, ldap_domain, ldap_base_dn) VALUES (?, ?, ?)");
            $stmt->execute([$ldapServer, $ldapDomain, $ldapBaseDn]);
            
            $sucesso = "Configurações LDAP atualizadas com sucesso!";
            
            // Recarregar configurações
            $stmt = $pdo->query("SELECT * FROM ldap_config ORDER BY id DESC LIMIT 1");
            $config = $stmt->fetch();
            
        } catch (Exception $e) {
            $erro = "Erro ao salvar configurações: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Configurações LDAP</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<?php include 'header.php'; ?>

<main class="container">
    <div class="page-header">
        <h1><i class="fas fa-server"></i> Configurações LDAP</h1>
        <div class="actions">
            <a href="usuarios.php" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>
    </div>

    <?php if ($sucesso): ?>
        <div class="sucesso"><?= sanitizar($sucesso) ?></div>
    <?php endif; ?>

    <?php if ($erro): ?>
        <div class="erro"><?= sanitizar($erro) ?></div>
    <?php endif; ?>

    <div class="card">
        <h3>Configurações Atuais</h3>
        <form method="POST" class="form-config">
            <div class="form-group">
                <label for="ldap_server">Servidor LDAP:</label>
                <input type="text" id="ldap_server" name="ldap_server" 
                       value="<?= sanitizar($config['ldap_server'] ?? '') ?>" required
                       placeholder="Ex: ldap://servidor.local">
                <small class="text-muted">URL do servidor LDAP (ex: ldap://servidor.local)</small>
            </div>

            <div class="form-group">
                <label for="ldap_domain">Domínio:</label>
                <input type="text" id="ldap_domain" name="ldap_domain" 
                       value="<?= sanitizar($config['ldap_domain'] ?? '') ?>" required
                       placeholder="Ex: dominio.local">
                <small class="text-muted">Domínio do Active Directory (ex: dominio.local)</small>
            </div>

            <div class="form-group">
                <label for="ldap_base_dn">Base DN:</label>
                <input type="text" id="ldap_base_dn" name="ldap_base_dn" 
                       value="<?= sanitizar($config['ldap_base_dn'] ?? '') ?>" required
                       placeholder="Ex: dc=dominio,dc=local">
                <small class="text-muted">Base DN para busca (ex: dc=dominio,dc=local)</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-primary">Salvar Configurações</button>
            </div>
        </form>
    </div>
</main>

<style>
.form-config {
    max-width: 600px;
    margin: 0 auto;
}
.form-config .form-group {
    margin-bottom: 1.5rem;
}
.form-config input {
    width: 100%;
    padding: 8px;
    margin-top: 4px;
}
.form-config .text-muted {
    display: block;
    margin-top: 4px;
    font-size: 0.9em;
    color: #666;
}
</style>
</body>
</html> 