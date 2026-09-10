<?php

/**
 * DE-PARA DO ACESSO — papéis do Spatie  ->  setores.
 *
 * Gerado por `php artisan acesso:inventario` em 04/09/2026 15:48 contra o banco `lara_hml`.
 *
 * O lado esquerdo é o que existia no banco naquele momento. O lado direito
 * é o que VOCÊ decide, e é o que a migração da fase 3 vai executar:
 *
 *   setor          nome do setor de destino. Se já existir com esse nome exato,
 *                  é reaproveitado; senão é criado. `null` descarta o papel —
 *                  quem só tinha ele fica sem acesso nenhum.
 *   permissoes     "copiar"  o setor nasce com as permissões que o papel tinha
 *                            (ninguém perde acesso no deploy; você poda depois
 *                            na tela, com calma).
 *                  "nenhuma" o setor nasce vazio e você monta a matriz do zero.
 *   coordenadores  e-mails de quem entra como COORDENADOR. Todo o resto do papel
 *                  entra como colaborador.
 *
 * Regravar este arquivo com --force apaga o que você preencheu.
 */

// ---------------------------------------------------------------------------
// SETORES QUE JÁ EXISTEM (use o nome exato para reaproveitar):
//   · Almoxarifado — 0 membro(s), SEM COORDENADOR
//   · Atendimento — 9 membro(s), coordenado por Denise Tomaz
//   · comercial — 2 membro(s), coordenado por Gustavo Alves, Paloma Botelho
//   · Contabilidade — 1 membro(s), coordenado por Gustavo Alves
//   · Diretoria — 1 membro(s), coordenado por Gustavo Alves
//   · Esporte — 1 membro(s), coordenado por Gustavo Alves
//   · Finanças — 0 membro(s), SEM COORDENADOR
//   · Gerência — 1 membro(s), coordenado por Gustavo Alves
//   · Manutenção — 0 membro(s), SEM COORDENADOR
//   · RH — 0 membro(s), SEM COORDENADOR
//   · TI — 3 membro(s), coordenado por Gustavo Alves

// ---------------------------------------------------------------------------

return [

    /*
    |--------------------------------------------------------------------------
    | Papéis  ->  setores
    |--------------------------------------------------------------------------
    */

    'papeis' => [

        // ------------------------------ admin -------------------------------
        // 5 usuário(s), 5 ativo(s) · 31 permissão(ões)
        //
        // Este papel NÃO vira setor: continua existindo depois da reforma.
        // Deixado aqui só para o inventário ficar completo.
        // permissões: authorize purchase orders, cotacao.criar, cotacao.definir_vencedor,
        //   cotacao.editar_precos, cotacao.exportar, cotacao.reabrir, cotacao.visualizar,
        //   create information, create reservations, delete information, delete reservations,
        //   edit information, edit reservations, import comp time, manage avisos,
        //   manage fleet, manage freelancers, manage home assistant, manage id cards,
        //   manage payments, manage permissions, manage reservations-configs, manage roles,
        //   manage smart panel, manage users, publish information, search parking,
        //   use lara chat, view information, view payments, view reservations
        // usuários: atendimento@clubedosfuncionarios.com.br,
        //   dep.informatica@clubedosfuncionarios.com.br, josue.cobucci@gmail.com,
        //   palomab.silva@hotmail.com, tiagoboldori@gmail.com
        'admin' => [
            'setor'         => null, // papel mantido — não migrar
            'permissoes'    => 'nenhuma',
            'coordenadores' => [],
        ],

        // ---------------------------- comercial -----------------------------
        // 5 usuário(s), 5 ativo(s) · 7 permissão(ões)
        // permissões: create reservations, delete reservations, edit reservations,
        //   manage freelancers, view information, view payments, view reservations
        // usuários: caixa@cfcsn.com, kamilapereira9419@gmail.com, marluci@a.com,
        //   teixeira.rafaelasantos@gmail.com, siqueirarogerio@gmail.com
        'comercial' => [
            'setor'         => null,     // <<< PREENCHA: nome do setor de destino
            'permissoes'    => 'copiar', // 'copiar' | 'nenhuma'
            'coordenadores' => [
                // 'fulano@clube.com',
            ],
        ],

        // ---------------------------- secretaria ----------------------------
        // 18 usuário(s), 18 ativo(s) · 16 permissão(ões)
        // permissões: create information, create reservations, delete information,
        //   delete reservations, edit information, edit reservations, manage avisos,
        //   manage freelancers, manage id cards, manage payments, manage reservations-configs,
        //   publish information, search parking, view information, view payments,
        //   view reservations
        // usuários: recepcao03@clubedosfuncionarios.com.br,
        //   esporte@clubedosfuncionarios.com.br, dep.esporte@clubedosfuncionarios.com.br,
        //   recepcao04@clubedosfuncionarios.com.br, srporto.erick@gmail.com,
        //   erickporto@id.uff.br, coord.seguranca@clubedosfuncionarios.com.br,
        //   recepcao07@clubedosfuncionarios.com.br, admin@admin,
        //   informatica@clubedosfuncionarios.com.br, recepcao01@clubedosfuncionarios.com.br,
        //   recepcao05@clubedosfuncionarios.com.br, dep.pessoal@clubedosfuncionarios.com.br,
        //   recepcao06@clubedosfuncionarios.com.br, recepcao02@clubedosfuncionarios.com.br,
        //   comunicacao.cfcsn@gmail.com, social@clubedosfuncionario.com.br,
        //   recepcao08@clubedosfuncionarios.com.br
        'secretaria' => [
            'setor'         => "Atendimento",     // <<< PREENCHA: nome do setor de destino
            'permissoes'    => 'copiar', // 'copiar' | 'nenhuma'
            'coordenadores' => [
                // 'fulano@clube.com',
            ],
        ],

        // ------------------------------- user -------------------------------
        // 2 usuário(s), 2 ativo(s) · 2 permissão(ões)
        //
        // Este papel NÃO vira setor: continua existindo depois da reforma.
        // Deixado aqui só para o inventário ficar completo.
        // permissões: manage freelancers, view information
        // usuários: social@clubedosfuncionarios.com.br, nataliarodrigues1821q@gmail.com
        'user' => [
            'setor'         => null, // papel mantido — não migrar
            'permissoes'    => 'nenhuma',
            'coordenadores' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Exceções pessoa a pessoa
    |--------------------------------------------------------------------------
    |
    | Aplicadas DEPOIS do mapa dos papéis, e somam-se a ele. Use para quem não
    | se resolve pelo papel: quem está em dois setores, quem coordena um setor
    | e colabora em outro, e os usuários sem papel nenhum listados abaixo.
    |
    |     'fulano@clube.com' => [
    |         ['setor' => 'Comercial',      'papel' => 'coordinator'],
    |         ['setor' => 'Contabilidade',  'papel' => 'collaborator'],
    |     ],
    */

    'pessoas' => [

        // Nada pendente: todo usuário ativo tinha exatamente um papel.

    ],

];
