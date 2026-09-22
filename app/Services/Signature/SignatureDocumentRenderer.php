<?php

namespace App\Services\Signature;

use App\Models\SignatureDocument;
use App\Models\SignatureTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Monta o HTML e o PDF de um documento de assinatura.
 *
 * É o lugar ÚNICO que produz o documento — o congelamento, o tablet e o job de
 * finalização passam todos por aqui. A lição vem do módulo de freelancer, onde
 * o texto do contrato viveu em dois lugares (Blade do painel e JavaScript do
 * tablet) até o dia em que os dois divergiram: o freelancer assinava no tablet
 * um texto diferente do que o painel imprimia.
 *
 * Dois modos:
 *
 *  - `original` — o documento com o CAMPO de assinatura em branco. É o que o
 *    tablet exibe e o que tem o `original_sha256`.
 *  - `final` — o mesmo documento com a IMAGEM do traço no lugar do campo. É o
 *    que o job de finalização gera, junto com a página de manifesto.
 *
 * O PDF final é RE-RENDERIZADO a partir do `body_snapshot` congelado, e não
 * carimbado sobre o PDF original — o dompdf não edita PDF pronto. Quem prova o
 * que foi lido é o `original_sha256` guardado, citado no manifesto, mais a
 * trilha de auditoria. O ponto de troca por um carimbo cirúrgico (FPDI) é o
 * SignaturePdfSealer.
 */
class SignatureDocumentRenderer
{
    public const MODE_ORIGINAL = 'original';
    public const MODE_FINAL = 'final';

    /**
     * Tags que um modelo de documento pode usar além da allow-list padrão do
     * HtmlSanitizer: título e lista numerada são a estrutura de um termo, e
     * "desembrulhar" um `<ol>` viraria cláusula em parágrafo corrido.
     *
     * @var array<int, string>
     */
    public const EXTRA_HTML_TAGS = ['h1', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'hr', 'blockquote'];

    public function __construct(private SignatureQrCode $qrCodes)
    {
    }

    /**
     * O corpo do documento com as variáveis substituídas.
     *
     * O marcador da assinatura NÃO é substituído aqui: ele fica no snapshot e
     * é resolvido no render, que é quem sabe se está montando o original (campo
     * em branco) ou o final (imagem do traço).
     *
     * @param  array<string, mixed>|null  $data
     */
    public function body(SignatureTemplate $template, ?array $data): string
    {
        $corpo = $template->body_html;

        foreach (($data ?? []) as $chave => $valor) {
            $corpo = str_replace(
                '[[' . $chave . ']]',
                e(is_scalar($valor) ? (string) $valor : ''),
                $corpo,
            );
        }

        return $corpo;
    }

    /**
     * Variáveis obrigatórias que ficaram sem valor. Devolve os rótulos, que é
     * o que a tela mostra.
     *
     * @param  array<string, mixed>|null  $data
     * @return array<int, string>
     */
    public function missingVariables(SignatureTemplate $template, ?array $data): array
    {
        $faltando = [];

        foreach ($template->declaredVariables() as $variavel) {
            if (!$variavel['required']) {
                continue;
            }

            $valor = $data[$variavel['key']] ?? null;

            if ($valor === null || trim((string) $valor) === '') {
                $faltando[] = $variavel['label'];
            }
        }

        return $faltando;
    }

    /**
     * O HTML completo do documento, pronto para virar PDF ou ir para a tela.
     *
     * Depois de congelado, o corpo vem do `body_snapshot` — nunca mais do
     * modelo. É essa troca que faz uma revisão do modelo não reescrever um
     * documento já assinado.
     *
     * @param  self::MODE_*  $mode
     */
    public function html(SignatureDocument $document, string $mode = self::MODE_ORIGINAL): string
    {
        $corpo = $document->body_snapshot
            ?? $this->body($document->template, $document->data);

        $placeholder = $document->template->signature_placeholder ?: '[[assinatura]]';

        $assinaturas = view('signature.pdf.signature-area', [
            'document' => $document,
            'signers' => $document->signers()->get(),
            'mode' => $mode,
            'signatureImages' => $mode === self::MODE_FINAL ? $this->signatureImages($document) : [],
        ])->render();

        /*
         | Sem o marcador no corpo, a área de assinatura vai para o fim. É o
         | que faz um modelo escrito às pressas continuar gerando um documento
         | assinável, em vez de um documento sem onde assinar.
         */
        $corpo = str_contains($corpo, $placeholder)
            ? str_replace($placeholder, $assinaturas, $corpo)
            : $corpo . $assinaturas;

        return view('signature.pdf.document', [
            'document' => $document,
            'body' => $corpo,
            'mode' => $mode,
            // A página de manifesto só existe no PDF final: é o relatório das
            // evidências, e não parte do que a pessoa leu e assinou.
            'manifest' => $mode === self::MODE_FINAL ? $this->manifest($document) : null,
        ])->render();
    }

    /**
     * A página de manifesto: o que liga a assinatura àquela pessoa e àquele
     * conteúdo.
     *
     * Vai no PDF final, depois do documento. Traz o `original_sha256` — o hash
     * do arquivo que a pessoa leu no tablet —, a trilha de eventos, a
     * miniatura da foto e o QR de validação.
     */
    private function manifest(SignatureDocument $document): string
    {
        $disk = Storage::disk(config('signature.disk'));

        $fotos = [];

        foreach ($document->signers()->with('evidence')->get() as $signatario) {
            $caminho = $signatario->evidence?->photo_path;

            if ($caminho && $disk->exists($caminho)) {
                $fotos[$signatario->id] = 'data:image/jpeg;base64,' . base64_encode($disk->get($caminho));
            }
        }

        $url = url('/validar/' . $document->validation_code);

        return view('signature.pdf.manifest', [
            'document' => $document,
            'signers' => $document->signers()->with('evidence')->get(),
            'events' => $document->auditEvents()->get(),
            'photos' => $fotos,
            'validationUrl' => $url,
            'qr' => $this->qrCodes->dataUri($url, 108),
        ])->render();
    }

    /** Os bytes do PDF. */
    public function pdf(SignatureDocument $document, string $mode = self::MODE_ORIGINAL): string
    {
        return Pdf::loadHTML($this->html($document, $mode))
            ->setPaper('a4')
            ->output();
    }

    /**
     * As imagens dos traços, em data URI, indexadas por signatário.
     *
     * Data URI porque o arquivo mora no disco PRIVADO e a assinatura de uma
     * pessoa não tem URL — a mesma decisão da assinatura do diretor nos
     * contratos de freelancer.
     *
     * @return array<int, string>
     */
    private function signatureImages(SignatureDocument $document): array
    {
        $disk = Storage::disk(config('signature.disk'));
        $imagens = [];

        foreach ($document->signers()->with('evidence')->get() as $signatario) {
            $caminho = $signatario->evidence?->signature_path;

            if (!$caminho || !$disk->exists($caminho)) {
                continue;
            }

            $imagens[$signatario->id] = 'data:image/png;base64,' . base64_encode($disk->get($caminho));
        }

        return $imagens;
    }
}
