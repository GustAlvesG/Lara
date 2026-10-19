# Prompts de ajuste — imagens de grupo e espaço por link externo (19/10/2026)

> No Lara, as imagens de **grupo** (`image_vertical`, `image_horizontal`) e de **espaço**
> (`image`) podem agora ser cadastradas por **link web** (imgur, postimg…), além do upload. O
> campo passa a guardar, conforme o caso, o nome do arquivo ou a URL completa.
>
> Há dois prompts abaixo, cada um para colar (da linha `---` dele em diante) em um Claude Code
> aberto no repositório correspondente, que **já está implementado**:
>
> 1. **Site de locação** (Next.js) — quadras e o que já consome imagens de espaço.
> 2. **Front da iluminação por autoatendimento** — a tela que usa `/api/lighting/*`.
>
> Os espaços sociais, que o site ainda não implementou, já trazem isto no prompt deles
> ([reservas-sociais-prompt-site-locacao.md](reservas-sociais-prompt-site-locacao.md)).

## 1. Site de locação (Next.js)

---

# Ajuste: imagens de quadra e de grupo podem vir de outro domínio

No Lara, a equipe agora pode cadastrar a imagem de um grupo ou de uma quadra por **link
externo**, além do upload. O formato da API não mudou, mas o **conteúdo** do campo pode:

- `GET /api/places/group`, `/api/places/group/{category}` e `POST /api/places` já devolvem
  `image`, `image_vertical` e `image_horizontal` como **URL completa**. O que muda é o **host**:
  além do domínio do Lara, pode ser `i.imgur.com`, `i.postimg.cc` ou outro site.
- `GET /api/place/{id}` devolve `image` **cru**, como sempre: ou o nome do arquivo
  (`1712...jpg`, que o site monta como `<host do Lara>/images/<nome>`) ou, agora, uma URL
  completa `https://…`.

O que fazer:

1. **Use a URL como vem** quando o valor começa com `http(s)://` — não acrescente o domínio do
   Lara nem `/images/`. Só monte o endereço quando for nome de arquivo (o caso do
   `/api/place/{id}`). Centralize isso numa função (`imageSrc(value)`) usada em todo lugar.
2. **`next/image`**: se o site usa, libere os hosts externos em `images.remotePatterns` do
   `next.config` — no mínimo `https://i.imgur.com/**` e `https://i.postimg.cc/**`. Como a equipe
   pode usar outro host, considere `unoptimized` (ou `<img>` comum) para as imagens de
   grupo/quadra, em vez de manter uma lista que sempre vai faltar alguém.
3. **Fallback**: imagem `null` ou que não carrega (link apagado no imgur, por exemplo) precisa de
   um visual de reserva — o mesmo placeholder que o site já usa quando não há imagem.
4. Se houver CSP (`img-src`) configurada, inclua `https:` ou os hosts acima.

Teste com um grupo cuja imagem seja `https://i.imgur.com/<id>.jpg` e com outro cuja imagem seja
arquivo do Lara: as duas precisam aparecer.

## 2. Front da iluminação por autoatendimento

---

# Ajuste: use `image_url` para a imagem da quadra

Em `GET /api/lighting/groups/{id}/places`, cada quadra ganhou o campo **`image_url`**: a URL
pronta para o `<img>`, ou `null` quando a quadra não tem imagem.

```jsonc
{ "id": 10, "name": "Quadra 1 (coberta)",
  "image": "1712...jpg",                                    // como antes (cru)
  "image_url": "https://<host do Lara>/images/1712...jpg",  // novo: use este
  "open": true, … }

{ "id": 20, "name": "Quadra 2",
  "image": "https://i.imgur.com/abc1234.jpg",               // agora pode ser link externo
  "image_url": "https://i.imgur.com/abc1234.jpg", … }
```

Por quê: a imagem pode agora ser cadastrada por **link externo** (imgur, postimg…). Nesses casos
`image` já é uma URL completa, e montar o endereço a partir do nome do arquivo (como o prompt
original orientava) gera um link quebrado.

O que fazer:

1. Troque a montagem da URL a partir de `image` por **`image_url`**, direto no `<img>`.
2. `image` continua vindo, sem mudança, só para não quebrar quem ainda o usa — não dependa mais
   dele.
3. Se usar `next/image`, libere `https://i.imgur.com/**` e `https://i.postimg.cc/**` em
   `images.remotePatterns`, ou use `<img>`/`unoptimized` para essas imagens.
4. Mantenha o placeholder para `image_url: null` e para imagem que não carrega.
