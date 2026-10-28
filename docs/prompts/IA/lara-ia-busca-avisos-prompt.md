# Prompt para o agente da VM da IA (Lara) — busca de avisos

Cole o bloco abaixo numa sessão do Claude Code aberta no repositório do agente da Lara (a VM
com Ollama). Contrato completo do lado do Lara em [lara-ia-consultas.md](../../funcionalidades/lara-ia-consultas.md).

---

```text
Contexto: este agente (Lara) já consulta O Lara (o portal interno do clube, Laravel) pela API
GET /api/information/search para responder sobre atividades, serviços, horários e preços do
InfoClube. O Lara ganhou uma segunda busca, de AVISOS, e o agente precisa passar a usá-la.

O QUE MUDOU NO LARA (já em produção junto com este prompt)

GET {LARA_PORTAL_URL}/api/avisos/search
Headers: Authorization: Bearer {mesmo token já usado na busca de informações}
         Accept: application/json
Query:
  q                (obrigatório) frase em linguagem natural, 2 a 100 caracteres. O Lara já
                   descarta stopwords; pode mandar a pergunta do funcionário ou palavras-chave.
  user_id          (obrigatório) quem está perguntando. Repasse EXATAMENTE o usuario_id que o
                   Lara manda em POST /perguntar ("portal_12"); o prefixo portal_ é aceito.
                   Também aceita o nome usuario_id.
  include_expired  (opcional) 1 para incluir avisos vencidos. Padrão: só os vigentes.

Resposta 200:
{
  "keyword": "...", "terms": ["..."], "user_id": 12, "include_expired": false, "total": 1,
  "data": [{
    "id": 87, "title": "...", "content": "texto puro",
    "privacy": "publico|setor|grupo|pessoa", "mandatory": false,
    "expires_at": "2026-11-05" | null, "expired": false,
    "created_at": "ISO-8601", "updated_at": "ISO-8601",
    "author": "Nome", "tags": ["..."], "url": "https://.../avisos/87", "relevance": 6
  }]
}
Ordenada por relevance (título 3, tag 2, conteúdo 1) e depois pelo mais recente.

Erros:
  401  token ausente/errado.
  422  {success:false, errors:{q:[...]}} ou errors.user_id = ["Usuário não encontrado."] ou
       ["Informe o usuário que pergunta (user_id)."].

PRIVACIDADE (importante)
A busca devolve só os avisos DIRIGIDOS a quem pergunta (públicos, do setor do autor, de grupo
que inclui a pessoa, ou pessoais dela). Por isso:
  - Nunca chame a busca de avisos sem o user_id da conversa atual, e nunca reaproveite o
    resultado de um funcionário na resposta a outro (nada de cache entre usuários).
  - Se der 422 de usuário, não tente de novo sem user_id: responda que não conseguiu consultar
    os avisos agora.

O QUE IMPLEMENTAR NO AGENTE
1. Uma ferramenta nova, ao lado da busca de informações, por exemplo buscar_avisos(q,
   incluir_vencidos=false), que chama o endpoint acima injetando o user_id da sessão — o modelo
   não deve escolher nem ver o user_id como parâmetro.
2. Orientação no prompt de sistema sobre qual fonte usar:
   - Avisos: comunicados e novidades — fechamentos, mudanças de horário, eventos, prazos,
     "teve algum aviso sobre...", "o que mudou...".
   - Informações: o que o clube oferece de forma permanente — atividades, preços, horários
     regulares, responsáveis.
   - Na dúvida (ex.: "a piscina abre amanhã?"), consulte as duas: o aviso tem precedência
     sobre a informação permanente quando trata do mesmo assunto e está vigente.
3. Ao responder com base em aviso: cite o título e a data (created_at), mencione a validade
   (expires_at) quando houver, e ofereça o link (url). Se mandatory = true, diga que o aviso é
   de leitura obrigatória no Lara.
4. Só use include_expired=1 quando a pergunta for sobre o passado ("teve aviso sobre... no mês
   passado?"). Aviso com expired = true nunca deve ser apresentado como vigente.
5. total = 0: diga que não encontrou aviso sobre o assunto para essa pessoa. Não afirme que
   "não existe aviso" — pode existir um que não foi dirigido a ela.
6. Timeout curto nessa chamada (a pergunta inteira tem 22 s do lado da IA) e falha da busca
   não pode derrubar a resposta: siga com o que tiver e avise que os avisos não puderam ser
   consultados.

Testes sugeridos: mock do endpoint cobrindo 200 com resultados, 200 vazio, 401, 422 de usuário
e timeout; e um teste garantindo que o user_id enviado é o da sessão, não um valor vindo do
modelo.
```
