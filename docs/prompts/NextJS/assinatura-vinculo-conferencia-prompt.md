# Prompt para o agente do site em Next.js: assinatura com modelo vinculado e conferência (29/10/2026)

**Sistema externo:** NextJS (o site da assinatura remota por link, o mesmo das Reservas).

Cole o bloco abaixo numa sessão do Claude Code aberta no repositório do site. O contrato completo da
API está em [assinatura-remota-prompt-site.md](../../funcionalidades/assinatura-remota-prompt-site.md).

---

```text
Contexto: este site tem a tela de assinatura remota por link, que fala com O Lara pela API
/api/assinatura/remota/* (link, abrir, sessao, pdf, respostas, visualizado, identidade, anexos,
assinar, recusar, encerrar). O Lara ganhou três recursos no módulo Assinaturas em 29/10/2026.
NENHUM deles muda a API: rotas, campos, códigos de erro e formatos continuam os mesmos.

1. Modelo vinculado: um modelo pode exigir outro junto ("quem assina X assina também Y"). O texto
   de Y entra no MESMO documento de X, em página nova. Para o site:
   - o PDF de GET /pdf pode ter mais páginas (dois textos seguidos) e mais de um lugar de
     assinatura — continua sendo um PDF só, com uma assinatura só da pessoa;
   - o formulário (form.fields, no estado da sessão e em POST /respostas) pode trazer mais
     perguntas, vindas dos dois modelos;
   - a lista de anexos pode ter itens dos dois modelos.
2. Campo restrito ao atendente: campos marcados no Lara como "Só o atendente responde" nunca
   chegam ao formulário. Nada a fazer.
3. Conferência das respostas pelo atendente: vale só para o tablet do balcão. Na sessão remota a
   chave "answer_check" NÃO existe e a assinatura não espera aprovação. Nada a fazer.

O que fazer:
- Confira que a leitura do PDF lida bem com documentos longos (rolagem até o fim, botão "Li o
  documento", desempenho em celular de 360 px) e que o formulário rola com 10+ perguntas.
- Não acrescente lógica nova para a conferência nem para os vínculos.
- Se encontrar suposição no código de "um documento = um texto curto" (altura fixa, número
  máximo de páginas, limite de perguntas), remova.

Ao terminar, me diga o que foi conferido, o que precisou mudar (se algo) e como testar contra a
homologação do Lara com um documento de modelo vinculado.
```
