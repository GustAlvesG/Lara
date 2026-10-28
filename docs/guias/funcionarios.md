---
modulo: Funcionários
palavras: funcionário, colaborador, RH, recursos humanos, cadastro de funcionário, matrícula, CPF, setor, cargo, admissão, rescisão, desligamento, demissão, férias, afastamento, licença, planilha, importar, excluir funcionário, restaurar, apto, bloqueado
---

# Funcionários — guia de uso

O módulo **Funcionários** é o cadastro de quem trabalha no clube, mantido pelo RH: matrícula, nome, CPF, setor, cargo, admissão e, quando for o caso, rescisão, férias e afastamentos.

Esse cadastro é o que **confirma quem é funcionário** em dois lugares do Lara: no **pedido de carro de aplicativo** pelo WhatsApp (a portaria vê se quem pediu é funcionário apto) e na **frota** do SIV (só funcionário apto retira veículo da empresa). Funcionário desligado, de férias ou afastado fica **bloqueado** nesses dois lugares enquanto a situação valer.

Funcionário não é o mesmo que usuário do Lara. Os dois só são tratados como a mesma pessoa quando a **matrícula** do usuário é igual à do funcionário; nesse caso, o setor do funcionário também passa a valer para o usuário.

## Quem faz o quê

| Para | Permissão (como aparece na tela Setores) |
|---|---|
| Ver a lista, cadastrar, editar, desligar, registrar férias e afastamentos, excluir, restaurar e importar planilha (**Funcionários**) | Funcionários › Cadastro de funcionários, férias, afastamentos e importação por planilha |

Quem libera é a TI ou a Gerência, na tela **Setores** (ou só para uma pessoa, na tela **Usuários**); o coordenador do setor pede a liberação e coloca a equipe no setor pela tela **Meu setor** — normalmente ela fica com o RH. Toda a tela Funcionários depende dessa permissão; sem ela, o item não aparece no menu. Quem não tem a permissão continua sendo conferido pelo cadastro (no carro de aplicativo e na frota), só não vê nem altera os dados.

## Consultar a lista de funcionários
<!-- telas: employees.index -->

A tela **Funcionários** ("Quem trabalha no clube") lista os funcionários com **Nome** (e cargo), **Matrícula**, **CPF**, **Setor**, **Usuário** (o usuário do Lara com a mesma matrícula, se houver), **Situação** e **Ações**.

Para filtrar:

- **Buscar por nome, matrícula ou CPF**;
- o setor (**Todos os setores** ou um setor específico);
- a situação: **Todos (sem os excluídos)**, **Aptos**, **De férias ou afastados**, **Desligados** ou **Excluídos**.

A coluna **Situação** mostra **Apto** ou o motivo do bloqueio: "Desligado em dd/mm/aaaa", "Férias até dd/mm/aaaa", "Férias (sem data de retorno)", "Afastamento até dd/mm/aaaa", "Afastamento (sem data de retorno)" ou "Cadastro excluído".

Clique em **Abrir a ficha** para ver e editar o funcionário. Se a busca não trouxer nada, aparece "Nenhum funcionário com esses filtros." com o atalho **Limpar a busca**.

## Cadastrar um funcionário
<!-- telas: employees.index -->

Na tela **Funcionários**, clique em **Novo funcionário**. O formulário cadastra um por vez (para vários, veja a importação por planilha).

1. **Matrícula** (obrigatória): até 20 caracteres, só letras, números, ponto e hífen. Se um usuário do Lara tiver a mesma matrícula, ele é este funcionário e entra no setor dele.
2. **CPF** (obrigatório): com ou sem pontos; o Lara confere os dígitos e guarda só os números.
3. **Nome completo** (obrigatório). No pedido de carro de aplicativo, a pessoa é conferida pelo **primeiro nome**, então escreva o nome como ela se apresenta.
4. **Setor** (obrigatório): um dos setores cadastrados na tela Setores.
5. **Cargo** e **Data de admissão** (opcionais).
6. Clique em **Cadastrar**. Aparece "Funcionário Fulano cadastrado.", com o aviso de quando um usuário entrou no setor.

### O setor do funcionário leva o usuário junto

Quando existe um usuário do Lara com a mesma matrícula, ele é **colocado no setor do funcionário** como colaborador — ao cadastrar, ao trocar setor ou matrícula, ao restaurar e ao importar planilha. A regra **só acrescenta**: trocar o funcionário de setor não tira o usuário do setor antigo (tire na tela Setores, se for o caso), e quem já estava no setor mantém o papel. Funcionário desligado ou excluído não leva ninguém a setor nenhum. Cuidado: setor dá acesso a telas do Lara.

## Importar vários funcionários por planilha
<!-- telas: employees.index -->

Na tela **Funcionários**, clique em **Novo funcionário** e use o bloco **Importar por planilha**.

1. Clique em **Baixar modelo** e preencha uma linha por funcionário (apague a linha de exemplo). Não altere nem remova as colunas do cabeçalho.
2. Colunas: **Matrícula**, **Nome**, **CPF** e **Setor** (obrigatórias), **Cargo** e **Data de admissão** (opcionais; dd/mm/aaaa).
3. Em **Arquivo .xlsx**, escolha o arquivo e clique em **Importar**.

Regras:

- **A matrícula é a chave**: matrícula nova cadastra; matrícula que já existe **atualiza** o funcionário. Célula opcional em branco não apaga o que já estava gravado.
- O **setor** é escrito pelo nome e precisa já existir na tela Setores (a planilha não cria setor).
- O CPF pode vir com ou sem máscara; zeros à esquerda cortados pelo Excel são recompostos.
- **Tudo-ou-nada**: havendo qualquer erro, nada é gravado. A tela mostra "N problema(s) encontrado(s) — nenhum registro foi importado:" com a lista de erros e o número da linha. Corrija e envie de novo.
- Matrícula ou CPF repetidos no próprio arquivo, CPF de outro funcionário ativo e matrícula de funcionário **excluído** são erros.
- Rescisão, férias e afastamento **não** entram pela planilha: são registrados na ficha de cada um.

## Registrar férias ou afastamento
<!-- telas: employees.index -->

Na tela **Funcionários**, procure a pessoa e clique em **Abrir a ficha**. No quadro **Férias e afastamentos**:

1. **Tipo**: **Férias** ou **Afastamento**.
2. **Início** (obrigatório).
3. **Fim (opcional)**: deixar em branco registra um período **em aberto**, que vale até alguém informar o fim.
4. **Observação** (opcional).
5. Clique em **Registrar**.

**Enquanto o período vale, o funcionário fica bloqueado**: não consegue pedir carro de aplicativo como funcionário (a portaria vê "Funcionário bloqueado") nem retirar veículo da frota. A lista mostra o período com a marca **Vigente** e a coluna Situação passa a mostrar, por exemplo, "Férias até 15/11/2026".

A tabela da ficha lista **Tipo**, **Início**, **Fim** (ou "Em aberto"), **Observação** e o botão **Remover** (pede confirmação). Para encerrar um afastamento em aberto, remova o registro e registre de novo com a data de fim.

## Desligar um funcionário (rescisão)
<!-- telas: employees.index -->

Para quem saiu da empresa, o caminho é **registrar a rescisão**, não excluir. Na tela **Funcionários**, abra a ficha (**Abrir a ficha**) e, no quadro **Cadastro**:

1. Preencha **Rescisão** com a data de desligamento.
2. Preencha **Motivo da rescisão** (ex.: "Pedido de demissão", "dispensa").
3. Clique em **Salvar**. Aparece "Cadastro de Fulano atualizado.".

A partir da data de rescisão, o funcionário não pede carro de aplicativo nem dirige a frota, e a Situação passa a "Desligado em dd/mm/aaaa". Rescisão com **data futura** (aviso prévio) só bloqueia quando a data chega. O histórico do funcionário é mantido.

Para desfazer, apague a data de **Rescisão** e salve — limpar a data limpa o motivo junto.

No mesmo quadro **Cadastro** dá para corrigir matrícula, CPF, nome, setor, cargo e data de admissão. A ficha mostra ainda o **Usuário do painel** de mesma matrícula (ou "Nenhum"), a **Origem do cadastro** e a **Estrutura no espelho de ponto**.

## Excluir ou restaurar um funcionário
<!-- telas: employees.index -->

Na ficha do funcionário (tela **Funcionários** › **Abrir a ficha**), o botão **Excluir** (com confirmação) tira o funcionário das listas e da conferência de carro de aplicativo e frota, mas **guarda o histórico** (banco de horas, viagens, pedidos de carro). Para quem saiu da empresa, prefira registrar a rescisão.

Aparece "Funcionário Fulano excluído. Ele deixa de valer para carro de aplicativo e frota; dá para restaurar pelo filtro \"Excluídos\"."

Para **restaurar**: na lista, escolha a situação **Excluídos**, abra a ficha e clique em **Restaurar**. A ficha do excluído avisa: "Este cadastro foi excluído e não vale para carro de aplicativo nem para a frota. Restaurar traz tudo de volta." Não existe exclusão definitiva.

Um funcionário excluído continua ocupando a matrícula: para trazê-lo de volta, restaure — não cadastre de novo. Um setor que tenha funcionários (mesmo excluídos) não pode ser apagado na tela Setores.

## Dúvidas comuns

### "Já existe um funcionário com esta matrícula (confira também os excluídos)."
<!-- telas: employees.index -->

A matrícula já está em uso, possivelmente por um funcionário **excluído**. Na lista de **Funcionários**, escolha a situação **Excluídos** e busque pela matrícula: se for a mesma pessoa, abra a ficha e clique em **Restaurar** em vez de cadastrar de novo. A planilha recusa matrícula de excluído pelo mesmo motivo.

### "Já existe um funcionário ativo com este CPF."
<!-- telas: employees.index -->

Outro funcionário ativo já tem esse CPF. Busque o CPF na tela **Funcionários**: pode ser um cadastro duplicado com outra matrícula. Corrija ou exclua o duplicado antes.

### "O CPF informado não é válido." ou "A matrícula aceita só letras, números, ponto e hífen."
<!-- telas: employees.index -->

O CPF é conferido pelos dígitos verificadores: revise o número no documento. A matrícula não aceita espaço, barra nem outros símbolos além de ponto e hífen.

### "Nenhum funcionário foi importado. Corrija a planilha e envie novamente."
<!-- telas: employees.index -->

A importação é tudo-ou-nada: um erro em qualquer linha impede a gravação de todas. Leia a lista de problemas exibida no bloco **Importar por planilha** (cada um com o número da linha), corrija no arquivo e envie de novo. Erros comuns: setor escrito diferente do nome da tela Setores ou ainda não criado, CPF inválido, matrícula ou CPF repetidos no arquivo, matrícula de funcionário excluído, e cabeçalho alterado.

### Por que o funcionário não consegue retirar o carro da frota ou aparece "Funcionário bloqueado" no pedido de Uber?
<!-- telas: employees.index -->

Abra a ficha em **Funcionários** e veja a **Situação**. Rescisão com data de hoje ou anterior, férias ou afastamento vigentes e cadastro excluído bloqueiam. Se a pessoa já voltou e o período estava "Em aberto", remova o registro e registre de novo com a data de fim. Se a rescisão foi lançada por engano, apague a data de **Rescisão** e salve.

### O funcionário não aparece como motorista na frota
<!-- telas: employees.index -->

A busca de motorista da frota só mostra funcionários **aptos**. Confira na tela **Funcionários** se ele está cadastrado, se a situação é **Apto** e se a matrícula e o CPF estão certos.

### O usuário do Lara não entrou no setor do funcionário
<!-- telas: employees.index -->

O vínculo é só pela **matrícula**: a matrícula do usuário (na tela Usuários) precisa ser exatamente igual à do funcionário. Funcionário desligado ou excluído não leva ninguém a setor. Na ficha, o quadro **Usuário do painel** mostra "Nenhum" quando não há usuário com a mesma matrícula.

### Troquei o funcionário de setor e o usuário continua no setor antigo
<!-- telas: employees.index -->

É de propósito: a regra só acrescenta, porque o acesso ao setor antigo pode ter sido dado de forma intencional. Para tirar o usuário do setor antigo, use a tela **Setores**.

### Não vejo o menu Funcionários
A tela depende da permissão **Funcionários › Cadastro de funcionários, férias, afastamentos e importação por planilha**. Fale com o coordenador do seu setor: ele coloca você num setor que já tem a permissão (tela **Meu setor**) ou pede à TI ou à Gerência que a liberem (tela **Setores**).
