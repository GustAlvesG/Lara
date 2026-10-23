# Guia — Torneios (setor Esporte)

Passo a passo de um torneio no Lara, do cadastro ao resultado final. As regras completas
estão em [torneios.md](torneios.md).

Menu: **Torneios** (Torneios, Categorias, Financeiro). O que cada pessoa vê depende das
permissões do setor (tela de Setores): `torneios.gerenciar`, `torneios.inscricoes`,
`torneios.resultados`, `torneios.pagamentos.baixa-manual`, `torneios.pagamentos.estornar` e
`torneios.financeiro`.

## 1. Catálogo de categorias (uma vez por modalidade)

**Torneios › Categorias**, escolha o grupo de espaços (ex.: Beach Tennis) e cadastre as
categorias que o clube costuma ter: nome, pessoas por equipe (1 = individual), sexo
(masculino, feminino ou livre) e idade mínima/máxima. A idade conta na **data de início do
torneio**. O catálogo é reaproveitado em todos os torneios do grupo.

## 2. Criar o torneio

**Torneios › Novo torneio**: grupo de espaços, nome, descrição, regulamento, período de
inscrição (data e hora), primeiro e último dia e imagem. Ele nasce em **rascunho** (não
aparece no site).

Na tela do torneio, em **Categorias**, ligue as do catálogo, cada uma com:

- **preço por pessoa** e **vagas** (vazio = sem limite; a vaga é de quem paga primeiro);
- **montagem dos times**: o organizador monta, sorteio, ou o capitão convida pelo site;
- **formato**: eliminatória simples, ou grupos + mata-mata (número de grupos e
  classificados por grupo);
- **sets para vencer** e se tem **disputa de 3º lugar**.

Clique em **Publicar no site**. Durante o período de inscrição os sócios se inscrevem e pagam
pelo site (eRede).

## 3. Inscrições

Botão **Inscrições** na tela do torneio.

- **Inscrever pelo balcão**: CPF, matrícula e categorias. A pessoa precisa ter conta no
  site (se não tiver, crie em **Reservas › Contas do site**). Se sexo ou idade não batem, só
  entra com justificativa.
- **Baixa manual**: quem pagou na secretaria (dinheiro, Pix, maquininha). Marque as
  inscrições pendentes da pessoa, escolha a forma e grave.
- **Cancelar**: com motivo. *Estornar pela eRede* devolve o valor daquela inscrição.

## 4. Times

Botão **Times** em cada categoria.

- **Sem time**: marque as pessoas e clique em *Montar time com os marcados*, ou *Sortear
  entre os pagos* (só fecha times inteiros; quem sobra fica sem time para você decidir).
- Em cada time, **Ajustar**: nome, capitão, **cabeça de chave** (1, 2, 3… — usado no
  sorteio do chaveamento), incluir ou tirar atleta, desfazer.
- Categoria de capitão: os capitães montam pelo site; você pode ajustar aqui.
- **Completo** = tem todos os atletas e todos pagaram.

## 5. Chaveamento

Botão **Chaveamento** em cada categoria.

1. Confira o aviso de times **em formação** — eles entram assim mesmo.
2. **Gerar por sorteio** (cabeças de chave primeiro) ou **Montar à mão** (os dois lados de
   cada jogo da 1ª rodada, ou o grupo de cada time).
3. Confira a chave. Pode **sortear de novo** ou **desfazer** enquanto nenhum jogo tiver
   resultado.
4. Marque **Data e local** dos jogos, se quiser (é só informativo).
5. Clique em **Divulgar no site**. Gerar de novo tira do site até você divulgar outra vez.

## 6. Resultados

### No Lara

Na tela do chaveamento, em cada jogo: **Lançar resultado** — os games de cada set
(tie-break opcional) ou W.O. O vencedor vai sozinho para o próximo jogo. Resultado lançado no
Lara **trava** o jogo para o site. **Corrigir** e **Limpar** ficam no mesmo lugar; cada jogo
tem o **Histórico** de quem lançou.

Para corrigir um jogo cujo vencedor mudaria, o jogo seguinte não pode ter resultado: limpe
aquele antes.

### Pelo site (equipe na quadra)

Na tela do torneio, **Lançamento de resultados pelo site › Gerar código**. Anote o código
(ele aparece uma vez) e passe para quem vai lançar; essa pessoa entra na tela da equipe no
site com o código e o nome dela. O código vale até o fim do último dia do torneio. **Gerar
outro** invalida o anterior; **Retirar** corta o site na hora.

### Fechar os grupos (grupos + mata-mata)

Quando todos os jogos de um grupo têm resultado, aparece **Fechar o grupo**, já na ordem da
classificação (vitórias, saldo de sets, saldo de games). Em caso de empate, ajuste a ordem
como vocês decidirem e clique em **Fechar e classificar**: os classificados entram no
mata-mata. Dá para mudar a ordem ou **reabrir** enquanto o mata-mata que recebe do grupo
não tiver resultado.

## 7. Situação do torneio

Na tela do torneio: **Iniciar torneio** quando começar e **Encerrar** no fim. Depois de
encerrado, o site não lança mais resultado (o Lara ainda corrige). **Cancelar** não estorna
ninguém sozinho: cancele cada inscrição em Inscrições.

## 8. Financeiro

**Torneios › Financeiro**: recebido, estornado, líquido e a receber de cada torneio. No
torneio, os totais por categoria (eRede e manual separados) e a lista de pagamentos, com
**CSV** para o Excel.

## Problemas comuns

| Situação | O que fazer |
|---|---|
| Sócio não consegue entrar no site / esqueceu a senha / e-mail errado | **Reservas › Contas do site** ([contas-do-site.md](contas-do-site.md)). |
| "Não há vaga" no pagamento | As vagas são de quem paga primeiro; aumente as vagas da categoria se for o caso. |
| O site diz que o jogo está travado | Foi lançado no Lara. Corrija no Lara ou limpe o resultado para o site lançar. |
| Não deixa mudar o formato da categoria | O chaveamento já foi gerado: desfaça o chaveamento antes. |
| Não deixa gerar de novo o chaveamento | Já há resultado lançado. Limpe os resultados, se for mesmo o caso. |
| A equipe da quadra perdeu o código | Gere outro na tela do torneio (o anterior deixa de valer). |
