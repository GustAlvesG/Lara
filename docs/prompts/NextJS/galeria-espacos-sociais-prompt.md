# Prompt para o agent do site Next.js: galeria de fotos (carrossel) dos espaços sociais

**Sistema externo:** NextJS (site das Reservas: espaços sociais, churrasqueiras e salões)

## O que mudou no Lara (10/11/2026)

Os espaços sociais ganharam uma **galeria de fotos** no Lara, que a equipe monta no painel: fotos em ordem, com legenda opcional, e algumas podem ficar escondidas. O site deve mostrar essas fotos num **carrossel** na página de cada espaço, junto com a capa (`image`/`cover`), que continua existindo.

Há também uma **vitrine pública** nova, que pede só o `api_token`, sem o `Session` do sócio. Com ela, quem ainda não entrou no site também consegue ver os espaços e as fotos.

## Regra principal: o navegador não acessa o Lara

O navegador de quem visita o site **não alcança o endereço do Lara**. Por isso:

- **Nunca** coloque no HTML um endereço do Lara: nem `url`, nem `thumb_url`, nem o `LARA_URL`.
- As fotos são **arquivos estáticos** no Lara (`/images/galeria/...`), entregues direto pelo servidor web. O **servidor do Next.js** busca o arquivo e o serve **no domínio do site**.
- Monte o endereço com a base que o servidor do site já usa para chamar a API, mais o `path` que a API devolve: `${LARA_BASE}/${path}`. Os campos `url`/`thumb_url` trazem o host pelo qual a chamada chegou ao Lara; servem para conferência, mas prefira `path`.
- O nome do arquivo **nunca muda**: foto nova é arquivo novo, e foto excluída some da API. Por isso dá para guardar em cache para sempre (`Cache-Control: public, max-age=31536000, immutable`).

Duas formas aceitas (escolha a que combinar com o projeto):

1. **Rota proxy no Next** (recomendada), por exemplo `app/espacos/fotos/[...path]/route.ts`:
   - aceite **só** caminhos que comecem com `images/` e terminem em `.jpg`, `.jpeg`, `.png` ou `.webp`, sem `..`. Assim ela não vira um proxy aberto para o Lara;
   - busque `${LARA_BASE}/${path}` no servidor e devolva o corpo com o `Content-Type` da resposta e o cache longo acima;
   - repasse o 404 do Lara como 404;
   - no componente, use `src="/espacos/fotos/images/galeria/12/abc.jpg"`.
2. **`next/image` com o otimizador do próprio Next**: ponha o host do Lara em `images.remotePatterns` (só o pathname `/images/**`). Quem busca a imagem é o servidor do Next (`/_next/image?url=...`), e o navegador só fala com o site. Confira se o deploy do site roda o otimizador (não serve com `output: 'export'` nem com `unoptimized`).

**Exceção:** a capa (`cover`) pode ser um link externo (imgur, postimg). Nesse caso vem `"remote": true` e `"path": null`, e o navegador abre a `url` direto, sem proxy.

## Endpoints

### Vitrine pública (só `api_token`, sem `Session`)

`GET /api/social-showcase`, com limite de 120 chamadas por minuto:

```json
{
  "groups": [
    {
      "id": 3,
      "name": "Churrasqueiras e Salões",
      "cover": { "path": "images/abc.jpg", "url": "https://…/images/abc.jpg", "remote": false },
      "places": [
        {
          "id": 12,
          "name": "Churrasqueira 21",
          "description": "…",
          "amenities": "fogão e geladeira",
          "capacity": 40,
          "price": 350.0,
          "cover": { "path": null, "url": "https://i.imgur.com/xyz.jpg", "remote": true },
          "photos": [
            {
              "id": 41,
              "path": "images/galeria/12/Xk3a9.jpg",
              "thumb_path": "images/galeria/12/Xk3a9-thumb.jpg",
              "url": "https://…/images/galeria/12/Xk3a9.jpg",
              "thumb_url": "https://…/images/galeria/12/Xk3a9-thumb.jpg",
              "width": 1920,
              "height": 1280,
              "caption": "Vista da piscina"
            }
          ]
        }
      ]
    }
  ]
}
```

- Só vêm grupos sociais com pelo menos um espaço ativo, só espaços ativos e só fotos visíveis, **já em ordem**. Não reordene.
- `cover` pode ser `null`. `caption` pode ser `null`. `photos` pode ser `[]`: nesse caso mostre só a capa, sem controles de carrossel.

`GET /api/social-showcase/places/{id}` traz um espaço no mesmo formato, mais `"group": {"id", "name"}`. Responde 404 quando o espaço não existe, está inativo ou não é social.

### API com login (já existente)

`GET /api/social/groups/{group}/places` ganhou, em cada espaço, o campo `photos` no **mesmo formato** acima. O campo `image` (a capa, uma URL) continua como estava. Para a capa passar pelo proxy na tela logada, use a vitrine para saber o `path`, ou trate `image` como link externo quando não for do host do Lara.

## O que fazer no site

1. **Proxy das imagens**, como descrito acima (opção 1 ou 2), com a validação de caminho.
2. **Componente de carrossel** na página do espaço:
   - primeiro slide = capa (quando houver), depois as `photos` na ordem da API;
   - setas, bolinhas e gesto de arrastar no celular; teclado (← →) quando estiver em foco;
   - miniaturas (`thumb_path`) na faixa de navegação e na lista de espaços; foto grande (`path`) no slide;
   - `width`/`height` para reservar o espaço do slide e evitar pulo de layout (`aspect-ratio`);
   - lazy loading: só a capa e o primeiro slide carregam de imediato;
   - `caption` como legenda do slide e como `alt` (sem legenda: "Foto N de <nome do espaço>");
   - clique abre em tela cheia (lightbox), se o projeto já tiver um componente para isso.
3. **Página pública dos espaços** ("Conheça nossos espaços"), com `GET /api/social-showcase` sem exigir login: cartões com capa, nome e capacidade; ao abrir, o carrossel e a descrição. O botão "Reservar" leva ao fluxo que já existe e pede login.
4. **Na tela de reserva (logada)**, use `photos` de `/api/social/groups/{group}/places` para mostrar o mesmo carrossel no espaço escolhido.
5. **Cache da vitrine**: a lista muda pouco. Revalidar a cada 5–10 minutos (`revalidate`) é suficiente. As imagens, por terem nome imutável, ficam em cache longo.

## O que não fazer

- Não mande o navegador direto para o Lara (nem em `<img src>`, nem em `<a href>`, nem em `srcset`).
- Não crie um proxy genérico que aceite qualquer caminho do Lara: só `images/...` com extensão de imagem.
- Não peça `Session` na vitrine: ela é pública por decisão do clube.
- Não há upload pelo site: a galeria é montada só no painel do Lara.
