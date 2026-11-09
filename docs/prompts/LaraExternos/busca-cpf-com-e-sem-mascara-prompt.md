# Prompt para o agent do Lara Externos: busca por CPF com e sem máscara

**Sistema externo:** Lara Externos (bot de freelancers e app da guarita / Monitor de Acesso da portaria)

## O que mudou no Lara (09/11/2026)

Alguns cadastros antigos guardam o CPF com máscara (`062.134.687-06`), principalmente prestadores de empresas parceiras de 06/2026 e alguns freelancers. Quando a pessoa digitava só os números (`06213468706`), o Lara respondia "não encontrado", mesmo com a pessoa cadastrada.

Agora o Lara procura o CPF nos dois formatos, venha ele como vier:

- `GET /api/telegram/freelancer/freelancer/{cpf}` e `GET /api/telegram/freelancer/freelancer/{cpf}/services`: acham o freelancer com o CPF gravado com ou sem máscara.
- API da portaria `/api/company-access/*` (`validate-access`, `register-access`): o `target` com CPF acha terceirizado, freelancer e liberação pontual nos dois formatos.

**Nenhum contrato mudou.** Rotas, corpo, respostas, códigos de status e `reason` continuam os mesmos.

## O que conferir na aplicação

1. **Não descarte o CPF antes de chamar o Lara.** Se o app valida ou formata o CPF no próprio aparelho, garanta que manda os 11 dígitos, com ou sem pontuação. As duas formas funcionam. Não corte dígitos: há freelancers no banco com CPF incompleto (9 dígitos), e esses só se resolvem corrigindo o cadastro no Lara.
2. **Sem cache de "não encontrado".** Se o app guarda localmente que um CPF "não existe", limpe esse cache ou dê a ele um prazo curto. Pessoas que antes vinham como 404 agora são encontradas.
3. **Fluxo de cadastro do bot.** O bot cadastra quando a consulta dá 404. Com a correção, o 404 deixa de aparecer para quem já tinha cadastro com máscara. Assim, o bot não tenta mais criar um cadastro em duplicidade. Não é preciso mudar nada além disso.

Se o app já manda o CPF como o usuário digitou e não guarda cache de "não encontrado", não há o que mudar.
