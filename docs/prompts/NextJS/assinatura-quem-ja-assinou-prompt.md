# Prompt para o agent do site Next.js: quem já assinou, e diretor pelo link

**Sistema externo:** NextJS (site das Reservas, área `/assinar`)

Cole o texto abaixo da linha `---` num Claude Code aberto no repositório do site.

---

## O que mudou no Lara (09/11/2026)

O Lara mudou duas coisas na assinatura remota (`/api/assinatura/remota/*`). Nenhuma rota, corpo de requisição ou código de `reason` mudou. As mudanças só acrescentam:

1. **Quem vai assinar sabe quem já assinou.** O objeto da sessão, devolvido por `POST /abrir`, `GET /sessao` e `POST /respostas`, ganhou o campo `signers`:

   ```json
   "signers": [
     { "name": "Maria de Souza", "role": "Contratante", "me": false, "signed": true,
       "signed_at": "2026-11-09T10:12:00-03:00", "status": "Assinado" },
     { "name": "João da Silva", "role": "Testemunha", "me": true, "signed": false,
       "signed_at": null, "status": "Pendente" }
   ]
   ```

   - Vem com todos os signatários do documento, na ordem em que aparecem no documento.
   - `me: true` marca quem está com o link.
   - `signed_at` vem em ISO 8601, ou `null` enquanto a pessoa não assinou.
   - `status` é o texto da situação, em português.
   - O campo não traz CPF, e-mail nem telefone, de propósito.

2. **O PDF já mostra a assinatura de quem assinou antes.** Enquanto ninguém assinou, `GET /pdf` devolve o original de sempre. Depois que alguém assina, ele devolve o mesmo texto com o traço, o visto e a data de quem já assinou. Quem ainda não assinou continua em branco. `document.pdf_version` **não** muda quando outra pessoa assina; ele só muda quando as respostas refazem o texto.

Também mudou uma regra de quem recebe link: o diretor do clube agora também pode receber o link. Para o site, ele é uma pessoa como qualquer outra, com CPF completo e foto obrigatória, e não há nada a mudar por causa disso.

## O que fazer no site

1. **Mostrar os signatários na etapa de leitura.** Mostre o bloco acima do PDF só quando `signers` tiver mais de um item. Comece com o resumo "N de M já assinaram". Depois, um item por pessoa com:
   - o nome e a `role` (se vier);
   - "assinou em dd/mm às hh:mm" para quem tem `signed: true`, formatando `signed_at` no fuso `America/Sao_Paulo`;
   - "você" para quem tem `me: true`;
   - `status` em minúsculas para os outros pendentes.

   Destaque quem já assinou (cor de sucesso do sistema de design) e quem está com o link. Use os componentes que já existem. O bloco precisa caber em 360 px de largura, com quebra de linha, sem rolagem horizontal.
2. **Tratar `signers` como opcional.** Se o campo não vier, não mostre o bloco e não quebre a tela. Isso cobre um Lara ainda sem a mudança.
3. **Não guardar o PDF em cache.** Busque `GET /pdf` na hora de mostrar a leitura, tanto no Next quanto no navegador. Repasse o `Cache-Control: private, max-age=0, no-store` que o Lara já envia. O arquivo pode mudar entre duas aberturas do link sem que `pdf_version` mude.
4. **Manter a privacidade.** Os nomes de outras pessoas não podem ir para log, analytics ou meta tags.
5. **Testes.** Com a API mockada, teste:
   - documento com um signatário: o bloco não aparece;
   - documento com dois signatários, um já assinado: o bloco aparece com "1 de 2" e a data formatada;
   - sessão sem `signers`: a tela segue normal.

## O que não mudar

- A ordem das etapas (CPF antes do documento), os cookies httpOnly, os headers `X-Signer-Ip` e `X-Signer-User-Agent` e a lógica de `pdf_version` para detectar resposta corrigida.
- Nenhuma regra é decidida no site. Se o Lara recusar a abertura do link de um diretor (por exemplo, "Este documento vai para a diretoria depois da assinatura de…"), mostre o `error` que veio, como em qualquer outra recusa.

Referência completa: `docs/funcionalidades/assinatura-remota-prompt-site.md`, no repositório do Lara.
