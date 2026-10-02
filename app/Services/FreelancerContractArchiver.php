<?php

namespace App\Services;

use App\Models\FreelancerService;
use App\Support\ArchivePath;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Arquiva no servidor de arquivos (FTP) a cópia do contrato de freelancer
 * assinado — o mesmo arquivo de rede dos documentos do balcão, na pasta
 * `Freelancers`, uma pasta por pessoa (ver App\Support\ArchivePath):
 *
 *     Lara/DocumentosAssinados/Freelancers/Joao Antonio da Conceicao/
 *       2026-10-03 - Contrato - C1234.pdf
 *       2026-10-03 - Termo aditivo - C1240.pdf
 *       2026-10-03 - Comissao - C1241.pdf
 *
 * **Só vai o documento com as duas assinaturas** (`isFinalDocument()`): na
 * redação 1, freelancer e coordenador; da 2 em diante, freelancer e diretor —
 * que assina na aprovação do lote. Antes disso o documento ainda muda (a
 * assinatura que falta entra nele), e uma cópia tirada cedo ficaria velha.
 *
 * O contrato não tem PDF guardado no sistema: ele é montado a cada exibição, a
 * partir da redação e da qualificação congeladas na assinatura. O PDF nasce
 * aqui, e o hash dele fica gravado para conferir, depois, se o arquivo da pasta
 * ainda é o que o sistema enviou.
 *
 * Quem chama é o comando `freelancers:archive`, de hora em hora. Não há envio
 * no ato da assinatura: a aprovação da diretoria assina dezenas de documentos
 * de uma vez, e gerar dezenas de PDFs ali seguraria a tela de quem aprova.
 */
class FreelancerContractArchiver
{
    public function __construct(private FreelancerContractPdf $pdf)
    {
    }

    public function enabled(): bool
    {
        return (bool) config('freelancers.archive.enabled', false);
    }

    /** O mesmo disco e a mesma pasta-raiz do arquivo dos documentos do balcão. */
    public function disk(): Filesystem
    {
        return Storage::disk(config('signature.archive.disk'));
    }

    public function root(): string
    {
        return trim((string) config('signature.archive.root', 'Lara/DocumentosAssinados'), '/');
    }

    /**
     * Onde este contrato fica no arquivo. Depende só do contrato: a mesma
     * chamada, meses depois, dá o mesmo caminho — o reenvio sobrescreve a
     * própria cópia.
     *
     * O nome da pasta é o do DOCUMENTO (a qualificação congelada), e não o do
     * cadastro de hoje: corrigir o cadastro não espalha os contratos antigos
     * por duas pastas diferentes das que eles citam.
     */
    public function pathFor(FreelancerService $service): string
    {
        return implode('/', [
            $this->root(),
            'Freelancers',
            ArchivePath::person($service->contractParty()['name'] ?? null),
            ArchivePath::file([
                ($service->freelancer_signed_at ?? now())->format('Y-m-d'),
                $this->kind($service),
                'C' . $service->id,
            ]),
        ]);
    }

    /**
     * Gera o PDF, envia, confere e registra.
     *
     * @throws RuntimeException quando o contrato ainda não tem as duas assinaturas ou a cópia não confere
     */
    public function archive(FreelancerService $service): FreelancerService
    {
        if (!$service->isFinalDocument()) {
            throw new RuntimeException("Contrato {$service->id} ainda não tem as duas assinaturas para ser arquivado.");
        }

        $bytes = $this->pdf->render($service);
        $destino = $this->pathFor($service);
        $disk = $this->disk();

        if (!$disk->put($destino, $bytes)) {
            throw new RuntimeException("Contrato {$service->id}: o servidor de arquivos recusou a gravação.");
        }

        // É cópia, e por isso é conferida: um PDF truncado numa pasta chamada
        // "DocumentosAssinados" é pior que nenhum.
        if ($disk->size($destino) !== strlen($bytes)) {
            throw new RuntimeException("Contrato {$service->id}: a cópia no servidor de arquivos ficou incompleta.");
        }

        // Direto na tabela: arquivar não é editar o contrato, e não deve mexer
        // em `updated_at` nem em `updated_by` de um documento assinado.
        DB::table($service->getTable())->where('id', $service->id)->update([
            'archive_path' => $destino,
            'archive_sha256' => hash('sha256', $bytes),
            'archived_at' => now(),
        ]);

        return $service->refresh();
    }

    /** O que o arquivo é, no nome dele: contrato, termo aditivo ou comissão. */
    private function kind(FreelancerService $service): string
    {
        if ($service->isCommissionAmendment()) {
            return 'Comissao';
        }

        if (!$service->isAmendment()) {
            return 'Contrato';
        }

        $ordem = $service->amendmentOrder();

        return ($ordem > 1 ? $ordem . 'o ' : '') . 'Termo aditivo';
    }
}
