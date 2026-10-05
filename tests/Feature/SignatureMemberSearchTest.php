<?php

namespace Tests\Feature;

use App\Services\Signature\SignatureMemberDirectory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\Concerns\MocksSignatureUser;
use Tests\Concerns\SharesSqliteWithUserConnection;
use Tests\TestCase;

/**
 * Busca de associado no formulário do documento.
 *
 * A tabela local só tem quem se cadastrou no aplicativo; a família de um
 * título — titular e dependentes — vem do MultiClubes. O MultiClubes é
 * simulado aqui: o que se testa é a junção dos dois lados.
 */
class SignatureMemberSearchTest extends TestCase
{
    use CreatesSignatureSchema;
    use MocksSignatureUser;
    use SharesSqliteWithUserConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSignatureSchema();
        $this->createPermissionSchema();

        // O model Member é preso à conexão `mysql`, como o User.
        $this->shareSqliteWithUserConnection();

        Schema::create('members', function (Blueprint $table) {
            $table->increments('Id');
            $table->string('Name');
            $table->string('title');
            $table->string('cpf');
            $table->string('Email')->nullable();
            $table->string('telephone')->nullable();
            $table->timestamps();
        });

        // Só o titular se cadastrou no aplicativo — com máscara no CPF, como acontece.
        DB::table('members')->insert([
            'Name' => 'Carlos Titular', 'title' => '12345', 'cpf' => '123.456.789-09',
            'Email' => 'carlos@app.test', 'telephone' => '24999990000',
        ]);
    }

    /** @param list<array<string, mixed>> $familia */
    private function multiclubes(array $familia, string $codigo = '12345'): void
    {
        $diretorio = Mockery::mock(SignatureMemberDirectory::class)->makePartial();
        $diretorio->shouldReceive('byTitle')->with($codigo)->andReturn($familia);

        $this->app->instance(SignatureMemberDirectory::class, $diretorio);
    }

    private function busca(string $termo): array
    {
        return $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->getJson(route('signature-documents.members', ['q' => $termo]))
            ->assertOk()
            ->json();
    }

    public function test_busca_por_titulo_traz_titular_e_dependentes(): void
    {
        $this->multiclubes([
            ['name' => 'Carlos Titular', 'cpf' => '12345678909', 'title' => '12345', 'email' => 'antigo@clube.test', 'phone' => null, 'titular' => true],
            ['name' => 'Ana Dependente', 'cpf' => '98765432100', 'title' => '12345', 'email' => 'ana@clube.test', 'phone' => '2433330000', 'titular' => false],
            ['name' => 'Bia Menor', 'cpf' => '', 'title' => '12345', 'email' => null, 'phone' => null, 'titular' => false],
        ]);

        $resultado = $this->busca('12345');

        $this->assertSame(['Carlos Titular', 'Ana Dependente', 'Bia Menor'], array_column($resultado, 'name'));
        $this->assertSame(['titular', 'dependente', 'dependente'], array_column($resultado, 'kind'));

        // O titular está nos dois lugares: aparece uma vez, com o vínculo local e o contato do aplicativo.
        $this->assertNotNull($resultado[0]['id']);
        $this->assertSame('carlos@app.test', $resultado[0]['email']);
        $this->assertSame('24999990000', $resultado[0]['phone']);

        // O dependente só existe no MultiClubes: sem vínculo local, com os dados de lá.
        $this->assertNull($resultado[1]['id']);
        $this->assertSame('98765432100', $resultado[1]['cpf']);
        $this->assertSame('ana@clube.test', $resultado[1]['email']);

        // Dependente sem CPF no cadastro vem em branco, para o atendente digitar.
        $this->assertSame('', $resultado[2]['cpf']);
        $this->assertSame('sem CPF no cadastro', $resultado[2]['cpf_masked']);
    }

    public function test_sem_resposta_do_multiclubes_a_busca_segue_com_a_tabela_local(): void
    {
        $this->multiclubes([]);

        $resultado = $this->busca('12345');

        $this->assertSame(['Carlos Titular'], array_column($resultado, 'name'));
        $this->assertNull($resultado[0]['kind']);
    }

    public function test_busca_por_nome_nao_consulta_o_multiclubes(): void
    {
        $diretorio = Mockery::mock(SignatureMemberDirectory::class)->makePartial();
        $diretorio->shouldNotReceive('byTitle');
        $this->app->instance(SignatureMemberDirectory::class, $diretorio);

        $this->assertSame(['Carlos Titular'], array_column($this->busca('Carlos Tit'), 'name'));
    }

    public function test_o_que_tem_cara_de_titulo(): void
    {
        $diretorio = new SignatureMemberDirectory();

        $this->assertTrue($diretorio->looksLikeTitle('12345'));
        $this->assertTrue($diretorio->looksLikeTitle('A-1234/2'));
        $this->assertFalse($diretorio->looksLikeTitle('Maria de Souza'));
        $this->assertFalse($diretorio->looksLikeTitle('123.456.789-09'));
    }
}
