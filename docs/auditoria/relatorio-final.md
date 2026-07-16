# Relatório final da auditoria funcional inicial

## Resultado

A auditoria inicial foi concluída sem alteração de código de aplicação.

Arquivos criados nesta etapa:

- `docs/auditoria/inventario-telas-rotas.md`
- `docs/auditoria/matriz-testes-funcionais.md`
- `docs/auditoria/pontas-soltas.md`
- `docs/auditoria/legado-e-desuso.md`
- `docs/auditoria/relatorio-final.md`

## Números consolidados

| Indicador | Total |
| --- | ---: |
| Declarações estáticas de rotas analisadas | 231 |
| Rotas `GET` | 104 |
| Rotas `POST` | 98 |
| Rotas `PUT` | 16 |
| Rotas `DELETE` | 9 |
| Rotas `MATCH` | 4 |
| Controllers encontrados | 43 |
| Services encontrados | 44 |
| Views Blade encontradas | 208 |
| Arquivos de teste encontrados | 22 |

## Telas identificadas

Foram identificadas telas ou famílias funcionais para:

- Login/logout;
- Dashboard;
- Financeiro;
- Contas bancárias;
- Despesas;
- Receitas;
- Agenda financeira;
- Movimentações bancárias;
- Categorias financeiras;
- Compras/Pedidos fiscais;
- Fiscal;
- Patrimônio;
- Safras;
- Talhões e mapa;
- Colheita;
- Produtos;
- Estoque de produção;
- Usuários;
- Propriedades/Fazendas;
- Grupos de fazendas;
- Auditoria;
- Relatórios;
- Orçamento;
- Chat interno;
- Suporte;
- Compatibilidade PHP legado.

## Rotas confirmadas em uso

As famílias abaixo têm evidência forte de uso por menu, redirect, testes ou fluxo recente:

- Autenticação;
- Dashboard;
- Financeiro central;
- Contas bancárias;
- Despesas e receitas;
- Compras/Pedidos fiscais;
- Fiscal;
- Talhões e mapa;
- Safras;
- Patrimônio;
- Usuários;
- Propriedades;
- Auditoria;
- Relatórios;
- Orçamento;
- Chat/Suporte.

## Rotas sem acesso totalmente confirmado nesta etapa

Não foram classificadas como desuso, apenas precisam validação manual:

- `/financeiro/movimentacoes`;
- algumas ações internas de agenda financeira;
- rotas específicas de categorias financeiras;
- algumas exportações;
- rotas completas de criação/edição que coexistem com modais.

## Componentes legados encontrados

Foram encontrados componentes de compatibilidade com o PHP legado:

- `/login.php`;
- `/index.php`;
- `/logout.php`;
- `/pages/{legacy}.php`;
- `/ajax/*`;
- `/pages/ajax/*.php`.

Esses componentes não devem ser removidos sem análise de logs de acesso e teste em homologação.

## Lacunas de teste

As principais lacunas são:

1. Testes automatizados menores por módulo, porque `ExampleTest.php` concentra muitos cenários.
2. Validação de relatórios/exportações no MariaDB 11.8.6 com `ONLY_FULL_GROUP_BY`.
3. Teste específico de auditoria garantindo isolamento total por propriedade.
4. Teste funcional de filtros padronizados, quando o componente reutilizável for criado.
5. Testes de UI para modais de Safras, Propriedades, Pedidos fiscais e Despesas.
6. Testes de compatibilidade de URLs legadas com logs reais.

## Validação humana necessária

Antes de qualquer limpeza ou refatoração, validar em homologação:

- se todos os itens do menu lateral abrem a tela esperada;
- se URLs antigas `/pages/*.php` ainda recebem acesso real;
- se os redirects após salvar/aprovar pedidos e despesas caem na tela correta;
- se chat/suporte funciona para usuários de propriedades diferentes;
- se auditoria exibe somente dados permitidos;
- se filtros, busca e paginação preservam parâmetros.

## Limitações da auditoria

- O PHP não está disponível no `PATH` local; por isso não foi possível executar `php artisan route:list` nem `php artisan test`.
- Não foi feito teste manual em produção.
- Não foi feito backup porque nenhum teste manual com banco foi executado.
- A documentação principal `docs/documentacao-completa-sistema.md` não existe neste checkout local.

## Próxima etapa recomendada

Executar uma segunda etapa em homologação:

1. Fazer backup do banco.
2. Executar `composer install`, `npm ci`, `npm run build`, `php artisan test` e `php artisan route:list`.
3. Exportar `route:list` para complementar o inventário com middleware e nomes finais.
4. Validar manualmente as rotas classificadas como “necessita investigação”.
5. Só depois abrir uma branch de correção para padronização visual, remoção de duplicidades ou limpeza de legado.

