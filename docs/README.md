# Documentação do Projeto Lara

Bem-vindo à documentação completa do **Lara**, um sistema de gestão de espaços/clube
construído em **Laravel 11**. Esta documentação descreve a arquitetura, as integrações
e **todas as funções** (métodos públicos) de controllers, services, models e demais
componentes da aplicação.

> Idioma: Português (pt_BR) · Stack: Laravel 11 / PHP 8.2 / MySQL + SQL Server

## Sumário

### Guias gerais
1. [Visão Geral](01-visao-geral.md) — o que é o sistema, módulos, integrações e fluxo principal.
2. [Instalação e Configuração](02-instalacao.md) — requisitos, `.env`, migrações, filas e deploy.
3. [Arquitetura](03-arquitetura.md) — camadas, conexões de banco, padrões adotados.
4. [Autenticação e Permissões](04-autenticacao-e-permissoes.md) — Breeze, JWT, Spatie e Policies.

### Referência de código
5. [Controllers](controllers.md) — todos os controllers e seus métodos públicos.
6. [Services](services.md) — regras de negócio (camada de serviço).
7. [Models](models.md) — entidades Eloquent, conexões, atributos e relacionamentos.
8. [Rotas](rotas.md) — tabelas de rotas `web`, `api` e `auth`.
9. [Requests, Resources e Middleware](requests-resources-middleware.md) — validação, transformação e filtros.
10. [Jobs, Listeners, Notifications e Providers](jobs-listeners-notifications.md) — componentes assíncronos e de bootstrap.
11. [Integrações Externas](integracoes.md) — WhatsApp, Telegram, RedeItau, e-mail, Home Assistant, SQL Server.
12. [Banco de Dados](banco-de-dados.md) — migrações, seeders e factories.

## Como navegar

- Cada método é documentado com **assinatura**, **parâmetros**, **retorno** e
  **comportamento**. Quando o método é exposto por uma rota, o endpoint e o middleware
  exigido são indicados.
- Os nomes de classes, métodos e campos refletem exatamente o código-fonte
  (`app/`, `routes/`, `config/`, `database/`).

## Convenções

| Símbolo | Significado |
|---------|-------------|
| `→ JSON` | O método retorna uma resposta JSON (API). |
| `→ View` | O método retorna uma view Blade (painel administrativo). |
| `→ Redirect` | O método redireciona após uma ação (formulários web). |
| `static` | Método estático, chamado sem instanciar a classe. |
