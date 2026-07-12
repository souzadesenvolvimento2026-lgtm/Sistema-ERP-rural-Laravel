# Arquitetura da aplicação

## Objetivo

O código de apresentação não deve decidir regras de negócio nem acessar persistência. A aplicação segue este fluxo de dependências:

```text
HTTP Request
    -> Controller (validação e orquestração)
        -> Service (caso de uso e persistência)
            -> Domain (políticas e cálculos puros)
        -> View Model/array preparado
            -> Blade (renderização)

View Composer
    -> Domain policy
    -> Blade de layout
```

## Responsabilidades

- `app/Http/Controllers`: valida entrada, chama um caso de uso e escolhe a resposta HTTP. Não deve conter consultas ou cálculos de negócio.
- `app/Services`: concentra casos de uso, consultas, transações e preparação dos dados entregues às views.
- `app/Domain`: contém políticas e cálculos puros, sem dependência de HTTP, sessão, Blade ou banco de dados.
- `app/View/Composers`: prepara navegação, permissões e estado compartilhado de layouts.
- `app/Support`: formatação e contexto técnico reutilizável.
- `resources/views`: somente apresentação, condicionais visuais e estado de formulário já preparado.

## Limites obrigatórios

- Blade não pode usar `DB`, Models ou resolver Services pelo container.
- Percentuais, totais, agrupamentos, autorização e decisões de estado devem ser calculados antes da renderização.
- Ações de workflow devem chegar à Blade como capabilities (`can_*`) ou descritores de ação já preparados e precisam ser revalidadas no backend.
- Consultas agregadas precisam ser compatíveis com MariaDB `11.8.6` no `sql_mode` estrito da produção, incluindo `ONLY_FULL_GROUP_BY`.
- Validações esperadas devem usar as exceções de validação/HTTP do framework.
- Falhas inesperadas tratadas na aplicação devem chamar `report($exception)` e retornar erro visível ao usuário.
- Services de domínio registrados como singleton precisam ser imutáveis e sem estado por requisição.

O teste `tests/Unit/Architecture/BladeBoundaryTest.php` impede regressões de acesso à persistência e ao Service Locator nas Blades.

## Transição do legado

O objetivo é remover gradualmente o acesso direto ao banco dos controllers restantes. Cada migração deve preservar comportamento, adicionar testes no MariaDB compatível com produção e manter o controller restrito ao protocolo HTTP.

O schema legado completo ainda precisa ser versionado como baseline de estrutura para que um MariaDB de CI possa ser criado do zero. Enquanto isso não existir, a suíte de integração não é reproduzível em ambiente limpo e nenhuma alteração deve ser classificada como pronta para produção.
