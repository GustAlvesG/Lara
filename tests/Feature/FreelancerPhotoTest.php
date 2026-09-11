<?php

namespace Tests\Feature;

use App\Models\Freelancer;
use App\Services\CompanyService;
use App\Services\FreelancerService as FreelancerServiceManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesFreelancerPixSchema;
use Tests\TestCase;

/**
 * Foto de identificação do freelancer: o que o formulário grava, o que a
 * portaria recebe e a migração das fotos que estavam no cadastro de
 * terceirizado (`freelancers:migrar-fotos`).
 *
 * O `public_path` aponta para um diretório temporário: os testes gravam e
 * conferem arquivos, e não podem deixar lixo em `public/images`.
 */
class FreelancerPhotoTest extends TestCase
{
    use CreatesFreelancerPixSchema;

    /** PNG 1×1 válido. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    /** CPF válido — a consulta da portaria confere os dígitos verificadores. */
    private const CPF = '52998224725';

    private string $publico;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createFreelancerPixSchema();

        Schema::withoutForeignKeyConstraints(function () {
            (require base_path('database/migrations/2026_01_14_151220_create_companies_table.php'))->up();
            (require base_path('database/migrations/2026_01_14_151348_create_company_workers_table.php'))->up();
            // A consulta por CPF da portaria também procura a liberação pontual do dia.
            (require base_path('database/migrations/2026_09_11_140000_create_one_off_accesses_table.php'))->up();
        });

        $this->publico = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lpr-freelancer-photo-' . uniqid();
        File::ensureDirectoryExists($this->publico . DIRECTORY_SEPARATOR . 'images');
        $this->app->usePublicPath($this->publico);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->publico);

        parent::tearDown();
    }

    private function freelancer(string $name = 'Ana', ?string $image = null, string $cpf = self::CPF): Freelancer
    {
        return Freelancer::create(['name' => $name, 'cpf' => $cpf, 'image' => $image]);
    }

    /** Terceirizado com foto; o arquivo é criado em `public/images` salvo pedido em contrário. */
    private function terceirizado(string $document, string $image, array $extra = [], bool $comArquivo = true): int
    {
        $companyId = DB::table('companies')->where('name', 'Parceira')->value('id')
            ?? DB::table('companies')->insertGetId(['name' => 'Parceira', 'created_at' => now(), 'updated_at' => now()]);

        if ($comArquivo) {
            file_put_contents(public_path('images/' . $image), base64_decode(self::PNG));
        }

        return DB::table('company_workers')->insertGetId(array_merge([
            'name' => 'Ana Terceirizada',
            'document' => $document,
            'image' => $image,
            'company_id' => $companyId,
            'created_at' => now(),
            'updated_at' => now(),
        ], $extra));
    }

    private function migrar(array $opcoes = []): int
    {
        return $this->artisan('freelancers:migrar-fotos', $opcoes)->run();
    }

    /* ---------------------------------------------------------------------
     | Migração das fotos
     |---------------------------------------------------------------------*/

    /** O documento do terceirizado com máscara casa com o CPF só de dígitos. */
    public function test_copia_o_caminho_da_foto_do_terceirizado_de_mesmo_cpf(): void
    {
        $freelancer = $this->freelancer();
        $this->terceirizado('529.982.247-25', 'worker_1.jpg');

        $this->assertSame(0, $this->migrar());

        $this->assertSame('worker_1.jpg', $freelancer->fresh()->image);
    }

    public function test_dry_run_nao_grava(): void
    {
        $freelancer = $this->freelancer();
        $this->terceirizado(self::CPF, 'worker_1.jpg');

        $this->migrar(['--dry-run' => true]);

        $this->assertNull($freelancer->fresh()->image);
    }

    /** Rodar de novo não desfaz uma foto tirada depois pelo formulário. */
    public function test_nao_sobrescreve_quem_ja_tem_foto_sem_a_opcao(): void
    {
        $freelancer = $this->freelancer(image: 'freelancer_nova.jpg');
        $this->terceirizado(self::CPF, 'worker_1.jpg');

        $this->migrar();
        $this->assertSame('freelancer_nova.jpg', $freelancer->fresh()->image);

        $this->migrar(['--sobrescrever' => true]);
        $this->assertSame('worker_1.jpg', $freelancer->fresh()->image);
    }

    /** Caminho para arquivo inexistente seria uma imagem quebrada na portaria. */
    public function test_nao_grava_quando_o_arquivo_nao_existe(): void
    {
        $freelancer = $this->freelancer();
        $this->terceirizado(self::CPF, 'worker_sumiu.jpg', comArquivo: false);

        $this->migrar();

        $this->assertNull($freelancer->fresh()->image);
    }

    /** Dois cadastros de terceirizado: vale o ativo, e entre ativos o mais recente. */
    public function test_prefere_o_cadastro_ativo_e_mais_recente(): void
    {
        $freelancer = $this->freelancer();
        $this->terceirizado(self::CPF, 'worker_excluido.jpg', ['updated_at' => now(), 'deleted_at' => now()]);
        $this->terceirizado(self::CPF, 'worker_antigo.jpg', ['updated_at' => now()->subYear()]);
        $this->terceirizado(self::CPF, 'worker_recente.jpg', ['updated_at' => now()->subDay()]);

        $this->migrar();

        $this->assertSame('worker_recente.jpg', $freelancer->fresh()->image);
    }

    /** Terceirizado excluído ainda é a foto da pessoa — serve quando é a única. */
    public function test_usa_o_cadastro_excluido_quando_e_o_unico(): void
    {
        $freelancer = $this->freelancer();
        $this->terceirizado(self::CPF, 'worker_excluido.jpg', ['deleted_at' => now()]);

        $this->migrar();

        $this->assertSame('worker_excluido.jpg', $freelancer->fresh()->image);
    }

    public function test_cpf_diferente_nao_leva_foto(): void
    {
        $freelancer = $this->freelancer();
        $this->terceirizado('11144477735', 'worker_outro.jpg');

        $this->migrar();

        $this->assertNull($freelancer->fresh()->image);
    }

    /* ---------------------------------------------------------------------
     | Gravação pelo formulário
     |---------------------------------------------------------------------*/

    public function test_foto_do_formulario_vira_arquivo_em_public_images(): void
    {
        $freelancer = app(FreelancerServiceManager::class)->create([
            'name' => 'Ana',
            'cpf' => self::CPF,
            'image' => 'data:image/png;base64,' . self::PNG,
        ]);

        $this->assertMatchesRegularExpression('/^freelancer_[0-9a-f-]{36}\.png$/', $freelancer->image);
        $this->assertFileExists(public_path('images/' . $freelancer->image));
        $this->assertSame(asset('images/' . $freelancer->image), $freelancer->imageUrl());
    }

    /** O formulário manda o campo vazio quando ninguém mexeu na câmera. */
    public function test_atualizar_sem_foto_nova_mantem_a_atual(): void
    {
        $freelancer = $this->freelancer(image: 'worker_1.jpg');

        app(FreelancerServiceManager::class)->updateFreelancer($freelancer, [
            'name' => 'Ana Maria',
            'cpf' => self::CPF,
            'image' => null,
        ]);

        $this->assertSame('worker_1.jpg', $freelancer->fresh()->image);
        $this->assertSame('Ana Maria', $freelancer->fresh()->name);
    }

    /** O cabeçalho do data URL passa na validação; os bytes é que são conferidos. */
    public function test_conteudo_que_nao_e_imagem_e_recusado(): void
    {
        $freelancer = $this->freelancer(image: 'worker_1.jpg');

        try {
            app(FreelancerServiceManager::class)->updateFreelancer($freelancer, [
                'name' => 'Ana',
                'cpf' => self::CPF,
                'image' => 'data:image/png;base64,' . base64_encode('<?php echo 1;'),
            ]);
            $this->fail('Conteúdo que não é imagem foi aceito.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('image', $e->errors());
        }

        $this->assertSame('worker_1.jpg', $freelancer->fresh()->image);
        $this->assertSame([], File::files(public_path('images')));
    }

    /* ---------------------------------------------------------------------
     | Portaria
     |---------------------------------------------------------------------*/

    public function test_monitor_de_acesso_recebe_a_foto_do_freelancer(): void
    {
        $this->freelancer(image: 'worker_1.jpg');

        $result = app(CompanyService::class)->validateTryToAccess(['target' => '529.982.247-25']);

        $this->assertTrue($result['found']);
        $this->assertSame('freelancer', $result['workers'][0]['type']);
        $this->assertSame(asset('images/worker_1.jpg'), $result['workers'][0]['image']);
    }

    public function test_monitor_de_acesso_sem_foto_manda_nulo(): void
    {
        $this->freelancer();

        $result = app(CompanyService::class)->validateTryToAccess(['target' => self::CPF]);

        $this->assertNull($result['workers'][0]['image']);
    }
}
