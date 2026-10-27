# 6. Services

A camada de **Services** (`app/Services/`) concentra as regras de negócio, transações de
banco e integrações externas. Os controllers delegam a essas classes.

---

## SchedulesService

`app/Services/SchedulesService.php` — núcleo de agendamentos. Integra `ScheduleRulesService`,
`MemberService`, `EmailService`, `RedeItauService`, geração de PDF e envio de e-mail.

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `getShedulesByPlace` | `getShedulesByPlace($place_id, $date = null)` | Agendamentos de um espaço (opcionalmente por data). |
| `getScheduleByMember` | `getScheduleByMember($member_id)` | Todos os agendamentos de um sócio. |
| `getSchedules` | `getSchedules($date = null)` | Agendamentos disponíveis agrupados por grupo de espaços. |
| `createSchedule` | `createSchedule(Request $request)` | Cria agendamento com detecção de colisão e notificação por e-mail. |
| `notifyScheduleStatus` | `notifyScheduleStatus(iterable $schedules, ?SchedulePayment $payment = null, array $extra = []): bool` | Envia ao sócio o e-mail correspondente ao status gravado (1 → confirmação, 3 → pendência, 0 → cancelamento). Ponto único usado na criação, na confirmação do pagamento e no cancelamento pelo painel; agrupa por sócio e por status e nunca propaga falha de envio. |
| `store` | `store(Request $request)` | Persiste um registro de agendamento. |
| `updateSchedulesStatus` | `updateSchedulesStatus($data)` | Atualiza o status e, se aplicável, estorna o pagamento. No cancelamento, avisa o sócio por e-mail depois da tentativa de estorno, informando o valor efetivamente devolvido. |
| `checkColide` | `checkColide($slotStartTime, $slotEndTime, $place_id, $date, $member_id = null)` | Detecta sobreposição de horários. |
| `countMemberSchedulesInPlaceGroupOnDate` | `countMemberSchedulesInPlaceGroupOnDate($group, $member_id, $date)` | Conta agendamentos do sócio no grupo/dia e o limite restante. |
| `homeAssistantAutomation` | `homeAssistantAutomation()` | Retorna o estado dos contatores (iluminação) para automação. |

---

## ScheduleRulesService

`app/Services/ScheduleRulesService.php` — regras de disponibilidade e cálculo de horários.

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `store` | `store(Request $request)` | Cria regra com associação a espaços e dias da semana. |
| `getFilteredRulesByPlaceGroup` | `getFilteredRulesByPlaceGroup($placeGroup)` | Regras aplicáveis a um grupo de espaços. |
| `getTimeOptions` | `getTimeOptions($place_id, $date, $member_id = null)` | Calcula horários disponíveis (colisão + exclusões de regras) e o preço de cada um. |
| `getLimit` | `getLimit($place_id, $member_id, $date)` | Limite restante de agendamentos do sócio para a data. |
| `isBetweenOrGreaterDates` | `isBetweenOrGreaterDates($currentDate, $startDate, $endDate = null)` | Valida intervalo de datas. |

---

## SchedulePricingService

`app/Services/SchedulePricingService.php` — preço e janela de venda do horário em andamento
(ver [Agendamento](funcionalidades/agendamento.md#horário-em-andamento-e-valor-proporcional)).

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `factor` | `factor($start, $end, $now = null)` | Fração do preço cheio que o horário vale agora (minutos restantes / duração). |
| `price` | `price($basePrice, $start, $end, $now = null)` | Preço a cobrar, em reais, arredondado a 2 casas. |
| `remainingMinutes` | `remainingMinutes($start, $end, $now = null)` | Minutos que ainda restam do horário. |
| `bookingDeadline` | `bookingDeadline($end)` | Último instante vendável: `fim - ExpirePendingSchedules::HOLD_MINUTES`. |
| `isBookable` | `isBookable($start, $end, $now = null)` | Se o horário ainda pode ser reservado neste instante. |

---

## PlaceService

`app/Services/PlaceService.php` — depende de `SchedulesService` e `ScheduleRulesService`.

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `getPlacesByGroup` | `getPlacesByGroup($group_id, $member_id, $date)` | Espaços disponíveis do grupo, com horários, contagem de disponibilidade e limite diário. |

---

## PlaceGroupService

`app/Services/PlaceGroupService.php` — grupos de espaços com upload de imagens.

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `store` | `store(Request $request)` | Cria grupo com anexação de dias da semana. |
| `show` | `show($placeGroup)` | Retorna os dados do grupo. |
| `update` | `update(Request $request, $placeGroup)` | Atualiza grupo (imagens + sincronização de dias da semana, em transação). |

---

## SchedulePaymentService

`app/Services/SchedulePaymentService.php`

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `createSchedulePayment` | `createSchedulePayment(StoreSchedulePaymentRequest $request)` | Cria o registro de pagamento (em transação). |

---

## Social\SocialBookingService

`app/Services/Social/SocialBookingService.php` — fluxo da reserva de espaço social.

| Método | Descrição |
|--------|-----------|
| `request(Place, memberId, data, ?userId, ?now, notify = true)` | Pré-reserva: trava o espaço, confere disponibilidade e limites, cria o `Schedule` do dia inteiro (status 3) e a reserva com itens e convidados. |
| `updateDetails(booking, data, ?userId)` | Edita a pré-reserva (tudo menos data e espaço; ausente = mantém; `items`/`guests` substituem) enquanto em análise e antes do contrato ok — depois, `contract_closed`. Bebida já contratada mantém o preço. |
| `markContract` | Contrato ok (+ anexo). |
| `approve(booking, discount, reason, userId, ?now, notify = true)` | Exige contrato; aplica desconto (com motivo), fixa total e `payment_due_at` (N dias do grupo, até a véspera). Total zero confirma na hora. Aprovada, aparece para pagar no perfil do sócio no site. |
| `refuse` / `cancelByMember` / `cancel` | Recusa; desistência do sócio (só antes de pagar); cancelamento da equipe com estorno opcional pela Rede. |
| `assertPayable` / `markPaid` | Usados por `SchedulePaymentController::store`; `markPaid` também pela baixa manual (`ManualPaymentService`). |
| `expireOverdue` | Comando `social:expire-bookings`. |
| `replaceGuests` / `syncGuestAccesses` | Lista de convidados e as liberações pontuais (`OneOffAccess`) na data do evento. |

## Support\ImageSource

`app/Support/ImageSource.php` — imagem por arquivo ou link web (grupos e espaços).

| Método | Descrição |
|--------|-----------|
| `resolve(Request, field, ?current)` | Arquivo em `field` (vai para `public/images` com `hashName`), senão link em `{field}_url` (normalizado), senão `current`. Link ou arquivo inválido: `ValidationException`. |
| `normalizeLink(link, errorKey)` | Exige https; `imgur.com/<id>` → `i.imgur.com/<id>.jpg`; página do postimg → `og:image` (só esse domínio é lido, com timeout de 8 s); outros precisam de extensão de imagem; até 255 caracteres. |
| `url(?value)` / `isRemote(?value)` | URL para exibir (link como está, arquivo via `asset`). |

## PlaceGroupService (imagens)

`resolveImages(Request, ?PlaceGroup)` resolve as duas imagens antes da transação; `store` e
`update` recebem o resultado e ignoram os campos de upload/link do request.

## Social\SocialCounterBookingService

`app/Services/Social/SocialCounterBookingService.php` — reserva social feita **por completo** no
balcão. `book(Place, memberId, data, userId, close, ?payment)`: `request` + `markContract` +
`approve` (+ `ManualPaymentService::record` quando `payment` vem) numa transação só, com os
e-mails dos passos desligados e um só no fim (`social.approved` ou `social.confirmed`). Qualquer
recusa desfaz tudo, inclusive a data presa.

## ManualPaymentService

`app/Services/ManualPaymentService.php` — baixa manual de pagamento (pago fora da plataforma).

| Método | Descrição |
|--------|-----------|
| `record(scheduleIds, data, userId, notify = true)` | Cria `SchedulePayment` status 1 com `is_manual`, `recorded_by`, `manual_notes` (sem transação na Rede), liga os schedules e põe status 1. Aceita schedules do mesmo sócio, status 1 ou 3, sem pagamento; social só sozinho, em `awaiting_payment` e pelo `total` (chama `markPaid`). `data`: `payment_method` (de `SchedulePayment::MANUAL_METHODS`), `amount` (padrão = soma), `paid_at` (não futuro), `notes`. E-mail: `social.confirmed` ou `schedule.confirm` dos que estavam pendentes. Recusa com `ManualPaymentException`. |

## Social\SocialAvailabilityService

`app/Services/Social/SocialAvailabilityService.php` — `month()`/`dayStatus()` (`available`, `reserved`, `blocked`, `out_of_window`, considerando `place_conflicts`), `limits()` e `assertWithinLimits()` (ativas ao mesmo tempo; N a cada X dias).

## Social\SocialStaffNotifier

`app/Services/Social/SocialStaffNotifier.php` — sino da equipe: `requested(booking)` (pré-reserva
do app) e `overdue()` (comando `social:contact-reminders`). Destinatários por
`AccessResolver::holderIds(reservas.sociais)` — setor ou individual, sem acesso total.

## Social\SocialBookingNotifier

`app/Services/Social/SocialBookingNotifier.php` — e-mails `social.requested|approved|refused|confirmed|expired|cancelled`, template `emails.social.status`, enviados por `EmailService::sendScheduleMail`.

---

## MemberService

`app/Services/MemberService.php` — sócios, com integração ao SQL Server (MultiClubes).

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `memberByCpf` | `memberByCpf(Request $request)` | Busca ou cria o sócio pelo CPF. |
| `getMemberById` | `getMemberById($member_id)` | Recupera o registro do sócio. |
| `store` | `store($cpf, $title, $birthDate)` | Cria sócio a partir da consulta externa. |
| `queryMember` | `static queryMember($title, $document, $birthdate)` | Consulta o SQL Server por dados do sócio. |

**Conexões:** `mc_sqlsrv` (dados).

---

## MultiClubes\MemberProfileLookup

`app/Services/MultiClubes/MemberProfileLookup.php` — sócio no MultiClubes para os torneios (nome, CPF, matrícula, nascimento, sexo, e-mail, titular), com parâmetros ligados. Mesmo critério do cadastro: título ativo e fora dos tipos especiais (`TitleMemberLookup::EXCLUDED_TITLE_TYPES`).

| Método | Descrição |
|--------|-----------|
| `byCpf($cpf)` | Lista de `MemberProfile`: o mesmo CPF pode estar em mais de um título. CPF inválido devolve `[]` sem consultar (há CPF de preenchimento `00000000000` no MultiClubes). |
| `byTitle($title)` | Todos do título (titular primeiro). |
| `find($cpf, $title)` | O registro daquele CPF naquela matrícula (o sócio logado). |
| `static genderFromSex($sex)` | `dbo.Members.Sex`: **0 = `male`, 1 = `female`** (regra fixada em 22/10/2026); outro valor = null. |

---

## Tournament\TournamentRegistrationService

`app/Services/Tournament/TournamentRegistrationService.php` — inscrição individual e pagamento. Regras em [torneios.md](funcionalidades/torneios.md#inscrição-e-pagamento-tournamentregistrationservice).

| Método | Descrição |
|--------|-----------|
| `profileFor($member)` | Perfil do MultiClubes por `find(cpf, matrícula)`; sem ele, `member_not_found`. |
| `eligibility($tournament, $profile, $member)` | Por categoria: elegível, motivo e inscrição ativa. |
| `register($tournament, $member, $profile, $categoryIds, $override = null, $force = false)` | Cria as inscrições (tudo ou nada), com trava na categoria; preço zero nasce paga. `$override` = [User, motivo\|null] é o balcão: prazo, situação, vaga, sexo e idade viram aviso (`ConfirmationRequired` sem `$force`); confirmado, grava quem liberou e o motivo. Repetida é sempre recusada. |
| `pay($member, $registrationIds, $data)` | Pagamento eRede: posse, pendência, período, vaga, valor exato; reenvio do tid devolve o existente. |
| `recordManual($registrationIds, $data, $user, $force = false)` | Baixa manual (mesma pessoa, `MANUAL_METHODS`); passar da vaga é aviso. |
| `withdraw($registration, $member)` | Desistência do sócio, só pendente. |
| `cancel($registration, $user, $reason, $refund)` | Cancelamento pelo organizador; estorno do valor da inscrição pela eRede antes de cancelar. |

Exceção de regra: `App\Exceptions\TournamentException` (`reason` estável). No Lara, as regras que só avisam lançam `App\Exceptions\ConfirmationRequired` (lista de avisos; a tela volta com "Confirmar mesmo assim", que reenvia com `force=1`).

## Tournament\TournamentTeamService

Times (F4): `create()`, `addMember()`, `removeMember()`, `setCaptain()`, `update()`, `cancel()` (organizador, recusas como `ValidationException`); `draw()` (pagos sem time, só times inteiros, devolve `leftover`); `afterPaid()` (individual vira time; recalcula o time do pagante); `detach()` (saída da inscrição); `refresh()` (completo = cheio e todos pagos); `occupied()` (pessoas + convites pendentes); `unassigned()`.

## Tournament\TournamentInviteService

Modo capitão pelo site: `createTeam()`, `lookup()` (por matrícula ou CPF, devolve `ref` cifrada e CPF mascarado), `invite()` (confere elegibilidade, vaga e duplicidade; e-mail `tournament.invite`), `accept()` (inscreve se preciso e põe no time, pendente), `decline()`, `cancel()`. Só dentro do período de inscrição.

## Tournament\TournamentBracketService

Chaveamento (F5): `generate(..., $force)` (sorteio ou à mão; todos os times não desfeitos; gerar de novo com resultado pede confirmação e apaga; tira do site), `clear($category, $force)`, `resultsWarning()`, `publish()`, `schedule()`, `outdated()` (times fora da chave / desfeitos na chave), `standings()` (classificação do grupo; ordem final do organizador prevalece), `advance()` (vencedor ao próximo jogo, perdedor da semifinal ao 3º lugar). Contas estáticas: `bracketSize()`, `seedOrder()` (posições-padrão), `crossRanks()` (cruzado padrão), `roundLabel()`, `groupName()`.

## Tournament\TournamentResultService

Resultados (F6): `record(..., $force)` (placar por sets/games ou W.O., origem `lara`/`site`; Lara trava; correção que muda vencedor com o jogo seguinte jogado ou jogo de grupo fechado: o site é recusado, o Lara confirma e o serviço reabre o grupo e limpa em cascata; histórico; `advance`), `clear(..., $force)`, `closeGroup(..., $force)` (ordem final, preenche o mata-mata, avança os "passa direto"; jogo pendente é aviso), `reopenGroup(..., $force)`, `static score()` (conferência do placar). Recusas do site como `TournamentException`; avisos do Lara como `ConfirmationRequired`.

## Tournament\TournamentResultCodeService

Código de lançamento pelo site: `generate()` (8 caracteres, só o sha256 no banco, vence no fim do último dia), `revoke()`, `check()`, `normalize()`, `format()`.

## Tournament\TournamentBracketPresenter

O chaveamento em JSON para o site: `category($categoria, staff: bool)` e `match()`; com `staff`, cada jogo traz `editable`, `locked`, `result_origin`.

## Tournament\TournamentFinanceService

Relatório financeiro por inscrição: `summary()` (por categoria e total: recebido eRede/manual, estornado, líquido, a receber), `payments()`, `overview()`, `csv()`, `methodLabel()`.

## Members\SiteAccountService

Contas do site: `search()`, `duplicatedCpfs()`, `diagnose()`, `update()`, `pullFromMultiClubes()`, `temporaryPassword()`, `createManual()`, `deactivate()`, `restore()`, `static siteHash()` (a senha do site chega em SHA256: `Hash::make(sha256(texto))`). Ver [contas-do-site.md](funcionalidades/contas-do-site.md).

## Tournament\TournamentSetupService

Montagem do torneio: `changeStatus(..., $force)` (caminho normal rascunho → publicado → em andamento → encerrado, e cancelado; qualquer outra, publicar sem categoria e voltar a rascunho com inscrição pedem confirmação), `attachCategory(..., $force)` (copia do catálogo do grupo; inativa ou com inscrições do site fechadas pede confirmação), `updateCategory(..., $force)` (mudar formato com chaveamento pede confirmação e desfaz o chaveamento), `detachCategory(..., $force)` (sem pagamento: confirma e apaga as inscrições; com pagamento: recusa), `linkRules()`.

## Tournament\TournamentNotifier

E-mails `tournament.registered`, `tournament.paid`, `tournament.cancelled` (template `emails.tournament.status`), um por pessoa e torneio, via `EmailService::sendScheduleMail`.

## Ticket\* (Ingressos)

Regras em [ingressos.md](funcionalidades/ingressos.md).

| Serviço | Papel |
|---------|-------|
| `TicketInventory` | `occupiedByLot()` (pagos + pendentes no prazo), `occupiedByDay()` (lotação: `[0 => n]` no evento sem dias; por dia, diárias do dia + passaportes), `capacityOverflow(evento, dias dos ingressos novos)`, `capacityLeft()`/`capacityLeftByDay()`, `currentLot()` (menor ordem ativa, na janela e com sobra), `nextLot()`, `summary()` por tipo. Só conta; não trava. |
| `TicketOrderService` | `buyerFor(Member)` (MultiClubes), `create(event, buyer, items, $counter, $force, $courtesy, $couponCode, $notify)` (trava o evento; aloca ingresso a ingresso, atravessando a virada de lote; aplica o cupom; balcão vira aviso), `issueAtCounter(..., $mode)` (`external` = cria + baixa manual; `courtesy` = valor zero; uma transação, um e-mail), `titleMembers(matrícula)` (pessoas do título no MultiClubes; o sócio compra para elas), `checkCoupon()`, `pay()` (idempotente pelo tid; pagamento tardio reconfere estoque e cupom → `hold_expired`), `recordManual()`, `withdraw()`, `expireOverdue()`, `cancelTicket()`/`cancelTickets(..., $refund)` (um estorno eRede pela soma, antes de gravar, dividido pelos ingressos), `changeHolder()`, `resendLink()`. |
| `TicketCheckinService` | `looksLikeCode()`, `find(código ou CPF, ?evento)` (pelo CPF, o ingresso que entra agora primeiro), `evaluate()` (motivos `ticket_*`; janela do evento ou do dia), `checkIn(..., $source, $force)` (linha em `ticket_checkins`, única por ingresso e dia: o passaporte entra uma vez em cada dia; fora da janela: Lara confirma, monitor nega), `windowLabel()`, `counts(evento, ?dia)`, `dayForCounts()`. Usado pelo `CompanyService` no Monitor de Acesso. |
| `TicketFinanceService` | `totals()`, `byLot()`, `byMethod()`, `byCoupon()`, `csvRows()`. |
| `TicketSetupService` | `changeStatus(..., $force)` (`TRANSITIONS`; publicar exige tipo ativo com lote ativo), `saveDay()`/`deleteDay()` (dentro do evento, sem cruzar; dia com diária não apaga), `saveType()` (dia do evento; não muda depois de vendido), `saveLot(..., $force)` (aviso de quantidade abaixo do ocupado e de preço com venda), `deleteType()`/`deleteLot()` (recusa com ingresso). |
| `TicketPresenter` | JSON do site: evento com lote vigente, cupom, pedido (com desconto e cortesia), ingresso (código e QR só pago; CPF mascarado). |
| `TicketPdf` | PDF dos ingressos pagos (dompdf, QR em SVG). |
| `TicketNotifier` | E-mails `tickets.paid`, `tickets.cancelled`, `tickets.link` (template `emails.tickets.status`); `orderLink()` para o não sócio (`TICKETS_SITE_URL`). |
| `TicketBuyer` | Valor de quem compra: `member(profile, member)` ou `guest(cpf, nome, e-mail, telefone)`. |

---

## FreelancerService

`app/Services/FreelancerService.php`

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `create` | `create($data)` | Cria um freelancer. |
| `get` | `get($cpf)` | Recupera o freelancer pelo CPF. |
| `getFunctions` | `getFunctions()` | Lista as funções disponíveis. |
| `createService` | `createService($data)` | Cria um registro de serviço de freelancer. Deriva `end_date`, `total_hours` e `price`; com `pricing_mode = fixed`, o `price` é o `fixed_price` informado (contrato de valor fixo), e não o das horas. |
| `markAsPaid` | `markAsPaid(FreelancerService $service, User $user)` | Baixa manual de pagamento (fluxo sem Pix). Registra quem deu e quando. |
| `markManyAsPaid` | `markManyAsPaid(array $ids, User $user): int` | Baixa manual em lote. Ignora contratos não aptos ou já pagos. |
| `requestPixForMany` | `requestPixForMany(array $ids, User $user): array` | **Move dinheiro.** Cria um `PixPayment` e enfileira um `SendFreelancerPixPayment` por contrato. Retorna `{queued, skipped, problems}`. **Não** marca `paid` — a baixa vem depois, do banco. |
| `pixBlockReason` | `pixBlockReason(FreelancerService $service): ?string` | Por que o contrato não pode receber Pix agora (sem chave, sem valor, acima do teto, ou já com pagamento em andamento). `null` quando pode. É a trava contra pagamento em dobro. |
| `markAsPaidFromPix` | `markAsPaidFromPix(PixPayment $payment): ?FreelancerService` | Grava a baixa a partir de um Pix `FINALIZADO`. Idempotente — job e reconciliação podem chamar para o mesmo pagamento. |
| `recordDinnerAnswer` | `recordDinnerAnswer(FreelancerService $service, bool $wantsDinner, ?User $assistedBy = null)` | Grava a resposta do jantar, perguntada no tablet depois da assinatura. Registra `dinner_date` junto — é por ela que a cozinha consulta. Lança `FreelancerServiceLockedException` no contrato cancelado, não assinado, sem direito ou **já respondido**: a resposta vale uma vez só. |

---

## Sicoob (`app/Services/Sicoob/`)

> **Movimenta dinheiro real.** Guia completo: [Pix automático (Sicoob)](funcionalidades/pix-sicoob.md).

### SicoobAuthService

`app/Services/Sicoob/SicoobAuthService.php` — OAuth2 `client_credentials` sobre **mTLS**.

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `token` | `token(array $scopes): string` | Access token válido para os escopos pedidos. Cacheado 240s (validade real ~300s), **por conjunto de escopos**. |
| `forgetToken` | `forgetToken(array $scopes): void` | Descarta o token cacheado — usado quando a API responde 401 dentro da validade. |
| `client` | `client(array $scopes): PendingRequest` | Cliente HTTP já com certificado, timeouts e os headers `Authorization` e `client_id`. |

Lança `SicoobCertificateException` (arquivo ausente/ilegível, senha errada, handshake) e
`SicoobAuthenticationException` (credencial recusada). Nunca loga token nem senha.

### SicoobPixPagamentoService

`app/Services/Sicoob/SicoobPixPagamentoService.php` — envio e consulta de Pix.

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `enviar` | `enviar(PixPayment $payment): PixPayment` | Orquestra a tentativa inteira: valida valor → confere saldo → inicia (DICT) → confere titular → confirma. Grava cada transição na linha. |
| `iniciar` | `iniciar(string $chave): array` | `POST /pagamentos`. **Não move dinheiro:** devolve `endToEndId`, `tipo` e `proprietario`. |
| `consultar` | `consultar(string $endToEndId): array` | `GET /pagamentos/{id}`. Estado atual da transação. |
| `reconciliar` | `reconciliar(PixPayment $payment): PixPayment` | Consulta o banco e atualiza a linha. Único caminho legítimo para tirar um pagamento de `unknown`. |
| `formatarValor` | `static formatarValor(float): string` | Formato da API: string com **vírgula**, sem milhar (`1234.5` → `"1234,50"`). |
| `validarValor` | `validarValor(float): void` | Positivo, ≤ `SICOOB_PIX_MAX_AMOUNT`, no máximo 2 casas. Última barreira local. |

### SicoobContaCorrenteService

`app/Services/Sicoob/SicoobContaCorrenteService.php` — só consulta de saldo.

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `assertSaldoSuficiente` | `assertSaldoSuficiente(float $valor): void` | Lança `SicoobInsufficientFundsException` se o disponível não cobrir. |
| `saldoDisponivel` | `saldoDisponivel(): ?float` | `GET /saldo`. `null` quando a consulta não pôde ser feita — e nesse caso o pagamento **segue**: quem recusa por saldo é o banco. Não considera cheque especial. |

---

## CompTimeService

`app/Services/CompTimeService.php` — banco de horas; faz parsing de HTML (Symfony DomCrawler).

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `importFile` | `importFile(string $filePath)` | Faz parsing do HTML de ponto e importa os dados (em transação). |
| `getStructures` | `getStructures()` | Retorna as estruturas/departamentos distintos. |
| `filterEmployees` | `filterEmployees(array $filters)` | Filtra funcionários por estrutura, nome ou código. |
| `getTimeEntriesForEmployees` | `getTimeEntriesForEmployees($employees, $filters)` | Marcações de vários funcionários (período/status). |
| `getTimeEntryDetails` | `getTimeEntryDetails($employeeId, $periodStart = null, $periodEnd = null)` | Detalhes das marcações + ajustes + métricas. |
| `showDayDetails` | `showDayDetails($employeeId, $day)` | Marcações e ajustes de um dia específico. |
| `recalculateAllBalances` | `recalculateAllBalances()` | Recalcula os saldos de todos os funcionários. |

---

## CompanyService

`app/Services/CompanyService.php` — empresas, trabalhadores e regras de acesso.

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `getAllCompanies` | `getAllCompanies()` | Lista todas as empresas. |
| `createCompany` | `createCompany($request)` | Cria empresa com upload de imagem. |
| `getCompanyDetails` | `getCompanyDetails($company)` | Carrega empresa com trabalhadores e regras. |
| `storeWorker` | `storeWorker($data)` | Cria trabalhador (imagem em base64). |
| `storeAccessRule` | `storeAccessRule($data)` | Cria regra de acesso com sincronização de dias da semana. |
| `validateTryToAccess` | `validateTryToAccess($data)` | Valida a tentativa de acesso por CPF ou nome da empresa. Por CPF, consulta terceirizados **e** freelancers (contrato). |
| `registerWorkerAccess` | `registerWorkerAccess(int $workerId)` | Valida e grava no histórico o acesso de um terceirizado já identificado. |
| `registerFreelancerAccess` | `registerFreelancerAccess(int $freelancerId)` | Valida e grava no histórico o acesso de um freelancer — liberado pelo contrato vigente (30 min antes do turno até o término). |

---

## ParkingAccessCorrelationService

`app/Services/ParkingAccessCorrelationService.php` — SIV, busca de placa: liga a leitura da
câmera aos externos registrados na portaria e aos pedidos de carro de aplicativo. Os associados
(catracas do MultiClubes) continuam em `AccessController::findAccessByTime`. Guia:
[Placas de Carro](funcionalidades/estacionamento-placas.md#como-funciona-a-correlação-com-os-acessos).

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `externalsAround` | `externalsAround($entryDate): array` | Terceirizados, freelancers e liberações pontuais de `company_access_logs` registrados a até `EXTERNAL_WINDOW_SECONDS` (60 s) da leitura. Uma linha por pessoa; inclui o registro negado. |
| `appCarRequests` | `appCarRequests(string $plate, $startOfDay, $endOfDay): Collection` | Pedidos de `uber_access_requests` da placa (normalizada) feitos ou liberados no dia. |
| `appCarsByEntry` | `appCarsByEntry(array $entryDates, Collection $requests): array` | Distribui os pedidos liberados entre as leituras do dia: cada um vai para a mais próxima, a até `APP_CAR_WINDOW_SECONDS` (30 min). |
| `appCarRow` | `appCarRow(UberAccessRequest $request): array` | Linha do pedido no formato da tabela de pessoas do acesso. |

---

## RuleValidatorService

`app/Services/RuleValidatorService.php` — validação de regras por data/hora/dia da semana.

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `validate` | `validate($rule, $data)` | Valida a regra contra a data/hora atual. |
| `isBetweenOrGreaterDates` | `isBetweenOrGreaterDates($currentDate, $startDate = null, $endDate = null)` | Verifica o intervalo de datas. |

---

## TelegramService

`app/Services/TelegramService.php` — contatos do Telegram.

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `findContacts` | `findContacts($param)` | Busca contatos por nome, chat_id ou telefone. |
| `createContact` | `createContact($data)` | Cria um contato. |
| `updateContact` | `updateContact($id, $data)` | Atualiza um contato. |

---

## WhatsAppService

`app/Services/WhatsAppService.php` — integração com o WhatsApp Cloud API (Meta Graph v18).

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `sendText` | `sendText($to, $message)` | Envia mensagem de texto via API do WhatsApp. |
| `downloadMedia` | `downloadMedia($mediaId)` | Baixa a mídia do WhatsApp e salva no storage. |

---

## RedeItauService

`app/Services/RedeItauService.php` — gateway de pagamento (Itaú/Rede).

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `authenticate` | `authenticate(): string` | Autentica na API (OAuth, client credentials) e retorna o token. |
| `getTransaction` | `getTransaction(string $tid): array` | Consulta os detalhes de uma transação. |
| `beginRefund` | `beginRefund(array $payments_ids)` | Inicia o estorno de pagamentos. |

`refundPayment` recusa pagamento de baixa manual (`is_manual`) com `InvalidArgumentException`: não passou pela Rede.

---

## EmailService

`app/Services/EmailService.php`

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `processContactForm` | `processContactForm(array $data): void` | Envia o e-mail do formulário de contato (Mailable `ContactMail`) para o endereço administrativo, com tratamento de exceções. |
| `sendScheduleMail` | `sendScheduleMail(array $data): bool` | Envia o e-mail transacional de agendamento **para o próprio sócio** (`$data['email']`). Endereço inválido ou falha de envio viram log e `false` — nunca derrubam o agendamento/pagamento já gravado. |

---

## LoginTokenService

`app/Services/LoginTokenService.php`. Ver [Autenticação e Permissões](04-autenticacao-e-permissoes.md) (seção *LoginTokenService*).

## JwtService

`app/Providers/Services/JwtService.php`. Ver [Autenticação e Permissões](04-autenticacao-e-permissoes.md) (seção *JwtService*).

---

## Replay (`app/Services/Replay/`)

### ReplayResolver
A cascata de herança em um lugar só: **quadra > esporte > padrão**. Telas e API consomem o
mesmo objeto — é o que garante que o preview mostrado ao Marketing é o que a câmera recebe.

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `settingFor` | `settingFor(Place): array` | Orientação e duração efetivas + `source` (`place`/`group`/`default`). Contém a duração à faixa de 5–60s mesmo se o banco tiver valor fora dela. |
| `layoutFor` | `layoutFor(Place, string $orientation): array` | Layout efetivo para a orientação, ou `null` (vídeo limpo). |
| `resolveFor` | `resolveFor(Place): array` | As duas coisas juntas — o que a API entrega. |
| `configHash` | `configHash(): string` | Impressão digital de toda a configuração publicada. O sistema de captura só reprocessa quando ela muda. |

As tabelas são pequenas (uma linha por esporte/quadra), então tudo é carregado de uma vez e
resolvido em memória — evita o N+1 no endpoint mais chamado da API.

### OverlayRenderer
Compõe as logomarcas no arquivo que o sistema de captura queima sobre o vídeo: **PNG
transparente sempre**, e **WebM com canal alpha** quando há GIF animado e ffmpeg no servidor.

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `render` | `render(Layout): Layout` | Compõe, grava com o hash no nome e atualiza o layout. Layout sem peças não gera arquivo (vídeo limpo é escolha válida). |

Sem ffmpeg o módulo funciona inteiro — o problema vai para o log e o PNG continua valendo.
Overlay parado é problema de estética; overlay nenhum é problema de operação.

### MediaService
Ponto único sobre a mídia do Replay: disco, URL e o que é um arquivo aceitável.

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `url` | `url(?string): ?string` (estático) | URL pública. Usa `asset()` e não `Storage::url()`, pela mesma razão do `ImagemService`: o APP_URL fixo sairia errado em outro host/porta. |
| `absolutePath` | `absolutePath(string): string` (estático) | Caminho em disco — o ffmpeg precisa dele. |
| `storeLogo` | `storeLogo(UploadedFile): array` | Valida (PNG/GIF, 8MB), detecta GIF animado pela contagem de blocos de controle, normaliza o PNG e grava. |
| `remove` | `remove(?string): void` | Apaga do disco. |

`VIDEO_DIR` (`replay/videos`) guarda só a **cópia local** dos clipes; o original está no FTP (ver `RemoteVideoStore`).

O tipo sai sempre dos **bytes reais**, nunca do `Content-Type` ou da extensão do nome.

### VideoIntakeService
Recebe o aviso de clipe novo (o arquivo já está no FTP), traz a cópia e amarra a quem for de direito.

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `store` | `store(Camera, string $remotePath, Carbon, int, ?string): array` | Idempotente pelo `external_id` **e** pelo caminho. Baixa o clipe do FTP para a cópia local, confere o tipo pelos bytes, resolve a reserva **paga** que cobria o instante, define a expiração a partir da **gravação** e agenda o aviso único da reserva. Lança `InvalidArgumentException` (422) ou `RemoteStorageUnavailable` (503). |
| `prune` | `prune(): array` | Apaga do FTP, a cópia e o registro dos vencidos — arquivo primeiro: registro órfão alguém vê, arquivo órfão ocupa disco em silêncio. FTP fora → para e devolve `ftp_unavailable: true`. |

### RemoteVideoStore
Os clipes no FTP (disco `replay_ftp`, raiz `REPLAY_FTP_ROOT`) e a cópia local deles (disco `replay`).

| Método | Assinatura | Descrição |
|--------|-----------|-----------|
| `normalizePath` | `normalizePath(string): string` | Caminho relativo à raiz, aceitando-o com ou sem a raiz na frente. Recusa `..`, `.` e trechos vazios. |
| `download` | `download(string $remote, string $local): void` | Baixa para `.part` e renomeia no fim. Arquivo ausente → `InvalidArgumentException`. |
| `ensureLocal` | `ensureLocal(Video): ?string` | Devolve a cópia, refazendo-a do FTP se sumiu; null se o clipe não está mais lá. |
| `delete` | `delete(Video): bool` | Apaga do FTP e a cópia; false se já não estava no FTP. |

Falha de conexão (ou qualquer recusa do driver) vira `RemoteStorageUnavailable` e marca o FTP
como fora por 60 s (`replay:ftp-offline`), para os pedidos seguintes não esperarem o timeout.
O adaptador FTP só lê o arquivo inteiro (`readStream` baixa tudo): por isso a validação na
recepção já produz a cópia que vai ser servida.
