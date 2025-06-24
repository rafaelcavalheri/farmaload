# Guia de Importação de Medicamentos

Este guia explica como preparar e importar medicamentos no sistema usando o arquivo modelo fornecido.

## Formatos de Importação Suportados

### 1. Formato Template Padrão (Recomendado)
Use o arquivo `modelo_importacao.xlsx` como base. Este formato possui as seguintes colunas:

- **Nome do Medicamento**: Nome completo do medicamento (obrigatório)
- **Quantidade**: Quantidade em estoque (obrigatório, deve ser maior que zero)
- **Lote**: Número do lote (opcional, será gerado automaticamente se não informado)
- **Validade**: Data de validade no formato DD/MM/AAAA (opcional, padrão: 31/12/2024)
- **Nome do Paciente**: Nome do paciente (opcional, para vincular medicamento ao paciente)

### 2. Formato RELINI_FIM
Para importações do sistema RELINI, use uma planilha com a aba "RELINI_FIM" contendo:

- **Coluna A**: LMES (Lote)
- **Coluna D**: FIM VAL. (Validade do paciente)
- **Coluna E**: PACIENTES
- **Coluna F**: DT ATEND. (Data de atendimento)
- **Coluna G**: QTDE. (Quantidade)
- **Coluna H**: MEDICAMENTOS
- **Coluna I**: CID

### 3. Formato Livre
O sistema também suporta importação de planilhas em formato livre, onde ele tentará identificar automaticamente:
- Medicamentos
- Quantidades
- Pacientes
- Lotes
- Validades

## Instruções para Importação

1. Baixe o arquivo `modelo_importacao.xlsx`
2. Preencha os dados seguindo o formato das colunas
3. Salve o arquivo
4. Acesse a página de importação no sistema
5. Selecione o arquivo e clique em importar

## Regras e Validações

- O nome do medicamento é obrigatório
- A quantidade deve ser maior que zero
- A data de validade deve estar no formato DD/MM/AAAA
- Se o lote não for informado, será gerado automaticamente
- O nome do paciente é opcional, mas se informado, o medicamento será vinculado ao paciente
- Medicamentos com o mesmo nome e lote terão suas quantidades somadas
- A apresentação do medicamento será extraída automaticamente do nome

## Dicas

1. Mantenha o nome do medicamento consistente para evitar duplicações
2. Inclua a apresentação no nome do medicamento (ex: "Comprimido", "Cápsula", etc.)
3. Verifique se as datas de validade estão no formato correto
4. Para vincular medicamentos a pacientes, preencha a coluna "Nome do Paciente"
5. Use o formato template padrão para maior precisão na importação

## Suporte

Em caso de dúvidas ou problemas na importação, consulte o log de importação em:
`/var/www/html/debug_logs/import_debug.log` 