# Documentação FARMALOAD

## 📚 Índice da Documentação

### 📋 Documentação Principal
- **[README Principal](../README.md)** - Apresentação

### 🔧 Documentação Técnica
- **[Manutenção de Lotes](README_MANUTENCAO_LOTES.md)** - Sistema de manutenção automática de lotes

### 📁 Estrutura do Projeto
```
farmaload/
├── README.md                    # Histórico de versões (raiz)
├── README/                      # Documentação técnica
│   ├── README.md               # Este arquivo (índice)
│   └── README_MANUTENCAO_LOTES.md
├── farmacia/                   # Código fonte da aplicação
│   ├── PHP/                   # Scripts PHP
│   ├── DOCKER-FILES/          # Configurações Docker
│   ├── CONFIG/                # Configurações do sistema
│   └── ...
├── backup_farmacia_*/         # Backups do banco de dados
├── atualizar.bat              # Script de atualização Windows
├── atualizar.sh               # Script de atualização Linux
└── .gitignore                 # Configurações Git
```

## 🚀 Início Rápido

### Para Desenvolvedores
1. Leia o **[README Principal](../README.md)**
2. Consulte a documentação específica conforme necessário

### Para Administradores
1. **[Manutenção de Lotes](README_MANUTENCAO_LOTES.md)** - Sistema de limpeza automática
2. Configurações Docker em `farmacia/DOCKER-FILES/`

### Para Usuários
1. Acesse o sistema via navegador
2. Consulte o administrador para dúvidas técnicas

## 📖 Documentação por Categoria

### 🔧 Manutenção e Operação
- [Sistema de Manutenção de Lotes](README_MANUTENCAO_LOTES.md)
  - Limpeza automática de lotes antigos
  - Configuração de cron jobs
  - Monitoramento e logs

### 🐳 Docker e Deploy
- Configurações em `farmacia/DOCKER-FILES/`
- Scripts de atualização em `atualizar.bat` e `atualizar.sh`

### 📊 Banco de Dados
- Estrutura definida em `farmacia/DOCKER-FILES/init.sql`
- Backups em `backup_farmacia_*/`

## 🔍 Como Contribuir

1. **Documentação:** Adicione novos arquivos nesta pasta `README/`
2. **Versões:** Atualize o `../README.md` com novas versões
3. **Código:** Documente mudanças significativas

## 📞 Suporte

Para dúvidas sobre:
- **Funcionalidades:** Consulte o administrador do sistema
- **Documentação:** Verifique os arquivos nesta pasta
- **Versões:** Consulte o README principal na raiz

---