# Inventário de telas e rotas

Auditoria funcional inicial do FarmFort ERP Rural.

- Data: 2026-07-16
- Branch: `auditoria-funcional-farmfort`
- Escopo: levantamento técnico e funcional, sem alteração de regras, rotas, telas ou consultas.
- Ambiente analisado: código local do repositório Laravel.

## Premissas da auditoria

- Esta etapa não executou testes manuais em produção.
- Não foi feita remoção, correção ou refatoração de código.
- O arquivo principal citado no pedido, `docs/documentacao-completa-sistema.md`, não existe neste checkout local. A ausência foi registrada como pendência documental.
- O comando `php artisan route:list` não pôde ser executado localmente porque o PHP não está disponível no `PATH` deste Windows. O inventário abaixo foi feito por leitura estática de `routes/web.php`, `app/Support/ModuleCatalog.php`, controllers, services, views e testes.

## Resumo técnico encontrado

| Item | Total encontrado | Evidência |
| --- | ---: | --- |
| Declarações estáticas de rotas | 231 | `routes/web.php` |
| Rotas `GET` | 104 | `routes/web.php` |
| Rotas `POST` | 98 | `routes/web.php` |
| Rotas `PUT` | 16 | `routes/web.php` |
| Rotas `DELETE` | 9 | `routes/web.php` |
| Rotas `MATCH` | 4 | `routes/web.php` |
| Controllers | 43 | `app/Http/Controllers` |
| Services | 44 | `app/Services` |
| Views Blade | 208 | `resources/views` |
| Arquivos de teste | 22 | `tests` |

## Classificação usada

| Status | Critério |
| --- | --- |
| Confirmado em uso | Rota aparece no menu, em links/forms Blade, redirect conhecido ou teste automatizado. |
| Uso indireto | Rota é chamada por compatibilidade, AJAX, formulário dinâmico, modal ou fluxo de controller. |
| Sem acesso identificado | Rota existe, mas não foi encontrado menu/link/teste claro nesta primeira leitura. |
| Duplicado | Duas entradas parecem apontar para a mesma tela ou mesmo fluxo. |
| Legado | Rota existe para compatibilidade com PHP legado ou URLs antigas. |
| Suspeito de desuso | Rota não tem acesso claro e parece substituída por tela/fluxo novo. |
| Necessita investigação | Exige validação manual em homologação ou `route:list` com aplicação inicializada. |

## Módulos e telas identificados

| Módulo | Tela principal | Acesso principal | Status | Observação |
| --- | --- | --- | --- | --- |
| Autenticação | Login e logout | `/login`, `/logout`, compatibilidade `login.php`, `logout.php` | Confirmado em uso | Tem rotas modernas e compatibilidade legado. |
| Dashboard | Central da Safra | `/dashboard` | Confirmado em uso | Menu principal aponta diretamente para a rota. |
| Financeiro | Lançamentos financeiros | `/financeiro` | Confirmado em uso | Tela central do módulo financeiro. |
| Financeiro | Contas bancárias | `/financeiro/contas` | Confirmado em uso | Acessada por aba Bancos e por relatórios financeiros. |
| Financeiro | Despesas | `/financeiro/despesas` | Uso indireto | Existe como tela própria e também como fluxo/modal a partir de lançamentos. |
| Financeiro | Receitas | `/financeiro/receitas` | Uso indireto | Similar a despesas. |
| Financeiro | Agenda, planejamento, livro caixa, movimentações | `/financeiro/...` | Necessita investigação | Rotas existem; validar todos os acessos no menu e em ações. |
| Compras | Pedidos fiscais | `/compras/pedidos` | Confirmado em uso | Fluxo recente com NF, aprovação, rejeição e financeiro. |
| Fiscal | Entrada de NF, notas, certificados, produtores, documentos | `/fiscal/...` | Confirmado em uso | Forte relação com compras e financeiro. |
| Patrimônio | Patrimônios e lançamentos | `/patrimonio` | Confirmado em uso | Tela principal e modais. |
| Safras | Safras, planejamento, insumos, atividades, colheita | `/safras` | Confirmado em uso | Tela principal e abas relacionadas. |
| Talhões | Listagem, mapa, pivô, exclusões, importação | `/talhoes`, `/talhoes/mapa` | Confirmado em uso | Tem cobertura automatizada específica. |
| Colheita | Registros de colheita | `/colheita` | Confirmado em uso | CRUD e status. |
| Estoque de produtos | Produtos e estoque | `/produtos` | Confirmado em uso | Acesso pelo catálogo de módulos. |
| Estoque de produção | Contratos e entregas | `/estoque-producao` | Confirmado em uso | Possui regras de domínio testadas. |
| Usuários | Usuários e permissões | `/usuarios` | Confirmado em uso | Também usado em auditoria. |
| Propriedades | Propriedades/Fazendas e grupos | `/propriedades` | Confirmado em uso | Admin e gestão de propriedades. |
| Relatórios | DRE, fluxo de caixa, orçado x realizado, comparativo | `/relatorios/...` | Confirmado em uso | Há rotas de exportação. |
| Auditoria | Logs de auditoria | `/auditoria` | Confirmado em uso | Tela sensível; deve manter escopo por propriedade. |
| Orçamento | Planejamento financeiro | `/orcamento` | Confirmado em uso | Possui várias ações POST/PUT/DELETE. |
| Compatibilidade legado | URLs `/pages/*.php` | `/pages/{legacy}` | Legado | Redireciona páginas antigas para rotas Laravel. |
| Chat e suporte | Chat interno e suporte | `/chat-interno`, `/suporte`, `/ajax/*`, `/pages/ajax/*` | Uso indireto | Mantém AJAX moderno e legado. |

## Inventário por família de rotas

### Autenticação e entrada pública

| Métodos e URIs | Controller/ação | Tela/fluxo | Status | Evidência |
| --- | --- | --- | --- | --- |
| `GET /` | redirect para `login` | Entrada do sistema | Confirmado em uso | `routes/web.php` |
| `GET /login`, `POST /login` | `AuthController` | Login | Confirmado em uso | Testes de autenticação e fluxo de dashboard. |
| `POST /logout`, `GET /logout` | `AuthController` | Logout | Confirmado em uso | Compatibilidade com navegação. |
| `GET /login.php`, `POST /login.php`, `GET /index.php`, `GET /logout.php` | `AuthController` ou redirect | Compatibilidade legado | Legado | Mantém links antigos funcionando. |

### Núcleo autenticado

| Métodos e URIs | Controller/ação | Tela/fluxo | Status | Evidência |
| --- | --- | --- | --- | --- |
| `GET /admin` | redirect para dashboard | Admin inicial | Uso indireto | Rota autenticada. |
| `GET /dashboard` | `DashboardController@index` | Dashboard | Confirmado em uso | Menu e testes. |
| `POST /sistema/liberar-edicao` | `SystemUnlockController@unlock` | Liberação de edição | Confirmado em uso | Teste de segurança cobre escopo por propriedade. |
| `POST /sistema/liberar-edicao/renovar` | `SystemUnlockController@renew` | Renovação de liberação | Confirmado em uso | Fluxo administrativo. |
| `POST /propriedades/selecionar` | `PropertyContextController@select` | Troca de propriedade | Confirmado em uso | Header global. |
| `GET /modulos/{module}` | `ModuleController@show` | Hub genérico de módulo | Uso indireto | `ModuleCatalog` usa esta rota em vários itens. |

### Suporte e chat

| Métodos e URIs | Controller/ação | Tela/fluxo | Status | Evidência |
| --- | --- | --- | --- | --- |
| `GET /suporte`, `POST /suporte/chat`, `POST /suporte/anexo`, `POST /suporte/chat/{mensagem}/visualizar` | `SupportController` | Suporte | Confirmado em uso | Botão Chat/Suporte. |
| `POST /chat-interno`, `GET /chat-interno/mensagens`, `POST /chat-interno/{mensagem}/visualizar`, `POST /chat-interno/anexo`, `POST /chat-interno/typing`, `GET /chat-interno/typing` | `InternalChatController` | Chat interno | Confirmado em uso | Widget global. |
| `MATCH /ajax/chat-interno`, `MATCH /ajax/chat-anexo`, `MATCH /ajax/suporte-chat`, `MATCH /ajax/suporte-anexo` | Controllers de chat/suporte | Compatibilidade AJAX | Uso indireto | Testes de compatibilidade AJAX. |
| `MATCH /pages/ajax/chat_interno.php`, `MATCH /pages/ajax/chat_anexo.php`, `MATCH /pages/ajax/suporte_chat.php`, `MATCH /pages/ajax/suporte_anexo.php` | Controllers de chat/suporte | Compatibilidade legado | Legado | URLs PHP antigas. |

### Financeiro

| Métodos e URIs | Controller/ação | Tela/fluxo | Status | Evidência |
| --- | --- | --- | --- | --- |
| `GET /financeiro`, `GET /financeiro/lancamentos/novo`, `POST /financeiro/lancamentos` | `FinancialController` | Lançamentos financeiros | Confirmado em uso | Menu e testes de UI. |
| `GET /financeiro/contas`, `POST /financeiro/contas`, `PUT /financeiro/contas/{conta}`, `POST /financeiro/contas/{conta}/status` | `FinancialAccountController` | Contas bancárias | Confirmado em uso | Aba Bancos. |
| `POST /financeiro/contas/transferencias`, `PUT /financeiro/contas/transferencias/{transferencia}` | `FinancialAccountController` | Transferências internas | Confirmado em uso | Tela Contas Bancárias. |
| `GET /financeiro/movimentacoes`, `POST /financeiro/movimentacoes/{movimentacao}/conciliar`, `POST /financeiro/movimentacoes/{movimentacao}/ignorar` | `BankMovementController` | Movimentações bancárias | Necessita investigação | Exige validação de acesso visual. |
| `GET /financeiro/agenda`, `POST /financeiro/agenda/{tipo}/{id}/pagar`, `POST /financeiro/agenda/{tipo}/{id}/receber` | `FinancialAgendaController` | Agenda financeira | Uso indireto | URL legada redireciona para esta tela. |
| `GET /financeiro/analise-despesas` | `ExpenseAnalyticsController@index` | Análise de despesas | Uso indireto | Mapeada no legado. |
| `GET /financeiro/despesas`, `GET /financeiro/despesas/{despesa}/editar`, `PUT /financeiro/despesas/{despesa}`, `POST /financeiro/despesas/{despesa}/aprovar`, `POST /financeiro/despesas/{despesa}/rejeitar`, `POST /financeiro/despesas/{despesa}/pagar`, `POST /financeiro/despesas/{despesa}/cancelar`, `POST /financeiro/despesas/{despesa}/duplicar` | `ExpenseController` | Despesas | Confirmado em uso | Menu de ações e pedido fiscal aprovado. |
| `GET /financeiro/receitas`, `GET /financeiro/receitas/{receita}/editar`, `PUT /financeiro/receitas/{receita}`, `POST /financeiro/receitas/{receita}/aprovar`, `POST /financeiro/receitas/{receita}/rejeitar`, `POST /financeiro/receitas/{receita}/receber`, `POST /financeiro/receitas/{receita}/cancelar`, `POST /financeiro/receitas/{receita}/duplicar` | `RevenueController` | Receitas | Confirmado em uso | Fluxo financeiro e dashboard. |
| `GET /financeiro/categorias`, `POST /financeiro/categorias`, `PUT /financeiro/categorias/{categoria}`, `POST /financeiro/categorias/{categoria}/status` | `FinancialCategoryController` | Categorias | Uso indireto | Rota legada aponta para ela. |
| `GET /financeiro/planejamento`, `GET /financeiro/livro-caixa`, `GET /financeiro/livro-caixa/exportar`, `GET /financeiro/relatorio-lancamentos`, `GET /financeiro/relatorio-lancamentos/exportar` | Controllers financeiros | Planejamento, livro caixa e exportações | Confirmado em uso | URLs legadas e botões de relatório. |

### Produtos, patrimônio, safras, talhões e colheita

| Módulo | Métodos e URIs | Controller principal | Status | Evidência |
| --- | --- | --- | --- | --- |
| Produtos | `GET /produtos`, `GET /produtos/novo`, `POST /produtos`, `GET /produtos/{produto}/editar`, `PUT /produtos/{produto}`, `POST /produtos/{produto}/status` | `ProductController` | Confirmado em uso | `ModuleCatalog` e rota legada `produtos.php`. |
| Patrimônio | `GET /patrimonio`, `GET /patrimonio/novo`, `POST /patrimonio`, `GET /patrimonio/{patrimonio}/editar`, `PUT /patrimonio/{patrimonio}`, `PUT /patrimonio/{patrimonio}/valor`, `POST /patrimonio/{patrimonio}/status`, `POST /patrimonio/{patrimonio}/lancamentos`, `GET /patrimonio/{patrimonio}` | `PatrimonyController` | Confirmado em uso | Tela e testes funcionais amplos. |
| Safras | `GET /safras`, `GET /safras/nova`, `POST /safras`, `GET /safras/{safra}/editar`, `PUT /safras/{safra}`, `POST /safras/{safra}/status`, `POST /safras/{safra}/excluir` | `SafraController` | Confirmado em uso | Tela Safras e testes de domínio. |
| Talhões | `GET /talhoes`, `GET /talhoes/mapa`, `POST /talhoes/mapa`, exportações, importação, unificação, mapa/dados, exclusões, pivôs, chuva, atividades, CRUD e status | `TalhaoController` e `TalhaoMapController` | Confirmado em uso | Testes específicos de mapa e área excluída. |
| Colheita | `GET /colheita`, `GET /colheita/nova`, `POST /colheita`, `GET /colheita/{colheita}/editar`, `PUT /colheita/{colheita}`, `DELETE /colheita/{colheita}`, `POST /colheita/{colheita}/finalizar`, `POST /colheita/{colheita}/reabrir` | `HarvestController` | Confirmado em uso | `ModuleCatalog` e regras de domínio. |

### Usuários, propriedades, estoque, fiscal, relatórios e orçamento

| Módulo | Métodos e URIs | Controller principal | Status | Evidência |
| --- | --- | --- | --- | --- |
| Usuários | `GET /usuarios`, `GET /usuarios/novo`, `POST /usuarios`, `GET /usuarios/{usuario}/editar`, `PUT /usuarios/{usuario}`, `POST /usuarios/{usuario}/status` | `UserController` | Confirmado em uso | Tela e auditoria. |
| Propriedades | `GET /propriedades`, `GET /propriedades/nova`, `POST /propriedades`, `GET /propriedades/{propriedade}/editar`, `PUT /propriedades/{propriedade}`, `POST /propriedades/{propriedade}/status`, grupos CRUD | `PropertyController` e `FarmGroupController` | Confirmado em uso | Painel admin e testes de segurança. |
| Estoque de produção | `GET /estoque-producao`, contratos e entregas | `ProductionStockController` | Confirmado em uso | Regras de contratos testadas. |
| Fiscal | `GET /fiscal`, entrada NF, notas, importação XML, confirmação, aprovação, rejeição, certificados, produtores, documentos | Controllers fiscais | Confirmado em uso | Relação direta com compras e financeiro. |
| Relatórios | `GET /relatorios`, DRE, fluxo de caixa, orçado x realizado, categorias, safra, talhão, KPIs, comparativo e exportações | `ReportController` e controllers de relatórios | Confirmado em uso | Menu financeiro e relatórios. |
| Auditoria | `GET /auditoria`, `GET /auditoria/exportar`, `GET /auditoria/{log}/detalhes` | `AuditController` | Confirmado em uso | Tela sensível e testes. |
| Orçamento | `GET /orcamento`, projeções, atividades planejadas, despesas, insumos, copiar safra anterior, editar, atualizar e excluir | `PlanejamentoFinanceiroController` | Confirmado em uso | Módulo listado e regras de orçamento. |

### Compras e pedidos fiscais

| Métodos e URIs | Controller/ação | Tela/fluxo | Status | Evidência |
| --- | --- | --- | --- | --- |
| `GET /compras`, `GET /compras/pedidos` | `CompraPedidoController@index` | Lista de pedidos fiscais | Confirmado em uso | Menu Compras. |
| `GET /compras/pedidos/novo`, `POST /compras/pedidos` | `CompraPedidoController@create/store` | Novo pedido fiscal | Confirmado em uso | Fluxo recém-implementado. |
| `GET /compras/pedidos/{pedido}`, `GET /compras/pedidos/{pedido}/editar`, `PUT /compras/pedidos/{pedido}` | `CompraPedidoController@show/edit/update` | Conferência/edição do pedido | Confirmado em uso | Botão Abrir e modal de pedido. |
| `POST /compras/pedidos/{pedido}/notas`, `POST /compras/pedidos/{pedido}/notas/importar`, `POST /compras/pedidos/{pedido}/notas/confirmar`, `POST /compras/pedidos/{pedido}/notas/cancelar-preview`, `DELETE /compras/pedidos/{pedido}/notas/{nota}` | `CompraPedidoController` | Vínculo de NF ao pedido | Confirmado em uso | Requisito funcional recente. |
| `POST /compras/pedidos/{pedido}/aprovar`, `POST /compras/pedidos/{pedido}/rejeitar` | `CompraPedidoController` | Aprovação/rejeição | Confirmado em uso | Testes de pedido fiscal. |

### Compatibilidade com PHP legado

| URI | Destino Laravel | Status | Observação |
| --- | --- | --- | --- |
| `/pages/agenda_financeira.php` | `/financeiro/agenda` | Legado | Redirecionamento controlado. |
| `/pages/despesas.php` | `/financeiro` | Legado | Mantém comportamento do legado. |
| `/pages/contas.php` | `/financeiro/contas` | Legado | Bancos. |
| `/pages/pedidos_fiscais.php` | `/compras/pedidos` | Legado | Compras. |
| `/pages/safras.php` | `/safras` | Legado | Safras. |
| `/pages/talhoes.php`, `/pages/mapa_talhoes.php` | `/talhoes`, `/talhoes/mapa` | Legado | Talhões. |
| `/pages/auditoria.php` | `/auditoria` | Legado | Auditoria. |
| Demais entradas do mapa em `routes/web.php` | Rotas Laravel equivalentes | Legado | Não remover sem validação de logs e acessos reais. |

