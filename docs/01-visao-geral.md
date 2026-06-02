# 1. Visão Geral

## O que é o Lara

O **Lara** é uma aplicação web/API construída em **Laravel 11** que funciona como um
**sistema de gestão de espaços/clube**. Ele atende tanto a um **painel administrativo**
(views Blade, autenticação por sessão via Laravel Breeze) quanto a uma **API** consumida
por aplicativos móveis e por integrações externas (autenticada por token de API + token
JWT de sócio).

A aplicação está configurada em Português do Brasil (`APP_LOCALE=pt_BR`) e fuso horário
`America/Sao_Paulo`.

## Principais módulos

| Módulo | Descrição | Componentes principais |
|--------|-----------|------------------------|
| **Agendamentos** | Reserva de quadras/salões por sócios, com regras de disponibilidade, limites diários, antecedência, colisão de horários, pagamento e notificações por e-mail. | `ScheduleController`, `SchedulesService`, `ScheduleRulesService`, `Schedule`, `ScheduleRules` |
| **Espaços** | Grupos de espaços (categorias) e espaços individuais, com imagens, preços e regras. | `PlaceGroupController`, `PlaceController`, `PlaceService`, `Place`, `PlaceGroup` |
| **Sócios (Members)** | Cadastro, autenticação e foto de sócios, com consulta a base externa SQL Server (MultiClubes). | `MemberController`, `MemberAuthController`, `MemberService`, `Member` |
| **Pagamentos** | Registro de pagamentos de agendamentos e integração com o gateway RedeItau (cobrança/estorno). | `SchedulePaymentController`, `SchedulePaymentService`, `RedeItauService`, `SchedulePayment` |
| **Freelancers** | Cadastro de freelancers, funções e serviços prestados (via API/Telegram). | `FreelancerController`, `FunctionFreelancerController`, `FreelancerServiceController`, `FreelancerService` (service) |
| **Banco de horas (CompTime)** | Importação de pontos a partir de arquivos HTML, cálculo de saldo e ajustes. | `CompTimeController`, `CompTimeService`, `TimeEntry`, `TimeAdjustment`, `Employee` |
| **Torneios** | Gestão de torneios, categorias, times e inscrições. | `Tournament\TournamentController`, `TournamentService`, models em `Tournament/` |
| **Empresas (Company)** | Empresas terceirizadas, trabalhadores e regras de acesso. | `Company\*Controller`, `CompanyService`, models em `Company/` |
| **Comunicação WhatsApp** | Webhook do WhatsApp Cloud API (Meta), recebimento/envio de mensagens e mídia. | `WhatsAppController`, `WhatsAppService`, `ProcessWhatsAppWebhook`, models em `WhatsApp/` |
| **Comunicação Telegram** | Cadastro/consulta de contatos do Telegram (para notificações de equipe). | `TelegramContactController`, `TelegramService`, `TelegramContact` |
| **Controle de acesso físico** | Consulta de acessos (catracas) e estacionamento, lendo a base SQL Server externa. | `AccessController`, `ParkingController`, `Access`, `Parking`, `Visitor` |
| **Conteúdo informativo** | Páginas de informações e dados estruturados (preços, responsáveis, etc.). | `InformationController`, `DataInfoController`, `Information`, `DataInfo` |
| **Usuários e Permissões** | Gestão de usuários administrativos, papéis e permissões (Spatie). | `UserController`, `PermissionController`, `User` |
| **Energia / VideoWall / FTP** | Painéis auxiliares (dashboard de energia, mural de vídeos, listagem FTP). | `EnergyController`, `VideoWallController`, `FtpController` |

## Integrações externas

- **WhatsApp Cloud API (Meta Graph v18)** — envio/recebimento de mensagens e download de mídia.
- **Telegram** — gerenciamento de contatos.
- **RedeItau (Itaú/Rede)** — gateway de pagamento com autenticação OAuth e estorno.
- **SQL Server (MultiClubes)** — base externa de sócios, acessos e visitantes (conexões `mc_sqlsrv` e `mc_sqlsrv_image`).
- **E-mail (SMTP/Mailable)** — confirmações de agendamento, contato e redefinição de senha.
- **Home Assistant** — endpoint de automação de iluminação a partir do estado dos agendamentos.
- **LDAP** — configuração presente (`config/ldap.php`) para autenticação corporativa.

Detalhes em [Integrações Externas](integracoes.md).

## Tecnologias e pacotes

| Pacote | Uso |
|--------|-----|
| `laravel/framework ^11.0` | Framework base. |
| `laravel/breeze` | Scaffolding de autenticação (sessão web). |
| `spatie/laravel-permission ^6.24` | Papéis e permissões. |
| `firebase/php-jwt ^6.11` | Geração/validação de tokens JWT (sócios). |
| `barryvdh/laravel-dompdf ^3.1` | Geração de PDF da agenda diária. |
| `league/flysystem-ftp ^3.27` | Acesso a arquivos via FTP. |
| `symfony/dom-crawler` + `symfony/css-selector` | Parsing do HTML de ponto (banco de horas). |
| `lucascudo/laravel-pt-br-localization` | Localização pt_BR. |

## Fluxo principal (agendamento)

1. O app móvel autentica o sócio (`POST /api/login`) e recebe um **token JWT** (`login_token`).
2. Consulta grupos/espaços disponíveis (`/api/places/group`, `/api/places`).
3. Consulta horários disponíveis (`POST /api/schedule/time-options`), que aplica regras,
   limites diários e detecção de colisão.
4. Cria o agendamento (`POST /api/schedule`), que pode gerar cobrança (RedeItau) e disparar
   e-mail de confirmação/pendência.
5. O painel administrativo gerencia status, regras, espaços e gera o **PDF diário**.
