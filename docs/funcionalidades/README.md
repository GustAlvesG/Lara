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
| [Pix automático (Sicoob)](pix-sicoob.md) | **Movimenta dinheiro real:** o "Dar baixa" do financeiro transfere o valor do contrato via Pix. |
| [Banco de Horas](banco-de-horas.md) | Importação de ponto, cálculo de saldo e ajustes. |
| [Torneios](torneios.md) | Torneios, categorias, times, inscrições e pagamentos. |
| [Empresas e Controle de Acesso](empresas.md) | Empresas terceirizadas, trabalhadores e regras de acesso. |
| [Autorização de Ordem de Compra (Questor)](questor-autorizacao-compra.md) | Fila de ordens de compra do ERP Questor, com fluxo de aprovação em três níveis. |
| [Front-end da aprovação (Next.js)](questor-frontend-prompt.md) | Contrato da API de aprovação externa e o prompt de implementação do site em DMZ. |
| [Mapa de Cotação (Questor)](cotacao-mapa.md) | Comparação de preço entre fornecedores a partir de uma solicitação de compra. **Somente leitura no ERP.** |
| [Lara — Assistente de IA](lara-ia.md) | Chat interno de pergunta e resposta sobre o estatuto, ligado à VM da IA. |
| [WhatsApp](whatsapp.md) | Webhook, envio de mensagens e gestão de conversas/mídia. |
| [Telegram](telegram.md) | Cadastro e consulta de contatos do Telegram. |
| [Usuários e Permissões](usuarios-e-permissoes.md) | Administração de usuários, papéis e permissões. |
| [Automação Home Assistant](automacao-home-assistant.md) | Iluminação automática a partir dos agendamentos. |

## Perfis de usuário

- **Sócio (app móvel):** agenda espaços, faz login, consulta seus agendamentos.
- **Administrador (painel web):** gerencia espaços, regras, informações, usuários, empresas,
  torneios, banco de horas e consulta de placas.
- **Integrações externas:** WhatsApp/Telegram/Home Assistant e bots (via token de API).
