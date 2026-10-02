<?php

namespace App\Services\Signature;

use App\Exceptions\UnreadablePdfException;
use App\Models\SignatureDocument;
use App\Models\SignatureSigner;
use App\Support\PngTrimmer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use setasign\Fpdi\PdfParser\StreamReader;
use Throwable;

/**
 * Carimba assinatura e visto POR CIMA de um PDF pronto.
 *
 * É o caminho do documento enviado em PDF: ele entra na íntegra — texto,
 * imagens, diagramação, cabeçalho — e nada dele é remontado. As páginas são
 * importadas como estão (FPDI) e o sistema só acrescenta:
 *
 *  - a linha de validação, miúda, no pé de cada página;
 *  - as caixas do visto, quando o modelo exige;
 *  - a assinatura de cada pessoa no ponto marcado pelo atendente — ou, para
 *    quem não tem ponto marcado, numa folha de assinaturas ao fim;
 *  - o manifesto, no PDF final.
 *
 * O dompdf, que monta os documentos de modelo, não edita PDF pronto; por isso
 * este serviço existe ao lado dele, e não dentro.
 *
 * Tudo em milímetros. A posição de uma assinatura é guardada como FRAÇÃO da
 * página (0 a 1, a partir do canto de cima à esquerda): assim ela vale para
 * qualquer tamanho de folha, e a tela que marca não precisa saber de unidades.
 */
class SignaturePdfStamper
{
    /** Caixa em que a assinatura é encaixada: o ponto marcado é o meio da base dela. */
    public const SIGNATURE_WIDTH = 62.0;
    public const SIGNATURE_HEIGHT = 22.0;

    private const VISTO_WIDTH = 26.0;
    private const VISTO_HEIGHT = 9.0;
    private const VISTO_GAP = 2.0;

    /** @var array<int, string> arquivos temporários das imagens, apagados ao fim */
    private array $temporarios = [];

    /**
     * Confere que o PDF pode ser aproveitado e devolve o número de páginas.
     *
     * Roda no ENVIO: é ali que o atendente pode trocar o arquivo. Descobrir
     * no congelamento que o PDF não abre seria descobrir com a pessoa na
     * frente do balcão.
     *
     * @throws UnreadablePdfException
     */
    public function inspect(string $bytes): int
    {
        try {
            $pdf = new Fpdi('P', 'mm');
            $paginas = $pdf->setSourceFile(StreamReader::createByString($bytes));

            // Importar todas as páginas é o que de fato prova que dá para usá-las.
            for ($n = 1; $n <= $paginas; $n++) {
                $pdf->importPage($n);
            }

            return $paginas;
        } catch (CrossReferenceException $e) {
            if ($e->getCode() === CrossReferenceException::ENCRYPTED) {
                throw new UnreadablePdfException(
                    'Este PDF está protegido por senha. Envie uma versão sem proteção.'
                );
            }

            throw new UnreadablePdfException(
                'Este PDF foi gravado num formato compactado que o sistema não consegue aproveitar. '
                . 'Abra o arquivo e gere de novo com "Imprimir → Salvar como PDF" (ou, no Word, '
                . '"Salvar como → PDF"), e envie o arquivo novo.'
            );
        } catch (Throwable $e) {
            throw new UnreadablePdfException(
                'O arquivo não é um PDF válido, ou está danificado. Gere o PDF de novo e envie outra vez.'
            );
        }
    }

    /**
     * O PDF do documento: as páginas enviadas, com o que o sistema carimba.
     *
     * @param  SignatureDocumentRenderer::MODE_*  $mode
     * @param  ?string  $manifest  o manifesto, já em PDF — só no final
     */
    public function build(SignatureDocument $document, string $mode, ?string $manifest = null): string
    {
        $origem = Storage::disk(config('signature.disk'))->get($document->source_path);

        if ($origem === null) {
            throw new UnreadablePdfException('O PDF enviado não está mais no disco do sistema.');
        }

        $final = $mode === SignatureDocumentRenderer::MODE_FINAL;
        $signers = $document->signers()->with('evidence')->get()->values();
        $comVisto = (bool) $document->template?->requires_initials;

        try {
            $pdf = new Fpdi('P', 'mm');
            $pdf->SetAutoPageBreak(false);
            $pdf->SetMargins(0, 0, 0);
            $pdf->SetCreator('Lara');
            $pdf->SetTitle($this->t($document->title));

            $paginas = $pdf->setSourceFile(StreamReader::createByString($origem));

            for ($n = 1; $n <= $paginas; $n++) {
                $pagina = $pdf->importPage($n);
                $tamanho = $pdf->getTemplateSize($pagina);

                $pdf->AddPage($tamanho['orientation'], [$tamanho['width'], $tamanho['height']]);
                $pdf->useTemplate($pagina);

                $this->validationLine($pdf, $document, $tamanho['width'], $tamanho['height']);

                if ($comVisto) {
                    $this->initials($pdf, $signers, $final, $tamanho['width'], $tamanho['height']);
                }

                if ($final) {
                    foreach ($signers as $signer) {
                        if (($signer->signature_position['page'] ?? null) === $n) {
                            $this->positionedSignature($pdf, $signer, $tamanho['width'], $tamanho['height']);
                        }
                    }
                }
            }

            // Quem não tem ponto marcado — ou tem numa página que o PDF não
            // tem — assina na folha de assinaturas. Ninguém fica sem lugar.
            $semLugar = $signers->filter(function (SignatureSigner $signer) use ($paginas) {
                $pagina = $signer->signature_position['page'] ?? null;

                return !is_int($pagina) || $pagina < 1 || $pagina > $paginas;
            })->values();

            if ($semLugar->isNotEmpty()) {
                $this->signatureSheet($pdf, $document, $semLugar, $final);
            }

            if ($manifest !== null) {
                $anexo = $pdf->setSourceFile(StreamReader::createByString($manifest));

                for ($n = 1; $n <= $anexo; $n++) {
                    $pagina = $pdf->importPage($n);
                    $tamanho = $pdf->getTemplateSize($pagina);

                    $pdf->AddPage($tamanho['orientation'], [$tamanho['width'], $tamanho['height']]);
                    $pdf->useTemplate($pagina);
                }
            }

            return $pdf->Output('S');
        } finally {
            foreach ($this->temporarios as $arquivo) {
                @unlink($arquivo);
            }

            $this->temporarios = [];
        }
    }

    /**
     * A linha de validação, no pé da página. Miúda e na margem: o documento é
     * de quem o enviou, mas quem o recebe impresso precisa do código para
     * conferir a autenticidade.
     */
    private function validationLine(Fpdi $pdf, SignatureDocument $document, float $largura, float $altura): void
    {
        if (!$document->validation_code) {
            return;
        }

        $pdf->SetFont('Helvetica', '', 5.5);
        $pdf->SetTextColor(110, 100, 100);
        $pdf->Text(8, $altura - 3.5, $this->t(
            'Documento eletrônico — autenticidade: ' . url('/validar/' . $document->validation_code)
        ));
    }

    /**
     * As caixas do visto, no canto de baixo à direita: em branco no original,
     * com a rubrica de cada um no final.
     *
     * @param  Collection<int, SignatureSigner>  $signers
     */
    private function initials(Fpdi $pdf, Collection $signers, bool $final, float $largura, float $altura): void
    {
        $total = $signers->count();
        $inicio = $largura - 8 - $total * self::VISTO_WIDTH - ($total - 1) * self::VISTO_GAP;
        $topo = $altura - 9 - self::VISTO_HEIGHT;

        $pdf->SetDrawColor(205, 197, 195);
        $pdf->SetLineWidth(0.15);
        $pdf->SetFont('Helvetica', '', 4.5);
        $pdf->SetTextColor(110, 100, 100);

        foreach ($signers as $i => $signer) {
            $x = $inicio + $i * (self::VISTO_WIDTH + self::VISTO_GAP);

            $pdf->Rect($x, $topo, self::VISTO_WIDTH, self::VISTO_HEIGHT);
            $pdf->Text($x, $topo + self::VISTO_HEIGHT + 2, $this->t('Visto — ' . Str::limit(
                $signer->party_label ?: $signer->name,
                24,
                '…',
            )));

            if ($final && ($imagem = $this->image($signer->evidence?->initials_path))) {
                $this->fit($pdf, $imagem, $x + 1, $topo + 0.8, self::VISTO_WIDTH - 2, self::VISTO_HEIGHT - 1.6);
            }
        }
    }

    /** A assinatura no ponto marcado: o ponto é o meio da base — a linha onde se assina. */
    private function positionedSignature(Fpdi $pdf, SignatureSigner $signer, float $largura, float $altura): void
    {
        $imagem = $this->image($signer->evidence?->signature_path);

        if (!$imagem) {
            return;
        }

        $w = min(self::SIGNATURE_WIDTH, $largura);
        $h = min(self::SIGNATURE_HEIGHT, $altura);

        $x = (float) ($signer->signature_position['x'] ?? 0.5) * $largura - $w / 2;
        $y = (float) ($signer->signature_position['y'] ?? 0.5) * $altura - $h;

        // Marcado rente à borda: a caixa volta para dentro da folha.
        $x = max(0, min($x, $largura - $w));
        $y = max(0, min($y, $altura - $h));

        $this->fit($pdf, $imagem, $x, $y, $w, $h, 'bottom');
    }

    /**
     * A folha de assinaturas: uma página do sistema, ao fim do documento, para
     * quem não tem ponto marcado no PDF.
     *
     * @param  Collection<int, SignatureSigner>  $signers
     */
    private function signatureSheet(Fpdi $pdf, SignatureDocument $document, Collection $signers, bool $final): void
    {
        $novaPagina = function () use ($pdf, $document) {
            $pdf->AddPage('P', [210, 297]);

            $pdf->SetTextColor(36, 26, 29);
            $pdf->SetFont('Helvetica', 'B', 13);
            $pdf->Text(20, 26, $this->t('Folha de assinaturas'));

            $pdf->SetFont('Helvetica', '', 9);
            $pdf->SetTextColor(110, 100, 100);
            $pdf->Text(20, 32, $this->t(Str::limit($document->title, 95, '…')));

            $pdf->SetDrawColor(160, 0, 1);
            $pdf->SetLineWidth(0.5);
            $pdf->Line(20, 35, 190, 35);

            $this->validationLine($pdf, $document, 210, 297);
        };

        $novaPagina();

        $y = 50.0;

        foreach ($signers as $signer) {
            if ($y > 245) {
                $novaPagina();
                $y = 50.0;
            }

            if ($final && ($imagem = $this->image($signer->evidence?->signature_path))) {
                $this->fit($pdf, $imagem, 20, $y, 80, 24, 'bottom', 'left');
            }

            $base = $y + 25;

            $pdf->SetDrawColor(36, 26, 29);
            $pdf->SetLineWidth(0.25);
            $pdf->Line(20, $base, 110, $base);

            $pdf->SetTextColor(36, 26, 29);
            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->Text(20, $base + 5, $this->t($signer->name));

            $pdf->SetFont('Helvetica', '', 8.5);
            $pdf->SetTextColor(110, 100, 100);
            $pdf->Text(20, $base + 9.5, $this->t($signer->capacityLabel() . ' — CPF ' . $signer->maskedCpf()));

            if ($final) {
                $pdf->Text(20, $base + 14, $this->t($signer->signed_at
                    ? 'Assinado eletronicamente em ' . $signer->signed_at->format('d/m/Y \à\s H:i') . ' (horário do servidor).'
                    : $signer->statusLabel() . '.'));
            }

            $y += 48;
        }
    }

    /**
     * Encaixa a imagem na caixa, sem deformar.
     *
     * @param  array{0: string, 1: int, 2: int}  $imagem  [arquivo, largura, altura]
     * @param  'middle'|'bottom'  $vertical
     * @param  'center'|'left'  $horizontal
     */
    private function fit(
        Fpdi $pdf,
        array $imagem,
        float $x,
        float $y,
        float $w,
        float $h,
        string $vertical = 'middle',
        string $horizontal = 'center',
    ): void {
        [$arquivo, $larguraPx, $alturaPx] = $imagem;

        $escala = min($w / $larguraPx, $h / $alturaPx);
        $largura = $larguraPx * $escala;
        $altura = $alturaPx * $escala;

        $pdf->Image(
            $arquivo,
            $horizontal === 'left' ? $x : $x + ($w - $largura) / 2,
            $vertical === 'bottom' ? $y + $h - $altura : $y + ($h - $altura) / 2,
            $largura,
            $altura,
            'PNG',
        );
    }

    /**
     * Uma imagem do disco privado, recortada ao traço, em arquivo temporário —
     * a biblioteca só lê imagem de caminho.
     *
     * @return array{0: string, 1: int, 2: int}|null
     */
    private function image(?string $caminho): ?array
    {
        $disk = Storage::disk(config('signature.disk'));

        if (!$caminho || !$disk->exists($caminho)) {
            return null;
        }

        $bytes = PngTrimmer::trim((string) $disk->get($caminho));
        $info = @getimagesizefromstring($bytes);

        // A biblioteca só aceita PNG aqui; um traço que não é PNG não entra.
        if (!$info || $info[2] !== IMAGETYPE_PNG) {
            return null;
        }

        $base = tempnam(sys_get_temp_dir(), 'ass');
        $arquivo = $base . '.png';

        file_put_contents($arquivo, $bytes);

        $this->temporarios[] = $base;
        $this->temporarios[] = $arquivo;

        return [$arquivo, $info[0], $info[1]];
    }

    /** As fontes padrão do PDF são Windows-1252, e não UTF-8. */
    private function t(?string $texto): string
    {
        $convertido = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', (string) $texto);

        return $convertido === false ? (string) $texto : $convertido;
    }
}
