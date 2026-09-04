# Prompt de implementação — front-end da aprovação de compras (Next.js)

> Documento de entrega para um agente Claude Code trabalhando **no repositório
> Next.js**, não neste. Copie a partir de "Início do prompt" até o fim.
>
> O contrato de API descrito aqui é o que a Lara expõe hoje
> (`routes/api.php`, prefixo `aprovacao`). Se a API mudar, este arquivo muda
> junto — ele é a fonte do que o front pode esperar.

---

## Início do prompt

Você vai implementar uma **nova área** neste projeto: aprovação de ordens de
compra por membros da diretoria. O projeto hoje atende aluguel de espaços, com
autenticação de sócios; esta área é outra coisa, com **outro público, outra
autenticação e outra API** — mas deve parecer parte do mesmo produto.

### Antes de escrever qualquer código

Explore o projeto e me diga o que encontrou, em especial:

1. Como as rotas são organizadas (App Router ou Pages, grupos, layouts).
2. Como a autenticação de sócio funciona hoje — onde o token é guardado, como as
   chamadas autenticadas são feitas, se existe middleware de rota.
3. **Se as chamadas à API saem do servidor (Route Handler / Server Action /
   `fetch` em Server Component) ou do navegador.** Esta resposta muda o desenho
   inteiro do que você vai fazer — não presuma, verifique.
4. O sistema de design: componentes de formulário, botão, tabela, card, toast;
   tokens de cor e tipografia; como o tema escuro é tratado, se houver.

Só depois disso proponha o plano.

### Restrições não negociáveis

**1. O navegador nunca fala com a API da Lara.**

Este site roda numa DMZ que alcança a porta da Lara; a Lara não é publicada na
internet. Toda chamada à API tem que sair do **servidor do Next** — Route
Handler, Server Action ou fetch em Server Component. Um `fetch` do lado do
cliente apontando para a Lara não funciona de fora da DMZ e, se funcionasse,
exigiria publicar a Lara na internet, que é justamente o que esta arquitetura
existe para evitar.

Se você descobrir que a área de agendamento chama a Lara pelo navegador, **não
copie esse padrão aqui** — relate o achado e siga server-side nesta área.

**2. A URL da API e o token nunca chegam ao bundle do cliente.**

- A base da API vai numa variável **sem** prefixo `NEXT_PUBLIC_`.
- O token de aprovação fica em cookie `httpOnly`, `secure`, `sameSite=lax`.
  Nunca em `localStorage`, `sessionStorage` ou estado de React.
- Componentes de cliente recebem dados já buscados, nunca credenciais.

**3. O front não decide nada.**

Quem valida senha, cargo, vez e estado é a Lara. O Next renderiza, repassa a
decisão e mostra a resposta. Não replique regra de negócio: não calcule se o
usuário pode aprovar, não esconda o botão porque "acha" que já aprovou, não
interprete o fluxo. Se a API recusar, mostre a mensagem que ela devolveu — elas
são escritas em português, para o usuário final.

**4. Área separada, visual compartilhado.**

Rotas, layout e sessão próprios, sem qualquer relação com a sessão de sócio.
Um sócio logado não é um aprovador; um aprovador não vira sócio. Mas os
componentes, cores, espaçamentos e tipografia são os que o projeto já usa —
esta área não pode parecer um sistema diferente colado no mesmo domínio.

### A API

Base: variável de ambiente (ex.: `LARA_API_URL`), apontando para a Lara na rede
interna. Todos os endpoints abaixo são relativos a ela.

#### `POST /api/aprovacao/login`

Sem autenticação. A senha vai em **texto puro** no corpo — a comparação é
bcrypt do lado da Lara. Não aplique SHA256 nem qualquer outro hash: o front de
sócio hasheia porque a chamada nasce no navegador; esta nasce no servidor.

Limite: **5 tentativas por minuto por matrícula** (não por IP — como todas as
chamadas saem do mesmo servidor em DMZ, um limite por IP faria um aprovador
trancar os outros). Estourando, vem `429` com a mensagem pronta para exibir.

```json
{ "matricula": "11882", "senha": "..." }
```

Resposta `200`:

```json
{
  "token": "<jwt>",
  "expira_em": "2026-08-13T18:41:52-03:00",
  "usuario": { "id": 1, "nome": "Gustavo Alves", "matricula": "11882" }
}
```

- `401` — matrícula ou senha inválida (a API não distingue os dois casos de
  propósito; **não invente** uma mensagem mais específica).
- `403` — usuário existe, mas não é da diretoria.
- `422` — campos faltando (formato de validação do Laravel).
- `429` — muitas tentativas para aquela matrícula; `error` já traz os segundos.

O token vale **8 horas**. Guarde-o em cookie `httpOnly` com expiração alinhada a
`expira_em`. Não há refresh: expirou, loga de novo.

#### `GET /api/aprovacao/ordens`

Header `Authorization: Bearer <token>`. A fila **do próprio aprovador** — só as
ordens em que ele tem decisão pendente.

```json
{
  "ordens": [
    {
      "cd_ordem_compra": 7,
      "processo_id": 1,
      "fornecedor": "ATACADAO MADUREIRA DOCES FESTAS E EMBALAGENS",
      "departamento": null,
      "solicitante": null,
      "vl_total": 383.2,
      "nr_itens": 3,
      "centros_custo": [75],
      "sem_centro_custo": false,
      "aguardando_desde": "2026-08-13T10:41:52-03:00",
      "escolhido_por": "centro de custo"
    }
  ]
}
```

`escolhido_por` é `"centro de custo"` ou `"gerente"` — diz se aquele aprovador
entrou pelo cadastro ou por escolha manual da gerência. Vale mostrar discreto;
não é destaque.

`fornecedor`, `departamento` e `solicitante` vêm do ERP e podem ser `null`: se o
Questor não responder, a fila ainda é devolvida, sem esses campos. Trate `null`
como "—", não como erro — a fila sem nome de fornecedor ainda é utilizável.

`aguardando_desde` é desde quando a ordem está **neste nível**, não desde que
foi criada. Rotule como "aguardando você desde", não "aberta em".

#### `GET /api/aprovacao/ordens/{cd_ordem_compra}`

Detalhe com itens. `404` quando a ordem não está na fila **daquele** aprovador —
trate como "não encontrada", não como erro do sistema.

```json
{
  "cd_ordem_compra": 7,
  "processo_id": 1,
  "filial": 3,
  "fornecedor": { "razao_social": "...", "fantasia": "...", "cnpj": "..." },
  "departamento": "...",
  "solicitante": "...",
  "referente": "...",
  "vl_total": 383.2,
  "nr_itens": 3,
  "dt_cadastro": "2022-08-17 11:43:54.280",
  "itens": [
    {
      "item": 1,
      "material": "...",
      "unidade": "UN",
      "quantidade": 2,
      "vl_unitario": 191.6,
      "vl_total": 383.2,
      "centro_custo": 75
    }
  ],
  "aprovacao": {
    "id": 1,
    "status": "open",
    "nivel_atual": 3,
    "nivel_atual_rotulo": "Diretoria",
    "aguardando": 1,
    "passos": [
      {
        "nivel": 1,
        "responsavel": "Coordenador da Contabilidade",
        "decisao": "approved",
        "decidido_por": "Gustavo Alves",
        "decidido_em": "2026-08-13T10:41:52-03:00",
        "observacao": null
      }
    ]
  }
}
```

`503` significa que a Lara não conseguiu falar com o ERP. É temporário: mostre
"não foi possível carregar os dados da ordem, tente novamente" e ofereça
recarregar — não trate como sessão inválida.

#### `POST /api/aprovacao/ordens/{cd_ordem_compra}/decidir`

```json
{ "decisao": "aprovar", "observacao": "opcional, até 1000 caracteres" }
```

`decisao` é `"aprovar"` ou `"reprovar"`. Resposta `200`:

```json
{
  "resultado": "open",
  "gravado_no_questor": false,
  "aprovacao": { "...": "mesmo formato do detalhe" }
}
```

`422` com `{ "error": "..." }` para recusas de negócio — não é a sua vez, já
decidiu, a ordem mudou no ERP durante o trâmite. **Mostre a mensagem da API.**

### A parte que mais importa acertar: o que a resposta significa

Esta é a regra que mais facilmente vira bug de interface. Depois de aprovar:

| `resultado` | `gravado_no_questor` | O que aconteceu | O que a tela deve dizer |
|---|---|---|---|
| `open` | `false` | Você aprovou; outros diretores ainda faltam | "Sua aprovação foi registrada. A ordem aguarda os demais diretores." |
| `approved` | `true` | Todos aprovaram e o ERP foi carimbado | "Ordem aprovada e autorizada no Questor." |
| `approved` | `false` | Aprovada, mas a gravação não foi registrada | Trate como caso anômalo: aprovada, com aviso para procurar a TI |
| `rejected` | `false` | Reprovada; o processo foi encerrado | "Ordem reprovada. O processo foi encerrado." |

**Nunca diga "ordem aprovada" quando `resultado` for `open`.** É a diferença
entre "fiz a minha parte" e "a compra está liberada", e alguém vai agir com base
nessa frase.

Duas coisas para a interface deixar claras antes do clique:

- **Aprovar sendo o último pendente grava no ERP e não tem desfazer** por aqui.
  Confirme a ação, com o número e o valor da ordem na confirmação.
- **Reprovar encerra o processo inteiro**, em qualquer nível. Não é "meu voto
  contra": é o fim.

### O que construir

1. **Login** — matrícula e senha. Campo de senha alfanumérica (não é PIN
   numérico; não force `inputMode="numeric"`). Erros da API exibidos como vieram.
2. **Fila** — lista das ordens pendentes do aprovador, com número, fornecedor
   (vem só no detalhe; na lista use o número e o valor), valor, há quanto tempo
   espera. Estado vazio bem resolvido: "nenhuma ordem aguardando você" é o
   estado normal e frequente, não uma falha.
3. **Detalhe** — cabeçalho, fornecedor, itens com centro de custo, e o andamento
   (`aprovacao.passos`) mostrando quem já decidiu. Ações de aprovar e reprovar,
   com campo de observação opcional.
4. **Sair** — limpa o cookie.

Comece por 1 e 2, me mostre funcionando, e só então siga.

### O que não construir

- Cadastro, recuperação de senha, troca de senha. Quem cria e redefine é a
  administração da Lara.
- Qualquer tela de gerência ou contabilidade — os níveis 1 e 2 são decididos
  dentro da Lara, não aqui.
- Escolha de aprovadores, cadastro de centro de custo, edição de ordem.
- Notificação por WhatsApp: será disparada pela Lara, fora deste projeto.

### Como testar

A API está no ar em homologação e responde a Postman. Peça as credenciais e a
URL antes de começar; não invente dados de exemplo em código de produção.

Casos que precisam funcionar, além do caminho feliz:

- token expirado ou ausente → volta ao login sem loop de redirecionamento;
- `404` no detalhe → "esta ordem não está na sua fila", com caminho de volta;
- `503` → erro temporário, com recarregar;
- decidir duas vezes (duas abas abertas) → a segunda recebe `422` e a tela
  mostra o motivo em vez de quebrar.

### Definição de pronto

- Nenhum `fetch` para a Lara sai do navegador — verificável na aba de rede.
- `LARA_API_URL` e o token não aparecem em nenhum bundle do cliente.
- A área nova não altera nem depende do fluxo de agendamento.
- Componentes e estilo são os do projeto, não uma biblioteca nova.
- Os quatro casos de erro acima foram exercitados de verdade, não só previstos.

## Fim do prompt
