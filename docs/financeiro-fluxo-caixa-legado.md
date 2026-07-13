# Fluxo de Caixa do legado

Este documento registra o comportamento funcional da tela legada de Fluxo de Caixa para orientar a implementação e revisão da versão Laravel do FarmFort.

Use este arquivo como referência quando for alterar consultas, controllers, services, cards, gráficos, filtros ou relatórios do fluxo de caixa.

## Identificação da tela

- Tela: Fluxo de Caixa.
- Módulo: Financeiro.
- Submenu: Fluxo de Caixa.
- URL no legado: `http://192.168.17.68:8081/farmfort/pages/fluxo_caixa.php?mod=financeiro`.

## Objetivo da tela

Mostrar entradas, saídas e saldo acumulado por mês, comparando valores previstos com valores reais pagos ou recebidos.

A tela é de consulta e análise. Ela não salva, exclui ou cancela registros diretamente.

## Elementos visíveis

### Campos e filtros

- Fazenda.
- Safras.
- Período:
  - Data inicial.
  - Data final.

Filtros esperados:

- Por fazenda.
- Por uma ou várias safras.
- Por período manual.

### Botões

- Aplicar safras.
- Atualizar fluxo.
- Ampliar gráfico.
- Voltar, dentro do gráfico ampliado.

### Cards de resumo

- Total Receitas, previsto.
- Total Despesas, previsto.
- Total Recebido, real.
- Total Pago, real.

### Gráfico

O gráfico apresenta a evolução mensal do fluxo de caixa.

Comportamento esperado:

- Barras para receitas previstas.
- Barras para despesas previstas.
- Linha para saldo acumulado.
- Opção para ampliar em tela cheia.

### Tabela de detalhamento

A tabela detalha os mesmos dados do gráfico, mês a mês.

Colunas esperadas:

- Mês.
- Receitas.
- Despesas.
- Saldo Previsto.
- Recebido.
- Pago.
- Saldo Real.
- Acumulado.

## Comportamento dos botões

### Aplicar safras

Aplica as safras marcadas no filtro e recalcula o fluxo.

Quando uma safra é marcada, o sistema deve limpar automaticamente o período manual.

### Todas as safras

Marca ou desmarca todas as safras da fazenda.

### Atualizar fluxo

Recarrega a tela com os filtros selecionados.

Se houver período manual informado, a análise deve usar esse período.

Se houver safras selecionadas, a análise deve usar as datas dessas safras.

### Ampliar gráfico

Abre o gráfico em tela cheia para facilitar a análise visual.

### Voltar

Fecha o gráfico ampliado e retorna para a tela normal do fluxo de caixa.

## Fluxo normal de uso

1. Usuário abre `Financeiro > Fluxo de Caixa`.
2. Seleciona a fazenda.
3. Seleciona uma ou mais safras, ou informa um período manual.
4. Clica em `Atualizar fluxo` ou `Aplicar safras`.
5. O sistema recalcula receitas, despesas, recebido, pago e acumulado.
6. Usuário analisa o gráfico e a tabela mensal.
7. Se quiser visualizar melhor, clica em `Ampliar gráfico`.
8. Dentro do gráfico ampliado, clica em `Voltar` para retornar.

## Regras de negócio

### Propriedade

Se não houver propriedade selecionada, mostrar aviso:

```text
Nenhuma propriedade selecionada.
```

Se o usuário selecionar uma fazenda sem acesso, o sistema deve voltar para a propriedade atual da sessão.

### Safras

A tela não depende de safra ativa.

Se não houver safra ativa, a tela continua funcionando com:

- Safras cadastradas da fazenda; ou
- Ano atual, se não houver safra cadastrada; ou
- Período manual informado pelo usuário.

Se não houver safra cadastrada para a fazenda, mostrar aviso:

```text
Nenhuma safra cadastrada nesta fazenda.
```

Mesmo com esse aviso, a tela ainda pode calcular pelo ano atual ou por período manual.

### Relação entre período manual e safras

Se o usuário informar período manual:

- O período manual desmarca as safras.
- A análise passa a ser por data.
- Mostrar orientação:

```text
Informar período desmarca as safras.
```

Se o usuário selecionar safra:

- O sistema limpa automaticamente os campos de período manual.
- A análise passa a considerar as datas das safras escolhidas.

### Datas

Se a data final for menor que a data inicial, o sistema inverte automaticamente as datas.

Exemplo:

- Data inicial informada: `2026-12-31`.
- Data final informada: `2026-01-01`.
- Período usado: `2026-01-01` até `2026-12-31`.

## Cálculos

### Receitas previstas

Somar `receitas.valor_total`.

Data de referência:

```text
COALESCE(data_recebimento, data_venda)
```

Critérios:

- Incluir receitas não canceladas.
- Agrupar por mês.
- Respeitar fazenda e filtros aplicados.

### Receitas reais

Somar receitas com status `recebido`.

Critérios:

- Usar apenas receitas recebidas.
- Agrupar por mês.
- Respeitar fazenda e filtros aplicados.

### Despesas previstas

Somar `despesas.valor_total`.

Data de referência:

```text
COALESCE(data_vencimento, data_lancamento)
```

Critérios:

- Excluir despesas canceladas.
- Excluir despesas reprovadas.
- Agrupar por mês.
- Respeitar fazenda e filtros aplicados.

### Despesas reais

Somar despesas com `status_pagamento = pago`.

Data de referência:

```text
COALESCE(data_pagamento, data_vencimento, data_lancamento)
```

Critérios:

- Usar apenas despesas pagas.
- Agrupar por mês.
- Respeitar fazenda e filtros aplicados.

### Saldo previsto

```text
receitas previstas - despesas previstas
```

### Saldo real

```text
recebido real - pago real
```

### Acumulado

Soma progressiva do saldo real mês a mês.

Exemplo:

```text
acumulado do mês atual = acumulado do mês anterior + saldo real do mês atual
```

### Agrupamento

O agrupamento é sempre por mês no formato:

```text
YYYY-MM
```

Na implementação Laravel, as consultas com agregação devem respeitar o MariaDB da produção com `ONLY_FULL_GROUP_BY` habilitado. Para evitar erro em modo estrito, preferir normalizar o mês em subconsulta e agrupar pelo alias na consulta externa.

## Erros e avisos

Quando faltar propriedade:

```text
Nenhuma propriedade selecionada.
```

Quando não houver safra cadastrada:

```text
Nenhuma safra cadastrada nesta fazenda.
```

Quando período manual for informado:

```text
Informar período desmarca as safras.
```

## O que manter na versão Laravel

- Filtro por fazenda.
- Filtro por safra.
- Filtro por período manual.
- Cards de valores previstos e reais.
- Gráfico com receitas, despesas e acumulado.
- Tabela mensal detalhada.
- Período manual sobrescrevendo filtro por safra.
- Safra sobrescrevendo período manual.
- Gráfico ampliado em tela cheia.

## O que melhorar na versão Laravel

- Criar botão de exportação PDF/Excel do fluxo.
- Permitir granularidade por mês, trimestre ou ano.
- Mostrar saldo inicial das contas bancárias.
- Separar previsto e realizado em abas ou legenda mais clara.
- Criar drill-down: clicar no mês e abrir lançamentos daquele mês.
- Centralizar os cálculos em service próprio, sem regra financeira na Blade.
- Criar testes automatizados para filtros, cards, gráfico, tabela e permissões de propriedade.

## Critérios de aceite para a migração

A tela Laravel será considerada equivalente ao legado quando:

- O usuário conseguir filtrar por fazenda.
- O usuário conseguir selecionar uma ou várias safras.
- O usuário conseguir informar período manual.
- Informar período manual desmarcar as safras.
- Selecionar safra limpar o período manual.
- Data final menor que data inicial for corrigida automaticamente.
- Usuário sem acesso à fazenda não conseguir consultar dados dela.
- Cards baterem com os registros filtrados.
- Tabela mensal bater com os cards e com o gráfico.
- Gráfico exibir receitas, despesas e acumulado.
- Gráfico ampliado abrir e fechar corretamente.
- Tela funcionar mesmo sem safra ativa.
- Tela exibir aviso quando não houver propriedade selecionada.
- Consultas funcionarem no MariaDB de produção com `ONLY_FULL_GROUP_BY`.

## Pontos a confirmar antes da implementação completa

- Nomes finais dos status de receita recebida.
- Nomes finais dos status de despesa paga, cancelada e reprovada.
- Se o fluxo deve considerar saldo inicial de contas bancárias já na primeira versão Laravel.
- Se transferências bancárias entram no fluxo como movimentação neutra ou se aparecem em coluna própria.
- Layout final do gráfico ampliado.
- Formato esperado da futura exportação PDF/Excel.
