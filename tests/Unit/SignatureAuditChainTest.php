<?php

namespace Tests\Unit;

use App\Models\SignatureAuditEvent;
use App\Services\Signature\SignatureAuditor;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSignatureSchema;
use Tests\TestCase;

/**
 * O encadeamento da trilha de auditoria.
 *
 * A promessa não é impedir adulteração — quem tem acesso de escrita ao banco
 * escreve. A promessa é que a adulteração FIQUE DETECTÁVEL: mudar uma linha
 * quebra a conferência dela em diante, e `verify()` aponta exatamente onde.
 *
 * Os testes de adulteração escrevem pelo query builder de propósito: o model
 * recusa update (ver SignatureAuditImmutabilityTest), então a única forma de
 * simular alguém mexendo por fora da aplicação é passar por fora dele.
 */
class SignatureAuditChainTest extends TestCase
{
    use CreatesSignatureSchema;

    private SignatureAuditor $auditor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSignatureSchema();

        $this->auditor = app(SignatureAuditor::class);
    }

    public function test_eventos_encadeiam_pelo_hash_do_anterior(): void
    {
        $documento = $this->criaDocumentoDeAssinatura();

        $primeiro = $this->auditor->record($documento, SignatureAuditEvent::EVENT_CREATED);
        $segundo = $this->auditor->record($documento, SignatureAuditEvent::EVENT_FROZEN);
        $terceiro = $this->auditor->record($documento, SignatureAuditEvent::EVENT_QR_ISSUED);

        $this->assertNull($primeiro->previous_hash, 'O primeiro evento abre a cadeia.');
        $this->assertSame($primeiro->hash, $segundo->previous_hash);
        $this->assertSame($segundo->hash, $terceiro->previous_hash);

        $this->assertSame(
            ['valid' => true, 'checked' => 3, 'broken_at' => null],
            $this->auditor->verify($documento),
        );
    }

    /**
     * Cadeia POR DOCUMENTO: um documento não interfere no outro. Sem isso,
     * conferir um termo obrigaria a varrer a tabela inteira, e duas
     * assinaturas simultâneas disputariam a mesma ponta.
     */
    public function test_cada_documento_tem_a_propria_cadeia(): void
    {
        $um = $this->criaDocumentoDeAssinatura();
        $outro = $this->criaDocumentoDeAssinatura();

        $this->auditor->record($um, SignatureAuditEvent::EVENT_CREATED);
        $primeiroDoOutro = $this->auditor->record($outro, SignatureAuditEvent::EVENT_CREATED);

        $this->assertNull($primeiroDoOutro->previous_hash);
        $this->assertTrue($this->auditor->verify($um)['valid']);
        $this->assertTrue($this->auditor->verify($outro)['valid']);
    }

    public function test_alterar_uma_linha_por_fora_quebra_a_conferencia(): void
    {
        $documento = $this->criaDocumentoDeAssinatura();

        $this->auditor->record($documento, SignatureAuditEvent::EVENT_CREATED);
        $adulterado = $this->auditor->record($documento, SignatureAuditEvent::EVENT_SIGNED);
        $this->auditor->record($documento, SignatureAuditEvent::EVENT_FINALIZED);

        // Alguém troca o evento no banco, direto, para esconder uma recusa.
        DB::table('signature_audit_events')
            ->where('id', $adulterado->id)
            ->update(['event' => SignatureAuditEvent::EVENT_REFUSED]);

        $resultado = $this->auditor->verify($documento);

        $this->assertFalse($resultado['valid']);
        $this->assertSame($adulterado->id, $resultado['broken_at']);
    }

    /** Apagar uma linha do meio também quebra: o elo seguinte fica órfão. */
    public function test_apagar_uma_linha_por_fora_quebra_a_conferencia(): void
    {
        $documento = $this->criaDocumentoDeAssinatura();

        $this->auditor->record($documento, SignatureAuditEvent::EVENT_CREATED);
        $doMeio = $this->auditor->record($documento, SignatureAuditEvent::EVENT_QR_ISSUED);
        $ultimo = $this->auditor->record($documento, SignatureAuditEvent::EVENT_SIGNED);

        DB::table('signature_audit_events')->where('id', $doMeio->id)->delete();

        $resultado = $this->auditor->verify($documento);

        $this->assertFalse($resultado['valid']);
        $this->assertSame($ultimo->id, $resultado['broken_at']);
    }

    public function test_cpf_entra_mascarado_no_payload(): void
    {
        $documento = $this->criaDocumentoDeAssinatura();

        $evento = $this->auditor->record($documento, SignatureAuditEvent::EVENT_IDENTITY_CONFIRMED, [
            'payload' => ['cpf' => '12345678909', 'signatario' => ['cpf' => '98765432100', 'nome' => 'Maria']],
        ]);

        $this->assertSame('123.***.**9-09', $evento->payload['cpf']);
        $this->assertSame('987.***.**1-00', $evento->payload['signatario']['cpf']);
        $this->assertSame('Maria', $evento->payload['signatario']['nome']);
    }

    /**
     * O token do QR não pode aparecer na trilha em hipótese nenhuma — nem em
     * claro, nem em hash. Quem lê a auditoria não precisa dele, e quem o
     * tivesse abriria uma sessão de assinatura.
     */
    public function test_token_nunca_entra_no_payload(): void
    {
        $documento = $this->criaDocumentoDeAssinatura();

        $evento = $this->auditor->record($documento, SignatureAuditEvent::EVENT_QR_ISSUED, [
            'payload' => ['token' => 'abc123', 'token_hash' => hash('sha256', 'abc123'), 'expira_em' => '5 min'],
        ]);

        $this->assertArrayNotHasKey('token', $evento->payload);
        $this->assertArrayNotHasKey('token_hash', $evento->payload);
        $this->assertSame('5 min', $evento->payload['expira_em']);

        $linha = DB::table('signature_audit_events')->where('id', $evento->id)->first();

        $this->assertStringNotContainsString('abc123', (string) $linha->payload);
    }

    /**
     * Num job de fila ou num comando agendado não há requisição: registrar o
     * IP do próprio servidor seria pior do que não registrar nada.
     */
    public function test_evento_de_sistema_nao_inventa_ip(): void
    {
        $documento = $this->criaDocumentoDeAssinatura();

        $evento = $this->auditor->record($documento, SignatureAuditEvent::EVENT_EXPIRED);

        $this->assertNull($evento->ip);
        $this->assertSame(SignatureAuditEvent::ACTOR_SYSTEM, $evento->actor_type);
    }

    public function test_ator_do_tablet_e_informado_explicitamente(): void
    {
        $documento = $this->criaDocumentoDeAssinatura();

        $evento = $this->auditor->record($documento, SignatureAuditEvent::EVENT_VIEWED, [
            'actor_type' => SignatureAuditEvent::ACTOR_KIOSK,
            'ip' => '10.0.0.15',
            'user_agent' => 'Mozilla/5.0 (Tablet)',
        ]);

        $this->assertSame(SignatureAuditEvent::ACTOR_KIOSK, $evento->actor_type);
        $this->assertSame('10.0.0.15', $evento->ip);
    }
}
