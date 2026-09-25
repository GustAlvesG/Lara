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
            'steps' => [
                'menu' => [
                    'say' => ['type' => 'template', 'template_uuid' => self::TPL_DEPARTAMENTOS],
                    'expect' => ['type' => 'option'],
                    'options' => [
                        ['label' => 'Achados e Perdidos', 'next' => 'achados'],
                        ['label' => 'Financeiro', 'next' => 'financeiro'],
                        ['label' => 'Secretaria', 'next' => 'secretaria'],
                        ['label' => 'Carro de Aplicativo', 'aliases' => ['uber', '99', 'taxi', 'carro'], 'next' => 'uber'],
                        ['label' => 'Funcionalidade Teste', 'next' => 'teste'],
                    ],
                    'save_as' => 'departamento',
                    'invalid' => 'Não entendi. Toque em *Ver opções* e escolha o departamento.',
                ],
                'achados' => $encaminhar('Achados e Perdidos', self::TEAM_ACHADOS),
                'financeiro' => $encaminhar('Financeiro', self::TEAM_FINANCEIRO),
                'secretaria' => $encaminhar('Secretaria', self::TEAM_SECRETARIA),
                'uber' => ['action' => ['type' => 'goto_flow', 'flow' => 'carro-de-aplicativo']],
                'teste' => [
                    'say' => ['type' => 'text', 'text' => 'Esta opção ainda está em desenvolvimento. 🙂'],
                    'next' => 'menu',
                ],
            ],
        ];
    }

    private static function carroDeAplicativo(): array
    {
        return [
            'start' => 'matricula',
            'triggers' => ['texts' => []],   // só se chega aqui pelo menu
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
                'fim' => [
                    'say' => ['type' => 'text', 'text' => "Pronto, {nome}! ✅ A entrada do carro placa *{placa}* está liberada por 30 minutos. Avisaremos quando ele chegar à portaria."],
                    'action' => ['type' => 'close'],
                ],
            ],
        ];
    }
}
