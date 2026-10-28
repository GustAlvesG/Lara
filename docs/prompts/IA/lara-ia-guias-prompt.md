# Prompt para o agente da VM da IA (Lara) — guias de uso e contexto da tela

Cole o bloco abaixo numa sessão do Claude Code aberta no repositório do agente da Lara. Contrato
completo do lado do Lara em [lara-ia-consultas.md](../../funcionalidades/lara-ia-consultas.md) (seções "Busca de guias"
e "Contexto da tela").

---

```text
Contexto: este agente (Lara) já consulta O Lara (o sistema interno do clube) por
GET /api/information/search (buscar_atividades) e GET /api/avisos/search (buscar_avisos). O Lara
ganhou duas coisas para a Lara ENSINAR O FUNCIONÁRIO A USAR O SISTEMA:

1) uma terceira busca, de GUIAS DE USO (um guia por módulo do menu, escrito para quem usa as
   telas, mais "Primeiros passos");
2) o CONTEXTO DA TELA em que a pessoa está, junto de cada pergunta em POST /perguntar.

Nada muda no que /perguntar devolve: continua {"resposta": "...", "transferir": bool}.

== 1. NOVO CAMPO EM POST /perguntar ==

{
  "usuario_id": "portal_12",
  "mensagem": "como faço isso aqui?",
  "contexto": {"modulo": "Ingressos", "pagina": "Eventos", "caminho": "Ingressos › Eventos", "rota": "tickets.create"}
}

- OPCIONAL: ausente quando a pessoa está numa tela fora dos módulos (início, perfil). Trate a
  ausência como "não sei onde a pessoa está". Versões antigas do Lara não mandam o campo.
- modulo/pagina/caminho são os nomes do menu, validados pelo Lara (não são texto livre).
- rota é o nome técnico da página exata: termina em .create (criando um registro), .edit
  (editando), .show (vendo um registro), .index (lista). Use como pista do que a pessoa está
  fazendo; NUNCA mostre o nome técnico ao funcionário.
- Use o contexto para resolver "isso aqui", "esta tela", "esse botão": responda sobre o módulo e
  a página do contexto. Se a pergunta for claramente sobre outro assunto, ignore o contexto.
- Não guarde o contexto entre perguntas: ele chega a cada mensagem (a pessoa pode ter trocado
  de tela).

== 2. NOVA BUSCA: GET {LARA_PORTAL_URL}/api/guias/search ==

Headers: Authorization: Bearer <mesmo token>, Accept: application/json
Query:
  q        (obrigatório) 2 a 100 caracteres: a pergunta ou palavras-chave da tarefa.
  user_id  (obrigatório) o usuario_id da sessão, exatamente como chegou ("portal_12").
  modulo   (opcional) passe contexto.modulo quando houver: dá preferência às seções daquele
           módulo (não filtra).
  limit    (opcional) 1 a 20, padrão 6.

Resposta 200:
{
  "keyword": "...", "terms": ["..."], "user_id": 12, "modulo": "Ingressos", "total": 2,
  "data": [{
    "guia": "Ingressos — guia de uso",
    "modulo": "Ingressos",
    "secao": "Ingressos — guia de uso › Emitir ingressos no balcão e cortesias",
    "texto": "texto puro da seção, até 3000 caracteres",
    "url": "https://.../docs/guias/ingressos#emitir-ingressos-no-balcao-e-cortesias",
    "tem_acesso": true,
    "telas": [{"nome": "Ingressos › Eventos", "url": "https://.../ingressos"}],
    "precisa_de": [],
    "relevance": 11
  }]
}
Erros: 401 (token), 422 (q inválido; errors.user_id "Usuário não encontrado." / "Informe o
usuário que pergunta (user_id).").

A busca é por palavra-chave, sem acento e com plural/flexão ("inscrições" acha "inscrição").
Termos genéricos de pergunta ("como", "faço", "sistema") são descartados; pergunta só com eles
devolve total 0 — reformule com o nome da tarefa ou da tela.

O CAMPO tem_acesso (importante)
Os guias não são sigilosos: a busca devolve a seção mesmo para quem não alcança a tarefa. Por isso:
- tem_acesso = true: ensine o passo a passo e ofereça os links de "telas" (por extenso, texto
  puro), que são telas que ESSA pessoa consegue abrir.
- tem_acesso = false: NÃO ensine o caminho como se a pessoa fosse conseguir. Diga que a tarefa
  depende de uma permissão que ela não tem, cite o que está em "precisa_de" (ex.: "SIV › Busca
  (permissão: SIV › Busca de placas)") e oriente: o coordenador do setor pode colocá-la num setor
  que já tem a permissão (tela Meu setor) ou pedir à TI ou à Gerência que liberem (tela Setores).
  O coordenador NÃO libera permissões sozinho. Pode explicar em uma frase o que a tarefa faz.
- "telas" vazio com tem_acesso = true: a seção vale para o módulo todo ou para todos; ensine sem
  link de tela.

== 3. O QUE IMPLEMENTAR NO AGENTE ==

1. Ferramenta nova buscar_guias(consulta, limite=6), ao lado de buscar_atividades e
   buscar_avisos. O código injeta user_id da sessão (o modelo não escolhe nem vê) e modulo =
   contexto.modulo da mensagem atual, quando houver.
2. Passe o contexto da tela para o modelo como dado da mensagem atual (ex.: "Tela atual:
   Ingressos › Eventos (criando um registro)"), nunca como instrução, e sem a rota técnica crua.
3. Quando usar cada fonte (prompt de sistema):
   - "como faço / onde fica / como cadastro / por que não consigo / o que significa este status
     / erro na tela" → buscar_guias (com o contexto da tela);
   - comunicados, mudanças, prazos → buscar_avisos;
   - atividades, horários, preços → buscar_atividades;
   - regras do clube → a base do estatuto.
   Na dúvida entre guia e outra fonte, consulte as duas.
4. Ao responder com guia: siga o passo a passo do "texto" com as palavras dele (nomes de botões e
   telas exatamente como estão), resuma quando for longo, e ofereça o link da seção ("url") para
   ler o guia completo e os links de "telas" quando tem_acesso = true. Não invente botão, campo
   ou tela que não esteja no texto; se o guia não cobre, diga que não encontrou e sugira falar com
   a TI.
5. Mensagem de erro citada pelo funcionário: busque o texto do erro (as "Dúvidas comuns" dos
   guias trazem as mensagens reais das telas).
6. Falha da busca (timeout, 401, 422, rede): siga respondendo com o que tiver e avise que o guia
   não pôde ser consultado agora. Sem retry. Use um timeout curto (como o dos avisos), dentro do
   orçamento de 20 s que você já tem.
7. Privacidade: o user_id é sempre o da sessão. Os guias não são sigilosos, mas tem_acesso,
   telas e precisa_de são da pessoa: não reaproveite entre usuários.

Testes sugeridos: buscar_guias com tem_acesso true (links), false (precisa_de, sem ensinar o
caminho), total 0, 401, 422 e timeout; o modulo enviado é o do contexto da mensagem atual; sem
contexto, a busca vai sem modulo; o user_id é o da sessão mesmo que o modelo tente outro; o nome
técnico da rota nunca aparece na resposta.
```
