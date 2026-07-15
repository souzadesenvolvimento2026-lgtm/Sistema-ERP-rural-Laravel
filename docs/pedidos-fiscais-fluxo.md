# Fluxo de Pedidos Fiscais

Este fluxo organiza compra, conferência fiscal, aprovação e baixa financeira sem misturar pedido, nota fiscal e pagamento.

## 1. Criar pedido fiscal

No menu **Compras > Pedidos Fiscais**, clique em **+ Novo Pedido** e informe:

- fornecedor;
- CNPJ do fornecedor;
- data do pedido;
- itens do pedido;
- categoria, unidade, quantidade e valor de cada item.

Se o pedido já tiver nota fiscal, marque **Vou vincular ou importar a NF antes de aprovar este pedido**. Assim o pedido fica aberto para conferência da nota antes de gerar o lançamento financeiro.

## 2. Aprovação por perfil

Usuários com perfil de gestão, financeiro ou administração podem aprovar pedidos da propriedade.

Usuários de hierarquia operacional criam o pedido, mas ele fica como **Aguardando aprovação**. O gestor visualiza um aviso na tela de pedidos com a quantidade de pedidos pendentes e pode abrir cada pedido para conferir.

Quando o próprio gestor cria um pedido sem marcar a opção de NF antes da aprovação, o sistema aprova automaticamente e cria o lançamento financeiro.

## 3. Vincular nota fiscal ao pedido

Abra o pedido e use a seção **Notas fiscais vinculadas**.

Você pode:

- selecionar uma NF já lançada no fiscal;
- importar um XML de NF-e para comparar com o pedido;
- confirmar o vínculo depois da conferência;
- remover o vínculo se ele estiver incorreto.

Ao aprovar o pedido, o lançamento financeiro recebe a referência da nota vinculada. Isso facilita a consulta posterior no financeiro, fiscal e auditoria.

## 4. Aprovar pedido

Depois de conferir itens e NF, clique em **Aprovar pedido**.

A aprovação:

- lança a despesa no financeiro;
- incorpora itens ao estoque quando aplicável;
- cria vínculo com patrimônio quando o item for patrimônio;
- registra auditoria;
- direciona para a edição da despesa financeira.

## 5. Conferir vencimento no financeiro

Na despesa gerada, confira:

- descrição;
- fornecedor;
- categoria e subcategoria;
- safra;
- valor;
- vencimento;
- forma de pagamento;
- nota fiscal vinculada.

Enquanto o pagamento ainda não aconteceu, deixe a despesa como pendente.

## 6. Baixar pagamento com conta real

Para confirmar o pagamento, o sistema exige a **conta real** usada na saída do dinheiro.

Ao selecionar a conta, o saldo atual é exibido para reduzir erro operacional. Sem conta ativa informada, a baixa do pagamento é bloqueada.

Recomendação operacional:

1. aprove o pedido;
2. confira o lançamento financeiro;
3. só marque como pago depois que o dinheiro sair da conta;
4. selecione a conta correta;
5. confira o saldo exibido;
6. confirme a baixa.

## 7. Auditoria

O sistema registra auditoria em ações críticas do fluxo, incluindo:

- criação do pedido fiscal;
- aprovação do pedido;
- geração de lançamento financeiro;
- baixa da despesa;
- conta usada na baixa do pagamento.

Nunca registre senha ou informação sensível no campo de detalhes da auditoria.
