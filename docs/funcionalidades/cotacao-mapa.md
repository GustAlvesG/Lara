# Mapa de Cotação (Questor)

## O que é

Substitui a planilha de Excel que a compra usa hoje para cotar preço com vários
fornecedores. O mapa nasce de uma **Solicitação de Compra (SC) do Questor**: os
itens viram linhas, os fornecedores viram colunas, e o comprador digita preço,
frete, prazo e condição de pagamento célula a célula.

O arquivo exportado sai no **mesmo layout da planilha em uso** — porque ela é
impressa, assinada e arquivada, e mudar a posição das linhas quebraria um hábito
que funciona.

## A regra inegociável

> **O banco do Questor (`FUNCSIDERURG`) é SOMENTE LEITURA neste módulo.**
> Nada é gravado, alterado ou apagado lá. Nem com `QUESTOR_DRY_RUN` desligado —
> aquela trava é do módulo de *autorização de ordem de compra*, que é outro.

Toda a cotação digitada vive no banco próprio da Lara (tabelas `cotacao_*`).

Três camadas sustentam isso, nesta ordem de importância:

1. **Permissão de banco.** O login usado pela conexão `questor_sqlsrv` deve ter
   apenas `SELECT`. Isto é com o DBA — código não é permissão de banco.
2. **Caminho de acesso único.** Os repositórios de cotação herdam de
   [`QuestorReadRepository`](../../app/Services/Questor/QuestorReadRepository.php),
   que expõe só `table()`, `select()` e `selectCached()`. Não há `insert`,
   `update`, `statement` nem acesso ao objeto de conexão.
3. **Trava de instrução.** `select()` recusa qualquer SQL que não comece por
   `SELECT` ou `WITH`, antes de tocar na conexão e independentemente de o módulo
   estar ligado. Coberto por
   [`QuestorSomenteLeituraTest`](../../tests/Feature/Cotacao/QuestorSomenteLeituraTest.php).

Além disso: **todo parâmetro entra por binding.** Nenhum valor de tela — nem
`CD_SOLICITACAO`, nem termo de busca — é concatenado na string do SQL.

---

## As três armadilhas do schema

São as três coisas que fazem um mapa de cotação errar em silêncio. Cada uma está
tratada no código, com o comentário no ponto em que ela aparece.

### 1. `CD_MATERIAL` é NULL-able na solicitação

`TBL_COMPRAS_SOLICITACAO_ITENS.CD_MATERIAL` aceita `NULL`. O solicitante pode ter
digitado o item como texto livre em `DS_MATERIAL`, sem cadastro em
`TBL_MATERIAIS`.

Esse item **existe, é comprado, e não tem histórico por código**.

| Onde | O que o código faz |
|------|--------------------|
| `SolicitacaoItemDTO::semCadastro()` | A pergunta fica no DTO, e não espalhada como `=== null` em cinco lugares. |
| `MapaImportService` | A linha entra no mapa normalmente, com os campos `ult_compra_*` nulos. |
| `MapaController::historicoItem()` | Devolve o modal com a mensagem *"item sem cadastro — sem histórico"* **sem consultar o ERP**. |
| Grade e prévia | Etiqueta "sem cadastro" ao lado da descrição. |
| Aba "Histórico" do XLSX | Escreve o texto na coluna da última compra, em vez de deixar a linha em branco e parecer erro de exportação. |

**Nunca deixar isso quebrar a tela.** É o comportamento normal de uma SC, não um
defeito.

### 2. `CD_STATUS` da NF de entrada não tem FK

`TBL_COMPRAS_NOTAFISCAL_ENTRADA.CD_STATUS` é `DEFAULT 2` e **não tem foreign key
declarada**. Duas consequências:

- **Todo join com `TBL_STATUS` é `LEFT JOIN`.** Com `INNER`, uma nota cujo status
  não está cadastrado sumiria do histórico sem aviso — o pior jeito de errar um
  preço.
- **O código de "cancelada" não pode ser hardcoded.** Não há nada no schema que
  diga qual número é esse.

Descubra rodando:

```bash
php artisan cotacao:descobrir-config
```

O comando roda as consultas de descoberta (`0.1b` e `0.2`), imprime a tabela e
sugere o valor para o `.env`:

```
QUESTOR_STATUS_NF_CANCELADA=
```

**Deixar a chave vazia é uma resposta legítima.** Sem ela, nenhuma nota é
descartada por status. Incluir uma nota cancelada de vez em quando é menos grave
do que esconder compras boas do histórico por causa de um chute errado.

#### Resultado na base de produção (04/09/2026)

| `CD_STATUS` | `DS_STATUS` | Notas | Período |
|---|---|---:|---|
| 2 | FATURADO | 15.936 | 20/07/2022 – 03/09/2026 |
| 1 | PENDENTE | 57 | 01/08/2022 – 23/07/2026 |
| 4 | CANCELADO | 15 | 15/08/2024 – 23/07/2026 |

Ou seja: **`QUESTOR_STATUS_NF_CANCELADA=4`**. Só três status aparecem, e nenhum
deles é órfão de `TBL_STATUS` — o `LEFT JOIN` continua obrigatório de qualquer
forma, porque nada no schema garante que siga assim.

### 3. O que conta como "compra" é `X_ATUALIZA_DT_ULTIMA_COMPRA`

`TBL_CME.X_ATUALIZA_DT_ULTIMA_COMPRA` (bit, default 1) é **o próprio flag que o
Questor usa** para dizer "esta operação conta como compra".

Filtrar por ele exclui devolução, transferência, remessa e industrialização sem
ninguém precisar manter uma lista de CFOP à mão. Todas as consultas de histórico
usam `ISNULL(cme.X_ATUALIZA_DT_ULTIMA_COMPRA, 1) = 1` — o `ISNULL` mantém a
entrada cujo CME não está cadastrado, porque na dúvida ela é compra (é o default
da coluna no ERP).

A segunda tabela do `cotacao:descobrir-config` lista os CME em uso e quais contam
como compra. **Confira antes de fechar o filtro.** Se uma compra de verdade
estiver de fora, o cadastro do CME no Questor é que precisa ser corrigido — não
o filtro.

#### Resultado na base de produção (04/09/2026) — e a ressalva

**As 14 operações em uso têm `X_ATUALIZA_DT_ULTIMA_COMPRA = 1`.** Nenhuma está
marcada como "não conta como compra".

Isso quer dizer que, **nesta base, o flag não discrimina nada** — provavelmente
o default 1 nunca foi ajustado no cadastro de CME. O filtro continua sendo o
certo (é o critério do próprio ERP, e passa a valer sozinho no dia em que alguém
corrigir o cadastro), mas hoje ele não exclui as operações que, pelo nome, não
são compra negociada:

| `CD_CME` | `DS_CME` | Itens |
|---:|---|---:|
| 24 | ENTRADA - BONIFICAÇÃO, DOAÇÃO OU BRINDE | 41 |
| 36 | SAÍDA - TRANSF. DE MERC. ADQ. DE TERCEIROS | 24 |
| 25 | ENTRADA - AMOSTRA GRÁTIS | 1 |
| 26 | ENTRADA - RETORNO DE MERC. REMETIDA P/ CONSERTO | 1 |
| 41 | SAÍDA - ANULAÇÃO DE VALOR RELATIVO A ENERGIA | 1 |

São 68 itens em ~44.500 — irrelevante no agregado, **mas não para um material
específico**: se a bonificação foi a última entrada daquele item, ela vira a
"última compra" do mapa e a comparação de preço sai errada para aquela linha.

**Isto é decisão de operação, não de código, e por isso não foi resolvido por
conta própria.** Dois caminhos, nesta ordem de preferência:

1. **Corrigir no Questor**: zerar `X_ATUALIZA_DT_ULTIMA_COMPRA` nos CME que não
   são compra. Conserta o mapa e todo o resto do ERP que usa o mesmo flag.
2. **Excluir por código no filtro da Lara**: exige uma chave de configuração
   nova (`cotacao.cme_excluidos`) e um `NOT IN` nas consultas de histórico.
   Não implementado — peça se for o caminho escolhido.

---

## Configuração

Tudo em `config/questor.php`, chave `cotacao`. No `.env`:

| Chave | Padrão | Para que serve |
|-------|--------|----------------|
| `QUESTOR_STATUS_NF_CANCELADA` | *(vazio)* | Código de NF cancelada. Ver armadilha 2. |
| `QUESTOR_COTACAO_MESMA_FILIAL` | `false` | Restringir a última compra à filial da SC. Desligado por padrão: o preço de uma tinta não muda por ela ter entrado noutra filial. |
| `QUESTOR_COTACAO_MESES_HISTORICO` | `24` | Janela do drill-down do item. |
| `QUESTOR_COTACAO_LIMITE_HISTORICO` | `20` | Quantas entradas o drill-down lista. |
| `QUESTOR_COTACAO_CACHE_TTL` | `900` | Cache do histórico, em segundos. `0` desliga. |
| `QUESTOR_COTACAO_MAX_FORNECEDORES` | `10` | Teto de colunas por mapa (o XLSX modelo vai de E a N). |

O módulo respeita `QUESTOR_ENABLED`: desligado, os mapas já criados continuam
abrindo e editáveis — só a criação de mapa novo e o histórico dependem do ERP.

## Acesso

**O módulo é do setor Contabilidade.** Estar no setor — em **qualquer papel,
colaborador ou coordenador** — dá acesso à aba e ao trabalho todo: ver, montar o
mapa, digitar preço, escolher vencedor, fechar e exportar.

Vincular alguém: **Setores → Contabilidade → adicionar usuário**. Não há mais
nada a configurar; tirar do setor corta o acesso na hora (é uma consulta por
requisição, memorizada só dentro dela).

### Não há permissão do Spatie no caminho — de propósito

É o mesmo arranjo do financeiro dos freelancers (`manage-freelancer-payments`):
quando o acesso é atribuição de setor, exigir **também** uma permissão cria uma
segunda porta que ninguém lembra de abrir. O efeito prático é o pior possível —
o funcionário entra no setor, continua levando 403, e ninguém sabe por quê.

O acesso passa pelo Gate `acessar-cotacao`, definido em `AppServiceProvider` e
respondido por `User::canAccessCotacao()`, que usa `belongsToSectorNamed()` (o
vínculo em qualquer papel) e **não** `isCoordinatorOfSectorNamed()`.

Duas consequências que valem ser ditas em voz alta:

> **A role `admin` não abre este módulo.** Quem administra o sistema não cota
> compra por consequência disso; entra no setor quem de fato cota.

> **Nenhuma permissão do Spatie pode se chamar `acessar-cotacao`.** O Spatie
> registra um `Gate::before` que consulta as permissões do usuário para
> qualquer habilidade — uma permissão com esse nome exato passaria por cima da
> checagem de setor, e o `admin` (que recebe `Permission::all()`) entraria sem
> estar na Contabilidade. O projeto se protege por nomenclatura: **Gates com
> hífen, permissões com espaço ou ponto**. Há um teste vigiando isso.

### A única exceção: reabrir

Reabrir um mapa **fechado** é do **coordenador** da Contabilidade, não de
qualquer membro: devolve à edição um documento que já fundamentou uma compra,
possivelmente já assinado e arquivado. Fica no log de qualquer forma.

Coordenação em vez de permissão pelo mesmo motivo do resto: é um cargo que o
painel de setores já mantém, não uma caixinha que alguém precisa lembrar de
marcar. Mesmo critério do primeiro nível da aprovação de ordem de compra
(`User::isAccountingCoordinator()`).

### O estado do mapa

Cruza com o setor em toda ação de escrita. Um **mapa fechado ou cancelado é
somente leitura para todo mundo**, inclusive para o coordenador — é ele que
sustenta a decisão de compra, e um preço corrigido depois do fechamento, sem
trilha, transformaria o documento em rascunho.

| | fora do setor | membro | coordenador |
|---|---|---|---|
| Ver a aba e os mapas | — | sim | sim |
| Gerar mapa, itens, colunas | — | sim | sim |
| Digitar preço | — | sim¹ | sim¹ |
| Escolher vencedor, fechar | — | sim¹ | sim¹ |
| Exportar | — | sim | sim |
| Reabrir mapa fechado | — | — | sim |

¹ só enquanto o mapa está em rascunho ou em cotação.

A composição está em
[`CotacaoMapaPolicy`](../../app/Policies/CotacaoMapaPolicy.php) e coberta por
[`CotacaoMapaPolicyTest`](../../tests/Unit/Cotacao/CotacaoMapaPolicyTest.php) —
inclusive o caso que motivou a regra: colaborador do setor, sem permissão
nenhuma, trabalhando no mapa inteiro.

### No menu

O módulo é submenu de **Compras**, ao lado de Ordens de Compra e Centros de
Custo — é a mesma área do ERP.

O item pai tem uma sutileza: as duas metades têm donos diferentes (Ordens de
Compra pede a permissão `authorize purchase orders`; Cotação pede o setor). Os
partials do menu só sabem filtrar pela permissão do item **pai**, então a
filtragem por filho é feita em `layouts/app.blade.php`, e o pai "Compras" só
aparece se sobrar algum filho — mesmo arranjo do menu Freelancers.

## Fluxo

1. **Buscar a SC** — por número, ou por período e texto (a busca procura também
   na descrição dos itens: quem pede a cotação lembra "as tintas do parquinho",
   não o número).
2. **Prévia** — cabeçalho, itens, última compra de cada um e a lista de
   fornecedores que já venderam aqueles itens. Se já existir mapa não cancelado
   para a mesma SC, a tela oferece abrir o existente em vez de duplicar o
   trabalho.
3. **Gerar** — o comprador marca quais fornecedores viram coluna. **Nenhum é
   marcado automaticamente**, senão o mapa nasce com vinte colunas.
4. **Cotar** — a grade salva **por célula**, a cada pausa de digitação. Não há
   botão "salvar tudo" para os preços: cotação é feita ao telefone ao longo de
   dias, e um formulário que só grava no fim perde tudo quando o navegador fecha.
5. **Decidir** — vencedor por item (a compra pode ser dividida), **sem recarregar
   a página**. A resposta traz a matriz recalculada pelo servidor, igual à do
   salvamento de preço: as contas seguem num lugar só, e a tela não perde a
   rolagem nem o foco a cada item decidido — num mapa de trinta itens era isso
   que tornava a decisão penosa.

   O que o reload dava de graça e agora é explícito: **o rádio já foi marcado
   pelo navegador antes da requisição**, então uma falha deixaria a tela
   afirmando uma decisão que não foi gravada. `reporVencedor()` devolve a linha
   ao estado do banco quando o gravamento falha — inclusive desmarcando tudo,
   quando o item ainda não tinha decisão.

   [`DecisaoSemReloadTest`](../../tests/Feature/Cotacao/DecisaoSemReloadTest.php)
   existe por causa de um modo de falha silencioso: enxugar a resposta para só
   `{ok, vencedor_id}` faria a tela parar de atualizar **sem erro nenhum** — o
   rodapé seguiria mostrando o total da decisão anterior.

   **Desfazer**: clicar de novo na loja já escolhida limpa a decisão do item
   (`fornecedor_id: null`, que o endpoint sempre aceitou). Um rádio não desmarca
   sozinho, e sem isso um item decidido por engano ficava decidido para sempre —
   a única saída era escolher outra loja, que é uma decisão diferente de "ainda
   não decidi". A diferença aparece no rodapé: item sem vencedor não entra no
   total decidido nem cobra o frete daquela loja. O rótulo da célula escolhida
   vira `escolhido ✕`, porque um gesto sem affordance é um gesto que ninguém
   descobre.

   O `@click` só age quando a célula **já** era a vencedora; nos demais casos
   quem grava é o `@change`, que é também o que cobre a seta do teclado. Como
   clicar num rádio já marcado não muda nada, o `change` não dispara ali e os
   dois não se atropelam. **A ressalva**: desfazer é só por clique — nenhum
   grupo de rádios se desmarca pelo teclado.
6. **Exportar / fechar.**

### O teclado anda pela grade

Cotação é digitação em série, e as duas leituras da grade existem na prática: ou
se está ao telefone com uma loja descendo a lista de itens, ou se tem a folha de
um item e se percorrem as lojas. Por isso as duas teclas fazem coisas diferentes:

| Tecla | Vai para |
|-------|----------|
| **Enter** | mesma loja, **item de baixo** (desce a coluna) |
| **Tab** | mesmo item, **próxima loja** (anda a linha) |
| **Tab** na última loja | **primeira loja do item seguinte** — a volta natural da leitura |
| **Shift+Tab** | o mesmo caminho ao contrário |

O `Enter` no último item **fica parado**: voltar ao topo faria o comprador
redigitar preço já digitado sem perceber.

As duas teclas cancelam o comportamento padrão do navegador, e é isso que tira o
seletor de situação (`R$ / NT / —`) do caminho — ele continua acessível pelo
mouse, mas não interrompe mais a digitação a cada célula. **Nenhuma tecla salva
por si**: mudar o foco dispara o `change` do campo, que é quem grava. Uma tecla
que gravasse por conta própria teria de repetir a regra de situação da célula.

### Salvar as condições de todas as colunas de uma vez

Frete, prazo e pagamento têm um **salvar geral** — um formulário só para as N
colunas (`PATCH cotacao/mapas/{mapa}/fornecedores`). O botão por linha obrigava o
comprador a lembrar de clicar em todos, e esquecer um deixava o mapa com uma
condição velha sem nada na tela avisando — **frete e desconto entram no TOTAL**,
ou seja, a decisão de compra saía errada em silêncio.

Três decisões que vão junto:

- **Um id de outra cotação derruba o lote inteiro** (guarda de escopo em
  [`AtualizarCondicoesCotacaoRequest::authorize()`](../../app/Http/Requests/AtualizarCondicoesCotacaoRequest.php),
  403 e não 422). Salvar 4 de 5 colunas e responder "salvo" seria pior que
  recusar: o comprador não teria como saber qual ficou de fora.
- **Um evento na trilha por ato**, não um por coluna — foi um clique só, e é
  assim que ele vai procurar depois. Clicar sem mudar nada **não registra nada**:
  com um botão único, ele vai ser clicado por hábito.
- **Remover coluna fica fora daquele formulário** (por `form=`, que é HTML
  válido). Remover é irreversível e não pode viajar de carona num "salvar tudo".

### Acrescentar fornecedores dentro da Lara

O comprador pode acrescentar fornecedores à cotação **a qualquer momento**,
inclusive quem **não existe no cadastro do Questor** (nesse caso
`questor_cd_entidade` fica nulo e a coluna vive só pelo nome). A sugestão
automática é ponto de partida, não limite — a boa proposta frequentemente vem de
quem nunca vendeu para a empresa.

`frete`, `prazo_entrega` e `condicao_pagamento` são **texto livre, não FK**: o
mapa em uso hoje tem "CONFIRMAR", "3DU" e "Á VISTA", que não existem em
`TBL_PRAZO_ENTREGA` nem em `TBL_FINANCEIRO_FORMAS_PAGAMENTO`. As tabelas do
Questor entram como autocomplete (`datalist`), não como restrição.

## As contas

Todas em [`MapaCalculoService`](../../app/Services/Cotacao/MapaCalculoService.php),
que é **puro** — nenhum I/O, nem banco, nem cache, nem relógio. É de propósito:
estas contas decidem para onde vai dinheiro, e uma conta que só dá para
exercitar com banco montado é uma conta que ninguém testa.

**A regra que atravessa tudo: célula vazia nunca vira zero.** Nem em subtotal,
nem em menor preço, nem em média, nem em economia. A planilha em Excel erra
exatamente aí — `SUMPRODUCT` trata vazio como zero, e quem não cotou nada aparece
com o menor total.

Daí a distinção entre as três situações de célula:

| Situação | No papel | Nas somas | Na cobertura |
|----------|----------|-----------|--------------|
| `cotado` | o valor | entra | conta |
| `nao_trabalha` | `NT` | **fora** | não penaliza — ele não vende o item |
| `sem_resposta` | *(vazio)* | **fora** | conta contra |

### O que o módulo mostra e a planilha não

- **Menor preço** de cada linha destacado, com o segundo menor mais discreto.
  Empate marca as duas colunas (escolher uma por sorteio esconderia do comprador
  que há dois preços iguais). O "segundo menor" é o segundo **valor distinto**,
  não a segunda célula.
- **No empate, o verde é de quem foi escolhido.** Duas células verdes estão certas
  enquanto ninguém decidiu — é a informação de que há empate. Depois de escolher,
  viram uma pergunta em aberto na tela: quem olha o mapa depois não sabe de qual
  das duas lojas se comprou. Então o escolhido fica com o verde forte e anel duplo,
  e o empatado preterido **cai para o verde fraco, não para o neutro** — o preço
  dele é o menor, e some só o anel, que é o que se lê como "esta é a escolha".
  Há `title` nas duas células explicando, porque a pergunta natural de quem vê é
  "por que este não está verde, se o preço é o mesmo?".

  Quando o escolhido **não** está entre os menores — o comprador preferiu pagar
  mais por prazo, frete ou relação — o verde fica no preço mais baixo. O verde é
  do preço, não da decisão. Regra em `classeCelula()`/`empateResolvido()` em
  [`show.blade.php`](../../resources/views/cotacao/mapas/show.blade.php), com os
  cinco cenários (empate aberto, resolvido, empate triplo, sem empate, escolhido
  mais caro) exercitados à mão.

  **No XLSX isto não vale**: lá o verde é formatação condicional sobre `MIN()`,
  que não conhece o vencedor, e os dois empatados saem verdes. É de propósito —
  o destaque acompanha a edição de preço no Excel, e trocá-lo por cor fixa por
  célula quebraria essa propriedade. Quem decidiu o quê está na aba `Decisão`.
- **Variação % vs. última compra** por célula — verde abaixo, vermelho acima.
- **Economia projetada**: `(última compra × qtd) − (melhor cotação × qtd)`, só
  nos itens que têm os dois lados. **Negativa é resultado legítimo** e aparece.
- **Cobertura**: "cotou 4 de 6 itens".
- **Dois totais por fornecedor**: `total` (só o que ele cotou — o número honesto)
  e `total_cheio`, que **só existe quando ele cotou tudo**. É o único caso em que
  comparar total contra total faz sentido. Na planilha de hoje essa distinção não
  existe, e quem cotou 2 de 6 itens parece o mais barato.
- **O que se compra de fato em cada loja**, que é uma pergunta diferente da
  anterior. A proposta e a decisão são duas coisas:

  | Campo | O que responde |
  |-------|----------------|
  | `subtotal` | tudo o que a loja cotou, à quantidade pedida, sem frete |
  | `total` | o subtotal com frete e desconto |
  | `total_vencidos` | **só os itens em que ela ganhou** — o subtotal dos escolhidos |
  | `total_vencidos_com_frete` | o pedido a esta loja: escolhidos + frete − desconto |

  A distinção importa porque quase nunca se compra tudo de um só: a loja pode ter
  o maior subtotal do mapa e a compra nela ser pequena. `total_vencidos` é **nulo,
  não zero**, quando ela não ganhou nada — zero se leria como um pedido de R$ 0,00.
- **O custo escondido da compra dividida.** `total_decidido_com_frete` soma
  frete e desconto **uma vez por loja em que se comprou algo**, e
  `lojas_decididas` diz em quantas. Dividir entre três lojas paga três fretes, e
  é esse número que costuma virar a compra dividida do avesso — a planilha em
  Excel não o mostra em lugar nenhum.
- **Duas estratégias lado a lado**: `melhor_combinacao` (compra dividida, item a
  item) contra `melhor_fornecedor_unico` (compra concentrada, só entre quem
  cobriu o mapa inteiro). A diferença entre elas é o que o comprador está de fato
  decidindo — por isso também existe `melhor_combinacao_com_frete`, já que
  dividir costuma perder parte da vantagem no frete.
- **Trilha completa** (`cotacao_mapa_logs`): quem criou, importou, mudou qual
  preço, escolheu qual vencedor e exportou. É boa parte da razão de o módulo
  existir — a planilha não responde "quem mudou este preço, e quando?".

## Exportação XLSX — dois layouts

O comprador **escolhe na hora de exportar**, num menu ao lado do botão. São dois
porque servem a momentos diferentes do mesmo trabalho:

| Layout | Para que serve |
|--------|----------------|
| `classico` *(padrão)* | O papel da reunião. Reproduz a planilha que a compra já imprime, assina e arquiva. |
| `completo` | O arquivo de análise, para decidir na tela. Quatro abas. |

O clássico segue como padrão de propósito: quem pede "exportar" sem pensar quer
o de sempre, e o dia em que o completo virar padrão é decisão de quem usa — não
consequência de alguém ter escrito o novo. Um `?layout=` ausente ou escrito
errado cai no clássico em vez de dar erro: link velho no meio de uma cotação
tem de entregar planilha, não página de erro.

A trilha registra qual layout saiu (`payload.layout`). Os dois arquivos
circulam, e depois alguém pergunta de qual deles veio o número da reunião.

Nomes de arquivo: `COTACAO_<slug>_<SC>_<dd_mm_aaaa>.xlsx` e o mesmo com sufixo
`_COMPLETO`. O sufixo não é enfeite — os dois convivem na pasta de downloads, e
sem ele o segundo sobrescreveria o primeiro em silêncio.

### O que valem para os dois

1. **Todo total é FÓRMULA, não número.** Quem corrigir um preço no Excel vê o
   total mudar. Uma exportação "com valores" quebraria isso em silêncio, e o
   teste reabre o arquivo do disco conferindo o **tipo** da célula.
2. **Os destaques são formatação condicional**, não cor fixa: editado o preço, o
   destaque acompanha.
3. **Célula vazia não vira zero.** `nao_trabalha` sai como o **texto** `NT`
   (string explícita, para o Excel não interpretá-lo) e `sem_resposta` sai em
   branco; `SUMPRODUCT`, `MIN` e `COUNT` ignoram os dois.
4. **O TOTAL do XLSX é conferido contra o `MapaCalculoService`.** O Excel avalia
   a fórmula do arquivo gerado e o número é comparado com o da tela, nos dois
   layouts. Não é zelo: o clássico somava `SUBTOTAL + FRETE` e **ignorava o
   desconto do fornecedor** — não tinha linha nem fórmula para ele — enquanto a
   tela e o completo subtraíam. A planilha impressa e a tela mostravam totais
   diferentes para o mesmo mapa, e nada na planilha denunciava a diferença.

   A mesma checagem cobre a aba `Decisão`, que fechava só com a mercadoria sob o
   rótulo "TOTAL DECIDIDO" — o mesmo rótulo que a tela usa para o valor **com**
   frete e desconto. Ela agora fecha em três linhas: `MERCADORIA`,
   `FRETE − DESCONTO (uma vez por loja)` e `TOTAL DO PEDIDO`.

   No clássico, a linha `DESCONTO` **só aparece quando algum fornecedor deu
   desconto**: o arquivo é impresso e assinado, e uma linha de zeros em toda
   cotação seria ruído permanente por um caso ocasional. Quando ela existe, o
   TOTAL a abate — e mostrar o valor não é enfeite: um total que não fecha com
   as linhas acima dele é pior que um total errado, porque ninguém consegue
   conferir.

   Na mesma correção entrou o `IF(COUNT(...)=0,0,...)` do TOTAL do clássico: sem
   ele, um fornecedor que não respondeu **nada** aparecia no rodapé devendo o
   frete, como se tivesse cotado.
5. **O texto do cabeçalho cabe por mesclagem, nunca por largura de coluna.**
   Esta é a armadilha de layout dos dois arquivos, e vale escrever por extenso.

   As colunas fixas são estreitas porque abaixo delas mora a grade (`A` = 8 ou 9
   por causa de "ITEM", `B` = 7 ou 10 por causa de "UND"). O cabeçalho, porém,
   escreve **nas mesmas colunas** — e no Excel um texto só transborda até achar
   célula ocupada, que é justamente o rótulo do bloco vizinho. Resultado: o nome
   do solicitante caía numa caixa de 8 e aparecia pela metade.

   A correção **mescla** (`A:B` para o rótulo, `C` inteira ou `F:G` para o valor)
   e usa `shrinkToFit`. Mesclar não mexe em largura de coluna nenhuma, então a
   grade abaixo continua exatamente como era. **A tentação a evitar é alargar a
   coluna `A`**: consertaria o cabeçalho e estragaria a grade inteira, que é
   impressa e assinada nessa proporção — há teste guardando as larguras.

   `shrinkToFit` e **não** `wrapText` nas células mescladas: célula mesclada não
   ganha altura automática no Excel, e quebrar linha ali esconderia a segunda —
   trocaria um corte por outro. Nas células **não** mescladas (as condições por
   fornecedor) é o contrário: `wrapText` com altura automática, que mostra o
   texto inteiro no tamanho normal em vez de encolhido.
6. **Rótulo é sigla** (`SOLIC.`, `COMPR.`, `DEPTO`, `SIT.`, `FRETE`, `PRAZO`,
   `PAGTO`). O nome por extenso ocupava a caixa de que o **valor** precisa, e o
   valor é o que se lê.
7. **A altura do cabeçalho da grade sai do nome mais comprido**, não de um número
   fixo. As células do cabeçalho quebram linha, mas altura fixa impede o Excel de
   crescer sozinho — e "COMERCIAL DE TINTAS E FERRAGENS SÃO JOSÉ LTDA" numa
   coluna de 14 precisa de três linhas.

### `classico` — a planilha impressa

Layout idêntico ao modelo: `A1` COTAÇÃO DE COMPRAS · `C3` DATA · `C4` SC ·
linhas 5/6/7 com frete, prazo e pagamento · linha 8 com o cabeçalho · itens a
partir da 9 · rodapé FRETE/SUBTOTAL/TOTAL/TOTAL GERAL DO PEDIDO.

- `SUBTOTAL` é `=SUMPRODUCT($D$9:$D$14,E9:E14)`; `TOTAL` é `=E16+E15`.
- O `TOTAL GERAL DO PEDIDO` usa uma **coluna auxiliar oculta** logo depois dos
  fornecedores, com `=IF(COUNT(...)=0,0,MIN(...)*$D$n)`. É o preço a pagar por
  manter o total recalculável sem sair do layout do papel: o Excel não soma "o
  mínimo de cada linha" numa fórmula só sem matricial.
- Aba 2 **"Histórico"** — item, última compra, melhor cotação, variação %.

**Este layout não ganha coluna nova.** É o que garante que ele continue sendo o
papel que a compra reconhece; tudo o que não cabe ali vai para o completo.

### `completo` — o arquivo de análise

Quatro abas, e a coluna auxiliar do clássico deixa de ser gambiarra escondida
para virar conteúdo visível:

**Mapa** — a grade. Colunas fixas `ITEM · UND · DESCRIÇÃO · QNT. · ÚLT. COMPRA`,
os fornecedores, e depois **MENOR** e **ECONOMIA** por item. Cabeçalho
congelado junto com as colunas fixas (rolar para a direita continua mostrando
qual item é), autofiltro, zebra, e três destaques condicionais: menor preço da
linha em verde, `NT` apagado (não é proposta cara, é não-proposta) e economia
negativa em vermelho. Rodapé com `FRETE · DESCONTO · SUBTOTAL · TOTAL · ITENS
COTADOS`, todos fórmula — a cobertura é `=COUNT(...)`, que é a distinção do
módulo inteira em uma função. O `TOTAL` é
`=IF(cobertura=0,0,subtotal+frete-desconto)`: quem não respondeu nada não pode
aparecer devendo frete. Impressão em paisagem, ajustada à largura, com a linha
de cabeçalho repetida e o número de página no pé.

**Resumo** — a página que responde "o que eu compro?". As duas estratégias lado
a lado (comprar dividido × comprar de um só, com a **diferença** entre elas),
a economia contra a última compra, e o quadro de cobertura por fornecedor com
uma coluna **COMPARÁVEL?** que diz, com todas as letras, quem cotou só parte.
Os números **apontam para a aba Mapa por fórmula** em vez de recalcular: se as
duas abas fizessem a própria conta, uma edição na grade faria as duas
discordarem — e a discordância apareceria tarde.

**Histórico** — item a item contra a última compra, com variação % colorida.
Aqui os valores vão calculados: a última compra veio do Questor e não está na
planilha para o Excel recalcular. O cabeçalho da aba diz isso.

**Decisão** — o que ficou escolhido, item a item, com fornecedor e total. Existe
porque a compra pode ser **dividida**: o mapa não termina num fornecedor,
termina numa lista de "este item, deste fornecedor". Sem a aba, essa lista só
existiria na cabeça de quem decidiu. Quando falta item por decidir, o arquivo
avisa que o total é parcial.

### Estrutura

```
app/Services/Cotacao/
  MapaExportService.php          -> fachada: escolhe o layout, grava e nomeia
  Export/
    LayoutExportacao.php         -> o contrato (nome, descrição, montar)
    LayoutClassico.php           -> a planilha impressa
    LayoutCompleto.php           -> o arquivo de análise
```

Gravar em disco e nomear o arquivo mora na fachada, não nos layouts: é igual nos
dois, e duplicar daria dois lugares para o caminho divergir. Acrescentar um
terceiro layout é uma classe nova e uma entrada em `layouts()` — a tela monta o
menu a partir dela e não precisa mudar.

### O teste que mais importa

[`LayoutCompletoTest::test_as_formulas_do_excel_dao_o_mesmo_numero_que_o_servico_de_calculo`](../../tests/Feature/Cotacao/LayoutCompletoTest.php)
grava o arquivo, reabre do disco, **manda o Excel calcular** e compara com o
`MapaCalculoService`, coluna a coluna.

São duas implementações da mesma regra — uma em PHP, que manda na tela, e outra
em fórmula, que manda depois que o arquivo sai daqui. É o par que costuma
divergir em silêncio, e a divergência só apareceria numa reunião, com o
comprador defendendo um número que a tela não mostra.

## O retrato da última compra

Os campos `ult_compra_*` de `cotacao_mapa_itens` são gravados **na importação** e
ficam congelados. O mapa precisa ser reproduzível meses depois, mesmo que notas
novas entrem no Questor no meio da cotação.

Quem quiser o dado de hoje usa o botão **"Atualizar histórico"**, que regrava o
retrato de propósito e deixa registro no log — é uma ação que muda a base de
comparação de um mapa que talvez já tenha preços digitados.

## Estrutura

```
app/
  Console/Commands/DescobrirConfigCotacao.php   -> cotacao:descobrir-config (0.1b e 0.2)
  Exceptions/CotacaoException.php
  Http/Controllers/Cotacao/{Mapa,Preco,Fornecedor}Controller.php
  Http/Requests/{ImportarMapa,SalvarPreco,StoreCotacaoFornecedor,
                 StoreCotacaoItem,DefinirVencedor}*Request.php
  Models/Cotacao{Mapa,MapaItem,MapaFornecedor,Preco,MapaLog}.php
  Policies/CotacaoMapaPolicy.php
  Services/Cotacao/
    MapaImportService.php    -> SC + retrato da última compra  (queries 1, 2, 3, 5.c)
    MapaCalculoService.php   -> puro: menor preço, totais, cobertura, economia
    MapaExportService.php    -> XLSX
  Services/Questor/
    QuestorReadRepository.php        -> base somente-leitura
    QuestorSolicitacaoRepository.php -> queries 1, 1.b, 2
    QuestorCompraRepository.php      -> queries 3, 4, 5.b, 5.c, 6, 7.b, 8
    QuestorCadastroRepository.php    -> queries 9.a-9.e + descoberta 0.1b e 0.2
    DTO/{Solicitacao,SolicitacaoItem,UltimaCompra,FornecedorHistorico}DTO.php
```

As consultas ao Questor estão numeradas em
[`docs/sql/mapa_cotacao_questor_queries.sql`](../sql/mapa_cotacao_questor_queries.sql)
— é a numeração que os comentários dos repositórios citam ("query 5.c", "query
0.1b"). O arquivo é versionado por uma exceção no `.gitignore`, que ignora
`*.sql` para não deixar dump entrar no repositório.

Tabelas: `cotacao_mapas`, `cotacao_mapa_fornecedores`, `cotacao_mapa_itens`,
`cotacao_precos`, `cotacao_mapa_logs`.

**Nenhuma delas tem foreign key para `users`** — o model `User` fixa a conexão
`mysql` enquanto estas seguem a conexão padrão, pelo mesmo motivo de
`questor_order_decisions`. O nome do autor vai junto como retrato.

**Um mapa aberto por solicitação** é validado na aplicação
(`MapaImportService::mapaAbertoDe`), e não por índice único: a regra real é "um
mapa **não cancelado** por SC", que é um índice parcial — coisa que o MySQL não
tem. Cancelado não ocupa a solicitação: o comprador precisa poder recomeçar
depois de descartar uma cotação.

## Testes

```bash
php -d memory_limit=1G vendor/bin/phpunit tests/Unit/Cotacao tests/Feature/Cotacao
```

- `MapaCalculoServiceTest` — **PHPUnit puro, sem aplicação**: empate, coluna
  vazia, `NT` misturado com sem resposta, frete zerado, quantidade fracionada,
  economia negativa, cobertura parcial.
- `MapaImportServiceTest` — Questor por mock: SC inexistente, SC sem itens, item
  com `CD_MATERIAL` nulo, SC que já tem mapa, mapa cancelado que libera nova
  importação, atualizar histórico, teto de colunas, fornecedor fora do ERP.
- `SalvarPrecoEscopoTest` — a trava de IDOR: item ou fornecedor de outro mapa é
  recusado na **autorização** (403), não na validação.
- `MapaExportServiceTest` — o layout clássico (posição de cada linha, `NT` como
  texto) e a fachada: quais layouts existem, o padrão, o fallback de um layout
  desconhecido e o sufixo no nome do arquivo.
- `LayoutCompletoTest` — as quatro abas, as colunas de análise, o rodapé em
  fórmula e o cruzamento das fórmulas do Excel contra o `MapaCalculoService`.
- `CotacaoMapaPolicyTest` — o acesso: colaborador do setor faz tudo sem
  permissão nenhuma, a role `admin` sozinha não entra, reabrir é do coordenador,
  mapa fechado é somente leitura — e a convenção de nome que impede uma
  permissão do Spatie de passar por cima do Gate de setor.
- `QuestorQueriesSqlTest` — a forma das 18 consultas ao ERP, interceptando o
  `select()`: nenhum `%s` solto, `?` batendo com bindings, todo `FROM`/`JOIN`
  apontando para tabela qualificada e `TBL_STATUS` só por `LEFT JOIN`.
- `QuestorSomenteLeituraTest` — a trava de escrita.

Os testes usam
[`Tests\Concerns\CreatesCotacaoSchema`](../../tests/Concerns/CreatesCotacaoSchema.php),
que aplica **as migrations de verdade** (não uma cópia do schema) sobre o SQLite
da suíte. Não há `RefreshDatabase` pelo motivo já registrado no repositório: a
cadeia completa de migrations não roda hoje, e várias dependem de `users`.

## Fora de escopo

Não implementado, e não deve ser sem nova conversa:

- Gravar cotação ou ordem de compra de volta no Questor.
- Envio automático de e-mail de cotação a fornecedor.
- Portal do fornecedor para autopreenchimento.
- Aprovação / alçada de compra (isso é o outro módulo:
  [Autorização de Ordem de Compra](questor-autorizacao-compra.md)).
