<?php

namespace Tests\Feature;

use App\Mail\SignatureGovbrInviteMail;
use App\Models\SignatureAuditEvent;
use App\Models\SignatureDocument;
use App\Models\SignatureGovbrInvite;
use App\Models\SignatureSigner;
use App\Services\Signature\SignatureDocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Concerns\BuildsGovbrSignedPdf;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\Concerns\MocksSignatureUser;
use Tests\TestCase;

/**
 * O convite por e-mail para assinar pelo gov.br.
 *
 * O atendente clica em "Enviar por e-mail"; a pessoa recebe o PDF, assina no
 * gov.br e responde ao e-mail com o arquivo — a resposta vai para o atendente,
 * que o envia na aba. Não há link: o Lara não é acessível de fora.
 */
class SignatureGovbrInviteTest extends TestCase
{
    use BuildsGovbrSignedPdf;
    use CreatesSignatureSchema;
    use MocksSignatureUser;

    /** @var array{cert: string, key: string} */
    private array $ac;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSignatureSchema();
        $this->createPermissionSchema();

        Storage::fake(config('signature.disk'));
        Mail::fake();

        $this->ac = $this->govbrAc();
        $this->govbrConfiaEm($this->ac);
    }

    /** Documento congelado e preparado para o gov.br, com Maria (1ª) e João (2º). */
    private function documentoPreparado(): SignatureDocument
    {
        $documento = $this->criaDocumentoDeAssinatura();

        SignatureSigner::create([
            'signature_document_id' => $documento->id,
            'name' => 'Joao da Silva',
            'cpf' => '52998224725',
            'role' => SignatureSigner::ROLE_WITNESS,
            'position' => 2,
        ]);

        $documento = app(SignatureDocumentService::class)->freeze($documento->fresh());

        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->post(route('signature-documents.govbr.prepare', $documento));

        return $documento->fresh();
    }

    private function convida(SignatureDocument $documento, SignatureSigner $signer, string $email = 'maria@example.com')
    {
        $atendente = $this->usuarioComPermissoes(['assinatura.documentos']);
        $atendente->email = 'atendente@clube.example';

        return $this->actingAs($atendente)
            ->post(route('signature-documents.govbr.invite', [$documento, $signer]), ['email' => $email]);
    }

    /**
     * O anexo é exatamente estes bytes, com este nome. O Laravel só carrega os
     * anexos de `attachments()` ao montar o e-mail — por isso o render().
     */
    private function anexou(SignatureGovbrInviteMail $mail, string $bytes, string $nome): bool
    {
        $mail->render();

        return $mail->hasAttachedData($bytes, $nome, ['mime' => 'application/pdf']);
    }

    private function original(SignatureDocument $documento): string
    {
        return Storage::disk(config('signature.disk'))->get($documento->fresh()->original_path);
    }

    private function assina(string $pdf, string $cpf, string $nome): string
    {
        return $this->govbrAssina($pdf, $this->govbrCertificado($this->ac, $cpf, $nome));
    }

    /** O atendente envia na aba o arquivo que a pessoa devolveu. */
    private function atendenteEnvia(SignatureDocument $documento, string $pdf)
    {
        return $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->post(route('signature-documents.govbr.store', $documento), [
                'documento' => UploadedFile::fake()->createWithContent('assinado.pdf', $pdf),
            ]);
    }

    public function test_botao_envia_o_pdf_e_a_resposta_vai_para_o_atendente(): void
    {
        $documento = $this->documentoPreparado();
        $maria = $documento->signers()->first();

        $this->convida($documento, $maria)->assertSessionHas('success');

        Mail::assertSent(SignatureGovbrInviteMail::class, function (SignatureGovbrInviteMail $mail) use ($documento) {
            return $mail->hasTo('maria@example.com')
                && $mail->hasReplyTo('atendente@clube.example')
                && $this->anexou($mail, $this->original($documento), 'documento-' . $documento->validation_code . '.pdf');
        });

        $this->assertSame(1, SignatureGovbrInvite::count());
        $this->assertSame('maria@example.com', $maria->fresh()->email);

        // A trilha guarda o e-mail mascarado, nunca inteiro.
        $evento = SignatureAuditEvent::where('event', SignatureAuditEvent::EVENT_GOVBR_INVITE_SENT)->sole();
        $this->assertSame('m***a@example.com', $evento->payload['email']);
        $this->assertStringNotContainsString('maria@example.com', json_encode($evento->payload));
    }

    public function test_email_nao_tem_link_para_o_lara(): void
    {
        $documento = $this->documentoPreparado();
        $this->convida($documento, $documento->signers()->first());

        $html = Mail::sent(SignatureGovbrInviteMail::class)->last()->render();

        $this->assertStringContainsString('Responda a este e-mail', $html);
        $this->assertStringNotContainsString(config('app.url'), $html);
        $this->assertFalse(Route::has('signature-govbr-public.show'));
    }

    public function test_sem_preparar_nao_envia(): void
    {
        $documento = app(SignatureDocumentService::class)->freeze($this->criaDocumentoDeAssinatura());

        $this->convida($documento, $documento->signers()->first())->assertSessionHas('warning');

        Mail::assertNothingSent();
        $this->assertSame(0, SignatureGovbrInvite::count());
    }

    public function test_qualquer_pendente_recebe_o_convite_em_qualquer_ordem(): void
    {
        $documento = $this->documentoPreparado();

        // O 2º da lista, antes do 1º.
        $this->convida($documento, $documento->signers()->where('position', 2)->first(), 'joao@example.com')
            ->assertSessionHas('success');

        Mail::assertSent(SignatureGovbrInviteMail::class, fn($mail) => $mail->hasTo('joao@example.com'));

        // A aba oferece o convite aos dois pendentes.
        $html = $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->get(route('signature-documents.show', [$documento, 'aba' => 'govbr']))
            ->assertOk()
            ->getContent();

        $this->assertSame(2, substr_count($html, 'data-govbr-invite-form='));
    }

    public function test_falha_no_envio_nao_registra_nada(): void
    {
        $documento = $this->documentoPreparado();

        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP fora do ar'));

        $this->convida($documento, $documento->signers()->first())->assertSessionHas('error');

        $this->assertSame(0, SignatureGovbrInvite::count());
        $this->assertSame(0, SignatureAuditEvent::where('event', SignatureAuditEvent::EVENT_GOVBR_INVITE_SENT)->count());
    }

    public function test_reenviar_manda_de_novo_e_registra_os_dois(): void
    {
        $documento = $this->documentoPreparado();
        $maria = $documento->signers()->first();

        $this->convida($documento, $maria);
        $this->convida($documento, $maria, 'maria.nova@example.com');

        Mail::assertSent(SignatureGovbrInviteMail::class, 2);
        $this->assertSame(2, SignatureGovbrInvite::count());
        $this->assertSame('maria.nova@example.com', $maria->fresh()->email);
    }

    public function test_o_segundo_recebe_o_arquivo_ja_assinado_pelo_primeiro(): void
    {
        $documento = $this->documentoPreparado();
        [$maria, $joao] = $documento->signers()->get()->all();

        $this->convida($documento, $maria);
        $daMaria = $this->assina($this->original($documento), '12345678909', 'MARIA DE SOUZA');
        $this->atendenteEnvia($documento, $daMaria)->assertSessionHas('success');

        $this->convida($documento, $joao, 'joao@example.com')->assertSessionHas('success');

        Mail::assertSent(SignatureGovbrInviteMail::class, fn(SignatureGovbrInviteMail $mail) => $mail->hasTo('joao@example.com')
            && $this->anexou($mail, $daMaria, 'documento-assinado-' . $documento->validation_code . '.pdf'));

        $this->atendenteEnvia($documento, $this->assina($daMaria, '52998224725', 'JOAO DA SILVA'))
            ->assertSessionHas('success');

        $this->assertSame(SignatureDocument::STATUS_FINALIZED, $documento->fresh()->status);
    }

    public function test_aba_mostra_o_botao_e_os_convites_enviados(): void
    {
        $documento = $this->documentoPreparado();
        $this->convida($documento, $documento->signers()->first());

        $this->actingAs($this->usuarioComPermissoes(['assinatura.documentos']))
            ->get(route('signature-documents.show', [$documento, 'aba' => 'govbr']))
            ->assertOk()
            ->assertSee('Reenviar por e-mail')
            ->assertSee('m***a@example.com')
            ->assertDontSee('Link vale até');
    }
}
