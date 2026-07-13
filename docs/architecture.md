# Arquitetura da aplicação

Este documento define o padrão oficial de desenvolvimento do FarmFort ERP Rural em Laravel. Ele deve ser seguido por colaboradores humanos e agentes automatizados ao criar, alterar ou revisar qualquer tela, rota ou regra de negócio.

## Objetivo

Separar claramente interface, fluxo HTTP, regra de negócio e acesso ao banco, preservando velocidade de desenvolvimento sem deixar regra crítica escondida dentro de Blade ou espalhada em consultas duplicadas.

O fluxo desejado é:

```text
HTTP Request
    -> Controller
        -> Form Request ou validação de entrada
        -> Service / Use Case
            -> Domain / Rules / Entity
            -> Repository / Model
                -> Banco de dados
        -> View Model / array preparado
            -> Blade
```

## Responsabilidades por camada

### View / Blade

Arquivos em `resources/views`.

Responsabilidade:

- renderizar telas, campos, botões, tabelas e modais;
- exibir mensagens, erros e estados visuais;
- usar dados já preparados pelo backend;
- executar apenas condicionais visuais simples.

Não deve:

- acessar `DB`, Models, Repositories ou Services;
- calcular regra de negócio;
- decidir permissão real;
- fazer agregações financeiras;
- conter SQL, query builder ou regra de workflow.

### Controller

Arquivos em `app/Http/Controllers`.

Responsabilidade:

- receber a requisição HTTP;
- validar entrada básica ou chamar Form Request;
- checar autorização;
- chamar um Service/Use Case;
- escolher resposta HTTP: view, redirect, JSON ou download.

Não deve:

- conter query builder;
- concentrar cálculos financeiros, fiscais ou agrícolas;
- montar relatórios complexos diretamente;
- misturar regra de negócio com protocolo HTTP.

### Service / Use Case

Arquivos atuais em `app/Services` e, para novos módulos ou refatorações, preferencialmente em subpastas por módulo, por exemplo:

```text
app/Services/Financeiro/
app/Services/Talhoes/
app/Services/Fiscal/
```

Responsabilidade:

- executar casos de uso da aplicação;
- aplicar regras de negócio;
- coordenar transações;
- chamar Repositories/Models para ler e gravar dados;
- preparar dados para views e relatórios;
- registrar auditoria quando a regra exigir.

Durante a transição, ainda existem Services com query builder direto. Eles não precisam ser refatorados todos de uma vez, mas qualquer nova alteração relevante deve caminhar para separar consultas em Repository/Model.

### Domain / Rules / Entity

Arquivos em `app/Domain`.

Responsabilidade:

- regras puras, cálculos e políticas;
- validações de domínio que não dependem de HTTP;
- objetos auxiliares de regra de negócio;
- classes de elegibilidade/capabilidade.

Não deve depender de:

- sessão;
- request;
- Blade;
- banco de dados;
- facades de infraestrutura, exceto quando a classe for explicitamente uma ponte técnica.

### Repository / Model

Camada desejada para acesso ao banco. Para novas implementações ou refatorações por módulo, usar:

```text
app/Repositories/<Modulo>/
```

Exemplos:

```text
app/Repositories/Financeiro/ContaRepository.php
app/Repositories/Financeiro/TransferenciaRepository.php
app/Repositories/Talhoes/TalhaoRepository.php
```

Responsabilidade:

- encapsular `DB::table`, Eloquent ou SQL;
- centralizar consultas reutilizáveis;
- evitar duplicação de filtros por propriedade, safra, status e permissões;
- manter queries agregadas compatíveis com MariaDB 11.8.6 e `ONLY_FULL_GROUP_BY`;
- devolver DTOs, arrays, Collections ou objetos simples para os Services.

Controllers e Blades não devem chamar Repository diretamente. O caminho normal é Controller → Service → Repository.

### Support / Helpers

Arquivos em `app/Support`.

Responsabilidade:

- formatação de data, dinheiro, decimal e rótulos;
- contexto técnico compartilhado;
- helpers reutilizáveis sem regra de negócio pesada.

Não duplicar helpers existentes. Antes de criar algo novo, verificar:

- `app/Support/FarmFormat.php`
- `app/Support/FarmContext.php`
- `app/Support/ModuleCatalog.php`

## Organização por módulo

Views já seguem subpastas por módulo e devem continuar assim:

```text
resources/views/financeiro/
resources/views/talhoes/
resources/views/fiscal/
resources/views/compras/
resources/views/safras/
```

Novas classes de backend também devem seguir módulo quando fizer sentido:

```text
app/Services/Financeiro/
app/Repositories/Financeiro/
app/Domain/Financeiro/
```

Para código legado já existente, não mover arquivos apenas por estética. Mover somente quando houver alteração funcional, teste associado e ganho claro de manutenção.

## Limites obrigatórios

- Blade não pode usar `DB`, Models, Repositories ou resolver Services pelo container.
- Controllers não devem conter consultas SQL/query builder.
- Regras de negócio devem ficar em Service/Domain.
- Acesso ao banco deve caminhar para Repository/Model.
- Permissões devem ser revalidadas no backend, mesmo que a Blade esconda o botão.
- Consultas agregadas precisam ser compatíveis com MariaDB `11.8.6` no `sql_mode` estrito da produção, incluindo `ONLY_FULL_GROUP_BY`.
- Evitar agrupamento direto por expressões calculadas; quando necessário, normalizar em subconsulta e agrupar pelo alias externo.
- Falhas inesperadas tratadas na aplicação devem chamar `report($exception)` e retornar erro visível ao usuário.
- Services registrados como singleton precisam ser imutáveis e sem estado por requisição.

O teste `tests/Unit/Architecture/BladeBoundaryTest.php` impede regressões de acesso à persistência e ao Service Locator nas Blades.

## Estratégia de transição

A migração para camadas deve ser progressiva.

Não fazer refatoração geral em massa apenas para “organizar”. O sistema está em desenvolvimento ativo e precisa continuar rápido. O padrão correto é:

1. mexeu em uma tela ou regra;
2. identificou query duplicada ou regra misturada;
3. move a consulta para Repository/Model;
4. mantém a regra no Service/Domain;
5. mantém Controller fino;
6. cria ou ajusta teste;
7. valida e sobe no branch de desenvolvimento.

Prioridade inicial de refatoração:

1. Financeiro;
2. Talhões e mapa;
3. Fiscal;
4. Compras;
5. Demais módulos conforme forem alterados.

## Exemplo recomendado

```text
ContaBancariaController
    -> ContaBancariaService
        -> ContaRepository
        -> TransferenciaRepository
        -> FinancialBalanceRules
    -> resources/views/financeiro/contas/index.blade.php
```

Nesse desenho:

- Controller só lida com HTTP;
- Service aplica a regra;
- Repository consulta e grava;
- Domain calcula/valida regra reutilizável;
- Blade apenas mostra card, tabela e modal.

## Observação sobre o legado

O projeto ainda tem acesso direto ao banco em vários Services porque veio de migração do legado. Isso é aceito temporariamente.

A regra a partir de agora é: código novo e alterações relevantes devem melhorar a separação, não piorar. Refatoração total só deve ocorrer por módulo, com teste e validação, nunca em lote gigante sem necessidade funcional.
