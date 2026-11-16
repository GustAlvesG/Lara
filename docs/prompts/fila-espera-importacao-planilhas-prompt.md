# Prompt: gerar o SQL de importação da Fila de Espera a partir de planilhas

**Para:** um agente Claude Code que vai receber, em anexo, as planilhas com a lista de espera atual.
**Sistema:** O Lara (Laravel, MySQL 8). Módulo InfoClube › Fila de Espera. Documentação:
`docs/funcionalidades/fila-de-espera.md`.

---

## Sua tarefa

Ler as planilhas anexadas e gerar **um arquivo `.sql` completo**, pronto para rodar no MySQL 8,
que coloca essas pessoas na Fila de Espera do Lara, mais um **relatório** do que ficou de fora
ou precisou de decisão.

**Você não roda nada no banco.** Não conecte em nenhum banco, não rode `migrate` nem o SQL
gerado. Quem roda é o usuário: primeiro no banco de homologação, depois no de produção.

## O que é a Fila de Espera

- Lista de espera por **turma** das modalidades (natação, tênis…), **só de não sócios**. A fila
  dos sócios fica em outro sistema (MultiClubes) e não entra aqui.
- A pessoa é identificada pelo **CPF**, que é obrigatório para todos, inclusive menores. Uma
  pessoa pode estar em várias turmas, mas só uma vez em cada.
- A ordem da fila é a **data de entrada** (`joined_at`). Quando duas pessoas entraram no mesmo
  instante, desempata o `id`, ou seja, a ordem em que as linhas foram inseridas.

## Tabelas de destino (já existem no banco)

```
waitlist_classes  id, modality VARCHAR(100), name VARCHAR(150), notes VARCHAR(255) NULL,
                  active TINYINT(1) DEFAULT 1, created_by NULL, created_at, updated_at
                  UNIQUE (modality, name)

waitlist_people   id, cpf VARCHAR(11) UNIQUE (só dígitos), name VARCHAR(255), birth_date DATE NULL,
                  phone VARCHAR(20) NOT NULL, phone_alt VARCHAR(20) NULL, email VARCHAR(255) NULL,
                  guardian_name VARCHAR(255) NULL, guardian_phone VARCHAR(20) NULL, notes TEXT NULL,
                  created_by NULL, created_at, updated_at

waitlist_entries  id, waitlist_class_id FK, waitlist_person_id FK, joined_at DATETIME,
                  status VARCHAR(20) ('waiting' para todos da importação),
                  closed_at NULL, closed_by NULL, close_reason NULL, created_by NULL,
                  created_at, updated_at
```

Não mexa em `waitlist_contacts` (ligações) nem em nenhuma outra tabela.

## Como trabalhar

1. **Leia todas as planilhas e mostre ao usuário o que encontrou antes de gerar o SQL:** as abas,
   as colunas e uma amostra de linhas. Proponha o **mapeamento** de cada coluna para os campos
   acima e o **nome de modalidade e turma** de cada aba ou bloco. As planilhas podem ter uma aba
   por turma, a turma numa coluna ou a turma num título acima da tabela. **Pergunte o que não
   for óbvio** e espere a resposta.
2. **Normalize com um script** (Python ou PHP, no seu diretório de rascunho, fora do
   repositório):
   - **CPF:** só dígitos, completado com zeros à esquerda se a planilha comeu os zeros (o Excel
     faz isso), e **validado pelos dígitos verificadores**. Recuse CPF com 11 dígitos iguais. CPF
     inválido ou ausente **não entra**: vai para o relatório. Nunca invente nem "corrija" um CPF.
   - **Nome:** sem espaços nas pontas e com espaços duplos reduzidos. Não mude maiúsculas e
     minúsculas.
   - **Datas:** a planilha usa dd/mm/aaaa ou número serial do Excel. Converta para `AAAA-MM-DD`.
     Nascimento impossível (no futuro, ou mais de 100 anos atrás) fica `NULL` e vai para o
     relatório.
   - **Telefone:** mantenha como está escrito (até 20 caracteres). É obrigatório: se faltar e
     houver o telefone do responsável, use-o e anote no relatório; se não houver telefone
     nenhum, a linha vai para o relatório e não entra.
   - **Data de entrada na fila:** use a da planilha. Se não houver, **pergunte ao usuário** qual
     data usar. Mantenha a ordem da planilha pela coluna de desempate (posição ou número da
     linha).
   - **Modalidade e turma:** escritas sempre do mesmo jeito ("Natação" e "Natacao" seriam duas
     turmas diferentes). Liste as variações que encontrar e confirme com o usuário.
   - **Sócios:** se a planilha indicar que a pessoa é sócia (coluna, cor ou observação), ela
     **não entra**. Liste no relatório.
3. **Mesmo CPF em várias linhas:**
   - na mesma turma: fica só a linha com a data de entrada mais antiga, e a duplicata vai para o
     relatório;
   - em turmas diferentes: é a mesma pessoa em várias filas, o que é normal;
   - com nomes ou datas de nascimento diferentes: fica o primeiro valor encontrado, e a
     divergência vai para o relatório.
4. **Gere o SQL** no formato abaixo. Os valores já vêm normalizados pelo seu script, com aspas
   escapadas corretamente. Uma linha de `INSERT` por pessoa em cada turma, em lotes de até 500.

## Formato obrigatório do SQL

```sql
-- Importação da Fila de Espera — gerado em <data> a partir de <arquivos>
-- <N> turmas, <N> pessoas, <N> inscrições. Ver relatório para o que ficou de fora.

DROP TABLE IF EXISTS waitlist_import;
CREATE TABLE waitlist_import (
    modalidade VARCHAR(100) NOT NULL, turma VARCHAR(150) NOT NULL,
    cpf CHAR(11) NOT NULL, nome VARCHAR(255) NOT NULL, nascimento DATE NULL,
    telefone VARCHAR(20) NOT NULL, telefone2 VARCHAR(20) NULL, email VARCHAR(255) NULL,
    responsavel VARCHAR(255) NULL, tel_responsavel VARCHAR(20) NULL, observacao TEXT NULL,
    data_entrada DATETIME NOT NULL, ordem INT NOT NULL
);

INSERT INTO waitlist_import VALUES
 (...),
 (...);

START TRANSACTION;

INSERT IGNORE INTO waitlist_classes (modality, name, active, created_at, updated_at)
SELECT DISTINCT modalidade, turma, 1, NOW(), NOW() FROM waitlist_import;

INSERT IGNORE INTO waitlist_people
 (cpf, name, birth_date, phone, phone_alt, email, guardian_name, guardian_phone, notes, created_at, updated_at)
SELECT cpf, MAX(nome), MAX(nascimento), MAX(telefone), MAX(telefone2), MAX(email),
       MAX(responsavel), MAX(tel_responsavel), MAX(observacao), NOW(), NOW()
FROM waitlist_import GROUP BY cpf;

INSERT INTO waitlist_entries (waitlist_class_id, waitlist_person_id, joined_at, status, created_at, updated_at)
SELECT c.id, p.id, i.data_entrada, 'waiting', NOW(), NOW()
FROM waitlist_import i
JOIN waitlist_classes c ON c.modality = i.modalidade AND c.name = i.turma
JOIN waitlist_people  p ON p.cpf = i.cpf
WHERE NOT EXISTS (SELECT 1 FROM waitlist_entries e
                  WHERE e.waitlist_class_id = c.id AND e.waitlist_person_id = p.id AND e.status = 'waiting')
ORDER BY c.id, i.data_entrada, i.ordem;

COMMIT;

-- Conferência: a fila de cada turma como o Lara mostra
SELECT c.modality, c.name, ROW_NUMBER() OVER (PARTITION BY c.id ORDER BY e.joined_at, e.id) AS posicao,
       p.name, e.joined_at
FROM waitlist_entries e
JOIN waitlist_classes c ON c.id = e.waitlist_class_id
JOIN waitlist_people  p ON p.id = e.waitlist_person_id
WHERE e.status = 'waiting'
ORDER BY c.modality, c.name, posicao;

DROP TABLE waitlist_import;
```

O SQL pode rodar mais de uma vez sem duplicar nada: turma e CPF que já existem são mantidos, e
inscrição que já está aguardando não é criada de novo. Pessoa que já tem cadastro no Lara
**mantém os dados que tem**; a importação só acrescenta turmas.

## Entregas

1. `fila-espera-importacao.sql`, no formato acima.
2. `fila-espera-importacao-relatorio.md`, com:
   - totais por turma: quantas linhas havia na planilha e quantas entram;
   - linhas que **não entram**, com arquivo, aba, linha da planilha, nome e motivo (CPF
     inválido ou ausente, sem telefone, sócio, duplicata na mesma turma);
   - **ajustes** feitos (telefone do responsável usado, nascimento descartado, nomes
     divergentes no mesmo CPF, variações de turma unificadas);
   - as decisões que o usuário tomou durante o trabalho.
3. Na resposta final: onde estão os dois arquivos, os totais e o lembrete de rodar primeiro em
   homologação.

## Não faça

- Não grave CPF com máscara nem com menos de 11 dígitos.
- Não use `REPLACE INTO`, `UPDATE` ou `DELETE` nas tabelas do Lara. Não use `ON DUPLICATE KEY
  UPDATE`, porque ele sobrescreveria quem já está cadastrado.
- Não crie inscrição com `status` diferente de `'waiting'`.
- Não coloque dados das planilhas no repositório. Os arquivos gerados ficam fora dele, ou onde
  o usuário pedir, porque têm CPF e telefone.
- Não suponha a turma, a data de entrada ou se a pessoa é sócia: pergunte.
