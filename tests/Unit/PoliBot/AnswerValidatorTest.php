<?php

namespace Tests\Unit\PoliBot;

use App\Services\Poli\ParsedPoliMessage;
use App\Services\PoliBot\AnswerValidator;
use App\Services\PoliBot\DefaultFlows;
use App\Services\PoliBot\FlowDefinition;
use App\Services\PoliBot\PoliTextMask;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Validação de respostas, definição de fluxo e máscara — sem banco.
 */
class AnswerValidatorTest extends TestCase
{
    private AnswerValidator $v;

    protected function setUp(): void
    {
        parent::setUp();

        $this->v = new AnswerValidator();
        Carbon::setTestNow('2026-09-25 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private const OPCOES = [
        ['label' => 'Passarela Gastrobar', 'aliases' => ['passarela']],
        ['label' => 'Campo'],
        ['label' => 'Campo Society'],
        ['label' => 'Ginásio', 'description' => 'Quadra coberta'],
    ];

    public function test_opcao_por_numero_rotulo_descricao_apelido_e_prefixo(): void
    {
        $this->assertSame('Campo', $this->v->option(self::OPCOES, '2')->value);
        $this->assertSame('Ginásio', $this->v->option(self::OPCOES, 'ginasio')->value);
        $this->assertSame('Ginásio', $this->v->option(self::OPCOES, "Ginásio\nQuadra coberta")->value);
        $this->assertSame('Passarela Gastrobar', $this->v->option(self::OPCOES, 'PASSARELA')->value);
        $this->assertSame('Campo', $this->v->option(self::OPCOES, 'Campo Bar do Campo')->value);
        $this->assertSame('Campo Society', $this->v->option(self::OPCOES, 'campo society perto do bar')->value, 'o rótulo mais longo vence');
    }

    public function test_opcao_inexistente_e_recusada(): void
    {
        $this->assertFalse($this->v->option(self::OPCOES, '9')->valid);
        $this->assertFalse($this->v->option(self::OPCOES, '0')->valid);
        $this->assertFalse($this->v->option(self::OPCOES, 'piscina')->valid);
        $this->assertFalse($this->v->option(self::OPCOES, 'Campeonato')->valid, 'prefixo só casa em palavra inteira');
    }

    public function test_placa_antiga_e_mercosul(): void
    {
        $this->assertSame('ABC1234', $this->v->plate('abc-1234')->value);
        $this->assertSame('ABC1D23', $this->v->plate(' abc 1d23 ')->value);
        $this->assertFalse($this->v->plate('AB1234')->valid);
        $this->assertFalse($this->v->plate('placa do uber')->valid);
    }

    public function test_data_com_separadores_e_checkdate(): void
    {
        $this->assertSame('1980-09-25', $this->v->date('25/09/1980')->value);
        $this->assertSame('1980-09-25', $this->v->date('25-9-80')->value);
        $this->assertSame('1980-09-25', $this->v->date('25091980')->value);
        $this->assertSame('2020-02-29', $this->v->date('29/02/2020')->value);
        $this->assertFalse($this->v->date('29/02/2021')->valid);
        $this->assertFalse($this->v->date('31/04/2020')->valid);
        $this->assertFalse($this->v->date('ontem')->valid);
        $this->assertFalse($this->v->date('01/01/2030', ['past_only' => true])->valid);
    }

    public function test_sim_e_nao(): void
    {
        $this->assertTrue($this->v->yesNo('Sim!')->value);
        $this->assertFalse($this->v->yesNo('não')->value);
        $this->assertFalse($this->v->yesNo('talvez')->valid);
    }

    public function test_numero_com_limites(): void
    {
        $this->assertSame(3, $this->v->number('3', ['min' => 1, 'max' => 5])->value);
        $this->assertFalse($this->v->number('9', ['max' => 5])->valid);
        $this->assertFalse($this->v->number('três')->valid);
    }

    public function test_texto_com_padrao_que_tem_barra(): void
    {
        $matricula = ['min' => 4, 'max' => 20, 'pattern' => '^[0-9A-Za-z./ -]+$'];

        $this->assertTrue($this->v->text('12.345/6', $matricula)->valid);
        $this->assertFalse($this->v->text('minha matrícula é 123', $matricula)->valid);
        $this->assertFalse($this->v->text('12', $matricula)->valid);
    }

    public function test_padrao_quebrado_recusa_sem_estourar(): void
    {
        $this->assertFalse($this->v->text('qualquer', ['pattern' => '(['])->valid);
    }

    public function test_imagem_e_midia(): void
    {
        $img = new ParsedPoliMessage('m1', 'c', null, null, null, ParsedPoliMessage::TYPE_IMAGE, null, 'https://x/p.jpg');
        $audio = new ParsedPoliMessage('m2', 'c', null, null, null, ParsedPoliMessage::TYPE_UNKNOWN);
        $texto = new ParsedPoliMessage('m3', 'c', null, null, null, ParsedPoliMessage::TYPE_TEXT, 'oi');

        $this->assertSame('https://x/p.jpg', $this->v->validate(['expect' => ['type' => 'image']], $img)->value);
        $this->assertSame('image', $this->v->validate(['expect' => ['type' => 'image']], $texto)->reason);
        $this->assertSame('media', $this->v->validate(['expect' => ['type' => 'text']], $audio)->reason);
    }

    public function test_fluxos_padrao_sao_validos(): void
    {
        foreach (DefaultFlows::all() as $slug => $fluxo) {
            $this->assertSame([], (new FlowDefinition($slug, $fluxo['definition']))->errors(), $slug);
        }
    }

    public function test_definicao_quebrada_aponta_os_problemas(): void
    {
        $erros = (new FlowDefinition('x', [
            'start' => 'nao-existe',
            'steps' => [
                'a' => ['say' => ['type' => 'menu', 'text' => 'Escolha'], 'expect' => ['type' => 'option'], 'options' => []],
                'b' => ['say' => ['type' => 'template'], 'next' => 'fantasma'],
                'c' => ['expect' => ['type' => 'telepatia']],
                'd' => ['action' => ['type' => 'goto_flow']],
            ],
        ]))->errors();

        $texto = implode("\n", $erros);
        $this->assertStringContainsString('Passo inicial "nao-existe"', $texto);
        $this->assertStringContainsString('menu sem opções', $texto);
        $this->assertStringContainsString('template sem template_uuid', $texto);
        $this->assertStringContainsString('"fantasma" não existe', $texto);
        $this->assertStringContainsString('"telepatia" desconhecido', $texto);
        $this->assertStringContainsString('goto_flow sem o fluxo', $texto);
    }

    public function test_mascara_cpf_e_data_mas_nao_placa_nem_matricula(): void
    {
        $this->assertSame('cpf ***.***.***-** ok', PoliTextMask::mask('cpf 123.456.789-09 ok'));
        $this->assertSame('***.***.***-**', PoliTextMask::mask('12345678909'));
        $this->assertSame('nasci em **/**/****', PoliTextMask::mask('nasci em 25/09/1980'));
        $this->assertSame('ABC1D23 e 12345', PoliTextMask::mask('ABC1D23 e 12345'));
    }
}
