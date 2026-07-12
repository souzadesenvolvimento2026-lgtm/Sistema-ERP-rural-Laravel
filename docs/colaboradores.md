# Guia para colaboradores

Este projeto está em desenvolvimento ativo. Por enquanto, o código mais atualizado não fica na `main`; ele fica na branch:

```text
refactor/separacao-regras-negocio
```

A `main` deve ser tratada como branch estável/aprovada. Não use a `main` para desenvolvimento diário sem alinhamento prévio.

## Primeiro acesso

Clone o repositório e entre na branch de desenvolvimento:

```bash
git clone https://github.com/souzadesenvolvimento2026-lgtm/Sistema-ERP-rural-Laravel.git
cd Sistema-ERP-rural-Laravel
git checkout refactor/separacao-regras-negocio
git pull --ff-only origin refactor/separacao-regras-negocio
```

## Antes de começar qualquer alteração

Sempre atualize sua cópia local:

```bash
git checkout refactor/separacao-regras-negocio
git pull --ff-only origin refactor/separacao-regras-negocio
```

Se esse comando falhar por conflito ou alteração local pendente, pare e resolva antes de programar. Não misture mudanças de assuntos diferentes no mesmo commit.

## Depois de alterar o código

Confira os arquivos modificados:

```bash
git status -sb
```

Rode validação proporcional à alteração. Para alterações de tela/mapa, no mínimo:

```bash
php artisan test tests/Unit/Architecture/TalhaoMapUiTest.php tests/Feature/TalhaoExcludedAreaTest.php
php artisan route:list --path=talhoes
```

Para alteração mais ampla:

```bash
composer install
npm ci
npm run build
php artisan test
php artisan route:list
```

Faça commit e envie para o GitHub:

```bash
git add caminho/do/arquivo
git commit -m "Descreva a alteração"
git pull --rebase origin refactor/separacao-regras-negocio
git push origin refactor/separacao-regras-negocio
```

## Publicar no servidor de desenvolvimento

Depois que a alteração estiver no GitHub, acesse o servidor e rode:

```bash
cd /home/higor/Sistema-ERP-rural-Laravel
./deploy/update-development.sh origin/refactor/separacao-regras-negocio
```

O deploy só deve ser considerado concluído quando aparecer:

```text
Deploy dev concluido
Commit publicado: ...
```

O script publica em:

```text
/var/www/erp-rural/Sistema-ERP-rural-Laravel
```

E grava o commit publicado em:

```text
/var/www/erp-rural/Sistema-ERP-rural-Laravel/.deploy-commit
```

Para conferir:

```bash
cat /var/www/erp-rural/Sistema-ERP-rural-Laravel/.deploy-commit
```

## Regras rápidas

- Trabalhe na branch `refactor/separacao-regras-negocio`.
- Não faça push direto na `main`.
- Antes de mexer, rode `git pull --ff-only`.
- Depois de commitar, rode `git pull --rebase` antes do `git push`.
- Se alterar `package.json`, também versionar `package-lock.json`.
- O servidor de desenvolvimento atualiza pelo script `./deploy/update-development.sh`.
- Depois do deploy, teste no navegador com `Ctrl + F5`.

