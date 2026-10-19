<?php

namespace Tests\Feature\Social;

use App\Authorization\Permissions as P;
use App\Models\Place;
use App\Models\PlaceGroup;
use App\Models\Social\SocialBooking;
use App\Services\Social\SocialBookingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreatesSocialBookingSchema;
use Tests\Concerns\MigratesAccessSchema;
use Tests\TestCase;

/**
 * O sino do painel avisa a equipe quando chega pré-reserva pelo app e quando o
 * prazo de contato vence. Com o acesso de verdade (setores, permissões e
 * notificações migrados): recebe quem tem `reservas.sociais` pelo setor ou
 * individualmente — e só essas pessoas.
 */
class SocialStaffNotificationTest extends TestCase
{
    use CreatesSocialBookingSchema;
    use MigratesAccessSchema;

    private Place $place;
    private int $memberId;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Carbon::setTestNow('2026-10-14 10:00:00');

        $this->migrateAccessSchema();
        Artisan::call('migrate', ['--path' => ['database/migrations/2026_06_10_000001_create_notifications_table.php']]);
        $this->createSocialBookingSchema(withUsers: false);

        $group = PlaceGroup::create(['name' => 'Salões', 'category' => PlaceGroup::CATEGORY_SOCIAL, 'minimum_antecedence' => 2]);
        $this->place = Place::create(['name' => 'Salão Estrela', 'place_group_id' => $group->id, 'status_id' => 1, 'price' => 390, 'max_guests' => 30]);
        $this->memberId = DB::table('members')->insertGetId(['cpf' => '39053344705', 'name' => 'Ana', 'email' => 'ana@teste.local']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function sectorWithPermission(string $name, string $permission): int
    {
        $sectorId = DB::table('sectors')->insertGetId(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sector_permission')->insert([
            'sector_id' => $sectorId,
            'permission_id' => DB::table('permissions')->where('name', $permission)->value('id'),
            'coordinators_only' => false,
        ]);

        return $sectorId;
    }

    private function unread(int $userId)
    {
        return DB::table('notifications')->where('notifiable_id', $userId)->whereNull('read_at')->get();
    }

    public function test_pre_reserva_do_app_avisa_quem_tem_a_permissao(): void
    {
        $sector = $this->sectorWithPermission('Secretaria Social', P::RESERVAS_SOCIAIS);
        $atendente = $this->makeUser('Atendente Social');
        DB::table('user_sector')->insert(['user_id' => $atendente->id, 'sector_id' => $sector, 'role' => 'collaborator', 'created_at' => now(), 'updated_at' => now()]);

        $individual = $this->makeUser('Pessoa Individual');
        DB::table('model_has_permissions')->insert([
            'permission_id' => DB::table('permissions')->where('name', P::RESERVAS_SOCIAIS)->value('id'),
            'model_type' => 'App\\Models\\User',
            'model_id' => $individual->id,
        ]);

        $outro = $this->makeUser('Outro Setor');

        $booking = app(SocialBookingService::class)->request($this->place, $this->memberId, ['date' => '2026-11-14', 'event_type' => 'Aniversário']);

        $notice = $this->unread($atendente->id);
        $this->assertCount(1, $notice);
        $data = json_decode($notice->first()->data, true);
        $this->assertSame('social_booking_requested', $data['type']);
        $this->assertSame($booking->id, $data['social_booking_id']);
        $this->assertStringContainsString('16/10', $data['message']);
        $this->assertSame(route('social-booking.show', $booking->id, false), $data['url']);

        $this->assertCount(1, $this->unread($individual->id));
        $this->assertCount(0, $this->unread($outro->id));
    }

    public function test_pre_reserva_do_balcao_nao_avisa(): void
    {
        $sector = $this->sectorWithPermission('Secretaria Social', P::RESERVAS_SOCIAIS);
        $atendente = $this->makeUser('Atendente Social');
        DB::table('user_sector')->insert(['user_id' => $atendente->id, 'sector_id' => $sector, 'role' => 'collaborator', 'created_at' => now(), 'updated_at' => now()]);

        app(SocialBookingService::class)->request($this->place, $this->memberId, ['date' => '2026-11-14', 'event_type' => 'Aniversário'], $atendente->id);

        $this->assertCount(0, $this->unread($atendente->id));
    }

    public function test_prazo_vencido_avisa_de_novo_uma_vez(): void
    {
        $sector = $this->sectorWithPermission('Secretaria Social', P::RESERVAS_SOCIAIS);
        $atendente = $this->makeUser('Atendente Social');
        DB::table('user_sector')->insert(['user_id' => $atendente->id, 'sector_id' => $sector, 'role' => 'collaborator', 'created_at' => now(), 'updated_at' => now()]);

        $booking = app(SocialBookingService::class)->request($this->place, $this->memberId, ['date' => '2026-11-14', 'event_type' => 'Aniversário']);

        Carbon::setTestNow('2026-10-17 08:00:00');
        $this->artisan('social:contact-reminders')->assertSuccessful();
        $this->artisan('social:contact-reminders')->assertSuccessful();

        $types = $this->unread($atendente->id)->map(fn ($n) => json_decode($n->data, true)['type'])->sort()->values()->all();
        $this->assertSame(['social_booking_contact_overdue', 'social_booking_requested'], $types);
        $this->assertNotNull(SocialBooking::find($booking->id)->contact_overdue_notified_at);
    }
}
