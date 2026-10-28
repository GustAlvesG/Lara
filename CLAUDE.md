Caso preciso, atualize este documento livremente incluindo mais regras, mas nunca removendo regras anteriores.

NUNCA APAGAR O BANCO DE DADOS COM REFRESHDATABASE OU QUALQUER OUTRAO COMANDO;

Todos os processos precisam ser documentados. Toda atualização precisa também atualizar a documentação.

Ao criar uma nova função que tenha permissão, a mesma deve ser incluída no banco de forma a poder ser vinculada a setores no sistema. A inclusão deve ser feita por meio do script de deploy sem que desconfigure permissões já existentes.

Como fazer: declare a permissão no catálogo `App\Authorization\Permissions` (a constante e a linha em `CATALOG`, com grupo e rótulo). O seed padrão `PermissionCatalogSeeder`, que `deploy_prod.sh` e `deploy_hml.sh` rodam depois das migrations, cria no banco o que ainda não existe. A permissão nasce sem setor: não atrele a nenhum setor no código; os setores pertinentes são configurados na tela de Setores. Não crie migration só para incluir permissão.

Sempre atualizar o que for pertinente. Uma mudança só está pronta quando tudo o que depende dela acompanha: documentação em `docs/`, guia do usuário, testes, catálogo de permissões e seed padrão, scripts de deploy, `.env.example` e configuração, e o cache de rotas quando uma rota muda.

Os módulos Freelancers, Tercerizados, Home Assistant, Reservas se comunicam com aplicações externas via API. Sempre que qualquer alteração for feita, gere um prompt para orientar outro agent Claude Code das alterações necessárias na outra aplicação.

Os prompts gerados devem ser salvos em docs/prompts e referenciados para qual sistema externo é: Lara Externos, NextJS, IA

O módulo Assinaturas também se comunica com aplicação externa desde 21/10/2026: a assinatura remota por link usa o site externo em Next.js (o mesmo das Reservas) pela API `/api/assinatura/remota/*`. A mesma regra vale: mudou essa API ou o fluxo de quem assina, atualize `docs/funcionalidades/assinatura-remota-prompt-site.md` e gere o prompt para o agent do site.

O módulo Ingressos (desde 27/10/2026) também se comunica com o site externo em Next.js (o mesmo das Reservas e dos Torneios) pela API `/api/tickets/*`: o Lara é o backend, a venda é no site. Mudou essa API ou o fluxo de compra, atualize `docs/funcionalidades/ingressos-site-prompt.md` e gere o prompt para o agent do site. O check-in dos ingressos pelo Monitor de Acesso mexe na API da portaria (Terceirizados): gere também o prompt para o agent do app da guarita.

O módulo Lara (IA) também se comunica com aplicação externa nos dois sentidos: O Lara chama a VM da IA (`/perguntar`, `/reiniciar`, `/health`) e a IA consulta O Lara pelas APIs `/api/information/search`, `/api/avisos/search` e `/api/guias/search` (desde 28/10/2026). O `/perguntar` leva o contexto da tela (`contexto`) desde 28/10/2026. Mudou alguma dessas chamadas ou o formato da resposta, atualize `docs/funcionalidades/lara-ia-consultas.md` e gere o prompt para o agent da VM da IA (os prompts da IA ficam em `docs/prompts/IA/`; modelo em `docs/prompts/IA/lara-ia-guias-prompt.md`). A busca de avisos segue a privacidade do aviso (`Aviso::scopeAddressedTo`): mudou a régua de privacidade, a busca muda junto.

Para cada módulo, crie um resumo do funcionamento dele em markdown para orientar outro agente claude code que vá fazer alguma nova implementação. Esse markdown de resumo deve ficar dentro docs/resumo-modulos e nomeado com o nome do módulo que aparece no Menu

Sempre se refira ao sistema como "O Lara", nunca "A Lara".

O "guia do usuário" de cada módulo mora em `docs/guias/<modulo>.md` (desde 28/10/2026), escrito para quem usa as telas, no formato de `docs/funcionalidades/guias-do-usuario.md`. É dele que a Lara (IA) tira as respostas de "como faço…": mudou uma tela, um botão, um fluxo ou uma regra que o funcionário percebe, atualize o guia do módulo na mesma mudança. Módulo novo no menu ganha guia novo. Renomeou um item do menu ou uma rota citada em `<!-- telas: -->`, ajuste o guia — o `tests/Feature/UserGuidesContentTest.php` acusa.

