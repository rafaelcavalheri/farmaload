<?php
require __DIR__ . '/config.php';
verificarAutenticacao(['admin']);

// Mensagens
$erro = $_GET['erro'] ?? null;
$sucesso = $_GET['sucesso'] ?? null;

// Lista de usuários
try {
    $stmt = $pdo->query("SELECT * FROM usuarios ORDER BY nome");
    $usuarios = $stmt->fetchAll();
} catch (PDOException $e) {
    $erro = "Erro ao carregar usuários: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Gerenciar Usuários</title>
    <link rel="icon" type="image/png" href="/images/fav.png">
    <link rel="stylesheet" href="/css/style.css">
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: #fff;
            margin: 15% auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            position: relative;
        }
        .close {
            position: absolute;
            right: 20px;
            top: 10px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover {
            color: var(--danger-color);
        }
        .btn-reset {
            background-color: var(--primary-color);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            margin-right: 5px;
            display: inline-block;
        }
        .btn-reset:hover {
            background-color: var(--primary-dark);
        }
        .btn-delete {
            background-color: var(--danger-color);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            display: inline-block;
        }
        .btn-delete:hover {
            background-color: #c0392b;
        }
        .text-muted {
            color: var(--secondary-color);
        }
        .action-buttons {
            display: flex;
            gap: 4px;
            justify-content: flex-start;
            flex-wrap: nowrap;
            width: 100%;
        }
        .btn-secondary {
            background-color: var(--secondary-color, #6c757d);
            color: #fff;
            border: none;
            border-radius: 4px;
            padding: 4px 8px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.95em;
            transition: background 0.2s;
        }
        .btn-secondary:hover {
            background-color: var(--primary-color, #007bff);
            color: #fff;
        }
        .text-muted {
            color: var(--secondary-color, #888);
        }
    </style>
    <script>
    function toggleSenhaField() {
        const authType = document.getElementById('auth_type').value;
        const senhaGroup = document.getElementById('senhaGroup');
        const senhaInput = document.getElementById('senha');
        
        if (authType === 'ldap') {
            senhaGroup.style.display = 'none';
            senhaInput.required = false;
            senhaInput.value = ''; // Limpa o campo
        } else {
            senhaGroup.style.display = 'block';
            senhaInput.required = true;
        }
    }

    function validarFormulario() {
        const authType = document.getElementById('auth_type').value;
        const senha = document.getElementById('senha').value;
        
        if (authType === 'local' && senha.length < 6) {
            alert('A senha deve ter no mínimo 6 caracteres para usuários locais.');
            return false;
        }
        
        return true;
    }

    // Inicializar o estado do campo de senha
    document.addEventListener('DOMContentLoaded', function() {
        toggleSenhaField();
    });

    function confirmarExclusao() {
        return confirm('Tem certeza que deseja excluir este usuário?');
    }

    function abrirModalReset(id) {
        document.getElementById('idUsuarioReset').value = id;
        document.getElementById('modalResetSenha').style.display = 'block';
        document.getElementById('nova_senha').value = '';
        document.getElementById('confirmar_senha').value = '';
    }

    function fecharModal() {
        document.getElementById('modalResetSenha').style.display = 'none';
    }

    // Fechar modal ao clicar fora
    window.onclick = function(event) {
        const modal = document.getElementById('modalResetSenha');
        if (event.target == modal) fecharModal();
    }

    function abrirModalPerfil(id, perfilAtual) {
        document.getElementById('idUsuarioPerfil').value = id;
        document.getElementById('novo_perfil').value = perfilAtual;
        document.getElementById('modalAlterarPerfil').style.display = 'block';
    }

    function fecharModalPerfil() {
        document.getElementById('modalAlterarPerfil').style.display = 'none';
    }

    function abrirModalEditarUsuario(usuario) {
        usuario = typeof usuario === 'string' ? JSON.parse(usuario) : usuario;
        document.getElementById('edit_id').value = usuario.id;
        document.getElementById('edit_nome').value = usuario.nome;
        document.getElementById('edit_email').value = usuario.email;
        document.getElementById('edit_auth_type').value = usuario.auth_type;
        document.getElementById('edit_perfil').value = usuario.perfil;
        document.getElementById('modalEditarUsuario').style.display = 'block';
    }

    function fecharModalEditarUsuario() {
        document.getElementById('modalEditarUsuario').style.display = 'none';
    }
    </script>
</head>
<body>
<?php include 'header.php'; ?>

<main class="container">
    <div class="page-header">
        <h1><i class="fas fa-users"></i> Usuários</h1>
        <div class="actions">
            <a href="ldap_config.php" class="btn-secondary">
                <i class="fas fa-server"></i> Configurar LDAP
            </a>
            <a href="#" onclick="document.getElementById('formNovoUsuario').style.display = 'block'" class="btn-secondary">
                <i class="fas fa-user-plus"></i> Novo Usuário
            </a>
        </div>
    </div>

    <h2>Gerenciamento de Usuários</h2>

    <?php if ($sucesso): ?>
        <div class="sucesso"><?= sanitizar($sucesso) ?></div>
    <?php endif; ?>

    <?php if ($erro): ?>
        <div class="erro"><?= sanitizar($erro) ?></div>
    <?php endif; ?>

    <!-- Formulário de criação -->
    <div id="formNovoUsuario" class="card" style="display: none;">
        <h3>Criar Novo Usuário</h3>
        <form method="POST" action="processar_usuario.php" onsubmit="return validarFormulario()">
            <div class="form-group">
                <label for="nome">Nome:</label>
                <input type="text" id="nome" name="nome" required>
            </div>

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="auth_type">Tipo de Autenticação:</label>
                <select id="auth_type" name="auth_type" required onchange="toggleSenhaField()">
                    <option value="local" selected>Local</option>
                    <option value="ldap">LDAP</option>
                </select>
            </div>

            <div id="senhaGroup" class="form-group">
                <label for="senha">Senha:</label>
                <input type="password" id="senha" name="senha" minlength="6" 
                       title="Mínimo de 6 caracteres para usuários locais">
                <small class="text-muted">Apenas para usuários locais</small>
            </div>

            <div class="form-group">
                <label for="perfil">Perfil:</label>
                <select id="perfil" name="perfil" required>
                    <option value="operador" selected>Operador</option>
                    <option value="admin">Administrador</option>
                </select>
            </div>

            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="document.getElementById('formNovoUsuario').style.display = 'none'">Cancelar</button>
                <button type="submit" name="acao" value="criar" class="btn-primary">Criar Usuário</button>
            </div>
        </form>
    </div>

    <!-- Tabela de usuários -->
    <div class="card">
        <h3>Usuários Cadastrados</h3>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Perfil</th>
                    <th>Autenticação</th>
                    <th>Data Cadastro</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $usuario): ?>
                <tr<?= !$usuario['ativo'] ? ' style="background:#eee;color:#888;"' : '' ?>>
                    <td><?= $usuario['id'] ?></td>
                    <td><?= sanitizar($usuario['nome']) ?></td>
                    <td><?= sanitizar($usuario['email']) ?></td>
                    <td><?= $usuario['perfil'] == 'admin' ? 'Administrador' : 'Operador' ?></td>
                    <td><?= $usuario['auth_type'] == 'ldap' ? 'LDAP' : 'Local' ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($usuario['data_cadastro'])) ?></td>
                    <td class="actions">
                        <div class="action-buttons">
                        <?php if ($usuario['id'] != $_SESSION['usuario']['id']): ?>
                            <?php if ($usuario['ativo']): ?>
                                <?php if ($usuario['auth_type'] === 'local'): ?>
                                    <a href="#" onclick="abrirModalReset(<?= $usuario['id'] ?>)" class="btn-secondary" title="Resetar Senha"><i class="fas fa-key"></i></a>
                                <?php endif; ?>
                                <a href="processar_usuario.php?acao=desativar&id=<?= $usuario['id'] ?>" class="btn-secondary" title="Desativar" onclick="return confirm('Tem certeza que deseja desativar este usuário?')"><i class="fas fa-power-off"></i></a>
                                <a href="#" onclick="abrirModalEditarUsuario(<?= htmlspecialchars(json_encode($usuario), ENT_QUOTES, 'UTF-8') ?>)" class="btn-secondary" title="Editar"><i class="fas fa-edit"></i></a>
                            <?php else: ?>
                                <a href="processar_usuario.php?acao=ativar&id=<?= $usuario['id'] ?>" class="btn-secondary" title="Ativar" onclick="return confirm('Deseja ativar este usuário?')"><i class="fas fa-power-off"></i></a>
                                <a href="#" onclick="abrirModalEditarUsuario(<?= htmlspecialchars(json_encode($usuario), ENT_QUOTES, 'UTF-8') ?>)" class="btn-secondary" title="Editar"><i class="fas fa-edit"></i></a>
                                <span class="text-muted">Desativado</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">Seu usuário</span>
                        <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Modal de reset -->
    <div id="modalResetSenha" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModal()">&times;</span>
            <h3>Resetar Senha</h3>
            <form method="POST" action="processar_usuario.php">
                <input type="hidden" name="id" id="idUsuarioReset">
                <div class="form-group">
                    <label for="nova_senha">Nova Senha:</label>
                    <input type="password" name="nova_senha" id="nova_senha" required minlength="6">
                </div>
                <div class="form-group">
                    <label for="confirmar_senha">Confirmar Senha:</label>
                    <input type="password" name="confirmar_senha" id="confirmar_senha" required minlength="6">
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="fecharModal()">Cancelar</button>
                    <button type="submit" name="acao" value="resetar" class="btn-secondary">Salvar Nova Senha</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de alterar perfil -->
    <div id="modalAlterarPerfil" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModalPerfil()">&times;</span>
            <h3>Alterar Perfil do Usuário</h3>
            <form method="POST" action="processar_usuario.php">
                <input type="hidden" name="id" id="idUsuarioPerfil">
                <div class="form-group">
                    <label for="novo_perfil">Novo Perfil:</label>
                    <select name="novo_perfil" id="novo_perfil" required>
                        <option value="operador">Operador</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="fecharModalPerfil()">Cancelar</button>
                    <button type="submit" name="acao" value="alterar_perfil" class="btn-secondary">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de editar usuário -->
    <div id="modalEditarUsuario" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModalEditarUsuario()">&times;</span>
            <h3>Editar Usuário</h3>
            <form method="POST" action="processar_usuario.php">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label for="edit_nome">Nome:</label>
                    <input type="text" id="edit_nome" name="nome" required>
                </div>
                <div class="form-group">
                    <label for="edit_email">Email:</label>
                    <input type="email" id="edit_email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="edit_auth_type">Tipo de Autenticação:</label>
                    <select id="edit_auth_type" name="auth_type" required>
                        <option value="local">Local</option>
                        <option value="ldap">LDAP</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_perfil">Perfil:</label>
                    <select id="edit_perfil" name="perfil" required>
                        <option value="operador">Operador</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="fecharModalEditarUsuario()">Cancelar</button>
                    <button type="submit" name="acao" value="editar" class="btn-secondary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</main>
</body>
</html>
