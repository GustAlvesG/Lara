# Prompt — agente da VM da IA: a IA passa a se chamar "HAL"

**Sistema externo:** IA (VM da IA do chat). **Origem:** O Lara, 05/11/2026.

## O que mudou no Lara

A IA do chat (o balão no canto inferior direito das telas d'O Lara) mudou de nome: deixou de ser
**"a Lara"** e passou a ser **"o HAL"**, uma referência ao computador de *2001: Uma Odisseia no
Espaço*. Do lado d'O Lara já mudaram o balão ("Olá! Eu sou o HAL."), o cabeçalho do painel, o
botão **Verificar com o HAL**, o item de menu **HAL (IA)**, a permissão **Chat com o HAL** e os
guias do usuário.

O **sistema** continua se chamando **O Lara** (masculino). Só a IA mudou de nome.

**A API não mudou.** `POST /perguntar`, `/reiniciar`, `/health` e as consultas
`/api/information/search`, `/api/avisos/search` e `/api/guias/search` seguem com os mesmos campos
e formatos. A única diferença de dado: a seção do guia da IA, devolvida por `/api/guias/search`,
agora vem com `modulo: "HAL (IA)"` (antes `"Lara (IA)"`), e o texto dos guias fala em "o HAL".

## O que implementar

1. **Persona / prompt de sistema:** troque a apresentação da IA para "o HAL", no masculino
   ("Eu sou o HAL", "o HAL não encontrou…", "ele"). Onde o prompt disser "Você é a Lara", passe
   para "Você é o HAL, o assistente de IA do clube dentro d'O Lara".
2. **Não confundir os dois nomes:** "O Lara" é o sistema (as telas, os módulos, o menu). Ao ensinar
   a usar o sistema, continue dizendo "O Lara" / "no Lara" / "d'O Lara". "O HAL" é só a própria IA.
3. **Pergunta com o nome antigo:** se alguém perguntar pela "Lara" referindo-se ao chat, responda
   normalmente e, se fizer sentido, diga que a assistente agora se chama HAL.
4. **Tom:** a referência ao filme é só no nome. Nada de imitar o HAL do filme (recusas do tipo
   "Sinto muito, Dave…"), que soaria como erro para quem não conhece a referência.
5. **Textos fixos da VM** (saudações, mensagens de erro, logs que alguém lê): troque "Lara" por
   "HAL" onde o nome se refere à IA. A frase de transbordo ("Vou te transferir para o setor
   responsável, só um momento!") não tem nome e fica igual.
6. **Não mude** nomes técnicos que o Lara usa para falar com a VM: o `usuario_id` continua
   `portal_{id}`, os endpoints e campos ficam iguais.

## Como conferir

- `POST /perguntar` com `"mensagem": "quem é você?"` → a resposta se apresenta como o HAL, no
  masculino.
- `"mensagem": "como abro o chat da IA?"` → a resposta cita **HAL (IA)** no menu e o balão no canto
  da tela, e chama o sistema de O Lara.

Contrato completo das consultas: `docs/funcionalidades/lara-ia-consultas.md` no repositório d'O Lara.
