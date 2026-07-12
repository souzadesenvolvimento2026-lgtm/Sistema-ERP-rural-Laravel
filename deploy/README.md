# Publicacao segura do FarmFort

O sistema nunca deve ser publicado diretamente a partir de uma alteracao local. O fluxo obrigatorio e:

1. Trabalhar em uma branch `homologacao/*`.
2. Abrir um pull request e aguardar o CI ficar verde.
3. Validar a homologacao no navegador, inclusive login, dashboard, mapa, exclusoes e modulos principais.
4. Somente depois da aprovacao, integrar o pull request na `main`.
5. No servidor, manter uma `.env.testing` apontando para um banco exclusivo de testes, com MariaDB 11.8 e `ONLY_FULL_GROUP_BY`.
6. Registrar a aprovacao do commit com `./deploy/approve-homologation.sh origin/main`.
7. Publicar exclusivamente com:

```bash
cd /home/higor/Sistema-ERP-rural-Laravel
./deploy/production.sh origin/main
```

O script valida lockfiles, Composer, dependencias, build, testes, rotas, versao do MariaDB e modo estrito antes de alterar a producao. Depois cria backup da aplicacao e do banco, preserva `.env`, `storage/` e `public/uploads/`, executa migrations, corrige permissoes e aplica rollback automatico se algo falhar.

Ao final, o deploy continua pendente ate o teste rapido ser confirmado:

```bash
./deploy/confirm-smoke.sh
```

Durante e depois da publicacao, acompanhe:

```bash
tail -f /var/www/erp-rural/Sistema-ERP-rural-Laravel/storage/logs/laravel.log
sudo tail -f /var/log/nginx/erp-rural.error.log
```
