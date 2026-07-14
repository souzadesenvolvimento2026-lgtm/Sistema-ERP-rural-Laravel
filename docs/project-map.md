# Mapa do projeto FarmFort ERP Rural Laravel

Este mapa orienta manutenção, novas telas e migração do legado para Laravel. Ele deve ser lido junto com `docs/architecture.md` e `docs/coding-standards.md`.

## Visão geral

- Framework: Laravel 13.
- PHP esperado: 8.3 ou superior.
- Frontend: Blade, CSS próprio em `public/css/farmfort.css`, JavaScript próprio em `public/js/`.
- Build: Vite.
- Banco alvo de produção: MariaDB 11.8.6 com modo SQL estrito.
- Fluxo principal: Controller fino → Service/Use Case → Domain/Repository/Model → Blade.

## Tamanho atual por camada

| Área | Pasta | Quantidade atual |
| --- | --- | ---: |
| Controllers | `app/Http/Controllers` | 42 |
| Services | `app/Services` | 43 |
| Domain | `app/Domain` | 14 |
| Support | `app/Support` | 3 |
| Views Blade | `resources/views` | 200 |
| Testes Feature | `tests/Feature` | 3 |
| Testes Unit/Architecture | `tests/Unit` | 17 |
| Migrations | `database/migrations` | 7 |
| Deploy | `deploy` | 3 |
| Documentação | `docs` | 9 |

## Pastas principais

| Caminho | Responsabilidade |
| --- | --- |
| `app/Http/Controllers` | Receber HTTP, validar entrada, autorizar e chamar Services. |
| `app/Services` | Casos de uso, regras de aplicação, coordenação de transações e preparação de dados para tela. |
| `app/Domain` | Regras puras, capacidades, validações e cálculos sem dependência de HTTP/Blade. |
| `app/Support` | Formatação, contexto e catálogo de módulos. |
| `app/Http/Middleware` | Autenticação FarmFort e headers de segurança. |
| `resources/views` | Blade, modais, tabelas, formulários e layout. |
| `public/css` | CSS compilado/manual do sistema. |
| `public/js` | JavaScript de telas, mapa, pedidos, chat e comportamento global. |
| `routes/web.php` | Mapa das rotas web do ERP. |
| `database/migrations` | Evolução estrutural do banco. |
| `tests/Feature` | Fluxos integrados e regras de negócio com banco. |
| `tests/Unit` | Regras puras e testes de arquitetura. |
| `deploy` | Scripts versionados de publicação. |
| `scripts/dev` | Scripts de apoio para desenvolvimento Windows/servidor. |
| `docs` | Regras de arquitetura, testes, segurança, legado e mapeamento. |

## Módulos funcionais

| Módulo | Rotas principais | Controllers | Services | Views |
| --- | --- | --- | --- | --- |
| Autenticação | `/login`, `/logout` | `AuthSessionController` | `AuthenticationService`, `RequestContextService`, `AuditService` | `auth/` |
| Dashboard | `/dashboard` | `MigrationModuleController` | `ModuleDataService`, `FinanceiroPainelService` | `dashboard.blade.php` |
| Painel admin | `/admin` | `AdminPainelController` | Serviços de suporte administrativo | `admin/` |
| Financeiro | `/financeiro` | `FinanceiroPainelController`, `FinanceiroLancamentoController` | `FinanceiroPainelService`, `FinanceiroLancamentoService`, `FinanceiroFormDataService` | `financeiro/` |
| Contas bancárias | `/financeiro/contas` | `ContaBancariaController` | `ContaBancariaService`, `MovimentacaoBancariaService` | `financeiro/contas/` |
| Despesas | `/financeiro/despesas` | `DespesaFinanceiraController` | `DespesaFinanceiraService`, `AgendaFinanceiraService` | `financeiro/despesas/` |
| Receitas | `/financeiro/receitas` | `ReceitaFinanceiraController` | `ReceitaFinanceiraService` | `financeiro/receitas/` |
| Categorias financeiras | `/financeiro/categorias` | `CategoriaController` | `CategoriaService` | `financeiro/categorias/` |
| Livro caixa | `/financeiro/livro-caixa` | `LivroCaixaController` | `LivroCaixaService` | `financeiro/livro-caixa/` |
| Relatórios financeiros | `/relatorios/*` | `RelatorioController`, `ComparativoSafrasController`, `RelatorioLancamentosController` | `RelatorioFinanceiroService`, `ComparativoSafrasService`, `RelatorioLancamentosService` | `relatorios/` |
| Orçamento e planejamento | `/orcamento`, `/financeiro/planejamento` | `PlanejamentoFinanceiroController` | `PlanejamentoFinanceiroService` | `orcamento/` |
| Talhões e mapa | `/talhoes`, `/talhoes/mapa` | `TalhaoController` | `TalhaoService` | `talhoes/` |
| Chuvas | `/talhoes/chuva` | `ChuvaController` | `ChuvaService` | `talhoes/chuva/` |
| Atividades de campo | `/talhoes/atividades` | `AtividadeCampoController` | `AtividadeCampoService` | `talhoes/atividades/` |
| Safras | `/safras` | `SafraController` | `SafraService` | `safras/` |
| Colheita | `/colheita` | `ColheitaController` | `ColheitaService` | `colheita/` |
| Fiscal | `/fiscal` | `FiscalConsolidadoController`, `EntradaNfController`, `NotaFiscalController`, `CertificadoDigitalController`, `DocumentoController`, `ProdutorController` | `FiscalConsolidadoService`, `EntradaNfService`, `NotaFiscalXmlService`, `NotaFiscalListagemService`, `CertificadoDigitalService`, `DocumentoService`, `ProdutorService` | `fiscal/` |
| Compras | `/compras/pedidos` | `CompraPedidoController` | `CompraPedidoService` | `compras/` |
| Patrimônio | `/patrimonio` | `PatrimonioController` | `PatrimonioService` | `patrimonio/` |
| Produtos | `/produtos` | `ProdutoController` | `ProdutoService` | `produtos/` |
| Estoque de produção | `/estoque-producao/contratos` | `ContratoController` | `ContratoService` | `estoque-producao/` |
| Propriedades | `/propriedades` | `PropriedadeController`, `GrupoFazendaController` | `PropriedadeService`, `GrupoFazendaService`, `CotacaoSojaService` | `propriedades/` |
| Usuários | `/usuarios` | `UsuarioController` | `UsuarioService`, `AuditService`, `RequestContextService` | `usuarios/` |
| Auditoria | `/auditoria` | `AuditoriaController` | `AuditoriaService`, `AuditService` | `auditoria/` |
| Chat e suporte | `/chat-interno/*`, `/suporte/*` | `ChatInternoController`, `SuporteChatController`, `SuporteAdminController`, `LegacyAjaxController` | `ChatInternoService`, `SuporteChatService`, `SuporteAdminService` | `partials/support-widget.blade.php`, `suporte/` |

## Domain atual

| Domínio | Classes |
| --- | --- |
| `Access` | `ProfileAccess` |
| `Finance` | `FinancialMetrics`, `FinancialWorkflowRules` |
| `Fiscal` | `DocumentCapabilities` |
| `Geo` | `InvalidPolygon`, `PointLocation`, `PolygonGeometry`, `PolygonRelation`, `TalhaoMapCapabilities` |
| `Production` | `ContractRules`, `HarvestFieldCapabilities`, `SafraCapabilities` |
| `Property` | `FarmGroupEligibility` |
| `Purchasing` | `PurchaseOrderCapabilities` |

## Views por módulo

| Pasta | Uso |
| --- | --- |
| `admin` | Painel administrativo do sistema. |
| `auditoria` | Consulta e exportação de auditoria. |
| `auth` | Login. |
| `colheita` | Lançamentos e acompanhamento de colheita. |
| `compras` | Pedidos de compra e notas vinculadas. |
| `estoque-producao` | Contratos e entregas de produção. |
| `financeiro` | Lançamentos, despesas, receitas, contas, agenda, livro caixa e relatórios financeiros. |
| `fiscal` | Entrada de NF, notas, produtores, documentos e certificados. |
| `layouts` | Layout base FarmFort. |
| `modules` | Telas migradas/compatibilidade por módulo. |
| `orcamento` | Planejamento financeiro e orçamento agrícola. |
| `partials` | Componentes compartilhados, stats, tabelas, abas e chat. |
| `patrimonio` | Máquinas, equipamentos e lançamentos vinculados. |
| `produtos` | Cadastro de produtos/insumos. |
| `propriedades` | Fazendas, grupos, usuários e georreferência. |
| `relatorios` | DRE, fluxo de caixa, safra, talhão, KPI e comparativo. |
| `safras` | Cadastro e status de safras. |
| `suporte` | Painel de atendimento/suporte. |
| `talhoes` | Cadastro, mapa, pivô, exclusões, chuva e atividades. |
| `usuarios` | Usuários e permissões por propriedade. |

## Rotas críticas que não são páginas

Estas rotas executam ações e não devem ser acessadas por `GET`:

```text
POST   /talhoes/{talhao}/mapa/exclusoes
DELETE /talhoes/{talhao}/mapa/exclusoes
POST   /talhoes/{talhao}/mapa/pivo
DELETE /talhoes/{talhao}/mapa/pivo
POST   /financeiro/contas/transferencias
POST   /financeiro/despesas/{despesa}/aprovar
POST   /financeiro/despesas/{despesa}/reprovar
POST   /financeiro/despesas/{despesa}/pagar
POST   /financeiro/receitas/{receita}/receber
POST   /usuarios/{usuario}/alternar-status
POST   /propriedades/{propriedade}/alternar-status
```

## Pontos de atenção

- O projeto ainda tem Services grandes por causa da migração do legado. Não refatorar tudo em lote.
- Código novo deve reduzir acoplamento, não aumentar.
- Consultas financeiras, fiscais e de mapa devem sempre filtrar por propriedade quando aplicável.
- Uploads geoespaciais devem continuar protegidos por extensão, tamanho, conteúdo e quantidade de entradas em ZIP/KMZ.
- Qualquer tela nova deve seguir o padrão visual de modais e ações do FarmFort.
- Mensagens ao usuário devem estar em português com acentuação correta.

## Roteiro para alterar um módulo

1. Localizar a rota em `routes/web.php`.
2. Identificar Controller, Service, Blade e testes no mapa acima.
3. Manter Controller fino.
4. Colocar regra de negócio no Service ou Domain.
5. Evitar regra de negócio em Blade.
6. Garantir isolamento por propriedade.
7. Criar ou ajustar teste proporcional ao risco.
8. Rodar validações disponíveis.
9. Versionar em branch própria, nunca direto na `main`.
