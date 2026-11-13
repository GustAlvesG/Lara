<?php

namespace Tests\Feature;

use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureSigner;
use App\Services\Signature\SignatureDocumentService;
use App\Services\Signature\SignatureRequestService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\TestCase;

/**
 * Prazos do documento (13/11/2026): 7 dias do congelamento no balcão e a
 * reativação dos que expiraram na regra antiga (`signature:reactivate-expired`).
 * Diretoria sem prazo: SignatureDirectorTest. Link remoto: SignatureRemoteLinkTest.
 */
class SignatureDeadlineTest extends TestCase
{
    use CreatesSignatureSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSignatureSchema();

        Storage::fake(config('signature.disk'));
        Queue::fake();
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function congelado(): SignatureDocument
    {
        return app(SignatureDocumentService::class)->freeze($this->criaDocumentoDeAssinatura());
    }

    /** Congela e deixa expirar pelo comando, `$diasAtras` dias atrás. */
    private function expiradoHa(int $diasAtras): SignatureDocument
    {
        $documento = $this->congelado();
        $documento->forceFill(['expires_at' => now()->subDays($diasAtras)])->save();

        app(SignatureRequestService::class)->expireDue();

        return $documento->fresh();
    }

    public function test_balcao_tem_prazo_de_7_dias(): void
    {
        $documento = $this->congelado();

        $this->assertTrue($documento->expires_at->between(now()->addDays(7)->subMinute(), now()->addDays(7)->addMinute()));

        // No 6º dia continua aguardando; depois do 7º, expira.
        Carbon::setTestNow(now()->addDays(6));
        app(SignatureRequestService::class)->expireDue();
        $this->assertSame(SignatureDocument::STATUS_AWAITING_SIGNATURE, $documento->fresh()->status);

        Carbon::setTestNow(now()->addDays(1)->addMinute());
        app(SignatureRequestService::class)->expireDue();
        $this->assertSame(SignatureDocument::STATUS_EXPIRED, $documento->fresh()->status);
    }

    public function test_simulacao_lista_e_nao_muda_nada(): void
    {
        $documento = $this->expiradoHa(2);

        $this->artisan('signature:reactivate-expired')
            ->expectsOutputToContain('Simulação')
            ->assertSuccessful();

        $this->assertSame(SignatureDocument::STATUS_EXPIRED, $documento->fresh()->status);
    }

    public function test_reativa_os_expirados_na_janela_com_prazo_novo(): void
    {
        $recente = $this->expiradoHa(2);
        $antigo = $this->expiradoHa(6);

        $this->assertSame(SignatureDocument::STATUS_EXPIRED, $recente->status);
        $this->assertSame(SignatureSigner::STATUS_EXPIRED, $recente->signers()->first()->status);

        $this->artisan('signature:reactivate-expired', ['--days' => 4, '--force' => true])->assertSuccessful();

        $recente->refresh();
        $this->assertSame(SignatureDocument::STATUS_AWAITING_SIGNATURE, $recente->status);
        $this->assertSame(SignatureSigner::STATUS_PENDING, $recente->signers()->first()->status);
        $this->assertTrue($recente->expires_at->between(now()->addDays(7)->subMinute(), now()->addDays(7)->addMinute()));
        $this->assertSame(2, SignatureAuditEvent::where('event', SignatureAuditEvent::EVENT_REACTIVATED)
            ->where('signature_document_id', $recente->id)->count());

        // Fora da janela: fica como estava.
        $this->assertSame(SignatureDocument::STATUS_EXPIRED, $antigo->fresh()->status);

        // Reaberto, ele assina de novo: o atendente gera outro QR.
        app(SignatureRequestService::class)->issue($recente->signers()->first());
    }
}
