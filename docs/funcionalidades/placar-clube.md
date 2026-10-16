# Placar Clube — cadastro

## O que é

Cadastro esportivo que alimenta o **placar eletrônico**: equipes (clubes), times (o recorte de
uma equipe por modalidade e categoria), jogadores, competições e jogos. O placar lê daqui os
jogos e os elencos, e devolve os lances; a súmula e o scout ficam em **Súmulas**.

Contrato da API do placar: [placar-clube-api.md](../placar-clube-api.md).

## Para quem

Setor de Esporte, com a permissão `placar.cadastro` (cadastro) e/ou `placar.scout` (súmulas).

## Pré-requisitos

A ordem natural do cadastro é **Equipe → Time → Jogadores (elenco) → Competição → Jogo →
Escalação**. Um time precisa de equipe; um jogo precisa de dois times da mesma modalidade e da
mesma categoria.

## Fluxo passo a passo

### Os dois botões do cadastro

Todo formulário de criação (equipe, time, jogador, competição, jogo) termina com:

- **Cadastrar**: grava e volta para a **lista**. O aviso de sucesso traz um atalho para o
  próximo passo:

  | Cadastro | Atalho no aviso |
  |---|---|
  | Equipe | Criar um time desta equipe (o formulário já vem com a equipe escolhida) |
  | Time | Montar o elenco deste time |
  | Jogador | Abrir a ficha (foto e vídeo) |
  | Competição | Abrir a competição |
  | Jogo | Montar a escalação |

- **Cadastrar e criar outro**: grava e reabre o formulário **vazio**, com os campos que
  costumam se repetir já preenchidos:

  | Cadastro | Volta preenchido |
  |---|---|
  | Equipe | nada (cada equipe é um clube diferente) |
  | Time | equipe, modalidade e categoria |
  | Jogador | equipe e modalidade |
  | Competição | modalidade e temporada |
  | Jogo | modalidade, categoria, competição, data/hora e local (os times, nunca) |

Apertar **Enter** no formulário equivale a **Cadastrar**.

### Novo jogo

1. Escolha a **modalidade**. As listas de categoria, times e competições passam a mostrar só
   o que é dela.
2. Escolha a **categoria** (opcional, mas recomendado). As listas de times passam a mostrar só
   os times dessa categoria. A lista de categorias mostra só as que têm time ativo naquela
   modalidade, e "Sub 15" e "Sub-15" aparecem como uma opção só.
3. Escolha **time da casa** e **time visitante**. Cada time aparece como
   `Nome (Equipe · Modalidade · Categoria)`, então a categoria fica visível mesmo quando o
   time tem nome de exibição próprio.
4. Competição, data/hora e local.

## Regras de negócio

- Os dois times de um jogo precisam ser da **mesma modalidade** e da **mesma categoria**. A
  tela já filtra; o servidor confere de novo e recusa com mensagem no campo do visitante.
- Categoria é texto livre, mas grafias equivalentes ("Sub 15", "sub15") são unificadas na que
  já existe.
- Ao criar um time que já existe (mesma equipe, modalidade e categoria), o sistema abre o
  existente com um aviso, em vez de duplicar.

## Referência técnica

Resumo para desenvolvimento: [resumo-modulos/Placar Clube.md](../resumo-modulos/Placar%20Clube.md).
