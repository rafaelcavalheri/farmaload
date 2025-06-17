# Sistema de Farmácia - Farmaload

Sistema de gerenciamento de farmácia com suporte a autenticação JWT e integração LDAP.

## Requisitos

- PHP 8.2 ou superior
- MySQL 8.0 ou superior
- Apache 2.4 ou superior
- Composer
- Docker (opcional)

## Instalação

### Usando Docker (Recomendado)

1. Clone o repositório
2. Configure as variáveis de ambiente no arquivo `.env`:
   ```
   DB_HOST=db
   DB_NAME=farmacia
   DB_USER=admin
   DB_PASSWORD=sua_senha
   JWT_SECRET_KEY=sua_chave_jwt
   ```
3. Execute o comando:
   ```bash
   docker-compose up -d
   ```

### Instalação Manual

1. Clone o repositório
2. Configure o banco de dados MySQL
3. Importe o arquivo `DOCKER-FILES/init.sql`
4. Configure o Apache para apontar para o diretório `PHP`
5. Instale as dependências:
   ```bash
   composer install
   ```

## Estrutura do Projeto

```
farmacia/
├── PHP/                  # Código fonte PHP
├── CSS/                  # Arquivos de estilo
├── images/              # Imagens do sistema
├── LOG/                 # Logs do sistema
├── CONFIG/              # Arquivos de configuração
└── DOCKER-FILES/        # Arquivos Docker
```

## Autenticação

O sistema suporta dois métodos de autenticação:

1. **Local**: Autenticação usando banco de dados MySQL
2. **LDAP**: Autenticação usando servidor LDAP

### JWT (JSON Web Token)

O sistema utiliza JWT para autenticação segura:

- Tokens são gerados no login
- Validade de 1 hora por padrão
- Renovação automática em uso
- Armazenamento seguro no cliente

## API Endpoints

### Autenticação

- `POST /login.php`
  - Autenticação de usuários
  - Suporte a LDAP e autenticação local
  - Retorna token JWT

### Medicamentos

- `GET /medicamentos_api.php`
  - Lista todos os medicamentos
  - Requer autenticação JWT

- `PUT /medicamentos_api.php`
  - Atualiza medicamento
  - Requer autenticação JWT

### Importação

- `POST /importar.php`
  - Importa dados via CSV
  - Requer autenticação JWT

## Segurança

- Autenticação JWT
- Proteção contra SQL Injection
- Validação de entrada
- Logs de auditoria
- CORS configurado
- Headers de segurança

## Logs

Os logs são armazenados em:
- `/LOG/` - Logs gerais
- `/logs/` - Logs de erro PHP

## Configuração

### Variáveis de Ambiente

- `DB_HOST`: Host do banco de dados
- `DB_NAME`: Nome do banco de dados
- `DB_USER`: Usuário do banco de dados
- `DB_PASSWORD`: Senha do banco de dados
- `JWT_SECRET_KEY`: Chave secreta para JWT
- `LDAP_HOST`: Host do servidor LDAP (opcional)
- `LDAP_PORT`: Porta do servidor LDAP (opcional)
- `LDAP_BASE_DN`: Base DN do LDAP (opcional)

## Desenvolvimento

### Docker

Para desenvolvimento local usando Docker:

```bash
# Construir imagens
docker-compose build

# Iniciar containers
docker-compose up -d

# Ver logs
docker-compose logs -f

# Parar containers
docker-compose down
```

### Banco de Dados

O arquivo `DOCKER-FILES/init.sql` contém a estrutura inicial do banco de dados.

## Contribuição

1. Faça um fork do projeto
2. Crie uma branch para sua feature (`git checkout -b feature/nova-feature`)
3. Commit suas mudanças (`git commit -am 'Adiciona nova feature'`)
4. Push para a branch (`git push origin feature/nova-feature`)
5. Crie um Pull Request

## Licença

Este projeto é proprietário e confidencial. 