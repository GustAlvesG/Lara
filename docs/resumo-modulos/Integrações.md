# Integrações — resumo para quem vai mexer

Documentação completa: [gestao-tokens.md](../funcionalidades/gestao-tokens.md).

## Em uma frase

Tokens de quem chama a API do Lara (site Next.js, HAL, portaria, Home Assistant, Telegram, Poli):
um por instalação, com escopos, validade escolhida na geração e último uso registrado. Fica no
menu da conta (ao lado de Usuários e Setores), em `/integracoes`.

## Como a rota se protege

```php
Route::middleware('api_token:' . ApiScopes::IA)->group(...);              // um escopo
->middleware('api_token:' . ApiScopes::HOME_ASSISTANT . ',' . ApiScopes::TELEGRAM) // qualquer um dos dois
Route::prefix('company-access')->middleware('api_token_portaria:' . ApiScopes::PORTARIA) // só registra até ligar
```

- Rota nova para sistema externo: escolha o escopo em `App\Support\ApiTokens\ApiScopes`. Sistema
  novo: escopo novo (constante + linha em `CATALOG`) e, se fizer sentido, sugestão em
  `ApiIntegrationController::SUGGESTED_SCOPES` e opção em `ApiIntegration::SYSTEMS`.
- **Nunca** `api_token` sem escopo: nenhum token gerenciado passa, e o `ApiRouteScopesTest` acusa.
- O controller pode saber qual token chamou: `$request->attributes->get('api_integration_token')`
  (nulo quando entrou pelo token antigo).

## Peças

| Peça | Papel |
|---|---|
| `ApiTokenGuard` | Decide (gerenciado → legado → recusa) e registra o uso; registrar nunca derruba a chamada |
| `ApiTokenManager` | `generate`, `rotate` (convivência em dias), `revoke`; formato `lara_` + 48 |
| `APIToken` / `ObserveApiToken` | Middlewares `api_token` / `api_token_portaria` |
| `ApiIntegration`, `ApiIntegrationToken` | Models (`status()`, `isUsable()`, `hasAnyScope()`) |
| `api_token_usage_daily` | Contagem por dia × token × tipo (`token`, `legado`, `sem-token`, `recusado`) × escopo × IP |
| `ApiIntegrationController` + `resources/views/api-integrations/*` | Tela |

## Regras que costumam pegar

- Só o SHA-256 vai ao banco. O texto do token aparece uma vez (flash `new_api_token`) e nunca vai
  para log — não acrescente log com o header `Authorization`.
- Último uso: gravado no máximo a cada `API_TOKEN_TOUCH_SECONDS` (60 s), na hora se mudar IP ou
  rota. A recusa não mexe no último uso, só conta.
- O `API_TOKEN` antigo passa em **todas** as rotas enquanto `API_TOKEN_LEGACY_ENABLED=true`. É ele
  que mantém os testes dos módulos verdes com o `config(['services.api.token' => …])` de sempre.
- Permissões `integracoes.visualizar` e `integracoes.gerenciar`; a tela abre com qualquer uma
  (`acessar-integracoes`, em `Permissions::ANY_OF`).
- Mudou o escopo de rota usada por sistema externo: prompt para o agent do sistema em
  `docs/prompts/<NextJS|IA|LaraExternos>/`.

## Testes

`tests/Feature/ApiTokens/*` — sem `RefreshDatabase`: rodam só a migration do módulo
(`2026_11_17_100000_create_api_integrations_tables`) e usam rotas de teste próprias. A tela usa
User mockado (o User está preso à conexão `mysql`).
