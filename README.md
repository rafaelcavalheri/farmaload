# FARMALOAD - Gerenciador de Farmacia Pública de Alto Custo


**Versão:** v.1.2025.1806.1700
**Data:** 18/06/2025

---

# Histórico de versões

## v.1.2025.1806.1700 (18/06/2025)

- Implementação de lista de observações na pagina dispensar

## v.1.2025.1806.0800 (18/06/2025)

### Implementação de Autenticação JWT
- Adicionada autenticação JWT para maior segurança
- Implementado sistema de tokens para API
- Integração com autenticação LDAP existente
- Proteção de endpoints da API
- Atualização do aplicativo Android para suporte JWT

### Melhorias de Segurança
- Implementação de headers de segurança
- Proteção contra SQL Injection
- Validação de entrada de dados
- Logs de auditoria
- Configuração CORS

### Correções e Melhorias

## v.1.2025.1606.1400 (16/06/2025)

- Implementação de API para aplicativos móveis

## v.1.2025.1306.1616 (13/06/2025)

- Correção no código dos medicamentos e na importação

### Correções e Melhorias

## v.1.2025.1306.1538 (13/06/2025)

- Correção na exportação e impressão de relatórios

### Correções e Melhorias

## v.1.2025.1306.1458 (13/06/2025)

- Correção de filtros de relatório na pagina relatórios
- Melhorias visuais na página relatórios

## v.1.2025.1306.1350 (13/06/2025)

### Correções e Melhorias

#### Correção no sistema de backup/restauração:
Agora o arquivo de backup não inclui colunas do tipo GENERATED, como crm_completo na tabela medicos, evitando erro 3105 ao restaurar o banco de dados.
- Correção na data de movimentação:
- Corrigido comportamento onde a data das movimentações era sobrescrita pela data da restauração do backup. Agora, a data original da transação é preservada corretamente.
- Correção de filtros de relatório na pagina relatórios
- Melhorias visuais na página relatórios

## v.1.2025.1206.1648 (12/06/2025)
### Correções e Melhorias
- Adicionado opção de extorno de medicamentos
- Melhorias na visualização de relatórios e filtros


## v.1.2025.1206.1500 (12/06/2025)
### Correções e Melhorias
- Alterado botao ver mais para editar observações em detalhes do paciente
- Correção ao ajustar estoque
- Visualização de quantidade disponivel dos medicamentos dos pacientes na pagina pacientes
- Correção de bugs

## v.1.2025.1206.1014 (12/06/2025)
### Correções e Melhorias
- Correção do erro ao cadastrar medicamento
- Implementação da funcionalidade de dispensar vários medicamentos simultaneamente
- Adicionado campo de observação no histórico na página de detalhes do paciente
- Melhorias na visualização de medicamentos no botão "Ver":
  - Exibição do nome do medicamento
  - Quantidade solicitada
  - CID
  - Data de renovação
- Implementação do cadastro de instituição com validação de 7 números no CNES
- Correção de exibição das datas de renovação

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

## v.1.2025.1906.1740 (19/06/2025)

### Correção Crítica na Importação de Pacientes e Medicamentos

- Corrigido bug na importação automática de planilhas (`modelo_importacao.xls`) que impedia a criação dos vínculos entre pacientes e medicamentos.
- Agora, ao importar, as associações entre pacientes e medicamentos são corretamente criadas na tabela `paciente_medicamentos`.
- Adicionado log de resumo ao final da importação, facilitando o diagnóstico de futuras importações.
- Criado script de limpeza de dados para facilitar testes e manutenção.

---
**Resumo técnico:**  
O problema estava na função `importarReliniFim` (`processar_importacao_automatica.php`), que não preenchia o array de associações. Isso impedia que os medicamentos fossem vinculados aos pacientes durante a importação. O código foi ajustado para garantir que as associações sejam criadas corretamente.

---

**Equipe responsável:** Rafael Cavalheri
