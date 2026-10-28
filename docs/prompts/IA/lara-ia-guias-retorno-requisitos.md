# Retorno para o agente da VM da IA (Lara) — guias: requisitos e contexto

**Sistema externo:** IA (VM da Lara). Responde ao retorno do agente sobre
[lara-ia-guias-prompt.md](lara-ia-guias-prompt.md). Contrato em
[lara-ia-consultas.md](../../funcionalidades/lara-ia-consultas.md).

---

```text
Retorno do Lara sobre a implementação de buscar_guias e do contexto da tela.

DE ACORDO COM AS DECISÕES DE VOCÊS
- Sem acesso (tem_acesso diferente de true), não mandar o texto do guia ao modelo, só o título
  da seção e os requisitos: concordamos, é o lado seguro.
- limit=6 fixo: ok.
- Registrar o caminho do menu do contexto ("Ingressos › Eventos (criando um registro)") por 90
  dias: [pendente — decisão do Lara].

a) CONTEXTO EM /perguntar
Já está implementado do nosso lado e vai junto com o próximo deploy do Lara (ainda não está em
produção). Ele só vai quando a pessoa está numa tela de módulo; no início e no perfil não vai.
Para conferir depois do deploy: a resposta de /api/guias/search devolve o campo "modulo" com o
valor que recebeu, então o seu próprio log da chamada basta; não há log por chamada do nosso lado.

b) USUÁRIOS DE TESTE
[pendente — o Lara vai indicar os usuários de teste]. Até lá, não tome portal_1 e portal_29 como
estáveis: são contas de gente de verdade e as permissões delas podem mudar.

c) FORMATO DE precisa_de (mudou, para melhor)
Agora a tela NUNCA vem sozinha: entre parênteses sempre vem o que a abre.
  - "SIV › Busca (permissão: SIV › Busca de placas)"
  - "Ingressos › Eventos (uma destas permissões: Ingressos › Eventos, tipos de ingresso e lotes; Ingressos › Check-in na entrada do evento; …)"
  - "Conta › Meu setor (só para coordenadores de setor)"   ← regra de cargo, não é permissão
O "Ingressos › Eventos" que você viu sem parênteses era uma tela que abre com qualquer uma de
várias permissões; agora elas vêm listadas.

d) CAMPO ESTRUTURADO NOVO: requisitos
Em cada seção, junto de precisa_de (que continua igual, só mais completo):
  "requisitos": [
    {"tela": "Ingressos › Eventos",
     "permissoes": ["Ingressos › Check-in na entrada do evento", "..."],
     "basta_uma": true,
     "regra": null}
  ]
  - permissoes: rótulos como aparecem na tela Setores ("Grupo › Rótulo"). São PERMISSÕES; "tela"
    é o nome da TELA. Não misture os dois.
  - basta_uma: true quando qualquer uma das permissões abre a tela.
  - regra: texto quando não é permissão ("só para coordenadores de setor"); aí permissoes vem [].
  - tela: null quando a seção pede uma permissão que não é de tela.
  - Com acesso, requisitos vem [].
  Use requisitos no lugar de precisa_de para montar a resposta sem acesso. Sugestão de frase:
  "Isso é feito em <tela> e depende da permissão <p>" (ou "de uma destas permissões: …" quando
  basta_uma).
Limite: requisitos diz o que abre a TELA. Exigência de um botão dentro dela ("Emitir pede também
Baixa manual ou Cortesia") só está no texto do guia, que você não envia a quem não tem acesso.
Não invente essa parte.

Lembrete da orientação (já está certa do lado de vocês): o coordenador coloca a pessoa num setor
que já tem a permissão (tela Meu setor); quem libera é a TI ou a Gerência (tela Setores, ou só
para a pessoa na tela Usuários).

PENDÊNCIAS DA CONSULTA ANTERIOR
- Avisos de teste (item A): [pendente — o Lara vai combinar].
- responsible_contact (item E): [pendente — decisão do Lara]. Mantenha sem repassar.

Testes sugeridos do seu lado: precisa_de/requisitos com basta_uma true e false, regra sem
permissões, tela null; e a resposta sem acesso nunca descreve o caminho.
```
