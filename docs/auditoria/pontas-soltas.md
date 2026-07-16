# Pontas soltas e riscos encontrados

Esta lista registra pontos que precisam investigação antes de remover, renomear ou refatorar qualquer rota/tela.

## 1. Documento principal ausente

O prompt cita `docs/documentacao-completa-sistema.md` como documentação principal, mas o arquivo não existe neste checkout local.

Risco:

- documentação funcional pode estar no servidor ou em outro diretório;
- decisões futuras podem ficar sem referência única.

Próxima ação segura:

- localizar o arquivo no servidor ou confirmar se deve ser recriado a partir dos documentos atuais.

## 2. Rotas modernas e rotas legadas convivem

Há compatibilidade para:

- `/login.php`;
- `/index.php`;
- `/logout.php`;
- `/pages/{legacy}.php`;
- `/ajax/*`;
- `/pages/ajax/*.php`.

Risco:

- remover uma rota aparentemente antiga pode quebrar favoritos, links externos ou chamadas AJAX herdadas.

Próxima ação segura:

- consultar logs de acesso em homologação/servidor antes de classificar como desuso real.

## 3. `ModuleCatalog` ainda aponta para telas genéricas

`app/Support/ModuleCatalog.php` usa `modules.show` para vários módulos, enquanto `routes/web.php` possui rotas concretas como `/financeiro`, `/safras`, `/talhoes`, `/patrimonio` e outras.

Risco:

- duplicidade visual ou navegação por hub genérico;
- inconsistência entre menu lateral e telas finais.

Próxima ação segura:

- validar no navegador quais módulos ainda passam por `ModuleController@show` e quais já abrem tela dedicada.

## 4. Telas completas e modais coexistem em alguns fluxos

Fluxos recentes pedem uso de janelas flutuantes, mas há rotas de página completa para criação/edição:

- despesas e receitas;
- pedidos fiscais;
- safras;
- propriedades;
- patrimônio.

Risco:

- o usuário pode cair em tela completa por redirect ou link antigo, mesmo quando o fluxo esperado é modal.

Próxima ação segura:

- mapear cada redirect após salvar/aprovar para confirmar se retorna à tela principal correta.

## 5. Auditoria precisa de validação por escopo

A auditoria foi reforçada, mas ainda precisa validação manual e automatizada para:

- impedir vazamento entre propriedades;
- permitir eventos administrativos apenas quando vinculados à propriedade afetada;
- rastrear liberação de edição por senha.

Próxima ação segura:

- criar teste dedicado para auditoria por propriedade e revisar dados exibidos na tela.

## 6. Filtros possuem implementação visual repetida

Há filtros em Financeiro, Fiscal, Compras, Patrimônio, Safras, Talhões, Colheita, Estoques, Usuários e Relatórios.

Risco:

- cada tela evolui com espaçamentos, campos, botões e comportamento diferentes;
- manutenção visual fica lenta.

Próxima ação segura:

- criar componente Blade reutilizável de filtros em etapa própria, sem misturar com esta auditoria.

## 7. Agregações e relatórios exigem MariaDB real

Relatórios financeiros e dashboard têm consultas agregadas. O projeto exige MariaDB 11.8.6 com `ONLY_FULL_GROUP_BY`.

Risco:

- consultas funcionarem em SQLite ou MySQL diferente e quebrarem em produção/homologação.

Próxima ação segura:

- executar testes no MariaDB com o mesmo `sql_mode` informado para produção.

## 8. Cobertura automatizada concentrada

`tests/Feature/ExampleTest.php` cobre muitos fluxos em um arquivo único.

Risco:

- difícil localizar falha;
- difícil manter quando telas mudam visualmente;
- alto custo para novos programadores.

Próxima ação segura:

- quebrar por módulo em etapas pequenas: Compras, Financeiro, Fiscal, Talhões, Safras, Propriedades e Auditoria.

