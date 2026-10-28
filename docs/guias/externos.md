---
modulo: Externos
palavras: terceirizado, empresa, prestador, parceiro, portaria, guarita, acesso, entrada, liberar, bloqueado, regra de acesso, monitor de acesso, liberação pontual, freelancer, ingresso, histórico, uber, carro de aplicativo, motorista, 99, fila da portaria, CPF
---

# Externos — guia de uso

O módulo **Externos** controla quem **não é sócio** e precisa entrar no clube: funcionários de empresas terceirizadas (prestadores), freelancers contratados, pessoas com uma liberação pontual do dia, portadores de ingresso de evento e carros de aplicativo (Uber e similares) pedidos pelo sócio no WhatsApp.

No Lara ficam o cadastro das empresas e dos seus funcionários, as **regras de acesso** (dias e horários em que cada empresa ou pessoa pode entrar), o **Monitor de Acesso** (onde se consulta e se registra uma entrada), a **Liberação Pontual**, o **Histórico** de todas as entradas e a tela de **Carros de Aplicativo**.

A guarita usa um aplicativo próprio que consulta as mesmas informações do Lara: tudo o que é cadastrado aqui vale lá, e toda entrada registrada lá aparece no Histórico do Lara.

## Quem faz o quê

| Para | Permissão (como aparece na tela Setores) |
|---|---|
| Ver e cadastrar empresas, funcionários terceirizados e regras (**Externos › Empresas**) | nenhuma — aberto a todos os usuários logados |
| Consultar e registrar entradas no **Externos › Monitor de Acesso** | nenhuma — aberto a todos os usuários logados |
| Criar e cancelar liberações pontuais (**Externos › Liberação Pontual**) | Externos › Liberação pontual |
| Ver o histórico de entradas (**Externos › Histórico**) | Externos › Histórico de acessos |
| Ver os pedidos de carro de aplicativo e liberar a fila da portaria (**Externos › Carros de Aplicativo**) | Externos › Carros de aplicativo e fila da portaria |

Quem libera é a TI ou a Gerência, na tela **Setores** (ou só para uma pessoa, na tela **Usuários**); o coordenador do setor pede a liberação e coloca a equipe no setor pela tela **Meu setor**. Se um item do menu ou um botão (como "Liberação Pontual", "Ver Histórico" ou "Aguardando Motorista" no Monitor) não aparece para você, é porque o seu setor não tem a permissão correspondente.

## Cadastrar uma empresa terceirizada
<!-- telas: company.index -->

Na tela **Externos › Empresas**, clique em **Nova Empresa**. Abre o formulário "Cadastrar Parceiro Terceirizado".

1. Preencha **Nome da Empresa** (obrigatório).
2. Preencha **E-mail Corporativo** (obrigatório).
3. Se quiser, informe **Telefone**, **Endereço Completo** e **Descrição / Sobre a Empresa**.
4. Se tiver, envie o logo da empresa na área de imagem (JPG, PNG, GIF ou SVG, até 2 MB).
5. Salve. Aparece a mensagem "Parceiro Terceirizado criado com sucesso." e a empresa entra na lista.

Na lista, cada empresa aparece num cartão com o selo **Acesso** (alguém da empresa pode entrar agora) ou **Bloqueado**. Avisos como "Nenhuma regra cadastrada" ou "Nenhuma regra vigente" indicam que a empresa ainda não tem regra que libere a entrada — **sem regra de inclusão vigente ninguém da empresa entra**. Veja a seção sobre regras de acesso.

Os cartões têm duas sanfonas: **Funcionários** (com o selo Acesso/Bloqueado de cada pessoa e o atalho **Adicionar Funcionário**) e **Regras** (com o atalho **Nova Regra**).

### Editar ou excluir uma empresa

Clique no nome ou na imagem da empresa para abrir o **Perfil da Empresa**. No alto há **Editar Dados** (mesmo formulário do cadastro) e **Excluir** (pede confirmação: "Deseja realmente remover esta empresa?"). O histórico de entradas já registradas não se perde.

## Cadastrar um funcionário de empresa terceirizada
<!-- telas: company.index -->

Há dois caminhos na tela **Externos › Empresas**:

- **Novo Funcionário** (botão no alto da lista): abre o "Cadastro Rápido de Funcionário", em que você escolhe a **Empresa** na lista.
- **Adicionar Funcionário**: dentro do cartão da empresa ou na aba **Funcionários** do Perfil da Empresa; a empresa já vem escolhida.

Campos do formulário:

1. **Foto de Identificação** (opcional): clique em **Ativar Câmera** e depois **Tirar Foto**, ou use **Importar Foto** para enviar um arquivo. **Tentar Novamente** descarta a foto e reabre a câmera.
2. **Nome Completo** (obrigatório).
3. **E-mail** (opcional).
4. **CPF / Documento**: digite só os números; a tela formata e avisa "✓ CPF válido", "✗ CPF inválido" ou "✗ CPF incompleto". **O CPF é o que a portaria digita para achar a pessoa** — sem ele, o funcionário só é encontrado pelo nome da empresa.
5. **Cargo / Função** (obrigatório).
6. **Telefone** (opcional).

Ao salvar aparece "Funcionário adicionado com sucesso.". Para encontrar um funcionário depois, digite pelo menos duas letras na busca da tela Empresas: aparece a seção "Funcionários encontrados", com atalho para o perfil da pessoa e para a empresa dela.

No **Perfil do Funcionário** ficam os botões **Editar** e **Excluir**, quem criou e quem alterou por último o cadastro, e as regras individuais da pessoa.

## Criar regras de acesso: dias e horários em que a empresa pode entrar
<!-- telas: company.index -->

As regras dizem **quando** os funcionários de uma empresa podem entrar. Em **Externos › Empresas**, abra a empresa e vá na aba **Regras de Acesso** (ou use **Nova Regra** no cartão da empresa). Clique em **Nova Regra**.

1. **Tipo de Regra**: **Inclusão** ("Permite o acesso conforme os critérios") ou **Exclusão** ("Bloqueia o acesso durante o período").
2. **Período de Vigência**: **Data de Início** (obrigatória) e **Data de Término (Opcional)** — vazia, a regra vale por tempo indeterminado.
3. **Dias da Semana**: marque os dias (vêm todos marcados).
4. **Horário de Acesso**: **Horário de Início** e **Horário de Término**. Vazios, a regra vale o dia todo.
5. **Observações / Motivo**: texto livre para explicar a regra.

Ao salvar aparece "Regra de acesso criada com sucesso.".

### Como o Lara decide se a pessoa entra

No momento da consulta, para cada funcionário:

1. Precisa existir pelo menos **uma regra de Inclusão** valendo agora (data, dia da semana e horário). Se não houver, o acesso é **negado**.
2. Se houver **alguma regra de Exclusão** valendo agora, o acesso é **negado**, mesmo com inclusão.
3. Passou nos dois, o acesso é **permitido**.

Exemplo: inclusão de segunda a sexta, das 7h às 18h, e uma exclusão para o feriado do dia 15 — no dia 15 a empresa fica bloqueada.

### Regras vigentes, expiradas e exclusão em lote

Na aba Regras de Acesso há as sub-abas **Vigentes**, **Expiradas** e **Todas**. Cada regra mostra tipo, vigência, dias, horário e quem criou ou alterou. Use o lápis para editar e a lixeira para excluir. Para apagar várias de uma vez, marque as caixas (ou **Selecionar visíveis**) e clique em **Excluir N regra(s)**. Excluir regra não apaga entradas já registradas.

## Dar uma regra só para um funcionário (regra individual)
<!-- telas: company.index -->

Às vezes uma pessoa tem horário diferente do resto da empresa. Em **Externos › Empresas**, abra a empresa, vá na aba **Funcionários** e clique no ícone para ver o funcionário. No **Perfil do Funcionário**, clique em **Nova Regra Individual**. O formulário é o mesmo da regra da empresa (tipo, vigência, dias da semana, horário e observações).

Atenção: **quando o funcionário tem regra individual, as regras gerais da empresa deixam de valer para ele** — só as regras individuais decidem. A tela avisa: "Este funcionário possui regras individuais. As regras gerais da empresa não se aplicam a ele". Por isso, a regra individual precisa cobrir todos os dias e horários em que a pessoa deve entrar, não só a exceção.

Se o funcionário não tem regra individual vigente, a tela mostra "Nenhuma regra individual vigente. Este funcionário segue as regras gerais da empresa." As regras individuais vencidas ficam em **Regras Fora de Vigência**, só para consulta.

Na aba Regras de Acesso da empresa, a regra individual aparece com a etiqueta **Exclusiva: nome do funcionário**.

## Consultar ou registrar uma entrada no Monitor de Acesso
<!-- telas: company.access.monitor -->

A tela **Externos › Monitor de Acesso** é onde se confere se alguém pode entrar e se registra a entrada. No campo "CPF do funcionário/freelancer, código do ingresso ou nome da empresa", digite:

- um **CPF** (com ou sem pontos) — o Lara procura ao mesmo tempo entre terceirizados, freelancers, liberações pontuais de hoje e ingressos de evento;
- o **código do ingresso** (começa com ING-; o leitor de QR digita sozinho);
- ou parte do **nome da empresa** — aparecem todos os funcionários dela.

Depois escolha:

- **Consultar**: só mostra o resultado, **não grava** nada. A etiqueta "Apenas Consulta" aparece.
- **Registrar Acesso** (ou Enter): valida e **grava a entrada** no Histórico, com o resultado (permitido ou negado). A etiqueta "Registrado" aparece.

Cada pessoa encontrada aparece com **✓ Permitido** ou **✗ Negado** e o motivo. Na busca por empresa, a etiqueta é "Selecione o funcionário": clique em **Registrar** na linha de cada pessoa que está entrando (o registro é por pessoa). O botão vira "✓ Registrado" ou "✗ Registrado".

Regras de cada tipo, em resumo:

- **Freelancer**: não tem regra; entra a partir de **30 minutos antes** do início do serviço contratado até o término. Sem serviço no horário: "Sem serviço registrado para este horário."
- **Liberação pontual**: vale **uma entrada, só hoje**. Consultar não gasta; registrar gasta.
- **Ingresso**: uma entrada por ingresso, de algumas horas antes do evento até o fim.

No pé da tela, **Consultas desta sessão** lista as últimas 10 buscas, marcadas "cons" (consulta) ou "reg" (registro). Os botões **Aguardando Motorista**, **Liberação Pontual** e **Ver Histórico** no alto só aparecem para quem tem a permissão de cada tela.

## Liberar a entrada de alguém sem cadastro (Liberação Pontual)
<!-- telas: company.one-off.index -->

Para o caso extraordinário: uma pessoa precisa entrar agora e não dá tempo de fazer o cadastro de terceirizado (por exemplo, um técnico chamado às pressas). Em **Externos › Liberação Pontual**, clique em **Nova Liberação**.

1. Foto (opcional): câmera ou importação.
2. **Nome Completo** (obrigatório).
3. **CPF** (obrigatório e conferido) — é por ele que a portaria encontra a liberação.
4. **Motivo** (obrigatório) — aparece para a portaria e fica no histórico. Ex.: "Técnico da empresa de elevadores — autorizado por telefone pela Gerência."
5. Salve. Aparece "Liberação pontual criada para Fulano: uma entrada, válida só hoje.".

Regras importantes:

- Vale para **uma entrada** e **só no dia em que foi criada**. Virou o dia, vence sozinha.
- Não liga a pessoa a nenhuma empresa. Se ela vai voltar outras vezes, faça o cadastro formal de terceirizado.
- Só pode haver **uma liberação disponível por CPF** no dia. Depois de usada, dá para criar outra.
- Na portaria nada muda: basta digitar o CPF no Monitor de Acesso. Quem criou aparece como "autorizada por".

Atalho: quando o Monitor de Acesso responde "Não Encontrado" para um CPF, ou todas as linhas vêm negadas, aparece o botão **Criar liberação pontual**, já com o CPF preenchido.

### Acompanhar e cancelar

A tela lista as liberações do dia (mude o **Dia** e clique em **Filtrar** para ver outro dia; **Hoje** volta). Os contadores mostram **Liberações no dia**, **Aguardando entrada** e **Utilizadas**. Cada linha tem a situação: Disponível, Utilizada (com "entrou às HH:MM"), Cancelada ou Vencida. Só a liberação **ainda não usada** tem o botão **Cancelar**; depois de cancelada, a portaria deixa de encontrá-la.

## Ver o histórico de entradas da portaria
<!-- telas: company.access.logs -->

Em **Externos › Histórico** ficam todas as consultas registradas — do Monitor de Acesso do Lara e do aplicativo da guarita. No alto, três contadores do dia: **Registros hoje**, **Permitidos hoje** e **Negados hoje**.

Há duas abas: **Todos os Acessos** e **Carros de Aplicativo** (só as entradas de Uber e similares, com a coluna **Imagem** do print da corrida e a coluna **Solicitante**).

Filtros:

- **Buscar nos acessos**: placa, documento, nome, empresa ou motivo.
- **Empresa** e **Status** (Permitido / Negado) — só na aba Todos os Acessos.
- **De** e **Até**: período.

Clique em **Filtrar**; o **✕** limpa tudo.

Colunas: **Data / Hora**, **Alvo** (o que foi digitado), **Empresa**, **Funcionário**, **Status** e **Motivo**. Quando não é terceirizado, a coluna Empresa mostra uma etiqueta: **Freelancer** (com o horário e o local do serviço), **Liberação Pontual** (com o motivo e quem autorizou), **Ingresso** (com código e evento), **Carro de Aplicativo** ou **Motorista de App**.

Motivos mais comuns: "Acesso liberado pelas regras", "Bloqueado pelas regras", "Funcionário não encontrado", "Empresa não encontrada", "Liberado pelo contrato de freelancer", "Freelancer sem serviço no horário", "Liberação pontual já utilizada", "Liberado na fila da portaria" e "Liberado na fila, fora do prazo".

## Acompanhar os pedidos de carro de aplicativo (Uber e similares)
<!-- telas: company.uber.requests -->

O sócio avisa pelo WhatsApp do clube que vai chegar de carro de aplicativo: informa matrícula ou CPF, nome, local do clube, placa e manda o print da corrida. O Lara guarda o pedido e confere se o sócio existe. Em **Externos › Carros de Aplicativo** há três abas: **Pedidos**, **Aguardando Motorista** e **Acessos Realizados**.

Na aba **Pedidos** aparecem todos os pedidos, em qualquer situação. Contadores do dia: **Pedidos hoje**, **Aguardando acesso**, **Concluídos hoje** e **Expirados hoje**.

Filtros: **Busca livre** (nome, placa, matrícula/CPF, telefone ou local de uma vez), **Status**, **Conferência do sócio**, **Nome**, **Placa**, **Matrícula / CPF**, **Telefone**, **Local do clube** e **Dia (de / até)**, com atalhos de período **Hoje**, **Ontem**, **7 dias** e **30 dias**. **Limpar** tira os filtros.

Situações do pedido: "Aguardando matrícula/CPF", "Aguardando nome", "Aguardando local", "Aguardando placa", "Aguardando print" (o sócio ainda está preenchendo), "Aguardando acesso do motorista" (pronto), "Concluído" (o carro entrou) e "Expirado".

A coluna **Conferência** mostra: "Sócio confere", "Nome/matrícula/CPF não confere", "Não foi possível conferir" ou "Funcionário bloqueado" (quando quem pediu é funcionário desligado, de férias ou afastado). A conferência **é só um aviso**: não impede a liberação.

A aba **Acessos Realizados** lista os pedidos que efetivamente entraram, com busca e filtros de status e período.

## Liberar o carro de aplicativo na fila da portaria
<!-- telas: company.uber.requests -->

Em **Externos › Carros de Aplicativo**, aba **Aguardando Motorista** (também pelo botão de mesmo nome no Monitor de Acesso), aparecem os pedidos prontos, esperando o carro chegar. Cada cartão traz o print da corrida, nome, matrícula/CPF, telefone, local, a conferência do sócio, a placa e a contagem "expira em ..." ou "vencido há ...".

1. Compare o print, o nome e a placa com o carro e o motorista à sua frente. Use a busca para achar pelo nome, placa, matrícula ou telefone.
2. Se a placa digitada no WhatsApp estiver errada, clique em **Corrigir placa** e informe a correta (formato ABC1234 ou ABC1D23).
3. Clique em **Liberar acesso** e confirme. O botão vira "✓ Liberado", a entrada vai para o Histórico e o **sócio recebe no WhatsApp o aviso de que o carro chegou**.

A liberação é feita pelo pedido escolhido, não pela placa: mesmo com a placa errada, o pedido certo é liberado.

O pedido vale por **30 minutos**. Os que passaram disso ficam em **Validade vencida**, com o botão **Liberar mesmo fora do prazo** — o histórico registra que foi fora do prazo. Em **Preenchendo agora no WhatsApp** aparecem os pedidos que o sócio ainda não terminou: só para consulta.

A tela se atualiza sozinha a cada 30 segundos (desmarque **Atualizar sozinho** para parar) e tem o botão **Atualizar**.

## Dúvidas comuns

### Por que o terceirizado foi barrado na portaria?
<!-- telas: company.access.logs -->

Abra **Externos › Histórico** e procure pelo CPF ou nome. Se o motivo for "Bloqueado pelas regras", a empresa (ou o funcionário) não tem uma regra de **Inclusão** valendo naquele dia e horário, ou tem uma regra de **Exclusão** valendo. Confira em **Externos › Empresas**, no perfil da empresa, a aba **Regras de Acesso** (sub-aba **Vigentes**): a data de término pode ter passado, o dia da semana pode estar desmarcado ou o horário pode não cobrir a chegada. Lembre: se o funcionário tem **regra individual**, só ela vale — as regras da empresa não se aplicam a ele.

### O Monitor diz "CPF não encontrado como terceirizado, freelancer, liberação pontual de hoje nem ingresso de evento com entrada aberta"
<!-- telas: company.access.monitor -->

O CPF não está em nenhum cadastro que dê acesso agora. Verifique se o funcionário da empresa foi cadastrado **com o CPF** (sem CPF, só se acha pelo nome da empresa), se a liberação pontual foi criada hoje ou se o ingresso é de um evento com a entrada aberta. Para um caso urgente, use o botão **Criar liberação pontual** que aparece no resultado (requer a permissão de Liberação Pontual).

### O Monitor diz "Empresa não encontrada no sistema."
<!-- telas: company.access.monitor -->

O texto digitado não bate com o nome de nenhuma empresa cadastrada. Tente uma parte menor do nome, ou procure em **Externos › Empresas**. Se a empresa não existe, ela precisa ser cadastrada (botão **Nova Empresa**) e receber uma regra de inclusão.

### O freelancer aparece como "Sem serviço registrado para este horário."
<!-- telas: company.access.monitor -->

O freelancer entra pelo **contrato de serviço**, não por regra: a entrada abre 30 minutos antes do início do serviço e fecha no término. Se ele chegou mais cedo, ou o contrato é de outro dia, foi cancelado ou não foi lançado, o acesso é negado. Quem cuida do contrato é o módulo Freelancers. A assinatura do contrato não é cobrada na portaria.

### A liberação pontual aparece como "Já utilizada"
<!-- telas: company.one-off.index -->

A liberação pontual vale uma única entrada: o primeiro **Registrar Acesso** a gasta (só **Consultar** não gasta). Se a pessoa precisa entrar de novo, crie uma nova liberação em **Externos › Liberação Pontual** — depois de usada, o mesmo CPF pode receber outra no mesmo dia.

### Aparece "Já existe uma liberação pontual disponível para este CPF hoje"
<!-- telas: company.one-off.index -->

Só pode haver uma liberação disponível por CPF no dia. Procure a pessoa na lista de **Externos › Liberação Pontual**: se a liberação existente estiver errada, clique em **Cancelar** e crie outra.

### Não consigo cancelar a liberação pontual
<!-- telas: company.one-off.index -->

O botão **Cancelar** só aparece para liberações **Disponíveis**. A que já foi usada é o registro de uma entrada que aconteceu ("Esta liberação já foi utilizada e não pode ser cancelada."); a vencida perdeu a validade sozinha na virada do dia.

### O CPF do funcionário dá "CPF incompleto" ou "CPF inválido"
<!-- telas: company.index -->

O campo **CPF / Documento** precisa dos 11 dígitos e confere os dígitos verificadores. Revise o número no documento da pessoa. O CPF é opcional no cadastro do terceirizado, mas sem ele a portaria não encontra a pessoa digitando o CPF.

### O pedido de Uber sumiu da fila "Aguardando Motorista"
<!-- telas: company.uber.requests -->

Ou ele já foi liberado (vira "Concluído"), ou expirou: o pedido vale 30 minutos e, depois de um tempo, o sistema o encerra como "Expirado". Procure na aba **Pedidos** com o nome, a placa ou o telefone. Se o botão de liberar mostrou "O pedido já saiu da fila — atualize a tela", outra pessoa já tinha liberado ou o pedido expirou. Pedido expirado não volta: o sócio precisa pedir de novo pelo WhatsApp.

### "Placa fora do formato esperado (ABC1234 ou ABC1D23)."
<!-- telas: company.uber.requests -->

Ao usar **Corrigir placa**, digite a placa no padrão antigo (três letras e quatro números) ou Mercosul (três letras, número, letra, dois números). Hífen e espaço são ignorados.

### O pedido mostra "Funcionário bloqueado" ou "Nome/matrícula/CPF não confere"
<!-- telas: company.uber.requests -->

É a conferência automática de quem pediu. "Funcionário bloqueado" aparece em vermelho quando quem pediu é funcionário desligado, de férias ou afastado, com o motivo logo abaixo. "Não confere" quer dizer que nome, matrícula ou CPF não bateram com o cadastro. Nos dois casos a conferência é **um alerta, não um bloqueio**: a portaria decide se libera.

### Não vejo Liberação Pontual, Histórico ou Carros de Aplicativo no menu
Essas telas têm permissão própria (Externos › Liberação pontual, Externos › Histórico de acessos e Externos › Carros de aplicativo e fila da portaria). Fale com o coordenador do seu setor: ele coloca você num setor que já tem a permissão (tela **Meu setor**) ou pede à TI ou à Gerência que a liberem (tela **Setores**). Empresas e Monitor de Acesso são abertos a todos os usuários logados.
