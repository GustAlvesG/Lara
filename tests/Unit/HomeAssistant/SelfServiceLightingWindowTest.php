<?php

namespace Tests\Unit\HomeAssistant;

use App\Models\LightingSelfServiceDate;
use App\Services\HomeAssistant\LightingWindow;
use App\Services\HomeAssistant\ManualCommandService;
use App\Services\HomeAssistant\SelfServiceLightingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A regra de calendário do autoatendimento, sozinha.
 *
 * É a parte que mais vai ser mexida — todo fim de ano alguém cadastra feriado —
 * e a que mais erra em silêncio: uma janela errada não quebra nada, só recusa
 * (ou libera) o acionamento no dia em que ninguém está olhando.
 *
 * Sem RefreshDatabase: só a tabela de datas é criada no SQLite :memory: do
 * phpunit.xml. O serviço não toca em mais nada para decidir uma janela.
 */
class SelfServiceLightingWindowTest extends TestCase
{
    private SelfServiceLightingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('lighting_self_service_dates', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->string('mode');
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->string('reason', 120)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        config([
            'home_assistant.self_service.windows' => [
                6 => ['17:00', '23:00'],
                0 => ['17:00', '21:00'],
            ],
            'home_assistant.self_service.holiday_window' => ['17:00', '21:00'],
            'home_assistant.self_service.max_minutes'    => 120,
            'home_assistant.self_service.min_minutes'    => 15,
            'home_assistant.self_service.step_minutes'   => 15,
        ]);

        // O serviço só usa o ManualCommandService para gravar o comando, e
        // nenhum teste daqui chega a acionar nada.
        $this->service = new SelfServiceLightingService(app(ManualCommandService::class));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /* ───────────── Regra semanal ───────────── */

    public function test_sabado_vai_das_17_as_23(): void
    {
        // Sábado, 26/09/2026.
        $window = $this->service->windowFor(Carbon::parse('2026-09-26'));

        $this->assertNotNull($window);
        $this->assertSame('17:00', $window->start->format('H:i'));
        $this->assertSame('23:00', $window->end->format('H:i'));
        $this->assertSame(LightingWindow::SOURCE_WEEKLY, $window->source);
    }

    public function test_domingo_fecha_mais_cedo_que_sabado(): void
    {
        $window = $this->service->windowFor(Carbon::parse('2026-09-27'));

        $this->assertSame('21:00', $window->end->format('H:i'));
    }

    public function test_dia_de_semana_nao_tem_janela(): void
    {
        // Quarta-feira.
        $this->assertNull($this->service->windowFor(Carbon::parse('2026-09-23')));
    }

    public function test_a_janela_e_meio_aberta_no_fim(): void
    {
        // Às 23:00 em ponto de sábado a janela já fechou: quem entra nesse
        // segundo não ganha luz nenhuma, e é isso que o app precisa dizer.
        $this->assertNotNull($this->service->openWindowAt(Carbon::parse('2026-09-26 22:59:59')));
        $this->assertNull($this->service->openWindowAt(Carbon::parse('2026-09-26 23:00:00')));
    }

    public function test_antes_da_abertura_o_sabado_ainda_esta_fechado(): void
    {
        $this->assertNull($this->service->openWindowAt(Carbon::parse('2026-09-26 16:59:00')));
    }

    /* ───────────── Datas especiais ───────────── */

    public function test_feriado_no_meio_da_semana_abre_com_a_janela_padrao(): void
    {
        // 25/12/2026 cai numa sexta-feira — dia sem janela semanal.
        LightingSelfServiceDate::create([
            'date'   => '2026-12-25',
            'mode'   => LightingSelfServiceDate::MODE_ALLOW,
            'reason' => 'Natal',
        ]);

        $window = $this->service->windowFor(Carbon::parse('2026-12-25'));

        $this->assertNotNull($window);
        $this->assertSame('17:00', $window->start->format('H:i'));
        $this->assertSame('21:00', $window->end->format('H:i'));
        $this->assertSame(LightingWindow::SOURCE_DATE, $window->source);
        $this->assertSame('Natal', $window->reason);
    }

    public function test_feriado_pode_trazer_o_proprio_horario(): void
    {
        LightingSelfServiceDate::create([
            'date'      => '2026-12-25',
            'mode'      => LightingSelfServiceDate::MODE_ALLOW,
            'starts_at' => '18:00',
            'ends_at'   => '22:30',
        ]);

        $window = $this->service->windowFor(Carbon::parse('2026-12-25'));

        $this->assertSame('18:00', $window->start->format('H:i'));
        $this->assertSame('22:30', $window->end->format('H:i'));
    }

    public function test_bloqueio_fecha_um_sabado_que_a_regra_semanal_abriria(): void
    {
        LightingSelfServiceDate::create([
            'date'   => '2026-09-26',
            'mode'   => LightingSelfServiceDate::MODE_BLOCK,
            'reason' => 'Torneio de beach tennis',
        ]);

        $this->assertNull($this->service->windowFor(Carbon::parse('2026-09-26')));
        $this->assertNull($this->service->openWindowAt(Carbon::parse('2026-09-26 19:00')));
    }

    public function test_janela_invertida_no_cadastro_nao_abre_nada(): void
    {
        // Um "das 22:00 às 02:00" não teria como ser executado: o comando
        // manual é truncado na meia-noite. Melhor não abrir do que abrir e
        // entregar menos do que prometeu.
        LightingSelfServiceDate::create([
            'date'      => '2026-12-25',
            'mode'      => LightingSelfServiceDate::MODE_ALLOW,
            'starts_at' => '22:00',
            'ends_at'   => '02:00',
        ]);

        $this->assertNull($this->service->windowFor(Carbon::parse('2026-12-25')));
    }

    /* ───────────── Próxima janela ───────────── */

    public function test_a_proxima_janela_numa_quarta_e_o_sabado_seguinte(): void
    {
        $next = $this->service->nextWindow(Carbon::parse('2026-09-23 10:00'));

        $this->assertSame('2026-09-26', $next->start->toDateString());
    }

    public function test_no_sabado_de_manha_a_proxima_janela_e_a_daquela_tarde(): void
    {
        $next = $this->service->nextWindow(Carbon::parse('2026-09-26 09:00'));

        $this->assertSame('2026-09-26', $next->start->toDateString());
        $this->assertSame('17:00', $next->start->format('H:i'));
    }

    public function test_depois_do_fim_do_sabado_a_proxima_e_o_domingo(): void
    {
        $next = $this->service->nextWindow(Carbon::parse('2026-09-26 23:10'));

        $this->assertSame('2026-09-27', $next->start->toDateString());
    }

    public function test_o_sabado_bloqueado_e_pulado_na_busca_da_proxima(): void
    {
        LightingSelfServiceDate::create([
            'date' => '2026-09-26',
            'mode' => LightingSelfServiceDate::MODE_BLOCK,
        ]);

        $next = $this->service->nextWindow(Carbon::parse('2026-09-23 10:00'));

        $this->assertSame('2026-09-27', $next->start->toDateString());
    }

    /* ───────────── Duração concedida ───────────── */

    public function test_sem_pedido_explicito_vale_o_teto(): void
    {
        $now = Carbon::parse('2026-09-26 18:00');

        $this->assertSame(120, $this->service->minutesToGrant($this->service->openWindowAt($now), $now));
    }

    public function test_o_socio_recebe_o_tempo_que_pediu(): void
    {
        $now = Carbon::parse('2026-09-26 18:00');

        $this->assertSame(40, $this->service->minutesToGrant($this->service->openWindowAt($now), $now, 40));
    }

    public function test_pedido_acima_do_teto_e_aparado_no_teto(): void
    {
        // A validação do FormRequest já recusa antes de chegar aqui; esta é a
        // segunda tranca, para quem chamar o serviço direto.
        $now = Carbon::parse('2026-09-26 18:00');

        $this->assertSame(120, $this->service->minutesToGrant($this->service->openWindowAt($now), $now, 300));
    }

    public function test_perto_do_fechamento_a_duracao_e_aparada_na_janela(): void
    {
        // 22:30 de sábado: sobram 30 minutos de janela, não duas horas. Sem
        // isto a luz ficaria acesa meia-noite adentro num clube vazio.
        $now = Carbon::parse('2026-09-26 22:30');

        $this->assertSame(30, $this->service->minutesToGrant($this->service->openWindowAt($now), $now, 120));
    }

    public function test_pedido_curto_cabe_inteiro_mesmo_perto_do_fechamento(): void
    {
        $now = Carbon::parse('2026-09-26 22:30');

        $this->assertSame(20, $this->service->minutesToGrant($this->service->openWindowAt($now), $now, 20));
    }
}
