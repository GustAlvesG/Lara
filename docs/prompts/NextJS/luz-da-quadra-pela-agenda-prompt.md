# Prompt para o agent do site Next.js: luz da quadra acesa pela agenda do Lara

**Sistema externo:** NextJS (site das Reservas, autoatendimento de iluminação `/api/lighting/*`)

## O que mudou no Lara (05/11/2026)

Na agenda de reservas do Lara (`/schedule`), a equipe agora acende a luz de uma quadra por N minutos (faixa "Luz da quadra", botão **Acender**). Acender uma quadra já acesa renova o prazo para a nova minutagem, contada a partir do clique.

Por dentro, é o mesmo comando manual do Home Assistant que o autoatendimento do sócio já usa. Ele é gravado com `origin = "Agenda de reservas"`.

**A API `/api/lighting/*` não mudou.** Rotas, corpo, respostas e códigos de `reason` são os mesmos. O Home Assistant também não precisa de ajuste: o endpoint de automação continua o mesmo.

## O que conferir no site

1. **Quadra acesa por fora do autoatendimento.** Uma quadra pode aparecer acesa sem nenhum sócio ter acionado, porque a equipe acendeu pela agenda. Se a tela do site supõe que "acesa" quer dizer "acionada por um sócio" (por exemplo, mostrando o nome de quem acendeu ou a contagem regressiva do sócio), confirme que ela não quebra quando não há acionamento de sócio vigente. Use só os campos que a API já devolve.
2. **Sócio que aciona depois.** Se um sócio acionar uma quadra que a equipe acendeu, o prazo da luz passa a ser o dos acionamentos dos sócios, e pode ficar menor que o da agenda. Não é preciso mudar nada no site por isso. Só não escreva na tela algo como "a luz vai ficar acesa até X" com base em suposições: mostre o prazo que a API devolve.
3. **Nenhuma chamada nova.** Não crie no site um botão de acender pela equipe. Esse fluxo é só do Lara.

Se a tela já segue a resposta da API sem supor quem acendeu, não há o que mudar.
