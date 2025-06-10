# Farmaload - Notas da Versão

**Versão:** v.1.2025.1006.0850
**Data:** 10/06/2025

---

# Histórico de versões

## v.1.2025.1006.0850 (10/06/2025)
### Melhorias de Visualização
- Adicionado botão "Ver mais" no campo observações da página de relatórios para evitar que textos longos estourem o tamanho da linha, 
melhorando a experiência visual e a organização da tabela.

## v.1.2025.0906.1500 (09/06/2025)
### Data de renovação por medicamentos
- Data de renovação visível por medicamento na pagina detalhes do paciente
### Observações
- Campo observações agora disponível em detalhes do paciente e ao dispensar pela pagina pacientes
### Acesso à Página de Relatórios
- Expandido o acesso à página de relatórios para incluir o perfil de operador
- Atualizado o menu de navegação para mostrar o link de relatórios para todos os usuários
- Mantida a restrição de outras funcionalidades administrativas apenas para administradores

## v.1.2025.0506.1700 (05/06/2025)
### Correções
- Correção de bugs no sistema

## v.1.2025.0506.1549 (05/06/2025)
### Implementação de Backup e Restauração
- Adicionados novos arquivos para gerenciamento de backup:
  - `gerar_backup.php`: Implementação do sistema de backup seletivo
  - `restaurar_backup.php`: Sistema de restauração de backups
- Melhorias no sistema de backup:
  - Otimização do processo de backup
  - Melhor gerenciamento de arquivos grandes
  - Correção de problemas com arquivos de log
### Melhorias na Página de Relatórios
- Atualização da interface de relatórios
- Otimização da geração de relatórios
- Melhorias na visualização dos dados

## v.1.2025.0506.1020 (05/06/2025)
### Padronização do Favicon
- Implementada padronização do favicon em todas as páginas do sistema:
  - Adicionado favicon em páginas que não possuíam
  - Corrigido favicon em páginas que usavam formato incorreto
  - Todas as páginas agora utilizam o arquivo `fav.png` da pasta images
- Páginas atualizadas:
  - login.php
  - medicos.php
  - medicamentos.php
  - editar_medico.php
  - pacientes.php (corrigido de fav.ico para fav.png)
  - dispensar.php
  - usuarios.php
  - relatorios.php
  - gerenciar_dados.php

## v.1.2025.0506.0810 (05/06/2025)
### Gerenciamento de Dados
- Implementado sistema de backup seletivo com opções para:
  - Backup completo
  - Apenas relatórios (inclui dados de estoque)
  - Apenas pacientes
  - Apenas medicamentos
  - Backup personalizado (seleção de tabelas específicas)
- Melhorias na interface de backup:
  - Adicionada caixa de informação sobre integridade dos dados
  - Implementada seleção de tabelas específicas para backup personalizado
  - Corrigido comportamento do modal de carregamento durante download
- Garantia de integridade dos dados em backups parciais:
  - Backup de relatórios inclui automaticamente tabelas relacionadas ao estoque
  - Mantém consistência entre transações e quantidades de medicamentos

## v.1.2025.0406.1935 (04/06/2025)
 Melhora na geração automatica de CPF na importação, 
 agora o CPF gerado começa com 000 para fácil identificação de CPF genérico

## v.1.2025.0406.1450 (04/06/2025)
 - Edição de medicamento por lote

## v.1.2025.0406.1430 (04/06/2025)
 - Correção de geração automatica de CPF em importação

## v.1.2025.0406.1040 (04/06/2025)
 - Correção da quantidade dos medicamentos por movimentações
 - Correção da data de renovação por medicamento

## v.1.2025.0406.0900 (04/06/2025)
 - Remoção da coluna Prox renovação da pagina pacientes
   agora a prox renovação aparece ao clicar em versão
   Correção de bug na pagina editar pacientes

## v.1.2025.0406.0820 (04/06/2025)
 - Visualização de quantidade por lote na pagina medicamentos

## v.1.2025.0406.0744 (04/06/2025)
 - Correção de bugs

## v.1.2025.0306.1008 (03/06/2025)
 - Melhora na visualização de medicamentos por lote

## v.1.2025.0306.0900 (03/06/2025)
 - Correção na importação de medicamentos por lote

## v.1.2025.0206.1450 (02/06/2025)
### Página de Medicamentos
- Adicionada ordenação clicável em todas as colunas da tabela de medicamentos.
- Implementada ordenação especial para a coluna "Total Recebido" (ordenada via PHP).
- Restaurada a exibição da data da última importação no cabeçalho da coluna "Total Recebido".

## v.1.2025.0206.1155 (02/06/2025)
### Principais melhorias e correções desta versão

#### 1. Ordenação de Colunas
- Implementada ordenação clicável em todas as colunas das tabelas de pacientes (Gerenciamento, Busca e Dispensação).
- O usuário pode clicar no cabeçalho para alternar entre ordem crescente e decrescente.
- Indicadores visuais (setas) mostram a direção da ordenação.

#### 2. Coluna "Última Coleta"
- Adicionada a coluna "Última Coleta" nas tabelas de pacientes, exibindo a data/hora da última retirada de medicamento.

#### 3. Alinhamento e Visualização das Tabelas
- Corrigido o alinhamento dos cabeçalhos e dados das tabelas de pacientes.
- Garantido que o cabeçalho "Ações" fique exatamente acima dos botões de ação.
- Corrigido o desalinhamento das colunas "Última Coleta" e "Status".

#### 4. Dispensação de Medicamentos
- Corrigido o envio do ID correto (relação paciente-medicamento) ao dispensar medicamentos.
- Resolvido o erro "Medicamento não encontrado ou não vinculado ao paciente".

#### 5. Outras Melhorias
- Melhorias visuais e responsividade das tabelas.
- Manutenção dos parâmetros de busca e ordenação durante a navegação.

#### 6. Configuração de Servidor LDAP
- Adicionada página para configuração dos parâmetros do servidor LDAP, permitindo integração e autenticação centralizada.

---
**Equipe responsável:** Rafael Cavalheri
