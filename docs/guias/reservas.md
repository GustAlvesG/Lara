---
modulo: Reservas
palavras: reserva, agendamento, quadra, horário, agenda, churrasqueira, salão, espaço social, festa, pré-reserva, contrato, convidados, pagamento, estorno, baixa manual, cancelar reserva, locais, grupos, regra de locação, bloquear horário, conta do site, senha do site, login do sócio, esqueci a senha, CPF duplicado
---

# Reservas — guia de uso

O módulo Reservas do Lara cuida de tudo o que o sócio reserva no clube: **quadras e espaços esportivos**, reservados por horário, e **espaços sociais** (churrasqueiras e salões), reservados pelo dia inteiro com pré-reserva, contrato e aprovação da equipe.

A maior parte das reservas o sócio faz sozinho pelo **site do clube**, com o login dele (CPF e senha). No Lara, a equipe acompanha a agenda, lança reservas no balcão, cancela, confere pagamentos, dá baixa no que foi pago fora do site, configura locais e regras e resolve problemas de login do sócio no site.

O menu **Reservas** tem as telas Novo Agendamento, Todos os Agendamentos, Espaços Sociais, Pagamentos, Contas do site e Locais e Grupos. Cada uma só aparece para quem tem a permissão correspondente.

## Quem faz o quê

Quem libera é a TI ou a Gerência, na tela **Setores** (ou só para uma pessoa, na tela **Usuários**); o coordenador do setor pede a liberação e coloca a equipe no setor pela tela **Meu setor**. A permissão é dada ao setor, e todos do setor passam a ter o acesso.

| Para | Permissão (como aparece na tela Setores) |
|---|---|
| Ver a agenda, lançar reserva de quadra no balcão, ver e cancelar agendamentos | Reservas › Agendamentos (criar, ver e editar) |
| Ver a lista de pagamentos e consultar na Rede | Reservas › Pagamentos |
| Estornar pagamento (quadra ou espaço social) | Reservas › Estornar pagamento |
| Marcar como pago o que foi recebido fora do site | Reservas › Baixa manual de pagamento (pago fora da plataforma) |
| Criar e editar grupos, locais, regras de locação e o catálogo de bebidas e mobiliário | Reservas › Locais, grupos e regras de reserva |
| Consultar espaços sociais, lançar pré-reserva e cuidar da lista de convidados | Reservas › Espaços sociais: consultar, pré-reservar e lista de convidados |
| Marcar contrato ok, aprovar com desconto, recusar e cancelar reserva social | Reservas › Espaços sociais: contrato, aprovar, recusar e cancelar |
| Corrigir conta do sócio no site, gerar senha provisória, cadastrar conta e resolver duplicadas | Reservas › Contas do site: corrigir dados, senha provisória, cadastro manual e duplicadas |

Quem recebe o aviso no sino quando chega uma pré-reserva de espaço social pelo site é quem tem a permissão de espaços sociais pelo setor (ou dada individualmente). Gerência, Diretoria e TI, que têm acesso total, não recebem esse aviso.

## Como ler a agenda do dia
<!-- telas: schedule.index -->

A tela **Reservas › Novo Agendamento** mostra a agenda de um dia, agrupada por modalidade (cada grupo de locais). Cada local aparece num cartão com o preço por horário e os horários do dia.

- **Trocar o dia:** use as setas ao lado da data (dia anterior e próximo dia) ou clique na data e escolha no calendário.
- **Buscar:** a barra de busca filtra por local, modalidade ou nome do sócio já agendado.
- **Cores e textos dos horários:** **Livre** (borda tracejada), **Selecionado** (grená), **Confirmado** (verde, com o nome do sócio), **Pendente** (amarelo, com o tempo que está esperando o pagamento), **Bloqueado** (cadeado, com o nome da regra que bloqueou) e **Já passou**.
- Horário que já começou e ainda pode ser vendido aparece com "Em andamento · resta N min" e o valor proporcional.
- Clicar num horário ocupado abre os detalhes daquele agendamento.
- **PDF do dia:** o botão gera o relatório de ocupação do dia (horário, estado e sócio de cada local) para imprimir.
- **Configurações:** leva a Locais e Grupos (só para quem tem a permissão de configurar).

Local com o selo "Indisponível nesta data" não tem horário à venda naquele dia. Veja os motivos em Dúvidas comuns.

Espaços sociais (churrasqueiras, salões) não aparecem nesta agenda: eles têm tela própria, em Reservas › Espaços Sociais.

## Agendar uma quadra para um sócio no balcão
<!-- telas: schedule.index -->

Na tela **Reservas › Novo Agendamento**:

1. Escolha o dia nas setas ou no calendário do topo.
2. No cartão do local, clique nos horários **Livres** que o sócio quer. Pode marcar mais de um, mas só de um local por vez (marcar em outro local limpa a seleção).
3. Abaixo dos horários aparece o campo **Sócio (título ou nome)**. Digite a **matrícula** do sócio (mínimo de 5 caracteres). A lista "Pessoas encontradas" mostra todos da matrícula, com a marcação Titular ou Dependente.
4. Clique na pessoa certa. Ela aparece como "Sócio selecionado"; o **X** troca o sócio.
5. Confira o resumo "Reserva para:" (horários e total) e clique em **Confirmar reserva**.

A tela mostra "Agendamento criado com sucesso!". A reserva feita no balcão já nasce **confirmada**, e o sócio recebe o e-mail de confirmação.

Lançar a reserva no balcão **não registra pagamento**. Se o sócio pagou na secretaria (dinheiro, Pix direto ao clube, maquininha), abra o agendamento e use a **Baixa manual de pagamento** (veja a seção sobre baixa manual).

O valor é sempre recalculado pelo sistema na hora de gravar: horário já começado é cobrado proporcionalmente ao tempo que falta.

## Como funcionam as reservas de quadra feitas pelo site

O sócio reserva quadras pelo **site do clube**, entrando com CPF e senha. Do lado de quem atende no Lara, o que acontece é:

1. Ao escolher os horários, a reserva nasce **Pendente** (aguardando pagamento). Na agenda, o horário fica amarelo com um cronômetro, e o sócio recebe um e-mail com o prazo para pagar.
2. O sócio tem **10 minutos** para pagar no cartão, pelo site (o pagamento é processado pela Rede).
3. Pagou: a reserva vira **Confirmada** e o sócio recebe o e-mail de confirmação com os dados do pagamento.
4. Não pagou em 10 minutos: a reserva **expira** sozinha e o horário volta a ficar livre. Ela continua visível em Todos os Agendamentos com o status Expirada.

Regras que o site aplica e que explicam reclamações comuns:

- O horário só aparece nos dias da semana do grupo e dentro da antecedência configurada.
- Para o próprio dia, a venda respeita a janela de vendas do grupo (horário de início e fim de venda).
- Cada grupo tem um **limite diário** de reservas por sócio.
- Horário em andamento continua à venda, com preço proporcional, até 10 minutos antes do fim.
- O sócio não consegue reservar dois horários que se sobrepõem.

A reserva confirmada também acende a luz da quadra automaticamente, se a quadra tiver o interruptor vinculado (veja o guia do Home Assistant).

## Encontrar um agendamento
<!-- telas: schedule.list -->

A tela **Reservas › Todos os Agendamentos** lista as reservas em qualquer status, inclusive canceladas e expiradas.

- **Buscar sócio:** digite o nome ou o CPF.
- **Filtros:** status (Todos os status, Confirmado, Pendente, Cancelado…), local (Todos os locais) e período (data inicial e final).
- Cada linha mostra o número da reserva, o sócio, o local, a data e o horário, o status (Confirmada, Pendente, Cancelada, Expirada) e o valor.
- **Detalhes** abre a tela **Gestão de Reserva** daquele agendamento.

Na Gestão de Reserva você vê:

- o sócio, a matrícula e o e-mail de contato;
- o histórico: **Criado por** (nome do funcionário ou "Via Site"), a última atualização e, se cancelada, quem cancelou, quando e o motivo;
- os **Agendamentos Vinculados**: o mesmo sócio no mesmo dia, para alterar vários de uma vez;
- o botão **Ver Pagamento Vinculado**, quando há pagamento (para quem tem a permissão de pagamentos);
- a área de cancelamento e a de baixa manual de pagamento.

Se o agendamento é a ocupação de um espaço social (o dia inteiro), a tela avisa "Reserva de espaço social" e oferece **Abrir reserva social**: tudo dessa reserva é feito pela tela de Espaços Sociais.

## Cancelar um agendamento de quadra
<!-- telas: schedule.list -->

Abra o agendamento em **Reservas › Todos os Agendamentos › Detalhes** (ou clique no horário ocupado na agenda de Novo Agendamento).

1. Em **Agendamentos Vinculados**, a reserva aberta já vem marcada. Marque também os outros horários do mesmo sócio no mesmo dia, se quiser cancelar todos juntos.
2. Em **Operação Desejada**, escolha "Cancelar Agendamento(s)".
3. Preencha o **Motivo do Cancelamento** (obrigatório; vai no e-mail ao sócio).
4. Marque **Confirmo que desejo cancelar permanentemente**.
5. Se a reserva foi feita e paga pelo site, aparece **Solicitar estorno do valor pago (Agendamento via Site)**. Marque para devolver o valor no cartão do sócio pela Rede.
6. Clique em **Salvar Alterações**.

O horário volta a ficar livre e o sócio recebe o e-mail de cancelamento com o motivo e, se houve estorno, o valor devolvido. Se o estorno falhar, o cancelamento continua valendo e o e-mail não promete devolução.

Reserva paga por **baixa manual** não tem estorno pela Rede: a opção não aparece, e a devolução, se houver, é feita fora do Lara.

Agendamento já cancelado não pode ser alterado. Reserva de espaço social não se cancela aqui: use a tela da reserva social.

## Dar baixa manual em pagamento feito fora do site
<!-- telas: schedule.list, schedule.index -->

Use a baixa manual quando o sócio pagou uma reserva de quadra **fora do site**: dinheiro, Pix direto ao clube, cartão na maquininha da secretaria ou transferência. Precisa da permissão de baixa manual.

1. Abra o agendamento (Reservas › Todos os Agendamentos › Detalhes, ou clique no horário na agenda).
2. Na área **Baixa manual de pagamento**, o agendamento aberto já vem marcado. Marque os outros horários do mesmo sócio no mesmo dia que ainda não têm pagamento, se for o caso.
3. Confira o **Valor recebido (R$)**: começa pela soma dos agendamentos marcados e pode ser ajustado.
4. Escolha a **Forma de pagamento**, informe **Pago em** (data e hora; não pode ser no futuro) e, se quiser, o **Comprovante / observação** (ex.: ID do Pix, número do recibo).
5. Clique em **Marcar como pago** e confirme.

O que acontece:

- É criado um pagamento marcado como "Baixa manual", com o nome de quem registrou. Ele aparece em Reservas › Pagamentos.
- Agendamento **pendente** passa a **confirmado** e o sócio recebe o e-mail de confirmação. Agendamento já confirmado (lançado no balcão) só ganha o pagamento.

A baixa é recusada para agendamento que já tem pagamento, que está cancelado ou expirado, ou quando os agendamentos marcados são de sócios diferentes.

## Consultar pagamentos e fazer estorno
<!-- telas: payment.index -->

A tela **Reservas › Pagamentos** lista os pagamentos das reservas: os feitos no site (cartão, pela Rede) e as baixas manuais.

- **Buscar sócio** por nome ou CPF.
- **Filtros:** status e método (Cartão de crédito, Cartão de débito, "Baixa manual (todas)" ou uma forma de baixa manual específica).
- A lista mostra método, data do pagamento, status, valor pago e valor estornado. **Detalhes** abre o pagamento.

Na tela do pagamento:

- Aparecem o valor pago, o método, a data, o ID da transação na Rede e os **Agendamentos Vinculados**.
- **Consultar transação na Rede** busca na hora os dados do cartão (portador, final do cartão, status, NSU, TID). Não existe para baixa manual.
- **Estornar Pagamento** (só para quem tem a permissão de estorno): informe o valor ou deixe em branco para estornar todo o valor disponível, clique em **Estornar** e confirme. O estorno pode ser parcial; a tela mostra quanto ainda está disponível.

Baixa manual não tem estorno pela Rede: a tela avisa "Baixa manual não tem estorno pela Rede. Se houver devolução, ela é feita fora do Lara." Pagamento totalmente estornado mostra "Pagamento já totalmente estornado".

O estorno por esta tela devolve o dinheiro, mas **não cancela** a reserva. Para cancelar a reserva e estornar juntos, use o cancelamento no agendamento (quadra) ou na reserva social.

## Como funciona a reserva de espaço social
<!-- telas: social-booking.index -->

Churrasqueiras e salões são reservados **pelo dia inteiro** e passam por análise da equipe. As reservas ficam em **Reservas › Espaços Sociais**. O caminho de uma reserva é:

1. **Pré-reserva** — o sócio pede pelo site do clube, ou a secretaria lança no balcão. A data fica presa para ele enquanto a equipe analisa (situação "em análise").
2. **Contato** — pedido feito pelo site abre um prazo de **2 dias úteis** para a equipe falar com o sócio. Quem atende recebe um aviso no sino.
3. **Contrato ok** — o contrato é feito e assinado **fora do Lara**; a equipe marca "Contrato ok" (com anexo opcional).
4. **Aprovação** — a equipe aprova (com desconto, se for o caso) ou recusa. Aprovar fixa o valor e libera o pagamento no site, com prazo.
5. **Pagamento** — o sócio paga pelo site. Pago, a reserva fica **confirmada** e os convidados não sócios passam a constar na portaria. Pagou fora do site? A equipe dá baixa manual.
6. Sem pagamento no prazo, a reserva **expira** e a data volta a ficar livre.

Na lista de Espaços Sociais, os atalhos no topo mostram quantas estão "com contato atrasado", "aguardando contato", "em análise" e "aguardando pagamento". Clique num deles para filtrar. Também dá para buscar o sócio por nome ou CPF e filtrar por situação, espaço e período do evento. **Abrir** mostra a reserva.

A cada mudança (pré-reserva recebida, aprovada, recusada, confirmada, expirada, cancelada) o sócio recebe um e-mail.

## Lançar uma pré-reserva de espaço social no balcão
<!-- telas: social-booking.index -->

Em **Reservas › Espaços Sociais**, clique em **Nova pré-reserva**.

1. **Espaço e data:** escolha o espaço. O calendário do mês mostra cada dia como **Livre**, **Reservado**, **Bloqueado** ou **Fora da janela**. Use ‹ e › para trocar de mês e clique num dia livre.
2. Se o espaço tem duração de evento definida, informe o **Horário de início**; a tela mostra a que horas termina.
3. **Sócio:** digite a **Matrícula** (mínimo 5 dígitos) e clique na pessoa certa.
4. **Evento:** **Tipo de evento** (obrigatório, ex.: Aniversário), **Pessoas (total)** e **Não sócios extras** (convidados além dos incluídos no preço, cobrados por pessoa).
5. **Observações**, se houver.
6. **Bebidas, mesas e cadeiras:** informe as quantidades dos itens adicionais. A tela mostra quantas mesas e cadeiras o espaço já inclui e o total.
7. **Convidados não sócios:** opcional agora. Use **+ Convidado** para incluir nome e CPF; a lista pode ser completada depois.
8. Confira o **Resumo (estimado)** e clique em **Registrar pré-reserva**.

A tela mostra "Pré-reserva registrada. Falta o contrato e a aprovação." e abre a reserva. O sócio recebe o e-mail de pré-reserva recebida. Pré-reserva lançada no balcão já conta como contato feito.

O valor do resumo é uma estimativa: o sistema recalcula ao gravar. Desconto só é dado na aprovação.

## Fechar a reserva de espaço social de uma vez no balcão
<!-- telas: social-booking.index -->

Quando o sócio já assinou o contrato na secretaria, dá para fazer tudo num envio só. Na tela **Reservas › Espaços Sociais › Nova pré-reserva**, quem tem a permissão de aprovar espaços sociais vê a opção **Fechar a reserva agora**.

1. Preencha espaço, data, sócio e evento normalmente.
2. Marque **Fechar a reserva agora**.
3. Anexe o **Contrato assinado (opcional)** — PDF, JPG ou PNG.
4. Se houver desconto, informe **Desconto (R$)** e o **Motivo do desconto**.
5. Se o sócio já pagou fora do site e você tem a permissão de baixa manual, marque **O sócio já pagou, fora da plataforma** e informe a **Forma de pagamento**, **Pago em** e o **Comprovante / observação**.
6. O botão muda conforme o caso: **Aprovar e enviar para pagamento** ou **Registrar reserva paga**.

Resultado:

- Sem pagamento: a reserva sai aprovada, aguardando pagamento, e o pagamento aparece no perfil do sócio no site, com o prazo do grupo.
- Com pagamento: a reserva sai confirmada e os convidados ficam liberados na portaria.
- Desconto igual ao total confirma na hora, sem cobrança.

É tudo ou nada: se algum passo for recusado (data ocupada, desconto inválido, limite do sócio), nada fica gravado. O sócio recebe um e-mail só, do resultado final.

## Analisar, aprovar ou recusar uma pré-reserva de espaço social
<!-- telas: social-booking.index -->

Abra a reserva em **Reservas › Espaços Sociais › Abrir**. Estas ações pedem a permissão de contrato, aprovação, recusa e cancelamento.

1. **Contrato:** depois que o contrato estiver assinado (fora do Lara), anexe o arquivo se quiser (PDF ou imagem) e clique em **Contrato ok**. Com o contrato marcado, o botão passa a ser **Trocar anexo**, e **Baixar anexo** abre o arquivo.
2. **Aprovar:** informe o **Desconto (R$)**, se houver, e o **Motivo do desconto** (obrigatório quando há desconto). Clique em **Aprovar e liberar pagamento**. O sócio recebe o e-mail com o valor e o prazo para pagar, que é o prazo do grupo (padrão de 7 dias) e nunca passa da véspera do evento.
3. **Recusar:** escreva o motivo (vai no e-mail ao sócio) e clique em **Recusar pré-reserva**. A data volta a ficar livre.

O botão de aprovar fica apagado enquanto o contrato não está ok.

**Editar a pré-reserva:** enquanto ela está em análise e antes do contrato ok, a área **Editar pré-reserva (evento, extras e bebidas)** permite mudar horário de início, tipo de evento, pessoas, extras, observações, bebidas e mobiliário. Data e espaço não mudam: para isso, cancele e faça outra pré-reserva. Depois do contrato ok, nada mais muda — nem pelo Lara, nem pelo site.

## Registrar o contato com o sócio na reserva social
<!-- telas: social-booking.index -->

Toda pré-reserva de espaço social feita **pelo site** abre um prazo para a equipe falar com o sócio: **2 dias úteis** (segunda a sexta), contados a partir do dia seguinte ao pedido, vencendo às 23:59. Pedido feito na sexta vence na terça.

- Quem tem a permissão de espaços sociais pelo setor recebe um aviso no **sino** quando a pré-reserva chega, e outro, uma vez só, se o prazo vencer sem contato.
- Na lista de **Reservas › Espaços Sociais**, cada reserva mostra "contatar até…" ou "contato atrasado", e os atalhos do topo filtram as que esperam contato.

Para registrar:

1. Abra a reserva.
2. Na área **Contato com o sócio**, escreva como foi o contato (opcional).
3. Clique em **Registrar contato**.

A tela passa a mostrar quando e por quem o contato foi feito. **Contrato ok**, aprovação e recusa também contam como contato. Pré-reserva lançada no balcão já nasce com o contato feito.

## Lista de convidados e portaria do espaço social
<!-- telas: social-booking.index -->

Na reserva (**Reservas › Espaços Sociais › Abrir**), a área **Convidados não sócios** mostra quantos já estão na lista e o limite (incluídos no preço + extras contratados).

- Para editar: **+ Convidado** inclui uma linha com nome e CPF; o X remove. Clique em **Salvar lista**.
- Cada convidado precisa de CPF válido, sem repetir. Sócio não entra na lista: ele entra com a carteirinha.
- A equipe edita a lista até o dia do evento. Pelo site, o sócio edita até o fim da véspera; depois disso, só a secretaria.
- **Lista para a portaria** gera o PDF com nome e CPF completo de cada convidado.

Com a reserva **confirmada** (paga), cada convidado ganha uma **liberação pontual** na data do evento no Monitor de Acesso, válida para uma entrada. Qualquer mudança na lista atualiza a portaria: quem saiu perde a liberação (se ainda não usou) e quem entrou ganha uma.

## Baixa manual e cancelamento de reserva social
<!-- telas: social-booking.index -->

Na reserva (**Reservas › Espaços Sociais › Abrir**):

**Baixa manual de pagamento** — aparece quando a reserva está aprovada, aguardando pagamento, e você tem a permissão de baixa manual. Serve para o que o sócio pagou fora do site. Informe a **Forma de pagamento**, **Pago em** e o **Comprovante / observação** e clique em **Marcar como pago**. A baixa é sempre pelo **total aprovado**: a reserva é confirmada, os convidados são liberados na portaria, o sócio recebe o e-mail e o site deixa de cobrar. Reserva ainda em análise não recebe baixa: aprove antes, para fixar o valor.

**Cancelar** — disponível em qualquer estágio ativo, para quem tem a permissão de aprovar espaços sociais. Escreva o motivo (vai no e-mail ao sócio). Se a reserva foi paga pelo site, aparece **Estornar (R$)** com o valor disponível: informe quanto devolver (0 cancela sem devolver). Estornar pede também a permissão de estorno de pagamentos. Clique em **Cancelar reserva**.

Se a Rede recusar o estorno, o cancelamento fica gravado mesmo assim e a tela mostra o erro. Reserva paga por baixa manual não tem estorno pela Rede: a devolução, se houver, é feita fora do Lara.

O cancelamento genérico de Todos os Agendamentos não vale para reserva social: use sempre esta tela.

## Cadastrar bebidas, mesas e cadeiras do espaço social
<!-- telas: social-booking.index -->

Em **Reservas › Espaços Sociais**, o botão **Bebidas** abre a tela **Bebidas e mobiliário** (só para quem tem a permissão de configurar locais, grupos e regras). É o catálogo do que o sócio pode somar à locação.

Para incluir um item, preencha em **Novo item**:

- **Nome** (ex.: Jogo completo (mesa + 4 cadeiras));
- **Categoria**: Bebida ou Mesas e cadeiras;
- **Unidade** (ex.: fardo 12 un.);
- **Código MV**, digitado à mão para quem lança a venda no MV;
- **Preço (R$)**;
- no mobiliário, **Mesas por unidade** e **Cadeiras por unidade** (uma mesa avulsa = 1 e 0; um jogo completo = 1 e 4);
- **Máximo por reserva** (vazio = sem limite);
- **Disponível para novas reservas**.

Clique em **Adicionar**. Na lista, **Editar** carrega o item no formulário (salve com **Salvar**) e **Remover** tira do catálogo.

Mudar preço, quantidades ou desativar um item **não altera** reservas já feitas: cada reserva guarda a cópia do item como estava.

## Configurar um grupo de locais (modalidade)
<!-- telas: place-group.index -->

A tela **Reservas › Locais e Grupos** mostra os grupos em cartões (nome, tipo, quantidade de locais e preço a partir de). **Novo grupo** cria um; clicar no cartão abre o grupo, com os dados, os **Locais** e as **Regras de locação**.

No formulário do grupo:

- **Nome do Grupo** e **Tipo de Espaço**: **Esportiva** (reserva por horário, paga na hora) ou **Social** (dia inteiro, com pré-reserva, contrato e aprovação).
- Só no esportivo: **Horário de Agendamento** (Abertura e Fechamento), **Janela de Vendas (App)** (Início e Fim da venda para o próprio dia), **Duração** de cada horário e **Limite Diário** de reservas por sócio.
- **Ant. Mín. (d)** e **Ant. Máx. (d)**: com quantos dias de antecedência o sócio pode reservar. No grupo esportivo, os dois em 0 liberam só o dia de hoje. No social, máxima 0 = sem limite.
- **Disponibilidade Semanal**: os dias em que o grupo funciona.
- Só no social: **Prazo p/ pagar (d)** (padrão 7, contado da aprovação), **Ativas ao mesmo tempo**, **Máx. de reservas** **A cada (dias)** (ex.: 1 a cada 30 dias) e **Regras mostradas ao sócio (app e e-mail)**. Em branco, os limites não se aplicam.
- **Mídia e Design**: **Imagem vertical** e **Imagem horizontal**, por arquivo (JPG, PNG, WEBP ou GIF) ou por link (Usar link).

Clique em **Enviar** para salvar.

## Cadastrar ou editar um local (quadra, churrasqueira, salão)
<!-- telas: place-group.index -->

Em **Reservas › Locais e Grupos**, abra o grupo e use **Novo local**, ou **Editar** no cartão do local. O lixo exclui o local.

- **Nome do Local**, **Status** (Ativo ou Inativo) e **Preço** (por horário na quadra; diária no espaço social).
- No espaço social: **Capacidade total (pessoas)**, **Não sócios incluídos no preço**, **Não sócios extras (máximo)**, **Preço por convidado extra**, **Mesas incluídas**, **Cadeiras incluídas**, **Duração do evento (horas)** (preenchida, o sócio informa o início), **Início a partir de** / **Início até**, **Itens fornecidos** e **Observações**.
- **Não pode ser usado no mesmo dia que**: marque os locais em conflito (segure Ctrl para marcar mais de um). Vale nos dois sentidos: uma reserva ativa em um ocupa o outro no dia. Serve para espaço combinado (ex.: "Sauna + Tênis") e para salão que ocupa uma quadra.
- Só na quadra: **Switch vinculado** (o interruptor da luz; só quem tem a permissão do Home Assistant altera) e **Permitir que o sócio acenda a luz pelo aplicativo** (autoatendimento de iluminação).
- **Imagem do espaço**, por arquivo ou link.

Clique em **Enviar** (novo) ou **Atualizar** (edição). Espaço social não tem interruptor de luz: a reserva ocupa o dia inteiro e deixaria a luz acesa 24 horas.

## Bloquear ou liberar horários com regras de locação
<!-- telas: place-group.index -->

Para fechar uma quadra em manutenção, num evento ou num feriado, ou para abrir horários extras, use as regras de locação. Em **Reservas › Locais e Grupos**, abra o grupo e clique em **Nova regra**.

1. **Nome da Regra** — aparece no horário bloqueado da agenda (ex.: "Manutenção").
2. **Tipo de Regra**: **Inclusão** (abre horários a mais) ou **Exclusão** (bloqueia os horários).
3. **Período de Vigência**: **Data de Início** (obrigatória) e **Data de Término** (vazia = tempo indeterminado).
4. **Dias da Semana**: marque ao menos um.
5. **Intervalo de Horário (Opcional)**: **Hora de Início** e **Hora de Término**. Sem horário, vale o dia inteiro.
6. **Quadras**: marque ao menos um local do grupo.
7. Clique em **Enviar**.

Na agenda, os horários atingidos por uma exclusão aparecem como **Bloqueado**, com o nome da regra. Para o espaço social, a exclusão deixa o dia como **Bloqueado** no calendário da pré-reserva.

As regras existentes ficam na área **Regras de locação** do grupo, onde podem ser editadas ou removidas. Bloquear não cancela reservas já feitas: confira a agenda e cancele à parte o que for preciso.

## Resolver "esqueci a senha" do site do clube
<!-- telas: site-accounts.index -->

O sócio entra no site do clube com o **CPF** e uma senha. Quando ele esquece a senha, a equipe gera uma senha provisória em **Reservas › Contas do site**.

1. Busque a conta por CPF, matrícula, nome ou e-mail e clique no nome.
2. **Confirme quem é a pessoa** antes de continuar (balcão ou telefone).
3. Na área **Senha**, clique em **Gerar senha provisória** e confirme.
4. A senha aparece **uma vez só**, em destaque ("anote agora — ela não aparece de novo"). São 8 letras e números minúsculos, sem caracteres que se confundem.
5. Passe a senha para a pessoa. Ela entra no site com o CPF e essa senha e troca por uma dela em "Alterar senha".

A senha anterior deixa de valer na hora. Conta desativada não recebe senha: reative antes.

Tudo o que a equipe faz fica na área **Histórico** da conta (quem fez e quando). A senha nunca é guardada no histórico.

## Resolver "meu login não entra" ou "meu e-mail está errado"
<!-- telas: site-accounts.index -->

Em **Reservas › Contas do site**, abra a conta do sócio. A área **Diagnóstico** aponta o que costuma explicar o problema:

- **CPF com mais de uma conta ativa:** o login entra só na conta mais antiga. Se a senha que a pessoa usa é a da outra conta, ela não entra. Desative a conta errada (veja a seção sobre contas duplicadas).
- **Sem sócio de título ativo no MultiClubes:** o site pode recusar reservas. Confira a situação do título no MultiClubes.
- **Matrícula diferente** entre a conta e o MultiClubes.
- **Conta sem e-mail:** os avisos de reserva e de torneio não chegam.
- **Senha no formato antigo:** é convertida no próximo login certo; se a pessoa não lembra, gere uma provisória.
- Uma tabela mostra as diferenças de nome, e-mail e telefone entre a conta e o MultiClubes.

Para corrigir:

- **E-mail e telefone:** edite os campos e clique em **Salvar**.
- **Trazer do MultiClubes:** copia nome, e-mail e telefone do cadastro oficial. O que estiver vazio no MultiClubes não apaga o que está na conta.

## Cadastrar a conta do site para um sócio
<!-- telas: site-accounts.index -->

Quando o sócio não consegue criar a conta pelo site, a equipe cria em **Reservas › Contas do site**.

1. Abra **Cadastrar conta para um sócio**.
2. Informe o **CPF** e a **Matrícula (título)**.
3. Clique em **Cadastrar**.

A pessoa precisa ser sócia de título ativo no MultiClubes: nome, nascimento, e-mail e telefone vêm de lá. O Lara gera uma **senha provisória**, que aparece uma vez na tela da conta; passe para a pessoa, que entra com o CPF e troca a senha no site.

CPF que já tem conta (mesmo desativada) é recusado: abra a conta existente para corrigir.

## Resolver conta duplicada do sócio no site
<!-- telas: site-accounts.index -->

Em **Reservas › Contas do site**, um aviso no topo conta os CPFs com mais de uma conta ativa ("o login entra só na mais antiga"). Clique em **Ver as duplicadas**, ou marque **Só duplicadas** na busca. Na lista, as contas trazem os selos *desativada*, *CPF duplicado* e *sem e-mail*.

Abra uma das contas. A área **Contas com este CPF** mostra cada conta com a data de criação, o e-mail, o que está ligado a ela (reservas, espaços sociais, inscrições em torneios, iluminação) e qual delas **o login usa**.

1. Decida qual conta fica (normalmente a que o login usa e tem as reservas).
2. Abra a conta errada, escreva o **Motivo** e clique em **Desativar esta**.

A conta desativada sai do login, mas reservas e inscrições ligadas a ela continuam guardadas. Só dá para desativar quando o CPF tem outra conta ativa. Para desfazer, abra a conta desativada e clique em **Reativar** — o que só é possível se o CPF não tiver outra conta ativa.

## Dúvidas comuns

### Por que o local aparece "Indisponível nesta data"?
<!-- telas: schedule.index -->

Na agenda de **Reservas › Novo Agendamento**, o local fica sem horários quando:

- o grupo não funciona naquele dia da semana (Disponibilidade Semanal);
- a data está fora da antecedência mínima ou máxima do grupo — no grupo esportivo, antecedência mínima e máxima em 0 liberam só o dia de hoje;
- é hoje e a hora atual está fora da **Janela de Vendas** do grupo;
- todos os horários de hoje já passaram (a venda de cada horário fecha 10 minutos antes do fim).

Ajustes no grupo são feitos em Reservas › Locais e Grupos, por quem tem a permissão de configurar.

### O que significa "Horário colide com outro agendamento."?
<!-- telas: schedule.index -->

Algum dos horários escolhidos foi ocupado entre a hora em que a agenda abriu e a hora da confirmação (por exemplo, um sócio reservou pelo site), ou o sócio escolhido já tem reserva nesse horário. Recarregue a agenda e escolha outro horário. Na grade, um horário ocupado por um espaço vinculado aparece como "Espaço bloqueado por reserva em …" — é o caso de salão ou espaço social marcado como "Não pode ser usado no mesmo dia que" a quadra.

### O que significa "Horário inválido para o local selecionado."?
<!-- telas: schedule.index -->

O horário deixou de estar à venda: passou do limite de venda do dia, saiu da janela de vendas ou foi bloqueado por uma regra de locação. Recarregue a agenda em Reservas › Novo Agendamento e escolha um horário que ainda aparece como Livre.

### Por que o horário custa menos que o preço da quadra?
<!-- telas: schedule.index -->

Horário que já começou continua à venda e é cobrado proporcionalmente ao tempo que falta, minuto a minuto. Exemplo: na quadra de R$ 100 das 20:00 às 21:00, às 20:30 o horário custa R$ 50. Na agenda ele aparece com "Em andamento · resta N min", o valor e a porcentagem. A venda fecha 10 minutos antes do fim.

### Não encontro o sócio na busca da agenda
<!-- telas: schedule.index, social-booking.index -->

A busca procura pela **matrícula** exata do título no MultiClubes, e só começa com 5 caracteres ou mais. Busca por nome não funciona. Se aparecer "Nenhuma pessoa encontrada com esses dados.", confira a matrícula; títulos de alguns tipos não aparecem. Na pré-reserva de espaço social, se o sistema mostrar "Não foi possível identificar o sócio no MultiClubes.", escolha a pessoa de novo na lista.

### A reserva que o sócio fez no site sumiu
<!-- telas: schedule.list -->

Reserva feita no site e não paga em 10 minutos **expira**, e o horário volta a ficar livre. Procure em **Reservas › Todos os Agendamentos** com o status Expirado (ou Todos os status): ela continua lá. Se o sócio diz que pagou, confira em Reservas › Pagamentos.

### Por que não aparece a opção de estorno ao cancelar?
<!-- telas: schedule.list -->

"Solicitar estorno do valor pago (Agendamento via Site)" só aparece quando a reserva foi feita e paga pelo site, com cartão. Não aparece em reserva lançada no balcão, em reserva ainda pendente nem em reserva paga por baixa manual — nesse caso, a devolução é feita fora do Lara.

### Mensagens da baixa manual de pagamento
<!-- telas: schedule.list, social-booking.index -->

- "Este agendamento já tem pagamento registrado." — já existe pagamento (do site ou outra baixa); confira em Ver Pagamento Vinculado.
- "Agendamento cancelado ou expirado não recebe baixa de pagamento."
- "Uma baixa é de um sócio só: os agendamentos escolhidos são de sócios diferentes."
- "A data do pagamento não pode estar no futuro." — corrija o campo Pago em.
- "Escolha a forma em que o pagamento foi recebido."
- "Só a reserva de espaço social aprovada (aguardando pagamento) recebe baixa: aprove antes, para fixar o valor."
- "Reserva de espaço social: o valor da baixa tem de ser o total aprovado."

### Por que o botão "Aprovar e liberar pagamento" está apagado?
<!-- telas: social-booking.index -->

Aprovar depende do contrato. Na reserva social, marque **Contrato ok** primeiro (com ou sem anexo). Se aparecer "Marque o contrato como ok antes de aprovar.", é o mesmo motivo. "O desconto precisa ficar entre zero e o valor da reserva." e "Informe o motivo do desconto." são erros no desconto da aprovação.

### "O contrato já foi fechado: a pré-reserva não aceita mais alterações."
<!-- telas: social-booking.index -->

Depois do Contrato ok, os dados da pré-reserva (evento, extras, bebidas, horário) não mudam mais, nem pelo Lara nem pelo site. Se precisar mudar data ou espaço, ou algo depois do contrato, cancele a reserva e faça outra pré-reserva. A lista de convidados continua editável.

### Por que a data aparece como Reservado, Bloqueado ou Fora da janela?
<!-- telas: social-booking.index -->

No calendário da **Nova pré-reserva**:

- **Reservado** ("Esta data já está reservada.") — já existe reserva ativa no espaço ou num espaço em conflito com ele no mesmo dia.
- **Bloqueado** ("O espaço está bloqueado nesta data.") — há uma regra de exclusão para o espaço.
- **Fora da janela** ("Esta data não está aberta para reserva.") — o grupo não funciona nesse dia da semana ou a data está fora da antecedência mínima e máxima do grupo.

### O sócio atingiu o limite de reservas sociais
<!-- telas: social-booking.index -->

"Você já tem N reserva(s) ativa(s) neste grupo; o limite é N." e "O limite deste grupo é de N reserva(s) a cada X dias." vêm dos limites do grupo social (Ativas ao mesmo tempo e Máx. de reservas a cada X dias). Contam as reservas em análise, aguardando pagamento e confirmadas, inclusive as já realizadas; recusadas, canceladas e expiradas não contam.

### Erros na lista de convidados ou no evento
<!-- telas: social-booking.index -->

- "A lista aceita até N convidado(s) não sócio(s) nesta reserva." — o limite é incluídos + extras contratados. Aumente os extras (enquanto a pré-reserva pode ser editada) ou tire nomes.
- "CPF inválido para o convidado …" e "Há CPF repetido na lista de convidados." — corrija o CPF.
- "A capacidade deste espaço é de N pessoas." — o campo Pessoas (total) passou da capacidade.
- "Este espaço permite até N convidado(s) extra(s)."
- "Informe o horário de início do evento (HH:MM)." e "O evento neste espaço pode começar entre … e …" — o espaço tem duração e faixa de início definidas.
- "Quantidade acima do permitido para …" — o item tem máximo por reserva.

### "Reserva cancelada, mas o estorno não funcionou na Rede"
<!-- telas: social-booking.index, payment.index -->

O cancelamento foi gravado, mas a Rede recusou a devolução. Tire um print da mensagem e envie para a TI. O sócio recebe o e-mail de cancelamento sem promessa de devolução. Em Reservas › Pagamentos, a mensagem equivalente é "O estorno não funcionou. Tire um print desta mensagem e envie para a TI."

### Mensagens de Contas do site
<!-- telas: site-accounts.index -->

- "Este CPF já tem conta no site…: abra a conta para corrigir." — busque pelo CPF e abra a conta existente.
- "Não há sócio de título ativo no MultiClubes com este CPF e esta matrícula." — confira CPF, matrícula e a situação do título.
- "Esta conta está desativada: reative antes de gerar senha."
- "Só dá para desativar quando o CPF tem outra conta ativa: esta é a única, e a pessoa ficaria sem login."
- "O CPF já tem uma conta ativa: desative aquela antes, para não voltar a duplicar." — aparece ao tentar reativar.
- "Não foi possível consultar o MultiClubes agora." — tente de novo mais tarde.

### Por que não vejo uma das telas de Reservas no menu?
Cada tela do módulo depende de uma permissão (veja "Quem faz o quê"). Fale com o coordenador do seu setor: ele coloca você num setor que já tem a permissão (tela **Meu setor**) ou pede à TI ou à Gerência que a liberem (tela **Setores**).
