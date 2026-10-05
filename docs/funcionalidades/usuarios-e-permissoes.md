# Usuários, Setores e Permissões

## O que é

O controle de acesso do painel. Cada pessoa alcança o que os **setores** dela
alcançam, somado às **permissões individuais** que tiver. Três setores têm
**acesso total**: Gerência, Diretoria e TI.

As roles do Spatie (`admin`, `secretaria`, `comercial`, `user`) não decidem mais
nada. Ver [Autenticação e Permissões](../04-autenticacao-e-permissoes.md#44-acesso-do-painel-setores-e-permissões)
para o mecanismo.

## Quem alcança o quê (matriz inicial)

A matriz nasce com a migration `sync_access_catalog` e é editada na tela de
Setores. "Secretaria" é o setor **Atendimento**; "Financeiro" é a
**Contabilidade** (e o setor **Finanças**, se existir, recebe o mesmo).

| Menu | Permissão | Setores |
|------|-----------|---------|
| InfoClube › Informações e Avisos | — (todo mundo logado) | — |
| InfoClube › criar/editar/excluir informação | `infoclube.editar` | Atendimento |
| SIV › Busca | `siv.busca` | Atendimento, Portaria |
| SIV › Placas Diretoria | `siv.placas-diretoria` | Atendimento |
| SIV › Frota, Viagens, Veículos | `siv.frota`, `siv.viagens`, `siv.veiculos` | Atendimento, Portaria, Manutenção |
| Home Assistant | `home-assistant` | Atendimento |
| Reservas › Agendamentos | `reservas.agendamentos` | Atendimento, Comercial |
| Reservas › Pagamentos (e estorno) | `reservas.pagamentos`, `reservas.pagamentos.estornar` | Atendimento, Contabilidade |
| Externos › Empresas e Monitor de Acesso | — (todo mundo logado) | — |
| Externos › Liberação Pontual | `externos.liberacao-pontual` | Atendimento |
| Externos › Histórico | `externos.historico` | Atendimento, Portaria |
| Externos › Carros de Aplicativo (e a fila "Aguardando Motorista", por dentro) | `externos.carros-aplicativo` | Atendimento, Portaria |
| Lara (IA) | `lara` | TI (acesso total) |
| Bot WhatsApp | `bot-whatsapp` | Atendimento, **só coordenador** |
| Compras | `compras` | Contabilidade |
| Carteirinhas | `carteirinhas` | RH |
| Freelancers › Freelancers | `freelancers.cadastro` | Atendimento, Comercial |
| Freelancers › Funções | `freelancers.funcoes` | Comercial |
| Freelancers › Serviços (só a lista, sem valores) | `freelancers.servicos.listar` | Atendimento, Comercial |
| Freelancers › Serviços (valores, documentos, registro, lotes) | `freelancers.servicos.gerenciar` | Comercial |
| Freelancers › Assinatura (Tablet) | `freelancers.assinatura` | Comercial |
| Freelancers › Acompanhamento | `freelancers.acompanhamento` | Comercial |
| Freelancers › Financeiro | `freelancers.financeiro` | Contabilidade |
| Placar Clube | `placar.cadastro`, `placar.scout` | Esporte |
| Replay (câmeras, layouts e vídeos das quadras) | `replay` | TI (acesso total); Marketing recebe na tela de Setores |
| Banco de Horas (administração) | `banco-horas.admin` | RH |
| Login da API do Telegram | `telegram.login` | Comercial |
| Locais e regras de reserva, Torneios, Sócios/Acessos | `reservas.configurar`, `torneios`, `socios.consulta` | TI (acesso total) |
| Usuários, Setores | `usuarios.gerenciar`, `setores.gerenciar` | acesso total |

O Smart Panel saiu do sistema.

### O que o acesso total não dá

Os **cargos**: aprovar lote de freelancer e cadastrar a diretoria (coordenador
da Gerência), validar contrato e assinar como contraparte no kiosk (coordenador
do Comercial), montar lote (coordenador de qualquer setor), o nível 1 da ordem
de compra e reabrir mapa de cotação (coordenador da Contabilidade), o nível 3
(Diretoria). E mapa de cotação fechado é somente leitura para todo mundo.

## As telas

### Usuários (`/users`, permissão `usuarios.gerenciar`)
- Lista com os setores de cada pessoa (★ = coordenador) e as permissões
  individuais.
- Edição em três blocos: **dados da conta**, **setores** (fora / colaborador /
  coordenador em cada um) e **permissões individuais**.
- Painel **Acesso efetivo**: cada permissão e de onde veio ("setor Portaria",
  "individual", ou acesso total) — é onde se responde "por que fulano leva 403".
- Histórico de acesso da pessoa.

### Setores (`/sectors`, permissão `setores.gerenciar`)
- Dados do setor e a marca de **acesso total**.
- **O que o setor alcança:** cada permissão do catálogo em "—", "Todos" ou
  "Só coordenadores".
- Membros: adicionar, trocar papel, remover.
- **Histórico de acesso** (`/sectors/audit`): toda mudança, com quem fez.

### Meu setor (`/meu-setor`, qualquer coordenador)
O coordenador cuida da equipe dos setores que coordena:
- coloca no setor, como colaborador, quem já tem usuário;
- **cadastra gente nova**, que já nasce colaboradora do setor — se o e-mail, a
  matrícula ou o CPF (só dígitos) já existirem, a tela oferece colocar a pessoa
  existente em vez de criar outra conta;
- tira colaborador do setor.

Não promove a coordenador, não tira coordenador, não mexe no que o setor
alcança nem em dados de quem já existe.

## Travas

Toda mudança passa pelo `AccessManager`, que recusa:
- deixar um setor de **acesso total sem nenhum membro**;
- tirar **de si mesmo** o acesso às telas de gestão (Usuários/Setores).

## Implantação (de roles para setores)

1. `php artisan migrate` — cria o catálogo, os setores que faltam (Portaria,
   por exemplo), a matriz inicial e as duas pontes: permissões individuais
   antigas viram as novas, e quem tinha a role `admin` recebe
   `usuarios.gerenciar` + `setores.gerenciar` individuais (para ninguém ficar
   sem acesso à tela de Setores).
2. `php artisan acesso:aplicar-de-para` (simulação) e depois `--aplicar` — leva
   quem tinha cada role para o setor indicado em `database/data/acesso-de-para.php`.
3. Colocar as pessoas nos setores certos (tela de Setores, ou os coordenadores
   em Meu setor) — em especial Gerência, Diretoria e TI.
4. `php artisan acesso:diferenca --so-perdas` — até a coluna "perde" mostrar só
   o que se quer. `(aberta)` marca telas que antes só pediam login.
5. Quando estiver limpo: `php artisan acesso:limpar-legado --confirmar` e tirar
   as permissões individuais da ponte dos admins que já estiverem em setor de
   acesso total.

## Referência técnica

- Catálogo e acesso: `app/Authorization/` (`Permissions`, `AccessResolver`,
  `UserAccess`, `AccessManager`, `LegacyPermissionMap`)
- Controllers: `UserController`, `SectorController`, `MySectorController`
- Model: [`User`](../models.md#sócios-e-usuários) (`access()`, `hasAccess()`,
  `hasFullAccess()`, `directPermissions()`), `Sector`, `AccessAuditLog`
