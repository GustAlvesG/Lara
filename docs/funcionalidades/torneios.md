# Torneios

> Módulo refeito do zero entre 22 e 23/10/2026 (fases F0 a F8, tabela no fim). Este
> documento registra as decisões e o funcionamento; o passo a passo para o setor Esporte
> está no [guia](torneios-guia.md).

## O que é

Registro de **torneios esportivos** no Lara, que serve de backend para o **site de locação**
(o mesmo Next.js das reservas). O Lara cadastra torneios e categorias, recebe as inscrições
individuais dos sócios com pagamento pela **eRede**, forma os times, gera o chaveamento e
guarda os resultados.

## Histórico

Havia um primeiro módulo (migration `2026_02_23_133450_tourments`, models em
`app/Models/Tournament/`, `TournamentController`, `TournamentService`, rotas `tournaments.*`
e `categories.*`, permissão `torneios`) que nunca chegou ao menu. Foi **apagado em 22/10/2026**:

- código, telas, rotas, testes e a permissão `torneios` saíram do catálogo;
- a migration `2026_10_22_100000_drop_legacy_tournament_tables` apaga as sete tabelas antigas
  (`tournaments`, `categories`, `tournaments_categories`, `teams`, `team_member`,
  `tournament_subscription`, `tournament_subscription_payment`). Antes, conferimos que
  estavam **vazias em homologação e em produção**, e que a permissão `torneios` não estava
  ligada a setor nem a pessoa;
- a migration antiga continua no repositório (já rodou nos bancos); a nova não tem `down()`,
  porque o módulo novo cria a sua própria `tournaments`;
- a linha da permissão `torneios` na tabela `permissions` sai com `php artisan
  acesso:limpar-legado` (primeiro sem `--confirmar`, para ver; depois com), rodado por alguém
  depois do deploy — o seed de permissões não apaga nada.

## Para quem

- **Sócio** (site de locação, login do sócio): se inscreve, paga, forma ou aceita convite de
  time, acompanha chaveamento e resultados.
- **Organizador** (painel do Lara, setor **Esporte**): cadastra torneio e categorias, cuida
  das inscrições, monta ou sorteia times, gera o chaveamento e lança resultados.
- **Equipe do clube no site**: lança resultados com o **código de lançamento** do torneio,
  gerado no Lara (decidido em 23/10/2026; não usa login).

## Regras decididas

### Quem participa
- **Só sócios**, por enquanto (`tournaments.audience = members`). Titular **e dependentes**.
- A pessoa pode se inscrever em **várias categorias** do mesmo torneio, mas está em **um time
  só por categoria**.

### Categorias
- Há um **catálogo de categorias por grupo de espaços** (cada `place_group` tem o seu "book"),
  reaproveitado entre torneios.
- A categoria define: **pessoas por equipe** (1 = individual), **sexo** aceito e **idade**
  mínima/máxima.
- **A idade é calculada na data de início do torneio.**
- **Sexo vem do MultiClubes**, coluna `dbo.Members.Sex`, por regra: **`0` = masculino,
  `1` = feminino** (o MultiClubes não tem tabela de domínio; a regra foi fixada em 22/10/2026
  depois de conferir a distribuição dos nomes). Cadastros com o sexo errado existem; o
  organizador inscreve assim mesmo pelo balcão (o Lara avisa e pede confirmação).
- Sexo da categoria: **Masculino**, **Feminino** ou **Livre**. Uma regra de composição para
  categoria mista (ex.: um de cada sexo na dupla) **ainda não foi definida**.
- Ao ligar a categoria ao torneio, define-se o **preço** (por pessoa), o **limite de vagas**
  (padrão: **sem limite**), a **forma de montar os times**, o **formato** de disputa e o
  número de **sets** para vencer.

### Inscrição e pagamento
- A inscrição é **individual**: a pessoa escolhe as categorias aceitas no torneio e paga a sua
  parte pela **eRede**. Um pagamento pode cobrir várias categorias.
- Pelo site, inscrever-se e pagar só **dentro do período de inscrição** do torneio. Pelo
  **balcão do Lara**, a qualquer momento (ver [No Lara, só aviso](#no-lara-só-aviso)).
- **Desistência e estorno**: caso a caso, decididos pelo organizador. Pelo site, o sócio só
  desiste **antes de pagar**; inscrição paga é cancelada pelo organizador, com ou sem estorno.
- **Vagas** (`max_registrations`) contam as inscrições **pagas**: quem paga primeiro garante a
  vaga. Inscrição pendente não segura vaga.
- Categoria de **preço zero** já nasce paga.

### Times
- O time se forma **depois** da inscrição, de um destes jeitos, escolhido **por categoria**:
  - **organizador** monta à mão;
  - **sorteio** entre os inscritos **pagos e sem time**, quando o organizador manda. Só fecha
    times inteiros; quem sobra fica sem time para ele resolver. Os times que já existem não
    mudam;
  - **capitão** cria o time (precisa estar inscrito na categoria) e convida por **matrícula ou
    CPF**. Pela matrícula (título da família), ele escolhe a pessoa entre os membros do título.
    O convidado recebe **e-mail**; ao aceitar, se ainda não estiver inscrito, a inscrição é
    criada ali mesmo (com a conferência de sexo e idade) e ele **entra no time já, aguardando
    pagamento**. O convite pendente ocupa uma vaga do time.
- O organizador pode montar, ajustar ou desfazer o time em **qualquer** categoria, a qualquer
  momento — inclusive nas de sorteio e capitão.
- Categoria individual: cada inscrito pago vira um "time" de uma pessoa (nome = o da pessoa).
- **Time completo** = tem o número de pessoas da categoria e **todas pagaram**; senão, *em
  formação*. Time sem ninguém é desfeito.
- **Capitão** é opcional; o organizador pode trocar ou retirar. Quem sai do time deixa de ser
  capitão.
- Desistência ou cancelamento da inscrição tira a pessoa do time.
- **Times incompletos** são resolvidos caso a caso pelo organizador.
- Depois do fim do período de inscrição, **só o organizador** altera a escalação.
- Categoria **mista** (composição por sexo) **não existe por enquanto** (decidido em
  23/10/2026): *Livre* aceita qualquer combinação.

### Disputa e resultados
- Formatos: **eliminatória simples** e **grupos + mata-mata**.
- Chaveamento **por sorteio ou manual** (regras completas em [Chaveamento](#chaveamento-tournamentbracketservice)).
- No chaveamento entram **todos os times não desfeitos**, completos ou em formação; a tela
  avisa quais estão incompletos (decidido em 23/10/2026).
- O chaveamento pode ser gerado **a qualquer momento** e gerado de novo ou desfeito; com
  jogo já com resultado, o Lara avisa que os resultados serão apagados.
- Grupos + mata-mata: os classificados se cruzam no **cruzado padrão** (1º A × 2º B,
  1º B × 2º A…).
- O site só vê o chaveamento depois que o organizador **divulga**; gerar de novo tira do site.
- Placar por **sets e games**. **Não há empate**: o placar empatado é recusado. Empate na
  classificação de grupo é resolvido fora do sistema; o organizador define a ordem na tela.
- Resultado pode ser lançado **no Lara** ou **no site** pela **equipe do clube**, com o
  código de lançamento do torneio. **O Lara sempre prevalece**: lançado no Lara, o jogo
  trava para o site.
- Jogos não se ligam à agenda de Reservas nem ao Home Assistant.

### No Lara, só aviso

Decidido em 23/10/2026: **na interface web do Lara tudo é permitido, mediante aviso**. As
regras de prazo, situação, vagas, sexo/idade e ordem dos resultados continuam valendo para
o **site** (que recebe a recusa com o `reason`), mas no Lara viram um quadro amarelo
*"Atenção: confira antes de seguir"* com a lista do que foge da regra e o botão **Confirmar
mesmo assim**, que reenvia o mesmo formulário com `force=1`. Nada é gravado antes de
confirmar.

| Ação no Lara | Aviso | Confirmado |
|---|---|---|
| Inscrever pelo balcão | fora do período, torneio em rascunho/andamento/encerrado/cancelado, sem vaga, sexo ou idade fora da categoria | entra; grava quem liberou (`eligibility_override_by`) e o motivo (a justificativa digitada ou os avisos). Fora do período o site não aceita o pagamento: dê baixa manual |
| Baixa manual | passa das vagas | paga |
| Mudar a situação | fora do caminho normal (ex.: reabrir encerrado ou cancelado), publicar sem categoria, voltar para rascunho com inscrição | muda |
| Ligar categoria | categoria inativa no catálogo; inscrições do site já fechadas (andamento, encerrado, cancelado, ou publicado com prazo vencido) | liga; só entra quem for inscrito pelo balcão |
| Editar categoria | mudar formato, grupos ou 3º lugar com chaveamento gerado; vagas abaixo das pagas | desfaz o chaveamento (e resultados) e salva |
| Tirar categoria | inscrições sem pagamento, chaveamento | apaga inscrições, times e chaveamento |
| Gerar de novo / desfazer chaveamento | já há N jogos com resultado | apaga os jogos e o histórico deles |
| Lançar, corrigir ou limpar resultado | torneio em rascunho ou cancelado; grupo já fechado; jogo seguinte (ou 3º lugar) já jogado | reabre o grupo e apaga em cascata os resultados que dependiam do jogo (cada um com `cascade` no histórico) |
| Fechar grupo | jogo do grupo sem resultado; mata-mata que recebe do grupo já jogado | fecha; limpa o mata-mata afetado |
| Reabrir grupo | mata-mata que recebe do grupo já jogado | limpa o mata-mata afetado e reabre |

Continuam **recusas** (não há o que confirmar): inscrição repetida na mesma categoria, quem
não é sócio de título ativo ou não tem cadastro no site, categoria de outro grupo ou já
ligada, placar inválido, jogo sem os dois times, ordem de grupo inválida, chaveamento com
menos de 2 times ou grupos impossíveis, time acima do tamanho, e **tirar categoria com
inscrição que passou por pagamento** (guarda o histórico financeiro: cancele e estorne as
inscrições e deixe a categoria, sem vagas se quiser).

Peças: `App\Exceptions\ConfirmationRequired` (`unless($force, $avisos)`; a própria exceção
devolve a tela com `withInput` e a sessão `confirmation`), o quadro em
`resources/views/partials/alerts.blade.php` (genérico: serve a qualquer tela do Lara que
inclua os alertas) e o parâmetro `$force` dos serviços.

Para o site, a API não mudou. Só um caso novo: inscrição do balcão fora do período aparece
em *minhas inscrições* pendente, e o pagamento pelo site é recusado (`registration_closed`);
o site mostra "pagamento na secretaria" (ver `torneios-site-prompt.md`).

### E-mails
Inscrição recebida, pagamento confirmado, convite de time, time formado, desistência ou
cancelamento.

## Modelagem

Tabelas criadas em `2026_10_22_110000_create_tournament_tables` (F2); models em
`app/Models/Tournament/` (ver [models.md](../models.md#torneios-appmodelstournament)). A
modalidade é a do grupo de espaços, por isso o torneio não tem campo próprio de modalidade.

| Tabela | Papel |
|---|---|
| `tournament_category_templates` | Catálogo do grupo: `place_group_id`, `name`, `team_size`, `gender` (`male`/`female`/`any`), `min_age`, `max_age`, `active`. |
| `tournaments` | `place_group_id`, `title`, `description`, `rules`, `image`, `registration_starts_at/ends_at`, `starts_on`, `ends_on`, `audience`, `status` (`draft`, `published`, `in_progress`, `finished`, `cancelled`), `results_code_*` (código de lançamento pelo site: sha256, validade, quem gerou). |
| `tournament_categories` | Vínculo torneio × catálogo, com cópia de nome/tamanho/sexo/idade, `price`, `max_registrations` (nulo = sem limite), `team_formation` (`organizer`/`draw`/`captain`), `format`, `groups_count`, `qualifiers_per_group`, `third_place_match`, `sets_to_win`. Sem pontuação: sem empate, a classificação do grupo é por vitórias e o desempate é do organizador. |
| `tournament_registrations` | Inscrição individual: categoria, `member_id`, cópia de CPF/matrícula/nome/e-mail/nascimento/sexo, `amount`, `status` (`pending_payment` → `paid`; `withdrawn`, `cancelled`), liberação de elegibilidade, pagamento, `refunded_amount` (estorno desta inscrição). |
| `tournament_payments` | Pagamento eRede (mesmo desenho do `SchedulePayment`: idempotência pelo tid, baixa manual, estorno). |
| `tournament_teams`, `tournament_team_members` | Time da categoria (capitão opcional, origem, status, cabeça de chave) e suas inscrições. |
| `tournament_team_invites` | Convites do capitão (CPF, e-mail, status). |
| `tournament_stages`, `tournament_groups`, `tournament_group_teams` | Fases, grupos e posição final no grupo. |
| `tournament_matches`, `tournament_match_sets` | Jogos (lados, avanço do vencedor, origem dos classificados, trava do Lara) e sets (games, tie-break). |
| `tournament_match_result_logs` | Histórico de cada resultado lançado ou alterado, com origem (Lara/site) e autor. |

## Permissões

No catálogo `App\Authorization\Permissions` desde a F2, grupo **Torneios**, sem setor: depois
do deploy, dê ao setor **Esporte** pela tela de Setores.

| Permissão | O que libera |
|---|---|
| `torneios.gerenciar` | Catálogo de categorias, torneios e chaveamento |
| `torneios.inscricoes` | Inscrições e times (consultar, montar, remover, cancelar) |
| `torneios.resultados` | Lançar e corrigir resultados no Lara, fechar grupo, gerar e retirar o código de lançamento pelo site (abre também a tela do chaveamento) |
| `torneios.pagamentos.baixa-manual` | Baixa de inscrição paga fora da eRede |
| `torneios.pagamentos.estornar` | Estorno na eRede |
| `torneios.financeiro` | Torneios › Financeiro: relatório e CSV dos pagamentos (F7) |

## Telas

### Torneios › Torneios (`/torneios`, `torneios.gerenciar`)

Lista com busca pelo nome e filtro de situação. **Novo torneio**: grupo de espaços
(modalidade), nome, descrição, regulamento, período de inscrição (data e hora), primeiro e
último dia, imagem (arquivo ou link). O torneio nasce em **rascunho**: não aparece no site.

Na tela do torneio:

- **Situação**: botões com o caminho normal — *Publicar no site*, *Voltar para rascunho*,
  *Iniciar*, *Encerrar*, *Cancelar* — e o seletor **Outra situação** para qualquer outra (ex.:
  reabrir um torneio encerrado). Publicar sem categoria, voltar para rascunho com inscrição
  e sair do caminho normal pedem confirmação. Cancelar o torneio **não** estorna ninguém
  sozinho: cancele cada inscrição, com ou sem estorno.
- **Categorias**: ligue uma categoria ativa do catálogo do grupo, com preço por pessoa, vagas
  (vazio = sem limite), montagem dos times (organizador, sorteio, capitão), formato
  (eliminatória ou grupos + mata-mata, com número de grupos e classificados), sets para
  vencer e disputa de 3º lugar. A tabela mostra pagas e pendentes por categoria. Preço novo
  só vale para as próximas inscrições. Ligar categoria com o torneio já em andamento (ou
  com o prazo do site vencido) pede confirmação. Tirar categoria com inscrição sem
  pagamento pede confirmação e apaga as inscrições; com inscrição que passou por pagamento,
  não sai. Com categoria ligada, o grupo do torneio não muda.

### Inscrições (`/torneios/{id}/inscricoes`, `torneios.inscricoes`)

Botão **Inscrições** na tela do torneio. Lista com busca (nome, CPF ou matrícula) e filtros
de categoria e situação.

- **Inscrever pelo balcão**: CPF, matrícula e categorias. A pessoa precisa ser sócia de
  título ativo no MultiClubes **e** ter cadastro no site de locação. **A qualquer momento**:
  fora do período, sem vaga, ou com sexo ou idade fora da categoria, o Lara avisa e pede
  confirmação. A **justificativa** é opcional; a inscrição liberada mostra na lista
  *Liberada: …* (a justificativa ou os avisos).
- **Baixa manual** (`torneios.pagamentos.baixa-manual`): marque as inscrições pendentes de
  uma mesma pessoa, informe forma, data e comprovante. O valor é o total delas. Não exige o
  período aberto (registra um pagamento que já aconteceu); passar das vagas pede
  confirmação.
- **Cancelar**: motivo obrigatório. *Estornar pela eRede* aparece para inscrição paga pela
  eRede e pede `torneios.pagamentos.estornar`; estorna o valor **desta** inscrição (um
  pagamento pode cobrir várias). Se a eRede recusar, a inscrição continua ativa. Baixa
  manual não tem estorno pelo Lara.

### Times (`/torneios/{id}/categorias/{categoria}/times`, `torneios.inscricoes`)

Botão **Times** em cada categoria da tela do torneio.

- **Sem time**: as inscrições ativas que não estão em time (pagas e pendentes). Marque e
  clique em *Montar time com os marcados* (nome opcional: vazio = primeiros nomes, "Ana /
  Bruno"). *Sortear entre os pagos* fecha times inteiros só com os pagos sem time.
- **Times**: cada um com quem joga (pendente marcado), capitão, convites pendentes do modo
  capitão e a situação (*em formação*, *completo*, *cancelado*). Em *Ajustar*: nome, capitão,
  cabeça de chave, incluir atleta sem time, tirar atleta, desfazer o time (as inscrições
  continuam, sem time).

### Chaveamento (`/torneios/{id}/categorias/{categoria}/chaveamento`, `torneios.gerenciar` ou `torneios.resultados`)

Botão **Chaveamento** em cada categoria da tela do torneio.

- No alto: quantos times, a situação (*sem chaveamento*, *gerado, não divulgado*, *divulgado
  no site*) e avisos — times em formação que entram assim mesmo, e times que mudaram depois
  da geração (fora da chave ou desfeitos na chave).
- **Gerar por sorteio** (ou *Sortear de novo*): cabeças de chave primeiro, na ordem marcada
  na tela de Times; os demais por sorteio.
- **Montar à mão**: na eliminatória, os dois lados de cada jogo da 1ª rodada ("passa direto"
  deixa o outro lado avançar sem jogar); em grupos, o grupo de cada time. Vem preenchido com
  o chaveamento atual ou, sem ele, com a ordem dos cabeças de chave.
- **Divulgar no site** / **Recolher do site**; **Desfazer**. Com resultado lançado, gerar de
  novo ou desfazer avisa que os resultados serão apagados.
- Fase de grupos: um cartão por grupo, com a classificação (jogos, vitórias, sets, games) e
  os jogos por rodada. Mata-mata: uma coluna por rodada (1ª rodada… Quartas, Semifinal,
  Final) e a disputa de 3º lugar.
- Em cada jogo, **Data e local** (informativos: não reservam quadra na agenda de Reservas).
- Com `torneios.resultados`: **Lançar resultado** / **Corrigir resultado** (games de cada set,
  tie-break opcional, ou W.O.), **Limpar resultado** e o **Histórico** de cada jogo (quem
  lançou, de onde e o placar). Selos: *Lara* (travado para o site) ou *site*.
- Grupo com todos os jogos encerrados: **Fechar o grupo** com a ordem final (vem na ordem da
  classificação; empate é resolvido por vocês). Os classificados entram no mata-mata. Depois
  dá para **mudar a ordem** ou **reabrir**, enquanto o mata-mata que recebe do grupo não
  tiver resultado.

### Lançamento de resultados pelo site (na tela do torneio, `torneios.resultados`)

**Gerar código** mostra o código uma vez (`ABCD-EFGH`). Passe para quem vai lançar no site;
vale até o fim do último dia do torneio. **Gerar outro** invalida o anterior; **Retirar**
corta o site na hora. A tela mostra quem gerou, quando e até quando vale.

### Torneios › Financeiro (`/torneios/financeiro`, `torneios.financeiro`)

Os torneios (fora rascunho), com filtro por ano: recebido, estornado, líquido e a receber.
No torneio (botão **Financeiro** na tela dele): os mesmos totais **por categoria** (eRede e
baixa manual separados, canceladas depois de pagas) e a lista de **pagamentos** (data,
pessoa, categorias, forma, TID ou quem deu a baixa, valor, estornado, situação), com busca e
**CSV** (`;`, UTF-8, abre no Excel).

### Torneios › Categorias (`/torneios/categorias`, `torneios.gerenciar`)

O catálogo de cada grupo de espaços **esportivo** (os sociais não aparecem). Escolha o grupo
no topo; cadastre nome, pessoas por equipe, sexo, idade mínima e máxima (vazio = sem limite)
e se está disponível para novos torneios. Busca na lista. O nome é único dentro do grupo; o
grupo não muda na edição. Remover tira do catálogo, mas torneio que já usa a categoria não
muda (ele guarda a cópia).

## Inscrição e pagamento (`TournamentRegistrationService`)

```
inscrever → pending_payment → (eRede pelo site | baixa manual) → paid
                ↘ withdrawn (sócio, antes de pagar)     ↘ cancelled (organizador, ± estorno)
```

- O sócio é o do JWT; os dados vêm do MultiClubes por `find(cpf, matrícula)` do cadastro do
  site. Sem sócio ativo: `member_not_found`.
- Várias categorias numa inscrição só: ou entram todas, ou nenhuma.
- A linha da categoria é travada (`lockForUpdate`) na inscrição e no pagamento, para a vaga e
  a regra de uma inscrição ativa por pessoa não escaparem por corrida.
- Pagamento eRede (`pay`): as inscrições são do sócio da sessão, estão pendentes, o período
  está aberto, há vaga e o valor é **exatamente** o total. O mesmo tid reenviado para as
  mesmas inscrições devolve o pagamento já gravado (200); para outras inscrições, 409.
  Como no `POST /api/schedule/payment`, o Lara confia no tid que o site envia (não consulta a
  eRede ao gravar).
- Estorno: `RedeItauService::refund($tid, $valor)` (passou a ser público para os torneios),
  gravado no `TournamentPayment` (`refunded_amount`, `partially_refunded`/`refunded`).
- E-mails (`TournamentNotifier`, template `emails.tournament.status`): `tournament.registered`
  (com o prazo de pagamento), `tournament.paid`, `tournament.cancelled` (com motivo e valor
  estornado), `tournament.invite` (convite do capitão, com o prazo para responder). Um por
  pessoa e torneio; vai para o e-mail do MultiClubes ou, sem ele, o do cadastro do site.
- Times (`TournamentTeamService`, `TournamentInviteService`): pagar chama `afterPaid`
  (individual vira time; o time do pagante pode completar); desistir ou cancelar chama
  `detach` (sai do time, deixa de ser capitão, time vazio é desfeito).

## Chaveamento (`TournamentBracketService`)

- **Quem entra**: os times da categoria que não foram desfeitos (`teamsFor`), cabeças de chave
  primeiro. São precisos ao menos 2.
- **Eliminatória simples**: a chave tem o tamanho da potência de 2 seguinte (5 times → chave
  de 8). As vagas que sobram são **passa direto** e ficam com os primeiros da ordem; nas
  posições-padrão (`seedOrder`: 1 × 8, 4 × 5, 2 × 7, 3 × 6) os cabeças 1 e 2 só se cruzam na
  final. O vencedor segue por `next_match_id` / `next_slot`.
- **Grupos + mata-mata**: os times vão para os grupos em serpentina (A B C C B A…), cabeças
  de chave primeiro — por isso caem em grupos diferentes. Cada grupo joga todos contra todos
  em turno único (uma rodada por volta, cada time no máximo uma vez por rodada). Cada grupo
  precisa de ao menos 2 times e de pelo menos o número de classificados. O mata-mata (fase
  2) nasce com os lados apontando para "Nº do Grupo X" (`*_source_group_id/position`), no
  cruzado padrão (`crossRanks`): com grupos em número par, A × B e C × D; ímpar, A × B,
  B × C, C × A; ninguém reencontra o próprio grupo na 1ª rodada quando há como evitar. Os
  times entram no mata-mata quando o grupo fecha (F6).
- **3º lugar**: se a categoria pede e há ao menos 4 times (ou classificados), um jogo a mais
  na rodada da final (`third_place`), que recebe os perdedores das semifinais (`advance`).
- **Gerar de novo / desfazer**: apaga fases, grupos e jogos (com datas e locais). Com jogo
  *encerrado* ou *W.O.* ("passa direto" não conta), pede confirmação (`resultsWarning`) e
  apaga os resultados e o histórico deles. Gerar de novo tira do site
  (`bracket_published_at` = nulo).
- **Formato**: com chaveamento gerado, mudar formato, número de grupos, classificados ou 3º
  lugar pede confirmação e desfaz o chaveamento.
- **Classificação do grupo** (`standings`): jogos, vitórias, derrotas, sets e games dos jogos
  com resultado; a ordem é a posição final do organizador (`final_position`, F6) ou, sem ela,
  vitórias, saldo de sets e de games.

## Resultados (`TournamentResultService`)

- **Placar**: games por set; todo set tem vencedor; o vencedor do jogo chega a `sets_to_win`
  e não há set depois de decidido; tie-break opcional, dos dois lados e com o mesmo
  vencedor do set. **W.O.**: vencedor sem sets (conta `sets_to_win` × 0).
- **Situação do torneio**: o Lara lança em publicado, em andamento e encerrado (em rascunho
  e cancelado, com confirmação); o site, só em publicado e em andamento.
- **O Lara prevalece**: lançado no Lara, `locked_by_lara` — o site recebe `match_locked`. O
  site pode corrigir o que ele mesmo lançou. **Limpar** (só no Lara) volta o jogo para *A
  jogar* e destrava.
- **Avanço**: o vencedor vai para o próximo jogo (`advance`); o perdedor da semifinal, para
  o 3º lugar. Correção que **muda o vencedor** com o jogo seguinte (ou o 3º lugar) já
  jogado: o site é recusado (`next_match_played`); o Lara pede confirmação e apaga em
  cascata (`clearDownstream` → `wipe`, cada um logado com `cascade`).
- **Grupo**: fechar pede uma posição para cada time (jogo sem resultado pede confirmação);
  preenche os lados do mata-mata que apontam para o grupo e faz avançar os "passa direto".
  Com o grupo fechado, o site não muda os jogos dele (`group_closed`); o Lara pede
  confirmação e reabre o grupo. Reabrir (ou fechar de novo) com o mata-mata afetado já
  jogado pede confirmação e limpa esse mata-mata (`freeFed`).
- **Histórico**: cada lançamento, correção e limpeza em `tournament_match_result_logs`
  (origem, usuário do Lara ou o nome informado pelo site, placar e o anterior).
- **Código de lançamento** (`TournamentResultCodeService`): 8 caracteres sem os que se
  confundem; o banco guarda só o sha256 (`tournaments.results_code_hash`); vence no fim do
  último dia do torneio (ou de hoje, se já acabou).

## Relatório financeiro (`TournamentFinanceService`)

A conta é **por inscrição**, porque um pagamento pode cobrir várias categorias:

- **recebido** = valor das inscrições que foram pagas (ainda pagas ou canceladas depois),
  separado em eRede e baixa manual;
- **estornado** = `tournament_registrations.refunded_amount`, gravado no cancelamento com
  estorno (o `TournamentPayment` continua com o total estornado do pagamento);
- **líquido** = recebido − estornado; **a receber** = inscrições aguardando pagamento.

## API do site (`Api\TournamentApiController`)

Todas pedem o `api_token` (`Authorization: Bearer`). As marcadas com **L** pedem também o JWT
do sócio no header `Session`. Recusas de regra: `{message, reason, context?}`, com os
`reason` de `App\Exceptions\TournamentException`.

| Método | Rota | | O que faz |
|---|---|---|---|
| GET | `/api/tournaments` (`?place_group_id=`) | | Publicados, em andamento e encerrados, com categorias, preço e `vacancies_left`. |
| GET | `/api/tournaments/{id}` | | O mesmo, com o regulamento (`rules`). Rascunho e cancelado: 404 `tournament_not_found`. |
| GET | `/api/tournaments/{id}/categories/{categoryId}/bracket` | | Chaveamento **divulgado**: fases, grupos (classificação e jogos) e mata-mata. Antes de divulgar: 404 `bracket_not_published`. |
| GET | `/api/tournaments/{id}/eligibility` | L | Por categoria: `eligible`, `reason` (`gender`, `gender_unknown`, `birth_date_unknown`, `too_young`, `too_old`) e a inscrição ativa, se houver. |
| POST | `/api/tournaments/{id}/registrations` | L | `{category_ids: []}` → 201 `{registrations, total_due, pay_until}`. |
| GET | `/api/tournaments/my-registrations` | L | Todas as inscrições do sócio, com torneio, categoria e pagamento. |
| POST | `/api/tournaments/payment` | L | `{registration_ids, payment_method, paid_amount, payment_integration_id, paid_at?}` → 201 (ou 200 no reenvio). |
| DELETE | `/api/tournaments/registrations/{id}` | L | Desiste (só pendente). Paga: 422 `paid_contact_organizer`. Alheia: 404. |
| GET | `/api/tournaments/my-teams` | L | Times do sócio: membros (pago, capitão) e, para o capitão, convites pendentes. |
| POST | `/api/tournaments/categories/{id}/teams` | L | Capitão cria o time (`{name?}`); precisa estar inscrito na categoria. |
| GET | `/api/tournaments/member-lookup?title=` ou `?cpf=` | L | Pessoas para convidar: `ref`, nome, CPF mascarado, titular. Nunca devolve o CPF. |
| POST | `/api/tournaments/teams/{id}/invites` | L | Capitão convida (`{ref}`); manda e-mail. |
| GET | `/api/tournaments/my-invites` | L | Convites pendentes recebidos. |
| POST | `/api/tournaments/invites/{id}/accept` | L | Aceita: entra no time (inscrevendo, se preciso) → `{registration, team}`. |
| POST | `/api/tournaments/invites/{id}/decline` | L | Recusa. |
| DELETE | `/api/tournaments/invites/{id}` | L | Capitão retira convite pendente. |

`/api/tournaments/{id}` traz também `categories[].teams` (nome, situação e quem joga), e
`my-registrations` traz `team` em cada inscrição. Toda categoria traz `bracket_published`.

Reasons: `tournament_not_found`, `registration_closed`, `member_not_found`,
`category_not_found`, `ineligible` (`context.detail`), `already_registered` (409),
`category_full` (409), `invalid_status`, `not_owner`, `payment_mismatch`
(`context.expected`), `paid_contact_organizer`, `wrong_formation`, `team_not_found`,
`not_captain` (403), `team_full` (409), `already_in_team` (409), `not_registered`,
`invite_not_found` (404), `bracket_not_published` (404).

### Equipe do clube (`Api\TournamentStaffApiController`)

Pedem o `api_token` e o código de lançamento no header `Tournament-Code` (no `check`, no
corpo). Código errado, vencido ou retirado: 401 `invalid_code`.

| Método | Rota | O que faz |
|---|---|---|
| POST | `/api/tournaments/{id}/staff/check` | Confere o código (10/min) → torneio e validade. |
| GET | `/api/tournaments/{id}/staff/matches` | Categorias com chaveamento gerado (divulgado ou não), com `editable`, `locked`, `result_origin` em cada jogo. |
| PUT | `/api/tournaments/{id}/staff/matches/{matchId}/result` | `{operator_name, sets[]}` ou `{operator_name, walkover_winner_team_id}`. |

Reasons novos (F6): `invalid_code` (401), `match_not_found` (404), `match_locked` (409),
`match_not_ready`, `invalid_score` (`context.detail`), `next_match_played` (409),
`group_closed` (409), `results_closed`.

Prompt para o agente do site (consolidado na F8): [torneios-site-prompt.md](torneios-site-prompt.md).

## Busca de sócio no MultiClubes

`App\Services\MultiClubes\MemberProfileLookup` traz nome, CPF, matrícula, nascimento, sexo e
e-mail, com o mesmo critério de sócio do cadastro (título ativo, fora dos tipos especiais).
Dois cuidados que os dados exigiram (conferidos em 22/10/2026):

- **CPF de preenchimento:** há cadastro com `00000000000`. CPF com dígito verificador
  inválido nem é consultado.
- **O mesmo CPF em mais de um título:** 438 CPFs estavam em mais de um registro ativo. A busca
  por CPF devolve lista; para o sócio logado, `find(cpf, matrícula)` pega o registro certo.

## Fases

| Fase | Conteúdo | Situação |
|---|---|---|
| F0 | Conferir que as tabelas antigas estão vazias em produção | feita (22/10/2026) |
| F1 | Apagar o módulo antigo | feita no código (22/10/2026); as tabelas caem no próximo deploy |
| F2 | Migrations, models, permissões, catálogo de categorias, busca no MultiClubes | feita (22/10/2026) |
| F3 | Torneio, inscrição, pagamento eRede, baixa manual, estorno, e-mails, API do sócio | feita (22/10/2026) |
| F4 | Times: organizador, sorteio, convite do capitão | feita (23/10/2026) |
| F5 | Chaveamento: eliminatória e grupos + mata-mata, sorteio ou à mão, divulgação no site | feita (23/10/2026) |
| F6 | Resultados no Lara e no site (código de lançamento por torneio), fechamento de grupo, histórico | feita (23/10/2026) |
| F7 | Andamento por categoria na tela do torneio, relatório financeiro com CSV, menu | feita (23/10/2026) |
| F8 | Fechamento: docs, [guia](torneios-guia.md), prompt do Next.js consolidado, suíte | feita (23/10/2026) |

A "F9" pedida em 23/10 (cadastro manual de associados e senha) **não é do módulo de
torneios**: virou **Reservas › Contas do site**, no Lara — ver
[contas-do-site.md](contas-do-site.md).

## Dados de teste (`php artisan torneios:dados-teste`)

Para homologação: cria um torneio em cada situação, com todos os cenários, para testar o
painel e o site de locação. **Recusa produção** (ambiente `production` ou banco `lara`).
Tudo leva o prefixo `[TESTE]`; rodar de novo apaga o anterior e recria; `--so-limpar` só
apaga. Não manda e-mail nem chama eRede ou MultiClubes (grava direto nas tabelas).

| Torneio | O que tem |
|---|---|
| Inscrições abertas | Duplas Livre (paga eRede, baixa manual, pendente, desistência, cancelada com estorno, time completo e em formação); Simples Masculino (4 vagas cheias, um pagamento cobrindo duas categorias, pendente sem vaga); Duplas Femininas 18+ (capitão, convite pendente e recusado, menor liberada); Kids (grátis) |
| Em andamento | eliminatória de 6 com "passa direto", 3º lugar, resultado do Lara (travado) e do site; grupos com o Grupo A fechado e um W.O. no B; chaveamento não divulgado; categoria sem chaveamento; **código de lançamento** (impresso no fim) |
| Rascunho, Encerrado, Cancelado | um de cada (o encerrado com a chave completa) |

Contas do site: um CPF com duas contas, uma conta sem e-mail e uma com senha no formato
antigo. As pessoas de teste entram no site (login = CPF, senha `teste123`), mas **não
existem no MultiClubes**: inscrição e elegibilidade *pelo site* delas dão
`member_not_found`. Para testar o fluxo do site com uma pessoa de verdade, passe
`--socio=<CPF de uma conta do site>`: ela ganha uma inscrição pendente (para pagar) e um
convite de time.

```
php artisan torneios:dados-teste --socio=12345678909    # --grupo=<id> --usuario=<id>
php artisan torneios:dados-teste --so-limpar
```

## Depois do deploy

1. As migrations e o seed de permissões rodam pelo `deploy_hml.sh` / `deploy_prod.sh`.
2. Tela de **Setores**: dar ao **Esporte** as `torneios.*` (gerenciar, inscricoes,
   resultados, pagamentos.baixa-manual, pagamentos.estornar, financeiro) — e
   `torneios.financeiro` também a quem cuida do financeiro, se for outro setor.
3. `php artisan acesso:limpar-legado` (primeiro sem `--confirmar`, para ver; depois com):
   tira a permissão antiga `torneios`.
4. Entregar o [prompt](torneios-site-prompt.md) ao agente do site.

## Para implementação futura

- **Inscrição de não sócios.** Hoje só sócios se inscrevem (`audience = members`). Abrir
  para o público exigirá cadastro de pessoa sem matrícula (CPF, nome, nascimento, sexo,
  e-mail), outro login no site e, provavelmente, preço diferente por público. O campo
  `audience` do torneio está previsto na modelagem para isso.
