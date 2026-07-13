# Lançamentos financeiros do legado

Este documento registra o comportamento funcional da tela legada de lançamentos financeiros para orientar a implementação e revisão da versão Laravel do FarmFort.

Use este arquivo como referência quando for alterar telas, controllers, services, permissões, relatórios ou consultas do módulo financeiro.

## Objetivo da tela

A tela de lançamentos financeiros centraliza despesas, receitas e transferências. Ela permite filtrar, visualizar, aprovar, pagar, receber, editar, duplicar, excluir e gerar relatórios dos lançamentos.

Na migração para Laravel, o objetivo é manter a experiência operacional do legado, mas com regras de negócio fora da Blade, validação server-side, permissões centralizadas e consultas testáveis.

## Elementos visíveis

### Filtros

- Tipo de lançamento:
  - Todos
  - Despesas
  - Receitas
  - Transferências
- Mês.
- Data inicial.
- Data final.
- Pesquisa da tabela.
- Quantidade de registros por página.
- Conta bancária.
- Pendências a pagar.
- Pendências a receber.
- Solicitações de aprovação.

### Cards de resumo

- Despesas.
- Receitas.
- Resultado.
- A pagar, quando houver filtro de pendências de pagamento.
- A receber, quando houver filtro de pendências de recebimento.

### Menus do financeiro

- Lançamentos.
- Fluxo de Caixa.
- DRE.
- Orçado x Realizado.
- DRE Agrícola.
- Comparativo de Safras.
- Bancos.

### Tabelas

A tela exibe despesas, receitas e transferências.

Quando o filtro está em `Todos`, os três tipos são misturados em uma tabela única. Os registros que precisam de aprovação aparecem primeiro; depois, a ordenação segue pela data mais recente.

## Modal de despesa

Campos esperados:

- Descrição.
- Data.
- Categoria.
- Subcategoria.
- Safra.
- Patrimônio.
- Produtor.
- Talhão.
- Fornecedor.
- Quantidade.
- Unidade.
- Valor unitário.
- Valor total.
- Parcelas.
- Vencimento.
- Forma de pagamento.
- Conta.
- Nota fiscal.
- Comprovante.
- Observações.

Campos obrigatórios principais:

- Descrição.
- Data.
- Categoria.
- Valor total.

## Botões e ações

### Botões principais

- Aplicar período.
- Limpar período.
- Lançamentos.
- Pendências.
- Solicitações.
- Bancos.
- Gerar relatório.
- Novo Lançamento.

### Novo Lançamento

Ao clicar em `Novo Lançamento`, o sistema abre uma escolha entre:

- Despesa.
- Receita.
- Transferência.
- XML/NF.

Comportamento esperado:

- `Despesa`: abre modal para cadastrar nova despesa.
- `Receita`: no legado redireciona para `receitas.php?mod=financeiro&novo=1`.
- `Transferência`: no legado redireciona para `contas.php?mod=financeiro&transferencia=1`.
- `XML/NF`: abre entrada fiscal se o usuário tiver permissão fiscal.

Na versão Laravel, esses fluxos podem usar rotas próprias, desde que mantenham o mesmo resultado operacional para o usuário.

### Ações por linha

- Aprovar.
- Pagar.
- Receber.
- Editar.
- Copiar.
- Duplicar.
- Excluir.
- Editar transferência.

## Fluxo normal de uso

1. Usuário abre `Financeiro > Lançamentos`.
2. Filtra por mês, período, tipo de lançamento, conta ou pendência.
3. Clica em `Novo Lançamento`.
4. Escolhe despesa, receita ou transferência.
5. Preenche os dados.
6. Salva.
7. Se o usuário tiver permissão de gestão/financeiro, o lançamento já fica aprovado.
8. Se precisar de aprovação, o lançamento aparece como pendente no topo da lista.
9. Gestor acessa `Solicitações`, aprova ou reprova.
10. Depois de aprovado, o lançamento pode ser pago ou recebido.

## Regras de negócio

### Safra

- Se não houver safra ativa, a tela não bloqueia o lançamento.
- Despesa pode ficar com safra `Nenhuma`.
- Se já houver safra selecionada, a safra atual vem pré-selecionada no cadastro de despesa.

### Permissões

- Usuário sem permissão não deve ver ou executar ações financeiras restritas.
- As ações restritas incluem, conforme o perfil:
  - Criar lançamento.
  - Aprovar.
  - Pagar.
  - Receber.
  - Gerar relatório.
  - Excluir diretamente.
  - Acessar XML/NF fiscal.
- Quando a ação for bloqueada, o sistema deve exibir erro claro, não falhar silenciosamente.

Mensagem esperada:

```text
Seu usuário não tem permissão para esta ação financeira.
```

### Aprovação

- Despesa criada por usuário sem permissão gerencial fica pendente.
- Despesa pendente não pode ser paga.
- Receita pendente não pode ser recebida.
- Despesa paga não pode ter a aprovação alterada.
- Solicitações pendentes devem aparecer no topo da lista.
- Aprovação em lote exige ao menos um item selecionado.

Mensagens esperadas:

```text
Despesa lançada e enviada para aprovação.
Despesa aprovada. Agora ela pode ser paga.
Despesa não aprovada não pode ser paga.
Receita não aprovada não pode ser recebida.
Despesa paga não pode ter a aprovação alterada.
Selecione ao menos uma solicitação para aprovar.
```

### Pagamento e recebimento

- Confirmar pagamento marca despesa aprovada como paga.
- Confirmar pagamento grava conta e data.
- Confirmar recebimento marca receita aprovada como recebida.
- Confirmar recebimento grava conta e data.
- Registro pendente de aprovação não pode ser pago ou recebido.

### Exclusão

- Gestor pode cancelar diretamente.
- Colaborador ou financeiro sem permissão máxima gera solicitação de exclusão.
- Solicitação de exclusão fica marcada em observações com marcador interno.
- Solicitação aparece para gestor aprovar.
- Ao aprovar exclusão, a despesa é cancelada.

Mensagens esperadas:

```text
Exclusão aprovada. Despesa cancelada.
```

### Duplicidade

- No legado não há bloqueio forte de duplicidade.
- Na versão Laravel, deve existir melhoria gradual para identificar possíveis duplicidades sem travar lançamentos legítimos.

## Cálculos

### Total da despesa

Quando quantidade e valor unitário forem informados:

```text
quantidade x valor unitário
```

Caso contrário, usa o valor total digitado.

### Parcelamento

- O valor de cada parcela é o valor total dividido pelo número de parcelas.
- Cada parcela gera um registro.
- As datas das parcelas seguem periodicidade mensal a partir da data base.

### Cards

- `Despesas`: soma `valor_total` das despesas não canceladas no período filtrado.
- `Receitas`: soma `valor_total` das receitas não canceladas no período filtrado.
- `Resultado`: receitas menos despesas.

### Tabela Todos

No filtro `Todos`, o sistema une despesas, receitas e transferências em uma listagem única.

Ordenação esperada:

1. Registros que precisam de aprovação.
2. Data mais recente.

## Relatórios

O botão `Gerar relatório` abre modal para exportar PDF ou Excel, respeitando o tipo e o período escolhidos.

O relatório deve usar os mesmos filtros aplicados na tela, incluindo tipo de lançamento, período e conta bancária quando aplicável.

## Auditoria e atualização da tela

Depois de qualquer mutação, o sistema deve:

1. Gravar a alteração no banco.
2. Registrar auditoria.
3. Recarregar a tela ou atualizar a listagem.

Mutação inclui:

- Salvar.
- Pagar.
- Receber.
- Aprovar.
- Reprovar.
- Excluir.
- Solicitar exclusão.
- Duplicar.

## Mensagens de sucesso

Mensagens esperadas:

```text
Despesa lançada com sucesso.
Despesa lançada e enviada para aprovação.
Despesa aprovada. Agora ela pode ser paga.
Exclusão aprovada. Despesa cancelada.
```

## O que manter na versão Laravel

- Visual da tela.
- Cards de resumo.
- Filtros por período, tipo e banco.
- Aprovações no topo.
- Auditoria de alterações.
- Fluxo de pagamento.
- Fluxo de recebimento.
- Relatório PDF/Excel.
- Experiência simples e rápida de uso.

## O que melhorar na versão Laravel

- Separar despesas, receitas e transferências em services/controllers próprios.
- Evitar regra de negócio dentro da Blade.
- Criar validação server-side completa.
- Melhorar controle de duplicidade.
- Criar status automático para vencidas.
- Centralizar permissões por cargo/perfil.
- Criar uma camada única de consulta de lançamentos financeiros, mantendo tabelas específicas por tipo quando necessário.
- Criar testes automatizados para filtros, cards, permissões, aprovação, pagamento, recebimento, exclusão e relatórios.

## Critérios de aceite para a migração

A tela Laravel será considerada equivalente ao legado quando:

- O usuário conseguir filtrar por tipo, mês, período, banco e pendência.
- O filtro `Todos` misturar despesas, receitas e transferências em uma tabela única.
- Pendências de aprovação aparecerem primeiro.
- Cards baterem com os registros filtrados.
- Novo lançamento permitir escolher despesa, receita, transferência e XML/NF.
- Despesa abrir modal com os campos funcionais do legado.
- Usuário sem permissão não conseguir executar ação restrita.
- Lançamento pendente não puder ser pago ou recebido.
- Gestor conseguir aprovar, reprovar e cancelar.
- Usuário sem permissão máxima gerar solicitação de exclusão.
- Relatório PDF/Excel respeitar os filtros da tela.
- Toda alteração registrar auditoria.

## Pontos a confirmar antes da implementação completa

- Matriz exata de permissões por perfil.
- Nomes finais dos status financeiros no Laravel.
- Colunas finais dos relatórios PDF e Excel.
- Regras definitivas para detectar duplicidade.
- Formato exato do marcador interno de solicitação de exclusão em observações.
- Se transferências continuarão em fluxo separado ou serão exibidas por uma camada unificada de consulta.
