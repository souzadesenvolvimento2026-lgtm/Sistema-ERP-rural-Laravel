# Windows dev setup

Este projeto pode usar runtimes portateis dentro de `work/runtime`, que e ignorado pelo Git. Isso evita depender do `PATH` global do Windows e nao instala servico permanente.

## Ativar atalhos na sessao

```powershell
. .\scripts\dev\activate.ps1
```

Depois disso, a sessao passa a reconhecer `php`, `composer`, `node`, `npm`, `artisan` e `gh` usando os binarios portateis quando eles existirem.

## Validacao rapida

Use durante desenvolvimento:

```powershell
.\scripts\dev\validate.ps1 -Mode fast
```

Esse modo reaproveita `vendor/` e `node_modules/` quando ja existem, roda build, checagem de sintaxe do mapa, testes unitarios e `route:list`.

## Gate completo local

Use antes de abrir ou atualizar PR:

```powershell
.\scripts\dev\validate.ps1 -Mode full
```

Esse modo inicia MariaDB `11.8.6` portatil em `127.0.0.1:3307`, executa os gates obrigatorios e para o banco ao final.

Para manter o banco ligado durante uma sessao de trabalho:

```powershell
.\scripts\dev\validate.ps1 -Mode full -KeepDatabase
```

## Sincronizar schema local com o servidor

Quando o banco local `farmflow_test` estiver sem tabelas ou desatualizado, sincronize somente o schema da aplicação publicada. O script nao copia dados de produção:

```powershell
.\scripts\dev\sync-schema.ps1
```

Ele usa SSH por chave, recria o banco local e importa apenas estrutura, rotinas, triggers e eventos.

Controle manual do banco:

```powershell
.\scripts\dev\test-db.ps1 start
.\scripts\dev\test-db.ps1 status
.\scripts\dev\test-db.ps1 stop
```

O banco criado usa:

- database: `farmflow_test`
- usuario: `root`
- senha: `root`
- porta: `3307`
- `sql_mode`: `ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION`

## GitHub CLI

O `gh` portatil fica em `work/runtime/gh/bin/gh.exe`. Ele nao roda em segundo plano. Para criar PR pelo terminal, autentique uma unica vez:

```powershell
.\work\runtime\gh\bin\gh.exe auth login
```

## Fluxo seguro para publicar branch

Depois de programar, use:

```powershell
.\scripts\dev\release.ps1 -SyncSchema -Validation full
```

Se passou e voce quiser commitar todos os arquivos alterados intencionalmente e subir a branch atual:

```powershell
.\scripts\dev\release.ps1 -Validation full -CommitMessage "Prepara fluxo seguro de desenvolvimento" -StageAll -Push
```

O script bloqueia `push` direto em `main`. Para evitar misturar alteracoes, sem `-StageAll` ele so commita arquivos que voce ja adicionou explicitamente com `git add`.

Deploy de produção continua exigindo main aprovada, CI/homologação e confirmação explicita:

```powershell
.\scripts\dev\release.ps1 -Validation full -DeployProduction -ConfirmProduction
```
