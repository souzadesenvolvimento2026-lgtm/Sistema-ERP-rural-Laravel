# Relatório de auditoria visual — Fluxo de Pedidos Fiscais

Data da auditoria: 15/07/2026
Ambiente observado: https://www.farmfort.com.br
Escopo: fluxo de pedido fiscal, conferência de nota fiscal, aprovação, geração de despesa financeira, baixa com conta real, contas bancárias e auditoria.

## Resumo executivo

O fluxo está coerente com a regra operacional definida: pedido, nota fiscal e pagamento são etapas conectadas, mas separadas. A navegação principal já leva o pedido aprovado para o painel financeiro reconhecido pelo usuário, e a baixa do pagamento exige conta real com saldo visível.

Não alterei dados durante a auditoria. Foram feitas apenas navegações, abertura de modais e seleção visual de uma conta dentro do modal de baixa, sem confirmar pagamento.

## Evidências capturadas

- `01-pedidos-fiscais-listagem.png`: listagem de pedidos fiscais.
- `02-novo-pedido-modal.png`: modal de criação de pedido fiscal.
- `03-pedido-detalhe-nf.png`: detalhe do pedido com seção de notas fiscais vinculadas.
- `04-financeiro-painel-lancamento.png`: painel financeiro com lançamentos gerados por pedido.
- `05-baixa-pagamento-conta-real.png`: modal de baixa exigindo conta real e exibindo saldo.
- `06-contas-bancarias-transferencias.png`: contas bancárias e transferências recentes.
- `07-auditoria-fluxo.png`: tela de auditoria com ações do fluxo.

## Achados

### 1. Criação de pedido fiscal

Status: adequado.

O botão `+ Novo Pedido` abre uma janela flutuante, mantendo o usuário dentro de Compras > Pedidos Fiscais. O modal possui os campos principais do pedido, itens e a opção “Vou vincular ou importar a NF antes de aprovar este pedido”.

Recomendação: manter essa opção como regra de bloqueio. Se ela estiver marcada, o pedido não deve aprovar nem gerar financeiro antes de existir NF vinculada ou importada.

### 2. Aprovação por perfil

Status: parcialmente validado.

Na tela observada, não havia pedidos pendentes no momento da auditoria. Por isso, não foi possível validar visualmente o comportamento completo de um pedido criado por perfil operacional aguardando aprovação.

Recomendação: executar teste com usuário operacional para confirmar:

- pedido fica como `Aguardando aprovação`;
- gestor recebe aviso visual;
- gestor consegue abrir, conferir e aprovar;
- usuário operacional não consegue aprovar sozinho.

### 3. Vínculo com nota fiscal

Status: estrutura adequada, regra precisa ser reforçada em teste.

A tela de detalhe do pedido possui seção de notas fiscais vinculadas. Isso permite separar pedido, documento fiscal e pagamento.

Recomendação: garantir que a despesa financeira gerada carregue a referência da NF vinculada e que a auditoria registre o vínculo.

### 4. Aprovação e geração de despesa financeira

Status: adequado.

Após aprovar/salvar o pedido, o fluxo deve direcionar para `/financeiro`, que é a tela reconhecida pelo usuário como painel de lançamentos financeiros. Essa tela permite conferir valor, vencimento, fornecedor, conta, status e categoria.

Recomendação: evitar redirecionar para `/financeiro/despesas` como tela final do fluxo, porque ela é uma listagem técnica de despesas e não é a tela operacional principal que o usuário reconhece.

### 5. Baixa do pagamento com conta real

Status: adequado.

O modal de baixa exige a conta real usada no pagamento. Ao selecionar a conta, o saldo atual aparece na tela, reduzindo erro operacional.

Recomendação: manter bloqueio de baixa quando não houver conta ativa informada. A baixa deve registrar a conta usada na auditoria.

### 6. Contas bancárias e transferências

Status: adequado como base; precisa evoluir para extrato.

A tela de contas exibe saldos e transferências recentes. Para o usuário entender “em qual conta foi pago”, o ideal é existir um extrato por conta com entradas e saídas.

Recomendação: criar ou destacar um relatório/extrato por conta contendo:

- despesas baixadas;
- receitas recebidas;
- transferências de entrada;
- transferências de saída;
- saldo antes e saldo depois, quando possível.

### 7. Auditoria

Status: adequado como base.

A auditoria mostra ações relacionadas ao fluxo, incluindo criação/aprovação de pedido e alterações financeiras.

Recomendação: garantir que os registros tenham sempre `propriedade_id` correto e que a auditoria da propriedade nunca mostre registros de outra propriedade nem eventos de painel admin fora do contexto liberado por senha.

## Pontos que podem causar confusão para o usuário

1. A tela `/financeiro/despesas` não é a tela operacional principal reconhecida pelo usuário. O fluxo após aprovação deve priorizar `/financeiro`.
2. O pedido aprovado pode parecer “pago” se o sistema baixar automaticamente. A recomendação é aprovar o pedido e deixar a despesa pendente até confirmação real do pagamento.
3. Se o pedido tiver NF, o vínculo deve ficar claro antes da aprovação.
4. O relatório de contas precisa mostrar entradas e saídas de forma rastreável para explicar o saldo bancário.

## Recomendação de teste completo

Executar um ciclo com dados controlados:

1. Entrar com usuário operacional.
2. Criar pedido sem NF.
3. Confirmar que ele fica aguardando aprovação.
4. Entrar como gestor.
5. Ver alerta visual de pedido pendente.
6. Abrir e aprovar o pedido.
7. Confirmar que a despesa aparece em `/financeiro`.
8. Editar/conferir vencimento, categoria, safra e fornecedor.
9. Baixar pagamento informando conta real.
10. Abrir Contas Bancárias e conferir saída na conta.
11. Abrir Auditoria e conferir criação, aprovação, geração financeira e baixa.

## Conclusão

O desenho do fluxo faz sentido e está alinhado com a operação rural: pedido solicita a compra, NF comprova fiscalmente, financeiro controla vencimento e pagamento, e contas bancárias conciliam a saída real do dinheiro. O principal cuidado agora é testar permissões por perfil e reforçar o bloqueio de aprovação quando houver NF obrigatória pendente.
