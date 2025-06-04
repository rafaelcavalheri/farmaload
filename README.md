# Farmaload - Notas da Versão

**Versão:** v.1.2025.0306.1008
**Data:** 03/06/2025

---

# Histórico de versões

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
