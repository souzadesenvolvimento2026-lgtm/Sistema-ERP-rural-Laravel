# Regras do projeto FarmFort ERP Rural

Este arquivo se aplica a todo o repositório. As regras abaixo são obrigatórias para qualquer pessoa ou agente que altere, valide, publique ou monitore esta aplicação.

## Fluxo obrigatório

- Nunca publicar diretamente em `origin/main`. Toda alteração deve passar por branch própria, CI, build, testes e homologação antes de chegar à `main`.
- Nunca considerar uma atualização pronta para produção sem aprovação explícita da homologação.
- Não executar `push`, merge, deploy, migration ou qualquer alteração de estado em produção sem solicitação expressa e sem cumprir todos os gates deste documento.
- Preservar alterações locais existentes e não misturar mudanças não relacionadas no mesmo commit.

## Arquitetura obrigatória

- Seguir o padrão documentado em `docs/architecture.md`.
- Blade deve ser somente apresentação: telas, campos, tabelas, modais e estados visuais.
- Controllers devem ficar finos: receber HTTP, validar entrada, autorizar, chamar Service/Use Case e retornar resposta.
- Regras de negócio devem ficar em Service/Domain.
- Acesso ao banco deve caminhar para Repository/Model, preferencialmente organizado por módulo.
- Não refatorar o sistema inteiro de uma vez apenas por estética. A separação deve ser progressiva por módulo/tela alterada, preservando comportamento e testes.
- Código novo ou alteração relevante não deve aumentar acoplamento entre View, Controller, regra de negócio e banco.

## Dependências e lockfiles

- `composer.lock` e `package-lock.json` são arquivos obrigatórios e devem permanecer versionados.
- Sempre que `package.json` for alterado, executar:

```bash
npm install
git add package.json package-lock.json
git commit -m "Atualiza dependências do frontend"
```

- O deploy deve falhar antes de alterar a produção se faltarem `composer.lock`, `package-lock.json` ou o `.env` no destino.

## Validação antes de liberar uma atualização

Executar, nesta ordem, usando as mesmas versões previstas para produção:

```bash
composer install
npm ci
npm run build
php artisan test
php artisan route:list
```

O script de deploy também deve executar, antes da troca:

```bash
composer validate --no-check-publish
npm ci
npm run build
php artisan test
```

- O build deve ocorrer em uma pasta temporária. Arquivos só podem ser copiados para produção depois que todas as validações passarem.
- Não executar migrations antes de dependências, build e testes passarem.
- Se uma validação obrigatória não puder ser executada no ambiente atual, declarar a limitação e não afirmar que a mudança está pronta para produção.

## Banco de dados e SQL

- O servidor de produção é MariaDB `11.8.6-MariaDB-0+deb13u1 from Debian`. Testes de integração devem usar MariaDB `11.8.6`, nunca Oracle MySQL como substituto.
- Até a confirmação do `.env` de produção, o conector Laravel versionado permanece `DB_CONNECTION=mysql`; o gate valida separadamente conector, fornecedor e versão real do servidor.
- A sessão de testes deve reproduzir exatamente: `ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION`.
- Toda consulta alterada que contenha agregações deve ser validada nesse MariaDB.
- Evitar agrupamento direto por expressões calculadas suscetíveis ao modo estrito. Quando necessário, normalizar os dados em uma subconsulta e agrupar pelo alias externo.

## Cobertura obrigatória do dashboard

Manter testes automatizados para:

- login e redirecionamento após autenticação;
- carregamento do dashboard;
- receitas com comprador preenchido;
- receitas sem comprador;
- receitas com comprador vazio;
- agrupamento e soma por comprador;
- banco sem receitas;
- safra ativa e ausência de safra ativa.

## Cobertura obrigatória das áreas excluídas dos talhões

Manter testes automatizados para:

- abertura do mapa;
- criação da exclusão;
- exclusão dentro do polígono;
- tentativa fora do polígono;
- limpeza das exclusões;
- cálculo das áreas bruta, excluída e líquida;
- talhão inexistente ou pertencente a outra propriedade.

Testar cada rota alterada com o método HTTP correto. Estas rotas não aceitam `GET`:

```text
POST   /talhoes/{talhao}/mapa/exclusoes
DELETE /talhoes/{talhao}/mapa/exclusoes
```

## Deploy de produção

Usar exclusivamente o script versionado:

```bash
cd /home/higor/Sistema-ERP-rural-Laravel
./deploy/production.sh origin/main
```

Durante o deploy, preservar obrigatoriamente:

```text
.env
storage/
public/uploads/
```

- Manter proprietário e grupo `www-data:www-data` onde requerido pelo PHP-FPM.
- `storage/` e `bootstrap/cache/` devem continuar graváveis pelo PHP.
- Criar backup antes da troca e manter rollback automático.
- Se qualquer etapa falhar depois da entrada em manutenção, restaurar o backup e executar `php artisan up`.

## Tratamento de falhas

Não capturar exceções silenciosamente. Ao tratar uma falha, registrar a exceção e devolver um erro visível ao fluxo HTTP, conforme aplicável:

```php
report($e);

return redirect()
    ->back()
    ->withErrors($e->getMessage());
```

## Verificação e monitoramento pós-deploy

Depois do deploy, executar obrigatoriamente um smoke test:

- abrir o login;
- autenticar;
- confirmar o dashboard;
- abrir o mapa dos talhões;
- salvar uma exclusão de teste;
- verificar os principais módulos;
- consultar os logs.

Monitorar imediatamente:

```bash
tail -f /var/www/erp-rural/Sistema-ERP-rural-Laravel/storage/logs/laravel.log
sudo tail -f /var/log/nginx/erp-rural.error.log
```

O deploy não está concluído apenas porque o script terminou. Login, dashboard e rotas críticas precisam ser confirmados no navegador.
