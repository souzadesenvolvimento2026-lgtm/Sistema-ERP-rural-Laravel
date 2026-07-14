# Padrões de código FarmFort

Este documento define o padrão obrigatório para código novo e alterações relevantes no FarmFort ERP Rural.

## Base obrigatória

- PHP deve seguir PSR-12.
- Usar Laravel Pint com preset PSR-12 quando PHP estiver disponível:

```bash
vendor/bin/pint --test
```

- Aplicar Clean Code: nomes claros, funções pequenas, baixa duplicação e regra de negócio em lugar explícito.
- Aplicar SOLID de forma prática, sem arquitetura artificial.
- Preservar o padrão de camadas descrito em `docs/architecture.md`.
- Consultar o mapa do projeto em `docs/project-map.md` antes de criar nova tela, rota, Service ou estrutura.

## Nomes

### Classes, métodos e arquivos PHP

- Classes: `StudlyCase`.
- Métodos: `camelCase`, com verbo claro quando executam ação.
- Services: nome do caso de uso ou módulo, por exemplo `ContaBancariaService`.
- Controllers: nome do módulo + `Controller`, por exemplo `UsuarioController`.
- Classes de regra pura em `app/Domain`: nome da regra, capacidade ou política, por exemplo `FinancialWorkflowRules`.

### Variáveis

- Variáveis em `camelCase`.
- Não abreviar palavras de negócio.
- Usar nomes que expliquem o dado:
  - correto: `$usuarioAtivo`, `$dataInicio`, `$saldoAtual`, `$propriedadeId`;
  - evitar: `$usr`, `$prop`, `$vl`, `$tmp`, `$x`.
- Quando o contexto pedir prefixo técnico já conhecido no projeto, usar prefixo curto + nome claro:
  - booleano: `$boAtivo`, `$boPermitido`;
  - data: `$dtInicio`, `$dtFim`;
  - identificador: `$idUsuario`, `$idPropriedade`.
- Não misturar estilos dentro do mesmo método. Se a classe já usa `$propriedadeId`, continue com esse padrão até uma refatoração planejada.

### Banco, rotas e Blade

- Nomes de rotas devem seguir o módulo: `financeiro.contas.index`, `talhoes.mapa.exclusoes.store`.
- Variáveis passadas para Blade devem chegar prontas para apresentação.
- Blade não deve criar regra de negócio, consultar banco ou resolver Service no container.

## Clean Code

- Cada método deve ter uma responsabilidade clara.
- Evitar métodos longos. Se um método mistura validação, consulta, cálculo e formatação, separar.
- Evitar comentários redundantes. Comentário deve explicar decisão, risco ou regra de negócio não óbvia.
- Não comentar o óbvio:
  - evitar: `// soma valores`;
  - aceitar: comentário explicando uma regra fiscal, financeira, geográfica ou compatibilidade com MariaDB estrito.
- Mensagens exibidas ao usuário devem ter acentuação correta.
- Mensagens devem ser diretas e úteis:
  - correto: `Usuário criado com sucesso.`;
  - correto: `Limite de usuários do plano Básico atingido.`;
  - evitar: `Erro`, `Falhou`, `OK`.
- Não capturar exceções silenciosamente. Ao tratar falha inesperada, usar `report($exception)` e devolver erro visível ao fluxo HTTP.

## SOLID aplicado ao projeto

- Single Responsibility: Controller recebe HTTP; Service executa caso de uso; Domain calcula regra; Repository/Model acessa banco; Blade apresenta.
- Open/Closed: novas regras devem ser adicionadas em classes ou métodos próprios, evitando quebrar regras já usadas por outros módulos.
- Liskov: quando usar interfaces ou contratos, implementações devem preservar o comportamento esperado.
- Interface Segregation: não criar interfaces genéricas enormes. Prefira contratos pequenos quando houver mais de uma implementação real.
- Dependency Inversion: Controllers dependem de Services; Services podem depender de Domain/Repository. Blade não depende de Service.

## Banco e consultas

- Toda query deve respeitar o contexto da propriedade, quando aplicável.
- Agregações precisam ser compatíveis com MariaDB 11.8.6 e `ONLY_FULL_GROUP_BY`.
- Em agregações complexas, normalizar dados em subconsulta e agrupar pelo alias externo.
- Não duplicar filtros críticos de propriedade, safra, status financeiro ou permissões em vários lugares sem necessidade.

## Frontend e visual

- Preservar a identidade visual FarmFort.
- Ações negativas ficam à esquerda; ações positivas ficam à direita.
- Modais devem seguir o tema claro/escuro, exceto componentes definidos como identidade fixa do produto.
- JavaScript deve ter funções pequenas e nomes claros.
- CSS deve evitar seletores genéricos que vazem para outros módulos.

## Testes e validação

Antes de liberar alteração relevante, executar quando o ambiente tiver PHP, Composer e Node:

```bash
composer install
npm ci
npm run build
vendor/bin/pint --test
php artisan test
php artisan route:list
```

Se algum comando não puder ser executado no ambiente atual, registrar a limitação no retorno da tarefa.
