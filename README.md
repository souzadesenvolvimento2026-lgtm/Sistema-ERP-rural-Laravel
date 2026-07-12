# Sistema ERP Rural Laravel

Migração do FarmFort ERP Rural para Laravel.

## Projeto

Este repositório contém a versão Laravel do sistema ERP rural, com módulos de painel, financeiro, fiscal, compras, patrimônio, safras, talhões, colheita, estoque, usuários, relatórios e suporte.

## Validação local

Consulte antes de alterar o projeto:

- [Arquitetura e limites entre camadas](docs/architecture.md)
- [Testes e gates de qualidade](docs/testing.md)
- [Política obrigatória de produção](AGENTS.md)

Com o MariaDB de testes configurado na mesma versão da produção, execute:

```bash
composer install
npm ci
npm run build
php artisan test
php artisan route:list
```

O banco de integração deve ser isolado, terminar em `_test` e reproduzir o MariaDB `11.8.6` e o `sql_mode` da produção, incluindo `ONLY_FULL_GROUP_BY`.
