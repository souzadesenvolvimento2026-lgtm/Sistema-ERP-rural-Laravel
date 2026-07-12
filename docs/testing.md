# Testes e gates de qualidade

## Banco de integração

Os feature tests usam exclusivamente um banco MariaDB isolado cujo nome termina em `_test`. O padrão atual é `farmflow_test`.

A referência de produção versionada é:

- servidor: `11.8.6-MariaDB-0+deb13u1 from Debian`;
- versão funcional exigida: `11.8.6`;
- conector Laravel atual: `mysql`, até confirmação do valor usado no `.env` de produção;
- `sql_mode`: `ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION`.

Antes de executar a suíte, configure:

```dotenv
DB_CONNECTION=mysql
DB_DATABASE=farmflow_test
DB_PRODUCTION_CONNECTION=mysql
DB_PRODUCTION_VENDOR=mariadb
DB_PRODUCTION_VERSION=11.8.6
DB_PRODUCTION_SQL_MODE="ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION"
```

A infraestrutura de testes falha imediatamente quando:

- o conector Laravel diverge do conector versionado da produção;
- o nome do banco não termina em `_test`;
- o servidor conectado não é MariaDB;
- a versão `major.minor.patch` diverge de `11.8.6`;
- o conjunto completo de `sql_mode` diverge da produção.

O guard consulta `DATABASE()`, `VERSION()` e `@@SESSION.sql_mode` antes de qualquer trait de migration/transação. Variáveis ou `DB_URL` externos não podem contornar a exigência de que o banco efetivamente conectado termine em `_test`. Os valores de referência ficam forçados no `phpunit.xml`, e os testes também recusam configuração em cache e Oracle MySQL.

O banco de testes deve conter o mesmo schema da produção. As fixtures de cada cenário criam seus próprios usuários, propriedades e vínculos; elas não podem reutilizar as primeiras linhas de uma base compartilhada.

## Validação local

```bash
composer install
npm ci
npm run build
php artisan test
php artisan route:list
```

Antes de qualquer liberação, executar também:

```bash
composer validate --no-check-publish
```

Se o schema legado, o MariaDB `11.8.6` ou qualquer serviço obrigatório estiver indisponível, registrar o bloqueio e não aprovar a mudança para homologação ou produção.
