# Legado, compatibilidade e suspeitas de desuso

Esta auditoria não removeu nada. A lista abaixo serve para orientar uma futura limpeza segura.

## Componentes claramente legados

| Componente | Tipo | Destino atual | Status |
| --- | --- | --- | --- |
| `/login.php` | Rota pública | `AuthController@loginForm/login` | Legado em uso possível |
| `/index.php` | Rota pública | redirect para dashboard | Legado em uso possível |
| `/logout.php` | Rota pública | `AuthController@logout` | Legado em uso possível |
| `/pages/{legacy}.php` | Rota de compatibilidade | Mapa para rotas Laravel | Legado |
| `/pages/ajax/chat_interno.php` | AJAX legado | `InternalChatController@legacy` | Legado |
| `/pages/ajax/chat_anexo.php` | AJAX legado | `InternalChatController@legacyAttachment` | Legado |
| `/pages/ajax/suporte_chat.php` | AJAX legado | `SupportController@legacyChat` | Legado |
| `/pages/ajax/suporte_anexo.php` | AJAX legado | `SupportController@legacyAttachment` | Legado |

## Mapa legado relevante

O fallback `/pages/{legacy}` redireciona diversas páginas antigas, incluindo:

- `despesas.php` para `/financeiro`;
- `contas.php` para `/financeiro/contas`;
- `pedidos_fiscais.php` para `/compras/pedidos`;
- `safras.php` para `/safras`;
- `talhoes.php` para `/talhoes`;
- `mapa_talhoes.php` para `/talhoes/mapa`;
- `auditoria.php` para `/auditoria`;
- `propriedades.php` para `/propriedades`;
- `maquinas.php` para `/patrimonio`;
- `notas_fiscais.php` para `/fiscal/notas`.

## Suspeitas de duplicidade funcional

| Área | Possível duplicidade | Motivo | Ação segura |
| --- | --- | --- | --- |
| Módulos | `ModuleController@show` e telas dedicadas | Menu usa hubs genéricos e rotas concretas | Validar navegação real no navegador. |
| Financeiro | `/financeiro`, `/financeiro/despesas`, modais de despesa | Tela central e tela específica coexistem | Manter até confirmar redirects. |
| Compras | Modal Novo Pedido e rota `/compras/pedidos/novo` | UX quer modal, mas rota completa existe | Manter rota para compatibilidade. |
| Safras | Modal Nova Safra e rota `/safras/nova` | Mesmo fluxo pode abrir por rota ou modal | Validar antes de remover. |
| Propriedades | Modal admin e rota `/propriedades/nova` | Administração usa modal flutuante | Manter até medir acesso. |

## Critério recomendado antes de remover qualquer legado

1. Confirmar que a rota não aparece em Blade, JavaScript, controllers, testes ou documentação.
2. Consultar logs de acesso reais por pelo menos 30 dias.
3. Criar redirect seguro se houver URL antiga conhecida.
4. Validar em homologação com usuários reais.
5. Remover em branch própria com teste específico provando o novo caminho.

