<?php

namespace Tests\Feature\Waitlist;

use App\Models\User;
use App\Models\Waitlist\WaitlistClass;
use App\Models\Waitlist\WaitlistContact;
use App\Models\Waitlist\WaitlistEntry;
use App\Models\Waitlist\WaitlistPerson;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\Concerns\MigratesAccessSchema;
use Tests\Concerns\SharesSqliteWithUserConnection;
use Tests\TestCase;

/**
 * InfoClube › Fila de Espera: não sócios nas turmas das modalidades, sem
 * vínculo com o MultiClubes. Acesso pela permissão, turmas, inclusão pelo
 * CPF (sem duplicar a pessoa), várias filas por pessoa, ordem de chegada que
 * ligação nenhuma muda, saída e reativação na posição original. Desde
 * 18/11/2026: cadastro sem CPF, que só fica na fila até alguém completar o CPF.
 *
 * Banco: migrations de acesso, da fila e as que o layout consulta, no SQLite
 * em memória, com a conexão do User apontando para o mesmo banco.
 */
class WaitlistModuleTest extends TestCase
{
    use MigratesAccessSchema;
    use SharesSqliteWithUserConnection;

    private User $secretaria;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateAccessSchema();
        Artisan::call('migrate', ['--path' => [
            'database/migrations/2026_11_10_120000_create_waitlist_tables.php',
            'database/migrations/2026_11_18_100000_make_waitlist_people_cpf_nullable.php',
            'database/migrations/2026_11_19_100000_add_notes_to_waitlist_entries.php',
            // O layout consulta notificações e avisos em toda tela.
            'database/migrations/2026_06_10_000001_create_notifications_table.php',
            'database/migrations/2026_06_10_000002_create_avisos_table.php',
            'database/migrations/2026_06_10_000003_avisos_privacy_and_lembretes.php',
            'database/migrations/2026_06_10_000004_create_aviso_views_table.php',
            'database/migrations/2026_06_10_000006_create_tags_tables.php',
            'database/migrations/2026_06_12_000002_create_aviso_user_table.php',
            'database/migrations/2026_07_31_120000_add_mandatory_reading_to_avisos.php',
        ]]);
        $this->shareSqliteWithUserConnection();

        // TI tem acesso total: alcança `infoclube.fila-espera` sem configurar setor.
        $this->secretaria = $this->makeUser('Pessoa da Secretaria');
        $this->joinSector($this->secretaria, 'TI');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function turma(string $name = 'Infantil ter/qui 9h', string $modality = 'Natação', bool $active = true): WaitlistClass
    {
        return WaitlistClass::create(['modality' => $modality, 'name' => $name, 'active' => $active]);
    }

    private function pessoa(?string $cpf ='52998224725', string $name = 'Ana Souza'): WaitlistPerson
    {
        return WaitlistPerson::create(['cpf' => $cpf, 'name' => $name, 'phone' => '(21) 99999-0000']);
    }

    private function inscrever(WaitlistPerson $person, WaitlistClass $class, string $when): WaitlistEntry
    {
        return WaitlistEntry::create([
            'waitlist_class_id' => $class->id,
            'waitlist_person_id' => $person->id,
            'joined_at' => Carbon::parse($when),
            'status' => WaitlistEntry::STATUS_WAITING,
        ]);
    }

    // --- Acesso ----------------------------------------------------------------

    public function test_so_quem_tem_a_permissao_abre_a_fila(): void
    {
        $this->actingAs($this->makeUser('Sem Acesso'))->get('/fila-espera')->assertForbidden();
        $this->actingAs($this->makeUser('Outro Sem Acesso'))->get('/fila-espera/pessoas')->assertForbidden();

        $this->actingAs($this->secretaria)->get('/fila-espera')->assertOk()
            ->assertSee('Fila de Espera')
            ->assertSee('A fila dos sócios continua no MultiClubes');
    }

    // --- Turmas ----------------------------------------------------------------

    public function test_cadastra_turma_e_recusa_nome_repetido_na_mesma_modalidade(): void
    {
        $this->actingAs($this->secretaria)->post('/fila-espera/turmas', [
            'modality' => 'Natação', 'name' => 'Adulto seg/qua 19h', 'active' => '1',
        ])->assertRedirect();

        $turma = WaitlistClass::firstOrFail();
        $this->assertTrue($turma->active);
        $this->assertSame($this->secretaria->id, $turma->created_by);

        $this->actingAs($this->secretaria)->from('/fila-espera/turmas/nova')->post('/fila-espera/turmas', [
            'modality' => 'Natação', 'name' => 'Adulto seg/qua 19h', 'active' => '1',
        ])->assertSessionHasErrors('name');

        // Mesmo nome em outra modalidade pode.
        $this->actingAs($this->secretaria)->post('/fila-espera/turmas', [
            'modality' => 'Hidroginástica', 'name' => 'Adulto seg/qua 19h', 'active' => '1',
        ])->assertSessionHasNoErrors();
    }

    public function test_turma_com_historico_nao_se_exclui_so_se_desativa(): void
    {
        $vazia = $this->turma('Vazia');
        $usada = $this->turma('Usada');
        $this->inscrever($this->pessoa(), $usada, '2026-11-01 10:00');

        $this->actingAs($this->secretaria)->delete('/fila-espera/turmas/' . $usada->id)
            ->assertRedirect('/fila-espera/turmas/' . $usada->id . '/editar')
            ->assertSessionHas('warning');
        $this->assertModelExists($usada);

        $this->actingAs($this->secretaria)->delete('/fila-espera/turmas/' . $vazia->id)->assertRedirect('/fila-espera');
        $this->assertModelMissing($vazia);

        $this->actingAs($this->secretaria)->put('/fila-espera/turmas/' . $usada->id, [
            'modality' => 'Natação', 'name' => 'Usada', 'active' => '0',
        ])->assertRedirect();
        $this->assertFalse($usada->fresh()->active);
    }

    // --- Inclusão --------------------------------------------------------------

    public function test_inclui_pessoa_nova_com_cpf_so_em_digitos_em_varias_turmas(): void
    {
        $natacao = $this->turma('Infantil ter/qui 9h');
        $tenis = $this->turma('Iniciante sáb 8h', 'Tênis');

        $this->actingAs($this->secretaria)->get('/fila-espera/pessoas/nova?cpf=529.982.247-25')
            ->assertOk()->assertSee('Dados da pessoa')->assertSee('529.982.247-25');

        $this->actingAs($this->secretaria)->post('/fila-espera/pessoas', [
            'cpf' => '529.982.247-25',
            'name' => 'Ana Souza',
            'birth_date' => '2018-03-10',
            'phone' => '(21) 99999-0000',
            'guardian_name' => 'Carla Souza',
            'classes' => [$natacao->id, $tenis->id],
        ])->assertRedirect();

        $person = WaitlistPerson::firstOrFail();
        $this->assertSame('52998224725', $person->cpf);
        $this->assertTrue($person->isMinor());
        $this->assertSame(2, $person->entries()->waiting()->count());
        $this->assertSame($this->secretaria->id, $person->entries()->first()->created_by);
    }

    public function test_cpf_ja_cadastrado_abre_a_ficha_em_vez_de_duplicar(): void
    {
        $person = $this->pessoa();

        $this->actingAs($this->secretaria)->get('/fila-espera/pessoas/nova?cpf=529.982.247-25')
            ->assertRedirect('/fila-espera/pessoas/' . $person->id);

        $this->actingAs($this->secretaria)->post('/fila-espera/pessoas', [
            'cpf' => '52998224725', 'name' => 'Outra Ana', 'phone' => '21988887777',
        ])->assertSessionHasErrors('cpf');

        $this->assertSame(1, WaitlistPerson::count());
    }

    public function test_cpf_invalido_e_recusado(): void
    {
        $this->actingAs($this->secretaria)->get('/fila-espera/pessoas/nova?cpf=123.456.789-00')
            ->assertRedirect('/fila-espera/pessoas/nova')
            ->assertSessionHasErrors('cpf');

        $this->actingAs($this->secretaria)->post('/fila-espera/pessoas', [
            'cpf' => '123.456.789-00', 'name' => 'CPF errado', 'phone' => '21988887777',
        ])->assertSessionHasErrors('cpf');
        $this->assertSame(0, WaitlistPerson::count());
    }

    // --- Sem CPF (18/11/2026) --------------------------------------------------

    public function test_cadastra_sem_cpf_ja_nas_turmas(): void
    {
        $natacao = $this->turma();
        $tenis = $this->turma('Iniciante sáb 8h', 'Tênis');

        $this->actingAs($this->secretaria)->get('/fila-espera/pessoas/nova')->assertOk()->assertSee('Cadastrar sem CPF');
        $this->actingAs($this->secretaria)->get('/fila-espera/pessoas/nova?sem_cpf=1')->assertOk()
            ->assertSee('Dados da pessoa')->assertSee('Cadastro sem CPF');

        foreach (['Ana Souza', 'Bia Lima'] as $name) {
            $this->actingAs($this->secretaria)->post('/fila-espera/pessoas', [
                'cpf' => '', 'name' => $name, 'phone' => '21988887777',
                'classes' => [$natacao->id, $tenis->id],
            ])->assertSessionHasNoErrors()->assertSessionHas('success', fn ($m) => str_contains($m, 'Cadastro sem CPF'));
        }

        // Dois sem CPF convivem (NULL não fere o único) e já estão nas filas.
        $this->assertSame(2, WaitlistPerson::whereNull('cpf')->count());
        $this->assertSame(4, WaitlistEntry::waiting()->count());
    }

    public function test_sem_cpf_nenhuma_acao_posterior_ate_preencher_o_cpf(): void
    {
        $natacao = $this->turma();
        $tenis = $this->turma('Iniciante sáb 8h', 'Tênis');
        $ana = $this->pessoa(null);
        $entrada = $this->inscrever($ana, $natacao, '2026-11-01 09:00');
        $saiu = $this->inscrever($ana, $tenis, '2026-10-01 09:00');
        $saiu->update(['status' => WaitlistEntry::STATUS_WITHDRAWN, 'closed_at' => now()]);
        $ficha = '/fila-espera/pessoas/' . $ana->id;

        $this->actingAs($this->secretaria)->from($ficha)
            ->post('/fila-espera/inscricoes/' . $entrada->id . '/ligacoes', ['result' => 'no_answer'])
            ->assertSessionHasErrors(['result' => 'Ana Souza está sem CPF. Para registrar a ligação, preencha o CPF em "Editar cadastro" antes.']);
        $this->actingAs($this->secretaria)->from($ficha)
            ->post('/fila-espera/inscricoes/' . $entrada->id . '/encerrar', ['status' => 'withdrawn'])
            ->assertSessionHasErrors('status');
        $this->actingAs($this->secretaria)->from($ficha)
            ->post('/fila-espera/inscricoes/' . $saiu->id . '/reativar')
            ->assertSessionHasErrors('status');
        $outra = $this->turma('Adulto seg 19h');
        $this->actingAs($this->secretaria)->from($ficha)
            ->post($ficha . '/filas', ['classes' => [$outra->id]])
            ->assertSessionHasErrors('classes');

        // Editar o cadastro também é alteração: sem CPF não salva.
        $this->actingAs($this->secretaria)->from($ficha)->put($ficha, [
            'cpf' => '', 'name' => 'Ana Souza Lima', 'phone' => '21988887777',
        ])->assertSessionHasErrors('cpf');

        $this->assertSame(0, WaitlistContact::count());
        $this->assertTrue($entrada->fresh()->isWaiting());
        $this->assertSame('Ana Souza', $ana->fresh()->name);
        $this->assertSame(1, $ana->entries()->waiting()->count());

        // A ficha mostra o aviso no lugar das ações.
        $this->actingAs($this->secretaria)->get($ficha)->assertOk()
            ->assertSee('Cadastro sem CPF')
            ->assertDontSee('Registrar ligação')
            ->assertDontSee('Reativar');

        // Completou o CPF: tudo liberado.
        $this->actingAs($this->secretaria)->put($ficha, [
            'cpf' => '529.982.247-25', 'name' => 'Ana Souza', 'phone' => '21988887777',
        ])->assertSessionHasNoErrors();
        $this->assertSame('52998224725', $ana->fresh()->cpf);

        $this->actingAs($this->secretaria)
            ->post('/fila-espera/inscricoes/' . $entrada->id . '/ligacoes', ['result' => 'no_answer'])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, WaitlistContact::count());
        $this->assertSame(1, $entrada->fresh()->position());
    }

    public function test_cpf_preenchido_depois_nao_pode_ser_de_outra_pessoa(): void
    {
        $this->pessoa('52998224725', 'Ana com CPF');
        $semCpf = $this->pessoa(null, 'Ana da planilha');

        $this->actingAs($this->secretaria)->from('/fila-espera/pessoas/' . $semCpf->id)
            ->put('/fila-espera/pessoas/' . $semCpf->id, [
                'cpf' => '52998224725', 'name' => 'Ana da planilha', 'phone' => '21988887777',
            ])->assertSessionHasErrors('cpf');
        $this->assertNull($semCpf->fresh()->cpf);
    }

    public function test_lista_filtra_e_marca_quem_esta_sem_cpf(): void
    {
        $turma = $this->turma();
        $this->pessoa('52998224725', 'Com Documento');
        $this->inscrever($this->pessoa(null, 'Veio da Planilha'), $turma, '2026-11-01 09:00');

        $this->actingAs($this->secretaria)->get('/fila-espera/pessoas')->assertOk()
            ->assertSee('Sem CPF (1)')->assertSee('Com Documento')->assertSee('Veio da Planilha');
        $this->actingAs($this->secretaria)->get('/fila-espera/pessoas?sem_cpf=1')->assertOk()
            ->assertSee('Veio da Planilha')->assertDontSee('Com Documento');
        $this->actingAs($this->secretaria)->get('/fila-espera/turmas/' . $turma->id)->assertOk()
            ->assertSee('Veio da Planilha')->assertSee('Sem CPF');
    }

    public function test_nao_entra_duas_vezes_na_mesma_turma_nem_em_turma_inativa(): void
    {
        $turma = $this->turma();
        $inativa = $this->turma('Encerrada', 'Natação', false);
        $person = $this->pessoa();
        $this->inscrever($person, $turma, '2026-11-01 10:00');

        $this->actingAs($this->secretaria)->post('/fila-espera/pessoas/' . $person->id . '/filas', [
            'classes' => [$turma->id],
        ])->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, 'Já estava aguardando'));
        $this->assertSame(1, $person->entries()->count());

        $this->actingAs($this->secretaria)->from('/fila-espera/pessoas/' . $person->id)
            ->post('/fila-espera/pessoas/' . $person->id . '/filas', ['classes' => [$inativa->id]])
            ->assertSessionHasErrors('classes');
        $this->assertSame(1, $person->entries()->count());
    }

    // --- Ordem, ligações e saída ----------------------------------------------

    public function test_ordem_de_chegada_e_ligacao_sem_resposta_nao_muda_a_posicao(): void
    {
        $turma = $this->turma();
        $primeira = $this->inscrever($this->pessoa('52998224725', 'Primeira'), $turma, '2026-11-01 09:00');
        $segunda = $this->inscrever($this->pessoa('11144477735', 'Segunda'), $turma, '2026-11-01 09:00');
        $terceira = $this->inscrever($this->pessoa('39053344705', 'Terceira'), $turma, '2026-11-03 09:00');

        $this->assertSame([1, 2, 3], [$primeira->position(), $segunda->position(), $terceira->position()]);

        $this->actingAs($this->secretaria)->post('/fila-espera/inscricoes/' . $primeira->id . '/ligacoes', [
            'result' => 'no_answer', 'notes' => 'caixa postal',
        ])->assertRedirect('/fila-espera/pessoas/' . $primeira->waitlist_person_id . '#fila-' . $primeira->id);

        $this->assertSame(1, $primeira->fresh()->position());
        $contact = WaitlistContact::firstOrFail();
        $this->assertSame('no_answer', $contact->result);
        $this->assertSame($this->secretaria->id, $contact->user_id);

        $html = $this->actingAs($this->secretaria)->get('/fila-espera/turmas/' . $turma->id)->assertOk()->getContent();
        $this->assertTrue(strpos($html, 'Primeira') < strpos($html, 'Segunda') && strpos($html, 'Segunda') < strpos($html, 'Terceira'));
        $this->assertStringContainsString('Não atendeu', $html);
    }

    public function test_matriculado_sai_da_fila_lembra_as_outras_e_reativar_volta_a_posicao(): void
    {
        $natacao = $this->turma();
        $tenis = $this->turma('Iniciante sáb 8h', 'Tênis');
        $ana = $this->pessoa();
        $entrada = $this->inscrever($ana, $natacao, '2026-11-01 09:00');
        $this->inscrever($ana, $tenis, '2026-11-02 09:00');
        $depois = $this->inscrever($this->pessoa('11144477735', 'Bia'), $natacao, '2026-11-05 09:00');

        $this->actingAs($this->secretaria)->post('/fila-espera/inscricoes/' . $entrada->id . '/encerrar', [
            'status' => 'enrolled',
        ])->assertSessionHas('success', fn ($m) => str_contains($m, 'Tênis — Iniciante sáb 8h'));

        $entrada->refresh();
        $this->assertSame(WaitlistEntry::STATUS_ENROLLED, $entrada->status);
        $this->assertSame($this->secretaria->id, $entrada->closed_by);
        $this->assertNull($entrada->position());
        $this->assertSame(1, $depois->fresh()->position());

        // Ligação para quem saiu da fila é recusada.
        $this->actingAs($this->secretaria)->from('/fila-espera/pessoas/' . $ana->id)
            ->post('/fila-espera/inscricoes/' . $entrada->id . '/ligacoes', ['result' => 'accepted'])
            ->assertSessionHasErrors('result');

        $this->actingAs($this->secretaria)->post('/fila-espera/inscricoes/' . $entrada->id . '/reativar')->assertRedirect();
        $this->assertSame(1, $entrada->fresh()->position());
        $this->assertSame(2, $depois->fresh()->position());
    }

    public function test_remover_pede_motivo(): void
    {
        $entrada = $this->inscrever($this->pessoa(), $this->turma(), '2026-11-01 09:00');

        $this->actingAs($this->secretaria)->from('/fila-espera/pessoas/' . $entrada->waitlist_person_id)
            ->post('/fila-espera/inscricoes/' . $entrada->id . '/encerrar', ['status' => 'removed'])
            ->assertSessionHasErrors('close_reason');
        $this->assertTrue($entrada->fresh()->isWaiting());

        $this->actingAs($this->secretaria)->post('/fila-espera/inscricoes/' . $entrada->id . '/encerrar', [
            'status' => 'removed', 'close_reason' => 'Telefone não existe e sem e-mail',
        ])->assertSessionHasNoErrors();
        $this->assertSame(WaitlistEntry::STATUS_REMOVED, $entrada->fresh()->status);
    }

    // --- Telas -----------------------------------------------------------------

    // --- Observação da fila e acordeão (19/11/2026) ---------------------------

    public function test_observacao_da_fila_e_por_turma_e_aparece_abaixo_do_nome(): void
    {
        $natacao = $this->turma();
        $tenis = $this->turma('Iniciante sáb 8h', 'Tênis');
        $ana = $this->pessoa();
        $naNatacao = $this->inscrever($ana, $natacao, '2026-11-01 09:00');
        $noTenis = $this->inscrever($ana, $tenis, '2026-11-01 09:00');

        // Pelo acordeão da turma: volta para a turma, com o acordeão aberto.
        $this->actingAs($this->secretaria)->put('/fila-espera/inscricoes/' . $naNatacao->id . '/observacao', [
            'entry_notes' => '  Só pode à tarde  ', 'entry_id' => $naNatacao->id, 'back' => 'turma',
        ])->assertRedirect('/fila-espera/turmas/' . $natacao->id . '#fila-' . $naNatacao->id)
            ->assertSessionHas('open_entry', $naNatacao->id);

        $this->assertSame('Só pode à tarde', $naNatacao->fresh()->notes);
        $this->assertNull($noTenis->fresh()->notes);
        $this->assertSame(1, $naNatacao->fresh()->position());

        $this->actingAs($this->secretaria)->get('/fila-espera/turmas/' . $natacao->id)->assertOk()
            ->assertSee('Só pode à tarde');
        $this->actingAs($this->secretaria)->get('/fila-espera/turmas/' . $tenis->id)->assertOk()
            ->assertDontSee('Só pode à tarde');

        // Pela ficha: volta para a ficha. Vazio apaga.
        $this->actingAs($this->secretaria)->put('/fila-espera/inscricoes/' . $naNatacao->id . '/observacao', ['entry_notes' => ''])
            ->assertRedirect('/fila-espera/pessoas/' . $ana->id . '#fila-' . $naNatacao->id);
        $this->assertNull($naNatacao->fresh()->notes);
    }

    public function test_observacao_da_fila_pede_cpf(): void
    {
        $turma = $this->turma();
        $entrada = $this->inscrever($this->pessoa(null), $turma, '2026-11-01 09:00');

        $this->actingAs($this->secretaria)->from('/fila-espera/turmas/' . $turma->id)
            ->put('/fila-espera/inscricoes/' . $entrada->id . '/observacao', ['entry_notes' => 'teste', 'back' => 'turma'])
            ->assertSessionHasErrors('entry_notes');
        $this->assertNull($entrada->fresh()->notes);
    }

    public function test_fila_da_turma_tem_as_operacoes_no_acordeao_e_o_lapis_leva_a_ficha(): void
    {
        $turma = $this->turma();
        $ana = $this->pessoa();
        $entrada = $this->inscrever($ana, $turma, '2026-11-01 09:00');
        $entrada->contacts()->create(['result' => 'no_answer', 'notes' => 'caixa postal']);

        $this->actingAs($this->secretaria)->get('/fila-espera/turmas/' . $turma->id)->assertOk()
            ->assertSee('id="acoes-' . $entrada->id . '"', false)
            ->assertSee('Registrar ligação')
            ->assertSee('Salvar observação')
            ->assertSee('Tirar da fila')
            ->assertSee('caixa postal')
            ->assertSee('name="back" value="turma"', false)
            ->assertSee('href="' . route('waitlist.people.show', $ana) . '#fila-' . $entrada->id . '"', false);

        // Ligação pelo acordeão volta para a turma.
        $this->actingAs($this->secretaria)->post('/fila-espera/inscricoes/' . $entrada->id . '/ligacoes', [
            'result' => 'message', 'back' => 'turma',
        ])->assertRedirect('/fila-espera/turmas/' . $turma->id . '#fila-' . $entrada->id);

        // Tirar da fila pelo acordeão: volta para a turma, sem acordeão para abrir.
        $this->actingAs($this->secretaria)->post('/fila-espera/inscricoes/' . $entrada->id . '/encerrar', [
            'status' => 'withdrawn', 'back' => 'turma',
        ])->assertRedirect('/fila-espera/turmas/' . $turma->id . '#fila-' . $entrada->id)
            ->assertSessionMissing('open_entry');
    }

    public function test_telas_mostram_contagens_ficha_e_busca(): void
    {
        $turma = $this->turma();
        $ana = $this->pessoa();
        $this->inscrever($ana, $turma, '2026-11-01 09:00');

        $this->actingAs($this->secretaria)->get('/fila-espera')->assertOk()
            ->assertSee('Infantil ter/qui 9h')->assertSee('01/11/2026')
            ->assertSee('1 não sócio aguardando');

        $this->actingAs($this->secretaria)->get('/fila-espera/pessoas?q=21999990000')->assertOk()->assertSee('Ana Souza');
        $this->actingAs($this->secretaria)->get('/fila-espera/pessoas?q=529982')->assertOk()->assertSee('Ana Souza');
        $this->actingAs($this->secretaria)->get('/fila-espera/pessoas?q=Zuleica')->assertOk()->assertDontSee('Ana Souza');

        $this->actingAs($this->secretaria)->get('/fila-espera/pessoas/' . $ana->id)->assertOk()
            ->assertSee('1º da fila')
            ->assertSee('Registrar ligação')
            ->assertSee('529.982.247-25');

        $this->actingAs($this->secretaria)->get('/fila-espera/turmas/' . $turma->id . '?situacao=encerrados')->assertOk()
            ->assertSee('Ninguém saiu da fila desta turma ainda.');

        // Formulários: passo do CPF, turma nova e edição (com a sugestão de modalidade).
        $this->actingAs($this->secretaria)->get('/fila-espera/pessoas/nova')->assertOk()->assertSee('CPF da pessoa');
        $this->actingAs($this->secretaria)->get('/fila-espera/turmas/nova')->assertOk()->assertSee('<option value="Natação">', false);
        $this->actingAs($this->secretaria)->get('/fila-espera/turmas/' . $turma->id . '/editar')->assertOk()
            ->assertSee('Turma ativa')->assertSee('Excluir');
    }
}
