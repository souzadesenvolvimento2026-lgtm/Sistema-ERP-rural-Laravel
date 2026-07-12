# Sistema ERP Rural Laravel

Migração do FarmFort ERP Rural para Laravel.

## Projeto

Este repositório contém a versão Laravel do sistema ERP rural, com módulos de painel, financeiro, fiscal, compras, patrimônio, safras, talhões, colheita, estoque, usuários, relatórios e suporte.

## Validação local

Os testes automatizados podem ser executados com:

```bash
php artisan test
```

## Deploy em produção

Use exclusivamente o script versionado para atualizar o servidor. Ele preserva o
`.env`, o diretório `storage`, uploads e dependências, cria backup antes da troca,
executa migrations e mantém os caches com o usuário correto do PHP-FPM.

```bash
cd /home/higor/Sistema-ERP-rural-Laravel
./deploy/production.sh origin/main
```

O deploy exige `sudo` e para imediatamente se uma validação falhar. Os backups
ficam em `/var/backups/farmfort/<data-hora>`.
