---
modulo: Contratos
palavras: contrato, contratos, vigência do contrato, vencimento, contrato vencendo, prazo do contrato, cancelar contrato, arquivar contrato, desarquivar, notificação de contrato, termo aditivo, aditivo de contrato, marcar como aditivo, variável do contrato original, cabeçalho do contrato, prestador, prestadores, cadastro de prestadores, inativar prestador, salvar automático, alguém salvou antes de você, preenchimento, variável, recusar assinatura, presidente recusou, contrato recusado, em assinatura, andamento da assinatura, cláusula, minuta, CTL, Gerência, revisão jurídica, risco jurídico, advogado, jurídico especializado, validar, aprovar, devolver, visto da Gerência, rubrica, aditivo, biblioteca, modelo de contrato, contratada, fiador, Word, docx, papel timbrado, revisão automática, Claude, assinatura do contrato, tablet da Gerência
---

# Contratos — guia de uso

O módulo Contratos d'O Lara é onde os contratos do clube são escritos, validados e assinados. O setor redige o contrato em cláusulas, a Gerência valida (aprova ou devolve com observações) e, aprovado, o contrato vira um documento de assinatura que é assinado na aba **Assinaturas** do próprio módulo, com o mesmo processo do módulo Assinaturas: tablet do balcão, link por e-mail ou gov.br.

O menu **Contratos** tem quatro telas: **Contratos** (redigir, validar e acompanhar a assinatura), **Assinaturas** (os documentos de assinatura dos contratos aprovados), **Biblioteca** (cláusulas e modelos do setor) e **Prestadores** (as empresas e pessoas contratadas). O cabeçalho e o rodapé (papel timbrado) e o presidente que assina pelo clube continuam configurados no módulo Assinaturas.

## Quem faz o quê
<!-- telas: contracts.index, contract-signatures.index, contract-library.index, contract-providers.index -->

Cada tela do menu Contratos depende de uma permissão, liberada pela TI ou pela Gerência na tela **Setores** (grupo **Contratos**). Um item do menu que não aparece para você é falta dessa permissão.

| Para | Permissão (como aparece na tela Setores) |
|---|---|
| Redigir os contratos do seu setor, usar a biblioteca e os modelos no editor | Redigir os contratos do próprio setor (validar é da Gerência) |
| Cadastrar, editar e excluir cláusulas e modelos da biblioteca do setor | Biblioteca de cláusulas e modelos do setor: cadastrar, editar e excluir |
| Conduzir a assinatura dos contratos do setor (liberar no tablet, link, gov.br) | Assinaturas: conduzir a assinatura dos documentos dos contratos do setor (tablet, link, gov.br) |
| Cadastrar, editar e inativar prestadores | A mesma de redigir contratos (quem acessa Contratos cuida do cadastro) |

Validar contratos não é uma permissão: é ser do setor **Gerência**, que vê os contratos de todos os setores. Cada setor vê só os próprios contratos, a própria biblioteca e as assinaturas dos próprios contratos.

## Redigir um contrato e enviar à Gerência
<!-- telas: contracts.index -->

1. Em **Contratos › Contratos**, clique em **Novo contrato**. A tela tem dois passos, como a de um documento comum:
   - **1. Contrato**: o **Setor** (só esse setor e a Gerência veem o contrato), o modelo do setor (se quiser) e o título.
   - **2. Contratado**: **Buscar nos Prestadores** traz quem já está cadastrado; ou escolha **Empresa (CNPJ)** ou **Pessoa física (CPF)** e preencha nome, documento, endereço, e-mail e, na empresa, quem assina por ela (nome e CPF). Clique em **Criar e ir para as cláusulas**.
   O contratado já entra no contrato, fica guardado em **Prestadores** e já aparece em **Quem assina** (na empresa, o representante; na pessoa física, a própria pessoa). O termo do modelo (CONTRATADA, LOCATÁRIO, ANUNCIANTE…) é mantido.
2. O editor abre em passos: **Contratado** (título, CNPJ do clube, contratada e fiadores), **Variáveis**, **Cláusulas** (considerandos e cláusulas) e **Quem assina**. Ele abre direto em **Variáveis**. Os botões no alto e **Voltar**/**Continuar** levam a qualquer passo. O contrato **se salva sozinho** enquanto você digita (o rodapé mostra "Salvo automaticamente às 14:32"); **Salvar** continua lá e volta no mesmo passo. Ao lado ficam três abas: **Prévia** (o contrato como vai ficar; ao digitar numa cláusula, a prévia rola até ela e a destaca), **Conferência** (o que falta corrigir; o número na aba é a quantidade de problemas) e **Claude** (a revisão automática). Na tela larga, o editor e a coluna ao lado rolam cada um por si.
3. Clique em **Salvar e enviar à Gerência**. O coordenador da Gerência recebe um e-mail.

O que O Lara faz por você:

- **Assinaturas no fim do contrato**: cada pessoa aparece com o nome em **maiúsculas** e, embaixo, só o cargo — sem "CONTRATANTE", "CONTRATADA" ou "Testemunha". O cargo do presidente e dos diretores é o da área deles em **Assinaturas › Diretoria** (por exemplo, Presidência), sem "titular" ou "substituto"; o dos demais é o que você escreve em **Cargo / qualidade** no passo **Quem assina** (em branco, sai só o nome e o CPF). Vale na prévia, no Word e no documento de assinatura. As assinaturas saem em **duas colunas** (as partes e, depois, as testemunhas, de duas em duas). A linha de assinatura nunca fica numa folha e o nome na outra: se não couberem juntos, os dois passam para a folha seguinte.
- **Cabeçalho do contrato**: sai sempre "CONTRATO DE PRESTAÇÃO DE SERVIÇO QUE ENTRE SI FAZEM: CLUBE DOS FUNCIONÁRIOS DA CSN E <prestador>", com o número embaixo. O **Título** que você digita é só o nome do contrato nas listas e na busca; ele não aparece no documento assinado nem no Word.

- **Numeração automática**: CLÁUSULA PRIMEIRA, 1.1, 1.1.1 não se digitam. Use "Subitem →" para o 1.1.1.
- **Remissão**: para citar outra cláusula, use **Inserir remissão**; o número acompanha se a ordem mudar.
- **Variáveis**: o que muda de um contrato para outro (prazo, datas, percentuais, quantias) se preenche uma vez no campo **Preenchimento** e entra no texto com **Inserir variável**. Números, percentuais e quantias em reais saem com o extenso. Atalhos:
  - **Variável comum…** traz pronta uma das mais usadas (valor global, valor mensal, número de parcelas, dia do vencimento, prazo de vigência, datas de início e término, multa, índice de reajuste, forma de pagamento, local).
  - Escreva direto na cláusula entre colchetes duplos, como `[[Prazo de vigência]]`: a variável aparece sozinha no passo **Variáveis**, já com o tipo. Se você apagar o marcador sem ter preenchido, ela some.
  - O **Tipo** é escolhido pelo nome enquanto você não troca: "Valor…" vira quantia em reais, "Data…" vira data por extenso, "Prazo…", "Dias", "Parcelas" viram número, "percentual" vira percentual.
  - Embaixo de cada variável aparece onde ela é usada ("Usada em: Cláusula 3") ou o aviso **Ainda não usada no texto**. O quadro **Falta preencher** lista as variáveis vazias; clique no nome para ir até ela.
- **Conferência**: a aba ao lado da prévia diz o que falta (trava o envio): variável sem preenchimento, CNPJ ou CPF inválido, remissão quebrada, cláusula vazia, falta de quem assina pela contratada.
- O clube é representado pelo presidente (o titular da Presidência em **Assinaturas › Diretoria**), que assina sem precisar ser incluído. A data do fecho é a da assinatura, e o documento sai com visto em todas as páginas.
- **Escrever com conforto**: a caixa de texto cresce com o que você digita e pode ser puxada pelo canto de baixo. A barra tem **Limpar** (tira a formatação do trecho), **↶ desfazer** e **↷ refazer**. Ao colar do Word ou de um PDF, ficam só negrito, itálico, sublinhado, parágrafos e listas. Na tela larga, **Ocultar prévia** (no alto) dá a largura toda ao editor; **Mostrar prévia** a traz de volta.

Para o fornecedor revisar, o botão **Word** baixa o contrato em .docx, já no papel timbrado do Clube (o cabeçalho e o rodapé de **Assinaturas › Modelos › Cabeçalho e rodapé**; sem imagem cadastrada, sai o nome do Clube no alto). Mudanças pedidas pelo fornecedor são feitas no editor.

## Revisão automática pelo Claude
<!-- telas: contracts.index -->

O Claude lê o contrato inteiro e aponta inconsistências (partes, valores por extenso, prazos, remissões, cláusulas contraditórias ou faltando), com sugestão de alteração. São sugestões de IA: não aprovam nem devolvem nada, e o texto enviado vai para a empresa do Claude (Anthropic).

- **Enquanto redige**: no editor, abra a aba **Claude** (ao lado da prévia) e clique em **Salvar e analisar**. O Lara salva o contrato e analisa o texto salvo.
- **O resultado se recolhe**: fechado, o cartão mostra só o veredito, quantos apontamentos há e o risco jurídico; clique em **Ver o resultado** para abrir e em **Esconder** para fechar. No editor, a revisão fica aberta na aba **Claude**.
- **Na tela do contrato** (Gerência, durante a validação): no cartão **Revisão automática (Claude)**, clique em **Analisar com o Claude** (leva até um minuto). Se o texto mudou, use **Analisar o texto atual**; para pedir de novo com outras escolhas, **Analisar de novo escolhendo outros trechos ou anexando material**.
- **Trechos com dados sensíveis**: os trechos com valores, CPF, documentos ou endereço (qualificação das partes, cláusula do valor…) **não são enviados**, a menos que você marque cada um. O cartão mostra em **Não enviados ao Claude** o que ficou de fora.
- **Anexar trechos ao pedido** (em todo pedido: editor, tela do contrato e tablet): abra **Anexar trechos ao pedido**, dê um título e cole um texto de referência, como a proposta do fornecedor, o contrato anterior ou uma orientação do jurídico. **+ Outro trecho** acrescenta mais (até 5, de até 12.000 caracteres cada). O Claude confere a minuta contra esse material e aponta as divergências. O texto colado também vai à Anthropic: não cole dado que não deva sair. A revisão mostra em **Trechos anexados ao pedido** o que foi junto.
- Volta um resumo e apontamentos por cláusula (grave, atenção, detalhe) com sugestão. Na validação, **Levar para a observação** copia o apontamento para a observação da cláusula.
- **Revisão jurídica (Gerência)**: quando quem pede é da Gerência (na tela do contrato, no editor ou no tablet), o cartão traz também o bloco **Revisão jurídica**: o nível de risco do contrato para o clube (**baixo**, **médio** ou **alto**), um resumo em linguagem simples e as possíveis fraquezas (multa só contra o clube, rescisão sem saída, falta de garantia, foro, dados pessoais…), cada uma com sugestão e **Levar para a observação**. Em **risco alto** (ou quando o tema pede um advogado), o bloco fica em vermelho com **Procure um jurídico especializado antes de aprovar este contrato.** É orientação automática, não é parecer jurídico, e não impede de aprovar.

Pedir de novo sem mudar o texto, os trechos marcados e os trechos anexados devolve a mesma revisão, sem nova análise.

## Validar um contrato (Gerência)
<!-- telas: contracts.index -->

Só o setor Gerência valida contratos. Em **Contratos › Contratos**, a aba **Para validar** mostra os que esperam validação.

1. Abra o contrato. Aparecem o recado de quem enviou, o que mudou desde a última devolução e o contrato inteiro.
2. Para pedir mudança, escreva observações gerais ou em cada cláusula. Se preferir, ajuste o texto direto.
3. Clique em **Devolver com observações** (quem redigiu é avisado por e-mail) ou, para aprovar, faça o seu **visto** (a rubrica) no quadro **Seu visto** com o mouse e clique em **Aprovar**. Sem o visto, O Lara não aprova; **Limpar** apaga o desenho para refazer. Devolver não pede visto.

O visto da Gerência sai no pé de **todas as páginas** do documento de assinatura, na primeira caixa da faixa de vistos ("Visto — Gerência" e o nome de quem aprovou), ao lado das caixas de quem assina. Ele já aparece no documento antes de qualquer assinatura e fica registrado no comprovante (manifesto) do PDF assinado. Mesmo quando o documento vai para o gov.br e o visto de quem assina é dispensado, o da Gerência continua.

Aprovado, o contrato ganha o número (por exemplo **CTL - 12/26**, uma sequência só para todos os setores) e o documento de assinatura é gerado em rascunho, na aba **Contratos › Assinaturas**. O ajuste feito pela Gerência fica no histórico como "Ajustado pela Gerência na validação".

## Validar pelo tablet do balcão (Gerência)
<!-- telas: contracts.index -->

1. Na tela de espera do tablet, toque em **Validação de contratos (Gerência)** e entre com a matrícula e o PIN d'O Lara.
2. Toque num contrato. Para comentar uma cláusula, toque nela e escreva.
3. **Revisão do Claude**: mostra a última revisão (com o aviso se o texto mudou depois), a **revisão jurídica** (risco, fraquezas e, em risco alto, o aviso para procurar um jurídico especializado; o botão avisa "risco jurídico alto") e os apontamentos. **Levar para a observação** copia o apontamento para a cláusula (ou para a observação geral da devolução). Para pedir uma análise, marque os trechos sensíveis que podem ir, anexe trechos de referência se quiser (**+ Outro trecho**) e toque em **Analisar com o Claude**.
4. **Editar texto**: ajuste o título do contrato e, em cada cláusula, o título e os itens. A barra no alto tem negrito, itálico e sublinhado; cada item pode virar **Subitem**, ser tirado (**Tirar**) e a cláusula pode ganhar **+ Item**. O texto se salva sozinho enquanto você digita (aparece "Salvo automaticamente às…"); **Salvar texto** grava e fecha. Partes, variáveis, considerandos e signatários só se ajustam no computador. As observações já escritas continuam.
5. Toque em **Devolver** ou **Aprovar**. Para aprovar, faça o seu **visto** com o dedo no quadro **Seu visto** (ele vai em todas as páginas do documento) e toque em **Aprovar**. Toque em **Sair** ao terminar; parado por um tempo, a entrada cai sozinha.

## Assinar um contrato aprovado
<!-- telas: contract-signatures.index -->

Em **Contratos › Assinaturas** ficam os documentos de assinatura dos contratos aprovados do seu setor (a Gerência vê os de todos). O processo é o mesmo do módulo Assinaturas:

1. Abra o documento (ele nasce em **Rascunho**). Confira signatários e anexos e clique em **Congelar** quando estiver tudo certo.
2. Para quem está no balcão, gere o **QR Code** do signatário e a pessoa assina no tablet. Para quem não pode vir, use o **link por e-mail** (assinatura remota) ou a aba **Assinatura gov.br**.
3. O presidente e os diretores assinam no tablet com matrícula e PIN, depois de quem é de fora da diretoria.
   Se o presidente ou a contratada **recusar**, o contrato volta para quem redigiu (veja "Recusado na assinatura", abaixo).
4. Assinado por todos, o documento fica **Finalizado** e a via vai por e-mail. Contrato não passa pela revisão interna: a Gerência já validou o texto.

A lista tem a mesma busca e os mesmos filtros de Assinaturas (situação, período, gerados por mim, arquivados). Quem tem a permissão de documentos do módulo Assinaturas também encontra esses documentos lá, com o filtro **Contratos**.

## Acompanhar a assinatura em Contratos
<!-- telas: contracts.index -->

Não é preciso ir até Assinaturas para saber como está a assinatura de um contrato:

- Na lista de **Contratos › Contratos**, a aba **Em assinatura** mostra os contratos aprovados que estão colhendo assinaturas. A coluna **Assinatura** (em todas as abas) mostra a situação do documento, quantos já assinaram (por exemplo **2/4**) e quem falta.
- Na tela do contrato, o cartão **Assinatura** traz o documento, a barra de andamento ("2 de 4 assinaram") e cada signatário com a situação (Pendente, Assinou com a data, Recusou).

## Recusado na assinatura (presidente ou contratada)
<!-- telas: contracts.index -->

Se o presidente ou a contratada não concordar com o texto na hora de assinar, o contrato volta para revisão:

- **Onde se recusa**: no tablet (o presidente, na **Assinatura da Diretoria**, abrindo o documento; a contratada, no balcão ou pelo link recebido por e-mail), com o botão **Recusar** e o motivo. O presidente também pode recusar n'O Lara: em **Contratos**, o contrato que aguarda a assinatura dele aparece na lista; na tela do contrato, o cartão **Aguardando a sua assinatura** pede o **Motivo da recusa** e tem o botão **Recusar e devolver para revisão**.
- **O que acontece**: o documento de assinatura fica encerrado (Recusado), o contrato volta a **Devolvido para ajustes** para quem redigiu, com o motivo como observação ("Recusado na assinatura por …"), e quem redigiu recebe um e-mail. O número do contrato continua o mesmo.
- **Depois**: ajuste o texto, marque a observação como **Resolvida** e envie de novo à Gerência. Aprovado, nasce um **documento de assinatura novo** e as assinaturas recomeçam do zero: quem já tinha assinado assina de novo.
- **Testemunha ou fiador recusou**: o contrato continua aprovado e só o documento fica recusado. Na tela do contrato, use **Gerar documento de assinatura** para colher as assinaturas de novo, ou **Reabrir para alteração** se o texto precisar mudar.

## Contrato devolvido, aprovado ou que precisa mudar
<!-- telas: contracts.index -->

Em **Contratos › Contratos**, abra o contrato:

- **Devolvido**: as observações aparecem em cada cláusula do editor. Ajuste, clique em **Resolvida** em cada observação atendida e envie de novo com **Salvar e enviar à Gerência**. A Gerência vê só o que mudou.
- **Aprovado**: o documento de assinatura aparece em **Contratos › Assinaturas** (o link também está na tela do contrato). Se a geração falhou ou o rascunho foi cancelado, use **Gerar documento de assinatura** na tela do contrato.
- **Mudar depois de aprovado**: **Reabrir para alteração** volta o contrato a rascunho e cancela o rascunho do documento de assinatura; o contrato precisa ser validado de novo. Depois que o documento foi congelado, não reabre: cancele o documento ou faça um aditivo.

Anexos do contrato (a proposta, um projeto) são incluídos na tela do contrato e seguem junto para a assinatura.

## Vigência e aviso de término
<!-- telas: contracts.index -->

- **Onde informar**: no editor, passo **Contratado**, cartão **Dados gerais**: **Vigência (meses a partir da assinatura)** — por exemplo 12 — ou, se preferir, **ou termina em (data fixa)**. A data fixa, se preenchida, vale no lugar do prazo. Em branco, o contrato fica sem vigência e sem aviso.
- **Quando começa**: no dia em que todos terminam de assinar. O Lara calcula o término nessa hora e mostra no cartão **Vigência** da tela do contrato ("Até 05/11/2027 · assinado em 05/11/2026").
- **Contrato já assinado sem vigência** (ou com prazo errado): no cartão **Vigência**, clique em **Informar a vigência** (ou **Mudar a vigência**), preencha e **Salvar vigência**. Só a vigência muda: o texto e o documento assinado ficam como estão.
- **Avisos**: a **60, 30 e 15 dias** do fim, quem redigiu e o pessoal do setor do contrato recebem uma notificação no sino e no navegador ("Contrato perto do fim"). Contrato cancelado ou arquivado não avisa.
- **Na lista**: a aba **Vencendo** mostra os contratos que terminam nos próximos 60 dias e os já vencidos, o mais perto primeiro. Na coluna **Situação** aparece "Vigente até …" (em laranja quando falta pouco, em vermelho quando venceu). Para continuar com o serviço, faça um **aditivo** antes do fim.

## Notificações no navegador
<!-- telas: contracts.index -->

Como nos avisos, as movimentações dos contratos chegam no **sino** do alto da tela e como notificação do navegador (se você permitiu as notificações para O Lara). Clicar abre o contrato.

- **Gerência (todos do setor)**: contrato **enviado para validação**; **recusado na assinatura**, **assinado por todos** e **cancelado**.
- **Quem redigiu**: **devolvido com observações**, **aprovado**, **a Gerência ajustou o texto** durante a validação (um aviso só até você abrir), **recusado na assinatura**, **assinado por todos** e **cancelado**.
- **Quem redigiu e o setor**: **contrato perto do fim** (60, 30 e 15 dias).

Quem fez a movimentação não recebe aviso dela. Os e-mails de sempre (para validar, decisão, recusa, cancelamento) continuam.

## Cancelar ou arquivar um contrato
<!-- telas: contracts.index -->

Na tela do contrato, o cartão **Cancelar ou arquivar** (para o setor do contrato e para a Gerência):

- **Cancelar o contrato**: vale em qualquer fase até a assinatura terminar — rascunho, devolvido, em validação, aprovado ou em assinatura. Escreva o **Motivo do cancelamento** (obrigatório) e confirme. Se o contrato estava em assinatura, o documento de assinatura é cancelado junto: quem ainda não assinou não assina mais. Quem redigiu recebe o motivo por e-mail, e ele fica no **Histórico**. Não dá para desfazer. Contrato **já assinado** não se cancela: para mudar ou encerrar, faça um aditivo.
- **Arquivar**: vale para qualquer contrato (um rascunho esquecido, um cancelado, um assinado que já terminou). Escreva o **Motivo** e clique em **Arquivar**. O contrato sai das abas **Todos**, **Para validar** e **Em assinatura** e passa a aparecer só na aba **Arquivados** da lista. Nada muda nele — a situação, o documento e os aditivos continuam iguais. Para trazê-lo de volta, abra-o pela aba **Arquivados** e clique em **Desarquivar**.

## Reaproveitar: biblioteca de cláusulas, modelos e aditivos
<!-- telas: contract-library.index -->

Em **Contratos › Biblioteca** fica o que o seu setor guardou para reaproveitar. Consultar e usar no editor é de quem redige; cadastrar, editar e excluir cláusulas e modelos pede a permissão da biblioteca.

- **Biblioteca de cláusulas**: no editor, **Da biblioteca…** insere uma cópia num contrato. **+ Cláusula** e **Da biblioteca…** ficam no alto e no fim da lista de cláusulas (entra por último); no pé de cada cláusula, **+ Cláusula abaixo** e **Da biblioteca abaixo…** põem a nova logo depois dela, e a numeração se ajusta. Com a permissão da biblioteca, o botão **Salvar na biblioteca** de cada cláusula guarda-a para o setor, e dá para escrever, editar ou remover uma cláusula direto na tela da biblioteca. Mudar ou remover da biblioteca não mexe em contrato já feito.
- **Modelos de contrato**: com a permissão da biblioteca, na tela de um contrato pronto, **Salvar como modelo do setor** guarda título, termos, variáveis (sem valor), considerandos e cláusulas. Ao criar um **Novo contrato**, escolha o modelo. Remover um modelo não muda os contratos feitos com ele.
- **Editar um modelo**: com a permissão da biblioteca, em **Contratos › Biblioteca**, na lista **Modelos de contrato**, clique em **Editar** ao lado do modelo. Abre o mesmo editor do contrato, com a prévia ao lado. Dá para mudar o nome do modelo, o título, a vigência padrão (em meses), o CNPJ e o termo do clube, as partes (papel, tipo e termo, como CONTRATADA ou LOCATÁRIO), as variáveis (nome e tipo), os considerandos, as cláusulas (inclusive **Da biblioteca…**) e quem assina (o papel, o cargo e, se quiser, o diretor). Nome, CNPJ/CPF e endereço da contratada, o preenchimento das variáveis e os dados de quem assina não ficam no modelo: são preenchidos em cada contrato. O modelo **não salva sozinho**: clique em **Salvar modelo**. Os contratos já feitos com ele não mudam; os próximos saem do modelo editado.
- **Modelos-base do clube**: a partir dos contratos já usados pelo clube, há modelos prontos — obra de engenharia, reforma com medições, serviço pontual (poda, pequenos reparos), mão de obra por diárias, confecção de uniformes, locação de loja ou sala com fiador, publicidade nos telões de LED, permuta, escolinha esportiva, aditivo de contrato feito fora do Lara e **atração musical** (DJ, banda ou cantor num evento do clube: nome artístico, evento, data, horários, valor, data do pagamento e multa). Eles aparecem em **Novo contrato** para os setores em que a TI os instalou. Escolha o modelo, preencha a contratada, as **variáveis** (valores, prazos, datas, dados bancários) e os signatários; a lista de problemas diz o que falta. Apague a cláusula ou o item que não se aplicar ao caso. Na biblioteca também há cláusulas avulsas para inserir quando couber: carência para reforma, aluguel escalonado, comissão de agência, amortização de investimento, ratificação do contrato-mãe, proteção de dados (LGPD), inexistência de vínculo, responsabilidade tributária e assinatura eletrônica. Se a cláusula avulsa usar uma variável (como `[[dias_de_carencia]]`), crie a variável com esse nome no contrato.
- **Contratada**: ao salvar, os dados dela ficam guardados em **Prestadores**. No próximo contrato, use **Buscar nos Prestadores**.
- **Aditivo**: só de contrato **já assinado**. Dá para criar de dois jeitos: na tela do contrato assinado, **Criar aditivo**; ou em **Novo contrato**, escolhendo **Aditivo de contrato assinado** e o contrato na lista. O aditivo já vem com a contratada e os fiadores, o CNPJ do clube, as variáveis e quem assina pela contratada — do último aditivo assinado ou, se não houver, do contrato. Testemunhas e fiador que assina, escolha de novo. Escreva as cláusulas do aditivo. O cabeçalho sai "1º TERMO ADITIVO AO CONTRATO CTL - 12/26 DE PRESTAÇÃO DE SERVIÇO…" e o número é o do contrato com A1, A2…
- **Variável do contrato original**: no aditivo, **Inserir variável** mostra também as do contrato ("Do contrato CTL - 12/26: Valor global"), que entram como `[[original:valor_global]]`. Assim dá para escrever "o valor de [[original:valor_global]] passa a [[valor_global]]".
- **Marcar um contrato como aditivo**: um contrato ainda não aprovado pode virar aditivo de um contrato assinado. Na tela dele, no cartão **Contrato e aditivos**, escolha o contrato e clique em **Marcar como aditivo**. O que você já escreveu fica; entram só a contratada, as variáveis e quem assina pela contratada que estiverem faltando. **Desmarcar como aditivo** volta a ser contrato comum (o que veio do original continua). Depois de aprovado, não muda mais.

## Prestadores
<!-- telas: contract-providers.index -->

Em **Contratos › Prestadores** fica o cadastro das empresas e pessoas que prestam serviço ao clube — as contratadas e os fiadores. É o mesmo cadastro que a busca do contrato usa.

1. **Novo prestador**: escolha **Empresa (CNPJ)** ou **Pessoa física (CPF)** e preencha nome, documento, endereço, telefone, e-mail e, na empresa, o representante (quem assina) e o CPF dele. Clique em **Cadastrar**. Não precisa ter contrato: o prestador já aparece em **Buscar nos Prestadores** ao redigir.
2. **Editar**: clique no nome ou em **Editar**. A tela mostra também os contratos que usam o prestador. Mudar o cadastro **não altera contrato já redigido** — os dados ficam copiados dentro de cada contrato.
3. **Inativar**: prestador não se exclui. **Inativar** tira ele da busca do contrato; **Reativar** traz de volta. O filtro da lista mostra **Ativos**, **Inativos** ou **Todos**.

Cada CNPJ ou CPF só pode estar uma vez no cadastro. Ao salvar um contrato, a contratada e os fiadores dele também são guardados ou atualizados aqui (sem reativar quem foi inativado).

## Dúvidas comuns

### "Alguém salvou este contrato depois que você abriu esta tela"
<!-- telas: contracts.index -->

Outra pessoa (ou você em outra aba) gravou o mesmo contrato enquanto você editava. Para não apagar o que ela gravou, O Lara para de salvar e mostra o aviso no alto do editor. Escolha **Recarregar e ver o que foi salvo** (o que você digitou desde então se perde; copie antes se precisar) ou **Gravar o meu texto por cima** (o que a outra pessoa salvou se perde). No tablet da Gerência, o aviso aparece na janela de edição, com o botão **Gravar o meu texto por cima**.

### A variável apareceu sozinha no passo Variáveis
<!-- telas: contracts.index -->

Você escreveu um marcador entre colchetes duplos (como `[[Prazo de vigência]]`) numa cláusula, e ainda não havia variável com esse nome: O Lara criou. Preencha o **Preenchimento** e confira o **Tipo**. Se foi sem querer, apague o marcador do texto: sem preenchimento, a variável some sozinha.

### O presidente não vê o menu Contratos
<!-- telas: contracts.index -->

O item **Contratos** do menu aparece com a permissão de redigir contratos. Sem ela, o presidente entra pelo endereço de Contratos d'O Lara (/contratos): a lista mostra os contratos que aguardam a assinatura dele, e ele pode ler e recusar. Para ter o item no menu, a TI ou a Gerência libera a permissão em **Setores**.

### Não vejo o menu Contratos, ou "Você não está em nenhum setor"
<!-- telas: contracts.index -->

O menu exige uma das permissões do grupo Contratos. E o contrato pertence ao setor de quem o redige: sem setor, peça à TI para incluí-lo no seu setor antes de redigir.

### Não vejo a aba Assinaturas, ou o documento do contrato não abre
<!-- telas: contract-signatures.index -->

A aba exige a permissão "Assinaturas: conduzir a assinatura dos documentos dos contratos do setor". E ela só mostra os contratos do seu setor: contrato de outro setor não abre (só a Gerência vê todos).

### Não aparecem os modelos-base em Novo contrato
<!-- telas: contracts.index -->

Os modelos são por setor: o **Novo contrato** só mostra os do setor escolhido. Peça à TI para instalar os modelos-base no seu setor.

### Não aparecem os botões Salvar na biblioteca e Salvar como modelo
<!-- telas: contract-library.index -->

Cadastrar, editar e excluir na biblioteca pede a permissão "Biblioteca de cláusulas e modelos do setor". Sem ela, a biblioteca só é consultada e usada no editor. O mesmo vale para o **Editar** dos modelos.

### Editei o modelo, mas o contrato que fiz com ele não mudou
<!-- telas: contract-library.index -->

É assim mesmo: cada contrato guarda a própria cópia do modelo no dia em que foi criado. A edição vale para os contratos criados **depois** dela. Para levar a mudança a um contrato já feito, altere o contrato no editor dele.

### Editei o modelo e saí da tela sem salvar
<!-- telas: contract-library.index -->

Diferente do contrato, o modelo não salva sozinho: só grava quando você clica em **Salvar modelo**. Se o navegador avisar que há alterações não salvas ao sair, volte e salve.

### O link antigo do contrato (no e-mail) ainda funciona?
<!-- telas: contracts.index -->

Sim. Até 04/11/2026 os contratos ficavam em Assinaturas › Contratos; o endereço antigo leva automaticamente para o novo, em Contratos.

### O Lara não deixa aprovar: "Faça o seu visto no quadro"
<!-- telas: contracts.index -->

Aprovar pede o visto (rubrica) de quem aprova, que vai em todas as páginas do documento. Desenhe no quadro **Seu visto**, acima do botão **Aprovar** (no tablet, na janela de aprovação). Um risco curto demais não conta: faça a rubrica inteira.

### A revisão jurídica diz "Procure um jurídico especializado"
<!-- telas: contracts.index -->

O Claude classificou o risco jurídico do contrato como alto, ou viu um tema que pede análise de advogado. Antes de aprovar, leve o contrato ao jurídico (interno ou contratado). A revisão é só orientação: não aprova, não reprova e não substitui o parecer de um advogado. Quem redige não recebe a parte jurídica; ela vem quando a Gerência pede a análise.

### O tablet não mostra "Revisão do Claude"
<!-- telas: contracts.index -->

A revisão automática não está configurada n'O Lara (falta a chave da API do Claude). Peça à TI.
