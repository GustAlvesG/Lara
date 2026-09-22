<?php

namespace Tests\Feature;

use App\Models\SignatureAuditEvent;
use App\Services\Signature\SignatureAuditor;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\TestCase;

/**
 * `signature_audit_events` é somente inserção.
 *
 * Este teste cobre a PRIMEIRA das três camadas dessa garantia — a do model. As
 * outras duas são a cadeia de hash (SignatureAuditChainTest) e o GRANT do
 * banco em produção, que o SQLite da suíte não reproduz e que está
 * documentado no README do módulo.
 *
 * A recusa é uma exceção, e não um `return false`: um save que não grava e não
 * reclama deixaria o chamador achando que gravou.
 */
class SignatureAuditImmutabilityTest extends TestCase
{
    use CreatesSignatureSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSignatureSchema();
    }

    private function evento(): SignatureAuditEvent
    {
        $documento = $this->criaDocumentoDeAssinatura();

        return app(SignatureAuditor::class)->record($documento, SignatureAuditEvent::EVENT_CREATED);
    }

    public function test_evento_de_auditoria_nao_pode_ser_alterado(): void
    {
        $evento = $this->evento();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('somente inserção');

        $evento->update(['event' => SignatureAuditEvent::EVENT_SIGNED]);
    }

    /** O caminho de trás: forceFill + save num registro que já existe. */
    public function test_evento_de_auditoria_nao_pode_ser_alterado_por_force_fill(): void
    {
        $evento = $this->evento();

        $this->expectException(RuntimeException::class);

        $evento->forceFill(['payload' => ['adulterado' => true]])->save();
    }

    public function test_evento_de_auditoria_nao_pode_ser_apagado(): void
    {
        $evento = $this->evento();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('somente inserção');

        $evento->delete();
    }

    public function test_recusa_nao_deixa_rastro_no_banco(): void
    {
        $evento = $this->evento();
        $antes = DB::table('signature_audit_events')->where('id', $evento->id)->first();

        try {
            $evento->update(['event' => SignatureAuditEvent::EVENT_SIGNED]);
        } catch (RuntimeException) {
            // esperado
        }

        try {
            $evento->delete();
        } catch (RuntimeException) {
            // esperado
        }

        $depois = DB::table('signature_audit_events')->where('id', $evento->id)->first();

        $this->assertEquals($antes, $depois);
        $this->assertSame(1, DB::table('signature_audit_events')->count());
    }

    /**
     * Um save que não muda nada (o `touch` involuntário de um
     * `$evento->save()` logo depois de ler) não é adulteração e não deve
     * explodir: o que a trava impede é ALTERAR.
     */
    public function test_save_sem_alteracao_nao_e_bloqueado(): void
    {
        $evento = $this->evento();

        $this->assertTrue($evento->save());
    }
}
