# Guias do usuário (docs/guias)

## O que é

Um guia por módulo do menu, mais "Primeiros passos", escrito para **quem usa as telas**: o passo a
passo, os botões, as regras em linguagem simples e as dúvidas comuns. Ficam em `docs/guias/` e
têm dois leitores:

- a **tela Documentação** (menu da conta), na seção "Guias do usuário";
- a **Lara (IA)**, que busca neles pela API `GET /api/guias/search` para ensinar o funcionário a
  usar O Lara (contrato em [lara-ia-consultas.md](lara-ia-consultas.md)).

Os outros documentos de `docs/` são técnicos. Guia **não** cita classe, rota, tabela, API, `.env`,
servidor nem comando.

## Quando atualizar

Mudou uma tela, um botão, um fluxo ou uma regra que o funcionário percebe → atualize o guia do
módulo **na mesma mudança**. A IA responde com o que está escrito ali: guia desatualizado vira
resposta errada.

## Formato

```markdown
---
modulo: Ingressos
palavras: ingresso, evento, festa, show, entrada, mesa, cortesia
---

# Ingressos — guia de uso

O que o módulo faz e para quem.

## Quem faz o quê

## Emitir ingressos no balcão e cortesias
<!-- telas: tickets.index -->

Na tela **Ingressos › Eventos**, …

### Um subtópico

## Dúvidas comuns

### Por que o botão Emitir não aparece?
<!-- telas: tickets.index -->
```

- **Nome do arquivo**: minúsculas, números e hífen (`placar-clube.md`). A tela Documentação recusa
  acento e espaço.
- **`modulo:`**: o nome do módulo **exatamente** como no menu (`Navigation::links()`), ou
  `Primeiros passos` (vale para todos).
- **`palavras:`**: os sinônimos e termos que as pessoas usam ao perguntar. Pesam na busca.
- **Um único `#`** (o título). Cada `##` é uma tarefa, com título que soe como a pergunta ("Emitir
  uma carteirinha"). Termine com **`## Dúvidas comuns`**, perguntas em `###`.
- **`<!-- telas: rota.a, rota.b -->`** logo abaixo de um `##`/`###` (opcional): as telas **do
  menu** onde a tarefa é feita. Vira o link da resposta e decide quem **alcança** a seção. `###`
  sem a linha herda a do `##`; seção sem nenhuma vale o módulo inteiro. Para uma permissão que não
  é de tela do menu: `<!-- permissoes: nome.da.permissao -->`.
- **A busca devolve cada seção sozinha**: comece repetindo o caminho da tela ("Na tela
  **Ingressos › Eventos**, …"). 80 a 400 palavras por seção.
- **Permissões**: quem libera é a TI ou a Gerência (tela Setores, ou por pessoa em Usuários); o
  coordenador coloca a pessoa num setor que já tem a permissão (tela Meu setor). Use os rótulos do
  catálogo (`App\Authorization\Permissions`) como aparecem na tela Setores.
- Sempre "O Lara" para o sistema; "a Lara" é a assistente de IA.

## Como é lido

- `UserGuides` recorta cada arquivo em seções (`##` e `###`; `####` fica dentro da de cima; o texto
  entre o `#` e o primeiro `##` é a "Visão geral") e guarda em cache até algum arquivo mudar —
  editar um guia vale na pergunta seguinte, sem deploy de código.
- A tela Documentação esconde o cabeçalho e as anotações e dá âncora a cada título; a âncora é a
  mesma que a busca manda no link (`GuideMarkdown::anchor`).

## Conferência automática

`tests/Feature/UserGuidesContentTest.php` passa por todos os guias: nome do arquivo, `modulo`
existente no menu, `palavras` preenchido, um `#`, `## Dúvidas comuns`, anotação logo abaixo de um
título e cada tela citada existente no menu. Rode-o ao mexer em guia ou no menu (renomear um item
do menu quebra o `modulo:` do guia).
