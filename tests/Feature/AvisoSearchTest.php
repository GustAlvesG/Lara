<?php

namespace Tests\Feature;

use App\Models\Aviso;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\MigratesAccessSchema;
use Tests\Concerns\SharesSqliteWithUserConnection;
use Tests\TestCase;

/**
 * Busca de avisos da API (GET /api/avisos/search), consumida pela Lara (IA).
 *
 * O que importa aqui é a privacidade: o aviso tem dono e destinatário, e a
 * busca só pode devolver o que foi dirigido a quem pergunta. Por isso o teste
 * usa banco de verdade para usuários e setores (MigratesAccessSchema +
 * SharesSqliteWithUserConnection) — a régua passa por `user_sector`.
 */
class AvisoSearchTest extends TestCase
{
    use MigratesAccessSchema;
    use SharesSqliteWithUserConnection;

    private User $autor;
    private User $colega;
    private User $outroSetor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateAccessSchema();
        $this->shareSqliteWithUserConnection();

        // As migrations dos avisos passam por ALTERs que o SQLite não faz
        // bem; as quatro tabelas que a busca toca vão montadas na mão.
        Schema::create('avisos', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->longText('content')->nullable();
            $table->string('image')->nullable();
            $table->string('privacy')->default('publico');
            $table->boolean('mandatory')->default(false);
            $table->date('expires_at')->nullable();
            $table->boolean('expiry_notified')->default(false);
            $table->unsignedBigInteger('created_by');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->timestamps();
        });

        Schema::create('aviso_tag', function (Blueprint $table) {
            $table->unsignedBigInteger('aviso_id');
            $table->unsignedBigInteger('tag_id');
            $table->primary(['aviso_id', 'tag_id']);
        });

        Schema::create('aviso_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('aviso_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });

        $this->autor = $this->makeUser('Autora RH');
        $this->joinSector($this->autor, 'RH');

        $this->colega = $this->makeUser('Colega RH');
        $this->joinSector($this->colega, 'RH');

        $this->outroSetor = $this->makeUser('Pessoa da Portaria');
        $this->joinSector($this->outroSetor, 'Portaria');
    }

    private function endpoint(string $query): string
    {
        return '/api/avisos/search?' . $query;
    }

    private function search(string $phrase, User $user, string $extra = ''): \Illuminate\Testing\TestResponse
    {
        return $this->getJson(
            $this->endpoint('q=' . urlencode($phrase) . '&user_id=' . $user->id . $extra),
            $this->authHeaders()
        );
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . config('services.api.token')];
    }

    private function createAviso(array $attributes, array $tags = []): Aviso
    {
        $aviso = Aviso::create(array_merge([
            'title'      => 'Sem título',
            'content'    => 'Sem conteúdo',
            'privacy'    => Aviso::PRIVACY_PUBLICO,
            'created_by' => $this->autor->id,
        ], $attributes));

        if ($tags !== []) {
            $aviso->tags()->sync(
                collect($tags)->map(fn ($name) => Tag::firstOrCreate(['name' => Tag::normalize($name)])->id)->all()
            );
        }

        return $aviso;
    }

    public function test_requires_api_token(): void
    {
        $this->getJson($this->endpoint('q=piscina&user_id=' . $this->colega->id))->assertStatus(401);
    }

    public function test_keyword_and_user_are_required(): void
    {
        $this->getJson($this->endpoint('user_id=' . $this->colega->id), $this->authHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.q.0', 'Informe a palavra-chave da busca (q).');

        $this->getJson($this->endpoint('q=piscina'), $this->authHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.user_id.0', 'Informe o usuário que pergunta (user_id).');
    }

    public function test_unknown_or_deleted_user_is_rejected(): void
    {
        $this->getJson($this->endpoint('q=piscina&user_id=999999'), $this->authHeaders())
            ->assertStatus(422)
            ->assertJsonPath('errors.user_id.0', 'Usuário não encontrado.');

        $this->colega->delete();

        $this->search('piscina', $this->colega)
            ->assertStatus(422)
            ->assertJsonPath('errors.user_id.0', 'Usuário não encontrado.');
    }

    public function test_accepts_the_portal_prefixed_id_the_ai_already_knows(): void
    {
        $this->createAviso(['title' => 'Piscina fechada']);

        foreach (['user_id', 'usuario_id'] as $param) {
            $this->getJson(
                $this->endpoint('q=piscina&' . $param . '=portal_' . $this->colega->id),
                $this->authHeaders()
            )
                ->assertOk()
                ->assertJsonPath('user_id', $this->colega->id)
                ->assertJsonPath('total', 1);
        }
    }

    public function test_finds_by_title_content_and_tag_ranked_by_weight(): void
    {
        $this->createAviso(['title' => 'Aardvark', 'content' => 'A piscina abre mais tarde']);
        $this->createAviso(['title' => 'Bocha', 'content' => 'Sem menção'], ['piscina']);
        $this->createAviso(['title' => 'Piscina em manutenção']);
        $this->createAviso(['title' => 'Festa junina', 'content' => 'Quadra coberta']);

        $response = $this->search('piscina', $this->colega)
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonMissing(['title' => 'Festa junina']);

        // Título 3, tag 2, conteúdo 1.
        $response->assertJsonPath('data.0.title', 'Piscina em manutenção');
        $response->assertJsonPath('data.1.title', 'Bocha');
        $response->assertJsonPath('data.2.title', 'Aardvark');
        $response->assertJsonPath('data.1.tags', ['piscina']);
    }

    public function test_returns_plain_text_and_no_private_relations(): void
    {
        $this->createAviso([
            'title'   => 'Piscina',
            'content' => '<p>Fechada <strong>sábado</strong>.</p><p>Traga toalha &amp; touca.</p>',
        ]);

        $response = $this->search('piscina', $this->colega)
            ->assertOk()
            ->assertJsonPath('data.0.content', 'Fechada sábado . Traga toalha & touca.')
            ->assertJsonPath('data.0.author', 'Autora RH')
            ->assertJsonStructure(['data' => [['id', 'title', 'content', 'privacy', 'mandatory', 'expires_at', 'expired', 'created_at', 'author', 'tags', 'url', 'relevance']]]);

        $item = $response->json('data.0');
        $this->assertArrayNotHasKey('users', $item);
        $this->assertArrayNotHasKey('created_by', $item);
    }

    public function test_only_returns_avisos_addressed_to_who_asks(): void
    {
        $this->createAviso(['title' => 'Piscina público']);
        $this->createAviso(['title' => 'Piscina setor RH', 'privacy' => Aviso::PRIVACY_SETOR]);
        $this->createAviso(['title' => 'Piscina pessoal', 'privacy' => Aviso::PRIVACY_PESSOA]);
        $grupo = $this->createAviso(['title' => 'Piscina grupo', 'privacy' => Aviso::PRIVACY_GRUPO]);
        $grupo->users()->attach($this->outroSetor->id);

        $titles = fn (User $user) => collect($this->search('piscina', $user)->assertOk()->json('data'))
            ->pluck('title')->sort()->values()->all();

        $this->assertSame(['Piscina público', 'Piscina setor RH'], $titles($this->colega));
        $this->assertSame(['Piscina grupo', 'Piscina público'], $titles($this->outroSetor));
        $this->assertSame(
            ['Piscina grupo', 'Piscina pessoal', 'Piscina público', 'Piscina setor RH'],
            $titles($this->autor)
        );
    }

    public function test_full_access_does_not_widen_the_search(): void
    {
        // Na tela, TI enxerga todo aviso; na busca da IA, só o dirigido a ela.
        $ti = $this->makeUser('Pessoa da TI');
        $this->joinSector($ti, 'TI');
        $this->assertTrue($ti->hasFullAccess());

        $this->createAviso(['title' => 'Piscina público']);
        $this->createAviso(['title' => 'Piscina pessoal', 'privacy' => Aviso::PRIVACY_PESSOA]);

        $this->search('piscina', $ti)
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.title', 'Piscina público');
    }

    public function test_expired_avisos_only_come_on_request(): void
    {
        $this->createAviso(['title' => 'Piscina vigente', 'expires_at' => today()]);
        $this->createAviso(['title' => 'Piscina vencida', 'expires_at' => today()->subDay()]);

        $this->search('piscina', $this->colega)
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.title', 'Piscina vigente')
            ->assertJsonPath('data.0.expired', false);

        $this->search('piscina', $this->colega, '&include_expired=1')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonFragment(['title' => 'Piscina vencida', 'expired' => true]);
    }

    public function test_ignores_deleted_avisos(): void
    {
        $this->createAviso(['title' => 'Piscina apagada'])->delete();

        $this->search('piscina', $this->colega)
            ->assertOk()
            ->assertJsonPath('total', 0);
    }

    public function test_reduces_a_natural_language_phrase_to_keywords(): void
    {
        $this->createAviso(['title' => 'Horário da piscina no feriado']);

        $this->search('Qual o horário da piscina?', $this->colega)
            ->assertOk()
            ->assertJsonPath('terms', ['horário', 'piscina'])
            ->assertJsonPath('total', 1);
    }
}
