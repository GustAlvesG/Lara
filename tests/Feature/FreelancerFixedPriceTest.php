<?php

namespace Tests\Feature;

use App\Http\Requests\StoreFreelancerServiceRequest;
use App\Http\Requests\StoreFreelancerServicesBulkRequest;
use App\Imports\FreelancerServiceImport;
use App\Imports\ImportValues;
use App\Exceptions\ImportRowException;
use App\Models\Freelancer;
use App\Models\FreelancerService;
use App\Models\FunctionFreelancer;
use App\Services\FreelancerService as FreelancerServiceManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\CreatesFreelancerPixSchema;
use Tests\TestCase;

/**
 * Contrato de valor fixo: o valor é digitado por quem registra, em vez de sair
 * dos blocos de 15 minutos × preço da função. O padrão continua sendo por horas.
 *
 * O que estes testes seguram é a fronteira entre as duas formas — o valor fixo
 * só troca a conta do preço, e não pode vazar para o contrato por horas nem ser
 * desfeito em silêncio por uma edição ou por um aditivo.
 *
 * Sem passar pelas rotas, pelo motivo de sempre: painel e tablet exigem o
 * `User`, preso à conexão mysql. A regra mora no serviço, que é o que todos os
 * caminhos de criação chamam.
 */
class FreelancerFixedPriceTest extends TestCase
{
    use CreatesFreelancerPixSchema;

    private const DIA = '2026-10-10';

    private FunctionFreelancer $garcom;

    private Freelancer $maria;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createFreelancerPixSchema();

        Carbon::setTestNow('2026-10-10 12:00:00');

        // R$ 10,00 por bloco de 15 minutos: 4h = 16 blocos = R$ 160,00.
        $this->garcom = FunctionFreelancer::create(['name' => 'Garçom', 'price' => 10.00]);
        $this->maria = Freelancer::create([
            'name' => 'Maria',
            'cpf' => '12345678901',
            'rg' => '123456',
            'nacionality' => 'brasileira',
            'civil_status' => 'solteira',
            'address' => 'Rua A, 1',
            'telephone' => '24999990000',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /* ---------------------------------------------------------------------
     | Criação
     |---------------------------------------------------------------------*/

    public function test_sem_dizer_a_forma_o_contrato_e_por_horas(): void
    {
        $service = $this->manager()->createService($this->dados())->refresh();

        $this->assertFalse($service->isFixedPrice());
        $this->assertSame(FreelancerService::PRICING_HOURLY, $service->pricing_mode);
        $this->assertEquals(160.00, (float) $service->price);
        $this->assertNull($service->kindLabel(), 'Contrato comum não leva rótulo.');
    }

    public function test_valor_fixo_grava_o_valor_digitado_e_nao_o_das_horas(): void
    {
        $service = $this->manager()->createService($this->dados([
            'pricing_mode' => FreelancerService::PRICING_FIXED,
            'fixed_price' => '350.50',
        ]))->refresh();

        $this->assertTrue($service->isFixedPrice());
        $this->assertEquals(350.50, (float) $service->price);

        // O turno continua sendo um turno: a duração e a data de término seguem
        // derivadas do horário, que é por onde a portaria e o jantar decidem.
        $this->assertEquals(4.00, (float) $service->total_hours);
        $this->assertSame(self::DIA, $service->end_date->toDateString());
    }

    public function test_valor_fixo_em_turno_que_vira_o_dia_ainda_deriva_o_termino(): void
    {
        $service = $this->manager()->createService($this->dados([
            'start_time' => '22:00',
            'end_time' => '02:00',
            'pricing_mode' => FreelancerService::PRICING_FIXED,
            'fixed_price' => 500,
        ]))->refresh();

        $this->assertSame('2026-10-11', $service->end_date->toDateString());
        $this->assertEquals(500.00, (float) $service->price);
    }

    /** `price` segue não sendo entrada, nem no valor fixo: quem manda é `fixed_price`. */
    public function test_price_enviado_e_ignorado_nas_duas_formas(): void
    {
        $fixo = $this->manager()->createService($this->dados([
            'pricing_mode' => FreelancerService::PRICING_FIXED,
            'fixed_price' => 300,
            'price' => 9999,
        ]))->refresh();

        $porHoras = $this->manager()->createService($this->dados([
            'start_date' => '2026-10-11',
            'price' => 9999,
        ]))->refresh();

        $this->assertEquals(300.00, (float) $fixo->price);
        $this->assertEquals(160.00, (float) $porHoras->price);
    }

    /** Um valor esquecido no campo não transforma o contrato por horas em fixo. */
    public function test_fixed_price_sem_a_forma_fixa_nao_vale_nada(): void
    {
        $service = $this->manager()->createService($this->dados([
            'pricing_mode' => FreelancerService::PRICING_HOURLY,
            'fixed_price' => 900,
        ]))->refresh();

        $this->assertFalse($service->isFixedPrice());
        $this->assertEquals(160.00, (float) $service->price);
    }

    #[DataProvider('valoresRecusados')]
    public function test_valor_fixo_invalido_e_recusado_tambem_no_servico($valor): void
    {
        $this->expectException(ValidationException::class);

        $this->manager()->createService($this->dados([
            'pricing_mode' => FreelancerService::PRICING_FIXED,
            'fixed_price' => $valor,
        ]));
    }

    public static function valoresRecusados(): array
    {
        return [
            'ausente' => [null],
            'zero' => [0],
            'negativo' => [-50],
            'texto' => ['abc'],
            'acima do teto' => [FreelancerService::MAX_FIXED_PRICE + 0.01],
        ];
    }

    /* ---------------------------------------------------------------------
     | Edição (contrato ainda sem assinatura)
     |---------------------------------------------------------------------*/

    /**
     * O `PUT` do bot não conhece o campo. Corrigir o local de um contrato de
     * valor fixo não pode recalculá-lo pelas horas.
     */
    public function test_editar_sem_dizer_a_forma_mantem_o_valor_fixo(): void
    {
        $service = $this->fixo(350);

        $this->manager()->updateService($service, $this->dados([
            'location' => 'Churrasqueira',
            'end_time' => '23:00',
        ]));

        $service->refresh();

        $this->assertTrue($service->isFixedPrice());
        $this->assertEquals(350.00, (float) $service->price);
        $this->assertSame('Churrasqueira', $service->location);
        $this->assertEquals(5.00, (float) $service->total_hours);
    }

    public function test_editar_troca_o_valor_fixo(): void
    {
        $service = $this->fixo(350);

        $this->manager()->updateService($service, $this->dados([
            'pricing_mode' => FreelancerService::PRICING_FIXED,
            'fixed_price' => 420,
        ]));

        $this->assertEquals(420.00, (float) $service->refresh()->price);
    }

    public function test_editar_de_volta_para_por_horas_recalcula(): void
    {
        $service = $this->fixo(350);

        $this->manager()->updateService($service, $this->dados([
            'pricing_mode' => FreelancerService::PRICING_HOURLY,
        ]));

        $service->refresh();

        $this->assertFalse($service->isFixedPrice());
        $this->assertEquals(160.00, (float) $service->price);
    }

    public function test_editar_de_por_horas_para_fixo_exige_o_valor(): void
    {
        $service = $this->manager()->createService($this->dados());

        $this->expectException(ValidationException::class);

        $this->manager()->updateService($service, $this->dados([
            'pricing_mode' => FreelancerService::PRICING_FIXED,
        ]));
    }

    /* ---------------------------------------------------------------------
     | Aditivo de horário
     |---------------------------------------------------------------------*/

    /** Se o valor não depende das horas, esticar o turno não o altera. */
    public function test_aditivo_de_contrato_de_valor_fixo_herda_a_forma_e_o_valor(): void
    {
        $base = $this->assinado($this->fixo(350));

        $aditivo = $this->manager()->createAmendment($base, [
            'location' => 'Salão Nobre',
            'start_time' => '18:00',
            'end_time' => '23:30',
        ])->refresh();

        $this->assertTrue($aditivo->isFixedPrice());
        $this->assertEquals(350.00, (float) $aditivo->price);
        $this->assertEquals(5.50, (float) $aditivo->total_hours);
        $this->assertTrue($base->refresh()->isAmended());
        $this->assertStringContainsString('Valor fixo', $aditivo->kindNote());
    }

    public function test_aditivo_de_contrato_por_horas_continua_recalculando(): void
    {
        $base = $this->assinado($this->manager()->createService($this->dados()));

        $aditivo = $this->manager()->createAmendment($base, [
            'location' => 'Salão Nobre',
            'start_time' => '18:00',
            'end_time' => '23:30',
        ])->refresh();

        $this->assertFalse($aditivo->isFixedPrice());
        $this->assertEquals(220.00, (float) $aditivo->price);
    }

    /* ---------------------------------------------------------------------
     | O que as telas de aprovação mostram
     |---------------------------------------------------------------------*/

    public function test_contrato_de_valor_fixo_se_identifica_para_quem_aprova(): void
    {
        $service = $this->fixo(350)->refresh();

        $this->assertSame('Valor fixo', $service->kindLabel());
        $this->assertNotNull($service->kindNote());
        $this->assertSame('Valor fixo', $service->pricingModeLabel());
    }

    /** Contrato anterior à coluna, ou com lixo nela, é lido como por horas. */
    public function test_forma_desconhecida_ou_ausente_e_lida_como_por_horas(): void
    {
        $this->assertFalse((new FreelancerService())->isFixedPrice());
        $this->assertFalse((new FreelancerService(['pricing_mode' => 'qualquer']))->isFixedPrice());
    }

    /* ---------------------------------------------------------------------
     | Validação de entrada
     |---------------------------------------------------------------------*/

    public function test_formulario_exige_o_valor_quando_a_forma_e_fixa(): void
    {
        $this->assertTrue($this->validar($this->dados())->passes(), 'Por horas é o padrão e não pede valor.');

        $semValor = $this->validar($this->dados(['pricing_mode' => FreelancerService::PRICING_FIXED]));
        $this->assertTrue($semValor->fails());
        $this->assertSame('Informe o valor fixo do contrato.', $semValor->errors()->first('fixed_price'));

        $this->assertTrue($this->validar($this->dados([
            'pricing_mode' => FreelancerService::PRICING_FIXED,
            'fixed_price' => '350.50',
        ]))->passes());

        $this->assertTrue($this->validar($this->dados([
            'pricing_mode' => FreelancerService::PRICING_FIXED,
            'fixed_price' => FreelancerService::MAX_FIXED_PRICE + 1,
        ]))->fails());

        $this->assertTrue($this->validar($this->dados(['pricing_mode' => 'outra']))->fails());
    }

    public function test_registro_em_massa_valida_o_valor_fixo_linha_a_linha(): void
    {
        $request = new StoreFreelancerServicesBulkRequest();

        $linhas = fn(array $segunda) => ['services' => [
            $this->dados(),
            $this->dados(['start_date' => '2026-10-11'] + $segunda),
        ]];

        $validar = fn(array $data) => Validator::make($data, $request->rules(), $request->messages());

        $this->assertTrue($validar($linhas(['pricing_mode' => FreelancerService::PRICING_FIXED, 'fixed_price' => 200]))->passes());

        $semValor = $validar($linhas(['pricing_mode' => FreelancerService::PRICING_FIXED]));
        $this->assertTrue($semValor->fails());
        $this->assertTrue($semValor->errors()->has('services.1.fixed_price'));
        $this->assertFalse($semValor->errors()->has('services.0.fixed_price'), 'A linha por horas não pede valor.');
    }

    /* ---------------------------------------------------------------------
     | Planilha
     |---------------------------------------------------------------------*/

    public function test_valor_da_planilha_aceita_virgula_ponto_e_cifrao(): void
    {
        $this->assertSame('', ImportValues::money('', 'valor fixo'));
        $this->assertSame('', ImportValues::money('   ', 'valor fixo'));
        $this->assertEquals(350.0, (float) ImportValues::money('350', 'valor fixo'));
        $this->assertEquals(350.5, (float) ImportValues::money('350,50', 'valor fixo'));
        $this->assertEquals(350.5, (float) ImportValues::money('350.5', 'valor fixo'));
        $this->assertEquals(1200.0, (float) ImportValues::money('R$ 1.200,00', 'valor fixo'));

        $this->expectException(ImportRowException::class);
        ImportValues::money('trezentos', 'valor fixo');
    }

    public function test_planilha_com_a_coluna_grava_fixo_so_onde_ela_foi_preenchida(): void
    {
        $path = $this->planilha(
            ['CPF do freelancer *', 'Função *', 'Evento / Local *', 'Data (dd/mm/aaaa) *', 'Início (HH:MM) *', 'Término (HH:MM) *', 'Descrição / Justificativa', 'Valor fixo (R$)'],
            [
                ['12345678901', 'Garçom', 'Salão Nobre', '10/10/2026', '18:00', '22:00', '', '350,50'],
                ['12345678901', 'Garçom', 'Salão Nobre', '11/10/2026', '18:00', '22:00', '', ''],
            ],
        );

        $result = app(FreelancerServiceImport::class)->import($path);

        $this->assertSame([], $result['errors']);
        $this->assertSame(2, $result['imported']);

        [$fixo, $porHoras] = FreelancerService::orderBy('start_date')->get()->all();

        $this->assertTrue($fixo->isFixedPrice());
        $this->assertEquals(350.50, (float) $fixo->price);
        $this->assertFalse($porHoras->isFixedPrice());
        $this->assertEquals(160.00, (float) $porHoras->price);
    }

    /** O modelo baixado antes de a coluna existir continua sendo aceito. */
    public function test_planilha_antiga_sem_a_coluna_importa_tudo_por_horas(): void
    {
        $path = $this->planilha(
            ['CPF do freelancer *', 'Função *', 'Evento / Local *', 'Data (dd/mm/aaaa) *', 'Início (HH:MM) *', 'Término (HH:MM) *', 'Descrição / Justificativa'],
            [['12345678901', 'Garçom', 'Salão Nobre', '10/10/2026', '18:00', '22:00', '']],
        );

        $result = app(FreelancerServiceImport::class)->import($path);

        $this->assertSame([], $result['errors']);
        $this->assertFalse(FreelancerService::firstOrFail()->isFixedPrice());
    }

    public function test_planilha_recusa_valor_fixo_que_nao_e_numero(): void
    {
        $path = $this->planilha(
            ['CPF do freelancer *', 'Função *', 'Evento / Local *', 'Data (dd/mm/aaaa) *', 'Início (HH:MM) *', 'Término (HH:MM) *', 'Descrição / Justificativa', 'Valor fixo (R$)'],
            [['12345678901', 'Garçom', 'Salão Nobre', '10/10/2026', '18:00', '22:00', '', 'a combinar']],
        );

        $result = app(FreelancerServiceImport::class)->import($path);

        $this->assertSame(0, $result['imported']);
        $this->assertStringContainsString('valor fixo inválido', $result['errors'][0]);
        $this->assertSame(0, FreelancerService::count());
    }

    /* ---------------------------------------------------------------------
     | Auxiliares
     |---------------------------------------------------------------------*/

    /** Turno de 4h (18:00 → 22:00) da Maria como garçom. */
    private function dados(array $overrides = []): array
    {
        return $overrides + [
            'freelancer_id' => $this->maria->id,
            'function_freelancer_id' => $this->garcom->id,
            'location' => 'Salão Nobre',
            'start_date' => self::DIA,
            'start_time' => '18:00',
            'end_time' => '22:00',
        ];
    }

    private function fixo(float $valor): FreelancerService
    {
        return $this->manager()->createService($this->dados([
            'pricing_mode' => FreelancerService::PRICING_FIXED,
            'fixed_price' => $valor,
        ]));
    }

    /** Aditivo só cabe em contrato já assinado. */
    private function assinado(FreelancerService $service): FreelancerService
    {
        $service->forceFill(['freelancer_signed_at' => now()])->save();

        return $service->refresh();
    }

    private function validar(array $data)
    {
        $request = new StoreFreelancerServiceRequest();

        return Validator::make($data, $request->rules(), $request->messages(), $request->attributes());
    }

    /** Grava um .xlsx descartável com o cabeçalho e as linhas informadas. */
    private function planilha(array $header, array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach (array_merge([$header], $rows) as $r => $row) {
            foreach ($row as $c => $value) {
                $sheet->setCellValueExplicit(
                    [$c + 1, $r + 1],
                    (string) $value,
                    \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                );
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'fixo') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    private function manager(): FreelancerServiceManager
    {
        return app(FreelancerServiceManager::class);
    }
}
