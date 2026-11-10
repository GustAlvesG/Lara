---
modulo: InfoClube
palavras: informação, informações, atividade, serviço, aula, horário de aula, preço, responsável, aviso, avisos, comunicado, recado, lembrete, leitura obrigatória, ciência, confirmar leitura, quem leu, expiração, visibilidade, setor, grupo, mural, tags, histórico de versões, fila de espera, lista de espera, não sócio, turma, vaga, modalidade, matrícula, turma lotada, ligar, ligação
---

# InfoClube — guia de uso

O InfoClube é o mural interno do clube no Lara. Ele tem três telas:

- **Informações** — o catálogo das atividades, serviços e ofertas do clube (aulas, escolinhas, serviços), com descrição, preços para sócio e não sócio, dias e horários, responsáveis, vagas e local. Cada edição guarda a versão anterior no histórico.
- **Avisos** — comunicados para a equipe, com escolha de quem vê (todos, o setor, um grupo de pessoas ou só você), lembretes, data de expiração e, para coordenadores, **leitura obrigatória**.
- **Fila de Espera** — a lista de espera dos **não sócios** nas turmas lotadas das modalidades. A fila dos sócios continua no MultiClubes. Só aparece para quem tem a permissão.

Todo funcionário logado lê as informações e os avisos que são para ele. Criar e editar informações pede permissão; avisos qualquer pessoa logada pode publicar.

O HAL, o assistente de IA do chat, também consulta as informações e os avisos para responder perguntas — por isso vale manter nomes, tags e textos claros.

## Quem faz o quê

| Para | Permissão (como aparece na tela Setores) |
|---|---|
| Ler as informações e o histórico de versões | Nenhuma: todo usuário logado |
| Criar, editar, restaurar versão e excluir informações | InfoClube › Criar, editar e excluir informações |
| Ler os avisos dirigidos a você | Nenhuma: todo usuário logado |
| Publicar, editar e remover avisos | Nenhuma: todo usuário logado |
| Marcar um aviso como leitura obrigatória | Não é permissão: só quem é coordenador de algum setor vê a opção |
| Usar a Fila de Espera (turmas, incluir pessoas, registrar ligações, tirar da fila) | InfoClube › Fila de espera das modalidades (não sócios): turmas, inclusão, ligações e saída da fila |

As permissões de informações e da fila de espera são liberadas pela TI ou pela Gerência, na tela **Setores**; o coordenador do setor pede a liberação e coloca a equipe no setor pela tela **Meu setor**. Coordenador é quem está cadastrado com o papel de coordenador num setor, também na tela Setores. Gerência, Diretoria e TI (acesso total) enxergam todos os avisos do sistema na lista.

## Encontrar uma informação
<!-- telas: information.index -->

A tela **InfoClube › Informações** mostra as informações em cartões, 12 por página. O título informa quantas estão publicadas.

- **Buscar:** digite na barra "Buscar por nome, tag, responsável, local…". A busca procura no nome, na descrição, no responsável, no status, na localização e nas tags, em todas as páginas.
- Cada cartão mostra a imagem (ou as iniciais do nome), o nome, as tags, um trecho da descrição, vagas, status e local, e no rodapé o **primeiro responsável** (com link de WhatsApp) e o **primeiro preço** de sócio.
- Clique no cartão para ver tudo: preços ("Título — R$ X (Sócio) | R$ Y (Não Sócio)"), dias e horários, todos os responsáveis, taxa de matrícula, vagas e local, além de quando e por quem a versão foi criada.

Quando a busca não acha nada, a tela mostra "Nenhuma informação corresponde a …" e oferece limpar a busca.

## Criar uma informação
<!-- telas: information.index -->

Em **InfoClube › Informações**, clique em **Nova informação** (só aparece para quem tem a permissão). A informação aparece para todos assim que for criada.

**Identificação (coluna da esquerda)**

- **Nome** (obrigatório, até 255 caracteres).
- **Descrição** (obrigatória): o editor tem negrito, itálico, sublinhado, tabela, cor de fundo do texto e tela cheia.

**Tags (obrigatórias)**: no mínimo **3** tags diferentes. Digite e tecle Enter ou vírgula para adicionar; o X remove. A tela avisa quantas faltam. As tags ajudam a busca e são compartilhadas com os avisos ("Natação" e "natação" são a mesma tag).

**Detalhes adicionais**: ative só o que se aplica — o que ficar desativado não é salvo.

- **Imagem** (JPG, PNG ou GIF, até 4 MB).
- **Taxa de Matrícula**, **Número de Vagas**, **Status** (ex.: Inscrições abertas) e **Localização** (ex.: Piscina coberta).
- **Preços (Sócio / Não Sócio)**: título, R$ Sócio e R$ Não Sócio, com **+ Adicionar preço**.
- **Dias e Horários**: dia (Domingo a Sábado, Dias de Semana, Fim de Semana, Todos os dias), início e fim, com **+ Adicionar dia**.
- **Responsáveis**: nome e **Telefone (WhatsApp)**, com **+ Adicionar responsável**.

Nos preços e nos responsáveis, **o primeiro da lista é o que aparece no cartão** (selo "★ Exibido no card"). Use as setas ↑ e ↓ para mudar a ordem.

Clique em **Criar informação**.

## Editar uma informação
<!-- telas: information.index -->

Em **InfoClube › Informações**, abra a informação e clique em **Editar** (só para quem tem a permissão).

O formulário é o mesmo da criação, já preenchido. Altere o que precisar e clique em **Salvar nova versão**.

Editar **não apaga** a versão anterior: o Lara grava uma nova versão, e a anterior fica no histórico, com quem a criou e quando. A nova versão passa a ser a exibida para todos.

Pontos de atenção:

- As tags também são da versão: a nova versão precisa de pelo menos 3.
- **Imagem:** sem enviar arquivo novo, a imagem atual continua. Para tirar a imagem, ative Imagem e marque **Remover a imagem atual**; para trocar, use **Substituir imagem**.
- Campo desativado nos Detalhes adicionais deixa de existir na nova versão.
- Para mudar qual preço ou responsável aparece no cartão, reordene com as setas ↑ e ↓.

**Cancelar** volta à informação sem salvar.

## Ver o histórico e voltar uma versão de uma informação
<!-- telas: information.index -->

Em **InfoClube › Informações**, abra a informação e clique em **Histórico** (todos podem ver).

O histórico mostra as versões da mais recente para a mais antiga, uma por vez, com a navegação de páginas no rodapé. Cada versão traz "Versão N de N", a data, o autor e todos os dados daquele momento. A versão em uso tem o selo **Atual**.

Para voltar a uma versão antiga (só para quem tem a permissão):

1. Navegue até a versão desejada.
2. Clique em **Tornar versão atual** e confirme ("Restaurar esta versão? Ela será copiada como a nova versão atual.").

O Lara copia a versão antiga como uma versão nova, com as tags daquela época. Nada é apagado: a versão que estava em uso continua no histórico, e dá para voltar a ela do mesmo jeito.

## Excluir uma informação
<!-- telas: information.index -->

Em **InfoClube › Informações**, abra a informação e clique em **Excluir** (só para quem tem a permissão). Confirme a mensagem "Você tem certeza que deseja apagar essa informação? Essa ação é irreversível."

A informação sai da lista e da busca, e a tela dela e o histórico deixam de abrir. O HAL (assistente de IA) também deixa de usá-la nas respostas.

Se a ideia é só corrigir ou atualizar, prefira **Editar**: a versão anterior fica guardada no histórico. Se a atividade foi suspensa temporariamente, uma alternativa é editar e mudar o **Status** (ex.: "Inscrições encerradas").

## Publicar um aviso
<!-- telas: avisos.index -->

Em **InfoClube › Avisos**, clique em **Novo aviso**. Qualquer usuário logado pode publicar.

1. **Título** (obrigatório, até 200 caracteres) — curto e direto.
2. **Conteúdo** — com negrito, itálico e sublinhado.
3. **Visibilidade** — Pessoal, Setor, Público ou Grupo (veja a seção sobre quem vê o aviso). Em Grupo, escolha os **Destinatários**.
4. **Leitura obrigatória** — só aparece para coordenadores de setor.
5. **Imagem (opcional)**.
6. **Tags** — digite e tecle Enter; tags novas são criadas na hora.
7. **Lembretes** — **Adicionar lembrete** e escolha data e hora (precisam estar no futuro).
8. **Data de expiração (opcional)** — não pode ser no passado.

Clique em **Publicar aviso**. A tela mostra "Aviso criado com sucesso!" e quem recebe o aviso ganha uma notificação no **sino** do Lara.

## Quem vê um aviso (visibilidade)
<!-- telas: avisos.index -->

Ao criar ou editar um aviso em **InfoClube › Avisos**, a **Visibilidade** define para quem ele é:

| Opção | Quem vê e recebe |
|---|---|
| **Pessoal** (Só você) | Só quem criou. Serve como lembrete pessoal. |
| **Setor** (Seu setor) | Quem está em pelo menos um setor em comum com quem criou. |
| **Público** (Todos) | Todos os usuários do Lara. |
| **Grupo** (Selecionar) | Quem criou e as pessoas escolhidas em **Destinatários** (busque pelo nome e clique para adicionar; o X remove). É preciso escolher ao menos uma pessoa. |

O padrão de um aviso novo é **Setor**. Quem tem acesso total (Gerência, Diretoria e TI) enxerga todos os avisos na lista, mesmo os que não são para eles — mas só recebe notificação e leitura obrigatória do que é dirigido a eles.

A visibilidade aparece numa etiqueta no cartão e na tela do aviso. O HAL (assistente de IA) segue a mesma regra: só usa, para cada pessoa, os avisos dirigidos a ela.

## Lembretes e data de expiração dos avisos
<!-- telas: avisos.index -->

No formulário do aviso (**InfoClube › Avisos › Novo aviso** ou **Editar**):

**Lembretes** — clique em **Adicionar lembrete** e escolha data e hora. Pode haver vários. Na data e hora marcadas, quem recebe o aviso ganha uma nova notificação no sino. Na tela do aviso, os lembretes já enviados aparecem com "(enviado)"; ao editar, só os ainda não enviados podem ser mudados ou removidos.

**Data de expiração** — o aviso vale até o fim desse dia. No próprio dia da expiração, quem recebe o aviso ganha uma notificação avisando que ele expira. No dia seguinte, ele sai da lista de ativos.

Na lista **InfoClube › Avisos**:

- a aba **Ativos** mostra os avisos válidos; os vencidos ficam recolhidos em **Expirados (N)**, logo abaixo;
- a aba **Todos** mostra ativos e expirados juntos;
- o selo **Expira em breve** aparece quando faltam 3 dias ou menos;
- o selo de lembretes mostra quantos o aviso tem.

Aviso sem data de expiração fica ativo até ser removido.

## Exigir leitura obrigatória de um aviso
<!-- telas: avisos.index -->

Coordenadores de setor veem, no formulário do aviso (**InfoClube › Avisos › Novo aviso** ou **Editar**), a opção **Leitura obrigatória**.

Com ela marcada, cada pessoa que recebe o aviso (conforme a Visibilidade) vê o texto **em tela cheia ao entrar no Lara** e só consegue usar o sistema depois de confirmar que leu. A confirmação fica registrada com nome, data e hora.

- Quem criou o aviso não precisa confirmar o próprio aviso.
- Só avisos ativos são cobrados: depois da data de expiração, ninguém mais é levado à tela de confirmação.
- Quem não é coordenador não vê a opção. Se editar um aviso obrigatório criado por um coordenador, ele continua obrigatório.

Na tela do aviso, a área **Ciência registrada** mostra quantas pessoas confirmaram, com o nome e a data e hora de cada uma. "Ninguém confirmou a leitura ainda." aparece enquanto não houver confirmações.

Use com moderação: a pessoa fica travada até confirmar. Prefira para normas, mudanças de procedimento e comunicados que todos precisam conhecer.

## Confirmar a leitura de um aviso obrigatório

Quando existe um aviso de leitura obrigatória para você, o Lara abre a tela **Aviso de leitura obrigatória** em vez da tela que você ia usar. Ela não tem menu: as únicas saídas são confirmar ou sair do sistema.

1. Leia o aviso (título, imagem, tags, texto, quem publicou e quando).
2. No rodapé, marque **Li e estou ciente deste aviso.**
3. Clique em **Confirmar leitura**.

A confirmação fica registrada com o seu usuário, a data e a hora. Se houver mais de um aviso pendente, o topo mostra quantos faltam e o botão vira **Confirmar e ver o próximo**. Depois do último, o Lara leva você para onde você ia.

O botão **Sair** (no topo) encerra a sessão sem confirmar; na próxima entrada, o aviso aparece de novo.

## Ver quem leu um aviso
<!-- telas: avisos.index -->

Em **InfoClube › Avisos**, abra o aviso. No fim da tela há duas áreas:

- **Histórico de acessos** — clique para abrir. Mostra cada pessoa que abriu o aviso, o último acesso (data e hora) e quantas vezes abriu. Cada abertura da tela do aviso conta, inclusive a de quem criou.
- **Ciência registrada** — só em avisos de leitura obrigatória. Mostra quem confirmou que leu, com data e hora.

O histórico de acessos conta quem **abriu a tela do aviso**. Quem viu o aviso apenas pelo cartão da lista, ou pela notificação sem clicar, não entra. Para ter certeza de que todos leram, use a leitura obrigatória (coordenadores).

## Editar ou remover um aviso
<!-- telas: avisos.index -->

Em **InfoClube › Avisos**, use o lápis do cartão ou abra o aviso e clique em **Editar**.

- Altere título, conteúdo, visibilidade, destinatários, tags, lembretes e expiração.
- **Remover imagem atual** tira a imagem; enviar outra substitui.
- Clique em **Salvar**. A tela mostra "Aviso atualizado!".

Editar não envia uma notificação nova; só os lembretes futuros notificam de novo.

Para remover: use o lixo do cartão ou **Remover aviso** na edição e confirme. A tela mostra "Aviso removido.".

Os botões de editar e remover aparecem para qualquer usuário logado que vê o aviso, não só para quem o criou. Combine no setor quem pode mexer nos avisos dos outros.

## Fila de Espera: como funciona
<!-- telas: waitlist.index -->

A tela **InfoClube › Fila de Espera** guarda quem **não é sócio** e quer vaga numa turma lotada (natação, tênis, ballet…). Cada turma tem a sua fila, na **ordem de chegada**.

- **Só não sócios.** O sócio entra na fila pelo MultiClubes, como sempre. O Lara não consulta o MultiClubes.
- **O sócio tem prioridade.** Antes de ligar para um não sócio, confira no MultiClubes se há sócio esperando na mesma turma. A tela da fila da turma lembra disso numa faixa de aviso, acima da lista.
- **Uma pessoa pode estar em várias filas** ao mesmo tempo (por exemplo, natação e tênis), uma vez em cada turma. O **CPF** identifica a pessoa e é obrigatório, inclusive para criança.
- **A posição não muda** por ligação sem resposta. Só sai da fila quem a secretaria tirar.

No alto da tela há duas listas: **Turmas** (cada turma com quantos aguardam e desde quando o primeiro espera) e **Pessoas** (busca por nome, CPF ou telefone).

## Cadastrar uma turma na Fila de Espera
<!-- telas: waitlist.index -->

Em **InfoClube › Fila de Espera**, clique em **Nova turma**.

1. **Modalidade** (obrigatória): ex. "Natação". O campo sugere as modalidades já usadas; escolha uma delas para não criar "Natação" e "natacao" separadas.
2. **Turma** (obrigatória): ex. "Infantil ter/qui 9h". Dias, horário e faixa etária no nome ajudam a escolher a turma certa.
3. **Observação** (opcional): professor, local, idade mínima.
4. **Turma ativa**: marcada, a turma recebe gente nova.

Clique em **Cadastrar turma**. Não pode haver duas turmas com o mesmo nome na mesma modalidade.

Para mudar, abra a turma e clique em **Editar turma**. Para parar de receber gente, desmarque **Turma ativa**: a fila continua visível. **Excluir** só funciona para turma que nunca teve ninguém na fila; a que já teve guarda o histórico.

## Incluir uma pessoa na Fila de Espera
<!-- telas: waitlist.index -->

Em **InfoClube › Fila de Espera**, clique em **Incluir pessoa**.

1. Digite o **CPF** da pessoa e clique em **Continuar**.
   - Se ela **já tem cadastro**, o Lara abre a ficha dela. Use **Incluir em outras turmas** lá.
   - Se não tem, aparece o formulário.
2. Preencha **Nome completo** e **Telefone** (obrigatórios). Os demais são opcionais: data de nascimento, outro telefone, e-mail, **responsável** e telefone do responsável (para menor) e observação (ex.: melhor horário para ligar).
3. Em **Turmas**, marque as turmas em que a pessoa entra na fila. Ela entra **no fim de cada fila**, com a data de hoje.
4. Clique em **Incluir na fila**.

Para colocar alguém que já está cadastrado em mais uma turma, abra a ficha (lista **Pessoas** ou pela fila da turma), marque as turmas em **Incluir em outras turmas** e clique em **Incluir na fila**.

## Chamar o próximo da fila e registrar a ligação
<!-- telas: waitlist.index -->

Quando abrir vaga numa turma:

1. Confira no **MultiClubes** se há **sócio** na fila dessa turma. Se houver, a vaga é dele.
2. Em **InfoClube › Fila de Espera**, abra a turma. O primeiro da lista (**1º**) é o próximo. A tabela mostra idade, telefone, desde quando espera e a última ligação.
3. Clique no nome (ou no lápis) para abrir a ficha da pessoa, já no cartão daquela turma.
4. Ligue. Em **Resultado da ligação**, escolha o que aconteceu, escreva uma observação se quiser e clique em **Registrar ligação**.

Resultados possíveis: **Não atendeu**, **Deixou recado**, **Pediu para ligar depois**, **Não quer a vaga agora (continua na fila)**, **Aceitou a vaga** e **Telefone errado**. Nenhum deles muda a posição nem tira a pessoa da fila. Cada ligação fica no cartão, com data, hora e quem ligou.

Se ninguém atender, você pode ligar para o seguinte e voltar ao primeiro depois: ele continua em 1º.

## Tirar uma pessoa da fila (matriculou ou desistiu)
<!-- telas: waitlist.index -->

Na ficha da pessoa, no cartão da turma, abra **Tirar da fila (matriculou, desistiu ou remover)**:

1. Escolha a **Situação**: **Matriculado** (fez a matrícula), **Desistiu** (não quer mais) ou **Removido** (por outro motivo, que precisa ser escrito).
2. Escreva o **Motivo / observação** (obrigatório só em Removido).
3. Clique em **Confirmar** e confirme a pergunta.

A matrícula em si continua sendo feita no MultiClubes; aqui só se registra que a pessoa saiu da fila. Se ela estiver em outras filas, o aviso verde lista quais: pergunte se ainda quer essas vagas e, se não, tire dela também.

Quem saiu aparece na ficha em **Saiu da fila** e, na turma, na aba **Saíram da fila**, com a situação, as datas, quem tirou e o motivo.

## Devolver uma pessoa à fila (reativar)
<!-- telas: waitlist.index -->

Tirou por engano? Na ficha da pessoa, em **Saiu da fila**, clique em **Reativar** na turma e confirme. A pessoa volta para a **mesma posição** de antes, porque a data de entrada não muda.

## Dúvidas comuns

### Por que não aparece o botão "Nova informação" ou "Editar"?
<!-- telas: information.index -->

Criar, editar, restaurar versão e excluir informações pedem a permissão "Criar, editar e excluir informações", do grupo InfoClube. Sem ela, você só lê. Fale com o coordenador do seu setor: ele coloca você num setor que já tem a permissão (tela **Meu setor**) ou pede à TI ou à Gerência que a liberem (tela **Setores**).

### "Cadastre pelo menos 3 tags para esta informação."
<!-- telas: information.index -->

Toda informação precisa de no mínimo 3 tags diferentes. Digite cada tag e tecle Enter ou vírgula para que ela vire uma etiqueta (só texto digitado sem Enter pode se perder). Outras mensagens das tags: "Há tags repetidas — cada tag precisa ser diferente das outras." e "Cada tag pode ter no máximo 50 caracteres."

### "Não foi possível salvar a informação."
<!-- telas: information.index -->

A mensagem completa é "Não foi possível salvar a informação. Tente novamente ou avise a TI se o problema continuar." O formulário volta com o que você digitou. Tente de novo; se repetir, avise a TI com o nome da informação e o horário. Confira também a imagem: precisa ser JPG, PNG ou GIF e ter até 4 MB.

### Mudei a informação, mas o cartão mostra o preço ou o responsável errado
<!-- telas: information.index -->

O cartão mostra sempre o **primeiro** preço e o **primeiro** responsável da lista. Edite a informação e use as setas ↑ e ↓ para colocar o certo em primeiro (ele ganha o selo "★ Exibido no card"). Salve a nova versão.

### Editei por engano. Como desfaço?
<!-- telas: information.index -->

Abra a informação, clique em **Histórico**, navegue até a versão anterior e clique em **Tornar versão atual**. Ela volta a ser a exibida, com as tags daquela época, e nada é perdido.

### Por que não vejo um aviso que um colega disse que publicou?
<!-- telas: avisos.index -->

Depende da Visibilidade escolhida: aviso de **Setor** só aparece para quem divide um setor com quem publicou; **Grupo**, só para os destinatários escolhidos; **Pessoal**, só para o autor. Confira também a aba **Todos** ou os **Expirados**: aviso vencido sai da lista de ativos. A busca procura no título e nas tags.

### "Selecione ao menos um destinatário."
<!-- telas: avisos.index -->

Na Visibilidade **Grupo**, é preciso escolher pelo menos uma pessoa em Destinatários. Busque pelo nome e clique para adicionar. Se não quiser escolher pessoas, use Setor ou Público.

### Erro na data do lembrete ou da expiração
<!-- telas: avisos.index -->

Ao publicar um aviso novo, os lembretes precisam estar no futuro e a data de expiração não pode ser anterior a hoje. Ajuste a data e a hora e publique de novo.

### Não recebi o lembrete de um aviso de Grupo
<!-- telas: avisos.index -->

Hoje, os lembretes agendados e a notificação do dia da expiração são enviados nos avisos Público, Setor e Pessoal, mas **não** nos avisos de Grupo. A notificação de publicação, essa sim, chega aos destinatários do grupo. Se o lembrete for importante, use Setor ou Público, ou avise a TI.

### Não aparece a opção "Leitura obrigatória"
<!-- telas: avisos.index -->

Só coordenadores de setor podem exigir leitura. Se você coordena a equipe e a opção não aparece, peça à TI para conferir o seu papel de coordenador na tela Setores.

### Não aparece a aba "Fila de Espera"
<!-- telas: waitlist.index -->

A Fila de Espera pede a permissão "Fila de espera das modalidades (não sócios)", do grupo InfoClube, porque guarda CPF e telefone. Fale com o coordenador do seu setor: ele coloca você num setor que já tem a permissão (tela **Meu setor**) ou pede à TI ou à Gerência que a liberem (tela **Setores**).

### Posso colocar um sócio na Fila de Espera do Lara?
<!-- telas: waitlist.index -->

Não. A Fila de Espera do Lara é só de não sócios. Sócio entra na fila pelo MultiClubes, que também tem prioridade: confira lá antes de chamar um não sócio.

### "Já existe uma pessoa na fila de espera com este CPF."
<!-- telas: waitlist.index -->

A pessoa já tem cadastro. Use **Incluir pessoa**, digite o CPF e clique em **Continuar**: o Lara abre a ficha dela, e lá você a coloca em outras turmas em **Incluir em outras turmas**. Se o CPF foi digitado errado em outro cadastro, corrija em **Editar cadastro** na ficha.

### A pessoa precisa mudar de turma. Ela perde a posição?
<!-- telas: waitlist.index -->

Não existe troca: cada turma tem a própria fila. Inclua a pessoa na turma nova (ela entra no fim dessa fila, com a data de hoje) e, se ela não quiser mais a antiga, tire-a da fila antiga com **Desistiu**. Ela pode também continuar nas duas.

### "Turma inativa não recebe gente nova"
<!-- telas: waitlist.index -->

A turma foi desativada. Se ela voltou a ter fila, abra a turma, clique em **Editar turma**, marque **Turma ativa** e salve.

### Não consigo excluir uma turma
<!-- telas: waitlist.index -->

Turma que já teve alguém na fila guarda esse histórico e não se exclui. Desmarque **Turma ativa** em **Editar turma**: ela para de receber gente nova.

### O Lara me levou para uma tela de aviso e não sai dela
Essa é a tela de **Aviso de leitura obrigatória**. Marque **Li e estou ciente deste aviso.** e clique em **Confirmar leitura**. Se aparecer "Marque que leu o aviso para continuar.", faltou marcar a caixa. Havendo mais de um aviso pendente, eles aparecem em sequência.
