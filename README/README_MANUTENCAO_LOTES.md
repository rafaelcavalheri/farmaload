# Sistema de Manutenção de Lotes - FARMALOAD

## 📋 Visão Geral

O sistema de manutenção de lotes foi implementado para manter o banco de dados otimizado, removendo lotes antigos zerados que não são mais necessários para auditoria.

## ⚙️ Configurações

### Parâmetros de Limpeza
- **Manter lotes por:** 730 dias (2 anos)
- **Manter vencidos por:** 365 dias (1 ano após vencimento)
- **Limite por execução:** 100 lotes
- **Frequência:** Mensal (1º dia do mês)

### Cron Jobs Configurados
```bash
# Relatório mensal - 1º dia do mês às 2h
0 2 1 * * www-data /usr/bin/php /var/www/html/manutencao_lotes.php relatorio

# Limpeza automática - 1º dia do mês às 3h
0 3 1 * * www-data /usr/bin/php /var/www/html/manutencao_lotes.php executar confirmar
```

## 🚀 Como Funciona

### 1. **Identificação de Lotes Candidatos**
O sistema identifica lotes que atendem aos critérios:
- Quantidade = 0 (lote zerado)
- Última atualização há mais de 2 anos OU
- Validade vencida há mais de 1 ano

### 2. **Backup Automático**
Antes de qualquer remoção, o sistema:
- Cria tabela de backup: `lotes_backup_YYYYMMDD_HHMMSS`
- Preserva todos os dados para recuperação

### 3. **Limpeza Controlada**
- Remove apenas lotes que atendem aos critérios
- Limite de 100 lotes por execução (segurança)
- Logs detalhados de todas as operações

## 📊 Estrutura do Banco de Dados

### Tabela: `lotes_medicamentos`
```sql
CREATE TABLE lotes_medicamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medicamento_id INT NOT NULL,
    lote VARCHAR(50) NOT NULL,
    quantidade INT NOT NULL DEFAULT 0,
    validade DATE NOT NULL,
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (medicamento_id) REFERENCES medicamentos(id) ON DELETE CASCADE,
    UNIQUE KEY unq_medicamento_lote (medicamento_id, lote)
);
```

## 📊 Relatórios

### Relatório Mensal
- Lista todos os lotes candidatos à limpeza
- Estatísticas de quantidade e idade
- Configurações atuais do sistema

### Logs de Execução
- Arquivo: `/var/log/manutencao_lotes.log`
- Registra todas as operações
- Inclui erros e sucessos

## 🛠️ Uso Manual

### Via Linha de Comando
```bash
# Gerar relatório
php manutencao_lotes.php relatorio

# Executar limpeza
php manutencao_lotes.php executar confirmar
```

### Via Web
```
# Relatório
http://localhost/manutencao_lotes.php?acao=relatorio

# Limpeza
http://localhost/manutencao_lotes.php?acao=executar&confirmar=1
```

## 🔍 Monitoramento

### Verificar Logs
```bash
# Ver logs em tempo real
tail -f /var/log/manutencao_lotes.log

# Ver últimas 50 linhas
tail -n 50 /var/log/manutencao_lotes.log
```

### Verificar Cron Jobs
```bash
# Listar cron jobs ativos
crontab -l

# Verificar status do serviço cron
service cron status
```

### Verificar Lotes no Banco
```sql
-- Ver todos os lotes
SELECT * FROM lotes_medicamentos;

-- Ver lotes zerados
SELECT * FROM lotes_medicamentos WHERE quantidade = 0;

-- Ver lotes vencidos
SELECT * FROM lotes_medicamentos WHERE validade < CURDATE();
```

## 🚨 Segurança

### Medidas Implementadas
- ✅ Backup automático antes da limpeza
- ✅ Confirmação obrigatória para execução
- ✅ Limite de lotes por execução
- ✅ Logs detalhados de todas as operações
- ✅ Execução como usuário www-data (sem privilégios root)

### Recuperação de Dados
Se necessário, os dados podem ser recuperados das tabelas de backup:
```sql
-- Restaurar lotes removidos
INSERT INTO lotes_medicamentos SELECT * FROM lotes_backup_YYYYMMDD_HHMMSS;
```

## 📧 Notificações

### Email de Relatório (Configurável)
- Envio automático de relatórios mensais
- Configuração no arquivo `manutencao_lotes.php`
- Destinatário: `admin@farmacia.com`

## 🔧 Manutenção do Sistema

### Atualizar Configurações
Editar o arquivo `PHP/manutencao_lotes.php`:
```php
$config = [
    'manter_lotes_por_dias' => 730, // Alterar período
    'manter_vencidos_por_dias' => 365, // Alterar período
    'limite_lotes_por_execucao' => 100, // Alterar limite
    'enviar_email_relatorio' => true,
    'email_destinatario' => 'admin@farmacia.com'
];
```

### Alterar Frequência
Editar o Dockerfile ou crontab:
```bash
# Exemplo: Executar semanalmente
0 2 * * 0 www-data /usr/bin/php /var/www/html/manutencao_lotes.php relatorio
```

## 🐛 Troubleshooting

### Problemas Comuns

**Erro: "Table 'lotes' doesn't exist"**
- ✅ **Solução:** O script foi corrigido para usar `lotes_medicamentos`

**Erro: "Invalid table name"**
- ✅ **Solução:** Nome da tabela de backup corrigido para formato válido

**Erro: "Column not found"**
- ✅ **Solução:** Colunas corrigidas para usar `data_atualizacao` e `lote`

### Verificar Status
```bash
# Testar conexão com banco
docker exec farmacia-web php -r "require_once 'config.php'; echo 'Conexão OK';"

# Verificar tabelas
docker exec farmacia-web php -r "require_once 'config.php'; \$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASS); \$stmt = \$pdo->query('SHOW TABLES'); while(\$row = \$stmt->fetch()) { echo \$row[0] . PHP_EOL; }"
```

## 📈 Benefícios

1. **Performance:** Banco de dados mais rápido
2. **Espaço:** Economia de espaço em disco
3. **Manutenção:** Processo automatizado
4. **Segurança:** Backup automático
5. **Auditoria:** Logs completos de todas as operações

## ⚠️ Importante

- O sistema mantém rastreabilidade completa por 2 anos
- Lotes vencidos são mantidos por 1 ano após vencimento
- Backup automático garante recuperação se necessário
- Processo totalmente automatizado e seguro
- **Tabela correta:** `lotes_medicamentos` (não `lotes`)
- **Colunas corretas:** `lote`, `data_atualizacao`, `validade` 