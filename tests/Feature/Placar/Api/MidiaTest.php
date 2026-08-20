<?php

namespace Tests\Feature\Placar\Api;

use App\Models\Placar\ApiCliente;
use App\Models\Placar\Equipe;
use App\Models\Placar\Jogador;
use App\Models\Placar\Modalidade;
use App\Models\Placar\Time;
use App\Services\Placar\ImagemService;
use App\Support\Placar\PlacarAbilities;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MigratesPlacarSchema;
use Tests\TestCase;

/**
 * Armazenamento e URL da mídia do Placar.
 *
 * Este projeto NUNCA usou `storage:link`: `public/storage` é um diretório
 * real, com arquivos de outras áreas do sistema, e o symlink do Laravel
 * nunca existiu. Enquanto o Placar gravou no disco `public`
 * (storage/app/public), toda logo/foto ia parar num lugar que nenhuma URL
 * alcançava — o link quebrava em produção. Estes testes travam as duas
 * pontas dessa correção: onde o arquivo é gravado, e como a URL é montada.
 */
class MidiaTest extends TestCase
{
    use MigratesPlacarSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migratePlacarSchema();

        $cliente = ApiCliente::create(['nome' => 'node-teste', 'ativo' => true]);
        Sanctum::actingAs($cliente, [PlacarAbilities::OPERAR]);
    }

    public function test_upload_grava_no_disco_servido_estaticamente_e_nao_no_disco_public(): void
    {
        Storage::fake(ImagemService::DISCO);
        Storage::fake('public');

        $equipe = Equipe::create(['nome' => 'Equipe com Logo', 'ativo' => true]);

        $this->post("/api/placar/equipes/{$equipe->id}/logo", [
            'arquivo' => UploadedFile::fake()->image('logo.png', 800, 600),
        ])->assertOk();

        $caminho = $equipe->fresh()->logo_path;
        $this->assertNotNull($caminho, 'o upload deveria ter gravado um logo_path');

        Storage::disk(ImagemService::DISCO)->assertExists($caminho);
        // O disco `public` depende do symlink que este projeto não tem —
        // se a mídia voltar a cair lá, o link quebra de novo.
        Storage::disk('public')->assertMissing($caminho);
    }

    public function test_url_e_absoluta_e_aponta_para_o_caminho_servido_pelo_servidor_web(): void
    {
        $url = ImagemService::url('placar/jogadores/9/foto.webp');

        $this->assertStringStartsWith('http', $url);
        $this->assertStringEndsWith('/storage/placar/jogadores/9/foto.webp', $url);
    }

    public function test_caminho_nulo_devolve_url_nula_em_vez_de_link_quebrado(): void
    {
        $this->assertNull(ImagemService::url(null));

        $jogador = Jogador::create(['nome' => 'Sem Foto', 'ativo' => true]);
        $this->assertNull($jogador->fotoUrl());
    }

    public function test_time_sem_logo_propria_herda_a_url_da_equipe_ja_no_formato_novo(): void
    {
        $futsal = Modalidade::create(['nome' => 'Futsal', 'slug' => 'futsal', 'ativo' => true]);
        $equipe = Equipe::create([
            'nome' => 'Equipe Herdeira', 'logo_path' => 'placar/equipes/3/logo.png', 'ativo' => true,
        ]);
        $time = Time::create([
            'equipe_id' => $equipe->id, 'modalidade_id' => $futsal->id,
            'categoria' => 'Adulto', 'ativo' => true,
        ]);

        $this->assertStringEndsWith('/storage/placar/equipes/3/logo.png', $time->logoUrl());
    }
}
