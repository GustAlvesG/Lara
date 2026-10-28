---
modulo: RePET
palavras: repet, replay, vídeo, clipe, câmera, quadra, logomarca, logo, layout, overlay, gravação, botão da quadra, vertical, horizontal, duração, baixar vídeo, excluir vídeo, patrocínio, marca d'água, reserva
---

# RePET — guia de uso

O RePET é o módulo dos vídeos das quadras. Em cada quadra há uma câmera e um botão: quando alguém aperta o botão, os últimos segundos do jogo viram um clipe. Quem grava, corta e aplica as logomarcas no vídeo é o equipamento das quadras; O Lara decide as regras (formato do vídeo, duração do clipe e quais logomarcas entram) e guarda, mostra e entrega os vídeos.

Quando o clipe foi gravado durante uma reserva paga daquela quadra, ele fica amarrado à reserva e ao sócio. O sócio recebe um único e-mail por reserva, alguns minutos depois do fim do horário, com um botão para entrar no site de locação e ver os vídeos dele. Sem reserva paga, o vídeo existe do mesmo jeito, mas fica só na galeria da quadra.

O módulo é usado principalmente pelo Marketing e pela TI. Tem quatro telas no menu **RePET**: **Configuração de Vídeo**, **Layouts de Logomarca**, **Câmeras** e **Vídeos**.

Todo vídeo é apagado automaticamente 7 dias depois da gravação, sem exceção. O que for usado em campanha precisa ser baixado antes.

## Quem faz o quê

| Para | Permissão (como aparece na tela Setores) |
|---|---|
| Usar as quatro telas do RePET (configuração, layouts, câmeras e vídeos) | RePET › Câmeras, layouts de logomarca e vídeos das quadras |

É uma permissão única: quem a tem vê e altera tudo no módulo. Ela é liberada para o setor na tela **Setores** (menu da conta, no canto superior direito), que fica com a TI e a Gerência; o coordenador do setor pede a liberação e coloca as pessoas da equipe no setor pela tela **Meu setor**. A TI já enxerga o módulo pelo acesso total; o Marketing precisa recebê-la. Sem essa permissão, o grupo RePET não aparece no menu.

## Como escolher se o vídeo sai em pé ou deitado e quantos segundos ele tem
<!-- telas: replay.settings.index -->

Na tela **RePET › Configuração de Vídeo** você define a **Orientação** — **Vertical (9:16)**, bom para celular e stories, ou **Horizontal (16:9)** — e a **Duração (s)** do clipe, de 5 a 60 segundos. O clipe é sempre feito com os segundos **anteriores** ao aperto do botão.

A tela mostra um bloco por esporte, com as quadras dele embaixo.

1. Para valer em todas as quadras de um esporte: no topo do bloco do esporte, escolha a **Orientação** e a **Duração (s)** e clique em **Salvar esporte**.
2. Para uma quadra diferente das outras: na linha da quadra, na coluna **Configuração própria da quadra**, escolha orientação e duração e clique em **Salvar**.
3. Para desfazer a exceção da quadra, clique em **Voltar a herdar** e confirme. Isso não desliga o RePET da quadra; ela só volta a seguir o esporte.

A regra é: a configuração da quadra vence a do esporte; sem nenhuma das duas, vale o padrão (horizontal, 30 segundos). A coluna **Configuração efetiva** mostra o que a câmera está usando de fato e de onde veio: **própria**, **herdado do esporte** ou **padrão do sistema**. A coluna **Câmeras** lista as câmeras da quadra (ou "sem câmera").

Depois de salvar aparece "Configuração salva. As câmeras recebem a mudança na próxima consulta." — ou seja, a mudança chega ao equipamento em pouco tempo, sem precisar mexer na câmera.

## Como colocar a logomarca do clube ou do patrocinador nos vídeos
<!-- telas: replay.layouts.index -->

As logomarcas que aparecem por cima do vídeo são montadas na tela **RePET › Layouts de Logomarca**. Cada layout vale para um esporte inteiro ou para uma quadra específica, e para **uma orientação só** (vertical ou horizontal).

1. Clique em **Novo Layout**.
2. Preencha **Nome do layout** (ex.: "Patrocínio 2026 — Tênis").
3. Em **Aplica-se a**, marque **Esporte (vale para todas as quadras)** ou **Quadra específica** e escolha qual.
4. Escolha a **Orientação**. Ela não pode ser trocada depois; para o outro formato, crie um segundo layout.
5. Clique em **Criar e enviar logomarcas**. O Lara abre o editor do layout.

Se já existia um layout para aquele esporte ou quadra naquela orientação, O Lara avisa "Já existia um layout para este dono nesta orientação — abrimos ele para edição." e abre o que já existe.

O layout da quadra sempre vence o do esporte, a mesma regra da configuração de vídeo. Sem layout, o vídeo sai limpo, sem logomarca, o que também é uma escolha válida.

## Como posicionar as logomarcas no editor de layout
<!-- telas: replay.layouts.index -->

Em **RePET › Layouts de Logomarca**, clique em **Editar** no layout. O editor mostra a **Composição** (o quadro do vídeo, com fundo quadriculado para indicar o que é transparente) e um painel lateral.

1. Em **Nova logomarca**, escolha o arquivo (PNG ou GIF, até 8MB) e clique em **Enviar**. A logo entra no canto superior esquerdo.
2. Arraste a logo para o lugar desejado e use o quadradinho verde no canto para redimensionar.
3. Clique na logo para abrir **Peça selecionada**: ajuste **Opacidade** e **Ordem (frente/trás)** — número maior fica na frente.
4. Clique em **Salvar posições**. O Lara gera a imagem final na hora e mostra "Overlay gerado ...".

Para tirar uma logo, selecione-a e clique em **Remover logomarca**. Em **Overlay publicado** você abre o resultado (**Abrir PNG** e, com GIF animado, **Abrir WebM (animado)**) e pode clicar em **Gerar overlay novamente**. Em **Dados do layout** muda o nome e a caixa **Ativo** — layout inativo é ignorado pelas câmeras. **Salvar dados** grava essas duas coisas.

As posições são guardadas em porcentagem da tela, então o layout continua certo mesmo que a câmera mude de resolução.

## Como cadastrar, desativar ou excluir uma câmera
<!-- telas: replay.cameras.index -->

Na tela **RePET › Câmeras**, no quadro **Nova câmera**:

1. Escolha a **Quadra**.
2. Preencha o **Identificador** — o nome do equipamento no sistema de captura (ex.: "cam-quadra1"). Pegue esse valor com quem instalou a câmera; um identificador inventado deixa a câmera gravando sem conseguir mandar os vídeos ao Lara.
3. Preencha o **Nome** (ex.: "Quadra 1") e, se for campo de futebol, a **Posição** (ex.: "Lado A").
4. Clique em **Cadastrar**.

Cada quadra aceita até 2 câmeras (campo de futebol usa uma por metade), e o identificador não pode se repetir em todo o clube.

Na lista, a coluna **Gravando como** mostra a orientação, a duração e o layout que aquela câmera está usando — é onde se descobre por que uma quadra está gravando em pé, por exemplo. **Último contato** mostra quando a câmera falou com O Lara pela última vez; fica em vermelho depois de uma hora sem contato, sinal de equipamento desligado ou sem rede. "nunca" quer dizer que ela ainda não se comunicou.

Em **Ações**: **Desativar** tira a câmera de uso (ela deixa de receber configuração) e **Ativar** volta. **Excluir** remove a câmera; os vídeos já gravados continuam disponíveis. Não há edição dos dados da câmera: para corrigir identificador, nome ou quadra, exclua e cadastre de novo.

## Como encontrar, baixar ou apagar um vídeo
<!-- telas: replay.videos.index -->

Na tela **RePET › Vídeos** fica a galeria de todos os clipes, com player em cada cartão.

1. Use os filtros **Esporte**, **Quadra**, **Data** e **Reserva** (**Com sócio vinculado** ou **Sem reserva**) e clique em **Filtrar**. **Limpar** tira os filtros.
2. Cada cartão mostra a quadra, o esporte, a câmera, a data e hora da gravação, a duração e o tamanho. Quando o clipe foi feito numa reserva paga, aparece "Reserva de" e o nome do sócio.
3. O selo no canto mostra quantos dias faltam para o vídeo expirar ("1 dia", "expira hoje").
4. **Baixar** salva o arquivo no computador. **Excluir** apaga o vídeo definitivamente, depois de confirmar.

Os vídeos ficam 7 dias, contados da gravação, e então somem sozinhos. Material para campanha precisa ser baixado antes.

## Dúvidas comuns

### O sócio diz que não recebeu o vídeo da reserva. O que aconteceu?
<!-- telas: replay.videos.index -->

Só reserva **paga** vincula o vídeo ao sócio. Reserva pendente de pagamento, cancelada, aula ou quadra livre não vinculam: o vídeo fica só na galeria da quadra. Confira em **RePET › Vídeos**, filtrando a quadra e a data, se o cartão mostra "Reserva de". O e-mail é um só por reserva, enviado cerca de 5 minutos depois do fim do horário, e não traz o vídeo: traz um botão para o login do site de locação. Se o sócio não tem e-mail válido, ele não recebe o aviso, mas os vídeos continuam no site.

### Por que a câmera aparece em vermelho no Último contato?
<!-- telas: replay.cameras.index -->

Em **RePET › Câmeras**, o horário fica vermelho quando a câmera passou mais de uma hora sem falar com O Lara. Normalmente é equipamento desligado, sem rede ou com o identificador errado. Acione quem cuida das câmeras. Se mostrar "nunca", a câmera nunca se comunicou — confira se o **Identificador** é exatamente o que o instalador configurou.

### Apareceu "Esta quadra já tem 2 câmeras — o limite do cadastro."
<!-- telas: replay.cameras.index -->

Cada quadra aceita no máximo 2 câmeras. Para trocar uma câmera, exclua a antiga em **RePET › Câmeras** e cadastre a nova.

### Apareceu "Já existe uma câmera com este identificador — ele é único em todo o clube."
<!-- telas: replay.cameras.index -->

O identificador não pode se repetir, nem em outra quadra. Veja na lista quem já usa esse identificador; se for a mesma câmera mudando de quadra, exclua o cadastro antigo e cadastre de novo.

### A logomarca não sobe ou aparece "Formato não aceito — envie PNG ou GIF."
<!-- telas: replay.layouts.index -->

O editor de layout só aceita **PNG ou GIF, até 8MB**. JPG não serve porque não tem fundo transparente. Outras mensagens possíveis: "Imagem maior que 8MB.", "Arquivo não é uma imagem válida." e "Não foi possível ler a imagem — arquivo corrompido?". Exporte de novo a logo em PNG com fundo transparente e envie outra vez.

### O GIF animado ficou parado no vídeo. Por quê?
<!-- telas: replay.layouts.index -->

Quando a tela **Layouts de Logomarca** mostra o aviso "ffmpeg não encontrado neste servidor.", o servidor não consegue gerar a versão animada: o GIF sai parado, só com o primeiro quadro. O resto do layout funciona normalmente. Peça à TI para resolver o aviso, se a animação for necessária.

### Mudei a logomarca, mas o vídeo ainda sai com a antiga.
<!-- telas: replay.layouts.index -->

Depois de mexer nas logos é preciso clicar em **Salvar posições** no editor. Confira também se o layout está **Ativo** e se existe um layout de quadra (que vence o do esporte) na mesma orientação. A coluna **Gravando como** em **RePET › Câmeras** mostra qual layout cada câmera está usando. Os vídeos já gravados não mudam.

### Como troco a orientação de um layout?
<!-- telas: replay.layouts.index -->

Não dá: a orientação é fixa porque as posições foram desenhadas para aquele formato. Crie outro layout com **Novo Layout** na outra orientação.

### Ao excluir um vídeo apareceu "Servidor de arquivos (FTP) indisponível."
<!-- telas: replay.videos.index -->

O servidor onde os vídeos ficam guardados não respondeu, e por segurança nada foi apagado. Espere alguns instantes e clique em **Excluir** de novo. Se persistir, avise a TI.

### Não vejo o RePET no menu.

O módulo só aparece para quem tem a permissão "Câmeras, layouts de logomarca e vídeos das quadras" (grupo RePET). Fale com o coordenador do seu setor: ele pede à TI ou à Gerência para liberar a permissão ao setor na tela **Setores**, ou a TI dá a permissão só para você em **Usuários**.
