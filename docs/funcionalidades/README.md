# Guias de Funcionalidades

Esta seção descreve **cada funcionalidade do sistema Lara do ponto de vista de uso** — o que
faz, para quem serve, o passo a passo, os campos envolvidos e as regras de negócio. É um
material complementar à [referência técnica](../README.md) (controllers, services, models e
rotas).

> Cada guia segue o mesmo formato: **O que é · Para quem · Pré-requisitos · Fluxo passo a
> passo · Campos · Regras de negócio · Mensagens/erros · Integrações · Referência técnica**.

## Funcionalidades

| Guia | Descrição |
|------|-----------|
| [Sistema de Informações](informacoes.md) | Catálogo versionado de atividades/serviços do clube, com histórico de alterações. |
| [Placas de Carro / Estacionamento](estacionamento-placas.md) | Busca de veículos por placa e identificação do condutor provável. |
| [Agendamento de Espaços](agendamento.md) | Reserva de quadras/salões pelo sócio: horários, colisão, limite e pagamento. |
| [Espaços e Grupos (admin)](espacos-e-grupos.md) | Cadastro de grupos, espaços e regras de disponibilidade. |
| [Sócios](socios.md) | Login, registro, foto e autenticação JWT (integração MultiClubes). |
| [Freelancers](freelancers.md) | Cadastro de freelancers, funções e serviços prestados. |
| [Banco de Horas](banco-de-horas.md) | Importação de ponto, cálculo de saldo e ajustes. |
| [Torneios](torneios.md) | Torneios, categorias, times, inscrições e pagamentos. |
| [Empresas e Controle de Acesso](empresas.md) | Empresas terceirizadas, trabalhadores e regras de acesso. |
| [WhatsApp](whatsapp.md) | Webhook, envio de mensagens e gestão de conversas/mídia. |
| [Telegram](telegram.md) | Cadastro e consulta de contatos do Telegram. |
| [Usuários e Permissões](usuarios-e-permissoes.md) | Administração de usuários, papéis e permissões. |
| [Automação Home Assistant](automacao-home-assistant.md) | Iluminação automática a partir dos agendamentos. |

## Perfis de usuário

- **Sócio (app móvel):** agenda espaços, faz login, consulta seus agendamentos.
- **Administrador (painel web):** gerencia espaços, regras, informações, usuários, empresas,
  torneios, banco de horas e consulta de placas.
- **Integrações externas:** WhatsApp/Telegram/Home Assistant e bots (via token de API).
