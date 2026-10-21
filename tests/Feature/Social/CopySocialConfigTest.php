<?php

namespace Tests\Feature\Social;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * `social:copy-config` entre dois bancos SQLite em memória ("origem" faz a
 * homologação, "destino" a produção). Copia grupos sociais, dias, espaços,
 * conflitos e catálogo; casa pelo nome (rodar de novo não duplica); não
 * apaga nem copia excluídos; a simulação não escreve nada.
 */
class CopySocialConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['origem', 'destino'] as $name) {
            config(["database.connections.{$name}" => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]]);
            $this->schema($name);
        }

        $this->seedOrigin();
        // Destino como a produção: as quadras já existem, o social não.
        $tenis = DB::connection('destino')->table('place_groups')->insertGetId(['name' => 'Tennis', 'category' => 'esportiva']);
        DB::connection('destino')->table('places')->insert(['id' => 5, 'name' => 'Quadra 1', 'place_group_id' => $tenis]);
    }

    private function schema(string $connection): void
    {
        $schema = DB::connection($connection)->getSchemaBuilder();

        $schema->create('place_groups', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('category')->nullable();
            $t->string('image_vertical')->nullable();
            $t->string('image_horizontal')->nullable();
            $t->integer('minimum_antecedence')->default(0);
            $t->integer('social_payment_days')->nullable();
            $t->text('social_rules')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
        $schema->create('places', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->unsignedBigInteger('place_group_id');
            $t->unsignedBigInteger('contactor_id')->nullable();
            $t->boolean('self_service_lighting')->default(false);
            $t->string('image')->nullable();
            $t->decimal('price', 8, 2)->default(0);
            $t->unsignedInteger('max_guests')->nullable();
            $t->unsignedInteger('base_tables')->nullable();
            $t->unsignedBigInteger('status_id')->default(1);
            $t->softDeletes();
            $t->timestamps();
        });
        $schema->create('weekdays', function (Blueprint $t) {
            $t->id();
            $t->string('name');
        });
        $schema->create('week_days_place_group', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('weekday_id');
            $t->unsignedBigInteger('place_group_id');
            $t->timestamps();
        });
        $schema->create('place_conflicts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('place_id');
            $t->unsignedBigInteger('conflicting_place_id');
            $t->timestamps();
        });
        $schema->create('social_beverages', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('category')->default('bebida');
            $t->string('mv_code')->nullable();
            $t->decimal('price', 8, 2);
            $t->unsignedSmallInteger('chairs_per_unit')->default(0);
            $t->boolean('active')->default(true);
            $t->softDeletes();
            $t->timestamps();
        });

        DB::connection($connection)->table('weekdays')->insert(array_map(fn ($i) => ['id' => $i, 'name' => "d{$i}"], range(1, 7)));
    }

    private function seedOrigin(): void
    {
        $db = DB::connection('origem');

        // Ids diferentes do destino de propósito: a cópia não pode depender deles.
        $tenis = $db->table('place_groups')->insertGetId(['id' => 2, 'name' => 'Tennis', 'category' => 'esportiva']);
        $db->table('places')->insert(['id' => 30, 'name' => 'Quadra 1', 'place_group_id' => $tenis]);

        $db->table('place_groups')->insert(['id' => 21, 'name' => 'Churrasqueira', 'category' => 'social', 'deleted_at' => '2025-11-14 10:00:00']);
        $churras = $db->table('place_groups')->insertGetId(['id' => 22, 'name' => 'Churrasqueiras', 'category' => 'social', 'image_vertical' => 'churras.jpg', 'social_payment_days' => 7, 'social_rules' => 'Crianças até 4 anos não contam.']);
        $db->table('week_days_place_group')->insert([['weekday_id' => 6, 'place_group_id' => $churras], ['weekday_id' => 7, 'place_group_id' => $churras]]);

        $db->table('places')->insert([
            ['id' => 37, 'name' => 'Churrasqueira 21', 'place_group_id' => $churras, 'image' => 'https://i.imgur.com/abc1234.jpg', 'price' => 350, 'max_guests' => 15, 'base_tables' => 6, 'contactor_id' => 3],
            ['id' => 38, 'name' => 'Churrasqueira da Sauna', 'place_group_id' => $churras, 'image' => 'nao-existe.png', 'price' => 800, 'max_guests' => 20, 'base_tables' => 10, 'contactor_id' => null],
        ]);
        $db->table('place_conflicts')->insert([
            ['place_id' => 38, 'conflicting_place_id' => 37],
            ['place_id' => 38, 'conflicting_place_id' => 30], // com a quadra
        ]);

        $db->table('social_beverages')->insert([
            ['name' => 'Coca-Cola 2L', 'category' => 'bebida', 'mv_code' => 'MV-1', 'price' => 14, 'chairs_per_unit' => 0, 'deleted_at' => null],
            ['name' => 'Jogo completo', 'category' => 'mobiliario', 'mv_code' => null, 'price' => 7.5, 'chairs_per_unit' => 4, 'deleted_at' => null],
            ['name' => 'Item retirado', 'category' => 'bebida', 'mv_code' => null, 'price' => 1, 'chairs_per_unit' => 0, 'deleted_at' => '2026-10-15 10:00:00'],
        ]);
    }

    private function copy(array $options = []): \Illuminate\Testing\PendingCommand
    {
        return $this->artisan('social:copy-config', array_merge(['--from' => 'origem', '--to' => 'destino', '--force' => true], $options));
    }

    public function test_copia_grupos_espacos_conflitos_e_catalogo(): void
    {
        $this->copy()
            ->expectsOutputToContain('Imagem não encontrada neste servidor: public/images/nao-existe.png')
            ->assertSuccessful();

        $db = DB::connection('destino');
        $group = $db->table('place_groups')->where('name', 'Churrasqueiras')->first();
        $this->assertSame('social', $group->category);
        $this->assertSame('churras.jpg', $group->image_vertical);
        $this->assertSame(7, (int) $group->social_payment_days);
        $this->assertSame('Crianças até 4 anos não contam.', $group->social_rules);
        $this->assertSame([6, 7], $db->table('week_days_place_group')->where('place_group_id', $group->id)->orderBy('weekday_id')->pluck('weekday_id')->map(fn ($d) => (int) $d)->all());
        // O grupo excluído na origem não vai.
        $this->assertFalse($db->table('place_groups')->where('name', 'Churrasqueira')->exists());

        $places = $db->table('places')->where('place_group_id', $group->id)->get()->keyBy('name');
        $this->assertCount(2, $places);
        $this->assertSame('https://i.imgur.com/abc1234.jpg', $places['Churrasqueira 21']->image);
        $this->assertSame(15, (int) $places['Churrasqueira 21']->max_guests);
        $this->assertNull($places['Churrasqueira 21']->contactor_id); // social não tem contator

        $sauna = $places['Churrasqueira da Sauna']->id;
        $this->assertTrue($db->table('place_conflicts')->where('place_id', $sauna)->where('conflicting_place_id', $places['Churrasqueira 21']->id)->exists());
        // A quadra casou pelo nome (id 30 na origem, 5 no destino).
        $this->assertTrue($db->table('place_conflicts')->where('place_id', $sauna)->where('conflicting_place_id', 5)->exists());

        $this->assertSame(['Coca-Cola 2L', 'Jogo completo'], $db->table('social_beverages')->orderBy('name')->pluck('name')->all());
        $this->assertSame(4, (int) $db->table('social_beverages')->where('name', 'Jogo completo')->value('chairs_per_unit'));
    }

    public function test_rodar_de_novo_atualiza_sem_duplicar(): void
    {
        $this->copy()->assertSuccessful();
        DB::connection('origem')->table('places')->where('id', 37)->update(['price' => 390]);
        DB::connection('origem')->table('social_beverages')->where('name', 'Coca-Cola 2L')->update(['price' => 15]);

        $this->copy()->assertSuccessful();

        $db = DB::connection('destino');
        $this->assertSame(1, $db->table('place_groups')->where('name', 'Churrasqueiras')->count());
        $this->assertSame(2, $db->table('week_days_place_group')->count());
        $this->assertSame(3, $db->table('places')->count()); // 2 sociais + a quadra
        $this->assertSame(2, $db->table('place_conflicts')->count());
        $this->assertSame(2, $db->table('social_beverages')->count());
        $this->assertSame(390.0, (float) $db->table('places')->where('name', 'Churrasqueira 21')->value('price'));
        $this->assertSame(15.0, (float) $db->table('social_beverages')->where('name', 'Coca-Cola 2L')->value('price'));
    }

    public function test_simulacao_nao_escreve_nada(): void
    {
        $this->copy(['--dry-run' => true, '--force' => false])
            ->expectsOutputToContain('Simulação: nada foi gravado')
            ->assertSuccessful();

        $db = DB::connection('destino');
        $this->assertSame(1, $db->table('place_groups')->count());
        $this->assertSame(1, $db->table('places')->count());
        $this->assertSame(0, $db->table('social_beverages')->count());
        $this->assertSame(0, $db->table('place_conflicts')->count());
    }

    public function test_sem_confirmacao_nao_grava(): void
    {
        $this->copy(['--force' => false])
            ->expectsConfirmation('Gravar no banco :memory:?', 'no')
            ->assertFailed();

        $this->assertSame(0, DB::connection('destino')->table('social_beverages')->count());
    }

    public function test_mesmo_banco_e_recusado(): void
    {
        $this->artisan('social:copy-config', ['--from' => 'origem', '--to' => 'origem', '--force' => true])->assertFailed();
    }
}
