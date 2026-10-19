<?php

namespace Tests\Feature\Locations;

use App\Authorization\Permissions as P;
use App\Authorization\UserAccess;
use App\Models\Place;
use App\Models\PlaceGroup;
use App\Models\User;
use App\Support\ImageSource;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\Concerns\CreatesSocialBookingSchema;
use Tests\TestCase;

/**
 * Imagem de grupo e de espaço por arquivo ou por link web.
 *
 * O link precisa ser da imagem; as páginas do imgur e do postimg (o que as
 * pessoas costumam copiar) viram o link direto. Upload vai para um
 * public/ temporário: o teste não suja public/images.
 */
class PlaceImageSourceTest extends TestCase
{
    use CreatesSocialBookingSchema;

    private string $public;

    protected function setUp(): void
    {
        parent::setUp();

        $this->public = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lara-public-' . uniqid();
        File::ensureDirectoryExists($this->public . DIRECTORY_SEPARATOR . 'images');
        $this->app->usePublicPath($this->public);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->public);
        Mockery::close();
        parent::tearDown();
    }

    private function refused(string $link, string $needle): void
    {
        try {
            ImageSource::normalizeLink($link, 'image_url');
            $this->fail("Esperava recusa para {$link}");
        } catch (ValidationException $e) {
            $this->assertStringContainsString($needle, $e->errors()['image_url'][0]);
        }
    }

    public function test_links_aceitos_e_convertidos(): void
    {
        $this->assertSame('https://i.imgur.com/abc1234.jpg', ImageSource::normalizeLink('https://imgur.com/abc1234'));
        $this->assertSame('https://i.imgur.com/abc1234.jpg', ImageSource::normalizeLink('https://imgur.com/abc1234.png'));
        $this->assertSame('https://i.imgur.com/abc1234.png', ImageSource::normalizeLink('https://i.imgur.com/abc1234.png'));
        $this->assertSame('https://i.postimg.cc/Xy12/churrasqueira.jpg', ImageSource::normalizeLink('https://i.postimg.cc/Xy12/churrasqueira.jpg'));
        $this->assertSame('https://cdn.clube.com.br/salao.webp?v=2', ImageSource::normalizeLink('https://cdn.clube.com.br/salao.webp?v=2'));
    }

    public function test_pagina_do_postimg_vira_o_link_direto(): void
    {
        Http::fake([
            'postimg.cc/*' => Http::response('<html><head><meta property="og:image" content="https://i.postimg.cc/Xy12/salao-estrela.jpg"></head></html>'),
        ]);

        $this->assertSame('https://i.postimg.cc/Xy12/salao-estrela.jpg', ImageSource::normalizeLink('https://postimg.cc/Xy12AbCd'));
    }

    public function test_links_recusados(): void
    {
        $this->refused('imgur.com/abc1234', 'https://');
        $this->refused('http://i.imgur.com/abc1234.jpg', 'https://');
        $this->refused('https://imgur.com/a/abc1234', 'álbum');
        $this->refused('https://www.google.com/search?q=churrasqueira', 'link direto');
        $this->refused('https://cdn.clube.com.br/' . str_repeat('a', 260) . '.jpg', 'longo demais');

        Http::fake(['postimg.cc/*' => Http::response('<html>sem imagem</html>')]);
        $this->refused('https://postimg.cc/Xy12AbCd', 'link direto');
    }

    public function test_url_de_exibicao(): void
    {
        $this->assertNull(ImageSource::url(null));
        $this->assertSame('https://i.imgur.com/abc1234.jpg', ImageSource::url('https://i.imgur.com/abc1234.jpg'));
        $this->assertSame(asset('images/quadra.jpg'), ImageSource::url('quadra.jpg'));
    }

    public function test_arquivo_vence_link_e_sem_nenhum_fica_o_atual(): void
    {
        $withFile = Request::create('/', 'POST', ['image_url' => 'https://i.imgur.com/abc1234.jpg'], [], ['image' => UploadedFile::fake()->image('foto.jpg')]);
        $name = ImageSource::resolve($withFile, 'image', 'antiga.jpg');
        $this->assertFalse(ImageSource::isRemote($name));
        $this->assertFileExists($this->public . '/images/' . $name);

        $withLink = Request::create('/', 'POST', ['image_url' => ' https://i.imgur.com/abc1234.jpg ']);
        $this->assertSame('https://i.imgur.com/abc1234.jpg', ImageSource::resolve($withLink, 'image', 'antiga.jpg'));

        $this->assertSame('antiga.jpg', ImageSource::resolve(Request::create('/', 'POST'), 'image', 'antiga.jpg'));

        $notImage = Request::create('/', 'POST', [], [], ['image' => UploadedFile::fake()->create('planilha.pdf', 10, 'application/pdf')]);
        $this->expectException(ValidationException::class);
        ImageSource::resolve($notImage, 'image');
    }

    private function configurador(): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('access')->andReturn(new UserAccess([P::RESERVAS_CONFIGURAR]));
        $user->shouldReceive('isCoordinator')->andReturn(false);
        $user->id = 9;

        return $user;
    }

    public function test_cadastro_do_espaco_e_do_grupo_por_link_e_por_arquivo(): void
    {
        $this->createSocialBookingSchema();
        $group = PlaceGroup::create(['name' => 'Tênis', 'category' => PlaceGroup::CATEGORY_SPORT, 'image_vertical' => 'v-antiga.jpg', 'image_horizontal' => 'h-antiga.jpg']);

        $this->actingAs($this->configurador())->post(route('place-group.storePlace'), [
            'name' => 'Quadra 1', 'place_group_id' => $group->id, 'price' => 80, 'status_id' => 1,
            'image_url' => 'https://imgur.com/abc1234',
        ])->assertRedirect();
        $place = Place::where('name', 'Quadra 1')->firstOrFail();
        $this->assertSame('https://i.imgur.com/abc1234.jpg', $place->image);

        // Editar sem arquivo nem link mantém a imagem.
        $this->actingAs($this->configurador())->put(route('place-group.updatePlace', $place->id), [
            'name' => 'Quadra 1', 'price' => 80, 'status_id' => 1,
        ])->assertRedirect();
        $this->assertSame('https://i.imgur.com/abc1234.jpg', $place->fresh()->image);

        // Link inválido volta ao formulário com o erro no campo.
        $this->actingAs($this->configurador())->from(route('place-group.editPlace', $place->id))
            ->put(route('place-group.updatePlace', $place->id), ['name' => 'Quadra 1', 'price' => 80, 'status_id' => 1, 'image_url' => 'https://exemplo.com/pagina'])
            ->assertSessionHasErrors('image_url');
        $this->assertSame('https://i.imgur.com/abc1234.jpg', $place->fresh()->image);

        // Grupo: vertical por arquivo e horizontal por link, no mesmo envio.
        $this->actingAs($this->configurador())->put(route('place-group.update', $group->id), [
            'name' => 'Tênis', 'category' => PlaceGroup::CATEGORY_SPORT, 'weekdays' => [1, 2],
            'image_vertical' => UploadedFile::fake()->image('vertical.png'),
            'image_horizontal_url' => 'https://i.postimg.cc/Xy12/tenis.jpg',
        ])->assertRedirect(route('place-group.index'));

        $group->refresh();
        $this->assertFileExists($this->public . '/images/' . $group->image_vertical);
        $this->assertNotSame('v-antiga.jpg', $group->image_vertical);
        $this->assertSame('https://i.postimg.cc/Xy12/tenis.jpg', $group->image_horizontal);
    }
}
