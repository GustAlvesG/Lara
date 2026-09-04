<?php

/*
|--------------------------------------------------------------------------
| Questor — autorização de Ordem de Compra
|--------------------------------------------------------------------------
|
| O Questor (banco FUNCSIDERURG, SQL Server) é o ERP de compras. A tela nativa
| de autorização não será usada: quem coleta as aprovações é a Lara, que ao
| final carimba a ordem no Questor em nome de um usuário técnico.
|
| Duas travas comandam o que sai daqui: `dry_run` (ligado = nada é gravado, só
| a prévia do que aconteceria) e `reprovacao_liberada`, separada porque a
| reprovação não tem a mesma evidência que a aprovação. Ver
| docs/funcionalidades/questor-autorizacao-compra.md.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Chave geral
    |--------------------------------------------------------------------------
    |
    | Desligado, o módulo inteiro fica inerte: as telas respondem que a
    | integração não está configurada e nenhuma consulta é feita ao Questor.
    |
    */

    'enabled' => (bool) env('QUESTOR_ENABLED', false),

    'connection' => env('QUESTOR_CONNECTION', 'questor_sqlsrv'),

    /*
    | Schema das tabelas. Fica separado do nome do banco porque as consultas
    | qualificam as tabelas (`FUNCSIDERURG.dbo.TBL_...`) — o mesmo login pode
    | ter outro banco como default.
    */

    'database' => env('DB_QUESTOR_DATABASE', 'FUNCSIDERURG'),
    'schema' => env('QUESTOR_SCHEMA', 'dbo'),

    /*
    |--------------------------------------------------------------------------
    | Simulação (dry run)
    |--------------------------------------------------------------------------
    |
    | Ligado, NENHUM UPDATE é executado no Questor: o serviço de escrita monta a
    | instrução, lê o estado atual da ordem e devolve o "antes/depois" para a
    | tela. É o padrão.
    |
    | Desligado, a APROVAÇÃO passa a gravar de verdade no ERP de produção. A
    | reprovação não vem junto: ela tem trava própria logo abaixo.
    |
    */

    'dry_run' => (bool) env('QUESTOR_DRY_RUN', true),

    /*
    |--------------------------------------------------------------------------
    | Reprovação
    |--------------------------------------------------------------------------
    |
    | Trava separada porque as duas ações não têm a mesma evidência.
    |
    | A aprovação foi observada ao vivo (ordem 40.975): só `CD_USUARIO_AUTORIZOU`
    | e `DT_AUTORIZACAO` mudam. A reprovação, não — dela só existe o padrão de 28
    | casos históricos, e três perguntas seguem sem resposta observada:
    | `CD_STATUS_ANTERIOR` é mesmo preenchido? `DT_ATUALIZACAO` muda neste caso?
    | Algum outro campo é tocado?
    |
    | Reprove uma ordem de teste pela tela nativa do Questor, compare a linha
    | campo a campo (seção 6.2 da especificação), ajuste o UPDATE se necessário e
    | só então ligue isto. Enquanto estiver desligado, a reprovação continua
    | funcionando em simulação.
    |
    */

    'reprovacao_liberada' => (bool) env('QUESTOR_REPROVACAO_LIBERADA', false),

    /*
    |--------------------------------------------------------------------------
    | Usuário técnico
    |--------------------------------------------------------------------------
    |
    | `CD_CODUSUARIO` do usuário dedicado à Lara no Questor. É ele que vai
    | aparecer em CD_USUARIO_AUTORIZOU/CD_USUARIO_REPROVOU — o Questor não tem
    | onde registrar vários aprovadores, então quem de fato aprovou fica no
    | histórico da Lara.
    |
    | Crie o usuário pela tela de administração do Questor (não por INSERT
    | direto) com X_ATIVO, X_AUTORIZA_ORDEM_COMPRA e X_REPROVA_ORDEM_COMPRA
    | iguais a 1, e ponha aqui o código dele.
    |
    | `filled()` e não `!== null`: a chave existe vazia no .env, e sem isso o
    | "não configurado" viraria o usuário 0 — que o Questor aceitaria como um
    | código qualquer na hora de gravar.
    |
    */

    'usuario_tecnico' => filled(env('QUESTOR_USUARIO_TECNICO'))
        ? (int) env('QUESTOR_USUARIO_TECNICO')
        : null,

    /*
    |--------------------------------------------------------------------------
    | Escopo da listagem
    |--------------------------------------------------------------------------
    |
    | `filiais` restringe quais filiais entram na fila (lista de CD_FILIAL
    | separada por vírgula no .env). Vazio = todas.
    |
    | `desde` é a política para as ~2.292 ordens que já estão pendentes há anos:
    | uma data (Y-m-d) faz a fila começar dali, evitando que a tela abra com o
    | passivo histórico inteiro. Vazio = tudo.
    |
    */

    'filiais' => array_values(array_filter(
        array_map('intval', array_filter(explode(',', (string) env('QUESTOR_FILIAIS', ''))))
    )),

    'desde' => filled(env('QUESTOR_ORDENS_DESDE')) ? env('QUESTOR_ORDENS_DESDE') : null,

    /*
    | Teto da listagem. A tela é uma fila de trabalho, não um relatório: se
    | passar disso, o filtro é que precisa melhorar.
    */

    'limite_listagem' => (int) env('QUESTOR_LIMITE_LISTAGEM', 10),

    /*
    |--------------------------------------------------------------------------
    | Códigos do Questor
    |--------------------------------------------------------------------------
    |
    | Confirmados em produção (TBL_STATUS). Autorizar NÃO muda o status: a ordem
    | continua PENDENTE e ganha só o carimbo (usuário + data). Reprovar, sim,
    | move para REPROVADO.
    |
    */

    'status' => [
        'pendente' => 1,
        'faturado' => 2,
        'parcial' => 3,
        'cancelado' => 4,
        'reprovado' => 5,
        'mesclado' => 6,
        'importado' => 99,
    ],

    /*
    | DS_MOTIVO_REPROVADO é varchar(100) no Questor. O motivo é truncado neste
    | tamanho antes de ir para o banco — o texto completo fica na Lara.
    */

    'motivo_max' => 100,

    /*
    |--------------------------------------------------------------------------
    | Fluxo de aprovação
    |--------------------------------------------------------------------------
    |
    | `quorum_diretoria` decide o último nível quando o gerente escolhe mais de
    | um diretor:
    |
    |   'todos'    — todos os escolhidos precisam aprovar (padrão). O gerente
    |                escolhe a dedo quem decide aquela ordem, então escolher
    |                três pessoas se lê como "estas três precisam aprovar".
    |   'qualquer' — o primeiro que aprovar fecha o nível. Mais rápido, e o
    |                caminho quando a operação estiver esperando o diretor mais
    |                ocupado.
    |
    | Vale só para a aprovação: qualquer reprovação encerra o processo nos dois
    | modos.
    |
    */

    'aprovacao' => [
        'quorum_diretoria' => env('QUESTOR_QUORUM_DIRETORIA', 'todos'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mapa de cotação
    |--------------------------------------------------------------------------
    |
    | O módulo de mapa de cotação lê a Solicitação de Compra e o histórico de
    | compras do Questor; tudo o que o comprador digita fica na Lara. Nada aqui
    | habilita escrita no ERP — o mapa é 100% leitura do lado do Questor.
    |
    */

    'cotacao' => [

        /*
        | CD_STATUS que marca uma NF de entrada CANCELADA. NÃO TEM FK no
        | Questor (TBL_COMPRAS_NOTAFISCAL_ENTRADA.CD_STATUS é DEFAULT 2 e
        | ninguém garante que exista em TBL_STATUS), então não dá para deduzir:
        | rode `php artisan cotacao:descobrir-config` e ponha o número no .env.
        |
        | Nulo = nenhuma nota é excluída por status. É o padrão deliberado:
        | chutar um código faria o histórico esconder compras boas em silêncio,
        | que é pior do que incluir uma nota cancelada de vez em quando.
        */

        'status_nf_entrada_cancelada' => filled(env('QUESTOR_STATUS_NF_CANCELADA'))
            ? (int) env('QUESTOR_STATUS_NF_CANCELADA')
            : null,

        /*
        | Restringir o histórico à filial da solicitação? Desligado (padrão), a
        | última compra vem de qualquer filial da mesma empresa — o preço de uma
        | tinta não muda por ter entrado noutra filial, e limitar demais faz o
        | mapa nascer sem referência nenhuma.
        */

        'somente_mesma_filial' => (bool) env('QUESTOR_COTACAO_MESMA_FILIAL', false),

        /*
        | Janela do drill-down de histórico do item (query 4), em meses.
        */

        'meses_historico' => (int) env('QUESTOR_COTACAO_MESES_HISTORICO', 24),

        /*
        | Quantas entradas o drill-down lista por item.
        */

        'limite_historico' => (int) env('QUESTOR_COTACAO_LIMITE_HISTORICO', 20),

        /*
        | TTL do cache dos fornecedores históricos, em segundos. O histórico de
        | compras não muda durante uma cotação; reconsultá-lo a cada abertura de
        | modal só castiga o ERP. Zero desliga o cache.
        */

        'cache_ttl' => (int) env('QUESTOR_COTACAO_CACHE_TTL', 900),

        /*
        | Teto de colunas de fornecedor por mapa. O XLSX modelo vai de E até N;
        | acima disso a grade deixa de caber na tela e no papel.
        */

        'max_fornecedores' => (int) env('QUESTOR_COTACAO_MAX_FORNECEDORES', 10),
    ],

];
