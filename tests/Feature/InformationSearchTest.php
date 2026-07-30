<?php

namespace Tests\Feature;

use App\Models\DataInfo;
use App\Models\Information;
use App\Models\Tag;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InformationSearchTest extends TestCase
{
    /**
     * O banco de testes é SQLite :memory: e nasce vazio a cada teste, mas a
     * suíte de migrações não roda aqui (2025_03_07_083719_add_columns_member
     * recria `members.title` e aborta). Como este endpoint só toca três
     * tabelas, elas são montadas na mão — sem depender da suíte inteira.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        Schema::create('information', function (Blueprint $table) {
            $table->id();
            $table->string('privacy')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('data_infos', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->longText('description');
            $table->string('category')->nullable();
            $table->unsignedBigInteger('information_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->softDeletes();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->timestamps();
        });

        Schema::create('data_info_tag', function (Blueprint $table) {
            $table->unsignedBigInteger('data_info_id');
            $table->unsignedBigInteger('tag_id');
            $table->primary(['data_info_id', 'tag_id']);
        });
    }

    private function endpoint(string $query = ''): string
    {
        return '/api/information/search' . ($query ? '?' . $query : '');
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . config('services.api.token')];
    }

    private function createInfo(array $attributes, array $tags = []): DataInfo
    {
        $information = Information::create(['privacy' => '0']);

        $dataInfo = DataInfo::create(array_merge([
            'name'            => 'Sem nome',
            'description'     => 'Sem descrição',
            'category'        => 'Geral',
            'information_id'  => $information->id,
        ], $attributes));

        if ($tags !== []) {
            $dataInfo->tags()->sync(
                collect($tags)->map(fn ($name) => Tag::firstOrCreate(['name' => Tag::normalize($name)])->id)->all()
            );
        }

        return $dataInfo;
    }

    public function test_requires_api_token(): void
    {
        $this->getJson($this->endpoint('q=natacao'))->assertStatus(401);
    }

    public function test_keyword_is_required(): void
    {
        $this->getJson($this->endpoint(), $this->authHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.q.0', 'Informe a palavra-chave da busca (q).');
    }

    public function test_finds_by_name_description_and_category(): void
    {
        $this->createInfo(['name' => 'Aula de Natação']);
        $this->createInfo(['name' => 'Hidroginástica', 'description' => 'Atividade na piscina de natação']);
        $this->createInfo(['name' => 'Futebol', 'category' => 'Natação e piscina']);
        $this->createInfo(['name' => 'Bocha', 'description' => 'Quadra coberta', 'category' => 'Esportes']);

        $response = $this->getJson($this->endpoint('q=natacao'), $this->authHeaders())
            ->assertOk();

        // A busca é um LIKE simples, então acento depende do collation: no
        // SQLite dos testes "natacao" não casa; no MySQL (utf8mb4_*_ci) casa.
        $response->assertJsonPath('total', 0);

        $this->getJson($this->endpoint('q=' . urlencode('natação')), $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonMissing(['name' => 'Bocha']);
    }

    public function test_returns_the_information_and_the_user_of_each_match(): void
    {
        $this->createInfo(['name' => 'Aula de Tênis']);

        $this->getJson($this->endpoint('q=' . urlencode('Tênis')), $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.name', 'Aula de Tênis')
            ->assertJsonPath('data.0.information.privacy', '0')
            ->assertJsonStructure(['keyword', 'all_versions', 'total', 'data' => [['id', 'name', 'description', 'category', 'information_id', 'information' => ['id']]]]);
    }

    public function test_returns_only_the_current_version_by_default(): void
    {
        $current = $this->createInfo(['name' => 'Aula de Judô']);

        // Nova versão da mesma informação, como faz InformationController::update.
        DataInfo::create(array_merge($current->toArray(), ['name' => 'Aula de Judô Infantil']));

        $response = $this->getJson($this->endpoint('q=' . urlencode('Judô')), $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('total', 1);

        $response->assertJsonPath('data.0.name', 'Aula de Judô Infantil');
    }

    public function test_all_versions_returns_the_whole_history(): void
    {
        $current = $this->createInfo(['name' => 'Aula de Judô']);
        DataInfo::create(array_merge($current->toArray(), ['name' => 'Aula de Judô Infantil']));

        $this->getJson($this->endpoint('q=' . urlencode('Judô') . '&all_versions=1'), $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('total', 2);
    }

    public function test_ignores_soft_deleted_records(): void
    {
        $deletedData = $this->createInfo(['name' => 'Aula de Vôlei']);
        $deletedData->delete();

        $orphan = $this->createInfo(['name' => 'Aula de Vôlei de Praia']);
        $orphan->information->delete();

        $this->getJson($this->endpoint('q=' . urlencode('Vôlei')), $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_reduces_a_natural_language_phrase_to_keywords(): void
    {
        $this->createInfo(['name' => 'Aula de Natação']);
        $this->createInfo(['name' => 'Hidroginástica', 'description' => 'Atividade na piscina de natação']);
        $this->createInfo(['name' => 'Bocha', 'description' => 'Quadra coberta', 'category' => 'Esportes']);

        $phrase = 'Qual o custo da aula de natação?';

        $this->getJson($this->endpoint('q=' . urlencode($phrase)), $this->authHeaders())
            ->assertOk()
            // "qual", "o", "da" e "de" são stopwords (config/search.php) e caem
            // fora; "custo" sobra como termo mas não casa em nenhum registro.
            ->assertJsonPath('terms', ['custo', 'aula', 'natação'])
            ->assertJsonPath('total', 2)
            ->assertJsonMissing(['name' => 'Bocha']);
    }

    public function test_ranks_a_name_match_above_a_description_only_match(): void
    {
        // Sem ranqueamento, a ordem alfabética colocaria "Aardvark" primeiro;
        // com peso por coluna, casar no nome (peso 3) supera casar só na
        // descrição (peso 1).
        $this->createInfo(['name' => 'Aardvark', 'description' => 'Gosta de observar aula de natação']);
        $this->createInfo(['name' => 'Zebra Natação']);

        $response = $this->getJson($this->endpoint('q=' . urlencode('natação')), $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('total', 2);

        $response->assertJsonPath('data.0.name', 'Zebra Natação');
        $response->assertJsonPath('data.1.name', 'Aardvark');
    }

    public function test_breaks_relevance_ties_by_recency(): void
    {
        $older = $this->createInfo(['name' => 'Aula de Escalada']);
        DataInfo::whereKey($older->id)->update(['created_at' => now()->subDay()]);

        $this->createInfo(['name' => 'Escalada Avançada']);

        // Ambos casam só no nome com o mesmo termo — mesma relevância (3).
        $response = $this->getJson($this->endpoint('q=' . urlencode('escalada')), $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('data.0.relevance', 3)
            ->assertJsonPath('data.1.relevance', 3);

        $response->assertJsonPath('data.0.name', 'Escalada Avançada');
        $response->assertJsonPath('data.1.name', 'Aula de Escalada');
    }

    public function test_finds_by_tag(): void
    {
        $this->createInfo(['name' => 'Aula de Remo', 'description' => 'Sem menção ao termo'], ['natação', 'aquático', 'piscina']);
        $this->createInfo(['name' => 'Bocha', 'description' => 'Quadra coberta'], ['esportes', 'quadra', 'lazer']);

        $this->getJson($this->endpoint('q=' . urlencode('natação')), $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.name', 'Aula de Remo');
    }

    public function test_ranks_a_name_match_above_a_tag_only_match(): void
    {
        // Nome pesa 3; tag pesa 2. "Aardvark" casa só pela tag.
        $this->createInfo(['name' => 'Aardvark', 'description' => 'Sem menção'], ['natação', 'a', 'b']);
        $this->createInfo(['name' => 'Zebra Natação'], ['esportes', 'c', 'd']);

        $response = $this->getJson($this->endpoint('q=' . urlencode('natação')), $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('total', 2);

        $response->assertJsonPath('data.0.name', 'Zebra Natação');
        $response->assertJsonPath('data.1.name', 'Aardvark');
    }

    public function test_strips_html_from_the_description(): void
    {
        $this->createInfo([
            'name' => 'Aula de Natação',
            'description' => '<p>Aula de <strong>natação</strong> para iniciantes.</p><p>Traga toalha &amp; touca.</p>',
        ]);

        $response = $this->getJson($this->endpoint('q=' . urlencode('natação')), $this->authHeaders())
            ->assertOk();

        $response->assertJsonPath(
            'data.0.description',
            'Aula de natação para iniciantes. Traga toalha & touca.'
        );
    }
}
