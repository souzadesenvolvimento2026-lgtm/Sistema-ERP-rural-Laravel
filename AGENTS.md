# Regras obrigatorias de publicacao

Estas regras valem para todo o repositorio Laravel do FarmFort.

## Fluxo

- Nunca publicar uma alteracao diretamente na `origin/main` ou na producao.
- Trabalhar em uma branch `homologacao/*`, abrir pull request e aguardar CI, build, testes e homologacao.
- A `main` so recebe alteracoes aprovadas.
- Em producao, usar exclusivamente `./deploy/production.sh origin/main`.
- O termino do script nao conclui a publicacao. Confirmar o teste rapido com `./deploy/confirm-smoke.sh`.

## Dependencias e validacao

- Manter `composer.lock` e `package-lock.json` versionados.
- Depois de alterar `package.json`, executar `npm install` e versionar os dois arquivos npm.
- Antes de liberar qualquer alteracao, executar `composer install`, `npm ci`, `npm run build`, `php artisan test` e `php artisan route:list`.
- Testar com a mesma linha do MariaDB da producao e com `ONLY_FULL_GROUP_BY` habilitado.
- Consultas agregadas devem ser validadas no modo estrito. Normalizar expressoes calculadas em subconsulta e agrupar pelo alias externo quando necessario.

## Cobertura obrigatoria

- Dashboard: login, redirecionamento, carregamento, comprador preenchido, nulo e vazio, soma agrupada, banco sem receitas, safra ativa e propriedade sem safra.
- Exclusoes de talhao: abertura do mapa, criacao interna, tentativa externa, limpeza, areas bruta/excluida/liquida, talhao inexistente e talhao de outra propriedade.
- Respeitar os metodos HTTP: `POST /talhoes/{talhao}/mapa/exclusoes` e `DELETE /talhoes/{talhao}/mapa/exclusoes`. Essa rota nao e uma pagina GET.

## Seguranca da publicacao

- Validar tudo em pasta temporaria antes de copiar arquivos para producao.
- Parar antes de alterar producao se faltarem `composer.lock`, `package-lock.json`, `.env`, `storage/` ou `public/uploads/`.
- Nao executar migrations antes de dependencias, build e testes passarem.
- Preservar `.env`, `storage/` e `public/uploads/`.
- Manter `storage/`, `bootstrap/cache` e `public/uploads/` gravaveis por `www-data:www-data`.
- Criar backup da aplicacao e do banco antes da manutencao.
- Manter rollback automatico e sempre executar `php artisan up` depois de uma falha.
- Nao silenciar excecoes. Usar `report($e)` antes de retornar o erro ao usuario.

## Depois da copia

- Abrir login, autenticar, carregar dashboard e mapa, criar/remover uma exclusao de teste e abrir os principais modulos.
- Consultar imediatamente `storage/logs/laravel.log` e `/var/log/nginx/erp-rural.error.log`.
