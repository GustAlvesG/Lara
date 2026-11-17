<?php

/*
|--------------------------------------------------------------------------
| Gestão de tokens da API (Administração › Integrações)
|--------------------------------------------------------------------------
|
| Quem chama O Lara de fora (site Next.js, HAL/IA, portaria, Home
| Assistant, Telegram, Poli) usa um token próprio por instalação, gerado na
| tela Integrações, com escopos e validade. Ver
| docs/funcionalidades/gestao-tokens.md.
|
| legacy_enabled — enquanto true, o `API_TOKEN` antigo do .env (um só para
|     todos) continua aceito em todas as rotas, e cada uso dele é registrado
|     para a tela mostrar quem ainda depende dele. Desligue (false) só depois
|     que a lista "Ainda usando o token antigo" ficar vazia por alguns dias.
|
| portaria_enforced — as rotas `/api/company-access/*` (Monitor de Acesso)
|     nunca pediram token. Com false, a chamada sem token continua passando
|     e fica registrada como "sem token"; com true, passa a exigir um token
|     de escopo `portaria`. Ligue depois que o Monitor mandar o token.
|
| rotation_grace_days — ao rotacionar, quantos dias o token antigo continua
|     valendo para o outro sistema trocar sem pressa.
|
| expiring_days — a tela marca "vence em breve" com esta antecedência.
|
| touch_seconds — o último uso de cada token é gravado no máximo uma vez
|     neste intervalo (o Home Assistant consulta a cada 30 s).
*/

return [
    'legacy_enabled' => (bool) env('API_TOKEN_LEGACY_ENABLED', true),
    'portaria_enforced' => (bool) env('API_TOKEN_PORTARIA_ENFORCED', false),
    'rotation_grace_days' => (int) env('API_TOKEN_ROTATION_GRACE_DAYS', 7),
    'expiring_days' => (int) env('API_TOKEN_EXPIRING_DAYS', 30),
    'touch_seconds' => (int) env('API_TOKEN_TOUCH_SECONDS', 60),
];
