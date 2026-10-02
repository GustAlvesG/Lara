<?php

namespace Tests\Feature;

use App\Models\Freelancer;
use App\Models\FreelancerDirector;
use App\Models\FreelancerService;
use App\Models\FunctionFreelancer;
use App\Services\FreelancerContractArchiver;
use App\Services\FreelancerContractPdf;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Concerns\CreatesFreelancerPixSchema;
use Tests\TestCase;

/**
 * A cópia do contrato de freelancer assinado no servidor de arquivos (FTP).
 *
 * O disco do arquivo é falso aqui. O que se testa:
 *
 * - só vai o documento com as DUAS assinaturas — e quem é a segunda depende da
 *   redação (coordenador na 1, diretor da 2 em diante);
 * - onde o arquivo vai parar (Freelancers → pessoa → data, tipo e número);
 * - que o PDF é o documento do contrato, com as assinaturas embutidas;
 * - que arquivar não mexe no contrato, e que o comando não envia duas vezes.
 */
class FreelancerContractArchiveTest extends TestCase
{
    use CreatesFreelancerPixSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createFreelancerPixSchema();

        // Colunas do traço e da cópia: migrations de verdade.
        (require base_path('database/migrations/2026_07_23_100002_add_signature_to_freelancer_services_table.php'))->up();
        (require base_path('database/migrations/2026_07_24_100001_add_coordinator_signature_to_freelancer_services_table.php'))->up();
        (require base_path('database/migrations/2026_10_07_100000_add_archive_to_freelancer_services_table.php'))->up();

        Storage::fake('public');
        Storage::fake(FreelancerDirector::DISK);
        Storage::fake('signature_archive');

        config(['freelancers.archive.enabled' => true]);
    }

    /* ---------------------------------------------------------------------
     | Quando o documento está pronto para ir
     |---------------------------------------------------------------------*/

    public function test_redacao_2_so_e_final_com_a_assinatura_do_diretor(): void
    {
        // Assinado pelo freelancer e validado pela coordenação: ainda falta o diretor.
        $validado = $this->contrato(['coordinator_signed_at' => '2026-10-04 09:00:00']);

        $this->assertFalse($validado->isFinalDocument());
        $this->assertFalse(FreelancerService::awaitingArchive()->pluck('id')->contains($validado->id));

        $assinado = $this->assinadoPeloDiretor();

        $this->assertTrue($assinado->isFinalDocument());
        $this->assertTrue(FreelancerService::awaitingArchive()->pluck('id')->contains($assinado->id));
    }

    public function test_redacao_1_e_final_com_a_assinatura_do_coordenador(): void
    {
        $soFreelancer = $this->contrato(['contract_version' => 1]);
        $completo = $this->contrato(['contract_version' => 1, 'coordinator_signed_at' => '2026-10-04 09:00:00']);

        $fila = FreelancerService::awaitingArchive()->pluck('id');

        $this->assertFalse($soFreelancer->isFinalDocument());
        $this->assertFalse($fila->contains($soFreelancer->id));

        $this->assertTrue($completo->isFinalDocument());
        $this->assertTrue($fila->contains($completo->id));
    }

    public function test_cancelado_e_sem_assinatura_do_freelancer_ficam_de_fora(): void
    {
        $cancelado = $this->assinadoPeloDiretor(['status_id' => FreelancerService::STATUS_CANCELLED]);
        $semFreelancer = $this->assinadoPeloDiretor(['freelancer_signed_at' => null]);

        $fila = FreelancerService::awaitingArchive()->pluck('id');

        foreach ([$cancelado, $semFreelancer] as $contrato) {
            $this->assertFalse($contrato->isFinalDocument());
            $this->assertFalse($fila->contains($contrato->id));
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ainda não tem as duas assinaturas');

        app(FreelancerContractArchiver::class)->archive($cancelado);
    }

    /* ---------------------------------------------------------------------
     | Onde vai parar
     |---------------------------------------------------------------------*/

    public function test_copia_vai_para_a_pasta_da_pessoa_com_data_tipo_e_numero(): void
    {
        $contrato = $this->assinadoPeloDiretor();
        $alteradoEm = $contrato->updated_at;

        $arquivado = app(FreelancerContractArchiver::class)->archive($contrato);

        // Sem acento; a data é a da assinatura do freelancer — a data do documento.
        $this->assertSame(
            'Lara/DocumentosAssinados/Freelancers/Joao Antonio da Conceicao/2026-10-03 - Contrato - C' . $contrato->id . '.pdf',
            $arquivado->archive_path,
        );

        $this->assertNotNull($arquivado->archived_at);
        $this->assertTrue($arquivado->isArchived());

        $bytes = Storage::disk('signature_archive')->get($arquivado->archive_path);

        $this->assertStringStartsWith('%PDF', $bytes);
        $this->assertSame($arquivado->archive_sha256, hash('sha256', $bytes));

        // Arquivar não é editar o contrato.
        $this->assertEquals($alteradoEm, $arquivado->updated_at);

        // CPF não entra em nome de pasta nem de arquivo.
        $this->assertStringNotContainsString('12345678909', $arquivado->archive_path);
    }

    public function test_aditivo_e_comissao_dizem_o_que_sao_no_nome_do_arquivo(): void
    {
        $base = $this->assinadoPeloDiretor();

        $aditivo = $this->assinadoPeloDiretor([
            'parent_service_id' => $base->id,
            'amendment_type' => FreelancerService::AMENDMENT_SCHEDULE,
        ], $base->freelancer);

        $segundoAditivo = $this->assinadoPeloDiretor([
            'parent_service_id' => $aditivo->id,
            'amendment_type' => FreelancerService::AMENDMENT_SCHEDULE,
        ], $base->freelancer);

        $comissao = $this->assinadoPeloDiretor([
            'parent_service_id' => $base->id,
            'amendment_type' => FreelancerService::AMENDMENT_COMMISSION,
        ], $base->freelancer);

        $arquivo = fn(FreelancerService $s) => basename(app(FreelancerContractArchiver::class)->pathFor($s));

        $this->assertSame("2026-10-03 - Termo aditivo - C{$aditivo->id}.pdf", $arquivo($aditivo));
        $this->assertSame("2026-10-03 - 2o Termo aditivo - C{$segundoAditivo->id}.pdf", $arquivo($segundoAditivo));
        $this->assertSame("2026-10-03 - Comissao - C{$comissao->id}.pdf", $arquivo($comissao));

        // Todos na pasta da mesma pessoa.
        $this->assertSame(
            dirname(app(FreelancerContractArchiver::class)->pathFor($base)),
            dirname(app(FreelancerContractArchiver::class)->pathFor($comissao)),
        );
    }

    public function test_a_pasta_leva_o_nome_do_documento_e_nao_o_do_cadastro_de_hoje(): void
    {
        $contrato = $this->assinadoPeloDiretor();

        // Congela a qualificação, como a assinatura faz, e depois corrige o cadastro.
        $contrato->forceFill(['signed_snapshot' => $contrato->buildContractSnapshot()])->save();
        $contrato->freelancer->forceFill(['name' => 'João A. Conceição'])->save();

        $this->assertStringContainsString(
            '/Freelancers/Joao Antonio da Conceicao/',
            app(FreelancerContractArchiver::class)->pathFor($contrato->fresh()),
        );
    }

    /* ---------------------------------------------------------------------
     | O PDF
     |---------------------------------------------------------------------*/

    public function test_o_pdf_e_o_documento_do_contrato_com_as_assinaturas_embutidas(): void
    {
        $contrato = $this->assinadoPeloDiretor();

        $html = app(FreelancerContractPdf::class)->html($contrato);

        $this->assertStringContainsString('Contrato Autônomo de Serviços de Freelancer', $html);
        $this->assertStringContainsString('João Antônio da Conceição', $html);
        $this->assertStringContainsString('Assinado digitalmente por Carlos Diretor', $html);

        // Nada de rota autenticada nem de arquivo público: o DomPDF não os alcançaria.
        $this->assertStringNotContainsString(route('freelancer-services.signature', [$contrato->id, 'freelancer']), $html);
        $this->assertStringNotContainsString('images/freelancer/cabecalho.png', $html);

        // Cabeçalho, rodapé, traço do freelancer e assinatura do diretor: quatro imagens embutidas.
        $this->assertSame(4, substr_count($html, 'src="data:image/png;base64,'));
        $this->assertStringContainsString('class="pdf-header"', $html);
        $this->assertStringContainsString('class="pdf-footer"', $html);
    }

    /* ---------------------------------------------------------------------
     | O comando agendado
     |---------------------------------------------------------------------*/

    public function test_comando_envia_os_prontos_uma_vez_so(): void
    {
        $pronto = $this->assinadoPeloDiretor();
        $pendente = $this->contrato(['coordinator_signed_at' => '2026-10-04 09:00:00']);

        $this->artisan('freelancers:archive')
            ->expectsOutputToContain('1 contrato(s) arquivado(s), 0 falha(s)')
            ->assertSuccessful();

        $this->assertNotNull($pronto->fresh()->archived_at);
        $this->assertNull($pendente->fresh()->archived_at);
        $this->assertCount(1, Storage::disk('signature_archive')->allFiles());

        // De novo: nada a fazer.
        $this->artisan('freelancers:archive')->assertSuccessful();

        $this->assertCount(1, Storage::disk('signature_archive')->allFiles());
    }

    public function test_desligado_o_comando_nao_envia_nada(): void
    {
        config(['freelancers.archive.enabled' => false]);

        $pronto = $this->assinadoPeloDiretor();

        $this->artisan('freelancers:archive')->assertSuccessful();

        $this->assertNull($pronto->fresh()->archived_at);
        $this->assertSame([], Storage::disk('signature_archive')->allFiles());

        // Com --forcar, para o teste manual no servidor antes de ligar.
        $this->artisan('freelancers:archive --forcar')->assertSuccessful();

        $this->assertNotNull($pronto->fresh()->archived_at);
    }

    /* ---------------------------------------------------------------------
     | Apoio
     |---------------------------------------------------------------------*/

    /** Redação 2 com as duas assinaturas do documento: freelancer e diretor. */
    private function assinadoPeloDiretor(array $sobrescreve = [], ?Freelancer $freelancer = null): FreelancerService
    {
        Storage::disk(FreelancerDirector::DISK)->put('freelancer/director-signatures/carlos.png', $this->png());

        $diretor = FreelancerDirector::firstOrCreate(
            ['email' => 'diretor@exemplo.test'],
            ['name' => 'Carlos Diretor', 'signature_path' => 'freelancer/director-signatures/carlos.png'],
        );

        return $this->contrato(array_merge([
            'coordinator_signed_at' => '2026-10-04 09:00:00',
            'freelancer_director_id' => $diretor->id,
            'director_signed_at' => '2026-10-06 10:15:00',
        ], $sobrescreve), $freelancer);
    }

    /** Contrato da redação 2, assinado pelo freelancer no tablet. */
    private function contrato(array $sobrescreve = [], ?Freelancer $freelancer = null): FreelancerService
    {
        $freelancer ??= Freelancer::create([
            'name' => 'João Antônio da Conceição',
            'cpf' => '12345678909',
            'rg' => '12.345.678-9',
            'nacionality' => 'brasileira',
            'civil_status' => 'Solteiro(a)',
            'address' => 'Rua A, 100 — Volta Redonda/RJ',
            'telephone' => '24999998888',
        ]);

        $funcao = FunctionFreelancer::firstOrCreate(['name' => 'Garçom'], ['price' => 10.00]);

        Storage::disk('public')->put('freelancer/signatures/traco.png', $this->png());

        $service = FreelancerService::create([
            'freelancer_id' => $freelancer->id,
            'function_freelancer_id' => $funcao->id,
            'location' => 'Salão',
            'start_date' => '2026-10-03',
            'start_time' => '18:00',
            'end_date' => '2026-10-03',
            'end_time' => '22:00',
            'price' => 160.00,
            'total_hours' => 4,
        ]);

        $service->forceFill(array_merge([
            'freelancer_signed_at' => '2026-10-03 17:40:00',
            'freelancer_signature_path' => 'freelancer/signatures/traco.png',
            'contract_version' => 2,
        ], $sobrescreve))->save();

        return $service->refresh();
    }

    /** Um PNG de verdade, pequeno: o DomPDF recusa bytes que não sejam imagem. */
    private function png(): string
    {
        $imagem = imagecreatetruecolor(120, 40);
        imagefill($imagem, 0, 0, imagecolorallocate($imagem, 255, 255, 255));
        imageline($imagem, 5, 30, 115, 10, imagecolorallocate($imagem, 20, 20, 60));

        ob_start();
        imagepng($imagem);

        return (string) ob_get_clean();
    }
}
