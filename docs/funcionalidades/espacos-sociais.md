# Reservas de Espaços Sociais

## O que é

Reserva de **churrasqueiras, salões e áreas de festa** pelo sócio, com antecedência. Ao
contrário da quadra (horário pago na hora), o espaço social é reservado **pelo dia inteiro** e
passa por análise:

```
pré-reserva ──► em análise ──► contrato ok + aprovação ──► aguardando pagamento ──► confirmada
 (app ou balcão)   (data presa)                                (prazo em dias)        (paga)
                     │                                              │
                     └─► recusada / cancelada              prazo vencido ─► expirada (data livre)
```

1. O sócio faz a **pré-reserva** de um dia no app de locação (ou a secretaria lança no balcão).
   A data fica **presa** para ele enquanto a equipe analisa.
2. A secretaria faz o **contrato fora do Lara** e marca "Contrato ok" (com anexo opcional).
3. A equipe **aprova** (com desconto, se for o caso) ou **recusa**. Aprovar fixa o valor final e
   libera o **pagamento no app**, com prazo.
4. O sócio paga online, pela Rede, no mesmo fluxo de pagamento das quadras. Pago, a reserva fica
   **confirmada** e os convidados não sócios passam a constar na portaria.
5. Sem pagamento dentro do prazo, a reserva **expira** e a data volta a ficar livre.

## Para quem

| Quem | O que faz | Permissão |
|---|---|---|
| Atendimento / secretaria | Consulta as reservas, lança pré-reserva no balcão, cuida da lista de convidados, imprime a lista da portaria | `reservas.sociais` |
| Quem decide | Contrato ok, aprovar com desconto, recusar, cancelar | `reservas.sociais.aprovar` |
| Quem estorna | Valor devolvido no cancelamento de reserva paga | `reservas.pagamentos.estornar` (a mesma do estorno das quadras) |
| Quem configura | Grupos, espaços, conflitos e catálogo de bebidas | `reservas.configurar` |
| Sócio | Pré-reserva, convidados, pagamento e desistência (antes de pagar) pelo app | JWT do app |

As permissões nascem sem setor: dê a cada setor na tela de **Setores**.

## Pré-requisitos (configuração)

### 1. Grupo social

Em **Reservas › Locais e Grupos**, crie (ou edite) um grupo com **Tipo de Espaço = Social**. Os
campos de quadra (horário, janela de vendas, duração, limite diário) somem, e aparecem:

| Campo | Para quê |
|---|---|
| Ant. mín. / máx. (dias) | A data precisa estar entre hoje + mínima e hoje + máxima. **Máxima 0 = sem limite** (no espaço social; na quadra, o 0 tem outro sentido). |
| Dias da semana | Dias em que o grupo aceita eventos. |
| Prazo p/ pagar (dias) | Conta da aprovação e vai até o fim do dia; nunca passa da véspera do evento. Em branco = 7. |
| Ativas ao mesmo tempo | Máximo de reservas futuras (em análise, aguardando pagamento ou confirmadas) por sócio no grupo. Em branco = sem limite. |
| Máx. de reservas a cada X dias | Em nenhuma janela de X dias seguidos o sócio pode ter mais de N eventos no grupo (ex.: 1 a cada 30 dias). Conta as já realizadas; recusada, cancelada e expirada não contam. Em branco = sem limite. |
| Regras mostradas ao sócio | Texto livre exibido no app e nos e-mails (ex.: regra das crianças). |

### 2. Espaços

Dentro do grupo, cada espaço tem:

| Campo | Para quê |
|---|---|
| Preço | Valor da locação (diária). |
| Capacidade total | Total de pessoas (sócios + não sócios). Confere o "Pessoas (total)" da pré-reserva. |
| Não sócios incluídos no preço | Quantos convidados não sócios a lista aceita sem custo. |
| Não sócios extras (máximo) e preço por extra | O sócio pode contratar até esse número de convidados a mais, cobrados por pessoa. |
| Itens fornecidos | Mesas, cadeiras, fogão… (mostrado no app). |
| Observações | Restrições (ex.: "Proibida a utilização de fogão"). |
| Não pode ser usado no mesmo dia que | Espaços em conflito (ver abaixo). |

O espaço social **não tem** switch de luz nem autoatendimento de iluminação: a reserva ocupa o
dia todo e acenderia a luz por 24 horas.

### 3. Conflitos de uso

"Não pode ser usado no mesmo dia que" liga dois espaços **nos dois sentidos**: uma reserva ativa
em um ocupa o outro no dia. Serve para:

- **espaço combinado** — "Churrasqueira da Sauna + Tênis" conflita com a "Churrasqueira da
  Sauna" e com a "Churrasqueira do Tênis";
- **espaço social que ocupa uma quadra** — o evento bloqueia todos os horários da quadra no dia,
  e uma reserva da quadra torna o dia indisponível para o espaço social.

### 4. Bebidas

Em Espaços Sociais › **Bebidas**: nome, unidade, **Código MV** (digitado à mão, para quem lança
a venda no MV), preço e se está disponível. Mudar o preço ou desativar não altera reservas já
feitas: cada reserva guarda a cópia do item.

### Exemplo: os espaços do clube (outubro/2026)

Grupo "Churrasqueiras e Salões" (Social). Regras mostradas ao sócio:

> Crianças com idade inferior a 4 anos não são contabilizadas como convidados. Crianças a partir
> de 5 anos são contabilizadas na capacidade. A reserva só é confirmada mediante pagamento.

| Espaço | Preço | Não sócios incluídos | Extras | Capacidade | Itens fornecidos / observações | Conflita com |
|---|---|---|---|---|---|---|
| Churrasqueira 21 | 350,00 | 15 | 5 × 15,00 | 40 | 15 jogos de mesa com 4 cadeiras, fogão e geladeira | — |
| Salão Estrela | 390,00 | 30 | 5 × 15,00 | 70 | 10 jogos de mesa com 4 cadeiras, fogão e geladeira | — |
| Churrasqueira da Sauna | 800,00 | 20 | 5 × 15,00 | 80 | 10 jogos de mesa com 4 cadeiras, fogão e freezer | Sauna + Tênis |
| Churrasqueira do Tênis | 700,00 | 20 | 5 × 15,00 | 60 | 10 jogos de mesa com 4 cadeiras, fogão e freezer | Sauna + Tênis |
| Churrasqueira da Sauna + Tênis | 1.200,00 | 40 | 5 × 15,00 | 130 | 20 jogos de mesa com 4 cadeiras, fogão e freezer | Sauna; Tênis |
| Salão Superior | 1.200,00 | 40 | 5 × 15,00 | 120 | 20 jogos de mesa com 4 cadeiras, micro-ondas e freezer. Observação: **proibida a utilização de fogão** | — |

Basta marcar o conflito em um dos lados (ex.: no "Sauna + Tênis", marcar os dois): vale nos dois
sentidos.

## Fluxo passo a passo (painel)

### Pré-reserva no balcão

Reservas › **Espaços Sociais** › **Nova pré-reserva**:

1. Escolha o espaço; o calendário do mês mostra cada dia como **Livre**, **Reservado**,
   **Bloqueado** (regra de exclusão do espaço) ou **Fora da janela**. Clique num dia livre.
2. Busque o sócio pela matrícula (MultiClubes, como na agenda das quadras).
3. Preencha tipo de evento, total de pessoas, convidados extras, observações e bebidas.
4. Convidados não sócios (nome + CPF) são opcionais agora.
5. **Registrar pré-reserva**. O sócio recebe o e-mail "Pré-reserva recebida".

### Análise

No detalhe da reserva (Abrir):

1. **Contrato ok** — depois de feito e assinado fora do Lara (anexo opcional: PDF ou imagem;
   fica em disco privado e só baixa quem tem `reservas.sociais`).
2. **Editar a pré-reserva** — tudo, menos data e espaço: tipo de evento, total de pessoas,
   convidados extras, observações, bebidas (incluir, tirar, mudar quantidade) e convidados. Vale
   pelo painel e pelo app do sócio, **enquanto a reserva está em análise e o contrato ainda não
   foi marcado ok**. Depois do contrato ok não há mais trocas (o app recebe `contract_closed`).
   Para mudar data ou espaço, cancele e faça outra pré-reserva. Bebida que já estava mantém o
   preço contratado; bebida nova entra pelo preço atual do catálogo.
3. **Aprovar e liberar pagamento** — desconto em reais (com motivo obrigatório). O sócio recebe
   o e-mail com o valor e o prazo. Desconto igual ao total confirma na hora, sem cobrança.
4. Ou **Recusar**, com motivo (vai no e-mail).

### Depois de aprovada

- O sócio paga pelo app até o prazo. Pago: "Reserva confirmada" por e-mail, e cada convidado
  vira uma **liberação pontual na data do evento** no Monitor de Acesso (Externos).
- **Lista para a portaria**: PDF com nome e CPF completo de cada convidado.
- **Cancelar**: em qualquer estágio ativo, com motivo. Se houver pagamento, informe quanto
  estornar (0 = nada). O estorno é pela Rede, total ou parcial. Se a Rede recusar, o cancelamento
  fica gravado e a tela mostra o erro; o e-mail não promete devolução.

### Convidados

- A lista aceita até **incluídos + extras contratados** nomes, cada um com CPF válido e sem
  repetir. Sócio não entra na lista (entra pela carteirinha).
- O sócio edita pelo app **até o fim da véspera**; a equipe edita até o dia, pelo painel.
- Com a reserva confirmada, toda mudança sincroniza a portaria: quem saiu tem a liberação
  cancelada (se não usada), quem entrou ganha uma.
- A liberação vale **uma entrada** no dia do evento, como toda liberação pontual.

## Regras de negócio

- **Valor** = locação + extras × preço do extra + bebidas − desconto. Preço do espaço, do extra e
  das bebidas fica congelado na pré-reserva.
- **Ocupação**: cada reserva é também um agendamento (`schedules`) das 00:00 às 23:59:59, em
  status 3 enquanto não paga, 1 confirmada, 0 recusada/cancelada, 4 expirada. Por isso ela
  aparece em Todos os Agendamentos, colide com quadras vinculadas e entra nos Pagamentos. O
  cancelamento genérico de Todos os Agendamentos **não vale** para ela: a tela aponta para a
  reserva social.
- **O hold de 10 minutos das quadras não expira** a pré-reserva. Em análise não há prazo; o
  único prazo automático é o do pagamento (`social:expire-bookings`, a cada 5 minutos).
- **Pagamento**: só uma reserva social por pagamento, no estágio "aguardando pagamento", dentro
  do prazo e pelo valor exato do total.
- A festa do sócio não o impede de reservar quadra no mesmo dia (salvo quadra vinculada ao
  espaço).
- O espaço social não aparece na agenda de horários das quadras nem em `/api/places/group`.

## Mensagens (e-mail)

Um e-mail por mudança de estágio, todos com o resumo do valor: pré-reserva recebida, aprovada
(com prazo), recusada (com motivo), confirmada, expirada e cancelada (com motivo e estorno, se
houve).

## Integrações

- **App de locação (Next.js)**: API `/api/social/*` — contrato e prompt em
  [reservas-sociais-prompt-site-locacao.md](../reservas-sociais-prompt-site-locacao.md).
- **Rede (Itaú)**: pagamento pelo app e estorno no cancelamento.
- **Monitor de Acesso (Externos)**: convidados como liberação pontual.

## Referência técnica

- Resumo para agentes: [docs/resumo-modulos/Reservas.md](../resumo-modulos/Reservas.md)
- Serviços: `App\Services\Social\SocialBookingService` (fluxo), `SocialAvailabilityService`
  (calendário e limites), `SocialBookingNotifier` (e-mails).
- Models: `App\Models\Social\SocialBooking`, `SocialBookingItem`, `SocialBookingGuest`,
  `SocialBeverage`; `Place::conflictingPlaceIds()`.
- Controllers: `Social\SocialBookingController`, `Social\SocialBeverageController`,
  `Api\SocialBookingApiController`; pagamento em `SchedulePaymentController::store`.
- Comando: `social:expire-bookings`.
- Testes: `tests/Feature/Social/*`, `tests/Feature/Rebrand/EspacosSociaisScreensTest.php`.
