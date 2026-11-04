---
modulo: Contratos
palavras: contrato, contratos, cláusula, minuta, CTL, Gerência, validar, aprovar, devolver, aditivo, biblioteca, modelo de contrato, contratada, fiador, Word, docx, papel timbrado, revisão automática, Claude, assinatura do contrato, tablet da Gerência
---

# Contratos — guia de uso

O módulo Contratos d'O Lara é onde os contratos do clube são escritos, validados e assinados. O setor redige o contrato em cláusulas, a Gerência valida (aprova ou devolve com observações) e, aprovado, o contrato vira um documento de assinatura que é assinado na aba **Assinaturas** do próprio módulo, com o mesmo processo do módulo Assinaturas: tablet do balcão, link por e-mail ou gov.br.

O menu **Contratos** tem três telas: **Contratos** (redigir e validar), **Assinaturas** (os documentos de assinatura dos contratos aprovados) e **Biblioteca** (cláusulas e modelos do setor). O cabeçalho e o rodapé (papel timbrado) e o presidente que assina pelo clube continuam configurados no módulo Assinaturas.

## Quem faz o quê
<!-- telas: contracts.index, contract-signatures.index, contract-library.index -->

Cada tela do menu Contratos depende de uma permissão, liberada pela TI ou pela Gerência na tela **Setores** (grupo **Contratos**). Um item do menu que não aparece para você é falta dessa permissão.

| Para | Permissão (como aparece na tela Setores) |
|---|---|
| Redigir os contratos do seu setor, usar a biblioteca e os modelos no editor | Redigir os contratos do próprio setor (validar é da Gerência) |
| Cadastrar, editar e excluir cláusulas e modelos da biblioteca do setor | Biblioteca de cláusulas e modelos do setor: cadastrar, editar e excluir |
| Conduzir a assinatura dos contratos do setor (liberar no tablet, link, gov.br) | Assinaturas: conduzir a assinatura dos documentos dos contratos do setor (tablet, link, gov.br) |

Validar contratos não é uma permissão: é ser do setor **Gerência**, que vê os contratos de todos os setores. Cada setor vê só os próprios contratos, a própria biblioteca e as assinaturas dos próprios contratos.

## Redigir um contrato e enviar à Gerência
<!-- telas: contracts.index -->

1. Em **Contratos › Contratos**, clique em **Novo contrato**. Escolha o **Setor** (só esse setor e a Gerência veem o contrato), o título e, se quiser, um modelo do setor.
2. Preencha: dados gerais (título e por qual CNPJ do clube), contratada (e fiadores, se houver; **Buscar no cadastro** traz as já usadas), variáveis, considerandos (opcional), cláusulas e quem assina. A prévia ao lado mostra o contrato como vai ficar.
3. Clique em **Salvar e enviar à Gerência**. O coordenador da Gerência recebe um e-mail.

O que O Lara faz por você:

- **Numeração automática**: CLÁUSULA PRIMEIRA, 1.1, 1.1.1 não se digitam. Use "Subitem →" para o 1.1.1.
- **Remissão**: para citar outra cláusula, use **Inserir remissão**; o número acompanha se a ordem mudar.
- **Variáveis**: valor, prazo e datas preenchidos uma vez e usados com **Inserir variável**. Valor sai com o extenso.
- **Conferência**: a lista ao lado da prévia diz o que falta (trava o envio): variável sem valor, CNPJ ou CPF inválido, remissão quebrada, cláusula vazia, falta de quem assina pela contratada.
- O clube é representado pelo presidente (o titular da Presidência em **Assinaturas › Diretoria**), que assina sem precisar ser incluído. A data do fecho é a da assinatura, e o documento sai com visto em todas as páginas.
- **Escrever com conforto**: a caixa de texto cresce com o que você digita e pode ser puxada pelo canto de baixo. A barra tem **Limpar** (tira a formatação do trecho), **↶ desfazer** e **↷ refazer**. Ao colar do Word ou de um PDF, ficam só negrito, itálico, sublinhado, parágrafos e listas. Na tela larga, **Ocultar prévia** (no alto) dá a largura toda ao editor; **Mostrar prévia** a traz de volta.

Para o fornecedor revisar, o botão **Word** baixa o contrato em .docx, já no papel timbrado do Clube (o cabeçalho e o rodapé de **Assinaturas › Modelos › Cabeçalho e rodapé**; sem imagem cadastrada, sai o nome do Clube no alto). Mudanças pedidas pelo fornecedor são feitas no editor.

## Revisão automática pelo Claude
<!-- telas: contracts.index -->

O Claude lê o contrato inteiro e aponta inconsistências (partes, valores por extenso, prazos, remissões, cláusulas contraditórias ou faltando), com sugestão de alteração. São sugestões de IA: não aprovam nem devolvem nada, e o texto enviado vai para a empresa do Claude (Anthropic).

- **Enquanto redige**: no editor, clique em **Salvar e analisar**, ao lado da prévia. O Lara salva o contrato e analisa o texto salvo.
- **Na tela do contrato** (Gerência, durante a validação): no cartão **Revisão automática (Claude)**, clique em **Analisar com o Claude** (leva até um minuto). Se o texto mudou, use **Analisar o texto atual**; para pedir de novo com outras escolhas, **Analisar de novo escolhendo outros trechos ou anexando material**.
- **Trechos com dados sensíveis**: os trechos com valores, CPF, documentos ou endereço (qualificação das partes, cláusula do valor…) **não são enviados**, a menos que você marque cada um. O cartão mostra em **Não enviados ao Claude** o que ficou de fora.
- **Anexar trechos ao pedido** (em todo pedido: editor, tela do contrato e tablet): abra **Anexar trechos ao pedido**, dê um título e cole um texto de referência, como a proposta do fornecedor, o contrato anterior ou uma orientação do jurídico. **+ Outro trecho** acrescenta mais (até 5, de até 12.000 caracteres cada). O Claude confere a minuta contra esse material e aponta as divergências. O texto colado também vai à Anthropic: não cole dado que não deva sair. A revisão mostra em **Trechos anexados ao pedido** o que foi junto.
- Volta um resumo e apontamentos por cláusula (grave, atenção, detalhe) com sugestão. Na validação, **Levar para a observação** copia o apontamento para a observação da cláusula.

Pedir de novo sem mudar o texto, os trechos marcados e os trechos anexados devolve a mesma revisão, sem nova análise.

## Validar um contrato (Gerência)
<!-- telas: contracts.index -->

Só o setor Gerência valida contratos. Em **Contratos › Contratos**, a aba **Para validar** mostra os que esperam validação.

1. Abra o contrato. Aparecem o recado de quem enviou, o que mudou desde a última devolução e o contrato inteiro.
2. Para pedir mudança, escreva observações gerais ou em cada cláusula. Se preferir, ajuste o texto direto.
3. Clique em **Devolver com observações** (quem redigiu é avisado por e-mail) ou em **Aprovar**.

Aprovado, o contrato ganha o número (por exemplo **CTL - 12/26**, uma sequência só para todos os setores) e o documento de assinatura é gerado em rascunho, na aba **Contratos › Assinaturas**. O ajuste feito pela Gerência fica no histórico como "Ajustado pela Gerência na validação".

## Validar pelo tablet do balcão (Gerência)
<!-- telas: contracts.index -->

1. Na tela de espera do tablet, toque em **Validação de contratos (Gerência)** e entre com a matrícula e o PIN d'O Lara.
2. Toque num contrato. Para comentar uma cláusula, toque nela e escreva.
3. **Revisão do Claude**: mostra a última revisão (com o aviso se o texto mudou depois) e os apontamentos. **Levar para a observação** copia o apontamento para a cláusula (ou para a observação geral da devolução). Para pedir uma análise, marque os trechos sensíveis que podem ir, anexe trechos de referência se quiser (**+ Outro trecho**) e toque em **Analisar com o Claude**.
4. **Editar texto**: ajuste o título do contrato e, em cada cláusula, o título e os itens. A barra no alto tem negrito, itálico e sublinhado; cada item pode virar **Subitem**, ser tirado (**Tirar**) e a cláusula pode ganhar **+ Item**. Toque em **Salvar texto**. Partes, variáveis, considerandos e signatários só se ajustam no computador. As observações já escritas continuam.
5. Toque em **Devolver** ou **Aprovar**. Toque em **Sair** ao terminar; parado por um tempo, a entrada cai sozinha.

## Assinar um contrato aprovado
<!-- telas: contract-signatures.index -->

Em **Contratos › Assinaturas** ficam os documentos de assinatura dos contratos aprovados do seu setor (a Gerência vê os de todos). O processo é o mesmo do módulo Assinaturas:

1. Abra o documento (ele nasce em **Rascunho**). Confira signatários e anexos e clique em **Congelar** quando estiver tudo certo.
2. Para quem está no balcão, gere o **QR Code** do signatário e a pessoa assina no tablet. Para quem não pode vir, use o **link por e-mail** (assinatura remota) ou a aba **Assinatura gov.br**.
3. O presidente e os diretores assinam no tablet com matrícula e PIN, depois de quem é de fora da diretoria.
4. Assinado por todos, o documento fica **Finalizado** e a via vai por e-mail. Contrato não passa pela revisão interna: a Gerência já validou o texto.

A lista tem a mesma busca e os mesmos filtros de Assinaturas (situação, período, gerados por mim, arquivados). Quem tem a permissão de documentos do módulo Assinaturas também encontra esses documentos lá, com o filtro **Contratos**.

## Contrato devolvido, aprovado ou que precisa mudar
<!-- telas: contracts.index -->

Em **Contratos › Contratos**, abra o contrato:

- **Devolvido**: as observações aparecem em cada cláusula do editor. Ajuste, clique em **Resolvida** em cada observação atendida e envie de novo com **Salvar e enviar à Gerência**. A Gerência vê só o que mudou.
- **Aprovado**: o documento de assinatura aparece em **Contratos › Assinaturas** (o link também está na tela do contrato). Se a geração falhou ou o rascunho foi cancelado, use **Gerar documento de assinatura** na tela do contrato.
- **Mudar depois de aprovado**: **Reabrir para alteração** volta o contrato a rascunho e cancela o rascunho do documento de assinatura; o contrato precisa ser validado de novo. Depois que o documento foi congelado, não reabre: cancele o documento ou faça um aditivo.

Anexos do contrato (a proposta, um projeto) são incluídos na tela do contrato e seguem junto para a assinatura.

## Reaproveitar: biblioteca de cláusulas, modelos e aditivos
<!-- telas: contract-library.index -->

Em **Contratos › Biblioteca** fica o que o seu setor guardou para reaproveitar. Consultar e usar no editor é de quem redige; cadastrar, editar e excluir pede a permissão da biblioteca.

- **Biblioteca de cláusulas**: no editor, **Da biblioteca…** insere uma cópia num contrato. **+ Cláusula** e **Da biblioteca…** ficam no alto e no fim da lista de cláusulas (entra por último); no pé de cada cláusula, **+ Cláusula abaixo** e **Da biblioteca abaixo…** põem a nova logo depois dela, e a numeração se ajusta. Com a permissão da biblioteca, o botão **Salvar na biblioteca** de cada cláusula guarda-a para o setor, e dá para escrever, editar ou remover uma cláusula direto na tela da biblioteca. Mudar ou remover da biblioteca não mexe em contrato já feito.
- **Modelos de contrato**: com a permissão da biblioteca, na tela de um contrato pronto, **Salvar como modelo do setor** guarda título, termos, variáveis (sem valor), considerandos e cláusulas. Ao criar um **Novo contrato**, escolha o modelo. Remover um modelo não muda os contratos feitos com ele.
- **Contratada**: ao salvar, os dados dela ficam guardados. No próximo contrato, use **Buscar no cadastro (contratadas e fiadores já usados)**.
- **Aditivo**: num contrato aprovado, **Criar aditivo** já vem com as partes, as variáveis e os signatários; escreva só as cláusulas do aditivo. O número é o do contrato com A1, A2…

## Dúvidas comuns

### Não vejo o menu Contratos, ou "Você não está em nenhum setor"
<!-- telas: contracts.index -->

O menu exige uma das permissões do grupo Contratos. E o contrato pertence ao setor de quem o redige: sem setor, peça à TI para incluí-lo no seu setor antes de redigir.

### Não vejo a aba Assinaturas, ou o documento do contrato não abre
<!-- telas: contract-signatures.index -->

A aba exige a permissão "Assinaturas: conduzir a assinatura dos documentos dos contratos do setor". E ela só mostra os contratos do seu setor: contrato de outro setor não abre (só a Gerência vê todos).

### Não aparecem os botões Salvar na biblioteca e Salvar como modelo
<!-- telas: contract-library.index -->

Cadastrar, editar e excluir na biblioteca pede a permissão "Biblioteca de cláusulas e modelos do setor". Sem ela, a biblioteca só é consultada e usada no editor.

### O link antigo do contrato (no e-mail) ainda funciona?
<!-- telas: contracts.index -->

Sim. Até 04/11/2026 os contratos ficavam em Assinaturas › Contratos; o endereço antigo leva automaticamente para o novo, em Contratos.

### O tablet não mostra "Revisão do Claude"
<!-- telas: contracts.index -->

A revisão automática não está configurada n'O Lara (falta a chave da API do Claude). Peça à TI.
