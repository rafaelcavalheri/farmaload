# FARMALOAD - Gerenciador de Farmacia Pública de Alto Custo


**Versão:** v.1.2025.2606.1200
**Data:** 26/06/2025

## v.1.2025.2606.1200 (26/06/2025)

### Melhorias no Layout e Design das Páginas

**Interface de Busca Aprimorada:**
- **Alinhamento Perfeito:** Botão "Buscar" agora está alinhado na mesma linha da caixa de texto de busca
- **Tamanho Otimizado:** Caixa de texto de busca aumentada para `400px` de largura mínima, proporcionando melhor experiência de digitação
- **Layout Flexível:** Implementado sistema flexbox para melhor responsividade e alinhamento
- **Espaçamento Melhorado:** Gap de `15px` entre elementos e altura padronizada de `48px` para o botão

**Melhorias nas Páginas:**
- **Busca Unificada:** Interface de busca mais intuitiva e profissional
- **Alinhamento Visual:** Botão e caixa de texto perfeitamente alinhados
- **Responsividade:** Layout adaptável para diferentes tamanhos de tela
- **Experiência do Usuário:** Interface mais limpa e organizada

**Correção Crítica de SQL:**
- **Erro de Parâmetro:** Corrigido erro `SQLSTATE[HY093]: Invalid parameter number` na página de dispensar
- **Consulta Otimizada:** Removido parâmetro desnecessário `:validade_formatada` que não existia no SQL
- **Estabilidade:** Sistema mais robusto e livre de erros de execução

**Arquivos Modificados:**
- `PHP/dispensar.php` - Melhorias de layout e correção de SQL
- `PHP/medicos.php` - Melhorias de layout
- `PHP/medicamentos.php` - Melhorias de layout
- `PHP/pacientes.php` - Melhorias de layout
- `CSS/style.css` - Estilos aprimorados para interface de busca

**Impacto:**
- Interface mais profissional e moderna
- Melhor experiência do usuário na busca de pacientes
- Sistema mais estável e confiável
- Layout responsivo e bem alinhado

---

## v.1.2025.2506.0820 (25/06/2025)

### Correção Crítica: Marcação de Medicamento Renovado por Paciente

**Problema Identificado:**
- Ao marcar o campo "Renovado" em um medicamento na tela de edição do paciente, a marcação era salva no medicamento errado ao visualizar os detalhes do paciente, especialmente quando havia múltiplos medicamentos.
- O erro ocorria devido ao uso de arrays indexados para checkboxes, o que causava descompasso entre os índices dos medicamentos e os valores enviados pelo formulário.

**Solução Aplicada:**
- O campo de checkbox "Renovado" agora utiliza o ID do medicamento como chave no atributo `name` (ex: `name="renovado[ID]"`).
- O processamento no PHP foi ajustado para associar corretamente o valor do campo "renovado" ao ID do medicamento, independentemente da ordem ou de medicamentos removidos/adicionados.
- Garantia de robustez mesmo com adição/remoção dinâmica de medicamentos no formulário.

**Impacto:**
- Agora, ao marcar ou desmarcar o campo "Renovado" de qualquer medicamento, a informação é salva e exibida corretamente na tela de detalhes do paciente.
- Eliminação do bug de "deslocamento" da marcação de renovação.

**Arquivos Modificados:**
- `PHP/editar_paciente.php` (HTML e processamento PHP do campo renovado)

### Melhorias Visuais na Tabela de Medicamentos

- Todas as linhas divisórias (borders) foram removidas da tabela de medicamentos para um visual mais limpo.
- Implementado efeito zebra: as linhas da tabela agora alternam entre branco e cinza claro, facilitando a leitura e separação visual dos itens.
- Nenhum traço ou linha residual aparece sob os botões de ação ou em qualquer célula.

**Arquivo Modificado:**
- `CSS/style.css`

### Correção: Coluna "Total Recebido" na Página de Medicamentos

**Problema Identificado:**
- A coluna "Total Recebido" na página de medicamentos estava exibindo o valor total de todas as importações do dia da última importação, em vez de mostrar apenas o valor recebido na última importação específica.
- A função `getTotalUltimaImportacao()` somava todas as importações do mesmo dia, causando confusão na visualização dos dados.

**Solução Aplicada:**
- A função `getTotalUltimaImportacao()` foi corrigida para buscar apenas a última importação específica de cada medicamento.
- Removida a lógica que somava todas as importações do mesmo dia.
- Agora a função retorna diretamente a quantidade da importação mais recente de cada medicamento.

**Impacto:**
- A coluna "Total Recebido" agora exibe corretamente apenas o valor recebido na última importação específica de cada medicamento.
- Informação mais precisa e útil para o usuário.
- Eliminação da confusão causada pela soma de múltiplas importações do mesmo dia.

**Arquivos Modificados:**
- `PHP/funcoes_estoque.php` - Função `getTotalUltimaImportacao()` corrigida

### Correção: Exibição de Extornos na Página de Detalhes do Paciente

**Problema Identificado:**
- Quando era feito um extorno de medicamento, ele não aparecia na página de detalhes do paciente, apenas nos relatórios.
- A consulta SQL estava filtrando apenas transações com `quantidade > 0`, excluindo os extornos que têm `quantidade < 0`.
- Os usuários não conseguiam visualizar o histórico completo de transações do paciente.

**Solução Aplicada:**
- **Consulta SQL Corrigida:** Removido o filtro `AND t.quantidade > 0` para incluir todas as transações (dispensações e extornos).
- **Nova Coluna "Tipo":** Adicionada coluna para diferenciar visualmente entre dispensações e extornos:
  - Dispensações: badge verde com ícone de seta para baixo
  - Extornos: badge vermelho com ícone de desfazer
- **Quantidade Absoluta:** Uso de `abs($registro['quantidade'])` para mostrar sempre valores positivos.
- **Botão Extornar Condicional:** O botão "Extornar" aparece apenas para dispensações (`quantidade > 0`).
- **Diferenciação Visual:** CSS adicionado para diferenciar linhas de dispensação (fundo verde claro) e extorno (fundo vermelho claro).

**Impacto:**
- Extornos agora aparecem corretamente na página de detalhes do paciente.
- Histórico completo de todas as transações do paciente visível.
- Diferenciação visual clara entre dispensações e extornos.
- Interface mais informativa e funcional.

**Arquivos Modificados:**
- `PHP/detalhes_paciente.php` - Consulta SQL corrigida e interface aprimorada

## v.1.2025.2406.1730

### Correçoes

- Duplicidade de medicamentos na pagina medicamentos
- Erro ao exibir detalhes da importaçao

**Arquivos Corrigidos:**

- `PHP/medicamentos.php`
- `PHP/detalhes_importacao.php`

## v.1.2025.2406.1500 (24/06/2025)

### Correçoes

**Problema Identificado:**
- As datas do arquivo de importação estão vindo com ' antes, exemplo '30/10/2025,
isso gerava um erro ao importar
**Arquivos Corrigidos:**
- `PHP/processar_importacao_automatica.php` - adicionado correção pra lidar com '

### Nova Funcionalidade: Desmarcação Automática do Campo "Renovado"

**Funcionalidade Implementada:**
- **Lógica Automática:** Quando uma data de renovação é atualizada durante a importação, se o medicamento estiver marcado como "renovado" (campo `renovado = 1`), o sistema automaticamente desmarca esse campo.
- **Inteligência de Detecção:** O sistema compara a data de renovação atual com a nova data. Se forem diferentes e o medicamento estiver marcado como renovado, executa a desmarcação automática.
- **Logs Detalhados:** Todas as ações de desmarcação automática são registradas nos logs de importação para auditoria.
- **Preservação de Dados:** Se a data de renovação for a mesma, o campo "renovado" permanece inalterado, evitando desmarcações desnecessárias.

**Detalhes Técnicos:**
- **Função Modificada:** `vincularMedicamentoPaciente()` no arquivo `processar_importacao_automatica.php`
- **Verificação Inteligente:** Comparação entre `renovacao_atual` e `renovacao_nova` antes de executar a desmarcação
- **Query Dinâmica:** Construção de SQL dinâmico que inclui `renovado = 0` apenas quando necessário
- **Compatibilidade:** Mantida total compatibilidade com funcionalidades existentes

**Cenários de Uso:**
1. **Importação com Nova Data:** Paciente com medicamento marcado como "renovado" e data 31/12/2024. Nova importação traz data 30/06/2025 → Campo "renovado" é automaticamente desmarcado.
2. **Importação com Mesma Data:** Paciente com medicamento marcado como "renovado" e data 31/12/2024. Nova importação traz a mesma data → Campo "renovado" permanece marcado.
3. **Novo Vínculo:** Criação de novo vínculo paciente-medicamento não afeta campos "renovado" existentes.

**Arquivos Modificados:**
- `PHP/processar_importacao_automatica.php` - Função `vincularMedicamentoPaciente()` aprimorada
- `PHP/teste_renovacao_automatica.php` - Arquivo de teste para validar a funcionalidade

**Resultado:**
- Sistema mais inteligente e automatizado para gestão de renovações
- Redução de trabalho manual para desmarcar campos "renovado" obsoletos
- Maior consistência nos dados de renovação
- Logs detalhados para auditoria e troubleshooting

---

## v.1.2025.2406.0920 (24/06/2025)

### Correção Crítica no Sistema de Busca de Médicos e Instituições

**Problema Identificado:**
- Erro fatal SQL: "Column not found: 1054 Unknown column 'i.nome' in 'where clause'"
- Ocorria na página `medicos.php` durante buscas por médicos e instituições
- WHERE clause unificado tentando referenciar aliases de tabelas incompatíveis em UNION query

**Análise do Problema:**
- Código usava uma única variável `$where` que referenciava tanto `m.` (medicos) quanto `i.` (instituicoes)
- Em queries UNION, cada parte deve ter seus próprios aliases de tabela válidos
- A cláusula WHERE incluía `i.nome` e `i.cnes` que não existiam no contexto da tabela `medicos`

**Correções Aplicadas:**
- **Separação de WHERE Clauses:** Criadas variáveis separadas `$where_medicos` e `$where_instituicoes`
- **Condições Específicas:** Cada tabela agora tem suas próprias condições de busca apropriadas
- **Aliases Corretos:** `$where_medicos` usa alias `m.` e `$where_instituicoes` usa alias `i.`
- **Queries Atualizadas:** Ambas as queries (COUNT e SELECT) foram corrigidas para usar os WHERE clauses apropriados

**Arquivo Corrigido:**
- `PHP/medicos.php` - Separação das cláusulas WHERE para medicos e instituicoes

**Resultado:**
- Sistema de busca funcionando corretamente para médicos e instituições
- Eliminação do erro SQL fatal
- Busca por nome, CRM, CNS, CNES funcionando sem problemas
- Sistema mais estável e confiável

---

## v.1.2025.2306.1400 (23/06/2025)

### Correções Críticas na Funcionalidade de Detalhes de Importação

**Problema Identificado:**
- Erro "Column not found: medicamento_nome" na funcionalidade de detalhes de importação
- Consultas SQL usando nomes de colunas incorretos da tabela `logs_importacao_detalhes`
- Função `registrarDetalhesImportacao` com estrutura de dados incompatível

**Correções Aplicadas:**
- **Consultas SQL:** Ajustadas para usar os nomes corretos das colunas (`nome`, `tipo`, `observacoes`)
- **Função de Registro:** Corrigida para usar o campo `tipo` para distinguir entre medicamentos e pacientes
- **Estrutura de Dados:** Padronizada para maior consistência e organização

**Arquivos Corrigidos:**
- `PHP/ajax_detalhes_importacao.php` - Consultas SQL corrigidas
- `PHP/processar_importacao_automatica.php` - Função de registro corrigida

**Resultado:**
- Funcionalidade de detalhes de importação funcionando corretamente
- Eliminação de erros "Column not found"
- Sistema mais estável e confiável

---

## v.1.2025.2306.1200 (23/06/2025)

### Implementação de Relatório de Importação Detalhado e Correções Críticas

**Nova Funcionalidade: Relatório de Importação Detalhado**
- **Banco de Dados:** Criada a nova tabela `logs_importacao_detalhes` para armazenar detalhes de cada item importado.
- **Backend:** O script `processar_importacao_automatica.php` foi aprimorado para popular a nova tabela com dados dos medicamentos e pacientes de cada importação.
- **Frontend:**
  - Adicionado um botão "Detalhes" na lista de logs de importação na página `relatorios.php`.
  - Implementado um modal que exibe os detalhes (medicamentos, pacientes, lotes, validades) de forma clara.
  - Criado o endpoint `ajax_detalhes_importacao.php` para carregar os dados dinamicamente.
- **Exportação:** A funcionalidade de exportar para Excel foi estendida para incluir os relatórios detalhados.

**Correções de Bugs e Melhorias**
- **Logs Duplicados:** Resolvido o problema crítico onde cada importação gerava duas entradas de log. O código duplicado foi removido, garantindo a integridade dos registros.
- **Redirecionamento Pós-Importação:** Melhorada a experiência do usuário, alterando o redirecionamento para a página `relatorios.php` com a aba de importações já selecionada.
- **Estabilidade:** Corrigidos múltiplos erros e warnings (`headers already sent`) que surgiram durante o desenvolvimento, garantindo uma execução limpa e estável.
- **Código Limpo:** Removidos todos os logs e `echo` de depuração.

**Arquivos Modificados:**
- `PHP/processar_importacao_automatica.php`
- `PHP/relatorios.php`
- `PHP/ajax_detalhes_importacao.php`
- `DOCKER-FILES/init.sql`

**Resultado:**
- O sistema agora possui um robusto relatório de detalhes de importação.
- A estabilidade foi melhorada com a correção de bugs críticos.
- A usabilidade foi aprimorada com o redirecionamento inteligente.

---

## v.1.2025.2306.0950 (23/06/2025)

### Implementação de Sistema Avançado de Observações Padrão

**Nova Funcionalidade: Sistema de Observações Múltiplas**
- **Interface Melhorada:** Substituído o sistema simples de dropdown por um modal elegante com seleção múltipla de observações.
- **Botão Adicionar:** Implementado botão "+" verde para abrir o seletor de observações padrão.
- **Botão Limpar:** Adicionado botão com ícone de borracha (fa-eraser) para limpar todas as observações de uma vez.
- **Seleção Múltipla:** Possibilidade de selecionar várias observações simultaneamente através de checkboxes.
- **Formatação Inteligente:** Observações são automaticamente separadas por vírgulas para melhor legibilidade.

**Funcionalidades Implementadas:**
- **Modal de Observações:** Interface elegante com lista de 15 observações padrão pré-definidas.
- **Confirmação de Limpeza:** Sistema de confirmação antes de limpar as observações.
- **Validação:** Verificação se o campo já está vazio antes de tentar limpar.
- **Edição Manual:** Possibilidade de editar o texto manualmente a qualquer momento.
- **Integração Completa:** Sistema implementado tanto na página `dispensar.php` quanto no modal de dispensação da página `pacientes.php`.

**Detalhes Técnicos:**
- **CSS Responsivo:** Estilos adaptáveis para diferentes tamanhos de tela.
- **JavaScript Robusto:** Sistema de eventos com retry para garantir funcionamento após carregamento AJAX.
- **UX Otimizada:** Feedback visual e confirmações para melhor experiência do usuário.
- **Compatibilidade:** Mantida compatibilidade com funcionalidades existentes.

**Arquivos Modificados:**
- `PHP/dispensar.php` - Implementação do novo sistema de observações
- `PHP/pacientes.php` - Integração do sistema no modal de dispensação
- `PHP/ajax_form_dispensar.php` - Atualização do HTML gerado para o modal

**Resultado:**
- Interface mais profissional e intuitiva para gestão de observações.
- Controle total sobre as observações: adicionar múltiplas, limpar todas ou editar manualmente.
- Experiência consistente entre diferentes páginas do sistema.
- Sistema robusto e confiável para uso em produção.

---

# Histórico de versões

## v.1.2025.1906.1920

### Implementação de Observações Padrão no Modal de Dispensação

**Funcionalidade implementada:**
- Adicionada funcionalidade de observações padrão no modal de dispensação da página `pacientes.php`
- Sistema idêntico ao já existente na página `dispensar.php`
- Melhoria na experiência do usuário com seleção rápida de observações comuns

**Detalhes técnicos:**

1. **Array de observações padrão:**
   - Implementado array com 15 observações padrão pré-definidas
   - Mesmas opções disponíveis na página `dispensar.php`
   - Observações incluem: "Retirado pelo próprio paciente", "Retirado por pessoa autorizada", "Avisado para trazer renovação", etc.

2. **Interface do modal:**
   - Adicionado select para escolha de observações padrão
   - Textarea para observações com placeholder explicativo
   - Labels claros e dica de uso para o usuário
   - Layout responsivo e bem estilizado

3. **Funcionalidade JavaScript:**
   - Atualização instantânea do textarea ao selecionar observação padrão
   - Mudança visual (cor de fundo) quando observação é selecionada
   - Possibilidade de edição manual do textarea
   - Limpeza automática do select quando texto é editado manualmente
   - Sistema robusto com retry para garantir inicialização dos eventos

4. **Sistema de inicialização:**
   - Controle centralizado na página `pacientes.php`
   - Sistema de retry para garantir que elementos sejam encontrados após carregamento AJAX
   - Logs de debug para facilitar troubleshooting
   - Busca específica dentro do container do modal

**Arquivos modificados:**
- `PHP/ajax_form_dispensar.php` - Adicionado array de observações padrão e interface
- `PHP/pacientes.php` - Implementado JavaScript para controle dos eventos

**Resultado:**
- Funcionalidade de observações padrão disponível em ambas as interfaces
- Experiência consistente entre página `dispensar.php` e modal de dispensação
- Sistema robusto e confiável
- Interface intuitiva e responsiva

**Teste realizado:**
- Funcionalidade testada e funcionando perfeitamente
- Sistema de retry funcionando corretamente
- Compatibilidade mantida com funções existentes de dispensação

## v.1.2025.1906.1800 (19/06/2025)

### Correção do Sistema de Observações na Página de Dispensa

**Problema identificado:**
- O sistema de observações padrão na página `dispensar.php` não estava funcionando corretamente
- Erro JavaScript: "Uncaught ReferenceError: atualizarObservacao is not defined"
- O textarea de observações não atualizava imediatamente ao selecionar uma observação padrão
- A funcionalidade só funcionava após dispensar e recarregar a página

**Análise do problema:**
- A função JavaScript `atualizarObservacao` estava sendo chamada antes de ser definida
- O script estava posicionado no final da página, mas o elemento HTML tentava usar a função via atributo `onchange`
- Falta de sincronização entre o carregamento do DOM e a inicialização dos eventos

**Correções aplicadas:**

1. **Reposicionamento do JavaScript:**
   - Movido todo o código JavaScript para a seção `<head>` da página
   - Garantido que a função esteja disponível antes de qualquer elemento HTML tentar usá-la

2. **Melhoria no sistema de eventos:**
   - Removido o atributo `onchange` inline do elemento `<select>`
   - Implementado sistema de event listeners via JavaScript
   - Adicionado MutationObserver para detectar quando elementos são criados dinamicamente

3. **Inicialização robusta:**
   - Verificação do estado do DOM (`document.readyState`)
   - Inicialização automática quando elementos são detectados
   - Prevenção de duplicação de event listeners

4. **Melhorias na experiência do usuário:**
   - Feedback visual: textarea muda de cor quando observação é selecionada
   - Possibilidade de edição manual do textarea
   - Limpeza automática do dropdown quando texto é editado manualmente

**Código JavaScript implementado:**
```javascript
function atualizarObservacao(valor) {
    const textarea = document.getElementById('observacao');
    if (textarea) {
        textarea.value = valor || '';
        textarea.style.backgroundColor = valor ? '#e8f5e8' : '#fff';
    }
}

// Sistema de inicialização com MutationObserver
const observer = new MutationObserver(function(mutations) {
    // Detecta quando elementos são criados e configura eventos
});
```

**Resultado:**
- Sistema de observações funciona perfeitamente
- Atualização imediata do textarea ao selecionar observação padrão
- Sem erros JavaScript no console
- Experiência do usuário melhorada com feedback visual
- Compatibilidade com edição manual de observações

**Arquivos modificados:**
- `PHP/dispensar.php` - Reposicionamento e melhoria do JavaScript

**Teste realizado:**
- Funcionalidade testada e funcionando perfeitamente
- Console limpo sem erros JavaScript
- Sistema responsivo e intuitivo

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
