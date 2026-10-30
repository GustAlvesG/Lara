---
modulo: Assinaturas
palavras: assinatura, assinar, assinatura eletrônica, tablet, balcão, QR Code, termo, contrato, documento, PDF assinado, modelo, Word, docx, gov.br, link por e-mail, assinatura remota, revisão, termo de menores, pulseira, diretoria, diretor, PIN, Gerência, cláusula, anexo, via
---

# Assinaturas — guia de uso

O módulo Assinaturas d'O Lara colhe a assinatura eletrônica de sócios, fornecedores e diretores em termos, fichas e contratos. O caminho de todo dia é o tablet do balcão: o atendente prepara o documento no computador, gera um QR Code, e a pessoa lê, confirma a identidade e assina com o dedo. Quem não pode vir ao clube assina por um link enviado por e-mail ou pela conta gov.br.

O trabalho se divide em duas partes. Os **modelos** (o texto-padrão de cada tipo de documento) são cadastrados uma vez, a partir de um arquivo do Word. Os **documentos** são um modelo preenchido para uma pessoa, e são eles que vão para assinatura. Depois de assinado, o documento vira um PDF com o comprovante (manifesto) e um código de validação.

O módulo também tem o Termo de Menores (autorização de entrada de menor em evento, assinada pelo próprio sócio num tablet de autoatendimento), a assinatura da Diretoria (o diretor entra no tablet com matrícula e PIN) e os Contratos (redigidos em cláusulas, validados pela Gerência e depois enviados para assinatura).

Há um guia ilustrado completo dentro d'O Lara, em **Assinaturas › Guia**, que também pode ser baixado em PDF.

## Quem faz o quê

Cada tela do menu Assinaturas depende de uma permissão. Quem libera é a TI ou a Gerência, na tela **Setores** (ou só para uma pessoa, na tela **Usuários**); o coordenador do setor pede a liberação e coloca a equipe no setor pela tela **Meu setor**. Um item do menu que não aparece para você é falta dessa permissão.

| Para | Permissão (como aparece na tela Setores) |
|---|---|
| Criar documentos, congelar, liberar o QR Code, enviar link remoto, gov.br, cancelar, arquivar | Criar documentos e liberar para assinatura no tablet |
| Só consultar documentos assinados e baixar o PDF | Consultar documentos assinados e baixar o PDF |
| Cadastrar e revisar modelos, cabeçalho e rodapé | Criar e revisar os modelos de documento |
| Ver a foto e o traço de quem assinou | Ver as evidências (foto do signatário e traço) |
| Fazer a revisão interna | Revisar documentos assinados (do dia seguinte em diante, só os que não acompanhou) |
| Revisar antes do prazo e os próprios documentos | Revisar antes do prazo e os próprios documentos (dê só aos coordenadores) |
| Cadastrar o termo de cada evento | Termo de Menores: cadastrar o termo de cada evento |
| Parear o tablet de autoatendimento | Termo de Menores: parear o tablet de autoatendimento |
| Consultar os menores autorizados (pulseira) | Termo de Menores: consultar o histórico de autorizações |
| Cadastrar áreas da diretoria, titulares e substitutos | Diretoria: áreas da diretoria, titulares e substitutos que assinam documentos |
| Redigir contratos do próprio setor, biblioteca e modelos | Contratos: redigir os contratos do próprio setor, biblioteca de cláusulas e modelos (validar é da Gerência) |

Todas ficam no grupo **Assinaturas** da tela Setores. Validar contratos não é uma permissão: é ser do setor Gerência. O item **Guia** aparece para quem tem qualquer uma das permissões de documentos, modelos ou Termo de Menores.

## As situações de um documento
<!-- telas: signature-documents.index -->

Na tela **Assinaturas › Documentos**, cada documento mostra uma situação:

- **Rascunho**: ainda pode ser editado. Não vai para o tablet.
- **Aguardando assinatura**: foi congelado. O texto e os dados não mudam mais, e já pode ser liberado para assinar.
- **Assinado**: todos assinaram. O Lara está montando o PDF final (leva alguns segundos), ou está esperando um anexo obrigatório que ainda não foi enviado. O quadro **Anexos** diz qual falta.
- **Assinado\***: todos assinaram e o PDF assinado está pronto para baixar e enviar, mas falta a revisão interna. Continua assim também se a revisão deu **Com pendência**.
- **Finalizado**: a revisão interna deu **Tudo em ordem**. O Termo de Menores não passa pela revisão e chega aqui direto.
- **Recusado**, **Cancelado** ou **Expirado**: encerrado sem assinatura. A pessoa recusou, o atendente cancelou, ou ninguém assinou dentro do prazo.

Os prazos padrão para assinar, contados do congelamento: 24 horas no balcão, 7 dias depois de preparar para o gov.br, 7 dias quando há diretor entre os signatários. O link remoto vale 72 horas e estende o prazo do documento até lá. A TI pode ter ajustado esses valores.

A regra mais importante: **depois de congelado, o documento não pode ser corrigido**. Para mudar qualquer coisa, cancele e emita um documento novo. Por isso, confira o texto com a pessoa antes de congelar.

## Preparar o arquivo do Word para virar modelo
<!-- telas: signature-templates.index -->

O modelo nasce de um arquivo do Word (.docx). Antes de cadastrar em **Assinaturas › Modelos**, prepare o arquivo assim:

1. Abra o documento no Word. Se ele só existe em PDF ou no formato antigo .doc, use **Arquivo › Salvar como › Documento do Word (.docx)**. O Lara só aceita .docx.
2. Onde entra um dado que muda a cada atendimento, escreva o nome do campo entre colchetes duplos, por exemplo `[[Data do evento]]` ou `[[Valor total]]`. Esse nome é o que o atendente vai ver ao preencher.
3. Onde a pessoa assina, escreva `[[assinatura]]` numa linha só dela. Não escreva nome nem CPF: eles entram sozinhos embaixo da linha.
4. Em documento com mais de uma parte, diga quem assina em cada lugar: `[[assinatura: Contratante]]` e `[[assinatura: Contratado]]`. Dois marcadores em linhas seguidas, ou nas duas células de uma tabela, saem lado a lado.

Dicas que evitam retrabalho:

- Sempre dois colchetes de cada lado. `[Data]` ou `{{Data}}` não são reconhecidos.
- O mesmo campo pode aparecer várias vezes: escreva o nome igual e ele é preenchido uma vez só.
- Não escreva o título no topo: ele é impresso automaticamente.
- Não escreva "R$" antes de um campo de valor: o tipo "Valor em reais" já escreve.
- Imagens, caixas de texto, cabeçalho e rodapé do Word não entram. Para documento com imagens, envie-o pronto em PDF.
- Se houve revisão com "controlar alterações", aceite as alterações antes de enviar.

## Cadastrar um modelo de documento
<!-- telas: signature-templates.index -->

Com o .docx pronto, vá em **Assinaturas › Modelos** e clique em **Novo modelo**.

1. Dê um nome e uma descrição. O nome é o título que sai no documento; a descrição ajuda o atendente a escolher o modelo certo.
2. Clique em **Escolher arquivo .docx** e selecione o Word. Em segundos aparece quantos campos foram encontrados e a prévia **Como o documento vai ficar**: em amarelo o que o atendente preenche, em azul o que a pessoa responde no tablet, em cinza o que o sistema preenche.
3. Confira a lista **Campos do documento**: para cada campo, o **Tipo** (o Lara sugere pelo nome; troque se estiver errado), **Obrigatório** e, se quiser, a **Pergunta no tablet**.
4. Se o documento tem partes, confira **Quem assina**. Uma parte pode ser marcada como **Assinada pela diretoria**, escolhendo a área: todo documento desse modelo já nasce com o titular dela.
5. Escolha as regras de assinatura:
   - **Conferência de identidade**: quatro primeiros dígitos do CPF (o normal), CPF completo (para contratos), código enviado por e-mail (todo signatário precisa ter e-mail) ou sem conferência (a opção mais fraca).
   - **Capturar foto na confirmação**: o tablet fotografa quem assina.
   - **Visto em todas as páginas**: a rubrica vai ao pé de cada página. Use nos contratos.
   - **Prazo de guarda (meses)**: só um registro, nada é apagado sozinho.
   - **Itens da revisão**: o que a revisão interna vai conferir depois.
6. Clique em **Criar modelo**. Ele já fica disponível para o atendimento.

Se a prévia não ficou boa, corrija o Word e escolha o arquivo de novo: os ajustes feitos nos campos que continuam existindo são mantidos. Nada é gravado até clicar em **Criar modelo**.

## Tipos de campo e perguntas para quem assina
<!-- telas: signature-templates.index, signature-documents.index -->

Cada campo do modelo tem um tipo, escolhido em **Assinaturas › Modelos** ao cadastrar ou revisar:

| Tipo | O que se digita | Como sai |
|---|---|---|
| Texto / Texto longo | Qualquer texto | Como foi digitado |
| Número | 150 ou 2,5 | 150 ou 2,5 |
| Valor em reais (R$) | 1.500,00 | R$ 1.500,00 |
| CPF / CNPJ | Com ou sem pontos (é conferido) | 123.456.789-09 |
| E-mail, Telefone, CEP | O dado | Formatado |
| Data abreviada / por extenso | No calendário | 03/10/2026 / 3 de outubro de 2026 |
| Hora | 14:30 | 14:30 |
| Opção única / Múltipla escolha | Uma ou várias opções listadas (uma por linha, no mínimo duas) | A(s) escolhida(s) |
| Sim ou não | Sim / Não | Sim ou Não |
| Data da assinatura — automática | Ninguém digita | A data do dia |

Quem responde cada campo se decide **no documento**, não no modelo. O padrão é o atendente preencher. Para que a própria pessoa responda no tablet (telefone, opções de uso), marque **Perguntar ao signatário** ao lado do campo, no formulário do documento. A pergunta mostrada é a **Pergunta no tablet** do modelo ou, em branco, o nome do campo.

Há campos que a pessoa nunca deve responder sozinha, como valor, número do contrato ou desconto. No modelo, marque **Só o atendente responde** nesse campo. No documento, ele aparece com o aviso "Só o atendente responde (regra do modelo)", sem a opção de perguntar ao signatário, e nem o link remoto o pergunta.

A data automática é a do dia em que a pessoa abre o documento para assinar. Com mais de um signatário, vale a data do primeiro que assinar.

Documento com campo marcado para perguntar ao signatário não pode ir para o gov.br: lá não há perguntas. Para o gov.br, o atendente preenche tudo.

## Revisar um modelo, desativar ou excluir
<!-- telas: signature-templates.index -->

Em **Assinaturas › Modelos**, abra o modelo (**Ver**) e clique em **Revisar**.

1. Envie o Word corrigido em **Escolher arquivo .docx**, ou ajuste só os campos e as regras.
2. Não tem mais o Word original? Clique em **Baixar o texto atual em Word** (ou em **Baixar Word**, na tela do modelo). Edite sem mexer no que está entre colchetes, salve e envie: os campos voltam com o tipo, a pergunta e as opções que já tinham.
3. Clique em **Salvar como versão 2** (o número sobe a cada revisão).

Revisar nunca altera o que já foi emitido: cada revisão cria uma versão nova. Os documentos já gerados continuam com o texto da versão deles; os próximos usam a nova. O histórico de versões fica na tela do modelo, e o link **Word** de cada versão baixa o texto daquela versão.

Um modelo que já gerou documentos não é apagado: ao excluí-lo, ele fica **inativo** e passa para a aba **Inativos** da lista. Modelo sem nenhum documento é excluído de vez.

Na lista de modelos, o botão **Emitir Documento** abre um documento novo já com aquele modelo escolhido.

## Exigir outro modelo junto (modelo vinculado)
<!-- telas: signature-templates.index -->

Use quando quem assina um modelo também precisa assinar outro. Por exemplo: todo contrato de locação leva junto o termo de uso de imagem. O vínculo vale num sentido só: quem assina o contrato assina também o termo, mas um documento só do termo não traz o contrato.

1. Em **Assinaturas › Modelos**, abra o modelo principal e clique em **Revisar** (ou cadastre um novo).
2. Em **Modelos exigidos junto**, escolha o outro modelo e clique em **+ Exigir este modelo**.
3. Em **Campos**, diga para cada campo do outro modelo se ele é o **mesmo campo** de um deste modelo (mesmo tipo, mesmo com nomes diferentes, como "Nome completo" e "Nome") ou um **campo próprio**. Campo ligado é preenchido uma vez só e sai nos dois textos. Campos com o mesmo nome e o mesmo tipo já vêm ligados.
4. Em **Onde assina**, diga para cada parte do outro modelo onde ela assina: **junto com todos que assinam este modelo**, **na parte deste modelo** escolhida, ou como **parte nova**. A parte nova se soma às deste modelo e pede um signatário a mais no documento.
5. Salve. A tela do modelo mostra, nas regras, **Exige também**.

No documento, o texto do outro modelo entra no **mesmo PDF**, em página nova. A pessoa assina uma vez, e a assinatura aparece no lugar de cada texto. Valem as regras mais exigentes dos dois modelos: se um pede foto, há foto; vale a conferência de identidade mais forte; e os anexos e os itens de revisão dos dois se somam.

Revisar o outro modelo depois não muda os documentos já criados. Se ele for desativado, o modelo principal deixa de gerar documento novo até o vínculo sair ou o outro voltar (o aviso aparece ao desativar e ao emitir). Um modelo que só entrou em documentos por vínculo também conta como usado e não pode ser excluído.

## Ajustar o cabeçalho e o rodapé (papel timbrado)
<!-- telas: signature-templates.index -->

O papel timbrado é um só para todos os documentos. Para mudar:

1. Em **Assinaturas › Modelos**, clique em **Cabeçalho e rodapé**.
2. Envie a imagem do cabeçalho e a do rodapé (PNG ou JPG, até 2 MB cada). Informe a altura em milímetros e a posição (esquerda, centro ou direita). Para uma arte de borda a borda, marque **Ocupar a largura inteira do papel**.
3. Se quiser, preencha o **Texto do rodapé**: uma linha com razão social, CNPJ e endereço.
4. Clique em **Salvar papel timbrado** e depois em **Ver exemplo em PDF** para conferir.

A mudança vale para os documentos congelados dali em diante. Os anteriores mantêm o cabeçalho que tinham. O código de validação continua no rodapé de todas as páginas, com qualquer papel timbrado. O logotipo não precisa estar no Word do modelo: ele vem do papel timbrado.

## Emitir um documento para assinatura no balcão
<!-- telas: signature-documents.index -->

Em **Assinaturas › Documentos**, clique em **Novo documento** e escolha o modelo. O formulário vai em passos: **1. Documento**, **2. Signatários**, **3. Dados do documento**, **4. Anexos**. Use **Continuar** e **Voltar**, ou clique no nome do passo, no alto.

1. **Documento**: confira o título.
2. **Signatários**: cada pessoa tem um cartão. Se o modelo tem partes, comece por **Assina como**. Digite o nome, o título ou o CPF em **Buscar associado** e clique no resultado: nome, CPF, e-mail e telefone são preenchidos. Pelo título aparecem o titular e os dependentes. Quem não é associado é digitado à mão. Para uma testemunha ou responsável legal, use **+ Acrescentar signatário** (até cinco). Para diretor, use **Diretor do clube**.
3. **Dados do documento**: preencha os campos (os com * são obrigatórios). **Preencher com os dados dos signatários** copia nome, CPF e contato de quem assina; o que não for preenchido sozinho tem a lista **Usar dados do signatário…**. Marque **Perguntar ao signatário** no campo que a própria pessoa deve responder no tablet.
4. **Anexos**: se quiser pedir um arquivo só deste documento, use **+ Pedir anexo** e marque **Obrigatório** se for o caso.
5. Clique em **Criar rascunho**. A página do documento mostra o texto preenchido. Leia com a pessoa e, se algo estiver errado, clique em **Editar**.
6. Clique em **Congelar e liberar** e confirme. A situação passa a **Aguardando assinatura** e nada mais muda.

O e-mail do signatário é o que permite à pessoa receber a via assinada. Depois de congelar, libere o QR Code (próxima seção), envie o link remoto ou use o gov.br.

Se o modelo exige outro junto, o formulário avisa no alto ("Inclui também, no mesmo documento: …") e já traz os campos, as partes e os anexos dos dois.

## Liberar o QR Code e acompanhar a assinatura no tablet
<!-- telas: signature-documents.index -->

Com o documento congelado, abra-o em **Assinaturas › Documentos**. No quadro **Assinatura no tablet**:

1. Se faltar mais de uma pessoa, escolha em **Quem vai assinar agora** quem está no balcão. A ordem é livre.
2. Clique em **Liberar para assinatura**. Aparece um QR Code com contagem regressiva (5 minutos por padrão). A pessoa aponta a câmera do tablet para ele.
3. Acompanhe pela sua tela: ela mostra sozinha a etapa (tablet conectado, documento visualizado, identidade confirmada, assinado). Ao terminar, a página se atualiza.
4. Para o próximo signatário, clique de novo em **Liberar para assinatura**: é um QR Code por pessoa.

Situações do balcão:

- O QR expirou antes da leitura: clique em **Gerar outro código**.
- Liberou para a pessoa errada, ou ela desistiu: clique em **Cancelar liberação**. O documento continua valendo.
- A pessoa demorou e o tablet voltou à tela inicial: a sessão dura 15 minutos por padrão. Gere outro código.
- O tablet não lê o QR (câmera com problema): se aparecer na sua tela "Sem câmera no tablet? Dite este código", dite o código de 8 caracteres; no tablet, a pessoa toca em **Digitar código**.
- O tablet do balcão fica sempre aberto na tela de assinatura e não precisa de login.

Diretor não assina pelo QR Code: ele entra no tablet com matrícula e PIN (veja "Como o diretor assina no tablet").

## Conferir as respostas da pessoa antes da assinatura
<!-- telas: signature-documents.index -->

Quando o documento tem campo marcado para **Perguntar ao signatário** e é assinado no tablet do balcão, o atendente confere as respostas antes. A pessoa só assina depois da sua aprovação.

1. Libere o QR Code. No quadro **Assinatura no tablet** aparece **Conferência das respostas**.
2. Enquanto a pessoa responde no tablet, as respostas aparecem na sua tela, campo a campo ("Preenchendo no tablet").
3. A pessoa toca em **Continuar** e passa a ler o documento. Na sua tela aparece "Aguardando a sua conferência". Confira as respostas com ela.
4. Escolha uma opção:
   - **Aprovar respostas**: o tablet libera o aceite e a assinatura.
   - **Devolver com observação**: escreva o que precisa ser corrigido e clique em **Devolver ao tablet**. O tablet volta às perguntas com o seu recado. Quando a pessoa envia de novo, a conferência recomeça.
   - **Corrigir**: ajuste você mesmo as respostas e clique em **Salvar correção e aprovar**. O documento é refeito, e o tablet pede à pessoa que leia de novo antes de assinar.

Se a pessoa voltar e mudar uma resposta depois da aprovação, a conferência volta a ser exigida. Depois que alguém assina, as respostas não mudam mais, e os próximos signatários não passam pela conferência. O link remoto, a entrada da diretoria e o Termo de Menores não têm conferência: não há atendente ao lado.

## O que a pessoa vê no tablet ao assinar

Conhecer as telas ajuda a orientar quem está assinando no tablet do balcão. Algumas etapas só aparecem se o modelo pedir.

1. **Leitura do QR**: a pessoa aponta a câmera para o código na tela do atendente.
2. **Perguntas** (se houver): "Antes de ler, responda". Depois, **Continuar**.
3. **Documento**: rola o texto até o fim; só então o botão vira **Li o documento**.
4. **Identidade**: digita os quatro primeiros dígitos do CPF, o CPF completo ou o código que chegou no e-mail (se não chegar, **Reenviar código**, depois de um minuto). São cinco tentativas.
5. **Aceite**: marca "Li e concordo" e, quando o modelo pede foto, também a autorização de imagem. Pode pedir a via por e-mail. Se houve perguntas, o botão só libera depois que o atendente aprova as respostas ("Aguardando o atendente conferir as suas respostas").
6. **Assinatura**: com o dedo ou a caneta. **Limpar** apaga e deixa refazer.
7. **Visto** (se houver): a rubrica que vai em todas as páginas.
8. **Foto** (se houver): a pessoa toca em **Tirar foto**, o tablet conta 3 segundos, e ela escolhe **Tirar nova foto** ou **Usar esta foto**.

O que vale saber: as respostas entram no texto antes da leitura (há **Corrigir respostas** na tela do documento); **Recusar** existe em todas as etapas e encerra o documento inteiro como "Recusado"; toda tela tem **Voltar**, e no primeiro passo voltar sai sem assinar (não é recusa, o documento continua pendente; gere outro QR). O tablet avisa antes de encerrar por inatividade e não guarda nada: ao fim volta à tela de espera. A via por e-mail só é oferecida a quem tem e-mail no documento.

## Anexar identidade, comprovante e outros arquivos
<!-- telas: signature-documents.index -->

O modelo pode pedir arquivos (identidade, comprovante de residência), e cada documento pode pedir outros no passo **Anexos** do formulário (**+ Pedir anexo**).

Para enviar, abra o documento em **Assinaturas › Documentos** e use o quadro **Anexos** (depois de congelar, ele fica logo acima do texto):

1. No item pedido, escolha o arquivo, ou vários de uma vez (até 10, como frente e verso), e clique em **Enviar**.
2. Faltou algum? **Enviar mais arquivos** no mesmo item.
3. Um arquivo que ninguém pediu vai em **Outro anexo**, com o nome do que é.

Regras:

- Aceita PDF, JPG ou PNG, até 10 MB cada. Foto do celular serve.
- Os anexos podem chegar antes ou depois da assinatura. Mas o documento só conclui com os **obrigatórios**: se todos assinaram e falta algum, ele fica em **Assinado**, esperando, e conclui sozinho quando o último chegar.
- No rascunho, dá para remover e enviar de novo. Depois de congelar, ainda dá para enviar, mas o que foi enviado não sai mais. Em **Assinado\*** ou Finalizado, nada muda.
- Os anexos não entram no PDF assinado: ficam guardados com ele, e o manifesto lista cada um. Não vão na via por e-mail.
- Na assinatura por link, a própria pessoa envia os anexos pela tela do link, e lá o anexo obrigatório impede a assinatura. Aparecem como enviados por "nome (signatário, pelo link)".

## Enviar um documento pronto em PDF
<!-- telas: signature-documents.index -->

Para um documento que precisa ir como está, com fotos ou diagramação própria, use o PDF pronto em vez de um modelo. O arquivo entra inteiro; o Lara só acrescenta as assinaturas, o visto e uma linha de validação no pé da página. Deixe 2 cm livres no rodapé.

1. Em **Assinaturas › Documentos › Novo documento**, escolha **Enviar documento pronto (PDF)**.
2. Informe o título, o arquivo, as regras de assinatura e quem assina, e clique em **Enviar e criar rascunho**.
3. Em **Lugar das assinaturas**, clique no nome da pessoa e depois no documento, em cima da linha onde ela assina. Repita para cada um e clique em **Salvar lugares**. Quem ficar sem lugar assina numa folha de assinaturas ao fim.
4. Congele e libere como qualquer documento.

Se o PDF for recusado, abra-o, use **Imprimir › Salvar como PDF** e envie o arquivo novo. Editar os signatários do rascunho apaga os lugares marcados: marque de novo. Na lista de documentos, eles aparecem no filtro de modelo **Documento enviado pronto (PDF)**.

## Enviar o link de assinatura por e-mail (assinatura a distância)
<!-- telas: signature-documents.index -->

Quem não pode vir ao clube assina por um link enviado por e-mail. O link abre uma tela no site do clube (o mesmo das reservas), sem login. A pessoa digita o CPF completo, lê, aceita, assina na tela e tira uma foto. O PDF assinado sai igual ao do tablet.

1. Congele o documento. Na página dele, abaixo de **Assinatura no tablet**, fica o quadro **Assinatura remota**.
2. Confira o e-mail de quem vai assinar (vem do cadastro e pode ser corrigido) e clique em **Enviar link**. A pessoa recebe na hora.
3. Logo depois de enviar, o link aparece no quadro. Clique em **Copiar link** para encaminhar também por outro canal, como o WhatsApp. Ele aparece só nessa hora: ao sair ou recarregar a página, não dá para ver de novo. Se precisar dele depois, use **Enviar novo link**, que manda outro e-mail, mostra o link novo e faz o anterior parar de valer.
4. O quadro mostra até quando o link vale, quantas vezes foi aberto e quantas vezes o CPF errou. Enquanto ela assina, o cartão mostra "Assinando a distância, pelo link".

O que vale saber:

- O link vale 72 horas, e o prazo do documento é estendido até lá.
- Serve para qualquer documento, inclusive com perguntas e visto. Tablet e link podem ser usados no mesmo documento, um para cada pessoa.
- A conferência é sempre pelo CPF completo. São 5 tentativas por link, somadas entre aberturas; na quinta errada o link é bloqueado.
- A foto é sempre obrigatória no link. Sem câmera, a pessoa não conclui.
- **Enviar novo link** faz o anterior parar de valer: use quando a pessoa perdeu o link, ele venceu ou foi bloqueado. **Revogar link** faz parar de abrir na hora.
- Preparar o documento para o gov.br cancela os links enviados.
- Diretor e Termo de Menores não usam link.
- Se o quadro **Assinatura remota** não aparece, a assinatura remota não está configurada: fale com a TI.

## Assinatura pelo gov.br
<!-- telas: signature-documents.index -->

Quem não vem ao clube também pode assinar o PDF com a própria conta gov.br (nível prata ou ouro) e devolver o arquivo. Quem tem certificado digital ICP-Brasil (e-CPF) pode assinar o mesmo PDF no programa do certificado e devolver do mesmo jeito.

1. Congele o documento. Abra a aba **Assinatura gov.br** e clique em **Preparar para o gov.br**. A partir daí o documento só é assinado pelo gov.br (o tablet não libera mais), a data da assinatura entra no texto, o prazo passa a 7 dias e, se o modelo pede visto, ele é dispensado neste documento.
2. Confira o e-mail e clique em **Enviar por e-mail** ao lado do nome de quem vai assinar. A pessoa recebe o PDF e o passo a passo. Se preferir enviar por conta própria, baixe o PDF pelo link da aba, sempre depois de preparar.
3. A pessoa assina em assinador.iti.br e **entrega o arquivo assinado no atendimento do clube**: o retorno não é por e-mail, e o e-mail não tem link para O Lara.
4. Abra a aba **Assinatura gov.br**, escolha o arquivo e clique em **Conferir assinatura**. Se estiver certo, a pessoa passa a **Assinou**; se não, a aba mostra o que não passou.
5. Tem mais alguém? Clique em **Enviar por e-mail** ao lado do nome: o e-mail já leva o arquivo com as assinaturas anteriores. Um de cada vez, em qualquer ordem.
6. Com todos, o documento fica **Assinado\*** até a revisão interna. O PDF assinado é o próprio arquivo do gov.br, e o comprovante sai à parte, no **Relatório gov.br**.

Cada documento é assinado todo no tablet ou todo pelo gov.br. Se alguém já assinou no tablet, não dá para preparar para o gov.br; para mudar de caminho, cancele e emita outro. Documento com perguntas ao signatário também não vai ao gov.br.

## Encontrar um documento assinado e baixar o PDF
<!-- telas: signature-documents.index -->

Em **Assinaturas › Documentos**, a busca aceita título, nome do modelo, nome ou e-mail do signatário, CPF (inteiro ou um pedaço), código de validação e o nome de quem gerou. Várias palavras estreitam a busca: "maria piscina" acha o termo da piscina da Maria. Ao lado há filtros por **Situação** (inclui **Aguardando diretoria**), **Modelo** (inclusive inativos e **Documento enviado pronto (PDF)**), período (**De** / **até**), **Gerados por mim** e **Arquivados**.

Abra o documento e use os botões do topo:

- **PDF assinado**: o documento com as assinaturas, o visto (se houver) e, ao final, o manifesto: quem assinou, quando, a foto (se houver), cada etapa e um QR Code de validação. O CPF sai mascarado.
- **PDF original**: o documento como a pessoa leu, sem assinaturas.
- **Relatório gov.br**: só nos assinados pelo gov.br; faz o papel do manifesto.

Quem tem só a permissão de consulta vê e baixa, mas não cria nem cancela. Foto e traço de quem assinou só aparecem para quem tem a permissão de evidências.

Outros lugares onde o documento fica: quem pediu a via e tem e-mail recebe o PDF alguns instantes depois (quem assinou pelo gov.br recebe sempre); e, se o arquivamento estiver ligado, há uma cópia na pasta de rede **Lara/DocumentosAssinados**, na pasta do modelo e da pessoa. Renomear ou apagar ali não muda nada n'O Lara.

## Enviar a via por e-mail de novo
<!-- telas: signature-documents.index -->

Depois que o documento está **Assinado\*** ou **Finalizado**, a via (o PDF assinado) pode ser enviada ou reenviada a cada signatário. Abra o documento em **Assinaturas › Documentos** e, no cartão do signatário, veja a situação da via: "Via enviada em…", "Via pedida, ainda não enviada" ou "Via não solicitada".

Clique em **Enviar via** (ou **Reenviar**, se já foi enviada). O envio vai para a fila e sai em instantes, para o e-mail cadastrado no documento. Aparece a mensagem "Envio da via colocado na fila para…".

Limites:

- Só depois que o PDF final fica pronto; não precisa esperar a revisão. Antes disso aparece "A via só pode ser enviada depois que o PDF final é gerado."
- O signatário precisa ter e-mail no documento. Sem e-mail aparece "Este signatário não tem e-mail cadastrado." Como o documento congelado não muda, a saída é baixar o **PDF assinado** e enviar por fora.
- A via não leva os anexos. Do gov.br, vão o arquivo assinado e o relatório.

Não há envio da via por WhatsApp.

## Validar se um documento é autêntico

Todo documento assinado traz um código de validação no rodapé de cada página e um QR Code no manifesto. Qualquer pessoa pode conferir o documento pelo endereço impresso no rodapé ou lendo o QR Code: a página confirma que o documento existe e mostra um resumo, mas não entrega o PDF. Essa página só abre de dentro da rede do clube.

De qualquer lugar, use o validador oficial do governo, **validar.iti.gov.br**: ele confere as assinaturas do gov.br, de certificado ICP-Brasil e o lacre digital do clube. Por isso, no PDF assinado com o lacre do clube e no assinado pelo gov.br, o QR Code e o rodapé já levam ao validador oficial: basta ler o QR e enviar o PDF.

O lacre do clube não assina por ninguém: prova que o arquivo não mudou depois de emitido. Ele não aparece na página; fica no painel de assinaturas do leitor de PDF. Um PDF aberto e salvo de novo em outro programa perde o lacre e deixa de validar: guarde e envie sempre o arquivo baixado d'O Lara.

## Cancelar ou arquivar um documento
<!-- telas: signature-documents.index -->

Abra o documento em **Assinaturas › Documentos**. Os botões ficam no topo.

**Cancelar** encerra o documento sem assinatura. Use quando percebeu um erro depois de congelar ou quando a assinatura não vai mais acontecer. Qualquer sessão aberta no tablet é encerrada. O documento fica como **Cancelado** e não volta: para corrigir, emita um documento novo. O botão só aparece enquanto o documento está em **Rascunho** ou **Aguardando assinatura**: assinado (com ou sem asterisco) ou finalizado não se cancela.

**Arquivar** só tira o documento da lista padrão, para ela não ficar cheia do que não precisa mais aparecer. Nada muda no documento: a situação continua a mesma e, se ainda falta alguém, a pessoa ainda pode assinar. Ele passa a aparecer só com o filtro **Arquivados**. Para trazê-lo de volta, abra-o e clique em **Desarquivar**.

Cancelar e arquivar exigem a permissão de criar documentos. Quem só consulta não vê esses botões.

## Fazer a revisão interna de documentos assinados
<!-- telas: signature-reviews.index -->

Todo documento concluído passa por uma revisão: outra pessoa confere se os processos internos daquele atendimento foram feitos. O que conferir vem do modelo, nos **Itens da revisão** (por exemplo "Cadastro atualizado no sistema", "Pagamento lançado").

1. Abra **Assinaturas › Revisão**. A lista traz os documentos concluídos ainda não revisados, o mais antigo primeiro. O filtro **Situação** alterna entre **A revisar** e **Revisados em ordem**.
2. Clique em **Revisar**. Na aba **Revisão** do documento, marque cada item feito, escolha **Tudo em ordem** ou **Com pendência** e clique em **Registrar revisão**.
3. Com pendência, escreva na observação o que falta. O documento continua na lista; resolvido, revise de novo e marque **Tudo em ordem**.

Até a revisão dar **Tudo em ordem**, o documento aparece como **Assinado\*** em **Assinaturas › Documentos**. Com **Tudo em ordem**, passa a **Finalizado**. O filtro **Situação** da lista separa os dois.

Regras:

- O documento fica disponível a partir do dia seguinte à conclusão.
- Quem acompanhou a assinatura (gerou o documento, o QR Code, o convite gov.br ou conferiu o arquivo do gov.br) não revisa. A lista mostra o motivo no lugar do botão.
- A coordenação (permissão "Revisar antes do prazo e os próprios documentos") pode revisar antes do prazo e os próprios documentos; a revisão fica marcada assim.
- Termo de Menores não passa pela revisão.

## Termo de Menores: cadastrar o termo do evento
<!-- telas: minor-terms.index -->

O Termo de Menores autoriza a entrada de um menor de idade num evento. O próprio sócio responsável assina num tablet de autoatendimento, sem atendente. Só para sócios: responsável e menor são do mesmo título.

Antes do evento:

1. Crie o modelo do texto em **Assinaturas › Modelos**. Os dados da pessoa entram sozinhos pelos campos `[[nome_responsavel]]`, `[[e_mail_responsavel]]`, `[[cpf_responsavel]]`, `[[rg_responsavel]]`, `[[endereco_responsavel]]`, `[[nome_menor]]`, `[[idade_menor]]`, `[[cpf_menor]]` e `[[rg_menor]]`, todos do tipo **Texto**. A data vai num campo do tipo **Data da assinatura**. Exija a foto e use a conferência "CPF completo". Sem visto, sem partes e sem código por e-mail.
2. Em **Assinaturas › Termo de Menores › Termos dos eventos**, clique em **Novo termo**. Informe o **Nome do evento** (aparece no tablet, na tela verde e no histórico), o **Modelo do termo** e o período em **Disponível a partir de** e **Disponível até** (dias inteiros). Clique em **Cadastrar termo**.

Só um termo fica disponível por vez: o Lara recusa período que cruza o de outro. Modelos que não servem aparecem bloqueados com "não serve ao autoatendimento", e o link **Por que alguns modelos estão bloqueados?** explica. A lista mostra a situação (Vigente, Agendado, Desativado) e quantos menores já foram autorizados. Revisar o modelo durante o evento vale para os termos seguintes.

## Termo de Menores: parear o tablet de autoatendimento
<!-- telas: minor-terms.index -->

O tablet do evento precisa ser pareado para aceitar termos. No dia:

1. No tablet, abra o endereço indicado na tela **Assinaturas › Termo de Menores › Tablet**. Ele mostra o leitor de QR.
2. No computador, na mesma tela, se quiser dê um **Nome do tablet** (por exemplo "Tablet da entrada") e clique em **Gerar QR de pareamento**.
3. Aponte a câmera do tablet para o QR. Em instantes ele mostra a tela do autoatendimento. O QR vale 5 minutos. Sem câmera, toque em **Digitar código** no tablet e informe o código mostrado.

O tablet fica pareado por 12 horas; depois disso, pede o pareamento de novo. A lista mostra cada tablet, quando foi pareado, até quando vale e o último acesso. Para tirar um tablet de uso antes, clique em **Desparear**: ele para de gerar termos na próxima tela.

O que o sócio faz no tablet: digita o número do título e toca no nome do responsável (só maiores de 18 anos); confirma com o CPF completo (5 tentativas); completa e-mail ou RG se faltarem (dá para pular, e o termo sai "não informado"); escolhe o menor (aparecem os menores do título com sobrenome em comum; quem já tem termo aparece como "Já autorizado ✓"); lê, aceita, assina e tira a foto. O tablet volta sozinho ao início depois de 2 minutos parado, menos na tela verde.

## Termo de Menores: conferir na entrada e colocar a pulseira
<!-- telas: minor-terms.index -->

Ao terminar, o tablet mostra a **tela verde**: "Apresente ao Representante do Clube", com o nome e a idade do menor e o nome e a foto do responsável. Ela só sai quando alguém toca em **Concluir** ou em **Autorizar outro menor** (mesmo responsável, sem digitar o CPF de novo).

Na entrada, confira na tela verde o nome do menor e a foto de quem assinou, e coloque a pulseira.

Se a tela verde já saiu, abra **Assinaturas › Termo de Menores › Histórico**. Cada cartão repete a tela verde: menor, idade, responsável, foto, evento e título. Busque pelo nome do menor, do responsável ou pelo número do título, e filtre por **Evento** e **Situação** (**Só os autorizados** ou **Todos (inclui não concluídos)**). **Abrir documento** mostra o termo assinado.

O que vale saber:

- Um termo por menor. Dois filhos, dois termos: o segundo sai pelo **Autorizar outro menor**.
- Cada termo é um documento como os outros, com PDF, comprovante e via por e-mail (se houver e-mail).
- Nada que o sócio digita no tablet muda o cadastro do clube.
- "Não há termo disponível hoje" no tablet: não há termo cadastrado para a data, ou ele foi desativado.

## Diretoria: cadastrar as áreas, titulares e substitutos
<!-- telas: signature-directors.index -->

Diretores do clube assinam no tablet do balcão com matrícula e PIN. Antes, é preciso cadastrar quem assina por cada área, em **Assinaturas › Diretoria**.

1. Em **Nova área**, informe o **Nome da área** (por exemplo "Diretoria Financeira"; sai impresso sob o nome de quem assina) e, se quiser, a **Descrição**. Clique em **Criar área**.
2. Em **Quem assina pela área**, escolha o **Diretor**, o **Papel** (titular ou substituto) e a data em **Desde**, e clique em **Incluir**. Um titular novo encerra o mandato do titular atual; substitutos podem ser vários.
3. Para tirar alguém, clique em **Encerrar** ao lado do nome. Os mandatos encerrados ficam no histórico da área.
4. Em **Editar a área**, dá para mudar nome, descrição e desmarcar **Ativa**. Os documentos já emitidos continuam com o nome que tinham.

Só aparecem na lista usuários do setor Diretoria, ativos e com matrícula. Se não aparece ninguém, vincule os diretores ao setor na tela Setores. O diretor também precisa de CPF no cadastro: em **Usuários**, abra o usuário e preencha o campo **CPF** (ou cadastre-o em Funcionários, com a mesma matrícula). Sem CPF, ele aparece no documento como "sem CPF no cadastro" e não pode ser escolhido.

Área sem titular nem substituto não aparece no documento. Quando alguém sai do mandato com documentos pendentes, a área mostra **Documentos pendentes de quem não responde mais por esta área**: cancele e emita de novo, escolhendo quem assume.

## Mandar um documento para um diretor assinar
<!-- telas: signature-documents.index -->

Em **Assinaturas › Documentos › Novo documento**, no passo **Signatários**, use **Diretor do clube** e escolha o titular ou um substituto da área. Nome, CPF e e-mail vêm do cadastro d'O Lara, sem digitar. Se o modelo tem uma parte marcada como "Assinada pela diretoria", o titular já vem escolhido.

O que acontece depois:

- A pessoa escolhida é a que assina: o nome e o CPF dela vão impressos. Se ela não puder assinar, cancele e emita de novo com quem a substitui.
- O documento ganha 7 dias de prazo ao congelar.
- Se há outros signatários (o sócio, um fornecedor), o documento só vai para a diretoria **depois que eles assinarem**. Até lá não aparece no tablet do diretor, e o cartão dele no documento diz "Vai para a diretoria depois da assinatura de…".
- Quando chega a vez da diretoria, o diretor recebe um e-mail de aviso, sem link.
- A lista **Documentos** tem o filtro **Aguardando diretoria**, só com os que já chegaram à diretoria.
- O diretor não assina por QR Code nem por link remoto. A outra forma é o gov.br: documento preparado para o gov.br sai da lista do tablet do diretor.

## Como o diretor assina no tablet

O diretor assina sem passar pelo atendente, no tablet do balcão:

1. Na tela de espera do tablet, toque em **Assinatura da Diretoria** e entre com a **matrícula** e o **PIN** d'O Lara. Sem PIN? Cadastre no seu **Perfil**, n'O Lara.
2. Aparece a lista dos documentos que esperam a sua assinatura. Toque num deles, leia até o fim, aceite, assine, faça o visto (se o documento pedir) e tire a foto. Para o diretor, a foto é sempre obrigatória. Não há conferência de CPF: o PIN já identifica.
3. Para assinar vários de uma vez, marque a caixa de cada documento (ou **Marcar todos**) e toque em **Assinar selecionados**. O tablet mostra um documento de cada vez para leitura; depois, um aceite, uma assinatura e uma foto valem para todos. Documento com perguntas a responder não entra no lote: assine sozinho.
4. A aba **Já assinados** mostra o que você assinou nos últimos 90 dias e em que pé está (quem ainda falta, ou "Concluído").
5. Toque em **Sair** ao terminar. Se esquecer, o tablet sai sozinho depois de um tempo parado.

Errar o PIN cinco vezes trava a matrícula por 15 minutos. O comprovante do PDF registra que o próprio diretor abriu o documento, com matrícula e PIN.

## Redigir um contrato e enviar à Gerência
<!-- telas: signature-contracts.index -->

Contratos são escritos no Lara, em cláusulas, validados pela Gerência e só então viram documento de assinatura.

1. Em **Assinaturas › Contratos**, clique em **Novo contrato**. Escolha o **Setor** (só esse setor e a Gerência veem o contrato), o título e, se quiser, um modelo do setor.
2. Preencha: dados gerais (título e por qual CNPJ do clube), contratada (e fiadores, se houver; **Buscar no cadastro** traz as já usadas), variáveis, considerandos (opcional), cláusulas e quem assina. A prévia ao lado mostra o contrato como vai ficar.
3. Clique em **Salvar e enviar à Gerência**. O coordenador da Gerência recebe um e-mail.

O que O Lara faz por você:

- **Numeração automática**: CLÁUSULA PRIMEIRA, 1.1, 1.1.1 não se digitam. Use "Subitem →" para o 1.1.1.
- **Remissão**: para citar outra cláusula, use **Inserir remissão**; o número acompanha se a ordem mudar.
- **Variáveis**: valor, prazo e datas preenchidos uma vez e usados com **Inserir variável**. Valor sai com o extenso.
- **Conferência**: a lista ao lado da prévia diz o que falta (trava o envio): variável sem valor, CNPJ ou CPF inválido, remissão quebrada, cláusula vazia, falta de quem assina pela contratada.
- O clube é representado pelo presidente (o titular da Presidência em **Assinaturas › Diretoria**), que assina sem precisar ser incluído. A data do fecho é a da assinatura, e o documento sai com visto em todas as páginas.
- **Escrever com conforto**: a caixa de texto cresce com o que você digita e pode ser puxada pelo canto de baixo. A barra tem **Limpar** (tira a formatação do trecho), **↶ desfazer** e **↷ refazer**. Ao colar do Word ou de um PDF, ficam só negrito, itálico, sublinhado, parágrafos e listas. Na tela larga, **Ocultar prévia** (no alto) dá a largura toda ao editor; **Mostrar prévia** a traz de volta.

Para o fornecedor revisar, o botão **Word** baixa o contrato em .docx; mudanças pedidas por ele são feitas no editor.

## Validar um contrato (Gerência)
<!-- telas: signature-contracts.index -->

Só o setor Gerência valida contratos. Em **Assinaturas › Contratos**, a aba **Para validar** mostra os que esperam validação.

1. Abra o contrato. Aparecem o recado de quem enviou, o que mudou desde a última devolução e o contrato inteiro.
2. Para pedir mudança, escreva observações gerais ou em cada cláusula.
3. Clique em **Devolver com observações** (quem redigiu é avisado por e-mail) ou em **Aprovar**. A Gerência também pode ajustar o texto direto e aprovar.

Aprovado, o contrato ganha o número (por exemplo **CTL - 12/26**, uma sequência só para todos os setores) e o documento de assinatura é gerado em rascunho.

**Pelo tablet do balcão**: na tela de espera, toque em **Validação de contratos (Gerência)** e entre com a matrícula e o PIN d'O Lara. Toque num contrato; para comentar uma cláusula, toque nela e escreva. Toque em **Devolver** ou **Aprovar**. Ajustar o texto só pelo computador. Toque em **Sair** ao terminar.

**Revisão automática (Claude)**: na tela do contrato, no cartão **Revisão automática (Claude)**, confira a lista **Trechos com dados sensíveis**: os trechos com valores, CPF, documentos ou endereço (qualificação das partes, cláusula do valor…) **não são enviados**, a menos que você marque cada um. Depois clique em **Analisar com o Claude** (leva até um minuto). Volta um resumo e apontamentos por cláusula (grave, atenção, detalhe) com sugestão. **Levar para a observação** copia o apontamento para a observação da cláusula. Se o texto mudou, use **Analisar o texto atual**; para incluir outros trechos, **Analisar de novo escolhendo outros trechos**. O cartão mostra em **Não enviados ao Claude** o que ficou de fora (e não foi analisado). São sugestões de IA: não aprovam nem devolvem nada, e o texto enviado vai para a empresa do Claude.

## Contrato devolvido, aprovado ou que precisa mudar
<!-- telas: signature-contracts.index -->

Em **Assinaturas › Contratos**, abra o contrato:

- **Devolvido**: as observações aparecem em cada cláusula do editor. Ajuste, clique em **Resolvida** em cada observação atendida e envie de novo com **Salvar e enviar à Gerência**. A Gerência vê só o que mudou.
- **Aprovado**: o documento de assinatura é gerado em rascunho em **Assinaturas › Documentos**. Abra-o, confira signatários e anexos e congele; daí em diante é a assinatura de sempre (balcão, link ou gov.br; o presidente e os diretores assinam no tablet, depois de quem é de fora da diretoria). Se a geração falhou ou o rascunho foi cancelado, use **Gerar documento de assinatura** na tela do contrato.
- **Mudar depois de aprovado**: **Reabrir para alteração** volta o contrato a rascunho e cancela o rascunho do documento de assinatura; o contrato precisa ser validado de novo. Depois que o documento foi congelado, não reabre: cancele o documento ou faça um aditivo.

Anexos do contrato (a proposta, um projeto) são incluídos na tela do contrato e seguem junto para a assinatura.

## Reaproveitar: biblioteca de cláusulas, modelos e aditivos
<!-- telas: signature-contracts.index -->

Em **Assinaturas › Contratos**, o botão **Biblioteca e modelos** abre o que o seu setor guardou para reaproveitar.

- **Biblioteca de cláusulas**: no editor, o botão **Salvar na biblioteca** de cada cláusula guarda-a para o setor; **Da biblioteca…** insere uma cópia num contrato. **+ Cláusula** e **Da biblioteca…** ficam no alto e no fim da lista de cláusulas (entra por último); no pé de cada cláusula, **+ Cláusula abaixo** e **Da biblioteca abaixo…** põem a nova logo depois dela, e a numeração se ajusta. Também dá para escrever uma cláusula direto na tela da biblioteca. Mudar ou remover da biblioteca não mexe em contrato já feito.
- **Modelos de contrato**: na tela de um contrato pronto, **Salvar como modelo do setor** guarda título, termos, variáveis (sem valor), considerandos e cláusulas. Ao criar um **Novo contrato**, escolha o modelo. Remover um modelo não muda os contratos feitos com ele.
- **Contratada**: ao salvar, os dados dela ficam guardados. No próximo contrato, use **Buscar no cadastro (contratadas e fiadores já usados)**.
- **Aditivo**: num contrato aprovado, **Criar aditivo** já vem com as partes, as variáveis e os signatários; escreva só as cláusulas do aditivo. O número é o do contrato com A1, A2…

Cada setor vê só a própria biblioteca e os próprios modelos.

## Dúvidas comuns

### Por que não consigo corrigir um documento?
<!-- telas: signature-documents.index -->

Documento congelado não muda: a mensagem é "Documento já congelado: o texto e os dados não mudam mais. Para corrigir algo, cancele e gere um novo documento." Abra o documento em **Assinaturas › Documentos**, clique em **Cancelar** e emita outro. Só o rascunho tem o botão **Editar**.

### O Lara não deixa congelar. O que falta?
<!-- telas: signature-documents.index -->

Leia a mensagem em vermelho. As mais comuns: "Documento sem signatário: informe quem vai assinar antes de congelar."; "Falta informar quem assina como: …" (uma parte do modelo ficou sem signatário); "Faltam dados obrigatórios do modelo: …"; e, em modelo com código por e-mail, "Informe o e-mail de: …". Clique em **Editar**, complete e congele de novo.

### O documento está em "Assinado*": o que falta?
<!-- telas: signature-documents.index -->

Só a revisão interna. O PDF está pronto e a via pode ser enviada. O documento passa a **Finalizado** quando a revisão em **Assinaturas › Revisão** der **Tudo em ordem**.

### Todos assinaram, mas o documento não sai de "Assinado" (sem asterisco)
<!-- telas: signature-documents.index -->

Primeiro, veja o quadro **Anexos**: falta um anexo obrigatório. Envie e o documento conclui sozinho. Se não falta anexo e passou de alguns minutos, avise a TI: o serviço que monta o PDF final está parado. Nada se perde, a assinatura está registrada.

### O tablet diz "QR Code expirado" ou "já foi usado"
<!-- telas: signature-documents.index -->

Cada código serve uma vez e por poucos minutos. Na página do documento, clique em **Gerar outro código** e peça que a pessoa leia de novo.

### O tablet diz que "os dados não conferem"
<!-- telas: signature-documents.index -->

O CPF digitado não bate com o do documento. São cinco tentativas. Se o CPF do documento estiver errado, cancele e emita outro.

### O botão "Liberar para assinatura" não libera
<!-- telas: signature-documents.index -->

A tela diz o motivo: "Congele o documento antes de liberar a assinatura."; "Este documento está sendo assinado pelo gov.br: a assinatura não é liberada no tablet."; "Diretor assina só no tablet, em 'Assinatura da Diretoria'…" (diretor não usa QR); ou "Este documento vai para a diretoria depois da assinatura de…".

### O tablet mostrou "Falha na comunicação com o servidor (código …)"

A assinatura não foi gravada: o tablet volta ao traço e a pessoa pode tentar de novo. Se repetir, anote o código e o horário e passe à TI. Já "Sem resposta do servidor" é problema de rede (Wi-Fi do tablet).

### O botão "Preparar para o gov.br" não aparece
<!-- telas: signature-documents.index -->

A aba **Assinatura gov.br** diz o motivo: o documento ainda não foi congelado; alguém já assinou no tablet; ou há campo marcado em **Perguntar ao signatário** (cancele e refaça preenchendo esse campo você mesmo). Modelo com visto pode ir ao gov.br: o visto é dispensado ao preparar.

### O arquivo do gov.br foi recusado
<!-- telas: signature-documents.index -->

Veja na aba **Assinatura gov.br** o item marcado com X. Os mais comuns: a pessoa devolveu o PDF sem assinar; assinou outro arquivo (de outro atendimento, um PDF baixado antes de preparar, ou um arquivo salvo de novo em outro programa); quem assinou não está entre os signatários (vale o CPF, não o nome); ou o certificado foi revogado. Peça que assine de novo o PDF certo, em assinador.iti.br.

### O arquivo do gov.br é válido, mas não registrou a assinatura
<!-- telas: signature-documents.index -->

A aba mostra o motivo em amarelo. Em geral, a pessoa assinou o original, ou o mesmo arquivo que outra pessoa assinou ao mesmo tempo, em vez do arquivo com as assinaturas anteriores. Clique de novo em **Enviar por e-mail** para ela (o e-mail novo leva o arquivo certo) e peça que assine de novo.

### "O e-mail não pôde ser enviado. Nada foi registrado como enviado."
<!-- telas: signature-documents.index -->

O convite do gov.br ou o link remoto não saíram. Confira o e-mail digitado e tente de novo; se repetir, avise a TI.

### O quadro "Assinatura remota" não aparece, ou não deixa enviar
<!-- telas: signature-documents.index -->

O quadro só aparece com o documento em **Aguardando assinatura** e fora do gov.br. Diretor e Termo de Menores não usam link. Se o quadro não existe em nenhum documento, a assinatura remota não está configurada: fale com a TI.

### O link remoto foi bloqueado ou venceu
<!-- telas: signature-documents.index -->

Na quinta tentativa errada de CPF o link é bloqueado; depois de 72 horas ele vence. No quadro **Assinatura remota**, clique em **Enviar novo link**.

### A pessoa não recebeu a via por e-mail
<!-- telas: signature-documents.index -->

Ela precisa ter pedido a via no tablet e ter e-mail no documento. Com o documento finalizado, use **Enviar via** no cartão dela, ou baixe o **PDF assinado** e envie.

### O documento saiu com [[alguma coisa]] impresso, ou com "R$ R$"
<!-- telas: signature-templates.index -->

O marcador foi digitado errado no Word (por exemplo, um colchete só de um lado), ou há "R$" antes de um campo do tipo **Valor em reais**. Corrija o Word e revise o modelo em **Assinaturas › Modelos › Revisar**. Uma lacuna "________" é um campo que a pessoa ainda vai responder no tablet, ou a data automática: some na hora da assinatura.

### O Lara recusou o meu arquivo do Word, ou um campo não apareceu
<!-- telas: signature-templates.index -->

O arquivo precisa estar em .docx (PDF e .doc não são aceitos): no Word, use **Salvar como › Documento do Word (.docx)**. Campo que não apareceu: confira os dois colchetes de cada lado e se não há quebra de linha no meio do nome.

### Por que não consigo revisar um documento?
<!-- telas: signature-reviews.index -->

A lista **Assinaturas › Revisão** mostra o motivo: "Disponível para revisão a partir de…" (só no dia seguinte à conclusão) ou "Você acompanhou a assinatura deste documento: a revisão é de outra pessoa." Só a coordenação revisa antes do prazo ou os próprios. Para "Tudo em ordem", todos os itens precisam estar marcados; com pendência, a observação é obrigatória.

### O tablet do evento diz "Não há termo disponível hoje"
<!-- telas: minor-terms.index -->

Não há termo vigente na data, ou ele foi desativado. Confira em **Assinaturas › Termo de Menores › Termos dos eventos** o período do termo. Se o tablet pede pareamento, gere um novo QR na aba **Tablet**: o pareamento vale 12 horas.

### O diretor não aparece para escolher
<!-- telas: signature-directors.index, signature-documents.index -->

Na área: ele precisa ser do setor Diretoria, estar ativo e ter matrícula. No documento: a área precisa ter titular ou substituto, e o diretor precisa de CPF no cadastro de **Usuários** (senão aparece "sem CPF no cadastro").

### O diretor não vê o documento na lista do tablet

Ou ainda falta a assinatura de quem é de fora da diretoria (o documento só chega depois), ou o documento foi preparado para o gov.br, ou o prazo de 7 dias venceu. O documento só aparece para a pessoa escolhida (titular ou substituto) ao criar.

### Não vejo o item Contratos, ou "Você não está em nenhum setor"
<!-- telas: signature-contracts.index -->

O item exige a permissão de Contratos. E o contrato pertence ao setor de quem o redige: sem setor, peça à TI para incluí-lo no seu setor antes de redigir.
