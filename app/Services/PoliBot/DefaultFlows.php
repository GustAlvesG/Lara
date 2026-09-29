<?php

namespace App\Services\PoliBot;

/**
 * Fluxos iniciais do bot, equivalentes ao que o bot da Poli faz hoje.
 *
 * Ponto de partida, não verdade final: depois de instalados
 * (`php artisan poli:bot-fluxos --instalar`) vivem em bot_flows e é lá que se
 * editam. Os uuids de template e de time são os da conta real, lidos da API
 * em 25/09/2026 (`poli:teste-envio --templates` e PoliClient::times()).
 */
class DefaultFlows
{
    // Templates LIST cadastrados no painel da Poli.
    public const TPL_DEPARTAMENTOS = 'a22783de-dcc6-4ff3-8f6e-70f264adefc3';
    public const TPL_LOCAL_UBER = 'a27eaf22-2607-4737-97e0-9b485051d86a';
    public const TPL_FORA_DO_HORARIO = 'a2500d8d-ca34-447e-86fe-f2f4b6f2460b';   // "Opções"

    // Times (departamentos) da conta.
    public const TEAM_ACHADOS = 'a22789b0-2dd4-484a-b622-e57498d533d3';
    public const TEAM_FINANCEIRO = 'aa628d96-430e-11f1-9d75-06799772b1cd';
    public const TEAM_SECRETARIA = 'aa4227ee-430e-11f1-9d75-06799772b1cd';

    /**
     * @return array<string, array{name: string, definition: array<string, mixed>}>
     */
    public static function all(): array
    {
        return [
            'atendimento' => ['name' => 'Atendimento inicial', 'definition' => self::atendimento()],
            'carro-de-aplicativo' => ['name' => 'Carro de aplicativo', 'definition' => self::carroDeAplicativo()],
        ];
    }

    private static function atendimento(): array
    {
        $encaminhar = fn (string $setor, string $time) => [
            'say' => ['type' => 'text', 'text' => "Certo! Vou te encaminhar para *{$setor}*. Em instantes um atendente continua com você."],
            'action' => ['type' => 'handoff', 'team_uuid' => $time],
        ];

        return [
            'start' => 'menu',
            'triggers' => ['any' => true],
            'timeout_minutes' => 15,
            'max_attempts' => 3,
            'on_max_attempts' => ['type' => 'handoff', 'team_uuid' => self::TEAM_SECRETARIA],

            // O horário que os templates da Poli anunciam. Os dois discordam no
            // fim de semana (HorarioAtendimento diz 07:10, Opções diz 07:00):
            // vale o de Opções, que é a mensagem que o bot manda quando fecha.
            'hours' => [
                'enabled' => true,
                'days' => [
                    '1' => ['07:00', '19:50'], '2' => ['07:00', '19:50'], '3' => ['07:00', '19:50'],
                    '4' => ['07:00', '19:50'], '5' => ['07:00', '19:50'],
                    '6' => ['07:00', '18:00'], '7' => ['07:00', '18:00'],
                ],
                'holidays' => [],
                'holiday' => ['07:00', '18:00'],
                'out_of_hours' => 'fora_do_horario',
            ],

            'steps' => [
                'menu' => [
                    'say' => ['type' => 'template', 'template_uuid' => self::TPL_DEPARTAMENTOS],
                    'expect' => ['type' => 'option'],
                    'options' => [
                        ['label' => 'Achados e Perdidos', 'description' => 'Materiais e equipamentos que foram esquecidos no Clube.', 'next' => 'achados'],
                        ['label' => 'Financeiro', 'description' => 'Consulte seus débitos ou outras pendências.', 'next' => 'financeiro'],
                        ['label' => 'Secretaria', 'description' => 'Atendimento pra sócios e não sócios para assuntos do Clube.', 'next' => 'secretaria'],
                        ['label' => 'Carro de Aplicativo', 'description' => 'Carro, moto ou táxi', 'aliases' => ['uber', '99', 'taxi', 'carro'], 'next' => 'uber'],
                        ['label' => 'Funcionalidade Teste', 'description' => '<em desenvolvimento>', 'aliases' => ['funcionalidade em teste'], 'next' => 'teste'],
                    ],
                    'save_as' => 'departamento',
                    'invalid' => 'Não entendi. Toque em *Ver opções* e escolha o departamento.',
                ],
                'fora_do_horario' => [
                    'say' => ['type' => 'template', 'template_uuid' => self::TPL_FORA_DO_HORARIO],
                    'expect' => ['type' => 'option'],
                    'options' => [
                        ['label' => 'Carro de Aplicativo', 'description' => 'Carro, moto ou táxi', 'aliases' => ['uber', '99', 'taxi', 'carro'], 'next' => 'uber'],
                        ['label' => 'Sair', 'description' => 'Encerrar atendimento.', 'next' => 'sair'],
                        ['label' => 'Funcionalidade Teste', 'description' => '<em desenvolvimento>', 'aliases' => ['funcionalidade em teste'], 'next' => 'teste'],
                    ],
                    'invalid' => 'Fora do horário eu só consigo ajudar com as opções do menu. Toque em *Opções* e escolha uma.',
                ],
                'sair' => [
                    'say' => ['type' => 'text', 'text' => 'Atendimento encerrado. Sempre que precisar, é só chamar!'],
                    'action' => ['type' => 'close'],
                ],
                'achados' => $encaminhar('Achados e Perdidos', self::TEAM_ACHADOS),
                'financeiro' => $encaminhar('Financeiro', self::TEAM_FINANCEIRO),
                'secretaria' => $encaminhar('Secretaria', self::TEAM_SECRETARIA),
                'uber' => ['action' => ['type' => 'goto_flow', 'flow' => 'carro-de-aplicativo']],
                // Porta do piloto: no bot da Poli, esta opção transfere para O
                // Lara, e o toque chega aqui como gatilho. Daqui em diante é o
                // menu da Lara com todos os fluxos, de verdade.
                'teste' => [
                    'say' => ['type' => 'text', 'text' => '🧪 Modo de teste: você está falando com o novo atendimento. Todas as opções funcionam de verdade.'],
                    'next' => 'menu',
                ],
            ],
        ];
    }

    private static function carroDeAplicativo(): array
    {
        return [
            'start' => 'matricula',
            'triggers' => ['only_goto' => true],   // só se chega aqui pelo menu
            'timeout_minutes' => 10,
            'max_attempts' => 3,
            'on_max_attempts' => ['type' => 'handoff', 'team_uuid' => self::TEAM_SECRETARIA],
            'steps' => [
                'matricula' => [
                    'say' => ['type' => 'text', 'text' => "Vamos liberar a entrada do seu carro de aplicativo. 🚗\n\nInforme sua *matrícula* de sócio (ou seu *CPF*, se for funcionário)."],
                    'expect' => ['type' => 'text', 'min' => 4, 'max' => 20, 'pattern' => '^[0-9A-Za-z./ -]+$'],
                    'save_as' => 'matricula',
                    'invalid' => 'Não reconheci. Envie só a matrícula (ou o CPF, se for funcionário), sem outras palavras.',
                    'next' => 'nome',
                ],
                'nome' => [
                    'say' => ['type' => 'text', 'text' => 'Qual é o seu *nome*?'],
                    'expect' => ['type' => 'text', 'min' => 2, 'max' => 80, 'pattern' => "^[\\p{L} '.\\-]+$"],
                    'save_as' => 'nome',
                    'invalid' => 'Envie seu nome, só com letras, por favor.',
                    'next' => 'local',
                ],
                'local' => [
                    'say' => ['type' => 'template', 'template_uuid' => self::TPL_LOCAL_UBER],
                    'expect' => ['type' => 'option'],
                    'options' => [
                        ['label' => 'Passarela Gastrobar', 'aliases' => ['passarela', 'gastrobar']],
                        ['label' => 'Campo'],
                        ['label' => 'Ginásio', 'aliases' => ['ginasio']],
                    ],
                    'save_as' => 'local',
                    'invalid' => 'Escolha um dos locais: *Passarela*, *Campo* ou *Ginásio*.',
                    'next' => 'placa',
                ],
                'placa' => [
                    'say' => ['type' => 'text', 'text' => 'Qual é a *placa* do carro? (ex.: ABC1D23)'],
                    'expect' => ['type' => 'plate'],
                    'save_as' => 'placa',
                    'invalid' => 'Essa placa não parece válida. Envie no formato ABC1D23 ou ABC-1234.',
                    'next' => 'print',
                ],
                'print' => [
                    'say' => ['type' => 'text', 'text' => 'Agora envie o *print* da corrida no aplicativo, mostrando o carro e a placa.'],
                    'expect' => ['type' => 'image'],
                    'save_as' => 'print',
                    'invalid' => 'Preciso do print da corrida (uma imagem). Envie a captura de tela, por favor.',
                    'next' => 'registrar',
                ],
                'registrar' => [
                    'action' => ['type' => 'uber_request'],
                    'next' => 'fim',
                ],
                // Sem close: o fim do fluxo deixa a conversa em encerramento
                // diferido. Fechar na hora prenderia a próxima mensagem do
                // sócio no atendimento fechado (medido em 29/09/2026).
                'fim' => [
                    'say' => ['type' => 'text', 'text' => "Pronto, {nome}! ✅ A entrada do carro placa *{placa}* está liberada por 30 minutos. Avisaremos quando ele chegar à portaria."],
                ],
            ],
        ];
    }
}
