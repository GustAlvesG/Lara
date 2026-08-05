# Pix automático (Sicoob) — pagamento de freelancers

> **Esta funcionalidade movimenta dinheiro real da conta do clube.** Leia a seção
> [Riscos e o que nunca fazer](#riscos-e-o-que-nunca-fazer) antes de ligar em produção.

## O que é

O botão **"Dar baixa"** da aba Financeiro de Serviços/Contratos passa a **transferir o valor
do contrato via Pix** para a chave do freelancer, usando a API *Pix Pagamentos* do Sicoob.

Antes desta integração, "Dar baixa" era só uma marcação: alguém pagava por fora e registrava
no sistema. Agora o próprio sistema paga — e o registro passa a ser consequência do
pagamento, não uma anotação sobre ele.

## Para quem

Usuários com o Gate `manage-freelancer-payments` (setores Contabilidade e Gerência) — os
mesmos que já davam baixa manualmente. Nenhuma permissão nova foi criada.

## Pré-requisitos

| Requisito | Onde |
|-----------|------|
| App ativo no portal `developers.sicoob.com.br` com as APIs **Pix Pagamentos** e **Conta Corrente** habilitadas | Portal Sicoob |
| Certificado ICP Brasil (PJ) convertido para PEM | `storage/certificates/` |
| Variáveis preenchidas | `.env` do servidor |
| Worker de fila rodando | `php artisan queue:work` |
| Scheduler rodando | `cron` chamando `php artisan schedule:run` |
| `SICOOB_PIX_ENABLED=true` | `.env` — **por último** |

Passo a passo de instalação: [Pix Sicoob — instalação](../pix-sicoob-instalacao.md).

---

## O fluxo, passo a passo

### Visão geral

```
Clique em "Dar baixa"
        │
        ├─► cria uma linha em `pix_payments` (status: pending)
        └─► enfileira SendFreelancerPixPayment
                    │
                    ├─ 1. valida valor (positivo, ≤ teto, 2 casas)   ← local, sem rede
                    ├─ 2. consulta saldo (API Conta Corrente)        ← cortesia
                    ├─ 3. POST /pagamentos           ── DINHEIRO PARADO
                    │      devolve endToEndId + titular da chave
                    ├─ 4. confere titular × CPF do freelancer        ← local
                    ├─ 5. POST /pagamentos/confirmacao  ── O DINHEIRO SAI
                    └─ 6. lê `estado` da resposta
                              │
        FINALIZADO ───────────┴──── EM_PROCESSAMENTO ──── REJEITADO ──── (sem resposta)
             │                            │                   │                │
        dá baixa no                  aguarda a           nada saiu;        status
        contrato (paid)             reconciliação        pode refazer     `unknown`
```

### O detalhe que organiza tudo: são duas chamadas

A API do Sicoob separa o envio em dois passos, e a diferença entre eles é a base de toda a
segurança desta implementação:

| Passo | Endpoint | Move dinheiro? | Repetir é seguro? |
|-------|----------|----------------|-------------------|
| Iniciação DICT | `POST /pagamentos` | **Não** | Sim |
| Efetivação | `POST /pagamentos/confirmacao` | **Sim** | **Não — transfere de novo** |
| Consulta | `GET /pagamentos/{endToEndId}` | Não | Sim |

A iniciação consulta a chave no DICT do Bacen, devolve **quem é o dono da chave** e **reserva
um `endToEndId`** (identificador fim a fim de 32 caracteres). Com isso em mãos e nada ainda
gasto, dá para conferir o titular e o saldo. E, depois da confirmação, quando a resposta não
volta, existe um identificador para **perguntar ao banco o que aconteceu** — em vez de
reenviar às cegas.

### Os estados de um pagamento

Cada tentativa é uma linha em `pix_payments` que nunca é sobrescrita. O `status` responde a
única pergunta que importa: **o dinheiro saiu?**

| status | O dinheiro saiu? | Pode reenviar? |
|--------|------------------|----------------|
| `pending` | Não, nada foi chamado | — (ainda vai rodar) |
| `initiated` | Não, só reservou o endToEndId | — (ainda vai rodar) |
| `sent` | Está saindo (banco processando) | Não |
| `finalized` | **Sim** | Não |
| `rejected` | Não — o banco recusou | **Sim** |
| `failed` | Não — parou antes de confirmar | **Sim** |
| `unknown` | **Não sabemos** | **NUNCA** |

`unknown` é o estado mais importante desta lista. Ele significa: a confirmação foi enviada e
a resposta se perdeu (timeout, queda de rede, 5xx). O Pix **pode** ter sido processado.
Reenviar nesse estado é o jeito de transferir duas vezes.

### O que dá baixa no contrato

Só `finalized`. Enquanto o Pix estiver em qualquer outro estado, o contrato continua
**pendente** na tela — mesmo que o clique já tenha acontecido. `EM_PROCESSAMENTO` não é
dinheiro na conta do freelancer.

Quem grava a baixa é `FreelancerService::markAsPaidFromPix()`, chamado pelo job (quando o
banco responde `FINALIZADO` na hora) ou pela reconciliação (quando responde depois). O
`paid_by` guarda **quem clicou**, não o processo que confirmou: a responsabilidade pelo
pagamento é de quem o autorizou.

---

## Por que o Job não tem retry

`SendFreelancerPixPayment` tem `tries = 1`, de propósito.

O retry automático de fila existe para operações que, repetidas, dão no mesmo. Confirmar um
Pix não é uma delas. E o caso em que o retry parece mais útil — "deu timeout, tenta de novo" —
é exatamente aquele em que ele é mais perigoso: o silêncio da rede não distingue *não
processou* de *processou e a resposta se perdeu*.

Quem decide se cabe uma nova tentativa é o **estado gravado**, depois de o banco ter dito o
que aconteceu. Isso tem uma contrapartida obrigatória: a **reconciliação**.

### A reconciliação

```
php artisan sicoob:pix-reconciliar
```

Agendada a cada minuto em `routes/console.php`. Ela varre os pagamentos em `initiated`,
`sent` e `unknown`, chama `GET /pagamentos/{endToEndId}` e escreve o que o banco respondeu —
dando baixa nos que finalizaram.

**Ela nunca envia nada.** É só consulta, e é por isso que pode rodar de minuto em minuto sem
risco. É ela que fecha o ciclo deixado em aberto pela ausência de retry.

Passado `SICOOB_PIX_RECONCILE_TIMEOUT` (24h por padrão) sem desfecho, o caso vira log
`critical` pedindo conferência humana no extrato.

### Como a reconciliação lê um 404

Se o banco responde "não conheço esse `endToEndId`", a leitura depende de **nós já termos
confirmado ou não** — e a diferença é entre destravar um contrato e esconder um pagamento:

| Estado quando o 404 chega | Leitura | O que acontece |
|---------------------------|---------|----------------|
| `initiated` (`confirmed_at` nulo) | O id foi só reservado; a confirmação nunca saiu | Vira `failed` — **nada saiu**, e o contrato volta a aceitar pagamento |
| `sent` / `unknown` (já confirmamos) | 404 **não prova** que não pagou; pode ser latência de liquidação | Continua em aberto e escala pelo prazo acima |

### Tentativas órfãs

Se o job se perde entre o commit e a fila (worker parado, `queue:flush`, deploy no meio), a
linha fica em `pending` para sempre — e como `pending` bloqueia, o contrato nunca mais
aceitaria pagamento.

A reconciliação varre linhas em `pending` **sem `end_to_end_id`** com mais de 30 minutos e as
marca como `failed`. É seguro afirmar que nada saiu nesse caso, e só nesse: sem
`end_to_end_id` a iniciação nunca concluiu, logo a confirmação nunca pôde ter sido enviada.
A folga de 30 minutos existe para uma fila apenas atrasada não ser confundida com um job
perdido — liberar cedo demais permitiria um segundo envio.

---

## As barreiras antes de o dinheiro sair

Em ordem de execução:

1. **Contrato apto** — assinado pelas duas partes, aprovado pela gerência **e** pela
   diretoria, não cancelado, não aditivado. (Regra que já existia.)
2. **Sem Pix em andamento** — `pixBlockReason()` faz `lockForUpdate` no contrato e recusa se
   já houver pagamento em estado bloqueante. É o que impede duplo clique, duas abas e F5 no
   POST de virarem duas transferências.
3. **Chave PIX presente** no cadastro do freelancer.
4. **Valor** positivo, com no máximo 2 casas decimais e **≤ `SICOOB_PIX_MAX_AMOUNT`**.
   Um preço digitado errado (R$ 25.000 no lugar de R$ 250,00) para aqui, não no extrato.
5. **Saldo** — pré-checagem via `GET /saldo` da API Conta Corrente. Se a consulta falhar, o
   pagamento **segue**: quem recusa por saldo é o banco, e uma API de consulta fora do ar não
   pode travar o financeiro. Só a resposta explícita de "não tem saldo" bloqueia.
6. **Titular da chave** — o CPF/CNPJ que o DICT devolve tem de ser o CPF do freelancer
   cadastrado. É o que impede o dinheiro de ir para um terceiro quando a chave está errada ou
   mudou de dono. Controlado por `SICOOB_PIX_VALIDAR_TITULAR`.

---

## Campos e formatos da API

Conferidos na especificação oficial **"Pagamentos PIX 2.0.24.6"**. Os que erram fácil:

| Campo | Formato | Observação |
|-------|---------|------------|
| `valor` | **string com vírgula** — `"1234,50"` | Não é float, não é centavos, não aceita separador de milhar. Regex: `^[0-9]{1,18}([,][0-9]{1,2})?$` |
| `meioIniciacao` | `CHAVE` \| `MANUAL` \| `QRCODE` | Usamos `CHAVE` |
| `endToEndId` | `^[a-zA-Z0-9]{32}$` | Gerado pelo **banco**, na iniciação |
| `estado` | `FINALIZADO` \| `EM_PROCESSAMENTO` \| `REJEITADO` \| `NÃO_REALIZADO` | **`NÃO_REALIZADO` vem acentuado** — normalizado em `normalizarEstado()` |
| `descricao` | máx. 140 caracteres | Aparece no extrato das duas pontas |
| `origem` | opcional em produção | O Sicoob usa a conta do certificado |
| `destino` | **omitido** de propósito | Com `meioIniciacao: CHAVE` o destino já foi resolvido na iniciação; mandá-lo só cria chance de divergência |

Erros seguem RFC 7807: `{type, title, status, detail, correlationId, violacoes[]}`.

**Escopos OAuth:** `pixpagamentos_escrita`, `pixpagamentos_consulta` (Pix) e `cco_consulta`
(Conta Corrente). São conjuntos diferentes, e o token é cacheado **por conjunto**.

---

## Mensagens ao usuário

| Situação | O que a tela mostra |
|----------|---------------------|
| Enviado para a fila | "Pix enviado para processamento. A baixa é registrada quando o banco confirmar." |
| Aguardando o banco | Badge azul **Em processamento** |
| Concluído | Badge verde **Pago** + o `endToEndId` |
| Recusado | Badge vermelho **Rejeitado** + o motivo + "Nada foi transferido; pode tentar de novo." |
| Desfecho desconhecido | Badge vermelho **Conferir no banco** + "Não refaça a baixa — avise a TI." |
| Chave de outro titular | "A chave PIX cadastrada pertence a outra pessoa (NOME)." |
| Saldo insuficiente | "Saldo insuficiente na conta para este Pix. Nenhum valor foi transferido." |

Toda mensagem de falha diz explicitamente se o dinheiro saiu ou não. Essa é a informação que
o financeiro precisa para decidir o que fazer.

---

## Riscos e o que nunca fazer

**Nunca:**

- **Aumentar `tries` no Job.** É a trava contra pagamento em dobro.
- **Reenviar um pagamento em `unknown`** sem antes conferir o extrato ou rodar a
  reconciliação. Este é o caminho para transferir duas vezes.
- **Commitar** o `.pfx`, o `.pem`, a senha do certificado ou o `client_id`. O `.gitignore`
  cobre `storage/certificates/`, `*.pfx`, `*.p12`, `*.pem`, `*.key` e `*.crt`.
- **Logar** o access token ou a senha do certificado. O canal `sicoob` é auditoria, não
  depuração.
- **Editar linhas de `pix_payments`** à mão para "consertar" um estado. Elas são a trilha de
  auditoria do dinheiro; corrigir o registro sem corrigir a realidade cria um problema pior.

**Atenção com o sandbox:** o ambiente de sandbox do Sicoob é um *mock estático*.
`POST /pagamentos` sempre devolve `endToEndId: "stringstringstringstringstringst"`, e
`POST /pagamentos/confirmacao` **sempre responde 400** (`"Dados do campo 'origem' precisam ser
preenchidos manualmente"`), mesmo com `origem` preenchida. O sandbox serve para validar mTLS,
token e conectividade — **não** para exercitar um envio bem-sucedido. A cobertura do caminho
feliz está nos testes com `Http::fake()`.

---

## Diagnóstico

| Sintoma | Causa provável |
|---------|----------------|
| `SicoobCertificateException` com "não encontrado ou sem permissão" | O usuário do PHP (`www-data`) não lê o arquivo em `storage/certificates/` |
| `SicoobCertificateException` no handshake | Chave e certificado não são o mesmo par, senha errada, ou certificado vencido |
| `SicoobAuthenticationException` com `invalid_client` | `SICOOB_CLIENT_ID` errado, app inativo no portal, ou escopo não habilitado |
| 400 com `violacoes: [origem]` | Está apontando para o sandbox (ver acima), ou a cooperativa exige `origem` — preencha `SICOOB_ORIGEM_*` |
| Pix fica em `sent` para sempre | O scheduler não está rodando: `sicoob:pix-reconciliar` nunca executa |
| Contrato não recebe baixa | O worker de fila não está rodando, ou o Pix não chegou a `FINALIZADO` |
| "já existe um Pix para o contrato" e nada acontece | Tentativa órfã em `pending` — a reconciliação libera em até 30 min; se persistir, o scheduler não está rodando |

**Primeiro comando a rodar** em qualquer suspeita — ele não transfere nada:

```bash
sudo -u www-data php artisan sicoob:testar --chave=<uma chave pix>
```

Confere configuração, certificados, token, saldo e DICT, e diz em qual etapa quebrou.

### Quando o erro é 401

A documentação do Sicoob atrela o 401 aos headers de autenticação, então há duas
ferramentas dedicadas a isso.

**Do lado da aplicação** — mostra os headers que o Guzzle põe no fio e varre variações
(presença, ausência, `Client-Id`, `client-id`, `X-Client-Id`, `Accept`):

```bash
sudo -u www-data php artisan sicoob:testar --chave=<chave> --headers
```

**Sem o Laravel na frente** — curl puro, para descartar a aplicação como suspeita e gerar
um anexo de chamado:

```bash
sudo -u www-data ./sicoob-diagnostico.sh --env /var/www/html/Lara/.env --chave=<chave>
```

Como ler o resultado:

| O que aparece | Conclusão |
|---|---|
| Alguma variação responde 2xx | É cabeçalho. Ajuste a configuração para essa variação |
| Alguma variação responde **404** | A autorização passou ali (o recurso é que não existe) — compare com as linhas 401 |
| **Todas** as variações dão o mesmo código | Não é cabeçalho. Sobra a autorização do produto para o `client_id` no gateway — resolve-se no portal/cooperativa |

Um detalhe já verificado e que costuma confundir: a especificação de **Conta Corrente**
declara o header `client_id` como obrigatório; a de **Pix Pagamentos não o menciona**. O flag
`SICOOB_PIX_ENVIAR_CLIENT_ID=false` omite o header apenas nas chamadas de Pix, mantendo-o na
Conta Corrente.

Outra causa de 401 que não aparece em inspeção visual: **espaço ou CRLF dentro do
`SICOOB_CLIENT_ID`** no `.env`. O script checa isso antes de qualquer chamada.

**Onde olhar:** `storage/logs/sicoob-YYYY-MM-DD.log` (retenção de 180 dias) e a tabela
`pix_payments`.

Consulta útil:

```sql
SELECT id, freelancer_service_id, end_to_end_id, amount, status, bank_state,
       rejection_detail, created_at
FROM pix_payments
WHERE status IN ('unknown', 'sent', 'initiated')
ORDER BY created_at;
```

---

## Referência técnica

| Componente | Arquivo |
|------------|---------|
| Configuração | `config/sicoob.php` |
| Autenticação (mTLS + token) | `app/Services/Sicoob/SicoobAuthService.php` |
| Envio e consulta de Pix | `app/Services/Sicoob/SicoobPixPagamentoService.php` |
| Consulta de saldo | `app/Services/Sicoob/SicoobContaCorrenteService.php` |
| Job de envio | `app/Jobs/SendFreelancerPixPayment.php` |
| Reconciliação | `app/Console/Commands/ReconcilePixPayments.php` |
| Pré-checagem (`sicoob:testar`) | `app/Console/Commands/TestSicoobConnection.php` |
| Diagnóstico sem Laravel | `sicoob-diagnostico.sh` (raiz do projeto) |
| Trilha de auditoria | `app/Models/PixPayment.php` + migration `create_pix_payments_table` |
| Orquestração pelo financeiro | `app/Services/FreelancerService.php` (`requestPixForMany`, `pixBlockReason`, `markAsPaidFromPix`) |
| Tela | `app/Http/Controllers/Freelancer/FinanceController.php` + `resources/views/freelancer/services/finance.blade.php` |
| Exceptions | `app/Exceptions/Sicoob/` |
| Testes | `tests/Feature/SicoobPixPagamentoTest.php`, `tests/Feature/FreelancerPixOnPaymentTest.php`, `tests/Unit/SicoobPixValorTest.php` |

Guia relacionado: [Freelancers](freelancers.md).
