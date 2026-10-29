---
modulo: Disparo de E-mails
palavras: e-mail, email, disparo, disparar, campanha, newsletter, e-mail marketing, mala direta, lista de e-mails, enviar e-mail em massa, mandar e-mail para todos, comunicado, convite, planilha, csv, xlsx, html, modelo de e-mail, imagem no e-mail, variável, nome do sócio, teste, agendar, pausar, cancelar, descadastrar, cancelar inscrição, não enviar, spam, remetente, falhou
---

# Disparo de E-mails — guia de uso

O módulo **Disparo de E-mails** manda um e-mail para uma lista grande de pessoas: convites, comunicados, novidades do clube. Você monta o e-mail (com texto, botões e imagens), importa a lista em planilha e clica em Enviar.

O envio é feito **pelo servidor do Lara, aos poucos** (por padrão, até 120 e-mails por minuto). Depois de clicar em Enviar, **pode fechar a tela ou desligar o computador**: o envio continua sozinho até o último destinatário. Quando quiser, volte à campanha para ver o andamento.

## Quem faz o quê

| Para | Permissão (como aparece na tela Setores) |
|---|---|
| Criar e editar campanhas, importar listas, mandar teste, enviar, pausar, cancelar e manter o **Não enviar para** | Disparo de E-mails › Criar campanhas, importar listas de destinatários, enviar e manter o "Não enviar para" |

Quem libera é a TI ou a Gerência, na tela **Setores** (ou só para uma pessoa, na tela **Usuários**); o coordenador do setor pede a liberação e coloca a equipe no setor pela tela **Meu setor**. Sem a permissão, o item **Disparo de E-mails** não aparece no menu.

## Criar uma campanha
<!-- telas: email-campaigns.index -->

Na tela **Disparo de E-mails › Campanhas**, clique em **Nova campanha** e preencha:

1. **Nome da campanha**: só para a equipe se achar (ex.: "Convite Festa Junina 2026"). O destinatário não vê.
2. **Assunto**: o que aparece na caixa de entrada. Aceita variáveis, como `{{primeiro_nome}}, venha para a festa!`.
3. **E-mail do remetente**: escolha um da lista. Só aparecem os endereços liberados pela TI no servidor de e-mail — outro endereço faria o e-mail cair no spam.
4. **Nome do remetente** (ex.: "Clube dos Funcionários") e **Responder para**: o e-mail que recebe as respostas e os pedidos de descadastro. Em branco, as respostas vão para o remetente.
5. Escreva o **Conteúdo** (veja a seção sobre o editor).
6. Clique em **Salvar e importar a lista**. A campanha fica como **Rascunho** e abre a tela dela, onde você importa a lista.

Nada é enviado ao salvar. O envio só começa quando você clica em **Enviar** na tela da campanha.

## Montar o e-mail no editor
<!-- telas: email-campaigns.index -->

Na tela da campanha (**Editar**, ou na **Nova campanha**), o bloco **Conteúdo (HTML)** tem o código do e-mail à esquerda e a **prévia** à direita, que se atualiza enquanto você digita.

- **Modelo inicial**: um e-mail pronto nas cores do clube, com título, texto, botão e rodapé. É o jeito mais fácil de começar: troque os textos e apague o que não usar.
- **Título**, **Parágrafo**, **Botão** e **Divisória**: inserem o bloco onde está o cursor. No botão, troque o texto e o endereço em `href="..."`.
- **Imagem**: escolha um arquivo PNG, JPG, GIF ou WEBP de até 1 MB. A imagem vai **dentro do e-mail**, então chega certinha para quem recebe.
- **Abrir arquivo .html**: carrega um e-mail feito em outro programa (por exemplo, exportado de um site de modelos de e-mail).
- **Computador** e **Celular**: mudam a largura da prévia.
- **Expandir**: abre a prévia na tela inteira, para ver o e-mail em tamanho real. **Fechar** (ou a tecla Esc) volta ao editor. A prévia da tela da campanha também tem o botão **Expandir**.

Dicas: use o modelo inicial como base, porque os programas de e-mail (Gmail, Outlook) só respeitam HTML simples, em tabelas e com o estilo escrito dentro das tags. Script não é enviado. A prévia é uma aproximação: **sempre mande um teste** antes.

## Personalizar com o nome e outras colunas (variáveis)
<!-- telas: email-campaigns.index -->

Variáveis são trocadas pelo dado de cada destinatário na hora do envio. Escreva o nome da coluna entre chaves duplas, no assunto ou no texto:

- `{{nome}}`: o nome completo da coluna Nome;
- `{{primeiro_nome}}`: só o primeiro nome ("Olá, {{primeiro_nome}}!");
- `{{email}}`: o e-mail da pessoa;
- **qualquer outra coluna da planilha**, pelo nome do cabeçalho em minúsculas, sem acento e com `_` no lugar dos espaços: "Título" vira `{{titulo}}`, "Número da mesa" vira `{{numero_da_mesa}}`.

No editor, as variáveis da lista aparecem como botões: clique para inserir no cursor. Se o texto usar uma variável que a lista **não tem**, aparece um aviso em amarelo e o **Enviar fica bloqueado**: ela sairia vazia para todo mundo. Corrija o texto ou importe uma lista com essa coluna. Para quem a coluna estiver vazia na planilha, a variável sai em branco.

## Importar a lista de destinatários
<!-- telas: email-campaigns.index -->

Na tela da campanha, bloco **Destinatários**, use **Importar lista (.csv ou .xlsx)**:

1. Prepare a planilha com uma coluna **E-mail** (também valem "Email", "Endereço de e-mail"…). A coluna **Nome** é opcional; as outras colunas viram variáveis do texto. Também serve uma lista **só com os e-mails, um por linha, sem cabeçalho**. Há modelos para baixar em .xlsx e .csv no próprio bloco.
2. Escolha o arquivo e clique em **Importar**. Pode ser o CSV salvo pelo Excel (com ponto e vírgula) ou pelo Google Planilhas (com vírgula).
3. A tela mostra quantos entraram. **E-mails inválidos ficam de fora** e aparecem listados em vermelho com o número da linha; **repetidos** são ignorados. O resto entra normalmente: não precisa corrigir tudo para seguir.

Se a campanha já tem lista, escolha **Acrescentar à lista atual** (para juntar várias planilhas) ou **Substituir a lista atual**. Uma célula com dois e-mails ("ana@x.com; beto@x.com") vira dois destinatários. O limite é de 50.000 destinatários por campanha. Para recomeçar, use **Apagar lista** (só em rascunho).

## Mandar um e-mail de teste
<!-- telas: email-campaigns.index -->

Na tela da campanha, bloco **Enviar**, o campo **E-mail de teste** já vem com o seu e-mail. Clique em **Mandar teste**.

O teste sai **na hora**, com **[TESTE]** no começo do assunto e com os dados do **primeiro destinatário** da lista (nome e colunas), para você ver as variáveis preenchidas. Ele não conta como envio de ninguém da lista.

Abra o teste no celular e no computador, confira os links e as imagens. Se aparecer um erro em vermelho ao mandar o teste, o servidor de e-mail recusou: leia a mensagem e, se não souber resolver, chame a TI antes de enviar a campanha.

## Enviar agora ou agendar
<!-- telas: email-campaigns.index -->

Na tela da campanha, bloco **Enviar**, em **Quando**:

- **Agora**: clique em **Enviar para N** e confirme. Os e-mails começam a sair no próximo minuto.
- **Agendar**: escolha **Data e hora** e clique em **Agendar envio**. A campanha fica **Agendada** e começa sozinha na hora marcada. Para mudar algo antes, clique em **Desfazer agendamento**: ela volta a rascunho.

O botão mostra quanto tempo o envio deve levar (por padrão, 120 e-mails por minuto: 6.000 levam cerca de 50 minutos). **Pode fechar a tela**: o envio continua. Depois de começar, o texto, o assunto e o remetente só mudam com a campanha **pausada**.

## Acompanhar, pausar, retomar e cancelar
<!-- telas: email-campaigns.index -->

Na tela da campanha, o quadro de andamento mostra **Destinatários**, **Enviados**, **Na fila**, **Falharam** e **Descadastrados**, com a barra de progresso. Enquanto envia, ele se atualiza sozinho.

- **Pausar**: para de enviar em segundos. Quem ainda não recebeu fica na fila. Pausada, dá para editar o texto e **acrescentar** pessoas à lista (substituir a lista não, porque parte já recebeu).
- **Retomar o envio**: continua de onde parou, sem repetir quem já recebeu.
- **Cancelar campanha**: encerra de vez. Quem estava na fila não recebe, e não dá para retomar (use **Duplicar** para mandar de novo).

Na lista de destinatários, filtre por situação (**Na fila**, **Enviado**, **Falhou**, **Não enviado (descadastrado)**) ou busque por e-mail ou nome. **Baixar resultado (.csv)** traz a planilha com a situação de cada um.

## Quando um envio falha
<!-- telas: email-campaigns.index -->

Na tela da campanha, quem não recebeu aparece como **Falhou**, com a mensagem do servidor de e-mail logo abaixo:

- **Caixa inexistente ou endereço errado**: falha na hora e não adianta tentar de novo; corrija o e-mail na sua planilha.
- **Caixa cheia ou recusa momentânea**: o Lara tenta de novo até 3 vezes antes de marcar Falhou.
- **Servidor de e-mail fora do ar**: ninguém é marcado como falha. Aparece o aviso "Servidor de e-mail indisponível" e o envio tenta de novo sozinho a cada minuto.

Se muitos envios seguidos forem recusados, a campanha é **pausada automaticamente** com o motivo em amarelo (normalmente é problema do remetente, não da lista). Chame a TI, corrija e clique em **Retomar o envio**. Depois de corrigir um problema geral, **Reenviar as que falharam** devolve à fila todos os que falharam.

## Não enviar para (descadastro)
<!-- telas: email-suppressions.index -->

Na tela **Disparo de E-mails › Não enviar para** ficam as pessoas que pediram para não receber mais. **Nenhuma campanha manda para esses endereços**, nem as que já estão enviando: quem estiver na lista aparece como **Não enviado (descadastrado)**.

Para incluir: cole os e-mails no campo **E-mails** (um por linha, ou separados por vírgula ou ponto e vírgula), escreva o **Motivo** se quiser (ex.: "respondeu DESCADASTRAR") e clique em **Não enviar mais**. Para tirar alguém da lista, clique na lixeira da linha: a pessoa volta a receber.

Os pedidos chegam por e-mail: o rodapé do modelo inicial pede para responder com DESCADASTRAR, e o "cancelar inscrição" do Gmail e do Outlook manda um e-mail para o endereço de **Responder para**. Quem cuida das campanhas lança esses endereços aqui.

## Duplicar e excluir uma campanha
<!-- telas: email-campaigns.index -->

Na tela da campanha, **Duplicar** cria uma cópia em **Rascunho** com o mesmo texto e a **mesma lista**, toda de volta à fila. Serve para mandar um lembrete ou repetir a campanha; ajuste o que precisar antes de enviar.

**Excluir** apaga a campanha e a lista inteira, sem volta. Só aparece em rascunho, concluída ou cancelada. Para excluir uma campanha que está enviando, cancele primeiro.

## Dúvidas comuns

### Posso fechar a tela depois de clicar em Enviar?
<!-- telas: email-campaigns.index -->

Pode. O envio é feito pelo servidor do Lara, não pelo seu navegador. Feche a tela, desligue o computador: a campanha continua até o último destinatário. Para acompanhar, volte em **Disparo de E-mails › Campanhas** e abra a campanha.

### Por que o envio demora?

Os e-mails saem aos poucos (por padrão, 120 por minuto) para respeitar o limite do servidor de e-mail. Mandar tudo de uma vez faria o provedor bloquear a conta e jogar os e-mails no spam. Uma lista de 20 mil pessoas leva umas 3 horas. A tela da campanha mostra a estimativa.

### Por que o botão Enviar está apagado?
<!-- telas: email-campaigns.index -->

Há dois motivos: a lista ainda não foi importada (não há ninguém **Na fila**), ou o texto usa uma variável que a lista não tem (aparece um aviso em amarelo no topo). Importe a lista ou corrija a variável.

### A planilha foi recusada: "A lista não tem coluna de e-mail"
<!-- telas: email-campaigns.index -->

Dê à coluna dos endereços o cabeçalho **E-mail** e importe de novo. Se a planilha tiver só os e-mails, apague a linha de cabeçalho e deixe um e-mail por linha. Baixe o modelo .xlsx do bloco de importação para conferir o formato.

### Os acentos da planilha saíram errados
<!-- telas: email-campaigns.index -->

O Lara entende o CSV salvo pelo Excel e pelo Google Planilhas. Se mesmo assim os acentos vierem trocados, salve a planilha como **.xlsx** e importe esse arquivo.

### Os e-mails estão caindo no spam
<!-- telas: email-campaigns.index -->

Confira se o remetente é um dos endereços da lista (os liberados pela TI), se o e-mail tem texto além de imagens, e evite assunto só em maiúsculas ou cheio de "!!!". Mande um teste e veja se ele também cai no spam; se cair, fale com a TI.

### Alguém pediu para não receber mais. O que eu faço?
<!-- telas: email-suppressions.index -->

Lance o e-mail da pessoa em **Disparo de E-mails › Não enviar para**. A partir daí nenhuma campanha manda para ela, inclusive as que estão enviando agora.

### Errei o texto e a campanha já está enviando
<!-- telas: email-campaigns.index -->

Clique em **Pausar**, depois em **Editar**, corrija e salve, e clique em **Retomar o envio**. Quem já recebeu ficou com a versão antiga; os que estavam na fila recebem a corrigida. Se o erro for grave, cancele e use **Duplicar** para mandar uma correção a todos.
