---
modulo: Freelancers
palavras: freelancer, freela, extra, contrato, serviço, diária, turno, função, garçom, tablet, kiosk, assinatura, aditivo, comissão, lote, aprovação, gerência, diretoria, pagamento, pix, baixa, financeiro, limite semanal, falta, jantar
---

# Freelancers — guia de uso

O módulo Freelancers cuida de quem trabalha no clube por dia, sem vínculo: o cadastro da pessoa, as funções que ela pode exercer (garçom, segurança, etc.) com o preço de cada uma, e o contrato de cada turno trabalhado.

O caminho de um contrato é sempre o mesmo: ele é registrado (no painel ou no tablet), o freelancer assina no tablet, a coordenação do Comercial confirma na manhã seguinte, o contrato entra num lote, a Gerência aprova, a Diretoria dá o aval por um código enviado por e-mail e, por fim, o Financeiro paga.

O contrato também é o que libera a entrada do freelancer na portaria e o que decide se ele tem direito ao jantar do turno.

Quem usa: a Secretaria e o Comercial (cadastro e contratos), a coordenação do Comercial (validação e lotes), a Gerência (aprovação), o Financeiro (pagamento) e quem atende o freelancer no tablet.

## Quem faz o quê

Cada tela pede uma permissão do grupo **Freelancers**. Quem libera é a TI ou a Gerência, na tela **Setores** (ou só para uma pessoa, na tela **Usuários**); o coordenador do setor pede a liberação e coloca a equipe no setor pela tela **Meu setor**.

| Para | Permissão (como aparece na tela Setores) |
|---|---|
| Cadastrar, editar, importar e excluir freelancers | Cadastro de freelancers |
| Cadastrar funções e preços | Funções |
| Ver a lista de contratos, sem valores | Lista de serviços (sem valores) |
| Registrar contratos, ver valores e documentos, cancelar, montar lotes | Serviços: valores, documentos, registro e lotes |
| Entrar no tablet para atender freelancers | Assinatura no tablet (kiosk) |
| Ver a tela Acompanhamento | Acompanhamento dos lotes |
| Pagar (dar baixa) no Financeiro | Financeiro e baixa de pagamento |

Algumas ações não são permissão, e sim **cargo** no setor (coordenador). O acesso total ao sistema **não** substitui esses cargos:

| Ação | Quem pode |
|---|---|
| Validar contratos e liberar o 3º contrato da semana | Coordenador do setor Comercial |
| Montar e enviar lote | Coordenador de qualquer setor |
| Aprovar lote, digitar o código da diretoria, cadastrar a diretoria | Coordenador do setor Gerência |
| Cancelar contrato pelo painel | Coordenador de qualquer setor |

## Como um contrato chega até o pagamento
<!-- telas: freelancer-services.index -->

Este é o caminho completo de um contrato de freelancer no Lara, em **Freelancers › Serviços / Contratos**:

1. **Registro** — pelo painel (um a um, em massa ou por planilha) ou no tablet, no balcão.
2. **Assinatura do freelancer** — sempre no tablet (**Freelancers › Assinatura (Tablet)**), antes de o turno começar (tolerância de 30 minutos). Antes de assinar, ele confere a chave PIX.
3. **Durante o dia** — se o horário mudar, faz-se um **aditivo**; para garçom, pode haver **comissão de venda**.
4. **08h do dia seguinte** — o contrato é liberado para a coordenação. Antes disso ele não aparece para validar nem para lote.
5. **Validação** — o coordenador do Comercial lê o contrato e valida com o PIN, na aba **Validação** (contratos antigos, da redação 1, eram assinados pelo coordenador no tablet).
6. **Lote** — um coordenador junta os contratos validados num lote e envia para a Gerência.
7. **Gerência** — aprova ou recusa contrato a contrato.
8. **Diretoria** — recebe um e-mail com dois códigos; dita o código escolhido e a Gerência digita no Lara.
9. **Financeiro** — dá baixa (ou paga via Pix) em **Freelancers › Financeiro**.

Para ver em que etapa está cada contrato, use **Freelancers › Acompanhamento**.

## Cadastrar um freelancer
<!-- telas: freelancers.index -->

Na tela **Freelancers › Freelancers**, clique em **Novo freelancer**.

1. Preencha **Nome**, **CPF** (só os 11 números), **RG**, **Telefone**, **Nacionalidade**, **Estado Civil** e **Endereço** — todos obrigatórios.
2. **Chave PIX** é opcional: se ficar em branco, o Lara usa o próprio CPF como chave.
3. **E-mail** é opcional.
4. Tire ou importe a **Foto de Identificação** (veja abaixo).
5. Clique em **Cadastrar**.

O CPF não pode se repetir: cada pessoa tem um cadastro só. Sem todos os dados obrigatórios, o freelancer fica com o selo **Cadastro incompleto** e **não pode receber contrato** até ser completado.

O freelancer também pode ser cadastrado direto no tablet, no momento do atendimento (opção **Cadastrar freelancer**, quando o CPF não é encontrado).

### Foto de identificação

A foto é a que o porteiro vê no Monitor de Acesso para reconhecer quem entra. No formulário, use **Ativar Câmera** e depois **Tirar Foto**, ou **Importar Foto** para escolher um arquivo. A foto é recortada em quadrado automaticamente. Para desistir da foto nova, clique em **Descartar** (ou **Manter a Foto Atual**, na edição). Salvar sem foto nova mantém a que já existe.

### Importar vários freelancers por planilha

Na tela **Novo freelancer**, abra o bloco **Importar por planilha**, clique em **Baixar modelo**, preencha uma linha por pessoa (apague a linha de exemplo, não mexa no cabeçalho) e envie em **Importar**. A importação é **tudo ou nada**: se uma linha tiver erro, nada é gravado e a tela lista os problemas por número de linha. CPF repetido dentro da própria planilha também é recusado.

## Editar ou excluir um freelancer
<!-- telas: freelancers.index -->

Na tela **Freelancers › Freelancers**, use a busca (nome, CPF, PIX ou função) e clique no nome ou no lápis do cartão.

- Corrija os dados e clique em **Salvar Alterações**.
- Se o cadastro estiver incompleto, um aviso no topo diz exatamente o que falta ("Cadastro incompleto — geração de contrato bloqueada").
- Abaixo do formulário aparecem os **Serviços / Contratos** da pessoa, com o número de cada um (#), função, local, período e situação da assinatura.

**Contrato já assinado não muda quando o cadastro muda.** O documento assinado guarda os dados do dia da assinatura (nome, RG, endereço, chave PIX). Isso é proposital: o instrumento firmado não se reescreve. A tela do contrato avisa quando o cadastro foi alterado depois.

**Excluir:** o botão **Excluir Freelancer** (ou a lixeira no cartão) só funciona para quem **não tem nenhum contrato**. Com contrato vinculado, aparece a mensagem "Não é possível excluir … pois possui serviços vinculados".

Os selos do cartão: **Excesso de serviços** (passou do limite semanal de contratos) e **Cadastro incompleto** (passe o mouse para ver o que falta).

## Cadastrar e editar funções
<!-- telas: freelancer-functions.index -->

Na tela **Freelancers › Funções**, clique em **Nova função** (ou em **Editar** numa função existente).

- **Nome** — como a função aparece no contrato (Garçom, Segurança…). Na planilha de contratos, a função é encontrada por este nome.
- **Preço (R$ por 15 min)** — atenção: é o valor de **cada bloco de 15 minutos**, não por hora. Um turno de 4 horas tem 16 blocos.
- **Permite comissão de venda** — marque nas funções que podem receber, no tablet, o termo de comissão sobre vendas (hoje, o Garçom).
- **Descrição** — opcional.

Clique em **Cadastrar** ou **Salvar Alterações**.

Mudar o preço vale para os contratos registrados depois; contratos já gravados mantêm o valor calculado.

**Excluir Função** só funciona se nenhum contrato usar a função. Caso contrário aparece "Não é possível excluir … pois possui serviços vinculados".

## Registrar um contrato pelo painel
<!-- telas: freelancer-services.index -->

Na tela **Freelancers › Serviços / Contratos**, clique em **Novo Serviço**.

1. **Freelancer** — quem está com cadastro incompleto aparece desabilitado na lista.
2. **Função**.
3. **Evento / Local** — só o evento ou local. Esclarecimentos vão em **Descrição / Justificativa** (opcional, não aparece no contrato).
4. **Data** (dia em que o turno começa), **Início** e **Término**. Se o término for menor que o início, o turno vira a meia-noite ("Termina no dia seguinte").
5. **Valor do contrato** — **Por horas (padrão)** ou **Valor fixo** (veja abaixo).
6. Confira **Duração**, **Horas pagas** e **Valor** no quadro e clique em **Registrar**.

**Como o valor é calculado:** preço da função × blocos de 15 minutos **completos**. Bloco incompleto não é pago (ex.: 3h10 paga 3h00). O turno precisa ter pelo menos 15 minutos e o término não pode ser igual ao início.

Se o freelancer já tem 2 contratos na semana, a tela volta pedindo a liberação do coordenador do Comercial — veja a seção sobre o limite semanal.

Depois de registrado, o contrato precisa ser **assinado pelo freelancer no tablet**.

### Valor fixo

Escolha **Valor fixo** em **Valor do contrato** e digite o valor em **Valor fixo (R$)** (até R$ 100.000,00). Esse valor vale pelo dia, qualquer que seja a duração. Início e término continuam obrigatórios (são eles que abrem a portaria e contam o prazo de assinatura). Em todas as telas de aprovação e pagamento o contrato aparece com o selo **Valor fixo**.

## Registrar vários contratos de uma vez
<!-- telas: freelancer-services.index -->

Na tela **Freelancers › Serviços / Contratos** há dois caminhos para lançar muitos contratos.

**Em massa (na própria tela):** clique em **Em massa** (ou em **Registrar vários de uma vez**, na tela Novo Serviço).

1. Cada linha tem Freelancer, Função, Evento / Local, Descrição / Justificativa, Data, Início, Término e Valor.
2. **+ Adicionar linha** repete função, local, data e horários da linha de cima e deixa só o freelancer em branco — ideal para várias pessoas no mesmo evento. O valor fixo não é repetido.
3. O **×** remove a linha. São no máximo 100 linhas por envio.
4. Clique em **Registrar N contrato(s)**.

**Por planilha:** na tela **Novo Serviço**, abra **Importar por planilha**, clique em **Baixar modelo**, preencha e envie em **Importar**. O freelancer é encontrado pelo **CPF** (precisa já estar cadastrado) e a função pelo **nome** igual ao da tela Funções. A coluna **Valor fixo (R$)** é opcional: em branco, o contrato é por horas.

Nos dois caminhos vale o **tudo ou nada**: com qualquer erro, nada é gravado e a tela lista os problemas por linha. No registro em massa, as linhas do mesmo envio contam juntas no limite semanal; se alguma passar do limite, o envio todo pede a liberação do coordenador do Comercial (um PIN ou um código por e-mail serve para o lote inteiro). Na planilha, o limite só gera um aviso no fim.

## Limite de 2 contratos por semana e liberação do coordenador
<!-- telas: freelancer-services.index, kiosk.index -->

Cada freelancer pode ter **2 contratos por semana**, contando de **segunda a domingo** pela data do turno. A segunda-feira zera a contagem. Contratos cancelados, faltas e aditivos não contam.

O 3º contrato da semana **não é bloqueado**, mas só é gravado com a **liberação de um coordenador do setor Comercial**. Quem registra não pode se autorizar sozinho.

**No painel** (**Freelancers › Serviços / Contratos › Novo Serviço** ou **Em massa**): ao clicar em **Registrar**, a tela volta com o aviso e o bloco **Liberação do coordenador do setor Comercial**.

- **Coordenador presente:** ele digita a **Matrícula do coordenador** e o PIN dele em **PIN ou código**; clique em **Liberar e registrar**.
- **Nenhum coordenador presente:** clique em **Nenhum coordenador presente? Enviar código por e-mail**. Um código de 6 dígitos vai para **todos** os coordenadores do Comercial; qualquer um deles dita o número. Digite-o em **PIN ou código**, deixe a matrícula em branco e clique em **Liberar e registrar**.

O código vale **uma vez**, só para aquele freelancer e aquela data, por cerca de 2 horas, com no máximo 5 tentativas. Pedir um código novo invalida o anterior.

**No tablet** a tela "Quem libera é o Comercial" oferece **O coordenador está aqui · usar o PIN dele** ou **Ninguém presente · enviar código por e-mail**. Antes disso, se algum contrato da semana não foi trabalhado, o tablet mostra "Algum destes dias não foi trabalhado?" — marcar a falta devolve a vaga e dispensa a liberação.

O contrato liberado mostra na tela quem liberou e quando.

## Encontrar contratos: busca e filtros
<!-- telas: freelancer-services.index -->

Na tela **Freelancers › Serviços / Contratos**, use o quadro de filtros e clique em **Filtrar**:

- **Busca** — nome, CPF (com ou sem pontos) ou evento/local.
- **Freelancer** e **Serviço** (a função).
- **Assinatura** — Não assinado, Aguardando coordenador (assinatura ou validação), Aguardando freelancer, Assinado, Cancelado, Falta do freelancer.
- **Registro** — falhas do registro: Qualquer falha, Sem falhas, Excesso em 7 dias (acima do limite semanal), Assinatura em atraso, Sem assinatura, turno já começou.
- **Redação** — qual texto de contrato foi assinado.
- **Período · de / Até** — pela data do turno.

**Limpar filtros** volta à lista inteira. A lista mostra 20 contratos por página; clique em **Freelancer** ou **Período** no cabeçalho para ordenar. Clicar em qualquer ponto da linha abre o contrato.

Selos da lista: ⚠️ freelancer acima do limite semanal; 🕒 assinado depois do início do turno; ⏳ turno começou e o freelancer nunca assinou (o contrato não entra em lote nem é pago enquanto não for assinado); **Aditivo**, **Aditivado · pago no aditivo**, **Comissão de venda** e **Valor fixo**.

Quem tem só a permissão "Lista de serviços (sem valores)" vê a lista sem preços e sem abrir o contrato.

## Editar, cancelar ou excluir um contrato
<!-- telas: freelancer-services.index -->

Na tela **Freelancers › Serviços / Contratos**, clique na linha do contrato.

**Editar:** enquanto **ninguém assinou**, os dados podem ser corrigidos no formulário **Dados do Serviço** e salvos em **Salvar Alterações**. Depois da primeira assinatura o formulário fica só leitura ("Este contrato já possui assinatura e por isso não pode mais ser alterado nem cancelado"). Se o turno mudou depois da assinatura, o caminho é o **aditivo**, feito no tablet.

**Cancelar:** o botão **Cancelar Contrato** aparece só para **coordenador de setor** e só enquanto não há nenhuma assinatura. O contrato cancelado fica no histórico, não vai para lote nem é pago, e não ocupa vaga no limite semanal.

**Excluir:** **Excluir Serviço** só existe para contrato sem assinatura. Contrato assinado não pode ser excluído.

**Abrir documento** mostra o contrato pronto para imprimir. A tela também mostra: as assinaturas (freelancer, validação da coordenação e diretoria), a chave PIX conferida, a resposta do jantar, se houve pagamento, e o texto (redação) em que o contrato foi assinado.

Se o freelancer não compareceu, a falta é registrada no tablet (**Contratos do dia · registrar falta**), não no painel.

## Atender o freelancer no tablet
<!-- telas: kiosk.index -->

O menu **Freelancers › Assinatura (Tablet)** abre a tela de toque usada no balcão. Ela tem login próprio: **matrícula** e **PIN de 6 dígitos** de quem está operando. O PIN é definido em **Perfil › PIN de Assinatura** (ou pela TI, na tela Usuários).

Quem tem os dois papéis escolhe em "O que você vai fazer agora?": **Atender freelancer** ou **Assinar como coordenador**. A sessão de atendimento dura 30 minutos ou 5 contratos; depois é preciso entrar de novo.

**Passo a passo do atendimento:**

1. **Localizar freelancer** — digite os 11 números do CPF e toque em **Buscar freelancer**. Se não existir, preencha **Cadastrar freelancer** e toque em **Cadastrar e continuar**. Cadastro incompleto pede **Completar cadastro**.
2. **Novo contrato** — responda: quando o turno começou (Hoje, Ontem ou outra data), hora de início, hora de término, função, local ou evento (e descrição opcional) e **Valor do contrato**. Confira a **Prévia do contrato** e toque em **Registrar contrato**.
3. **Confira a chave PIX** — mostre a chave ao freelancer. Se estiver certa, **A chave está correta**; se não, **A chave está errada · atualizar**, escolha o tipo, digite a nova e **Salvar nova chave**.
4. **Confira e assine o documento** — o freelancer lê, assina com o dedo e toca em **Confirmar assinatura**; o operador confirma com o próprio PIN.
5. **Vai jantar?** — aparece só quando o turno dá direito (veja Dúvidas comuns). **Sim, vai jantar**, **Não vai jantar** ou **Responder depois**.

Em **Meus contratos** ficam os contratos do freelancer com os botões **Assinar**, **Jantar**, **Fazer aditivo** e **Comissão de venda**, conforme o caso.

## Fazer aditivo ou comissão de venda no tablet
<!-- telas: kiosk.index -->

Contrato assinado não pode ser editado. Quando algo muda no dia, o caminho é um documento novo, feito no tablet (**Freelancers › Assinatura (Tablet)**), em **Meus contratos** do freelancer.

**Aditivo de horário** (o turno esticou, encurtou ou mudou de local):

1. Toque em **Fazer aditivo** no contrato assinado.
2. Informe **Novo horário de início**, **Novo horário de término** e o **Local ou evento**.
3. Confira a **Prévia do aditivo** (antes → depois, com o novo valor) e toque em **Gerar aditivo**. O documento abre direto para o freelancer assinar.

O aditivo **substitui** o pagamento do contrato original: o original continua sendo assinado e fica no histórico, mas quem vai a lote e é pago é o aditivo. No contrato de valor fixo, o aditivo mantém o mesmo valor. Não cabe aditivo em contrato cancelado, já pago, já aprovado ou que esteja num lote em andamento (retire-o do lote antes).

**Comissão de venda** (só funções marcadas com "Permite comissão de venda", como Garçom):

1. Toque em **Comissão de venda** — aparece para contratos assinados dos últimos 7 dias.
2. Escolha o critério: **R$ 50,00 a cada R$ 1.000,00 vendidos** ou **5% do valor total vendido**.
3. Em **Apurar vendas**, toque em **Consultar vendas** para buscar o total no sistema de vendas (login e período vêm preenchidos e podem ser ajustados). Se o sistema de vendas estiver fora do ar, digite o valor à mão.
4. Confira a **Prévia da comissão** e toque em **Gerar comissão**.

A comissão é paga **além** do contrato do turno, e só uma por turno. Se você alterar o valor apurado, é obrigatório explicar o motivo (no mínimo 10 caracteres) — a justificativa vai escrita no termo assinado.

## Registrar falta e buscar freelancer por função no tablet
<!-- telas: kiosk.index -->

Na tela **Localizar freelancer** do tablet (**Freelancers › Assinatura (Tablet)**) há dois atalhos.

**Contratos do dia · registrar falta**

Mostra todos os contratos de um dia (**Hoje**, **Ontem** ou **Outra data**): quem estava escalado, quem assinou e quem não apareceu. No contrato de quem não veio, toque em **Marcar falta** e confirme. Regras:

- só contrato **sem nenhuma assinatura** (quem assinou compareceu);
- só turno cujo dia **já chegou**;
- aditivo não recebe falta: marque no contrato original.

A falta dá baixa no contrato (ele não vai a lote nem é pago) e **devolve a vaga do limite semanal**, então o freelancer pode ser contratado de novo naquela semana sem liberação do coordenador. No painel, a falta aparece como "Falta do freelancer".

**Precisa chamar alguém? Buscar por função**

Escolha **Hoje** ou **Amanhã** e a função. Aparecem os freelancers que já trabalharam nela, dos que mais trabalharam para os que menos, com telefone (toque para ligar), quantas vezes atuaram e a data do último serviço. Quem já tem 2 contratos na semana aparece em **Bloqueados · limite semanal**, sem telefone. Isso não proíbe o contrato: um 3º contrato continua possível com a liberação do coordenador do Comercial.

## Validar contratos (coordenador do Comercial)
<!-- telas: freelancer-services.index, kiosk.index -->

Os contratos assinados pelo freelancer precisam ser confirmados pela coordenação do Comercial antes de irem para lote. Nos contratos atuais isso é feito **pela web**, na aba **Validação** da tela **Freelancers › Serviços / Contratos** (a aba só aparece para o coordenador do Comercial).

1. Abra a aba **Validação**. A fila traz os contratos assinados pelo freelancer, liberados e ainda não validados, os mais antigos primeiro.
2. Clique num contrato. O documento abre inteiro, como é impresso.
3. **Role até o fim do documento** — só então o bloco de validação é liberado.
4. Digite **Seu PIN (6 dígitos)** e clique em **Validar contrato**.

A validação é **um contrato por vez**, sem seleção em massa, de propósito: cada documento precisa ser lido. Validado, o contrato fica disponível para lote. A validação não aparece no documento — quem assina pelo clube (CONTRATANTE) é a Diretoria, na aprovação do lote.

**Quando o contrato entra na fila:** às **08h da manhã seguinte** ao dia do turno. Até lá ainda pode haver aditivo, e a tela mostra quantos estão esperando.

**Contratos antigos (redação 1):** foram feitos para o coordenador **assinar no tablet**. Nesses, o coordenador entra em **Freelancers › Assinatura (Tablet)**, escolhe **Assinar como coordenador** e assina em **Contratos a assinar** (**Conferir e assinar**).

Não existe "recusar" na validação: se algo estiver errado, resolva com aditivo ou fale com a Gerência, que pode recusar no lote.

## Montar e enviar o lote para a gerência
<!-- telas: freelancer-services.index, kiosk.index -->

O lote junta contratos validados para a aprovação da Gerência. Pode ser montado na aba **Lotes** da tela **Freelancers › Serviços / Contratos** (coordenador de qualquer setor) ou no tablet, no modo **Assinar como coordenador**, botão **Lote para a gerência** (coordenador do Comercial).

**Na web:**

1. Abra a aba **Lotes**.
2. Em **Contratos disponíveis**, marque os contratos e clique em **Incluir no lote**. Eles passam para **Lote em montagem** (o rascunho).
3. Para tirar um contrato, clique em **Retirar**. **Descartar rascunho** devolve todos para a fila, sem perder nada.
4. Clique em **Enviar para aprovação** e confirme. Depois de enviado, o lote **não pode mais ser alterado**.

**No tablet:** em **Lote para a gerência**, selecione em **Disponíveis para incluir**, toque em **Incluir selecionados** e depois em **Enviar para aprovação** (pede o PIN).

**O que aparece em Contratos disponíveis:** contratos assinados pelo freelancer e validados pela coordenação, liberados (08h do dia seguinte ao turno) e fora de qualquer lote aberto. Contrato que a Gerência recusou volta para esta lista com o motivo da recusa, e pode entrar num lote seguinte.

Cada coordenador mantém **um rascunho por vez**. Os lotes enviados ficam listados em **Lotes enviados**. Termos de comissão aparecem destacados, porque repetem nome e data do turno com outro valor — não são lançamento duplicado.

## Aprovar o lote e registrar a decisão da diretoria
<!-- telas: freelancer-services.index -->

Esta etapa é do **coordenador do setor Gerência**, na aba **Aprovação** da tela **Freelancers › Serviços / Contratos**.

**1. Análise da Gerência**

1. A aba lista os lotes enviados, os mais antigos primeiro. Clique em **Analisar**.
2. Tudo começa marcado como **Aprovar**. Marque **Recusar** só no que deve voltar e preencha o **Motivo da recusa** (obrigatório; o coordenador vê o motivo).
3. Use **Abrir contrato assinado** para conferir um documento.
4. Clique em **Concluir análise do lote** e confirme. A decisão é definitiva.

Se sobrar algum contrato aprovado, o Lara envia na hora o e-mail à Diretoria com **dois códigos de 6 dígitos**: um aprova, o outro recusa o lote inteiro. O e-mail lista cada contrato com o **Evento / Local** e a **Descrição / Justificativa** registrados, por isso vale preencher esses campos com clareza: é o que o diretor lê para decidir. Se tudo foi recusado, nada segue para a Diretoria.

**2. Decisão da Diretoria**

O diretor não entra no Lara. Ele lê o e-mail e **dita o código** à Gerência (telefone, WhatsApp, pessoalmente). Na tela do lote, em **Aprovação da diretoria**:

1. Digite em **Código informado pelo diretor**.
2. Se quiser, preencha **Observação (opcional)** (ex.: "informado por telefone").
3. Clique em **Registrar decisão** e confirme.

O próprio código diz qual foi a decisão. Aprovado, os contratos vão para o Financeiro e, nos contratos atuais, a assinatura do diretor entra no documento. Recusado, todos os contratos voltam para a fila do coordenador.

Se o e-mail não saiu, a tela avisa; corrija e use **Reenviar e-mail** (ou **Enviar à diretoria**). Reenviar não troca os códigos.

### Cadastro da diretoria

Na aba **Diretoria** (coordenador da Gerência), informe **Nome do diretor**, **E-mail** (recebe os códigos) e **Imagem da assinatura (PNG)** e clique em **Salvar cadastro**. Sem diretor cadastrado, nenhum lote vai à Diretoria; sem a imagem, lotes com contratos atuais não seguem. Cada alteração vira um cadastro novo, e os contratos já aprovados continuam com a assinatura de quem os aprovou.

## Acompanhar onde o contrato parou
<!-- telas: freelancer-services.tracking -->

A tela **Freelancers › Acompanhamento** responde à pergunta "e o meu pagamento?". É **só de consulta**: nela não se aprova nem se paga nada.

**Como usar:**

1. Escolha o **Período**: Últimos 30 dias, Últimos 90 dias, Últimos 6 meses ou Todo o histórico. Filas em aberto aparecem sempre, mesmo fora do período.
2. Use a busca (freelancer, CPF, evento ou lote).

**O que a tela mostra:**

- **Resumo por etapa** — um cartão por fila, com a quantidade e o valor parado: Aguardando assinaturas, Aguardando o fim do dia, Aguardando entrar em lote, Em lote (rascunho), Aguardando gerência, Aguardando diretoria, Aguardando pagamento, Pagamento em processamento, Pago.
- **Lotes** — cada lote com a linha do tempo (assinaturas → gerência → diretoria → pagamento), marcando o que já foi feito, a etapa atual e as recusas. Abrindo o lote, aparece a etapa de cada contrato.
- **Ainda fora de lote** — contratos esperando assinatura ou esperando o coordenador montar o lote. É onde um contrato costuma ficar esquecido.

"Aguardando o fim do dia" quer dizer que o contrato está assinado mas só é liberado às 08h do dia seguinte ao turno — não falta ninguém assinar, falta o relógio.

Se a etapa é "Aguardando gerência" ou "Aguardando diretoria", quem resolve é a Gerência. Se é "Aguardando pagamento", é o Financeiro. Os links para o contrato e o lote só aparecem para quem tem acesso a essas telas.

## Pagar os freelancers no Financeiro
<!-- telas: freelancer-services.finance -->

A tela **Freelancers › Financeiro** abre pela lista de **lotes aprovados pela diretoria**, separados em **Aguardando pagamento** e **Quitados**, com os totais **A pagar** e **Pago** no topo.

1. Clique num lote para abrir os contratos dele.
2. Confira cada linha: freelancer, período, **Chave PIX** (com botão de copiar) e **Valor**. O aviso "⚠ Diferente da chave assinada" indica que o freelancer trocou a chave depois de assinar: o pagamento vai para a chave **atual do cadastro**; confirme com ele antes.
3. Para pagar um contrato, clique em **Dar baixa** na linha e confirme.
4. Para vários, marque as caixas (o total selecionado aparece) e use **Dar baixa nos selecionados** na barra do rodapé. **Selecionar o lote inteiro** marca todos os pendentes; a confirmação continua sendo o último passo.

A baixa é **definitiva** e fica registrada com o seu nome; não há desfazer pelo painel.

⚠️ **Atenção: com o Pix automático ligado, o botão vira "Pagar via Pix" e transfere dinheiro de verdade** da conta do clube para a chave do freelancer. A tela mostra o aviso "Pix automático ativo". Nesse modo o contrato fica **em processamento** e só vira **Pago** quando o banco confirma. Não clique duas vezes. Sai um Pix por contrato.

Outras opções: **Imprimir relação** (relação do lote em papel, com as três aprovações), **Tabela reduzida** (só pendentes, formato folha de pagamento), **Ver todos os contratos** e o aviso de **contrato(s) sem lote**, quando houver.

O total do lote no Financeiro pode ser menor que o aprovado pela Diretoria: o que a Gerência recusou não entra.

## Dúvidas comuns

### Por que o freelancer não aparece para eu gerar o contrato?
<!-- telas: freelancer-services.index, freelancers.index -->

Ele está com **cadastro incompleto**: na lista de Novo Serviço aparece "— cadastro incompleto" e fica desabilitado; no tablet aparece "Cadastro incompleto. Complete os dados do freelancer antes de gerar o contrato." Abra o cadastro em **Freelancers › Freelancers**, veja o aviso "Faltam: …" no topo, preencha e clique em **Salvar Alterações**. RG, telefone, nacionalidade, estado civil e endereço são obrigatórios para contrato.

### Por que o contrato não aparece para validar nem para incluir no lote?
<!-- telas: freelancer-services.index -->

Quase sempre é a regra das **08h do dia seguinte**: o contrato só é liberado para a coordenação na manhã seguinte ao dia do turno, porque até lá ainda pode ter aditivo. A mensagem é "O turno de dd/mm/aaaa ainda pode receber aditivo. Este contrato é liberado para a coordenação em … às 08:00." Outros motivos: o freelancer ainda não assinou; o contrato ainda não foi validado (só validado entra em lote); ele já está noutro lote; foi cancelado, marcado como falta ou substituído por aditivo (nesse caso quem vai a lote é o aditivo).

### O tablet diz "PIN inválido" ou "Usuário sem PIN". O que faço?

O PIN é o código de 6 dígitos de quem está **operando** o tablet (não do freelancer). Para criar ou trocar, vá em **Perfil › PIN de Assinatura**; a matrícula também precisa estar no seu perfil. Se aparecer "Este usuário não tem acesso ao kiosk", falta a permissão "Assinatura no tablet (kiosk)": fale com o coordenador do seu setor: ele coloca você num setor que já tem a permissão (tela **Meu setor**) ou pede à TI ou à Gerência que a liberem (tela **Setores**). Na validação pela web, a mensagem "Você ainda não tem PIN definido" tem a mesma solução.

### Por que a sessão do tablet encerrou sozinha?
<!-- telas: kiosk.index -->

A sessão de atendimento dura **30 minutos** ou **5 contratos** ("Sessão encerrada: limite de 5 contratos. Entre novamente."). É uma proteção para o tablet não ficar aberto no nome de alguém. Entre de novo com matrícula e PIN. Retirar a permissão de alguém também derruba a sessão dele na hora.

### Não consigo editar nem cancelar o contrato. Por quê?
<!-- telas: freelancer-services.index -->

Porque ele **já tem assinatura**. Contrato assinado não muda: "Este contrato já possui assinatura e por isso não pode mais ser alterado nem cancelado." Se o horário ou o local mudaram, faça um **aditivo** no tablet. Se a pessoa não veio e nada foi assinado, registre a **falta** no tablet. Cancelar pelo painel também exige ser coordenador de algum setor.

### O tablet diz que a chave PIX mudou ou que o texto do contrato foi atualizado
<!-- telas: kiosk.index -->

"A chave PIX do freelancer mudou desde a conferência" aparece quando alguém alterou a chave enquanto a tela estava aberta: a conferência recomeça, para o documento citar a chave certa. "O texto do contrato foi atualizado desde que este documento foi aberto" significa que entrou um texto novo de contrato: o documento recarrega e deve ser conferido de novo com o freelancer antes de assinar.

### O que significa "Assinado … após o início" ou "Sem assinatura · turno começou"?
<!-- telas: freelancer-services.index -->

O contrato deve ser assinado **antes do turno**, com tolerância de 30 minutos. 🕒 indica que o freelancer assinou depois disso — é só informativo, não bloqueia nada. ⏳ é mais grave: o turno começou e o freelancer **nunca assinou**. Sem assinatura o contrato não entra em lote nem é pago; colha a assinatura no tablet assim que possível, ou marque a falta se ele não veio.

### Quem tem direito ao jantar?
<!-- telas: kiosk.index -->

O freelancer com turno de **6 horas ou mais** que esteja trabalhando em **algum momento entre 17:30 e 18:30** (sair às 18:00 ou entrar às 18:00 conta; sair às 17:30 em ponto ou entrar às 18:30 em ponto não conta). Comissão de venda não pede jantar. A pergunta "Vai jantar?" aparece no tablet logo depois da assinatura e a resposta é **registrada uma vez só** — mudança de ideia se resolve direto com a cozinha. No painel, a resposta aparece na tela do contrato.

### O freelancer pode entrar no clube só com o contrato?

Sim. Na portaria, o porteiro digita o CPF no Monitor de Acesso e o contrato libera a entrada de **30 minutos antes do início até o horário de término** do turno. A assinatura não é exigida para entrar (ela é colhida lá dentro, no tablet). Contrato cancelado ou substituído por aditivo não libera — vale o aditivo. A foto mostrada na portaria é a do cadastro do freelancer.

### O e-mail não foi para a diretoria. E agora?
<!-- telas: freelancer-services.index -->

A aprovação da Gerência fica gravada mesmo assim, e a tela avisa "O e-mail para a diretoria NÃO foi enviado". Confira na aba **Diretoria** se há diretor cadastrado com e-mail e com a **imagem da assinatura** (sem ela, lotes com contratos atuais não seguem). Depois, na tela do lote, use **Enviar à diretoria** ou **Reenviar e-mail**. Os códigos continuam os mesmos.

### Digitei o código da diretoria e deu "Código não confere"
<!-- telas: freelancer-services.index -->

Confirme com o diretor o código do **lote certo** (o número do lote está no e-mail). Por segurança, a mensagem não diz qual dos dois códigos falhou, e há um limite de tentativas por minuto. O código não aparece em lugar nenhum do Lara — só no e-mail do diretor.

### Cliquei em Dar baixa e o contrato ficou "em processamento"
<!-- telas: freelancer-services.finance -->

Isso acontece quando o **Pix automático** está ligado: o Lara enviou o Pix e espera a confirmação do banco para marcar **Pago**. Não clique de novo (o sistema recusa um segundo envio). Se aparecer **Rejeitado** ou **Falhou**, nada foi transferido e dá para tentar de novo. Se aparecer **Conferir no banco**, a resposta do banco não chegou e o Pix pode ter sido feito: **não refaça a baixa** e avise a TI.

### Por que o total do lote no Financeiro é menor que o aprovado?
<!-- telas: freelancer-services.finance -->

Porque os contratos que a **Gerência recusou** continuam ligados ao lote, mas não são pagáveis e ficam fora da tela e da soma. Também saem os contratos substituídos por aditivo (paga-se o aditivo). Use **Imprimir relação** para conferir contrato a contrato.

### Não vejo as abas Validação, Lotes, Aprovação ou Diretoria
<!-- telas: freelancer-services.index -->

Elas dependem de cargo, além da permissão "Serviços: valores, documentos, registro e lotes": **Validação** é do coordenador do Comercial; **Lotes**, de coordenador de qualquer setor; **Aprovação** e **Diretoria**, do coordenador da Gerência. O acesso total ao sistema não dá esses cargos. O vínculo como coordenador é feito na tela **Setores**.
