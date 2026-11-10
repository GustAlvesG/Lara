<?php

namespace Tests\Feature\Social;

use App\Authorization\Permissions as P;
use App\Authorization\UserAccess;
use App\Models\Place;
use App\Models\PlaceGroup;
use App\Models\Social\PlacePhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Mockery;
use Tests\Concerns\CreatesSocialBookingSchema;
use Tests\TestCase;

/**
 * Galeria de fotos do espaço social (10/11/2026): o painel grava arquivos
 * estáticos reduzidos em `<raiz>/galeria/{espaço}/` (aqui, uma pasta
 * temporária), e o site recebe os caminhos pela API — pela lista do sócio
 * logado e pela vitrine pública (só `api_token`).
 */
class PlacePhotoGalleryTest extends TestCase
{
    use CreatesSocialBookingSchema;

    private string $root;

    private string $token = 'token-de-teste';

    private PlaceGroup $group;

    private Place $place;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lara-galeria-' . uniqid();
        File::ensureDirectoryExists($this->root);
        config(['social_bookings.gallery_root' => $this->root, 'services.api.token' => $this->token]);

        $this->createSocialBookingSchema();

        $this->group = PlaceGroup::create(['name' => 'Churrasqueiras', 'category' => PlaceGroup::CATEGORY_SOCIAL]);
        $this->place = Place::create(['name' => 'Churrasqueira 21', 'place_group_id' => $this->group->id, 'status_id' => 1, 'price' => 350, 'capacity' => 40, 'max_guests' => 15]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        Mockery::close();
        parent::tearDown();
    }

    private function usuario(array $permissions = [P::RESERVAS_CONFIGURAR]): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('access')->andReturn(new UserAccess($permissions));
        $user->shouldReceive('isCoordinator')->andReturn(false);
        $user->id = 9;
        $user->name = 'Pessoa de Teste';

        return $user;
    }

    private function upload(array $files, ?Place $place = null)
    {
        return $this->actingAs($this->usuario())
            ->post(route('place-group.photos.store', ($place ?? $this->place)->id), ['photos' => $files]);
    }

    private function photos(): \Illuminate\Support\Collection
    {
        return PlacePhoto::where('place_id', $this->place->id)->orderBy('position')->get();
    }

    public function test_envio_reduz_grava_miniatura_e_poe_no_fim(): void
    {
        $this->upload([
            UploadedFile::fake()->image('grande.jpg', 3000, 2000),
            UploadedFile::fake()->image('pequena.png', 800, 600),
        ])->assertRedirect(route('place-group.editPlace', $this->place->id) . '#galeria')
            ->assertSessionHas('success', '2 fotos adicionadas à galeria.');

        $photos = $this->photos();
        $this->assertCount(2, $photos);
        [$big, $small] = $photos;

        $this->assertSame([1, 2], $photos->pluck('position')->all());
        $this->assertSame([1920, 1280], [$big->width, $big->height]);
        $this->assertSame([800, 600], [$small->width, $small->height], 'foto menor não é ampliada');
        $this->assertStringStartsWith('galeria/' . $this->place->id . '/', $big->file);
        $this->assertStringEndsWith('.jpg', $small->file, 'PNG vira JPEG');

        $this->assertFileExists($this->root . '/' . $big->file);
        [$tw, $th] = getimagesize($this->root . '/' . $big->thumb);
        $this->assertSame([480, 320], [$tw, $th]);
        $this->assertSame(IMAGETYPE_JPEG, getimagesize($this->root . '/' . $small->file)[2]);

        // Envio seguinte vai para o fim.
        $this->upload([UploadedFile::fake()->image('c.jpg', 100, 100)]);
        $this->assertSame([1, 2, 3], $this->photos()->pluck('position')->all());
    }

    public function test_arquivo_que_nao_e_imagem_e_recusado_sem_gravar_nada(): void
    {
        $this->upload([
            UploadedFile::fake()->image('ok.jpg', 200, 200),
            UploadedFile::fake()->create('planilha.jpg', 10, 'image/jpeg'),
        ])->assertSessionHasErrors('photos');

        $this->assertCount(0, $this->photos());
        $this->assertSame([], File::allFiles($this->root), 'a foto boa do mesmo envio também é desfeita');
    }

    public function test_espaco_de_quadra_nao_tem_galeria(): void
    {
        $sport = PlaceGroup::create(['name' => 'Tênis', 'category' => PlaceGroup::CATEGORY_SPORT]);
        $court = Place::create(['name' => 'Quadra 1', 'place_group_id' => $sport->id, 'status_id' => 1, 'price' => 50]);

        $this->upload([UploadedFile::fake()->image('q.jpg', 100, 100)], $court)->assertSessionHasErrors('photos');
        $this->assertSame(0, PlacePhoto::count());
    }

    public function test_limite_de_fotos_por_espaco(): void
    {
        for ($i = 1; $i <= PlacePhoto::MAX_PER_PLACE - 1; $i++) {
            PlacePhoto::create(['place_id' => $this->place->id, 'file' => "galeria/x{$i}.jpg", 'thumb' => "galeria/x{$i}-thumb.jpg", 'width' => 1, 'height' => 1, 'position' => $i]);
        }

        $this->upload([UploadedFile::fake()->image('a.jpg', 50, 50), UploadedFile::fake()->image('b.jpg', 50, 50)])
            ->assertSessionHasErrors('photos');
        $this->assertSame(PlacePhoto::MAX_PER_PLACE - 1, PlacePhoto::count());

        $this->upload([UploadedFile::fake()->image('a.jpg', 50, 50)])->assertSessionHasNoErrors();
        $this->assertSame(PlacePhoto::MAX_PER_PLACE, PlacePhoto::count());
    }

    public function test_ordenar_legenda_esconder_capa_e_excluir(): void
    {
        $this->upload([
            UploadedFile::fake()->image('a.jpg', 100, 100),
            UploadedFile::fake()->image('b.jpg', 100, 100),
            UploadedFile::fake()->image('c.jpg', 100, 100),
        ]);
        [$a, $b, $c] = $this->photos();
        $user = $this->usuario();

        $this->actingAs($user)->post(route('place-group.photos.move', $c->id), ['direction' => 'up'])->assertRedirect();
        $this->assertSame([$a->id, $c->id, $b->id], $this->photos()->pluck('id')->all());

        // Primeira para cima: nada muda.
        $this->actingAs($user)->post(route('place-group.photos.move', $a->id), ['direction' => 'up']);
        $this->assertSame([$a->id, $c->id, $b->id], $this->photos()->pluck('id')->all());

        $this->actingAs($user)->put(route('place-group.photos.update', $b->id), ['caption' => '  Vista da piscina  '])->assertRedirect();
        $b->refresh();
        $this->assertSame('Vista da piscina', $b->caption);
        $this->assertFalse($b->active, 'checkbox desmarcado esconde');

        $this->actingAs($user)->post(route('place-group.photos.cover', $a->id))->assertSessionHas('success');
        $cover = $this->place->fresh()->image;
        $this->assertNotSame($a->file, $cover, 'capa é cópia própria');
        $this->assertFileExists($this->root . '/' . $cover);

        $this->actingAs($user)->delete(route('place-group.photos.destroy', $a->id))->assertRedirect();
        $this->assertFileDoesNotExist($this->root . '/' . $a->file);
        $this->assertFileDoesNotExist($this->root . '/' . $a->thumb);
        $this->assertFileExists($this->root . '/' . $cover, 'a capa fica mesmo sem a foto');
        $this->assertSame([[$c->id, 1], [$b->id, 2]], $this->photos()->map(fn ($p) => [$p->id, $p->position])->all());
    }

    public function test_sem_permissao_de_configurar_nao_mexe(): void
    {
        $this->actingAs($this->usuario([P::RESERVAS_SOCIAIS]))
            ->post(route('place-group.photos.store', $this->place->id), ['photos' => [UploadedFile::fake()->image('a.jpg', 50, 50)]])
            ->assertForbidden();

        $this->assertSame(0, PlacePhoto::count());
    }

    public function test_vitrine_publica_mostra_so_o_visivel_em_ordem_sem_login(): void
    {
        $this->upload([UploadedFile::fake()->image('a.jpg', 100, 100), UploadedFile::fake()->image('b.jpg', 100, 100), UploadedFile::fake()->image('c.jpg', 100, 100)]);
        [$a, $b, $c] = $this->photos();
        $b->update(['active' => false, 'caption' => null]);
        $c->update(['caption' => 'Cozinha']);
        $this->place->update(['image' => 'capa.jpg']);

        // Espaço inativo e grupo de quadra ficam de fora; grupo sem espaço ativo também.
        Place::create(['name' => 'Salão fechado', 'place_group_id' => $this->group->id, 'status_id' => 2, 'price' => 1]);
        PlaceGroup::create(['name' => 'Tênis', 'category' => PlaceGroup::CATEGORY_SPORT]);
        PlaceGroup::create(['name' => 'Salões', 'category' => PlaceGroup::CATEGORY_SOCIAL]);

        $this->getJson('/api/social-showcase')->assertUnauthorized();

        $json = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])->getJson('/api/social-showcase')
            ->assertOk()->json();

        $this->assertCount(1, $json['groups']);
        $this->assertSame('Churrasqueiras', $json['groups'][0]['name']);
        $places = $json['groups'][0]['places'];
        $this->assertSame(['Churrasqueira 21'], array_column($places, 'name'));
        $this->assertSame(['path' => 'images/capa.jpg', 'url' => asset('images/capa.jpg'), 'remote' => false], $places[0]['cover']);

        $photos = $places[0]['photos'];
        $this->assertSame([$a->id, $c->id], array_column($photos, 'id'));
        $this->assertSame('images/' . $a->file, $photos[0]['path']);
        $this->assertSame('images/' . $a->thumb, $photos[0]['thumb_path']);
        $this->assertSame(asset('images/' . $a->file), $photos[0]['url']);
        $this->assertSame('Cozinha', $photos[1]['caption']);
        $this->assertSame([100, 100], [$photos[0]['width'], $photos[0]['height']]);

        $one = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])->getJson('/api/social-showcase/places/' . $this->place->id)
            ->assertOk()->json();
        $this->assertSame(['id' => $this->group->id, 'name' => 'Churrasqueiras'], $one['group']);
        $this->assertCount(2, $one['photos']);
    }

    public function test_capa_por_link_vem_como_remota(): void
    {
        $this->place->update(['image' => 'https://i.imgur.com/abc123.jpg']);

        $cover = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson('/api/social-showcase/places/' . $this->place->id)->json('cover');

        $this->assertSame(['path' => null, 'url' => 'https://i.imgur.com/abc123.jpg', 'remote' => true], $cover);
    }

    public function test_vitrine_nao_mostra_quadra(): void
    {
        $sport = PlaceGroup::create(['name' => 'Tênis', 'category' => PlaceGroup::CATEGORY_SPORT]);
        $court = Place::create(['name' => 'Quadra 1', 'place_group_id' => $sport->id, 'status_id' => 1, 'price' => 50]);

        $this->withHeaders(['Authorization' => 'Bearer ' . $this->token])
            ->getJson('/api/social-showcase/places/' . $court->id)->assertNotFound();
    }
}
