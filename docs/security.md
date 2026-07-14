# Segurança operacional do FarmFort

Este checklist deve ser usado em homologação e no servidor antes de considerar uma atualização segura.

## Login

- O login usa limite por e-mail e IP: 5 tentativas inválidas em 60 segundos.
- Não remover o `RateLimiter` do `AuthSessionController` sem substituir por proteção equivalente.
- Mensagens de erro devem continuar genéricas para credencial inválida.

## Headers HTTP

O middleware `App\Http\Middleware\SecurityHeaders` adiciona headers seguros no grupo `web`:

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy` bloqueando câmera, microfone, pagamento e USB
- `Strict-Transport-Security` apenas quando a requisição já chega por HTTPS

Não aplicar CSP rígido sem testar mapas, gráficos, assets, anexos e scripts do sistema.

## `.env` do servidor

Conferir no servidor, sem versionar segredo:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://www.farmfort.com.br
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_ENCRYPT=true
```

Depois de ajustar:

```bash
php artisan config:clear
php artisan config:cache
```

## Uploads

- KML/KMZ/SHP/ZIP geoespacial: no máximo 20 MB.
- O backend valida extensão, arquivo vazio e quantidade de entradas internas no ZIP/KMZ.
- Entradas internas com caminho absoluto, `..` ou bytes nulos são ignoradas.
- Anexos de chat, suporte, documentos, patrimônio e financeiro devem manter validação de tipo, tamanho e `realpath` antes de download.
- O servidor web não deve executar nada dentro de `public/uploads` ou `../uploads`.

## Isolamento por propriedade

- Usuário operacional não pode ser vinculado a duas propriedades.
- Chat interno só lista e envia mensagens para usuários da mesma propriedade/grupo autorizado.
- Serviços financeiros sempre filtram por `propriedade_id`.
- Downloads de anexos precisam validar usuário, propriedade/conversa e caminho real dentro da pasta esperada.

## Auditoria de permissões

Ao alterar módulos críticos, revisar:

- Financeiro: criação/edição de contas, transferências, lançamentos, relatórios e anexos.
- Propriedades: criação, edição, desativação/reativação, vínculo/remoção de usuários.
- Usuários: perfis, vínculos por propriedade/grupo e bloqueio de usuários de sistema.
- Anexos: upload, download, expiração e remoção segura.

Toda falha tratada deve chamar `report($exception)` antes de retornar erro visível ao usuário.
