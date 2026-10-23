<?php

namespace Tests\Feature\Tournament;

use App\Models\Tournament\Tournament;
use App\Models\Tournament\TournamentCategory;
use App\Models\Tournament\TournamentCategoryTemplate as Template;
use App\Models\User;
use App\Services\Tournament\TournamentFinanceService;
use App\Services\Tournament\TournamentRegistrationService;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsTournaments;
use Tests\Concerns\CreatesTournamentSchema;
use Tests\TestCase;

/**
 * Torneios, F7: relatório financeiro por inscrição. Um pagamento eRede que
 * cobre duas categorias, uma baixa manual, um estorno de uma das categorias
 * e uma inscrição pendente.
 */
class TournamentFinanceTest extends TestCase
{
    use CreatesTournamentSchema;
    use BuildsTournaments;

    private Tournament $published;
    private TournamentCategory $duplas;
    private TournamentCategory $simples;
    private User $organizer;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->createTournamentSchema();
        $this->fakeIntegrations();
        $this->organizer = $this->makeUser('Pessoa do Esporte');
        $this->joinSector($this->organizer, 'TI');

        $this->published = $this->tournament();
        $this->duplas = $this->category($this->published, ['price' => 80]);
        $this->simples = $this->category($this->published, ['name' => 'Simples', 'team_size' => 1, 'price' => 50]);

        $registrations = $this->app->make(TournamentRegistrationService::class);

        // Ana: uma eRede cobrindo as duas categorias (130), depois estorno da Simples (50).
        $ana = $this->member('39053344705', 'Ana Souza', '1001');
        $regs = $registrations->register($this->published, $ana, $registrations->profileFor($ana), [$this->duplas->id, $this->simples->id]);
        $registrations->pay($ana, $regs->pluck('id')->all(), ['payment_method' => 'credit', 'paid_amount' => 130, 'payment_integration_id' => 'TID-ANA']);
        $registrations->cancel($regs->firstWhere('tournament_category_id', $this->simples->id)->fresh(), $this->organizer, 'Lesão', refund: true);

        // Bruno: baixa manual nas Duplas (80).
        $bruno = $this->member('86288366757', 'Bruno Lima', '1002', Template::GENDER_MALE);
        $reg = $registrations->register($this->published, $bruno, $registrations->profileFor($bruno), [$this->duplas->id])->first();
        $registrations->recordManual([$reg->id], ['payment_method' => 'manual_cash'], $this->organizer);

        // Carla: pendente nas Duplas (80).
        $carla = $this->member('52998224725', 'Carla Dias', '1003');
        $registrations->register($this->published, $carla, $registrations->profileFor($carla), [$this->duplas->id]);
    }

    public function test_resumo_por_categoria_e_total(): void
    {
        $summary = $this->app->make(TournamentFinanceService::class)->summary($this->published);
        $duplas = $summary['categories']->firstWhere('category.id', $this->duplas->id);
        $simples = $summary['categories']->firstWhere('category.id', $this->simples->id);

        $this->assertSame(2, $duplas['paid_count']);
        $this->assertSame(160.0, $duplas['received']);
        $this->assertSame(80.0, $duplas['received_rede']);
        $this->assertSame(80.0, $duplas['received_manual']);
        $this->assertSame(0.0, $duplas['refunded']);
        $this->assertSame(1, $duplas['pending_count']);
        $this->assertSame(80.0, $duplas['pending_amount']);

        $this->assertSame(0, $simples['paid_count']);
        $this->assertSame(1, $simples['cancelled_after_payment']);
        $this->assertSame(50.0, $simples['received']);
        $this->assertSame(50.0, $simples['refunded']);
        $this->assertSame(0.0, $simples['net']);

        $this->assertSame(210.0, $summary['total']['received']);
        $this->assertSame(50.0, $summary['total']['refunded']);
        $this->assertSame(160.0, $summary['total']['net']);
        $this->assertSame([['tid' => 'TID-ANA', 'amount' => 50.0]], $this->redeRefunds);
    }

    public function test_telas_e_csv(): void
    {
        $semAcesso = $this->makeUser('Sem Acesso');
        $this->actingAs($semAcesso)->get(route('tournaments.finance.index'))->assertForbidden();

        $this->actingAs($this->organizer)->get(route('tournaments.finance.index'))
            ->assertOk()
            ->assertSee('Copa de Verão')
            ->assertSee('R$ 160,00');

        $this->actingAs($this->organizer)->get(route('tournaments.finance.show', $this->published))
            ->assertOk()
            ->assertSee('Simples')
            ->assertSee('TID TID-ANA')
            ->assertSee('baixa manual por Pessoa do Esporte');

        $csv = $this->actingAs($this->organizer)->get(route('tournaments.finance.csv', $this->published));
        $csv->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $body = $csv->getContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $this->assertStringContainsString('"Ana Souza";39053344705;"Duplas Livre, Simples";credit;não;TID-ANA;130,00;50,00;"Estornado em parte"', $body);
        $this->assertStringContainsString('"Bruno Lima";86288366757;"Duplas Livre";Dinheiro;sim', $body);
    }
}
