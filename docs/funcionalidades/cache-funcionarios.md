# Cachê de Funcionários

## O que é

Pagamento extra a **funcionários da casa** por um turno fora da rotina (um evento, um apoio a outro
setor). É o irmão do contrato de freelancer, mas para gente de dentro — e por isso **não** tem
contrato civil, aditivo, limite semanal, aprovação da diretoria nem Pix automático.

O que o distingue de ponta a ponta: **o coordenador informa o horário previsto e o funcionário
informa o horário real**, no momento em que assina. Divergindo, o cachê volta para uma segunda
aprovação antes de o dinheiro sair.

## Fluxo

```
Coordenador do setor solicita EM LOTE (horário previsto)
  → Gerência aprova item a item
    → Funcionário assina no tablet/celular, informando o horário REAL
      → o horário mudou?
           não → Financeiro
           sim → Coordenador reconfere → Gerência reconfere → Financeiro
        → Financeiro dá baixa (manual)
```

## Para quem

| Etapa | Quem faz | Onde |
|---|---|---|
| Solicitar em lote | **Coordenador de setor**, para os funcionários do seu departamento | `/cache/solicitar` |
| Aprovar (1ª vez) | **Coordenador do setor `Gerência`** | `/cache/aprovacao` |
| Assinar | O **funcionário**, sem login | `/cache/assinatura` |
| Reconferir (2ª vez) | Coordenador do setor **e** a Gerência | `/cache/reconferencia` |
| Dar baixa | Setor **Contabilidade** ou **Gerência** | `/cache/financeiro` |

O vínculo entre coordenador e "seus funcionários" é o **nome do setor batendo com
`employees.department`** — a mesma regra do Banco de Horas, agora em `App\Support\EmployeeScope`,
com **RH e TI** enxergando todos.

## Valor: faixa de horas, sem proporção

O cachê **não** é proporcional. A duração do turno serve apenas para achar a **faixa**, e cada faixa
tem seu valor de tabela, cadastrado por função.

| Regra | Como é |
|---|---|
| Faixas | de **2h a 11h**, uma linha por hora cheia (`function_cache_rates`) |
| Arredondamento | soma-se **15 minutos** e toma-se a hora do resultado — **3h45 paga 4h**, 3h44 paga 3h |
| Piso | menos de 2h paga a faixa de 2h |
| Teto | 11h ou mais paga a faixa de 11h |
| Virada de meia-noite | 22:00 → 02:00 são 4h (`CalculatesShiftPeriod`, compartilhado com o freelancer) |

A conta vive em `FunctionFreelancer::cacheBilledHours()` — um lugar só, exercitado em
`tests/Unit/EmployeeCacheRateTest.php`. A tela repete a conta em JavaScript apenas para mostrar o
valor enquanto se digita; **quem grava é sempre o servidor**.

### O catálogo de funções ganhou modalidade

`function_freelancers` agora tem `type`:

| Tipo | Quem exerce | Preço |
|---|---|---|
| `freelancer` | pessoa externa | por bloco de 15 min (`price`) |
| `cache` | funcionário da casa | por faixa de horas (`function_cache_rates`) |

O tipo é **exclusivo**: "Garçom Freelancer" e "Garçom Cachê" são dois cadastros. Uma função só é
oferecida no fluxo do seu tipo, e **função com lançamento não troca mais de modalidade** — trocar
mudaria a conta de registros que já existem. Função de cachê só entra na tela de solicitação com as
**dez faixas preenchidas**.

## Assinatura do funcionário

`/cache/assinatura` é uma tela **fora da sessão do painel**: o funcionário vem da importação do ponto
e **não é usuário do sistema**.

- Ele se identifica por **matrícula ou CPF** — sem senha e sem PIN.
- Vê apenas os cachês **dele** que a gerência **já aprovou**.
- Informa o **horário real** (a tela abre preenchida com o previsto) e desenha o **traço**, guardado
  em PNG no disco público.
- O valor é **recalculado na assinatura**, pelo horário real. O previsto não é reaproveitado — senão
  um turno que esticou continuaria sendo pago pelo que se imaginou na véspera.

O que sustenta a assinatura não é uma senha, e sim o conjunto: o traço guardado, o horário que só ele
poderia informar e — quando esse horário diverge — a reconferência antes do pagamento. Trocar o id na
URL não serve: o cachê precisa ser do funcionário identificado na sessão.

## Divergência e reconferência

**Divergência é qualquer alteração do início ou do término previstos** — não a mudança de faixa. O
que a gerência aprovou foi um turno; se o turno mudou, quem aprovou vê de novo, ainda que o valor
tenha ficado igual.

- Sem divergência, o cachê vai **direto ao financeiro**: aconteceu exatamente o que foi aprovado.
- Com divergência, entra na fila do **coordenador** e depois na da **gerência**. A tela mostra
  *previsto × real* lado a lado e a diferença de valor.
- **Recusar em qualquer uma das duas encerra o cachê** (`recheck_rejected_at`) — resolver isso é
  conversa fora do sistema.

## Financeiro

Baixa **manual**: grava `paid`, `paid_at` e `paid_by`. O cachê não passa pelo Pix automático do
Sicoob, e o cadastro de funcionário não tem chave PIX. A tela dá baixa individual ou em massa;
ids que deixaram de estar aptos com a tela aberta são ignorados e contados no aviso.

## Estados

O estado **não é uma coluna**: sai da leitura dos carimbos (`EmployeeCache::statusLabel()`), como nos
contratos de freelancer. Um enum ali seria uma segunda verdade a manter sincronizada com as datas que
a tela mostra.

`Aguardando aprovação da gerência` → `Aguardando assinatura do funcionário` → `Assinado` →
(`Aguardando reconferência do coordenador` → `Aguardando reconferência da gerência`) →
`Liberado para o financeiro` → `Pago`. Fora da linha: `Recusado pela gerência`,
`Recusado na reconferência`, `Cancelado`.

## Regras de negócio

- **Solicitação é tudo-ou-nada:** a gravação corre em transação; nada entra pela metade.
- **Um lote não muda mais de estado depois da análise.** O que acontece dali em diante (assinatura,
  reconferência, baixa) é de **cada cachê** — cada funcionário assina o seu.
- **Cachê assinado não é cancelado**, e cachê pago também não.
- **Valores são congelados** (`expected_price` na solicitação, `price` na assinatura): mexer na
  tabela de faixas depois não reprecifica o que já tramitou.
- **Não há teto por período** — diferente do limite de 2 serviços em 7 dias do freelancer.

## Referência técnica

- **Rotas:** `routes/web.php` — grupo `cache/assinatura` (público, com throttle) e o grupo `cache`
  dentro de `auth`.
- **Controllers:** `app/Http/Controllers/Employee/{CacheController,CacheFinanceController,CacheSignatureController}`.
- **Service:** `app/Services/EmployeeCacheService.php` — o **único** lugar que grava os carimbos do
  fluxo; o painel e a tela de assinatura não compartilham sessão e não podem divergir na regra.
- **Models:** `EmployeeCache` (estado, divergência, período), `EmployeeCacheBatch` (lote),
  `FunctionFreelancer` (modalidade e faixa), `FunctionCacheRate`, `Employee::findByCodeOrCpf()`.
- **Trait:** `App\Models\Concerns\CalculatesShiftPeriod` — virada de meia-noite e duração, hoje
  compartilhada com `FreelancerService`.
- **Escopo de funcionários:** `App\Support\EmployeeScope` (extraído do `CompTimeController`).
- **Permissões:** vínculo de setor, não permissão do Spatie. Gate
  `manage-employee-cache-payments` (`AppServiceProvider`) para o financeiro.
- **Migrations:** `2026_08_05_100001` a `2026_08_05_100004`.
- **Testes:** `tests/Unit/EmployeeCacheRateTest.php` (faixa, arredondamento, piso e teto) e
  `tests/Unit/EmployeeCacheFlowTest.php` (divergência e quando o cachê fica pagável).
