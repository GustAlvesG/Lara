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
| Nesta PRIMEIRA VERSÃO nada é gravado. `dry_run` está ligado e o serviço de
| escrita apenas monta o SQL e devolve a prévia do que aconteceria — é o modo
| de conferir a integração contra o banco real sem risco. Ver
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
    | tela. É o padrão, e é o que esta versão entrega.
    |
    | Desligar isto é o passo que passa a mexer no ERP de produção. Antes disso,
    | é preciso: (1) criar o usuário técnico no Questor e preencher
    | QUESTOR_USUARIO_TECNICO, (2) rodar o teste ao vivo de reprovação descrito
    | na seção 6.2 da especificação, e (3) conferir a permissão de UPDATE do
    | login do banco.
    |
    */

    'dry_run' => (bool) env('QUESTOR_DRY_RUN', true),

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

    'limite_listagem' => (int) env('QUESTOR_LIMITE_LISTAGEM', 200),

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

];
