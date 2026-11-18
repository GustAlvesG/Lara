# Fila de Espera das modalidades (não sócios)

## O que é

Uma lista de espera por **turma** para **não sócios** que querem vaga numa modalidade (natação,
tênis, ballet…) quando a turma está cheia. Fica no InfoClube, aba **Fila de Espera**
(`/fila-espera`), desde 10/11/2026.

**Não tem vínculo com o MultiClubes.** As turmas e a fila dos **sócios** continuam no
MultiClubes, e o sócio tem prioridade sobre o não sócio: quem confere isso é a secretaria, antes
de ligar (a tela da fila da turma lembra). A integração com o MultiClubes ficou de fora de
propósito: o processo de lá (fila automática, pré-matrícula, limite de fila) é complexo demais
para agora. O que foi levantado para uma futura integração está no fim deste documento.

## Para quem

Permissão do catálogo **`infoclube.fila-espera`** (grupo InfoClube, "Fila de espera das
modalidades (não sócios): turmas, inclusão, ligações e saída da fila"). Uma permissão só cobre
tudo. Nasce sem setor: dê à secretaria na tela de Setores. Os setores de acesso total (Gerência,
Diretoria, TI) já a alcançam.

É separada da leitura do InfoClube (que é de todo mundo logado) porque a fila guarda dados
pessoais: CPF, telefone, nascimento, responsável.

## Telas

| Tela | Rota | O que faz |
|---|---|---|
| Turmas | `GET /fila-espera` (`waitlist.index`) | Turmas com quantos aguardam e desde quando o primeiro espera. Busca por modalidade ou turma; filtro ativas / inativas / todas. Botões **Nova turma** e **Incluir pessoa**. |
| Pessoas | `GET /fila-espera/pessoas` (`waitlist.people.index`) | Não sócios cadastrados, com em quantas turmas aguardam. Busca por nome, CPF ou telefone. Filtro **Sem CPF (n)** (`?sem_cpf=1`), que só aparece enquanto houver alguém sem CPF. |
| Fila da turma | `GET /fila-espera/turmas/{id}` (`waitlist.classes.show`) | Quem aguarda, na ordem (posição, idade, telefone, desde quando, última ligação), com a etiqueta **Sem CPF** em quem não tem. Aba **Saíram da fila** com situação, datas e motivo. |
| Turma nova / editar | `/fila-espera/turmas/nova`, `/fila-espera/turmas/{id}/editar` | Modalidade (com sugestão das já usadas), nome da turma, observação, ativa. Excluir só turma que nunca teve ninguém. |
| Incluir pessoa | `GET /fila-espera/pessoas/nova` (`waitlist.people.create`) | Começa pelo **CPF**. CPF já cadastrado abre a ficha (não duplica); CPF novo abre o formulário com as turmas. O link **Cadastrar sem CPF** (`?sem_cpf=1`) abre o formulário direto, com o CPF opcional. |
| Ficha da pessoa | `GET /fila-espera/pessoas/{id}` (`waitlist.people.show`) | Um cartão por fila em que aguarda (posição, registrar ligação, histórico de ligações, tirar da fila), **Incluir em outras turmas**, **Saiu da fila** (com **Reativar**) e **Editar cadastro** (âncora `#cadastro`). Cada fila tem a âncora `#fila-{id}`. Sem CPF: faixa de aviso, as ações viram um lembrete e o **Editar cadastro** já vem aberto. |

## Regras

- **CPF de todos, inclusive de menor**, validado pelos dígitos verificadores, **gravado só
  com dígitos** e **único**: é ele que diz que a pessoa da fila da natação é a mesma da fila do
  tênis.
- **Cadastro sem CPF (desde 18/11/2026).** As listas antigas vieram de planilhas sem CPF, e ir
  atrás de todos antes de cadastrar era inviável. Por isso o **cadastro** aceita a pessoa sem
  CPF (`cpf` NULL), e ela já entra nas turmas marcadas no próprio cadastro (ou pela importação
  das planilhas). **Qualquer ação posterior exige o CPF antes:** registrar ligação, tirar da
  fila, reativar, incluir em outra turma e salvar o **Editar cadastro** (que é por onde o CPF é
  completado). A pessoa sem CPF continua na fila, na posição dela, e aparece com a etiqueta
  **Sem CPF**. CPF informado continua sendo validado e único: completar com o CPF de outro
  cadastro é recusado ("Já existe uma pessoa na fila de espera com este CPF."). Sem CPF o Lara
  não percebe duplicidade: a tela de cadastro sem CPF pede para conferir a lista antes. Não há
  junção automática de cadastros duplicados.
- **Várias filas ao mesmo tempo.** Uma pessoa pode aguardar em quantas turmas quiser, mas só uma
  vez em cada (incluir de novo na mesma turma é ignorado e avisado).
- **Ordem de chegada.** A posição é a data e hora de entrada (`joined_at`), com o id como
  desempate. Quem entra vai para o fim, com a data de hoje. Não há troca de turma: entra na fila
  nova (que conta a partir de hoje) e, se for o caso, sai da antiga.
- **Ligação não muda a posição.** A secretaria liga e registra o resultado: não atendeu, deixou
  recado, pediu para ligar depois, não quer a vaga agora (continua na fila), aceitou a vaga,
  telefone errado. Nenhum deles tira a pessoa da fila.
- **Sair da fila é decisão da secretaria**: *Matriculado*, *Desistiu* ou *Removido* (este pede
  motivo). Grava quem e quando. Ao marcar uma saída, o aviso lista as outras filas em que a
  pessoa continua, para a secretaria decidir se encerra também. A matrícula de verdade continua
  sendo feita no MultiClubes.
- **Reativar** devolve a pessoa à **posição original** (a data de entrada não muda). Recusa se
  ela já voltou à mesma turma por outra inscrição.
- **Turma inativa** não recebe gente nova; a fila dela continua visível. **Excluir** só turma
  que nunca teve ninguém; a que teve guarda o histórico e se desativa.
- Ligação para quem já saiu da fila é recusada ("Reative a inscrição antes").
- Ação em cadastro sem CPF é recusada com "<nome> está sem CPF. Para <ação>, preencha o CPF em
  "Editar cadastro" antes." (`WaitlistService::ensureHasCpf()`).

## Dados

| Tabela | Conteúdo |
|---|---|
| `waitlist_classes` | `modality`, `name` (únicos juntos), `notes`, `active`, `created_by`. |
| `waitlist_people` | `cpf` (11 dígitos, único, **NULL** = sem CPF desde 18/11/2026; vazio vira NULL no mutator), `name`, `birth_date`, `phone`, `phone_alt`, `email`, `guardian_name`, `guardian_phone`, `notes`, `created_by`. |
| `waitlist_entries` | Pessoa × turma: `joined_at` (a ordem), `status` (`waiting`, `enrolled`, `withdrawn`, `removed`), `closed_at`, `closed_by`, `close_reason`, `created_by`. |
| `waitlist_contacts` | Ligações: `waitlist_entry_id`, `result`, `notes`, `user_id`, `created_at`. |

Migrations: `2026_11_10_120000_create_waitlist_tables.php` (idempotente, nomes de índice curtos)
e `2026_11_18_100000_make_waitlist_people_cpf_nullable.php` (CPF anulável; o índice único
continua, porque o MySQL aceita vários NULL num UNIQUE; o `down` só volta a NOT NULL se ninguém
estiver sem CPF).
Nada é apagado: não há exclusão de pessoa nem de inscrição.

## Integrações

Nenhuma. Não chama o MultiClubes, não tem API e não manda mensagem. Não precisa de prompt para
outro sistema.

## Referência técnica

| Peça | Papel |
|---|---|
| `app/Http/Controllers/Waitlist/WaitlistClassController.php` | Lista de turmas, cadastro, fila da turma. |
| `app/Http/Controllers/Waitlist/WaitlistPersonController.php` | Pessoas, inclusão pelo CPF, ficha, incluir em outras turmas. |
| `app/Http/Controllers/Waitlist/WaitlistEntryController.php` | Registrar ligação, tirar da fila, reativar. |
| `app/Services/Waitlist/WaitlistService.php` | As regras (incluir sem duplicar, turma inativa, ligação só de quem aguarda, saída, reativação, CPF exigido nas ações posteriores: `ensureHasCpf()`; `enroll(..., onCreate: true)` só no cadastro). |
| `app/Models/Waitlist/*` | `WaitlistClass`, `WaitlistPerson` (CPF só dígitos ou NULL, `hasCpf()`, escopo `withoutCpf()`, idade), `WaitlistEntry` (situações, posição, dias na fila), `WaitlistContact` (resultados). |
| `app/Http/Requests/WaitlistPersonRequest.php`, `WaitlistClassRequest.php` | Validação (CPF opcional no cadastro, obrigatório na edição). |
| `resources/views/waitlist/*` | Telas. |
| `tests/Feature/Waitlist/WaitlistModuleTest.php` | Acesso, turmas, inclusão, CPF, cadastro sem CPF e o bloqueio das ações, ordem, ligações, saída, reativação e telas. |

Guia do usuário: [docs/guias/infoclube.md](../guias/infoclube.md), seções da Fila de Espera.

Importação das planilhas antigas: o prompt [docs/prompts/fila-espera-importacao-planilhas-prompt.md](../prompts/fila-espera-importacao-planilhas-prompt.md)
orienta um agente a gerar o SQL (a pessoa sem CPF entra sem CPF, desde 18/11/2026).

## Para uma futura integração com o MultiClubes

Levantado em 10/11/2026 com a consulta de diagnóstico do usuário (só leitura), não implementado:

- Turmas em `dbo.Classes` (`Id`, `Name`, `Discipline`, `Active`, `Capacity`, `QueueType`,
  `QueueCapacity`), modalidade em `dbo.Disciplines`.
- Matrículas e fila em `dbo.Registers` (`Class`, `Member`, `QueueDate`, `JoinDate`, `EndDate`,
  `IsPreRegister`): **na fila** = `JoinDate` e `EndDate` nulos; **matriculado** = `JoinDate`
  preenchido e `EndDate` nulo ou futuro. Fila automática em `dbo.RegisterAutomaticQueues`;
  auditoria em `Audit.Actions` (30/31 = entrou na fila pelo Portal/secretaria).
- Em aberto: se a pré-matrícula (`IsPreRegister`) conta como fila e ocupa vaga, e se o
  `QueueCapacity` valeria também para a fila de não sócios.
