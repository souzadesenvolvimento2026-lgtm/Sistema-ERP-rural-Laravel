# Matriz de testes funcionais

Auditoria funcional inicial, sem execução de testes manuais em produção.

## Resultado da leitura dos testes

Foram encontrados 22 arquivos de teste em `tests`. A cobertura está concentrada em três frentes:

- regras de domínio em `tests/Unit/Domain`;
- regras de UI/arquitetura em `tests/Unit/Architecture`;
- fluxos funcionais grandes em `tests/Feature/ExampleTest.php` e `tests/Feature/TalhaoExcludedAreaTest.php`.

O comando `php artisan test` não foi executado localmente porque o PHP não está disponível no `PATH` deste ambiente Windows.

## Matriz por módulo

| Módulo | Fluxos cobertos por teste | Testes encontrados | Situação | Lacunas recomendadas |
| --- | --- | --- | --- | --- |
| Login/Dashboard | Login, redirect, dashboard e regras de comprador/receitas | `DashboardBusinessRulesTest`, `ExampleTest` | Cobertura boa | Executar no MariaDB de homologação com SQL mode da produção. |
| Talhões/Mapa | Abrir mapa, exclusões, pivô, rotas POST/DELETE, geometria | `TalhaoExcludedAreaTest`, `TalhaoMapUiTest`, `PolygonGeometryTest`, `TalhaoMapCapabilitiesTest` | Cobertura boa | Validar manualmente importados KML/KMZ/SHP e talhões unificados. |
| Financeiro | Lançamentos, despesas, receitas, contas, transferências, pagamento com conta | `ExampleTest`, `FinanceiroUiTest`, `FinancialWorkflowRulesTest`, `FinancialMetricsTest` | Cobertura média | Criar testes menores por controller/service; validar tela `/financeiro/despesas` e modal de edição. |
| Compras/Pedidos fiscais | Criar pedido, NF, divergência, aprovação, rejeição, geração financeira | `ExampleTest`, `PurchaseOrderCapabilitiesTest` | Cobertura média/boa | Separar testes de pedido em arquivo próprio e validar com banco real. |
| Fiscal | Notas, XML, aprovação/rejeição, certificados, documentos | `ExampleTest`, `DocumentCapabilitiesTest` | Cobertura média | Testar uploads reais e arquivos inválidos em homologação. |
| Safras | Status, bloqueios, ações permitidas | `SafraCapabilitiesTest` | Cobertura de domínio | Criar testes funcionais para modal Nova/Editar Safra e seleção de talhões. |
| Patrimônio | Cadastro, custos, lançamentos e vínculo com despesa | `ExampleTest` | Cobertura parcial | Criar testes específicos de modal e lançamento. |
| Usuários/Permissões | Perfis, isolamento por propriedade, acesso efetivo | `ProfileAccessTest`, `SecurityHardeningTest`, `ExampleTest` | Cobertura boa | Expandir para remoção de usuário da propriedade e vínculos proibidos entre propriedades. |
| Propriedades/Admin | Listagem, criação/edição, liberação por senha, isolamento | `SecurityHardeningTest`, `FarmGroupEligibilityTest`, `ExampleTest` | Cobertura média | Testar visual/admin, limite de plano e reativação/desativação. |
| Auditoria | Login/logout, detalhes, exportação, escopo por propriedade | `ExampleTest`, `SecurityHardeningTest` | Cobertura média | Criar teste específico garantindo que auditoria admin não vaza para propriedade errada. |
| Relatórios | DRE, fluxo de caixa, comparativo, exportação | `ExampleTest` | Cobertura parcial | Criar testes para exportação PDF/Excel/CSV e dados sem safra. |
| Chat/Suporte | AJAX moderno e legado | `ExampleTest` | Cobertura parcial | Validar som, favicon, leitura e notificação visual no navegador. |
| Estoque de produção | Contratos e entregas | `ContractRulesTest` | Cobertura de domínio | Criar testes funcionais de tela e rotas. |

## Testes manuais pendentes em homologação

Antes de qualquer remoção ou alteração estrutural, executar:

1. Backup do banco de homologação.
2. Login com gestor, financeiro, visualizador e usuário operacional.
3. Navegação completa pelo menu lateral.
4. Fluxo de pedido fiscal com e sem NF.
5. Aprovação/rejeição de pedido fiscal.
6. Geração de despesa financeira a partir do pedido.
7. Baixa de pagamento com conta real e saldo insuficiente.
8. Cadastro, edição e transferência entre contas.
9. Auditoria filtrada por propriedade.
10. Talhões: mapa, exclusão, pivô, unificação e edição de polígono.
11. Safras: modal, seleção de talhões e ações da safra.
12. Compatibilidade de URLs antigas `/pages/*.php`.

## Observação de manutenção

`tests/Feature/ExampleTest.php` concentra muitos fluxos. Funciona como rede de segurança, mas dificulta manutenção. Recomenda-se quebrar gradualmente em arquivos por módulo, sem perder cobertura.

