# Automação Home Assistant (Iluminação)

## O que é

Endpoint que informa ao **Home Assistant** quais espaços devem estar com a **iluminação
ligada** com base nos agendamentos ativos do momento. Permite acender/apagar as luzes das
quadras automaticamente conforme as reservas.

## Para quem

O sistema de automação (Home Assistant), que consulta o endpoint periodicamente. É uma rota
**pública** (sem token).

## Endpoint

`GET /api/schedule/home-assistant/automation` → `ScheduleController@homeAssistantAutomation`.

## Como funciona

1. O serviço busca os agendamentos **confirmados** do momento.
2. Para cada espaço com um **contator** configurado (campo `contactor`, ex.: um relé), define
   o estado conforme exista (ou não) um agendamento ativo naquele intervalo.
3. Retorna um JSON com o estado de cada contator (ligado/desligado), que o Home Assistant usa
   para sincronizar os relés/lâmpadas.

> Tipicamente o Home Assistant chama este endpoint em intervalos curtos (a cada 1–5 minutos).

## Regras de negócio

- Apenas espaços com `contactor` preenchido entram na automação.
- O estado é derivado dos agendamentos **ativos** no instante da consulta.
- A rota é pública por design (consumida por um dispositivo na rede), portanto não exige
  `api_token` nem `login_token`.

## Referência técnica

- Controller: [`ScheduleController@homeAssistantAutomation`](../controllers.md#schedulecontroller)
- Service: [`SchedulesService::homeAssistantAutomation`](../services.md#schedulesservice)
- Integração: [Integrações](../integracoes.md#116-home-assistant-automação-de-iluminação)
