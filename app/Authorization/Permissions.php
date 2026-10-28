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
    // Baixa manual: marcar como pago o que foi recebido fora da plataforma
    // (dinheiro, Pix direto, maquininha da secretaria). Mexe em caixa, por
    // isso é separada de quem só agenda.
    public const RESERVAS_PAGAMENTOS_BAIXA_MANUAL = 'reservas.pagamentos.baixa-manual';
    public const RESERVAS_CONFIGURAR = 'reservas.configurar';
    // Espaços sociais (churrasqueiras, salões): o dia a dia (pré-reserva no
    // balcão, consulta, lista de convidados) e a decisão (contrato, aprovar
    // com desconto, recusar, cancelar com estorno), que costuma ser só da
    // secretaria/coordenação.
    public const RESERVAS_SOCIAIS = 'reservas.sociais';
    public const RESERVAS_SOCIAIS_APROVAR = 'reservas.sociais.aprovar';
    public const RESERVAS_CONTAS_SITE = 'reservas.contas-site';

    public const EXTERNOS_LIBERACAO_PONTUAL = 'externos.liberacao-pontual';
    public const EXTERNOS_HISTORICO = 'externos.historico';
    public const EXTERNOS_CARROS_APLICATIVO = 'externos.carros-aplicativo';

    public const LARA = 'lara';
    public const BOT_WHATSAPP = 'bot-whatsapp';
    // Só ver o diário de transferências e encerramentos do O Lara — para quem
    // acompanha o atendimento sem editar fluxo.
    public const BOT_WHATSAPP_OCORRENCIAS = 'bot-whatsapp.ocorrencias';
    public const COMPRAS = 'compras';
    public const CARTEIRINHAS = 'carteirinhas';

    public const FREELANCERS_CADASTRO = 'freelancers.cadastro';
    public const FREELANCERS_FUNCOES = 'freelancers.funcoes';
    public const FREELANCERS_SERVICOS_LISTAR = 'freelancers.servicos.listar';
    public const FREELANCERS_SERVICOS_GERENCIAR = 'freelancers.servicos.gerenciar';
    public const FREELANCERS_ASSINATURA = 'freelancers.assinatura';
    public const FREELANCERS_ACOMPANHAMENTO = 'freelancers.acompanhamento';
    public const FREELANCERS_FINANCEIRO = 'freelancers.financeiro';

    // Assinatura eletrônica presencial (tablet do balcão). Quatro acessos de
    // peso diferente: escrever o texto de um termo, atender no balcão,
    // consultar o que foi assinado e ver foto/traço de uma pessoa.
    public const ASSINATURA_MODELOS = 'assinatura.modelos';
    public const ASSINATURA_DOCUMENTOS = 'assinatura.documentos';
    public const ASSINATURA_CONSULTAR = 'assinatura.consultar';
    public const ASSINATURA_EVIDENCIAS = 'assinatura.evidencias';
    // Revisão interna do documento assinado: a comum (do dia seguinte em
    // diante, só documento que a pessoa não acompanhou) e a da coordenação
    // (antes do prazo e os próprios). A segunda é para dar, em Setores, só aos
    // coordenadores.
    public const ASSINATURA_REVISAR = 'assinatura.revisar';
    public const ASSINATURA_REVISAR_COORDENACAO = 'assinatura.revisar-coordenacao';
    // Termo de Menores (autoatendimento de sócio nos eventos): cadastrar o
    // termo de cada evento, parear o tablet e consultar o histórico — este
    // último é o de quem põe a pulseira na entrada.
    public const ASSINATURA_TERMO_MENORES_GERENCIAR = 'assinatura.termo-menores.gerenciar';
    public const ASSINATURA_TERMO_MENORES_PAREAR = 'assinatura.termo-menores.parear';
    public const ASSINATURA_TERMO_MENORES_HISTORICO = 'assinatura.termo-menores.historico';
    // Assinatura Diretoria: cadastrar as áreas da diretoria e quem responde
    // por cada uma (titular e substitutos). Escolher o diretor num documento
    // é parte de `assinatura.documentos`.
    public const ASSINATURA_DIRETORIA = 'assinatura.diretoria';
    // Contratos (27/10/2026): redigir contratos do próprio setor, biblioteca
    // de cláusulas e modelos. VALIDAR não é permissão: é de quem está no
    // setor Gerência (Gate `validar-contratos`).
    public const ASSINATURA_CONTRATOS = 'assinatura.contratos';

    public const PLACAR_CADASTRO = 'placar.cadastro';
    public const PLACAR_SCOUT = 'placar.scout';

    // Torneios (módulo novo, 22/10/2026): o Lara é o backend do site de
    // locação. Nascem sem setor; o Esporte recebe na tela de Setores.
    // `gerenciar` cobre o catálogo de categorias, o torneio e o chaveamento.
    public const TORNEIOS_GERENCIAR = 'torneios.gerenciar';
    public const TORNEIOS_INSCRICOES = 'torneios.inscricoes';
    // Lançar resultado no Lara; dá também o login da equipe no site.
    public const TORNEIOS_RESULTADOS = 'torneios.resultados';
    public const TORNEIOS_PAGAMENTOS_BAIXA_MANUAL = 'torneios.pagamentos.baixa-manual';
    public const TORNEIOS_PAGAMENTOS_ESTORNAR = 'torneios.pagamentos.estornar';
    public const TORNEIOS_FINANCEIRO = 'torneios.financeiro';

    // Ingressos (módulo novo, 27/10/2026): venda de lotes de ingresso de
    // eventos pelo site de locação, com o Lara de backend. Nascem sem setor.
    // `gerenciar` cobre evento, tipos e lotes; `vendas`, pedidos e balcão;
    // `checkin`, a entrada do evento (a portaria também confere pelo Monitor
    // de Acesso, que não pede permissão).
    public const INGRESSOS_GERENCIAR = 'ingressos.gerenciar';
    public const INGRESSOS_VENDAS = 'ingressos.vendas';
    public const INGRESSOS_CHECKIN = 'ingressos.checkin';
    public const INGRESSOS_CORTESIAS = 'ingressos.cortesias';
    // Cupons mexem no preço: separados de quem cadastra o evento.
    public const INGRESSOS_CUPONS = 'ingressos.cupons';
    public const INGRESSOS_PAGAMENTOS_BAIXA_MANUAL = 'ingressos.pagamentos.baixa-manual';
    public const INGRESSOS_PAGAMENTOS_ESTORNAR = 'ingressos.pagamentos.estornar';
    public const INGRESSOS_FINANCEIRO = 'ingressos.financeiro';
    public const INGRESSOS_CAIXA_TODOS = 'ingressos.caixa.todos';

    // Replay (vídeos das quadras): formato, layouts de logomarca, câmeras e o
    // repositório dos clipes. Marketing e TI configuram; TI já alcança pelo
    // acesso total, o Marketing recebe na tela de Setores.
    public const REPLAY = 'replay';

    // Cadastro de funcionários (RH): criar, editar, desligar, excluir, férias
    // e afastamentos e a importação por planilha. É o cadastro que autentica
    // quem pede carro de aplicativo e quem dirige a frota.
    public const FUNCIONARIOS_GERENCIAR = 'funcionarios.gerenciar';

    public const BANCO_HORAS_ADMIN = 'banco-horas.admin';
    public const TELEGRAM_LOGIN = 'telegram.login';
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
        self::RESERVAS_PAGAMENTOS_BAIXA_MANUAL => ['Reservas', 'Baixa manual de pagamento (pago fora da plataforma)'],
        self::RESERVAS_CONFIGURAR => ['Reservas', 'Locais, grupos e regras de reserva'],
        self::RESERVAS_SOCIAIS => ['Reservas', 'Espaços sociais: consultar, pré-reservar e lista de convidados'],
        self::RESERVAS_SOCIAIS_APROVAR => ['Reservas', 'Espaços sociais: contrato, aprovar, recusar e cancelar'],
        self::RESERVAS_CONTAS_SITE => ['Reservas', 'Contas do site: corrigir dados, senha provisória, cadastro manual e duplicadas'],

        self::EXTERNOS_LIBERACAO_PONTUAL => ['Externos', 'Liberação pontual'],
        self::EXTERNOS_HISTORICO => ['Externos', 'Histórico de acessos'],
        self::EXTERNOS_CARROS_APLICATIVO => ['Externos', 'Carros de aplicativo e fila da portaria'],

        self::LARA => ['Lara (IA)', 'Chat com a Lara'],
        self::BOT_WHATSAPP => ['Bot WhatsApp', 'Editar os fluxos do bot'],
        self::BOT_WHATSAPP_OCORRENCIAS => ['Bot WhatsApp', 'Ver transferências e encerramentos'],
        self::COMPRAS => ['Compras', 'Mapas de cotação e ordens de compra'],
        self::CARTEIRINHAS => ['Carteirinhas', 'Emissão e modelos'],

        self::FREELANCERS_CADASTRO => ['Freelancers', 'Cadastro de freelancers'],
        self::FREELANCERS_FUNCOES => ['Freelancers', 'Funções'],
        self::FREELANCERS_SERVICOS_LISTAR => ['Freelancers', 'Lista de serviços (sem valores)'],
        self::FREELANCERS_SERVICOS_GERENCIAR => ['Freelancers', 'Serviços: valores, documentos, registro e lotes'],
        self::FREELANCERS_ASSINATURA => ['Freelancers', 'Assinatura no tablet (kiosk)'],
        self::FREELANCERS_ACOMPANHAMENTO => ['Freelancers', 'Acompanhamento dos lotes'],
        self::FREELANCERS_FINANCEIRO => ['Freelancers', 'Financeiro e baixa de pagamento'],

        self::ASSINATURA_DOCUMENTOS => ['Assinaturas', 'Criar documentos e liberar para assinatura no tablet'],
        self::ASSINATURA_CONSULTAR => ['Assinaturas', 'Consultar documentos assinados e baixar o PDF'],
        self::ASSINATURA_MODELOS => ['Assinaturas', 'Criar e revisar os modelos de documento'],
        self::ASSINATURA_EVIDENCIAS => ['Assinaturas', 'Ver as evidências (foto do signatário e traço)'],
        self::ASSINATURA_REVISAR => ['Assinaturas', 'Revisar documentos assinados (do dia seguinte em diante, só os que não acompanhou)'],
        self::ASSINATURA_REVISAR_COORDENACAO => ['Assinaturas', 'Revisar antes do prazo e os próprios documentos (dê só aos coordenadores)'],
        self::ASSINATURA_TERMO_MENORES_GERENCIAR => ['Assinaturas', 'Termo de Menores: cadastrar o termo de cada evento'],
        self::ASSINATURA_TERMO_MENORES_PAREAR => ['Assinaturas', 'Termo de Menores: parear o tablet de autoatendimento'],
        self::ASSINATURA_TERMO_MENORES_HISTORICO => ['Assinaturas', 'Termo de Menores: consultar o histórico de autorizações'],
        self::ASSINATURA_DIRETORIA => ['Assinaturas', 'Diretoria: áreas da diretoria, titulares e substitutos que assinam documentos'],
        self::ASSINATURA_CONTRATOS => ['Assinaturas', 'Contratos: redigir os contratos do próprio setor, biblioteca de cláusulas e modelos (validar é da Gerência)'],

        self::PLACAR_CADASTRO => ['Placar Clube', 'Cadastro (equipes, times, jogadores, jogos)'],
        self::PLACAR_SCOUT => ['Placar Clube', 'Súmulas e scout'],

        self::TORNEIOS_GERENCIAR => ['Torneios', 'Catálogo de categorias, torneios e chaveamento'],
        self::TORNEIOS_INSCRICOES => ['Torneios', 'Inscrições e times (consultar, montar, remover, cancelar)'],
        self::TORNEIOS_RESULTADOS => ['Torneios', 'Lançar e corrigir resultados; gerar o código de lançamento pelo site'],
        self::TORNEIOS_PAGAMENTOS_BAIXA_MANUAL => ['Torneios', 'Baixa manual de inscrição (pago fora da eRede)'],
        self::TORNEIOS_PAGAMENTOS_ESTORNAR => ['Torneios', 'Estornar pagamento de inscrição'],
        self::TORNEIOS_FINANCEIRO => ['Torneios', 'Relatório financeiro dos torneios'],

        self::INGRESSOS_GERENCIAR => ['Ingressos', 'Eventos, tipos de ingresso e lotes'],
        self::INGRESSOS_VENDAS => ['Ingressos', 'Pedidos e ingressos (consultar, vender no balcão, trocar titular, cancelar)'],
        self::INGRESSOS_CHECKIN => ['Ingressos', 'Check-in na entrada do evento'],
        self::INGRESSOS_CORTESIAS => ['Ingressos', 'Emitir cortesia (ingresso sem cobrança)'],
        self::INGRESSOS_CUPONS => ['Ingressos', 'Cupons de desconto (criar, editar, desativar)'],
        self::INGRESSOS_PAGAMENTOS_BAIXA_MANUAL => ['Ingressos', 'Baixa manual de pedido (pago fora da eRede)'],
        self::INGRESSOS_PAGAMENTOS_ESTORNAR => ['Ingressos', 'Estornar pagamento de ingresso'],
        self::INGRESSOS_FINANCEIRO => ['Ingressos', 'Relatório financeiro dos ingressos'],
        self::INGRESSOS_CAIXA_TODOS => ['Ingressos', 'Relatório de caixa de todos os vendedores (fechamento do financeiro)'],

        self::REPLAY => ['RePET', 'Câmeras, layouts de logomarca e vídeos das quadras'],

        self::FUNCIONARIOS_GERENCIAR => ['Funcionários', 'Cadastro de funcionários, férias, afastamentos e importação por planilha'],

        self::BANCO_HORAS_ADMIN => ['Banco de Horas', 'Importar espelho de ponto'],

        self::TELEGRAM_LOGIN => ['Integrações', 'Login pela API do Telegram'],
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
