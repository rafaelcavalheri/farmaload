# 📋 Histórico de Versões - FARMALOAD

## 🆕 Versão: v.1.2025.2410.1200

### ✅ Correções e Melhorias Implementadas

#### 1. Relatório de Importação — inclusão do código do paciente
- Observações passam a exibir "Código: <CODIGO>, Paciente importado da linha X".
- Arquivos modificados:
  - `farmacia/php/processar_importacao_automatica.php` — função `registrarDetalhesImportacao`.

#### 2. Importação de pacientes — evitar mesclagem por nome quando há código
- Busca por paciente existente por nome só ocorre quando nenhum `codigo_paciente` é fornecido.
- Pacientes homônimos com códigos distintos são cadastrados separadamente.
- Arquivos modificados:
  - `farmacia/php/processar_importacao_automatica.php` — ajuste da lógica de identificação e fallback.

#### 3. Conversores RELINI — deduplicação por código confirmada
- Fluxos RELINI priorizam `codigo_paciente`; quando ausente, geram automaticamente e mantêm mapeamento por nome e código.

- Resultado: Relatório “Pacientes Importados” desambigua nomes duplicados e exibe códigos.
- Impacto: Maior rastreabilidade e consistência com a lista em `pacientes.php`.

#### 4. Correções de Dados — rollback e limpeza de lotes 'LOT%'


## 🆕 Versão: v.1.2025.2409.1200

### ✅ Correções e Melhorias Implementadas

#### 1. Adicionado campo Operador na pagina detalhes_paciente que mostra o responsável pela movimentação
em Histórico de Transações de Medicamentos.
- **Arquivos modificados**:
  - `farmacia/detalhes_paciente.php` - Mostra o responsável pela movimentação

#### 2. Otimização de Layout - Página Editar Paciente
- **Simplificação da estrutura HTML**: Removidos divs desnecessários na seção "Pessoas Autorizadas"
- **Otimização de espaçamentos**: Reduzidos paddings e margins em toda a página para melhor aproveitamento vertical
- **Melhorias visuais**: Removidos contornos azuis indesejados nos campos individuais de pessoas autorizadas
- **Arquivos modificados**:
  - `farmacia/editar_paciente.php` - Simplificação da estrutura HTML
  - `farmacia/css/editar_paciente.css` - Otimização de espaçamentos e estilos
- **Resultado**: Página mais compacta com menos necessidade de scroll, mantendo usabilidade e responsividade
- **Impacto**: Melhor experiência do usuário com visualização otimizada dos dados do paciente

#### 3. Implementação de Limite de Tempo para Extorno de Medicamentos
- **Nova funcionalidade**: Limite máximo de 3 dias para realizar extorno de medicamentos
- **Validação automática**: Sistema verifica automaticamente se o prazo foi ultrapassado
- **Mensagens informativas**: Alertas claros indicando o motivo do bloqueio e data da dispensação
- **Arquivos modificados**:
  - `farmacia/php/ajax_extornar.php` - Verificação de limite para extornos gerais
  - `farmacia/php/ajax_extornar_transacao.php` - Verificação de limite para extornos de transações específicas
- **Resultado**: Controle rigoroso de extornos com prazo definido de 3 dias
- **Impacto**: Maior segurança e controle nas operações de extorno, evitando extornos tardios

## 🆕 Versão: v.1.2025.0409.1200

### ✅ Correções e Melhorias Implementadas

#### 1. Observações Padrão na Dispensação (ajax_form_dispensar)
- Removidos textos informativos da seleção (dica e contador)
- CSS extraído para arquivo dedicado `farmacia/css/ajax_form_dispensar.css`
- Inclusão de `<link rel="stylesheet" href="css/ajax_form_dispensar.css">` no PHP
- Modal passa a abrir sempre limpo (sem seleções persistidas)
- Removida persistência em `sessionStorage` para evitar vazamento de seleções entre pacientes

- Arquivos modificados:
  - `farmacia/php/ajax_form_dispensar.php`
  - `farmacia/css/ajax_form_dispensar.css` (novo)

- Impacto: UX mais limpa; sem confusão por seleções anteriores; manutenção facilitada pelo CSS externo

---

## 🆕 Versão v.1.2025.0209.1200

### ✅ Correções e Melhorias Implementadas

#### 1. Correção no Sistema de Ajuste de Lotes
- **Problema Resolvido**: Diferenças negativas no relatório de ajuste de estoque agora são exibidas corretamente
- **Arquivo Modificado**: `ajuste_lote_api.php` - removido uso da função `abs()` na linha 132
- **Resultado**: Relatórios de ajuste de estoque mostram valores positivos e negativos com sinais corretos
- **Impacto**: Melhor rastreabilidade de aumentos e reduções de estoque

#### 2. Correção no Sistema de Edição de Medicamentos
- **Problema Resolvido**: Diferenças negativas eram exibidas como positivas nos relatórios
- **Arquivo Modificado**: `editar_medicamento.php` - removido uso da função `abs()` nas linhas 93 e 167
- **Resultado**: Movimentações de estoque mostram valores corretos com sinais apropriados

#### 3. Correção do Usuário Responsável nas Movimentações
- **Problema Resolvido**: Usuário responsável sempre aparecia como "Sistema" nas movimentações
- **Arquivo Modificado**: `editar_medicamento.php` - adicionado campo `usuario_id` nas inserções da tabela `movimentacoes`
- **Resultado**: Rastreabilidade correta das alterações de estoque por usuário
- **Impacto**: Melhor auditoria e controle de responsabilidades

---

#### 4. Implementação do Botão "+ Adicionar Novo Lote"
- **Nova Funcionalidade**: Botão para adicionar lotes diretamente na página de editar medicamento
- **Interface Dinâmica**: Adição de lotes sem recarregar a página usando AJAX
- **Validação Backend**: Verificação de lotes duplicados e validação de dados
- **Registro Automático**: Movimentações de estoque registradas automaticamente
- **Cálculo em Tempo Real**: Estoque total atualizado incluindo novos lotes
- **Estilo Padronizado**: Visual consistente com demais botões da interface

---

- **Atualização de apk para v.1.1.24

## 🆕 Versão v.1.2025.0109.1200

### ✅ Correções e Melhorias Implementadas

#### 1. Melhorias na Interface de Edição de Pacientes
- **Espaçamento Aprimorado**: Campos de dados pessoais com melhor organização visual
- **Estilização de Fieldsets**: Padding de 20px e cor de fundo clara para melhor legibilidade
- **Campos de Input**: Bordas arredondadas e efeitos de foco aprimorados
- **Margens Otimizadas**: Espaçamento adequado entre seções e labels
- **Botão "+ Medicamento"**: Reposicionado para o lado esquerdo da interface

#### 2. Estrutura de Banco de Dados Expandida

- **Sistema de Lotes**: Tabela `lotes_medicamentos` com controle automático de quantidade
- **Triggers Automáticos**: Atualização automática de quantidades baseada em lotes

#### 3. Organização de Arquivos CSS
- **Estilos de Medicamentos**: CSS específico para página de edição de medicamentos

---

## 🆕 Versão v.1.2025.0108.1600

### ✅ Correções e Melhorias Implementadas

#### 1. Restauração do Campo Observações do Medicamento
- Campo `observacoes` restaurado em todas as páginas de medicamentos
- Página Editar Paciente: Campo observações restaurado e funcionando
- Página Detalhes Paciente: Exibição de observações restaurada
- Página Cadastrar Paciente: Campo observações incluído no template
- Banco de Dados: Campo `observacoes` incluído em todas as queries INSERT/UPDATE

#### 2. Sistema de Modal para Observações
- Modal para visualizar observações completas de medicamentos
- Página Dispensar: Modal para ver observações completas
- Página Relatório: Modal para observações em relatórios
- Truncamento Inteligente: Texto cortado com "..." quando muito longo
- Tooltip: Informação completa no hover
- Responsividade: Modal adaptado para dispositivos móveis

#### 3. Remoção da Página Dispensar Independente
- Página dispensar.php removida
- CSS da página dispensar removido
- Menu atualizado (removido link dispensar)
- Funcionalidade preservada na página pacientes

#### 4. Centralização da Edição de Observações
- Edição centralizada apenas na página editar paciente
- Página Detalhes: Removida opção de editar observações
- Página Editar: Mantida funcionalidade completa de edição
- AJAX removido da página detalhes
- Interface limpa para visualização

#### 5. Correção da Página Cadastrar Paciente
- Layout unificado com página editar paciente
- CSS consistente e responsivo
- JavaScript igual para adicionar/remover medicamentos
- Campo observações incluído no template
- Validações padronizadas

---

## 🆕 Versão v.1.2025.3107.1600

### ✅ Implementação do Código do Paciente no Sistema

#### Funcionalidades Implementadas:
- **Campo Código do Paciente**: Adicionado `codigo_paciente VARCHAR(50)` nas tabelas
- **Sistema de Importação Atualizado**: Adaptação para nova estrutura de planilhas
- **Páginas Atualizadas**: Lista, detalhes e edição de pacientes
- **Busca Expandida**: Busca por código do paciente incluída
- **Migração de Dados**: Script para bancos de produção

---

## 🆕 Versão v.1.2025.1707.1600

### ✅ Sistema de Download Direto do APK

#### Funcionalidades:
- **Download Direto**: APK servido diretamente do servidor local
- **Proteção de Segurança**: Arquivo `.htaccess` protege pasta `apk/`
- **Configuração Docker**: Pasta `apk/` copiada para container
- **Documentação Completa**: Guia detalhado do sistema

---

## 🆕 Versão v.1.2025.1707.1200

### ✅ Sistema de Redirecionamento Móvel

#### Funcionalidades:
- **Detecção Automática**: Sistema detecta dispositivos móveis
- **Redirecionamento Inteligente**: Dispositivos móveis redirecionados para download
- **Página de Download**: Interface moderna para download do app
- **Configuração Flexível**: Links configuráveis para Android e iOS

---

## 🆕 Versão v.1.2025.1607.1600

### ✅ Melhorias Avançadas na Página de Relatórios

#### Correções Implementadas:
- **Exibição Correta**: Bloco de pacientes só aparece em relatório de pacientes
- **Remoção de Coluna**: Coluna telefone removida do relatório de dispensas
- **Redimensionamento**: Coluna quantidade redimensionada
- **Impressão Otimizada**: Observações completas visíveis na impressão
- **Títulos Únicos**: Sem títulos duplicados na impressão

---

## 🆕 Versão v.1.2025.1607.1200

### ✅ Melhorias na Página de Relatórios

#### Funcionalidades:
- **Filtros de Data**: Habilitados para ajuste de estoque
- **Dropdown Ampliado**: Largura aumentada para 600px
- **Campo Observações**: Largura mínima aumentada para 300px
- **Modal Otimizado**: Ocupando todo o espaço disponível
- **Botões Padronizados**: Visual consistente com outras páginas

---

## 🆕 Versão v.1.2025.1507.1600

### ✅ Correções e Melhorias no Relatório de Ajuste de Estoque

#### Melhorias:
- **Informações do Usuário**: Incluídas informações do responsável pelo ajuste
- **Nova Coluna**: Coluna de responsável pelo ajuste adicionada
- **Arquivos Modificados**: `ajuste_estoque_api.php` e `relatorios.php`

---

## 🆕 Versão v.1.2025.1507.1200

### ✅ Correção de Loop Infinito na Página de Agenda

#### Correção:
- **Problema Resolvido**: Loop infinito ao abrir agenda em dia sem agendamentos
- **Mensagem Adequada**: "Nenhum agendamento para este dia" exibida
- **Carregamento Interrompido**: Sistema para corretamente o carregamento

### ✅ Novo Relatório de Ajuste de Estoque

#### Funcionalidades:
- **Registro Detalhado**: Cada ajuste registra medicamento, quantidade anterior/nova, responsável
- **Relatório Completo**: Nova aba "Ajuste de Estoque" com filtros
- **Integração com App**: Informações do usuário enviadas pelo app Android

---

## 🆕 Versão v.1.2025.1407.1200

### ✅ Nova Interface Moderna da Página Inicial (Dashboard)

#### Funcionalidades:
- **Header da Dashboard**: Boas-vindas personalizadas e relógio em tempo real
- **Seção de Informações**: Cards informativos com layout em grid
- **Seção da Marca**: Logo centralizado com efeito hover
- **Footer Moderno**: Layout em grid com dados de contato
- **Responsividade Completa**: Adaptação para todos os dispositivos

### ✅ Carregamento Automático de Agendamentos por Horário Local

#### Funcionalidades:
- **Detecção Automática**: Sistema identifica horário atual do usuário
- **Intervalos de Horário**: Considera intervalos específicos de atendimento
- **Carregamento Inteligente**: Mostra agendamentos a partir do horário atual
- **Interface Otimizada**: Título dinâmico e abas filtradas

---

## 🆕 Versão v.1.2025.1107.1200

### ✅ Melhorias de Segurança Implementadas

#### Medidas de Segurança:
- **Chave JWT Forte**: Chave criptográfica de 128 caracteres hexadecimais
- **Ambiente de Produção**: Headers de segurança ativos
- **CORS Restritivo**: Lista de domínios permitidos específicos
- **Validação de Upload**: Verificação de tipo MIME, extensão e tamanho
- **Senhas Fortes**: Mínimo 8 caracteres com requisitos complexos
- **Proteção Contra Força Bruta**: Limite de 5 tentativas por hora
- **Validação de Entrada**: Função robusta com múltiplos tipos
- **Sessões Seguras**: Detecção de HTTPS e regeneração de ID

---

## 🆕 Versão v.1.2025.1007.1600

### ✅ Navegação dos Meses na Agenda

#### Funcionalidades:
- **Botões de Navegação**: Setas nas extremidades do calendário
- **Navegação Circular**: Dezembro → Janeiro, Janeiro → Dezembro
- **Carregamento Dinâmico**: AJAX otimizado para novos meses
- **Interface Responsiva**: Botões adaptáveis para diferentes telas

### ✅ Correção da Atualização da Interface ao Cancelar Agendamentos

#### Correções:
- **Sincronização de Interface**: Atualização automática após cancelar
- **Fechamento Automático**: Lista fecha após deletar agendamento
- **Correções JavaScript**: Verificações de elementos e tratamento de erros
- **Formatação de Data**: Tratamento adequado de datas inválidas

---

## 🆕 Versão v.1.2025.1007.1200

### ✅ Sistema de Bloqueio de Agenda por Dia

#### Funcionalidades:
- **Tabela de Bloqueio**: Nova tabela `agenda_bloqueada`
- **Interface de Controle**: Checkbox no modal de agendamento
- **Indicadores Visuais**: Ícone de cadeado em dias bloqueados
- **Tooltip Informativo**: Mostra motivo do bloqueio
- **Validação Backend**: Verificação antes de permitir agendamentos

### ✅ Sistema de Abas de Horários na Agenda

#### Funcionalidades:
- **Interface de Abas**: Organização por horário (07:30, 08:00, etc.)
- **Contadores Dinâmicos**: Ocupação por horário (ex: "2/21 pacientes")
- **Atualização Automática**: Abas atualizadas em tempo real
- **Navegação Intuitiva**: Troca de abas com clique simples

### ✅ Correção da Ordenação na Página de Medicamentos

#### Correções:
- **Ordenação por Quantidade**: Query otimizada com LEFT JOIN
- **Ordenação por Total Recebido**: Subquery eficiente para última importação
- **Ordenação por Lote**: Lógica FIFO para primeiro lote disponível
- **Ordenação por Validade**: Lotes com validade mais próxima primeiro
- **Tratamento de Erros**: Try-catch implementado para todas as queries

---

## 🆕 Versão v.1.2025.0807.1600

### ✅ Indicador Numérico de Agendamentos no Calendário

#### Funcionalidades:
- **Badge Numérico**: Quantidade de agendamentos no canto superior direito
- **Funcionalidade de Clique**: Clique no badge abre seção de agendamentos
- **Separação de Ações**: Indicador e dia do calendário têm ações diferentes
- **Correção de Funcionamento**: Arrays separados para mês e dia atual

### ✅ Bloqueio de Agendamentos em Datas e Horários Passados

#### Funcionalidades:
- **Validação Backend**: Verificação de data/hora passada no servidor
- **Bloqueio Visual**: Dias passados marcados com opacidade reduzida
- **Bloqueio de Seleção**: Alerta ao tentar selecionar data passada
- **Bloqueio de Horários**: Horários passados desabilitados no dropdown

### ✅ Exibição do Último Agendamento na Página de Detalhes do Paciente

#### Funcionalidades:
- **Busca do Último Agendamento**: Query otimizada com status válido
- **Exibição Visual com Badges**: Status coloridos para cada tipo
- **Indicador de Encaixe**: Ícone especial para agendamentos extras
- **Interface Responsiva**: Badge adaptável para diferentes telas

---

## 🆕 Versão v.1.2025.0807.1200

### ✅ Relatório de Agendamentos Implementado

#### Funcionalidades:
- **Filtros Disponíveis**: Período, usuário/operador, paciente específico
- **Informações Exibidas**: Data/hora, paciente, status, tipo, operador
- **Exportação**: Suporte completo para Excel
- **Impressão**: Layout otimizado para impressão

### ✅ Atualização do Banco de Dados

#### Mudanças:
- **Campo Encaixe**: Adicionado na tabela `agenda`
- **Compatibilidade**: Estrutura suporta regras de encaixe
- **Correção de Sintaxe**: Erro SQL corrigido no `init.sql`

### ✅ Melhorias e Novas Funcionalidades na Agenda

#### Funcionalidades:
- **Encaixe Automático**: Sistema permite encaixes extras automaticamente
- **Bloqueio de Duplicidade**: Não permite agendar mesmo paciente no mesmo horário
- **Busca de Pacientes**: Busca por nome, CPF ou telefone
- **Layout Moderno**: Interface responsiva e intuitiva

---

## 🆕 Versão v.1.2025.0707.1600

### ✅ Implementação: Sistema de Agenda para Retirada de Medicamentos

#### Funcionalidades Principais:
- **Estrutura do Banco**: Nova tabela `agenda` com campos completos
- **Página Principal**: Interface completa com calendário interativo
- **Sistema de Horários**: Horários pré-definidos com validação
- **Funcionalidades AJAX**: Operações assíncronas para melhor experiência
- **Integração Completa**: Menu de navegação e autenticação

---

## 🆕 Versão v.1.2025.0707.1200

### ✅ Correção: Campo "Renovado" dos Medicamentos na Edição de Pacientes

#### Problema Resolvido:
- **Associação por ID**: Checkboxes agora usam `name="renovado[medicamento_id]"`
- **Processamento PHP**: Verificação `isset($_POST['renovado'][$medId])`
- **JavaScript Atualizado**: Cada checkbox associado ao ID do medicamento
- **Resultado**: Status de renovado sempre correto

---

## 🆕 Versão v.1.2025.0707.1300

### ✅ Correção: Conflito de IDs entre Médicos e Instituições

#### Solução Implementada:
- **Sistema de Offset**: Instituições com offset de +10000
- **Queries Atualizadas**: UNION ALL com offset aplicado
- **Lógica de Processamento**: Detecção automática de tipo
- **Interface Atualizada**: Dropdown mostra diferenciação entre tipos

---

## 🆕 Versão v.1.2025.0707.1200

### ✅ Implementação: Campo Telefone 2 para Pacientes

#### Funcionalidades:
- **Novo Campo**: `telefone2 VARCHAR(15)` na tabela `pacientes`
- **Páginas Atualizadas**: Cadastro, edição e visualização
- **Relatórios**: Queries atualizadas para incluir telefone2
- **Sistema de Busca**: Busca expandida para ambos os telefones

---

## 🆕 Versão v.1.2025.0607.1200

### ✅ Correção: Sistema de Autenticação e Inicialização do Banco de Dados

#### Problemas Resolvidos:
- **Login Flexível**: Aceita nome de usuário ou email completo
- **Autenticação Local**: Sistema funciona independente do LDAP
- **Constraint Problemática**: Removida constraint `chk_prescritor`
- **Hash da Senha**: Hash correto para senha padrão

---

## 🆕 Versão v.1.2025.0407.1200

### ✅ Correção: Sistema de Médicos e Instituições - CONFLITO DE IDs RESOLVIDO

#### Solução Implementada:
- **Novos Campos**: `tipo_prescritor` e `instituicao_id`
- **Migração de Dados**: Registros existentes migrados
- **Código PHP Atualizado**: Lógica para distinguir médicos e instituições
- **JavaScript Atualizado**: Select inteligente para ambos os tipos

---

## 🆕 Versão v.1.2025.0407.0820

### ✅ Correção: Sistema de Notificação de Dispensa Recente (20 dias)

#### Funcionalidades:
- **Verificação de 20 Dias**: Sistema verifica dispensação recente
- **Notificação Não-Bloqueante**: Mostra aviso mas permite continuar
- **Mensagens Informativas**: Alerta sobre dispensas recentes
- **Interface Atualizada**: Avisos em todas as formas de dispensa

---

## 🆕 Versão v.1.2025.0307.1900

### ✅ Implementação: Bloqueio de Dispensa Duplicada em 30 Dias (REMOVIDO)

#### Funcionalidades (Removidas):
- **Bloqueio Total**: Impedia dispensas em menos de 30 dias
- **Verificação Automática**: Sistema verificava dispensação recente
- **Mensagem Clara**: Explicava quantos dias se passaram
- **Sem Confirmação**: Bloqueio total sem opção de continuar

---

## 🆕 Versão v.1.2025.0307.1500

### ✅ Melhorias de Layout e Usabilidade nas Páginas Pacientes e Relatórios

#### Melhorias:
- **Página Pacientes**: Ajuste de larguras e centralização do status
- **Página Relatórios**: Padronização de altura e espaçamento
- **Interface Limpa**: Melhor aproveitamento do espaço horizontal
- **Consistência Visual**: Padrão uniforme entre páginas

---

## 🆕 Versão v.1.2025.0307.0815

### ✅ Padronização de Espaçamento e Altura de Linha das Tabelas

#### Melhorias:
- **Espaçamento do Header**: Margin-bottom adicionado
- **Larguras de Colunas**: Ajuste na página de medicamentos
- **Altura de Linha**: Padronização em todas as tabelas
- **Estrutura de Container**: Padronização com `<div class="card">`

---

## 🆕 Versão v.1.2025.0207.1500

### ✅ Padronização Visual Completa do Sistema

#### Melhorias:
- **Identidade Visual Unificada**: Todas as páginas com estrutura padronizada
- **Padronização de Botões**: Mesma classe `btn-secondary` para ações
- **Campos de Busca**: Estrutura unificada em todas as páginas
- **Layout Responsivo**: Melhorias na página de dispensar e relatórios

---

## 🆕 Versão v.1.2025.0207.1200

### ✅ Melhoria Visual e Funcional: Página de Medicamentos e Médicos

#### Melhorias:
- **Filtro Alfabético**: Visual idêntico ao da página de pacientes
- **Tabela Ampliada**: Ocupa toda a largura do container
- **Layout do Container**: Ampliado para até 2000px
- **Bloco de Paginação**: Reposicionado acima da tabela

---

## 🆕 Versão v.1.2025.0207.0900

### ✅ Melhoria: Modularização Completa de CSS

#### Funcionalidade:
- **Arquivos CSS Específicos**: Cada página com seu próprio arquivo CSS
- **Eliminação de Estilos Inline**: Todos movidos para arquivos externos
- **Organização Estruturada**: Estilos organizados por funcionalidade
- **Performance Otimizada**: Carregamento mais eficiente

---

## 🆕 Versão v.1.2025.0107.1355

### ✅ Nova Funcionalidade: Filtro Alfabético na Página de Pacientes

#### Funcionalidades:
- **Interface Visual**: Alfabeto completo (A-Z) + botão "Todas"
- **Filtro Inteligente**: `p.nome LIKE 'C%'` para letra selecionada
- **Integração Completa**: Funciona com busca, paginação e ordenação
- **Design Responsivo**: Adapta-se a diferentes tamanhos de tela

---

## 🆕 Versão v.1.2025.0107.1345

### ✅ Nova Funcionalidade: Sistema de Paginação na Página de Pacientes

#### Funcionalidades:
- **Limite Padrão**: 100 pacientes por página (configurável)
- **Opções de Limite**: 100, 200, 300, 500, 1000 pacientes
- **Navegação Inteligente**: Botões anterior/próximo + numeração
- **Informações Detalhadas**: Total de pacientes, página atual

---

## 🆕 Versão v.1.2025.0107.1200

### ✅ Nova Funcionalidade: Filtro de Renovação em Andamento nos Relatórios

#### Funcionalidades:
- **Filtro Integrado**: "Renovação em Andamento" no filtro "Status"
- **Interface Limpa**: Menos filtros separados
- **Lógica Intuitiva**: Tratado como tipo de status
- **Exportação**: Suporte completo para Excel

---

## 🆕 Versão v.1.2025.0107.1100

### ✅ Correção Completa: Sistema de Status de Renovação em Medicamentos

#### Correções:
- **Status "Renovação em andamento"**: Medicamentos renovados exibem ícone e texto
- **Correção de Visibilidade CSS**: Classes específicas para badges
- **Cores Definidas**: Verde, vermelho e amarelo para diferentes status
- **Interface Limpa**: Informação clara e objetiva

---

## 🆕 Versão v.1.2025.3006.1420

### ✅ Correção Crítica: Preservação de Quantidade Solicitada na Importação Automática

#### Solução Implementada:
- **Primeira Importação**: Ambos os campos com mesmo valor
- **Importações Subsequentes**: Apenas `quantidade` atualizada
- **Preservação Automática**: `quantidade_solicitada` mantida
- **Rastreabilidade**: Histórico da receita original vs. quantidade recebida

---

## 📊 Resumo de Funcionalidades por Versão

| Versão | Data | Principais Funcionalidades |
|--------|------|---------------------------|
| v.1.2025.0108.1600 | 08/01/2025 | Observações, Modal, Remoção página dispensar |
| v.1.2025.3107.1600 | 31/07/2025 | Código do paciente, Importação atualizada |
| v.1.2025.1707.1600 | 17/07/2025 | Download direto APK, Proteção de segurança |
| v.1.2025.1707.1200 | 17/07/2025 | Redirecionamento móvel, Detecção de dispositivos |
| v.1.2025.1607.1600 | 16/07/2025 | Melhorias relatórios, Impressão otimizada |
| v.1.2025.1607.1200 | 16/07/2025 | Filtros de data, Dropdown ampliado |
| v.1.2025.1507.1600 | 15/07/2025 | Relatório ajuste estoque, Informações usuário |
| v.1.2025.1507.1200 | 15/07/2025 | Correção agenda, Relatório ajuste estoque |
| v.1.2025.1407.1200 | 14/07/2025 | Dashboard moderna, Carregamento automático |
| v.1.2025.1107.1200 | 11/07/2025 | Melhorias segurança, JWT forte |
| v.1.2025.1007.1600 | 10/07/2025 | Navegação meses, Correção interface |
| v.1.2025.1007.1200 | 10/07/2025 | Bloqueio agenda, Abas horários |
| v.1.2025.0807.1600 | 08/07/2025 | Indicador numérico, Bloqueio datas passadas |
| v.1.2025.0807.1200 | 08/07/2025 | Relatório agendamentos, Encaixe automático |
| v.1.2025.0707.1600 | 07/07/2025 | Sistema agenda completo |
| v.1.2025.0707.1200 | 07/07/2025 | Correção campo renovado |
| v.1.2025.0707.1300 | 07/07/2025 | Correção conflito IDs |
| v.1.2025.0707.1200 | 07/07/2025 | Campo telefone 2 |
| v.1.2025.0607.1200 | 06/07/2025 | Correção autenticação |
| v.1.2025.0407.1200 | 04/07/2025 | Correção conflito médicos/instituições |
| v.1.2025.0407.0820 | 04/07/2025 | Notificação dispensa recente |
| v.1.2025.0307.1900 | 03/07/2025 | Bloqueio dispensa duplicada (removido) |
| v.1.2025.0307.1500 | 03/07/2025 | Melhorias layout |
| v.1.2025.0307.0815 | 03/07/2025 | Padronização tabelas |
| v.1.2025.0207.1500 | 02/07/2025 | Padronização visual |
| v.1.2025.0207.1200 | 02/07/2025 | Melhorias medicamentos/médicos |
| v.1.2025.0207.0900 | 02/07/2025 | Modularização CSS |
| v.1.2025.0107.1355 | 01/07/2025 | Filtro alfabético |
| v.1.2025.0107.1345 | 01/07/2025 | Sistema paginação |
| v.1.2025.0107.1200 | 01/07/2025 | Filtro renovação |
| v.1.2025.0107.1100 | 01/07/2025 | Status renovação |
| v.1.2025.3006.1420 | 30/06/2025 | Preservação quantidade solicitada |

---

## 🔄 Próximas Versões

### Planejado para v.1.2025.0208.1600
- [ ] Sistema de notificações push
- [ ] API REST completa
- [ ] Integração com sistemas externos
- [ ] Dashboard analytics avançado
- [ ] Backup automático em nuvem

### Planejado para v.1.2025.0308.1600
- [ ] Aplicativo iOS
- [ ] Sistema de auditoria avançado
- [ ] Relatórios customizáveis
- [ ] Integração com prontuário eletrônico
- [ ] Sistema de alertas inteligentes

---

## 📝 Notas de Desenvolvimento

### Convenções de Versionamento
- **Formato**: v.1.YYYY.MMDD.HHMM
- **Exemplo**: v.1.2025.0108.1600 = 08/01/2025 às 16:00
- **Compatibilidade**: Versões mantêm compatibilidade com dados anteriores
- **Migração**: Scripts automáticos para atualização de banco

### Processo de Release
1. **Desenvolvimento**: Funcionalidades desenvolvidas em branch separada
2. **Testes**: Testes unitários e de integração
3. **Review**: Code review e validação
4. **Deploy**: Deploy em ambiente de produção
5. **Documentação**: Atualização desta documentação

### Suporte a Versões
- **Versão Atual**: Suporte completo
- **Versão Anterior**: Suporte por 6 meses
- **Versões Antigas**: Sem suporte oficial

---

<div align="center">

**📋 Histórico completo de todas as versões do FARMALOAD**

*Última atualização: 08/01/2025*

</div>
