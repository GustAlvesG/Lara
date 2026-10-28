---
modulo: Home Assistant
palavras: luz, iluminação, refletor, acender, apagar, ligar luz, desligar luz, quadra apagada, quadra acesa, contator, interruptor, switch, agendamento de luz, manutenção, autoatendimento, sócio acende a luz, fim de semana, feriado, horário da luz, manual, automático
---

# Home Assistant — guia de uso

O módulo Home Assistant do Lara controla **quando cada luz do clube liga e desliga**, principalmente os refletores das quadras. O Home Assistant é o sistema que comanda os interruptores (contatores); de poucos em poucos minutos ele pergunta ao Lara se cada contator deve estar ligado, e o Lara responde.

A resposta combina três coisas: o **controle manual** feito pela equipe, os **agendamentos** de iluminação (por exemplo, "refletores toda noite das 18:00 às 22:00" ou "quadra desligada em manutenção") e as **reservas confirmadas** das quadras. Além disso, nos horários de uso livre (fim de semana e feriados liberados), o sócio pode **acender a luz sozinho** pelo site do clube — é o autoatendimento.

A tela fica em **Home Assistant** no menu e tem três abas: **Contatores**, **Agendamentos** e **Autoatendimento**. Ela recarrega sozinha a cada minuto, a não ser que você esteja digitando ou com uma janela aberta.

## Quem faz o quê

| Para | Permissão (como aparece na tela Setores) |
|---|---|
| Abrir a tela Home Assistant, ligar e desligar luzes, cadastrar contatores, criar agendamentos e configurar o autoatendimento | Home Assistant › Painel de iluminação e autoatendimento |
| Escolher o interruptor (Switch vinculado) de uma quadra no cadastro do local | Home Assistant › Painel de iluminação e autoatendimento (além da permissão de configurar locais, em Reservas) |
| Marcar se a quadra aceita autoatendimento ("Permitir que o sócio acenda a luz pelo aplicativo") | Reservas › Locais, grupos e regras de reserva |

Quem libera é a TI ou a Gerência, na tela **Setores** (ou só para uma pessoa, na tela **Usuários**); o coordenador do setor pede a liberação e coloca a equipe no setor pela tela **Meu setor**. Na prática, o painel costuma ficar com a TI e com quem cuida das quadras.

## O que decide se uma luz está acesa
<!-- telas: home-assistant.index -->

Na tela **Home Assistant**, o botão **Como funciona** resume a regra. Para cada contator, a primeira regra que se aplica decide:

1. **Controle manual** — "Ligado" ou "Desligado" escolhido no cartão do contator. Vale até o fim do dia (meia-noite) ou até alguém voltar para "Automático". Sempre vence os agendamentos e as reservas.
2. **Agendamentos** — regras por dia e horário criadas na aba Agendamentos. Se dois valem ao mesmo tempo, vence o de maior prioridade. Fora das faixas de horário de um agendamento "Por horário", passam a valer as reservas.
3. **Reservas** — a luz liga **5 minutos antes** e desliga **5 minutos depois** de cada reserva **confirmada** nos espaços ligados ao contator. Reserva pendente (esperando pagamento) não acende a luz. Sem reserva, fica desligado.

O topo da tela mostra os números do momento: **Ligados agora**, **Em modo manual**, **Agendamentos ativos** e **Valendo agora**, com a data e hora do estado e o link **Atualizar**.

O acendimento pelo sócio (autoatendimento) entra como um comando manual com prazo: enquanto o tempo dele não acaba, a luz fica acesa.

## Ligar ou desligar uma luz agora
<!-- telas: home-assistant.index -->

Na tela **Home Assistant**, aba **Contatores**, cada cartão tem no rodapé três botões:

- **Automático** — o contator segue os agendamentos e as reservas.
- **Ligado** — liga agora e mantém ligado até o fim do dia.
- **Desligado** — desliga agora e mantém desligado até o fim do dia, **mesmo com reserva**.

Clique no botão desejado. A tela confirma com "Contator ligado até o fim do dia.", "Contator desligado até o fim do dia." ou "Contator de volta ao automático.". O botão do estado atual fica destacado e desabilitado.

O comando manual termina sozinho à meia-noite. No dia seguinte, o contator volta ao automático. Se você desligou uma quadra por um problema que vai durar mais de um dia, crie um agendamento "Manter desligado" em vez de repetir o comando todo dia.

O Home Assistant aplica a mudança na próxima vez que consultar o Lara, o que pode levar alguns instantes.

## Ler o cartão e a linha do tempo de um contator
<!-- telas: home-assistant.index -->

Na tela **Home Assistant**, aba **Contatores**, cada cartão mostra:

- o **nome** do contator e o código dele no Home Assistant;
- o estado atual (**Ligado** ou **Desligado**) e a origem: **Manual**, **Agendamento**, **Reserva** ou **Automático**;
- o motivo em texto, por exemplo "Reserva até 21:05", "Agendamento “Refletores à noite”" ou "Sem reserva no momento";
- a **linha do tempo de hoje**, com cores: Reserva, Agendamento, Manual, trechos **Forçado desligado** (hachurados) e uma linha vermelha marcando **Agora**;
- os **espaços** ligados ao contator. Se aparecer "Nenhum espaço vinculado: reservas não acendem este contator", nenhuma quadra usa esse interruptor;
- quantos agendamentos usam o contator (clique para ir à aba Agendamentos).

A busca do topo da aba filtra por nome do contator, código ou espaço.

## Cadastrar, editar ou remover um contator
<!-- telas: home-assistant.index -->

Um contator é o interruptor do Home Assistant que liga um conjunto de luzes (por exemplo, os refletores de uma quadra).

Para cadastrar, na tela **Home Assistant**, aba **Contatores**, clique em **Novo contator** e preencha:

- **Nome** — como aparece no painel e no dashboard (ex.: "Quadra 1 – Refletores").
- **Entity ID no Home Assistant** — precisa ser **idêntico** ao do Home Assistant (em Configurações → Entidades, por exemplo `switch.quadra_1`). Se estiver diferente, o Home Assistant não reconhece a resposta e a luz não obedece.

Clique em **Cadastrar contator**.

Para editar ou remover, use o menu de três pontos do cartão: **Editar** (salve com **Salvar alterações**), **Copiar entity_id** ou **Remover**. Ao remover, os espaços ligados a ele ficam sem contator e ele sai dos agendamentos.

Depois de cadastrar, ligue o contator à quadra: em **Reservas › Locais e Grupos**, abra o grupo, edite o local e escolha o contator em **Switch vinculado**. Só assim as reservas daquela quadra acendem a luz. Esse campo só pode ser alterado por quem tem a permissão do Home Assistant; para os demais, aparece como "Restrito a TI".

## Criar um agendamento de iluminação
<!-- telas: home-assistant.index -->

Na tela **Home Assistant**, aba **Agendamentos**, clique em **Novo agendamento** (o botão só funciona depois que existe pelo menos um contator).

1. **Nome** — ex.: "Refletores à noite".
2. **O que fazer**:
   - **Por horário** — liga ou desliga em faixas de horário. Em **Faixas de horário**, escolha **Liga** ou **Desliga** e informe das … às …. Use **Adicionar faixa** para ter mais de uma. Faixas podem passar da meia-noite (22:00 às 02:00 aparece como "+1 dia"). Fora das faixas, valem as reservas.
   - **Manter ligado** — o dia inteiro, nos dias escolhidos (ex.: evento).
   - **Manter desligado** — o dia inteiro, mesmo com reserva (ex.: manutenção).
3. **Em quais dias** — **Todos os dias**, **Segunda a sexta**, **Fim de semana** ou os dias marcados um a um. Nenhum dia marcado vale para todos os dias.
4. **Quais contatores** — marque ao menos um (**Selecionar todos** marca todos).
5. **Período, prioridade e status** (opcional): **Começa em** e **Termina em** (em branco = sem limite), **Prioridade** (de 0 a 999; se dois agendamentos valem ao mesmo tempo para o mesmo contator, vence o de maior número) e **Ativo**.

Antes de salvar, o rodapé mostra um resumo em texto, como "Liga das 18:00 às 22:00 · Quadra 1 · de segunda a sexta.". Clique em **Criar agendamento**.

O controle manual do cartão sempre vence qualquer agendamento.

## Pausar, editar ou remover um agendamento de iluminação
<!-- telas: home-assistant.index -->

Na tela **Home Assistant**, aba **Agendamentos**, cada agendamento aparece com o resumo, os dias da semana destacados, os contatores, o período, a prioridade e quem criou. Os selos indicam a situação:

- **Valendo agora** — está decidindo o estado de algum contator neste momento;
- **Pausado** — guardado, mas sem efeito;
- **Expirado** — a data de término já passou.

Ações de cada linha:

- a **chave** liga e desliga o agendamento (Pausar / Ativar) sem apagar nada;
- o **lápis** abre a edição (salve com **Salvar alterações**);
- o **lixo** remove, depois de confirmar.

Quando existem agendamentos pausados ou expirados, aparecem os filtros **Ativos** e **Pausados e expirados**. A busca filtra por nome do agendamento ou do contator.

Use a pausa para suspender um agendamento por um tempo (por exemplo, nas férias) e reativá-lo depois sem recriar.

## Como funciona o autoatendimento de iluminação do sócio
<!-- telas: home-assistant.index -->

No fim de semana não há reserva de quadra: o uso é livre. Nos horários liberados, o sócio **acende a luz da quadra pelo site do clube**, escolhendo por quanto tempo. A aba **Autoatendimento** da tela **Home Assistant** mostra e configura isso.

Regras para o sócio:

- O tempo de cada acionamento tem um teto (o valor aparece no topo da aba, por padrão 2 horas).
- **Uma quadra por vez** por sócio.
- Acabando o tempo, qualquer sócio presente aciona de novo e a luz continua acesa sem piscar. Acionar uma quadra já acesa **prolonga**.
- Recusas automáticas: quadra com reserva confirmada, sócio que já está em outra quadra e menos de 15 minutos para o fim do horário liberado.

Para uma quadra aparecer no site, ela precisa:

1. ter um **Switch vinculado** (o contator), e
2. estar marcada em **Permitir que o sócio acenda a luz pelo aplicativo**, no cadastro do local (Reservas › Locais e Grupos › editar o local), e
3. pertencer a um grupo.

No topo da aba aparece se há quadras abertas agora (ou "Fechado agora") e quando será a próxima abertura. A lista **Quadras liberadas** mostra cada quadra marcada, o contator e o horário de hoje ("fechada hoje" quando não abre). **Acesas agora pelos sócios** lista as quadras acesas pelo autoatendimento, o sócio e até que horas.

## Definir o horário padrão do autoatendimento
<!-- telas: home-assistant.index -->

Na tela **Home Assistant**, aba **Autoatendimento**, a área **Horário padrão** define quando o sócio pode acender a luz, para toda quadra liberada que não tenha horário próprio.

1. Para cada dia da semana, preencha o horário de início e de fim ("às").
2. Deixe **os dois campos vazios** para **fechar** o dia.
3. A linha **Feriado** é o horário usado quando uma data especial liberada não traz horário próprio.
4. Clique em **Salvar horário padrão**.

O horário final precisa ser depois do inicial e no mesmo dia. Informe os dois horários ou nenhum.

Exemplo comum: sábado e domingo das 07:00 às 22:00, dias de semana vazios (fechados, porque há reservas), Feriado das 07:00 às 22:00.

## Dar um horário próprio a uma quadra no autoatendimento
<!-- telas: home-assistant.index -->

Quadra coberta escurece antes e pode abrir mais cedo que a descoberta ao lado. Para isso, cada quadra pode ter seu próprio horário.

1. Na tela **Home Assistant**, aba **Autoatendimento**, em **Quadras liberadas**, clique em **Horário** na linha da quadra.
2. Para cada dia, escolha:
   - **Seguir o padrão** (mostra o horário padrão entre parênteses);
   - **Horário próprio** — informe início e fim;
   - **Fechada neste dia**.
3. Clique em **Salvar**.

A quadra passa a mostrar o selo "horário próprio". O que não for definido na quadra segue o horário padrão do clube. A linha Feriado também pode ser própria, para a quadra coberta abrir cedo nos feriados.

Para desfazer tudo, abra o mesmo **Horário** e clique em **Voltar ao padrão em todos os dias**.

## Liberar ou bloquear uma data no autoatendimento (feriado, torneio)
<!-- telas: home-assistant.index -->

Na tela **Home Assistant**, aba **Autoatendimento**, a área **Datas especiais** abre ou fecha o autoatendimento num dia específico. A data sempre vence o dia da semana.

1. Informe a **Data**.
2. Em **Regra**, escolha **Liberar** (ex.: feriado no meio da semana) ou **Bloquear** (ex.: sábado de torneio).
3. Em Liberar, informe **Das** e **Até**, ou deixe em branco para usar a linha Feriado do horário de cada quadra. Em Bloquear, os horários são ignorados: o bloqueio é o dia inteiro, em todas as quadras.
4. Escreva o **Motivo** (ex.: Natal, torneio).
5. Clique em **Salvar data**.

Há uma regra por data: salvar a mesma data de novo substitui a anterior. A lista abaixo mostra as datas futuras com o selo **Liberado** ou **Bloqueado**; **Remover** apaga a data, depois de confirmar.

## Dúvidas comuns

### A quadra tem reserva e a luz não acendeu
<!-- telas: home-assistant.index -->

Confira na tela **Home Assistant**, no cartão do contator:

- A reserva está **confirmada**? Reserva pendente (esperando pagamento no site) não acende.
- O cartão mostra "Nenhum espaço vinculado: reservas não acendem este contator"? Então a quadra não tem o **Switch vinculado**: ajuste em Reservas › Locais e Grupos › editar o local.
- O cartão está em **Manual · Desligado** ou há um agendamento **Manter desligado** ou com faixa **Desliga** valendo? Eles vencem a reserva.
- A luz liga só 5 minutos antes do início da reserva.

Se tudo estiver certo no painel e a luz não acender, o problema pode estar no Home Assistant ou na fiação: avise a TI.

### A luz ficou acesa sem ninguém na quadra
<!-- telas: home-assistant.index -->

Veja a origem no cartão do contator: **Manual** (alguém clicou em Ligado; vale até a meia-noite), **Agendamento** (veja qual no motivo) ou **Reserva** (há reserva confirmada até a hora mostrada; a luz desliga 5 minutos depois do fim). Para apagar agora, use **Desligado** no cartão — vale até o fim do dia, mesmo com reserva.

### Cliquei em Ligado e no dia seguinte a luz voltou ao normal
<!-- telas: home-assistant.index -->

É o esperado: o controle manual do painel vale só até a meia-noite. Para manter uma luz ligada ou desligada por vários dias, crie um agendamento **Manter ligado** ou **Manter desligado**, com período em **Começa em** e **Termina em**.

### O botão "Novo agendamento" está desabilitado
<!-- telas: home-assistant.index -->

Agendamentos precisam de pelo menos um contator. Cadastre o contator na aba **Contatores** (botão **Novo contator**). No formulário, "Selecione ao menos um contator." aparece enquanto nenhum contator estiver marcado, e o botão de salvar fica apagado.

### Mensagens ao salvar um agendamento ou horário
<!-- telas: home-assistant.index -->

- "Adicione ao menos uma faixa de horário." — o modo Por horário precisa de pelo menos uma faixa.
- "O horário final de uma faixa precisa ser diferente do inicial."
- "O horário final precisa ser depois do inicial, e no mesmo dia." — no autoatendimento, a faixa não passa da meia-noite.
- "Informe os dois horários, ou nenhum."
- "Informe o horário da faixa, ou escolha herdar/fechado." — no horário próprio da quadra, escolheu Horário próprio sem preencher.

### Erro ao cadastrar um contator
<!-- telas: home-assistant.index -->

O **Entity ID no Home Assistant** não pode repetir: cada interruptor só pode ser cadastrado uma vez. Se o sistema recusar dizendo que o código já está em uso, procure o contator existente na busca da aba Contatores e edite-o.

### Por que a quadra não aparece para o sócio acender a luz?
<!-- telas: home-assistant.index -->

Na aba **Autoatendimento**, confira se a quadra está em **Quadras liberadas**. Ela precisa ter um Switch vinculado, a opção "Permitir que o sócio acenda a luz pelo aplicativo" marcada no cadastro do local e um grupo (o selo "Sem grupo — invisível no app" indica o contrário). Se a lista estiver vazia, aparece "Nenhuma quadra liberada" e o site não mostra a opção. Confira também se o dia está aberto no horário padrão ou no horário próprio da quadra, e se não há uma data especial **Bloquear**.

### O sócio diz que o site recusou acender a luz
<!-- telas: home-assistant.index -->

As recusas automáticas são: quadra com reserva confirmada naquele momento, sócio que já acendeu outra quadra (uma por vez), horário fechado (fora da janela do dia ou data bloqueada) e menos de 15 minutos para o fim do horário liberado. Em **Acesas agora pelos sócios** dá para ver quem está com a luz acesa e até quando.

### Por que não vejo o menu Home Assistant?
A tela pede a permissão "Painel de iluminação e autoatendimento" do grupo Home Assistant. Fale com o coordenador do seu setor: ele coloca você num setor que já tem a permissão (tela **Meu setor**) ou pede à TI ou à Gerência que a liberem (tela **Setores**).
