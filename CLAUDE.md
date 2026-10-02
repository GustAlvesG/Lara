NUNCA APAGAR O BANCO DE DADOS COM REFRESHDATABASE OU QUALQUER OUTRAO COMANDO;

Todos os processos precisam ser documentados. Toda atualização precisa também atualizar a documentação.

Ao criar uma nova função que tenha permissão, a mesma deve ser incluída no banco de forma a poder ser vinculada a setores no sistema. A inclusão deve ser feita por meio do script de deploy sem que desconfigure permissões já existentes.

Como fazer: declare a permissão no catálogo `App\Authorization\Permissions` (a constante e a linha em `CATALOG`, com grupo e rótulo). O seed padrão `PermissionCatalogSeeder`, que `deploy_prod.sh` e `deploy_hml.sh` rodam depois das migrations, cria no banco o que ainda não existe. A permissão nasce sem setor: não atrele a nenhum setor no código; os setores pertinentes são configurados na tela de Setores. Não crie migration só para incluir permissão.

Sempre atualizar o que for pertinente. Uma mudança só está pronta quando tudo o que depende dela acompanha: documentação em `docs/`, guia do usuário, testes, catálogo de permissões e seed padrão, scripts de deploy, `.env.example` e configuração, e o cache de rotas quando uma rota muda.