<?php

namespace App\Authorization;

/**
 * Catálogo das permissões de acesso do painel.
 *
 * É a fonte única dos nomes: rota (`can:`), menu, view e a tela de setores
 * leem daqui. A tabela `permissions` do Spatie guarda as mesmas linhas só para
 * servir de chave estrangeira a `sector_permission` e `model_has_permissions`
 * — o que vale é esta lista, e a migration `sync_access_catalog` a espelha.
 *
 * Quem tem cada permissão é decidido no banco, não aqui: o setor recebe a
 * permissão na tela de Setores (todos os membros ou só os coordenadores) e o
 * usuário pode recebê-la individualmente na tela de Usuários. Os setores com
 * **acesso total** (Gerência, Diretoria e TI) têm todas elas — ver
 * AccessResolver.
 *
 * O que NÃO está aqui, de propósito: as regras de cargo. Aprovar lote
 * (coordenador da Gerência), validar contrato e assinar no kiosk (coordenador
 * do Comercial), o nível 1 da ordem de compra e reabrir mapa (coordenador da
 * Contabilidade), o nível 3 (Diretoria). Essas continuam sendo Gates e
 * métodos do User, e o acesso total não passa por cima delas — administrar o
 * sistema não faz de ninguém o coordenador que responde pelo lote.
 *
 * O Gate::before do AppServiceProvider só responde por nomes que estão neste
 * catálogo; qualquer outra ability (policy, Gate de cargo) segue o caminho
 * normal do Laravel. Por isso nenhum Gate de cargo pode ter um nome daqui —
 * o acesso total passaria por cima dele. tests/Unit/Authorization cobre isso.
 */
final class Permissions
{
    public const INFOCLUBE_EDITAR = 'infoclube.editar';

    public const SIV_BUSCA = 'siv.busca';
    public const SIV_PLACAS_DIRETORIA = 'siv.placas-diretoria';
    public const SIV_FROTA = 'siv.frota';
    public const SIV_VIAGENS = 'siv.viagens';
    public const SIV_VEICULOS = 'siv.veiculos';

    public const HOME_ASSISTANT = 'home-assistant';

    public const RESERVAS_AGENDAMENTOS = 'reservas.agendamentos';
    public const RESERVAS_PAGAMENTOS = 'reservas.pagamentos';
    public const RESERVAS_PAGAMENTOS_ESTORNAR = 'reservas.pagamentos.estornar';
    public const RESERVAS_CONFIGURAR = 'reservas.configurar';

    public const EXTERNOS_LIBERACAO_PONTUAL = 'externos.liberacao-pontual';
    public const EXTERNOS_HISTORICO = 'externos.historico';
    public const EXTERNOS_CARROS_APLICATIVO = 'externos.carros-aplicativo';

    public const LARA = 'lara';
    public const BOT_WHATSAPP = 'bot-whatsapp';
    public const COMPRAS = 'compras';
    public const CARTEIRINHAS = 'carteirinhas';

    public const FREELANCERS_CADASTRO = 'freelancers.cadastro';
    public const FREELANCERS_FUNCOES = 'freelancers.funcoes';
    public const FREELANCERS_SERVICOS_LISTAR = 'freelancers.servicos.listar';
    public const FREELANCERS_SERVICOS_GERENCIAR = 'freelancers.servicos.gerenciar';
    public const FREELANCERS_ASSINATURA = 'freelancers.assinatura';
    public const FREELANCERS_ACOMPANHAMENTO = 'freelancers.acompanhamento';
    public const FREELANCERS_FINANCEIRO = 'freelancers.financeiro';

    public const PLACAR_CADASTRO = 'placar.cadastro';
    public const PLACAR_SCOUT = 'placar.scout';

    public const BANCO_HORAS_ADMIN = 'banco-horas.admin';
    public const TELEGRAM_LOGIN = 'telegram.login';
    public const TORNEIOS = 'torneios';
    public const SOCIOS_CONSULTA = 'socios.consulta';

    public const USUARIOS_GERENCIAR = 'usuarios.gerenciar';
    public const SETORES_GERENCIAR = 'setores.gerenciar';

    /**
     * nome => [grupo, rótulo]. A ordem é a da tela: grupos na ordem do menu.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const CATALOG = [
        self::INFOCLUBE_EDITAR => ['InfoClube', 'Criar, editar e excluir informações'],

        self::SIV_BUSCA => ['SIV', 'Busca de placas'],
        self::SIV_PLACAS_DIRETORIA => ['SIV', 'Placas da diretoria'],
        self::SIV_FROTA => ['SIV', 'Frota (saída e retorno)'],
        self::SIV_VIAGENS => ['SIV', 'Viagens'],
        self::SIV_VEICULOS => ['SIV', 'Cadastro de veículos'],

        self::HOME_ASSISTANT => ['Home Assistant', 'Painel de iluminação e autoatendimento'],

        self::RESERVAS_AGENDAMENTOS => ['Reservas', 'Agendamentos (criar, ver e editar)'],
        self::RESERVAS_PAGAMENTOS => ['Reservas', 'Pagamentos'],
        self::RESERVAS_PAGAMENTOS_ESTORNAR => ['Reservas', 'Estornar pagamento'],
        self::RESERVAS_CONFIGURAR => ['Reservas', 'Locais, grupos e regras de reserva'],

        self::EXTERNOS_LIBERACAO_PONTUAL => ['Externos', 'Liberação pontual'],
        self::EXTERNOS_HISTORICO => ['Externos', 'Histórico de acessos'],
        self::EXTERNOS_CARROS_APLICATIVO => ['Externos', 'Carros de aplicativo e fila da portaria'],

        self::LARA => ['Lara (IA)', 'Chat com a Lara'],
        self::BOT_WHATSAPP => ['Bot WhatsApp', 'Editar os fluxos do bot'],
        self::COMPRAS => ['Compras', 'Mapas de cotação e ordens de compra'],
        self::CARTEIRINHAS => ['Carteirinhas', 'Emissão e modelos'],

        self::FREELANCERS_CADASTRO => ['Freelancers', 'Cadastro de freelancers'],
        self::FREELANCERS_FUNCOES => ['Freelancers', 'Funções'],
        self::FREELANCERS_SERVICOS_LISTAR => ['Freelancers', 'Lista de serviços (sem valores)'],
        self::FREELANCERS_SERVICOS_GERENCIAR => ['Freelancers', 'Serviços: valores, documentos, registro e lotes'],
        self::FREELANCERS_ASSINATURA => ['Freelancers', 'Assinatura no tablet (kiosk)'],
        self::FREELANCERS_ACOMPANHAMENTO => ['Freelancers', 'Acompanhamento dos lotes'],
        self::FREELANCERS_FINANCEIRO => ['Freelancers', 'Financeiro e baixa de pagamento'],

        self::PLACAR_CADASTRO => ['Placar Clube', 'Cadastro (equipes, times, jogadores, jogos)'],
        self::PLACAR_SCOUT => ['Placar Clube', 'Súmulas e scout'],

        self::BANCO_HORAS_ADMIN => ['Banco de Horas', 'Importar espelho e administrar funcionários'],

        self::TELEGRAM_LOGIN => ['Integrações', 'Login pela API do Telegram'],
        self::TORNEIOS => ['Outros', 'Torneios e categorias'],
        self::SOCIOS_CONSULTA => ['Outros', 'Consulta de sócios e acessos'],

        self::USUARIOS_GERENCIAR => ['Administração', 'Gerenciar usuários'],
        self::SETORES_GERENCIAR => ['Administração', 'Gerenciar setores e permissões'],
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::CATALOG);
    }

    public static function exists(string $name): bool
    {
        return isset(self::CATALOG[$name]);
    }

    public static function label(string $name): string
    {
        return self::CATALOG[$name][1] ?? $name;
    }

    public static function group(string $name): string
    {
        return self::CATALOG[$name][0] ?? 'Outros';
    }

    /**
     * Catálogo agrupado para as telas: grupo => [nome => rótulo].
     *
     * @return array<string, array<string, string>>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::CATALOG as $name => [$group, $label]) {
            $groups[$group][$name] = $label;
        }

        return $groups;
    }
}
