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
   **confirmada** e os convidados não sócios passam a constar na portaria. Se ele pagar fora da
   plataforma (dinheiro, Pix direto, maquininha), a equipe dá **baixa manual** no painel.
5. Sem pagamento dentro do prazo, a reserva **expira** e a data volta a ficar livre.

No balcão, a equipe também pode fazer a reserva **por completo** de uma vez — contrato, aprovação
e, se o sócio já pagou, a baixa — ver [Reserva completa no balcão](#reserva-completa-no-balcão).

## Para quem

| Quem | O que faz | Permissão |
|---|---|---|
| Atendimento / secretaria | Consulta as reservas, lança pré-reserva no balcão, cuida da lista de convidados, imprime a lista da portaria | `reservas.sociais` |
| Quem decide | Contrato ok, aprovar com desconto, recusar, cancelar | `reservas.sociais.aprovar` |
| Quem estorna | Valor devolvido no cancelamento de reserva paga | `reservas.pagamentos.estornar` (a mesma do estorno das quadras) |
| Quem dá baixa | Marcar como paga a reserva recebida fora da plataforma | `reservas.pagamentos.baixa-manual` (a mesma das quadras) |
| Quem configura | Grupos, espaços, conflitos e catálogo de bebidas e mobiliário | `reservas.configurar` |
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
| Mesas incluídas / Cadeiras incluídas | Quantidade base que vem com a locação. Mesas, cadeiras e jogos a mais vêm do catálogo. |
| Duração do evento (horas) | Preenchida, o sócio informa o **horário de início** e o fim é início + duração (pode passar da meia-noite). Vazia, a reserva não tem horário. |
| Início a partir de / Início até | Faixa opcional em que o evento pode começar. |
| Itens fornecidos | Fogão, geladeira… (texto mostrado no app). |
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

### 4. Bebidas e mobiliário

Em Espaços Sociais › **Bebidas** (tela "Bebidas e mobiliário"): nome, **categoria** (Bebida ou
Mesas e cadeiras), unidade, **Código MV** (digitado à mão, para quem lança a venda no MV), preço,
**máximo por reserva** (vazio = sem limite) e se está disponível. No mobiliário, também quantas
**mesas e cadeiras cada unidade acrescenta** — por exemplo:

| Item | Mesas por unidade | Cadeiras por unidade |
|---|---|---|
| Mesa avulsa | 1 | 0 |
| Cadeira avulsa | 0 | 1 |
| Jogo completo (mesa + 4 cadeiras) | 1 | 4 |

O total do evento é a base do espaço + o mobiliário contratado. Mudar o preço, o que a unidade
traz ou desativar não altera reservas já feitas: cada reserva guarda a cópia do item e da base.

### Exemplo: os espaços do clube (outubro/2026)

Grupo "Churrasqueiras e Salões" (Social). Regras mostradas ao sócio:

> Crianças com idade inferior a 4 anos não são contabilizadas como convidados. Crianças a partir
> de 5 anos são contabilizadas na capacidade. A reserva só é confirmada mediante pagamento.

| Espaço | Preço | Não sócios incluídos | Extras | Capacidade | Mesas / cadeiras incluídas | Itens fornecidos / observações | Conflita com |
|---|---|---|---|---|---|---|---|
| Churrasqueira 21 | 350,00 | 15 | 5 × 15,00 | 40 | 15 / 60 | fogão e geladeira | — |
| Salão Estrela | 390,00 | 30 | 5 × 15,00 | 70 | 10 / 40 | fogão e geladeira | — |
| Churrasqueira da Sauna | 800,00 | 20 | 5 × 15,00 | 80 | 10 / 40 | fogão e freezer | Sauna + Tênis |
| Churrasqueira do Tênis | 700,00 | 20 | 5 × 15,00 | 60 | 10 / 40 | fogão e freezer | Sauna + Tênis |
| Churrasqueira da Sauna + Tênis | 1.200,00 | 40 | 5 × 15,00 | 130 | 20 / 80 | fogão e freezer | Sauna; Tênis |
| Salão Superior | 1.200,00 | 40 | 5 × 15,00 | 120 | 20 / 80 | micro-ondas e freezer. Observação: **proibida a utilização de fogão** | — |

A duração de cada espaço (horas) e a faixa de início não vieram na lista do clube: preencha no
cadastro quando estiverem definidas.

Basta marcar o conflito em um dos lados (ex.: no "Sauna + Tênis", marcar os dois): vale nos dois
sentidos.

## Fluxo passo a passo (painel)

### Pré-reserva no balcão

Reservas › **Espaços Sociais** › **Nova pré-reserva**:

1. Escolha o espaço; o calendário do mês mostra cada dia como **Livre**, **Reservado**,
   **Bloqueado** (regra de exclusão do espaço) ou **Fora da janela**. Clique num dia livre.
2. Busque o sócio pela matrícula (MultiClubes, como na agenda das quadras).
3. Se o espaço tem duração definida, informe o **horário de início** (a tela mostra a que horas
   termina).
4. Preencha tipo de evento, total de pessoas, convidados extras, observações, bebidas e
   mesas/cadeiras adicionais (o resumo mostra o total de mesas e cadeiras).
5. Convidados não sócios (nome + CPF) são opcionais agora.
6. **Registrar pré-reserva**. O sócio recebe o e-mail "Pré-reserva recebida". Lançada no balcão,
   o contato com o sócio já conta como feito.

### Prazo de contato (2 dias úteis)

Pré-reserva feita **pelo app** abre um prazo para a equipe falar com o sócio: **2 dias úteis**
(segunda a sexta; feriados não são descontados), contados do dia seguinte ao pedido e vencendo às
23:59 — pedido na sexta vence na terça. O número de dias é `SOCIAL_CONTACT_BUSINESS_DAYS` no
`.env`.

- Quem tem `reservas.sociais` **pelo setor ou individualmente** recebe um aviso no **sino** do
  painel quando a pré-reserva chega, e outro, uma única vez, se o prazo vencer sem contato
  (`social:contact-reminders`, a cada 15 minutos). Quem só tem acesso total (Gerência, Diretoria,
  TI) não recebe, para não virar ruído — dê a permissão ao setor que atende.
- A lista mostra "N aguardando contato" e "N com contato atrasado" (com filtro) e, em cada linha,
  "contatar até…" ou "contato atrasado".
- No detalhe, **Registrar contato** (com anotação opcional) encerra o prazo. Contrato ok,
  aprovação e recusa também contam como contato.

### Análise

No detalhe da reserva (Abrir):

1. **Contrato ok** — depois de feito e assinado fora do Lara (anexo opcional: PDF ou imagem;
   fica em disco privado e só baixa quem tem `reservas.sociais`).
2. **Editar a pré-reserva** — tudo, menos data e espaço: horário de início, tipo de evento,
   total de pessoas, convidados extras, observações, bebidas e mobiliário (incluir, tirar, mudar
   quantidade) e convidados. Vale
   pelo painel e pelo app do sócio, **enquanto a reserva está em análise e o contrato ainda não
   foi marcado ok**. Depois do contrato ok não há mais trocas (o app recebe `contract_closed`).
   Para mudar data ou espaço, cancele e faça outra pré-reserva. Bebida que já estava mantém o
   preço contratado; bebida nova entra pelo preço atual do catálogo.
3. **Aprovar e liberar pagamento** — desconto em reais (com motivo obrigatório). O sócio recebe
   o e-mail com o valor e o prazo. Desconto igual ao total confirma na hora, sem cobrança.
4. Ou **Recusar**, com motivo (vai no e-mail).

### Reserva completa no balcão

Na **Nova pré-reserva**, quem tem `reservas.sociais.aprovar` vê **Fechar a reserva agora**: o
contrato já está assinado, então o mesmo envio faz pré-reserva, contrato ok (anexo opcional) e
aprovação (com desconto e motivo, se houver), tudo em nome do sócio escolhido.

- Sem mais nada, a reserva sai **aguardando pagamento**, com o prazo do grupo, e o pagamento
  aparece no **perfil do sócio no site** para ele pagar — a reserva é dele (a mesma do app).
- Quem também tem `reservas.pagamentos.baixa-manual` pode marcar **O sócio já pagou, fora da
  plataforma** (forma, data e comprovante): a reserva sai **confirmada**, com os convidados
  liberados na portaria.
- É tudo ou nada: se um passo recusa (data ocupada, desconto inválido, limite do sócio), nada fica
  gravado, nem a data presa.
- O sócio recebe **um e-mail só**, do estado final (aprovada para pagar ou confirmada).
- Desconto igual ao total confirma sem cobrança, como na aprovação normal.

### Depois de aprovada

- O sócio paga pelo app até o prazo. Pago: "Reserva confirmada" por e-mail, e cada convidado
  vira uma **liberação pontual na data do evento** no Monitor de Acesso (Externos).
- **Baixa manual de pagamento**: pagou fora da plataforma? No detalhe, quem tem
  `reservas.pagamentos.baixa-manual` informa a forma (dinheiro, Pix direto ao clube, cartão na
  maquininha, transferência, outro), a data e o comprovante. A baixa é sempre pelo **total
  aprovado** e tem o mesmo efeito do pagamento online: confirma, libera os convidados e manda o
  e-mail; o site deixa de cobrar. Só cabe em reserva aprovada (aguardando pagamento) — em análise,
  aprove antes para fixar o valor.
- **Lista para a portaria**: PDF com nome e CPF completo de cada convidado.
- **Cancelar**: em qualquer estágio ativo, com motivo. Se houver pagamento, informe quanto
  estornar (0 = nada). O estorno é pela Rede, total ou parcial. Se a Rede recusar, o cancelamento
  fica gravado e a tela mostra o erro; o e-mail não promete devolução. Reserva paga por **baixa
  manual** não tem estorno pela Rede: a devolução, se houver, é feita fora do Lara.

### Convidados

- A lista aceita até **incluídos + extras contratados** nomes, cada um com CPF válido e sem
  repetir. Sócio não entra na lista (entra pela carteirinha).
- O sócio edita pelo app **até o fim da véspera**; a equipe edita até o dia, pelo painel.
- Com a reserva confirmada, toda mudança sincroniza a portaria: quem saiu tem a liberação
  cancelada (se não usada), quem entrou ganha uma.
- A liberação vale **uma entrada** no dia do evento, como toda liberação pontual.

## Levar a configuração da homologação para a produção

Grupos, espaços e catálogo cadastrados em homologação não precisam ser refeitos à mão:

```bash
# no servidor de produção, depois do deploy (as migrations precisam estar aplicadas)
php artisan social:copy-config --from=lara_hml --to=lara --dry-run   # mostra o que faria, não grava
php artisan social:copy-config --from=lara_hml --to=lara             # pede confirmação e grava
```

- Copia os **grupos sociais** (com dias da semana, prazo de pagamento, limites e regras), os
  **espaços** deles (preço, convidados, capacidade, mesas e cadeiras, horário, imagens), os
  **conflitos** entre espaços e o **catálogo** de bebidas e mobiliário.
- **Não copia** reservas, convidados nem pagamentos (são testes da homologação), nem o que
  está excluído na origem.
- Casa **pelo nome** (grupo social; espaço dentro do grupo; item pelo nome e categoria): o que
  já existe no destino é atualizado, o resto é criado. Pode rodar de novo depois de ajustar a
  homologação. **Nunca apaga** nada no destino.
- Conflito com uma quadra casa pelo nome da quadra e do grupo; se ela não existir no destino, o
  comando avisa e pula.
- Imagem que é arquivo precisa estar em `public/images` do servidor de destino (as enviadas pelo
  painel ficam no repositório e chegam pelo deploy); o comando lista as que faltam. Link
  externo vai como está.
- `--from`/`--to` são bancos no mesmo servidor e usuário da conexão `mysql` (ou o nome de uma
  conexão configurada). Os dias da semana precisam ter os mesmos ids nos dois bancos.

## Regras de negócio

- **Valor** = locação + extras × preço do extra + bebidas + mobiliário − desconto. Preço do
  espaço, do extra e dos itens fica congelado na pré-reserva, assim como a base de mesas e
  cadeiras do espaço.
- **Mesas e cadeiras do evento** = base do espaço + Σ (quantidade × mesas/cadeiras por unidade).
  Cada item respeita o máximo por reserva.
- **Horário**: com duração definida no espaço, o início é obrigatório e precisa estar na faixa
  configurada; o fim é calculado. A ocupação continua sendo o dia inteiro (o horário não libera
  o resto do dia nem a quadra vinculada).
- **Ocupação**: cada reserva é também um agendamento (`schedules`) das 00:00 às 23:59:59, em
  status 3 enquanto não paga, 1 confirmada, 0 recusada/cancelada, 4 expirada. Por isso ela
  aparece em Todos os Agendamentos, colide com quadras vinculadas e entra nos Pagamentos. O
  cancelamento genérico de Todos os Agendamentos **não vale** para ela: a tela aponta para a
  reserva social.
- **O hold de 10 minutos das quadras não expira** a pré-reserva. Em análise não há prazo; o
  único prazo automático é o do pagamento (`social:expire-bookings`, a cada 5 minutos).
- **Pagamento**: só uma reserva social por pagamento, no estágio "aguardando pagamento", dentro
  do prazo e pelo valor exato do total. A baixa manual segue o mesmo (sozinha, aprovada, pelo
  total), e uma reserva já paga — online ou manual — não aceita outro pagamento.
- **Dono da reserva**: o sócio (`member_id`), venha ela do app ou do balcão. Por isso a reserva
  do balcão aparece no perfil dele no site, com `origin: "secretaria"`.
- A festa do sócio não o impede de reservar quadra no mesmo dia (salvo quadra vinculada ao
  espaço).
- O espaço social não aparece na agenda de horários das quadras nem em `/api/places/group`.

## Mensagens (e-mail)

Um e-mail por mudança de estágio, todos com o resumo do valor, o horário (quando há) e o total
de mesas e cadeiras: pré-reserva recebida, aprovada
(com prazo), recusada (com motivo), confirmada, expirada e cancelada (com motivo e estorno, se
houve).

## Integrações

- **App de locação (Next.js)**: API `/api/social/*` — contrato e prompt em
  [reservas-sociais-prompt-site-locacao.md](../reservas-sociais-prompt-site-locacao.md) (um
  prompt só, com tudo até 19/10/2026: mesas, horário, contato, reserva do balcão e baixa manual).
- **Sino do painel**: notificações `social_booking_requested` e `social_booking_contact_overdue`.
- **Rede (Itaú)**: pagamento pelo app e estorno no cancelamento (não para baixa manual).
- **Monitor de Acesso (Externos)**: convidados como liberação pontual.

## Referência técnica

- Resumo para agentes: [docs/resumo-modulos/Reservas.md](../resumo-modulos/Reservas.md)
- Serviços: `App\Services\Social\SocialBookingService` (fluxo), `SocialAvailabilityService`
  (calendário e limites), `SocialBookingNotifier` (e-mails), `SocialStaffNotifier` (sino da
  equipe), `SocialCounterBookingService` (reserva completa no balcão);
  `App\Services\ManualPaymentService` (baixa manual); `App\Support\BusinessDays`;
  `AccessResolver::holderIds()`.
- Models: `App\Models\Social\SocialBooking`, `SocialBookingItem`, `SocialBookingGuest`,
  `SocialBeverage`; `Place::conflictingPlaceIds()`.
- Controllers: `Social\SocialBookingController`, `Social\SocialBeverageController`,
  `Api\SocialBookingApiController`; pagamento em `SchedulePaymentController::store` (site) e
  `storeManual` (baixa manual, rota `payment.manual`).
- Comandos: `social:expire-bookings`, `social:contact-reminders`, `social:copy-config` (manual).
- Testes: `tests/Feature/Social/*`, `tests/Feature/Payments/ManualPaymentTest.php`,
  `tests/Feature/Rebrand/EspacosSociaisScreensTest.php`, `tests/Unit/BusinessDaysTest.php`.
