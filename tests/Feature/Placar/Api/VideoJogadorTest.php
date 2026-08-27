<?php

namespace Tests\Feature\Placar\Api;

use App\Models\Placar\ApiCliente;
use App\Models\Placar\Elenco;
use App\Models\Placar\Equipe;
use App\Models\Placar\Jogador;
use App\Models\Placar\Jogo;
use App\Models\Placar\Modalidade;
use App\Models\Placar\Time;
use App\Services\Placar\ImagemService;
use App\Services\Placar\VideoService;
use App\Support\Placar\PlacarAbilities;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MigratesPlacarSchema;
use Tests\TestCase;

/**
 * Vídeo de apresentação do jogador — o telão usa foto e vídeo em momentos
 * diferentes, então os dois convivem no cadastro e no payload do jogo.
 *
 * Sem transcodificação (exigiria ffmpeg, dependência que o projeto não
 * tem): o formato é restrito ao que o navegador toca nativamente e é
 * conferido pelos bytes reais do container, não pela extensão do nome nem
 * pelo Content-Type — os dois são declarados pelo cliente.
 */
class VideoJogadorTest extends TestCase
{
    use MigratesPlacarSchema;

    private Jogador $jogador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migratePlacarSchema();

        $cliente = ApiCliente::create(['nome' => 'node-teste', 'ativo' => true]);
        Sanctum::actingAs($cliente, [PlacarAbilities::OPERAR]);

        $this->jogador = Jogador::create(['nome' => 'Craque do Vídeo', 'ativo' => true]);
    }

    /** Cabeçalho MP4 real: box `ftyp` nos bytes 4..7. */
    private function mp4(string $nome = 'entrada.mp4'): UploadedFile
    {
        $bytes = "\x00\x00\x00\x20" . 'ftypisom' . str_repeat("\x00", 64);

        return UploadedFile::fake()->createWithContent($nome, $bytes);
    }

    /** Cabeçalho WebM real: EBML magic 1A 45 DF A3. */
    private function webm(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('entrada.webm', "\x1A\x45\xDF\xA3" . str_repeat("\x00", 64));
    }

    public function test_envia_mp4_e_devolve_a_url_do_video(): void
    {
        Storage::fake(ImagemService::DISCO);

        $resposta = $this->post("/api/placar/jogadores/{$this->jogador->id}/video", [
            'video' => $this->mp4(),
        ])->assertOk();

        $caminho = $this->jogador->fresh()->video_path;
        $this->assertSame("placar/jogadores/{$this->jogador->id}/video.mp4", $caminho);
        Storage::disk(ImagemService::DISCO)->assertExists($caminho);

        $this->assertStringEndsWith("/storage/{$caminho}", $resposta->json('video_url'));
    }

    public function test_aceita_webm(): void
    {
        Storage::fake(ImagemService::DISCO);

        $this->post("/api/placar/jogadores/{$this->jogador->id}/video", ['video' => $this->webm()])
            ->assertOk();

        $this->assertStringEndsWith('.webm', $this->jogador->fresh()->video_path);
    }

    /**
     * Um .mp4 no nome não prova nada: se os bytes não forem de vídeo, o
     * telão receberia um arquivo que não consegue reproduzir.
     */
    public function test_recusa_arquivo_que_nao_e_video_mesmo_com_extensao_de_video(): void
    {
        Storage::fake(ImagemService::DISCO);

        $falso = UploadedFile::fake()->createWithContent('nao-e-video.mp4', 'isto aqui e texto puro');

        $this->post("/api/placar/jogadores/{$this->jogador->id}/video", ['video' => $falso])
            ->assertStatus(422);

        $this->assertNull($this->jogador->fresh()->video_path);
    }

    public function test_recusa_video_acima_do_limite(): void
    {
        Storage::fake(ImagemService::DISCO);

        $grande = UploadedFile::fake()->create('gigante.mp4', (VideoService::TAMANHO_MAXIMO_BYTES / 1024) + 1024);

        $this->post("/api/placar/jogadores/{$this->jogador->id}/video", ['video' => $grande])
            ->assertStatus(422);

        $this->assertNull($this->jogador->fresh()->video_path);
    }

    public function test_enviar_outro_video_substitui_o_anterior_inclusive_trocando_de_formato(): void
    {
        Storage::fake(ImagemService::DISCO);

        $this->post("/api/placar/jogadores/{$this->jogador->id}/video", ['video' => $this->mp4()])->assertOk();
        $primeiro = $this->jogador->fresh()->video_path;

        $this->post("/api/placar/jogadores/{$this->jogador->id}/video", ['video' => $this->webm()])->assertOk();
        $segundo = $this->jogador->fresh()->video_path;

        $this->assertNotSame($primeiro, $segundo);
        // O antigo não pode ficar órfão ocupando disco.
        Storage::disk(ImagemService::DISCO)->assertMissing($primeiro);
        Storage::disk(ImagemService::DISCO)->assertExists($segundo);
    }

    public function test_remove_o_video_sem_mexer_na_foto(): void
    {
        Storage::fake(ImagemService::DISCO);

        $this->post("/api/placar/jogadores/{$this->jogador->id}/foto", [
            'arquivo' => UploadedFile::fake()->image('foto.jpg', 400, 400),
        ])->assertOk();
        $this->post("/api/placar/jogadores/{$this->jogador->id}/video", ['video' => $this->mp4()])->assertOk();

        $this->delete("/api/placar/jogadores/{$this->jogador->id}/video")->assertOk();

        $jogador = $this->jogador->fresh();
        $this->assertNull($jogador->video_path);
        // Foto e vídeo são independentes — remover um não pode levar o outro.
        $this->assertNotNull($jogador->foto_path);
    }

    /**
     * O Node monta a entrada em quadra a partir do payload do jogo, então
     * o vídeo precisa vir junto com a foto — sem uma segunda chamada.
     */
    public function test_payload_do_jogo_traz_foto_e_video_do_elenco(): void
    {
        Storage::fake(ImagemService::DISCO);

        $futsal = Modalidade::create(['nome' => 'Futsal', 'slug' => 'futsal', 'ativo' => true]);
        $equipeA = Equipe::create(['nome' => 'Equipe A', 'ativo' => true]);
        $equipeB = Equipe::create(['nome' => 'Equipe B', 'ativo' => true]);
        $casa = Time::create(['equipe_id' => $equipeA->id, 'modalidade_id' => $futsal->id, 'categoria' => 'Adulto', 'ativo' => true]);
        $fora = Time::create(['equipe_id' => $equipeB->id, 'modalidade_id' => $futsal->id, 'categoria' => 'Adulto', 'ativo' => true]);

        Elenco::create([
            'time_id' => $casa->id, 'jogador_id' => $this->jogador->id,
            'temporada' => now()->year, 'numero' => '10', 'ativo' => true,
        ]);

        $this->post("/api/placar/jogadores/{$this->jogador->id}/video", ['video' => $this->mp4()])->assertOk();

        $jogo = Jogo::create([
            'modalidade_id' => $futsal->id, 'time_casa_id' => $casa->id, 'time_fora_id' => $fora->id,
            'data_hora' => now(), 'status' => Jogo::STATUS_AGENDADO, 'criado_em_campo' => false,
        ]);

        $elenco = $this->getJson("/api/placar/jogos/{$jogo->id}")->assertOk()->json('time_casa.elenco.0');

        $this->assertStringEndsWith('.mp4', $elenco['video_url']);
        $this->assertArrayHasKey('foto_url', $elenco);
    }

    public function test_jogador_sem_video_devolve_null(): void
    {
        $this->assertNull($this->jogador->videoUrl());
    }
}
