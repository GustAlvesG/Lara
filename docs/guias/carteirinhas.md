---
modulo: Carteirinhas
palavras: carteirinha, crachá, cartão, cartão PVC, identificação, funcionário, emitir carteirinha, imprimir carteirinha, impressora de cartão, SD260, foto, webcam, câmera, modelo de carteirinha, frente e verso, matrícula, admissão
---

# Carteirinhas — guia de uso

O módulo Carteirinhas d'O Lara emite a carteirinha de identificação de funcionário em cartão PVC, impressa numa impressora de cartões (como a SD260). O operador escolhe um modelo, digita os dados da pessoa, tira a foto pela câmera do computador (ou importa uma foto) e imprime.

São duas telas: **Carteirinhas › Emitir Carteirinha**, usada a cada emissão, e **Carteirinhas › Modelos**, onde se cadastram as artes de frente e verso e a posição de cada informação no cartão.

Importante: O Lara não guarda os dados de quem recebeu a carteirinha. Nome, função, matrícula, admissão e foto ficam só na tela enquanto você emite. Para uma segunda via, digite e fotografe de novo.

## Quem faz o quê

As duas telas usam a mesma permissão. Quem libera é a TI ou a Gerência, na tela **Setores** (ou só para uma pessoa, na tela **Usuários**); o coordenador do setor pede a liberação e coloca a equipe no setor pela tela **Meu setor**.

| Para | Permissão (como aparece na tela Setores) |
|---|---|
| Emitir carteirinhas e cadastrar, editar ou excluir modelos | Emissão e modelos (grupo **Carteirinhas**) |

Sem essa permissão, o menu Carteirinhas não aparece. Não há permissão separada para só emitir: quem emite também pode cadastrar, editar e excluir modelos. Por isso, dê a permissão só a quem opera a impressora de cartões. Para emitir, o computador também precisa de uma câmera (ou de fotos prontas para importar) e da impressora de cartões instalada.

## Emitir uma carteirinha
<!-- telas: id-cards.issue -->

Em **Carteirinhas › Emitir Carteirinha**, a tela tem o formulário de um lado e a prévia do cartão (frente e verso) do outro. A prévia acompanha o que você digita.

1. Em **Modelo**, escolha a arte do cartão. Só aparecem os modelos ativos.
2. Em **Nome**, digite o nome completo, como deve sair no cartão.
3. Em **Função**, digite o cargo (por exemplo "Recepcionista").
4. Preencha **Matrícula** e **Data de admissão**. Elas saem no verso.
5. Em **Foto**, tire a foto pela câmera ou importe um arquivo (veja "Tirar ou importar a foto da carteirinha").
6. Confira a prévia e clique em **Imprimir**. O diálogo de impressão do navegador abre só com o cartão.

O botão **Imprimir** fica desabilitado até haver um modelo escolhido, o nome preenchido e a foto capturada. Enquanto isso, a tela mostra "Selecione um modelo, informe o nome e capture a foto para habilitar a impressão."

Função, matrícula e admissão não são obrigatórias: o que ficar em branco sai em branco no cartão.

Nada do que você digita fica salvo n'O Lara. Ao sair da tela ou recarregar a página, os dados e a foto se perdem.

## Tirar ou importar a foto da carteirinha
<!-- telas: id-cards.issue -->

Na tela **Carteirinhas › Emitir Carteirinha**, o quadro **Foto** oferece dois caminhos.

**Pela câmera do computador:**

1. Clique em **Ativar câmera**. O navegador pode perguntar se O Lara pode usar a câmera: clique em permitir.
2. Posicione a pessoa no quadro e clique em **Tirar foto**.
3. Não gostou? Clique em **Tentar novamente** e repita.

**Por um arquivo:**

1. Clique em **Importar foto** e escolha a imagem no computador (uma foto do celular transferida, por exemplo).
2. Para trocar, clique em **Tentar novamente** e importe outra.

A foto aparece na prévia, no lugar reservado a ela no modelo. Prefira fundo liso e rosto centralizado: a foto é ajustada para preencher a caixa e as bordas podem ser cortadas.

Se aparecer "Não foi possível acessar a câmera. Verifique as permissões.", o navegador bloqueou a câmera ou não há câmera ligada. Libere a permissão de câmera do site no navegador (no ícone ao lado do endereço) ou use **Importar foto**.

## Imprimir no cartão PVC
<!-- telas: id-cards.issue -->

Depois de preencher os dados e a foto em **Carteirinhas › Emitir Carteirinha**, clique em **Imprimir**. O navegador abre o próprio diálogo de impressão, só com o cartão, já no tamanho do cartão (padrão: 54 mm × 85,6 mm, em pé).

No diálogo:

1. Em **Destino**, escolha a impressora de cartões (por exemplo, a SD260). Ela precisa estar instalada no Windows como uma impressora comum.
2. Em **Margens**, escolha **Nenhuma**.
3. Em **Escala**, deixe **Padrão (100%)**.
4. Confirme a impressão. A frente e o verso saem como duas páginas.

Se sobrar um filete branco na borda, é a configuração de impressão de borda a borda da própria impressora (no driver, em preferências de impressão), não d'O Lara. O Lara não se comunica direto com a impressora: tudo passa pelo diálogo do navegador.

Se o cartão sair pequeno, com sobra branca, confira se a escala está em 100% e as margens em "Nenhuma".

## Cadastrar um modelo de carteirinha
<!-- telas: card-templates.index -->

O modelo é a arte do cartão (frente e verso) mais a posição de cada informação. Para criar, vá em **Carteirinhas › Modelos** e clique em **Novo modelo**.

1. Em **Nome do modelo**, dê um nome que o operador reconheça (por exemplo "Funcionário 2026").
2. Deixe marcado **Modelo ativo (aparece na emissão de carteirinhas)** para ele já aparecer em Emitir Carteirinha.
3. Em **Frente**, escolha a imagem da frente; em **Verso**, a do verso. As duas são obrigatórias no cadastro.
4. Posicione as caixas de cada informação sobre as imagens (veja "Posicionar os campos no modelo de carteirinha").
5. Ajuste o estilo dos textos na tabela **Estilo dos campos de texto**.
6. Clique em **Cadastrar modelo**.

Recomendações para as imagens, mostradas na própria tela:

- **Orientação**: vertical (retrato), largura menor que a altura.
- **Proporção**: 54 : 85,6 (largura : altura do cartão).
- **Resolução**: cerca de 638 × 1011 pixels (300 DPI) para impressão nítida.
- **Formato**: PNG ou JPG, até 5 MB por imagem.

Imagens fora dessa proporção são cortadas nas bordas para preencher o cartão. O logotipo, as cores e os textos fixos ("Funcionário", o nome do clube) devem estar na própria arte.

## Posicionar os campos no modelo de carteirinha
<!-- telas: card-templates.index -->

Ao criar ou editar um modelo em **Carteirinhas › Modelos**, as imagens de frente e verso aparecem com caixas tracejadas por cima. Cada caixa é o lugar de uma informação:

- **Frente**: a foto (caixa azul), o **Nome (frente)** e a **Função (frente)**.
- **Verso**: o **Nome (verso)**, a **Data de admissão (verso)** e a **Matrícula (verso)**.

Para ajustar:

1. Arraste a caixa para o lugar certo na arte.
2. Para mudar o tamanho, puxe a bolinha no canto inferior direito da caixa.
3. Na tabela **Estilo dos campos de texto**, ajuste para cada campo de texto o **Tamanho** da letra, **Negrito**, **Alinhamento** (Esquerda, Centro, Direita) e **Cor**.

As seis caixas são obrigatórias: não dá para remover uma. Se uma informação não deve aparecer, deixe o campo em branco na hora de emitir.

Confira o resultado emitindo um cartão de teste em **Carteirinhas › Emitir Carteirinha**: a prévia de lá tem o tamanho real do cartão e mostra como o texto vai sair impresso.

## Editar, desativar ou excluir um modelo de carteirinha
<!-- telas: card-templates.index -->

Em **Carteirinhas › Modelos**, cada modelo aparece num cartão com a frente e o verso, o nome e a situação (**Ativo** ou **Inativo**). A busca no alto filtra pelo nome.

**Editar**: clique em **Editar**, mude o que precisar e clique em **Salvar alterações**. Para trocar só uma das imagens, escolha o arquivo novo daquele lado; o outro lado continua como estava. Ao editar, as imagens não precisam ser enviadas de novo.

**Desativar**: em **Editar**, desmarque **Modelo ativo (aparece na emissão de carteirinhas)** e salve. O modelo continua cadastrado, mas some da lista de Emitir Carteirinha. Use quando a arte saiu de uso, mas pode voltar.

**Excluir**: clique em **Excluir** e confirme. O modelo é apagado de vez. Como O Lara não guarda as carteirinhas emitidas, excluir um modelo não afeta nenhum cartão já impresso, mas não dá para desfazer: na dúvida, prefira desativar.

## Dúvidas comuns

### O botão "Imprimir" está cinza e não clica
<!-- telas: id-cards.issue -->

Falta alguma das três coisas: um **Modelo** escolhido, o **Nome** preenchido ou a **Foto** capturada. A tela avisa: "Selecione um modelo, informe o nome e capture a foto para habilitar a impressão." Depois de tirar a foto, confira se ela aparece no quadro.

### A tela diz "Nenhum modelo de carteirinha ativo"
<!-- telas: id-cards.issue, card-templates.index -->

Não há modelo ativo para emitir. Em **Carteirinhas › Modelos**, cadastre um modelo ou edite um existente e marque **Modelo ativo (aparece na emissão de carteirinhas)**.

### O modelo que eu quero não aparece na lista de Emitir Carteirinha
<!-- telas: card-templates.index -->

Ele está inativo. Em **Carteirinhas › Modelos**, clique em **Editar** no modelo, marque **Modelo ativo** e salve.

### Aparece "Não foi possível acessar a câmera. Verifique as permissões."
<!-- telas: id-cards.issue -->

O navegador bloqueou a câmera, outro programa está usando a câmera, ou não há câmera conectada. Libere a câmera para O Lara no ícone ao lado do endereço do site, feche outros programas que usam a câmera e tente de novo. Como alternativa, use **Importar foto**.

### O cartão saiu pequeno, cortado ou com borda branca
<!-- telas: id-cards.issue -->

No diálogo de impressão, use **Margens: Nenhuma** e **Escala: Padrão (100%)**. Um filete branco que resta é a configuração de borda a borda do driver da impressora: ajuste nas preferências de impressão da impressora no Windows ou chame a TI.

### O texto saiu fora do lugar ou grande demais no cartão
<!-- telas: card-templates.index -->

Ajuste o modelo em **Carteirinhas › Modelos › Editar**: arraste ou redimensione a caixa do campo e diminua o **Tamanho** na tabela de estilo. Nomes longos ocupam mais espaço; deixe a caixa do nome larga o suficiente.

### Ao salvar o modelo, aparece "Posição do campo … está ausente ou inválida"

O posicionamento de uma das seis caixas não foi enviado direito. Recarregue a tela de edição, confira se todas as caixas aparecem sobre as imagens, ajuste de novo e salve. Se repetir, avise a TI.

### A imagem da frente ou do verso foi recusada
<!-- telas: card-templates.index -->

A imagem precisa ser PNG ou JPG e ter até 5 MB. No cadastro de um modelo novo, frente e verso são obrigatórias. Reduza o arquivo (exportando em JPG, por exemplo) e envie de novo.

### Preciso emitir a segunda via de uma carteirinha. Onde estão os dados?
<!-- telas: id-cards.issue -->

O Lara não guarda os dados nem a foto das carteirinhas emitidas. Em **Carteirinhas › Emitir Carteirinha**, preencha os dados e tire a foto de novo.

### Dá para mudar o tamanho do cartão?

O cartão sai no tamanho padrão de cartão de crédito em pé (54 mm × 85,6 mm). As telas não têm campo para mudar o tamanho. Se precisar de outro formato, fale com a TI.
