<?php

namespace Tests\Feature;

use App\Exceptions\FreelancerBatchException;
use App\Exceptions\FreelancerServiceLockedException;
use App\Mail\DirectorBatchApprovalMail;
use App\Models\Freelancer;
use App\Models\FreelancerDirector;
use App\Models\FreelancerService;
use App\Models\FreelancerServiceBatch;
use App\Models\FunctionFreelancer;
use App\Models\User;
use App\Services\FreelancerBatchService;
use App\Services\FreelancerDirectorService;
use App\Services\FreelancerService as FreelancerServiceManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\Concerns\CreatesFreelancerPixSchema;
use Tests\TestCase;

/**
 * Redação 2 do contrato: a coordenação valida pela web e quem assina pelo
 * CONTRATANTE é o diretor, na aprovação do lote.
 *
 * O que estes testes protegem:
 *
 * - as duas filas da coordenação não se misturam — o tablet só vê a redação 1,
 *   a validação web só vê a 2 —, e a validação libera o contrato para o lote
 *   como a assinatura liberava;
 * - a assinatura aplicada é a do diretor que RECEBEU o e-mail, e não a do
 *   cadastro de hoje;
 * - ela vai a todos os documentos do turno da redação 2, inclusive o contrato
 *   base que ganhou aditivo (e que por isso nunca passa por lote);
 * - o cadastro da diretoria nunca é editado no lugar.
 *
 * Como nos demais testes do módulo, nada autentica ninguém nem passa pela rota:
 * o model User fixa a conexão `mysql`. O usuário é só de fachada.
 */
class FreelancerDirectorSignatureFlowTest extends TestCase
{
    use CreatesFreelancerPixSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createFreelancerPixSchema();

        Mail::fake();
        Storage::fake(FreelancerDirector::DISK);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /* ---------------------------------------------------------------------
     | As duas filas da coordenação
     |---------------------------------------------------------------------*/

    public function test_o_tablet_so_ve_a_redacao_1_e_a_web_so_a_2(): void
    {
        $redacao1 = $this->contrato(['contract_version' => 1]);
        $redacao2 = $this->contrato(['contract_version' => 2]);

        $tablet = FreelancerService::awaitingCoordinator()->pluck('id');
        $web = FreelancerService::awaitingCoordinatorValidation()->pluck('id');

        $this->assertTrue($tablet->contains($redacao1->id));
        $this->assertFalse($tablet->contains($redacao2->id));

        $this->assertTrue($web->contains($redacao2->id));
        $this->assertFalse($web->contains($redacao1->id));
    }

    /** A validação web espera a mesma manhã seguinte que a assinatura no tablet. */
    public function test_a_fila_da_web_espera_o_fim_do_dia_do_turno(): void
    {
        Carbon::setTestNow('2026-09-10 22:00:00');

        $hoje = $this->contrato(['start_date' => '2026-09-10', 'end_date' => '2026-09-10']);

        $this->assertFalse(FreelancerService::awaitingCoordinatorValidation()->pluck('id')->contains($hoje->id));
        $this->assertStringContainsString('11/09/2026 às 08:00', $hoje->coordinatorValidationBlockReason());

        Carbon::setTestNow('2026-09-11 08:00:00');

        $this->assertTrue(FreelancerService::awaitingCoordinatorValidation()->pluck('id')->contains($hoje->id));
    }

    /* ---------------------------------------------------------------------
     | Validação pela web
     |---------------------------------------------------------------------*/

    public function test_validar_libera_o_contrato_para_o_lote(): void
    {
        $contrato = $this->contrato();

        $this->assertFalse(FreelancerService::availableForBatch()->pluck('id')->contains($contrato->id));

        $this->servico()->validateAsCoordinator($contrato, $this->usuario());

        $contrato->refresh();

        $this->assertNotNull($contrato->coordinator_signed_at);
        $this->assertSame(7, (int) $contrato->coordinator_signed_by);
        $this->assertNull($contrato->coordinator_signature_path, 'A validação não grava traço.');
        $this->assertTrue(FreelancerService::availableForBatch()->pluck('id')->contains($contrato->id));
        $this->assertFalse(FreelancerService::awaitingCoordinatorValidation()->pluck('id')->contains($contrato->id));
    }

    public function test_validar_nao_serve_para_a_redacao_1(): void
    {
        $contrato = $this->contrato(['contract_version' => 1]);

        $this->expectException(FreelancerServiceLockedException::class);
        $this->expectExceptionMessage('assina no tablet');

        $this->servico()->validateAsCoordinator($contrato, $this->usuario());
    }

    public function test_nao_se_valida_duas_vezes(): void
    {
        $contrato = $this->contrato(['coordinator_signed_at' => now()->subHour(), 'coordinator_signed_by' => 5]);

        $this->expectException(FreelancerServiceLockedException::class);
        $this->expectExceptionMessage('já foi validado');

        $this->servico()->validateAsCoordinator($contrato, $this->usuario());
    }

    public function test_nao_se_valida_antes_de_o_freelancer_assinar(): void
    {
        $contrato = $this->contrato(['freelancer_signed_at' => null]);

        $this->expectException(FreelancerServiceLockedException::class);
        $this->expectExceptionMessage('ainda não assinou');

        $this->servico()->validateAsCoordinator($contrato, $this->usuario());
    }

    public function test_nao_se_valida_contrato_de_cadastro_incompleto(): void
    {
        $contrato = $this->contrato();
        $contrato->freelancer->forceFill(['address' => null])->save();

        $this->expectException(FreelancerServiceLockedException::class);
        $this->expectExceptionMessage('Cadastro do freelancer incompleto');

        $this->servico()->validateAsCoordinator($contrato->fresh(), $this->usuario());
    }

    /**
     * A tela da validação: o documento inteiro e, ao pé dele, o PIN — que chega
     * TRAVADO e só é liberado pelo navegador quando a rolagem alcança o fim.
     * Um contrato por formulário, com a marca de abertura que o POST exige.
     */
    public function test_a_tela_de_validacao_traz_o_documento_e_o_pin_travado(): void
    {
        $contrato = $this->contrato()->load(['freelancer', 'functionFreelancer']);

        $html = view('freelancer.validation.show', [
            'service' => $contrato,
            'blockReason' => null,
            'token' => 'marca-de-abertura',
            'hasPin' => true,
            'remaining' => 3,
        ])->render();

        $this->assertStringContainsString('id="docSheet"', $html);
        $this->assertStringContainsString('id="endOfDocument"', $html);
        $this->assertStringContainsString('<fieldset id="validationFields" disabled', $html);
        $this->assertStringContainsString('value="marca-de-abertura"', $html);
        $this->assertStringContainsString(route('freelancer-validation.store', $contrato), $html);
        $this->assertSame(1, substr_count($html, 'name="pin"'), 'Um PIN, um contrato.');
        $this->assertStringNotContainsString('name="services[]"', $html);
    }

    public function test_a_tela_de_validacao_nao_oferece_o_pin_quando_nao_cabe(): void
    {
        $contrato = $this->contrato(['contract_version' => 1])->load(['freelancer', 'functionFreelancer']);

        $html = view('freelancer.validation.show', [
            'service' => $contrato,
            'blockReason' => $contrato->coordinatorValidationBlockReason(),
            'token' => null,
            'hasPin' => true,
            'remaining' => 0,
        ])->render();

        $this->assertStringContainsString('Validação indisponível', $html);
        $this->assertStringContainsString('assina no tablet', $html);
        $this->assertStringNotContainsString('name="pin"', $html);
    }

    /** A tela do tablet aberta desde antes do deploy não assina a redação 2. */
    public function test_o_tablet_nao_assina_a_redacao_2(): void
    {
        $contrato = $this->contrato();

        $this->expectException(FreelancerServiceLockedException::class);
        $this->expectExceptionMessage('valida pela web');

        $this->servico()->signAsCoordinator($contrato, $this->usuario());
    }

    /* ---------------------------------------------------------------------
     | Envio à diretoria
     |---------------------------------------------------------------------*/

    public function test_sem_diretor_cadastrado_o_lote_nao_segue(): void
    {
        $lote = $this->loteAprovadoPelaGerencia([$this->contratoValidado()]);

        $this->expectException(FreelancerBatchException::class);
        $this->expectExceptionMessage('Nenhum diretor cadastrado');

        $this->lotes()->notifyDirector($lote);
    }

    /** Sem imagem, a aprovação não teria o que aplicar aos contratos da redação 2. */
    public function test_sem_imagem_da_assinatura_o_lote_com_redacao_2_nao_segue(): void
    {
        FreelancerDirector::create(['name' => 'Carlos Diretor', 'email' => 'diretor@exemplo.test']);

        $lote = $this->loteAprovadoPelaGerencia([$this->contratoValidado()]);

        try {
            $this->lotes()->notifyDirector($lote);
            $this->fail('O lote seguiu sem a imagem da assinatura.');
        } catch (FreelancerBatchException $e) {
            $this->assertStringContainsString('sem a imagem da assinatura', $e->getMessage());
        }

        Mail::assertNothingSent();
    }

    /** Lote só com redação 1 não depende da imagem: nele o diretor não assina. */
    public function test_lote_so_com_redacao_1_segue_sem_imagem(): void
    {
        FreelancerDirector::create(['name' => 'Carlos Diretor', 'email' => 'diretor@exemplo.test']);

        $lote = $this->loteAprovadoPelaGerencia([
            $this->contratoValidado(['contract_version' => 1]),
        ]);

        $this->lotes()->notifyDirector($lote);

        Mail::assertSent(DirectorBatchApprovalMail::class, fn($mail) => $mail->hasTo('diretor@exemplo.test'));
    }

    public function test_o_envio_vai_ao_diretor_cadastrado_e_o_lote_guarda_qual(): void
    {
        $diretor = $this->diretorComAssinatura();
        $lote = $this->loteAprovadoPelaGerencia([$this->contratoValidado()]);

        $this->lotes()->notifyDirector($lote);

        $lote->refresh();

        $this->assertSame($diretor->id, (int) $lote->freelancer_director_id);
        $this->assertSame('diretor@exemplo.test', $lote->director_email);
        Mail::assertSent(DirectorBatchApprovalMail::class, fn($mail) => $mail->hasTo('diretor@exemplo.test'));
    }

    /* ---------------------------------------------------------------------
     | Aprovação: a assinatura vai ao documento
     |---------------------------------------------------------------------*/

    /**
     * A assinatura é a de quem recebeu o e-mail. Se o cadastro mudar entre o
     * envio e a digitação do código, o documento continua levando a assinatura
     * de quem de fato decidiu.
     */
    public function test_a_aprovacao_aplica_a_assinatura_de_quem_recebeu_o_email(): void
    {
        $quemRecebeu = $this->diretorComAssinatura();
        $contrato = $this->contratoValidado();
        $lote = $this->loteAprovadoPelaGerencia([$contrato]);

        $this->lotes()->notifyDirector($lote);

        // O cadastro muda depois do envio.
        FreelancerDirector::create([
            'name' => 'Outro Diretor',
            'email' => 'outro@exemplo.test',
            'signature_path' => $quemRecebeu->signature_path,
        ]);

        $this->aprovar($lote);

        $contrato->refresh();

        $this->assertTrue($contrato->hasDirectorSignature());
        $this->assertSame($quemRecebeu->id, (int) $contrato->freelancer_director_id);
        $this->assertSame('Carlos Diretor', $contrato->director->name);
    }

    /** Na redação 1 o documento já está completo: o diretor não assina. */
    public function test_a_aprovacao_nao_assina_a_redacao_1(): void
    {
        $this->diretorComAssinatura();
        $antigo = $this->contratoValidado(['contract_version' => 1]);
        $novo = $this->contratoValidado();
        $lote = $this->loteAprovadoPelaGerencia([$antigo, $novo]);

        $this->lotes()->notifyDirector($lote);
        $this->aprovar($lote);

        $this->assertFalse($antigo->refresh()->hasDirectorSignature());
        $this->assertNotNull($antigo->director_approved_at, 'Aprovado continua aprovado — só não é assinado.');
        $this->assertTrue($novo->refresh()->hasDirectorSignature());
    }

    public function test_a_recusa_nao_assina_nada(): void
    {
        $this->diretorComAssinatura();
        $contrato = $this->contratoValidado();
        $lote = $this->loteAprovadoPelaGerencia([$contrato]);

        $this->lotes()->notifyDirector($lote);

        $decisao = $this->lotes()->applyDirectorPin($lote->fresh(), $this->usuario(), (string) $lote->fresh()->director_reject_pin);

        $this->assertSame(FreelancerServiceBatch::DECISION_REJECTED, $decisao);
        $this->assertFalse($contrato->refresh()->hasDirectorSignature());
    }

    /**
     * O contrato base que ganhou aditivo de horário não vai a lote — quem paga
     * é o aditivo. Sem subir a cadeia, ele ficaria para sempre sem a assinatura
     * do CONTRATANTE. Vale também para o aditivo do aditivo.
     */
    public function test_a_aprovacao_do_aditivo_assina_os_documentos_que_ele_substituiu(): void
    {
        $this->diretorComAssinatura();

        $base = $this->contratoValidado();
        $primeiro = $this->aditivoDeHorario($base);
        $segundo = $this->aditivoDeHorario($primeiro);

        $lote = $this->loteAprovadoPelaGerencia([$segundo]);

        $this->lotes()->notifyDirector($lote);
        $this->aprovar($lote);

        $this->assertTrue($segundo->refresh()->hasDirectorSignature());
        $this->assertTrue($primeiro->refresh()->hasDirectorSignature(), 'O aditivo substituído também é assinado.');
        $this->assertTrue($base->refresh()->hasDirectorSignature(), 'O contrato base também é assinado.');
        $this->assertNull($base->director_approved_at, 'O base é assinado, não aprovado para pagamento.');
    }

    /**
     * A comissão ACRESCE ao contrato do turno, que continua indo a lote e é
     * assinado na aprovação dele — não na da comissão.
     */
    public function test_a_comissao_nao_assina_o_contrato_do_turno(): void
    {
        $this->diretorComAssinatura();

        $turno = $this->contratoValidado();
        $comissao = $this->contratoValidado([
            'parent_service_id' => $turno->id,
            'amendment_type' => FreelancerService::AMENDMENT_COMMISSION,
        ]);

        $lote = $this->loteAprovadoPelaGerencia([$comissao]);

        $this->lotes()->notifyDirector($lote);
        $this->aprovar($lote);

        $this->assertTrue($comissao->refresh()->hasDirectorSignature());
        $this->assertFalse($turno->refresh()->hasDirectorSignature());
    }

    /** O e-mail avisa o diretor de que o código também assina — e quantos. */
    public function test_o_email_avisa_que_a_aprovacao_assina_os_documentos(): void
    {
        $diretor = $this->diretorComAssinatura();
        $lote = $this->loteAprovadoPelaGerencia([
            $this->contratoValidado(),
            $this->contratoValidado(['contract_version' => 1]),
        ]);

        $lote->load(['services.freelancer', 'services.functionFreelancer']);

        $html = (new DirectorBatchApprovalMail($lote, $diretor, '111111', '222222'))->render();

        $this->assertStringContainsString('Prezado(a) Carlos Diretor', $html);
        $this->assertStringContainsString('a 1 documento', $html);
        $this->assertStringContainsString('Assinado digitalmente por Carlos Diretor', $html);
    }

    /* ---------------------------------------------------------------------
     | Cadastro da diretoria
     |---------------------------------------------------------------------*/

    /**
     * Nunca edita no lugar: contratos aprovados apontam para o registro que os
     * assinou. Trocar só o e-mail repete a imagem anterior.
     */
    public function test_alterar_o_cadastro_cria_um_registro_novo(): void
    {
        $servico = app(FreelancerDirectorService::class);

        $primeiro = $servico->register('Carlos Diretor', 'Diretor@Exemplo.test', $this->png(), $this->usuario());
        $segundo = $servico->register('Carlos Diretor', 'novo@exemplo.test', null, $this->usuario());

        $this->assertNotSame($primeiro->id, $segundo->id);
        $this->assertSame('diretor@exemplo.test', $primeiro->fresh()->email, 'O registro antigo não muda.');
        $this->assertSame($primeiro->signature_path, $segundo->signature_path, 'Sem imagem nova, vale a anterior.');
        $this->assertSame($segundo->id, FreelancerDirector::current()->id);
        $this->assertTrue($segundo->hasSignature());
    }

    public function test_o_primeiro_cadastro_exige_a_imagem(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(FreelancerDirectorService::class)->register('Carlos Diretor', 'diretor@exemplo.test', null, $this->usuario());
    }

    /* ---------------------------------------------------------------------
     | Auxiliares
     |---------------------------------------------------------------------*/

    private function servico(): FreelancerServiceManager
    {
        return app(FreelancerServiceManager::class);
    }

    private function lotes(): FreelancerBatchService
    {
        return app(FreelancerBatchService::class);
    }

    /** Usuário só de fachada: tocar a tabela `users` levaria a suíte ao mysql. */
    private function usuario(): User
    {
        return (new User())->forceFill(['id' => 7]);
    }

    private function png(): UploadedFile
    {
        return UploadedFile::fake()->create('assinatura.png', 12, 'image/png');
    }

    private function diretorComAssinatura(): FreelancerDirector
    {
        Storage::disk(FreelancerDirector::DISK)->put('freelancer/director-signatures/carlos.png', 'PNG');

        return FreelancerDirector::create([
            'name' => 'Carlos Diretor',
            'email' => 'diretor@exemplo.test',
            'signature_path' => 'freelancer/director-signatures/carlos.png',
        ]);
    }

    private function aprovar(FreelancerServiceBatch $lote): void
    {
        $lote = $lote->fresh();

        $decisao = $this->lotes()->applyDirectorPin($lote, $this->usuario(), (string) $lote->director_approve_pin);

        $this->assertSame(FreelancerServiceBatch::DECISION_APPROVED, $decisao);
    }

    /** @param  array<int, FreelancerService>  $contratos */
    private function loteAprovadoPelaGerencia(array $contratos): FreelancerServiceBatch
    {
        // Sem `created_by` / `reviewed_by`: o envio carrega esses usuários para o
        // e-mail, e com a chave nula o Laravel nem chega a consultar `users` —
        // que está na conexão mysql, fora da suíte.
        $lote = FreelancerServiceBatch::create([
            'status' => FreelancerServiceBatch::STATUS_AWAITING_DIRECTOR,
            'created_by' => null,
            'sent_at' => now()->subDays(2),
            'reviewed_by' => null,
            'reviewed_at' => now()->subDay(),
        ]);

        foreach ($contratos as $contrato) {
            $contrato->forceFill([
                'batch_id' => $lote->id,
                'manager_approved_at' => now()->subDay(),
                'manager_approved_by' => 7,
            ])->save();
        }

        return $lote;
    }

    /** Aditivo de horário sobre $base, que passa a ser pago por ele. */
    private function aditivoDeHorario(FreelancerService $base): FreelancerService
    {
        $aditivo = $this->contratoValidado([
            'parent_service_id' => $base->id,
            'amendment_type' => FreelancerService::AMENDMENT_SCHEDULE,
        ]);

        $base->forceFill(['amended_at' => now()->subDays(4), 'amendment_service_id' => $aditivo->id])->save();

        return $aditivo;
    }

    private function contratoValidado(array $sobrescreve = []): FreelancerService
    {
        return $this->contrato(array_merge([
            'coordinator_signed_at' => now()->subDays(3),
            'coordinator_signed_by' => 7,
        ], $sobrescreve));
    }

    /** Contrato da redação 2, assinado pelo freelancer e já liberado. */
    private function contrato(array $sobrescreve = []): FreelancerService
    {
        $freelancer = Freelancer::create([
            'name' => 'Freelancer ' . Str()->random(6),
            'cpf' => (string) random_int(10000000000, 99999999999),
            'rg' => '12.345.678-9',
            'nacionality' => 'brasileira',
            'civil_status' => 'Solteiro(a)',
            'address' => 'Rua A, 100 — Volta Redonda/RJ',
            'telephone' => '24999998888',
        ]);

        $funcao = FunctionFreelancer::create(['name' => 'Garçom', 'price' => 10.00]);

        $dia = now()->subDays(5)->toDateString();

        $service = FreelancerService::create([
            'freelancer_id' => $freelancer->id,
            'function_freelancer_id' => $funcao->id,
            'location' => 'Salão',
            'start_date' => $dia,
            'start_time' => '18:00',
            'end_date' => $dia,
            'end_time' => '22:00',
            'price' => 160.00,
            'total_hours' => 4,
        ]);

        $service->forceFill(array_merge([
            'freelancer_signed_at' => now()->subDays(5),
            'contract_version' => 2,
        ], $sobrescreve))->save();

        return $service->refresh();
    }
}
