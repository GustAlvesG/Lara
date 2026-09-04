<?php

namespace Tests\Feature;

use App\Models\Place;
use App\Models\PlaceGroup;
use App\Services\ScheduleRulesService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

/**
 * A grade de horários de hoje continua oferecendo o horário já em andamento.
 *
 * Antes, qualquer horário cujo início já tivesse passado sumia da grade — às
 * 20:01 o horário das 20:00 às 21:00 estava perdido. Agora ele fica à venda
 * pelo valor proporcional ao tempo restante e só sai da grade quando não dá
 * mais tempo de pagá-lo antes do fim (fim menos o hold do pendente).
 *
 * Sem RefreshDatabase pelo mesmo motivo registrado em FleetMileageTest: a
 * cadeia completa de migrations falha hoje. Aqui só as tabelas que a grade
 * consulta são criadas no SQLite :memory: do phpunit.xml.
 */
class ScheduleInProgressSlotTest extends TestCase
{
    /** Sexta-feira, 04/09/2026. */
    private const DATE = '2026-09-04';

    private Place $place;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();

        $weekdayId = Carbon::parse(self::DATE)->dayOfWeek + 1;

        DB::table('weekdays')->insert([
            'id' => $weekdayId,
            'name' => 'Friday',
            'short_name' => 'Fri',
            'name_pt' => 'Sexta-feira',
            'short_name_pt' => 'Sex',
        ]);

        $group = PlaceGroup::create([
            'name' => 'Quadras',
            'start_time' => '08:00:00',
            'end_time' => '22:00:00',
            'duration' => '01:00:00',
            'daily_limit' => 2,
            'minimum_antecedence' => 0,
            // Sem antecedência máxima o grupo só vende para o próprio dia
            // (calcAntecedenceDate), e o caso da data futura nem chegaria à grade.
            'maximum_antecedence' => 30,
        ]);

        $group->weekdays()->attach($weekdayId);

        $this->place = Place::create([
            'name' => 'Quadra 1',
            'place_group_id' => $group->id,
            'price' => 100,
            'status_id' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @return array<string, array<string, mixed>> horários da grade indexados por "20:00 - 21:00" */
    private function gridAt(string $now): array
    {
        Carbon::setTestNow($now);

        $options = (new ScheduleRulesService())->getTimeOptions($this->place->id, self::DATE);

        return collect($options)
            ->keyBy(fn ($option) => $option['start_time'] . ' - ' . $option['end_time'])
            ->all();
    }

    public function test_horario_em_andamento_continua_na_grade_pela_metade_do_preco(): void
    {
        $grid = $this->gridAt(self::DATE . ' 20:30:00');

        $this->assertArrayHasKey('20:00 - 21:00', $grid);

        $slot = $grid['20:00 - 21:00'];

        $this->assertTrue($slot['in_progress']);
        $this->assertSame(50.0, $slot['price']);
        $this->assertSame(100.0, $slot['full_price']);
        $this->assertSame(30, $slot['remaining_minutes']);
    }

    public function test_horario_que_ainda_nao_comecou_sai_pelo_preco_cheio(): void
    {
        $grid = $this->gridAt(self::DATE . ' 20:30:00');

        $slot = $grid['21:00 - 22:00'];

        $this->assertFalse($slot['in_progress']);
        $this->assertSame(100.0, $slot['price']);
        $this->assertSame(60, $slot['remaining_minutes']);
    }

    /** O corte passou a ser o fim do horário menos o hold, não mais o início. */
    public function test_horario_sai_da_grade_um_hold_antes_do_fim(): void
    {
        $this->assertArrayHasKey('20:00 - 21:00', $this->gridAt(self::DATE . ' 20:49:00'));
        $this->assertArrayNotHasKey('20:00 - 21:00', $this->gridAt(self::DATE . ' 20:50:00'));
    }

    public function test_horarios_ja_encerrados_continuam_fora_da_grade(): void
    {
        $grid = $this->gridAt(self::DATE . ' 20:30:00');

        $this->assertArrayNotHasKey('19:00 - 20:00', $grid);
        $this->assertArrayNotHasKey('08:00 - 09:00', $grid);
    }

    /**
     * Contrato do payload de `POST /api/schedule/time-options`.
     *
     * O frontend já consome essa grade: as chaves que ele lia continuam com o
     * mesmo nome e o mesmo tipo, e os campos de preço são acrescentados ao
     * lado — nunca substituem nem renomeiam nada.
     */
    public function test_payload_preserva_as_chaves_que_o_frontend_ja_lia(): void
    {
        $slot = $this->gridAt(self::DATE . ' 20:30:00')['20:00 - 21:00'];

        $this->assertSame('20:00', $slot['start_time']);
        $this->assertSame('21:00', $slot['end_time']);
        $this->assertArrayHasKey('colides', $slot);
        $this->assertNull($slot['colides']);
        $this->assertArrayNotHasKey('past_date', $slot);

        // Tudo o que é novo no horário livre, e nada além disso.
        $novas = collect(array_keys($slot))
            ->diff(['start_time', 'end_time', 'colides'])
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            ['full_price', 'in_progress', 'price', 'price_factor', 'remaining_minutes'],
            $novas
        );
    }

    /** Data futura não tem "tempo restante": todos os horários saem cheios. */
    public function test_data_futura_mantem_o_preco_cheio(): void
    {
        Carbon::setTestNow(self::DATE . ' 20:30:00');

        $options = (new ScheduleRulesService())->getTimeOptions(
            $this->place->id,
            Carbon::parse(self::DATE)->addWeek()->toDateString()
        );

        $this->assertNotEmpty($options);

        foreach ($options as $option) {
            $this->assertFalse($option['in_progress']);
            $this->assertSame(100.0, $option['price']);
        }
    }

    private function createSchema(): void
    {
        Schema::create('place_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->time('duration')->nullable();
            $table->time('start_time_sales')->nullable();
            $table->time('end_time_sales')->nullable();
            $table->integer('minimum_antecedence')->default(0);
            $table->integer('maximum_antecedence')->default(0);
            $table->integer('daily_limit')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('places', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('place_group_id');
            $table->decimal('price', 8, 2)->default(0);
            $table->unsignedBigInteger('status_id')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('weekdays', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_name');
            $table->string('name_pt');
            $table->string('short_name_pt');
        });

        Schema::create('week_days_place_group', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('weekday_id');
            $table->unsignedBigInteger('place_group_id');
            $table->timestamps();
        });

        Schema::create('schedule_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('type')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedBigInteger('status_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('place_schedule_rule', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('place_id');
            $table->unsignedBigInteger('schedule_rule_id');
            $table->timestamps();
        });

        Schema::create('week_days_schedule_rule', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('weekday_id');
            $table->unsignedBigInteger('schedule_rule_id');
            $table->timestamps();
        });

        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('place_id');
            $table->unsignedBigInteger('member_id')->nullable();
            $table->dateTime('start_schedule');
            $table->dateTime('end_schedule');
            $table->unsignedBigInteger('status_id')->default(1);
            $table->decimal('price', 8, 2)->nullable();
            $table->unsignedBigInteger('schedule_payment_id')->nullable();
            $table->timestamps();
        });
    }
}
