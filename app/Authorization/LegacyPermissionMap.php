<?php

namespace App\Authorization;

use App\Authorization\Permissions as P;

/**
 * De-para das permissões e Gates de antes da reforma para o catálogo novo.
 *
 * Serve a duas coisas, e só a elas:
 *
 *   - a migration `sync_access_catalog` traduz as permissões **individuais**
 *     que já existiam (dadas direto ao usuário, fora de role) para os nomes
 *     novos — quem tinha `import comp time` nominal continua tendo o Banco de
 *     Horas;
 *   - o comando `acesso:diferenca` traduz o acesso antigo de cada pessoa para
 *     os nomes novos e compara com o que ela alcança agora.
 *
 * Não é usado para decidir acesso em lugar nenhum. Some junto com o
 * `acesso:limpar-legado`, quando não houver mais nada antigo para comparar.
 */
final class LegacyPermissionMap
{
    /** @var array<string, list<string>> permissão antiga => permissões novas */
    public const PERMISSIONS = [
        'create information' => [P::INFOCLUBE_EDITAR],
        'edit information' => [P::INFOCLUBE_EDITAR],
        'delete information' => [P::INFOCLUBE_EDITAR],
        'publish information' => [P::INFOCLUBE_EDITAR],
        // A consulta de placas abria as duas telas do SIV.
        'search parking' => [P::SIV_BUSCA, P::SIV_PLACAS_DIRETORIA],
        'manage fleet' => [P::SIV_FROTA, P::SIV_VIAGENS, P::SIV_VEICULOS],
        'manage home assistant' => [P::HOME_ASSISTANT],
        'view reservations' => [P::RESERVAS_AGENDAMENTOS],
        'create reservations' => [P::RESERVAS_AGENDAMENTOS],
        'edit reservations' => [P::RESERVAS_AGENDAMENTOS],
        'delete reservations' => [P::RESERVAS_AGENDAMENTOS],
        'manage reservations-configs' => [P::RESERVAS_CONFIGURAR],
        'view payments' => [P::RESERVAS_PAGAMENTOS],
        'manage payments' => [P::RESERVAS_PAGAMENTOS_ESTORNAR],
        'use lara chat' => [P::LARA],
        'manage whatsapp bot' => [P::BOT_WHATSAPP],
        'authorize purchase orders' => [P::COMPRAS],
        'manage id cards' => [P::CARTEIRINHAS],
        // Era uma porta só para o módulo inteiro. Traduzida para o mínimo que
        // a Secretaria (Atendimento) recebe: o resto do módulo é do Comercial.
        'manage freelancers' => [P::FREELANCERS_CADASTRO, P::FREELANCERS_SERVICOS_LISTAR],
        'import comp time' => [P::BANCO_HORAS_ADMIN],
        'manage users' => [P::USUARIOS_GERENCIAR, P::SETORES_GERENCIAR],
        'manage roles' => [P::USUARIOS_GERENCIAR, P::SETORES_GERENCIAR],
        'manage permissions' => [P::USUARIOS_GERENCIAR, P::SETORES_GERENCIAR],
        // Sem destino: InfoClube e Avisos ficaram públicos, Smart Panel saiu.
        'view information' => [],
        'manage avisos' => [],
        'manage smart panel' => [],
    ];

    /**
     * Gates de setor de antes da reforma, pela regra que tinham, traduzidos.
     * O setor é comparado sem diferenciar maiúsculas, como o User fazia.
     *
     * @var array<string, array{sectors: list<string>, coordinator: bool, grants: list<string>}>
     */
    public const SECTOR_GATES = [
        'manage-freelancer-payments' => ['sectors' => ['Contabilidade', 'Gerência'], 'coordinator' => false, 'grants' => [P::FREELANCERS_FINANCEIRO]],
        'track-freelancer-batches' => ['sectors' => ['Comercial', 'Contabilidade', 'Gerência'], 'coordinator' => false, 'grants' => [P::FREELANCERS_ACOMPANHAMENTO]],
        'acessar-cotacao' => ['sectors' => ['Contabilidade'], 'coordinator' => false, 'grants' => [P::COMPRAS]],
        'placar' => ['sectors' => ['Esporte'], 'coordinator' => false, 'grants' => [P::PLACAR_CADASTRO, P::PLACAR_SCOUT]],
        'manage-comp-time' => ['sectors' => ['RH'], 'coordinator' => false, 'grants' => [P::BANCO_HORAS_ADMIN]],
    ];

    /** A role que abria o login da API do Telegram. */
    public const TELEGRAM_ROLE = 'comercial';

    /**
     * Telas que antes só pediam login (ou só se escondiam no menu) e agora
     * exigem permissão. Todo mundo "tinha" essas; quem não tiver agora perde —
     * é a origem provável dos chamados de "liberar depois".
     */
    public const PREVIOUSLY_OPEN = [
        P::RESERVAS_AGENDAMENTOS,          // Novo Agendamento e a edição da reserva
        P::SIV_PLACAS_DIRETORIA,
        P::EXTERNOS_LIBERACAO_PONTUAL,
        P::EXTERNOS_HISTORICO,
        P::EXTERNOS_CARROS_APLICATIVO,
        P::RESERVAS_CONFIGURAR,            // locais, grupos e regras
        P::TORNEIOS,
        P::SOCIOS_CONSULTA,
    ];

    /**
     * @param iterable<string> $legacy
     * @return list<string>
     */
    public static function translate(iterable $legacy): array
    {
        $out = [];

        foreach ($legacy as $name) {
            foreach (self::PERMISSIONS[$name] ?? [] as $new) {
                $out[$new] = true;
            }
        }

        return array_keys($out);
    }
}
