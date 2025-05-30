<?php
require __DIR__ . '/config.php';
verificarAutenticacao(['admin']);

// Mensagens
$erro = $_GET['erro'] ?? null;
$sucesso = $_GET['sucesso'] ?? null;
$testResult = null;

// Configurações LDAP vazias
$ldapConfig = [
    'ldap_server' => '',
    'ldap_domain' => '',
    'ldap_base_dn' => ''
];

// Carregar configurações existentes apenas se o arquivo existir
$configFile = __DIR__ . '/ldap_settings.php';
if (file_exists($configFile)) {
    $savedConfig = include $configFile;
    if (is_array($savedConfig)) {
        $ldapConfig = $savedConfig;
    }
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ldapServer = trim($_POST['ldap_server'] ?? '');
    $ldapDomain = trim($_POST['ldap_domain'] ?? '');
    $ldapBaseDn = trim($_POST['ldap_base_dn'] ?? '');
    
    // Validar campos
    if (empty($ldapServer) || empty($ldapDomain) || empty($ldapBaseDn)) {
        $erro = "Todos os campos são obrigatórios.";
    } else {
        try {
            // Testar conexão LDAP
            $ldapConn = ldap_connect($ldapServer);
            if (!$ldapConn) {
                throw new Exception("Não foi possível conectar ao servidor LDAP.");
            }
            
            ldap_set_option($ldapConn, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($ldapConn, LDAP_OPT_REFERRALS, 0);
            
            // Tentar conexão anônima para verificar se o servidor está acessível
            if (!@ldap_bind($ldapConn)) {
                throw new Exception("Não foi possível conectar ao servidor LDAP. Verifique se o servidor está acessível.");
            }
            
            // Se for apenas um teste de conexão
            if (isset($_POST['test_connection'])) {
                $testResult = [
                    'success' => true,
                    'message' => "Conexão com o servidor LDAP estabelecida com sucesso!"
                ];
                ldap_unbind($ldapConn);
                $ldapConfig = [
                    'ldap_server' => $ldapServer,
                    'ldap_domain' => $ldapDomain,
                    'ldap_base_dn' => $ldapBaseDn
                ];
            } else {
                // Salvar configurações em um arquivo
                $config = [
                    'ldap_server' => $ldapServer,
                    'ldap_domain' => $ldapDomain,
                    'ldap_base_dn' => $ldapBaseDn
                ];
                
                $configFile = __DIR__ . '/ldap_settings.php';
                $configContent = "<?php\nreturn " . var_export($config, true) . ";\n";
                
                if (file_put_contents($configFile, $configContent)) {
                    $sucesso = "Configurações LDAP salvas com sucesso!";
                    $ldapConfig = $config;
                } else {
                    throw new Exception("Não foi possível salvar as configurações.");
                }
                
                ldap_unbind($ldapConn);
            }
            
        } catch (Exception $e) {
            if (isset($_POST['test_connection'])) {
                $testResult = [
                    'success' => false,
                    'message' => "Erro: " . $e->getMessage()
                ];
            } else {
                $erro = "Erro: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Configuração LDAP</title>
    <link rel="stylesheet" href="/css/style.css">
    <style>
        .test-result {
            margin-top: 1rem;
            padding: 1rem;
            border-radius: 4px;
        }
        .test-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .test-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .form-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<main class="container">
    <div class="page-header">
        <h1><i class="fas fa-server"></i> Configuração LDAP</h1>
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

    <?php if ($testResult): ?>
        <div class="test-result <?= $testResult['success'] ? 'test-success' : 'test-error' ?>">
            <?= sanitizar($testResult['message']) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h3>Configurações do Servidor LDAP</h3>
        <form method="POST" action="">
            <div class="form-group">
                <label for="ldap_server">Servidor LDAP:</label>
                <input type="text" id="ldap_server" name="ldap_server" 
                       value="<?= htmlspecialchars($ldapConfig['ldap_server']) ?>" 
                       placeholder="ldap://servidor:389" required>
                <small class="text-muted">Exemplo: ldap://servidor:389 ou ldap://192.168.1.100</small>
            </div>

            <div class="form-group">
                <label for="ldap_domain">Domínio:</label>
                <input type="text" id="ldap_domain" name="ldap_domain" 
                       value="<?= htmlspecialchars($ldapConfig['ldap_domain']) ?>" 
                       placeholder="dominio.local" required>
                <small class="text-muted">Exemplo: empresa.local ou dominio.com.br</small>
            </div>

            <div class="form-group">
                <label for="ldap_base_dn">Base DN:</label>
                <input type="text" id="ldap_base_dn" name="ldap_base_dn" 
                       value="<?= htmlspecialchars($ldapConfig['ldap_base_dn']) ?>" 
                       placeholder="dc=dominio,dc=local" required>
                <small class="text-muted">Exemplo: dc=empresa,dc=local ou dc=dominio,dc=com,dc=br</small>
            </div>

            <div class="form-actions">
                <button type="submit" name="test_connection" class="btn-secondary">
                    <i class="fas fa-plug"></i> Testar Conexão
                </button>
                <button type="submit" class="btn-primary">Salvar Configurações</button>
            </div>
        </form>
    </div>
</main>
</body>
</html> 