<?php

namespace App\Services\Signature;

use App\Models\SignatureDocument;
use App\Models\SignatureLayout;
use App\Models\SignatureTemplate;
use App\Support\PngTrimmer;
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

    public function __construct(
        private SignatureQrCode $qrCodes,
        private SignaturePdfStamper $stamper,
    ) {
    }

    /**
     * O que aparece no lugar de um campo que ainda não tem valor — a resposta
     * que o signatário ainda não deu, a data da assinatura que ainda não
     * chegou. Uma lacuna, como num formulário em papel.
     */
    public const BLANK = '________________';

    /**
     * O corpo do documento com os campos DO ATENDENTE substituídos.
     *
     * Dois marcadores ficam de fora, e ficam no snapshot:
     *
     *  - o da assinatura, resolvido no render, que é quem sabe se está
     *    montando o original (campo em branco) ou o final (imagem do traço);
     *  - os dos campos respondidos por quem assina e os automáticos, que só
     *    têm valor no ato da assinatura — ver `resolved()`.
     *
     * Quem responde cada campo é decisão do documento (o atendente marca o que
     * vai ao tablet) — por isso recebe o documento, e não só o modelo.
     */
    public function body(SignatureDocument $document): string
    {
        $corpo = $document->template->body_html;
        $data = $document->data ?? [];

        $doAtendente = [];
        $deOutros = [];

        foreach ($document->fieldDefinitions() as $campo) {
            if ($campo['ask_signer'] || SignatureFieldTypes::isAutomatic($campo['type'])) {
                $deOutros[$campo['key']] = true;
            } else {
                $doAtendente[$campo['key']] = $campo;
            }
        }

        foreach ($doAtendente as $chave => $campo) {
            $corpo = str_replace(
                '[[' . $chave . ']]',
                $this->escape(SignatureFieldTypes::format($campo, $data[$chave] ?? null)),
                $corpo,
            );
        }

        // Chave que veio nos dados sem estar declarada no modelo: entra como
        // texto, como sempre entrou.
        foreach ($data as $chave => $valor) {
            if (isset($doAtendente[$chave]) || isset($deOutros[$chave])) {
                continue;
            }

            $corpo = str_replace(
                '[[' . $chave . ']]',
                $this->escape(is_scalar($valor) ? (string) $valor : ''),
                $corpo,
            );
        }

        return $corpo;
    }

    /**
     * O corpo com os dados do ato da assinatura no lugar: as respostas de quem
     * assina e a data automática.
     *
     * Parte do `body_snapshot` quando o documento já foi congelado — o
     * snapshot nunca é regravado, e é por isso que a pessoa pode corrigir uma
     * resposta no tablet antes de assinar. O que ainda não tem valor sai como
     * lacuna.
     */
    public function resolved(SignatureDocument $document): string
    {
        $corpo = $document->body_snapshot
            ?? $this->body($document);

        $valores = $document->signing_data ?? [];

        foreach ($document->fieldDefinitions() as $campo) {
            if (!$campo['ask_signer'] && !SignatureFieldTypes::isAutomatic($campo['type'])) {
                continue;
            }

            $texto = SignatureFieldTypes::format($campo, $valores[$campo['key']] ?? null);

            $corpo = str_replace(
                '[[' . $campo['key'] . ']]',
                $texto === '' ? self::BLANK : $this->escape($texto),
                $corpo,
            );
        }

        return $corpo;
    }

    /**
     * Um valor pronto para entrar no HTML do documento.
     *
     * Além de escapar, tira os colchetes de circulação: um valor que
     * contivesse `[[assinatura]]` — ou o marcador de outro campo — seria
     * substituído na passada seguinte, e um texto digitado no tablet passaria
     * a decidir onde fica a área de assinatura.
     */
    private function escape(string $texto): string
    {
        return str_replace(['[', ']'], ['&#91;', '&#93;'], nl2br(e($texto), false));
    }

    /**
     * Campos obrigatórios DO ATENDENTE que ficaram sem valor. Devolve os
     * rótulos, que é o que a tela mostra.
     *
     * Os que quem assina responde não entram: a obrigatoriedade deles é
     * conferida no tablet, quando a resposta chega.
     *
     * @return array<int, string>
     */
    public function missingVariables(SignatureDocument $document): array
    {
        $data = $document->data ?? [];
        $faltando = [];

        foreach ($document->attendantFields() as $variavel) {
            if ($variavel['required'] && SignatureFieldTypes::isEmpty($data[$variavel['key']] ?? null)) {
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
    public function html(
        SignatureDocument $document,
        string $mode = self::MODE_ORIGINAL,
        bool $withManifest = true,
    ): string {
        return view('signature.pdf.document', [
            'document' => $document,
            // Documento enviado em PDF não tem corpo aqui: as páginas dele são
            // as do arquivo. Deste HTML só sai o manifesto.
            'body' => $document->isUploaded()
                ? null
                : $this->placeSignatures($this->resolved($document), $document, $mode),
            'mode' => $mode,
            'geometry' => $this->geometry($document),
            // A página de manifesto só existe no PDF final: é o relatório das
            // evidências, e não parte do que a pessoa leu e assinou.
            'manifest' => $mode === self::MODE_FINAL && $withManifest ? $this->manifest($document) : null,
        ])->render();
    }

    /**
     * As medidas da página deste documento: papel timbrado e faixa do visto.
     *
     * Congelado, o papel timbrado é o que estava vigente no congelamento —
     * inclusive "nenhum". Trocar o logotipo da empresa não muda a cara de um
     * contrato que alguém já leu.
     */
    public function geometry(SignatureDocument $document): SignaturePageGeometry
    {
        $layout = $document->signature_layout_id
            ? $document->layout
            : ($document->isFrozen() ? null : SignatureLayout::current());

        return new SignaturePageGeometry($layout, (bool) $document->template->requires_initials);
    }

    /**
     * Põe as áreas de assinatura no lugar dos marcadores.
     *
     * Três regras, nesta ordem:
     *
     *  - `[[assinatura:contratante]]` recebe quem assina por aquela parte;
     *  - `[[assinatura]]` recebe quem sobrou — signatário sem parte, ou de uma
     *    parte cujo marcador não está no texto;
     *  - sem lugar nenhum para quem sobrou, a área vai para o fim. É o que faz
     *    um modelo escrito às pressas continuar gerando um documento
     *    assinável, em vez de um documento sem onde assinar.
     *
     * Marcadores VIZINHOS (um logo depois do outro, sem texto entre eles) saem
     * lado a lado, dois por linha — é como um contrato dispõe Contratante e
     * Contratado, e é o que a pessoa desenhou no Word com uma tabela.
     *
     * @param  self::MODE_*  $mode
     */
    private function placeSignatures(string $corpo, SignatureDocument $document, string $mode): string
    {
        $generico = $document->template->signature_placeholder ?: '[[assinatura]]';
        $imagens = $mode === self::MODE_FINAL ? $this->signatureImages($document) : [];

        // '' é o grupo de quem vai para o marcador genérico.
        $grupos = $document->signers()->get()->groupBy(
            fn($signer) => $signer->party && str_contains($corpo, SignatureTemplate::partyPlaceholder($signer->party))
                ? $signer->party
                : '',
        );

        $area = function (string $parte) use ($grupos, $document, $mode, $imagens): string {
            $signers = $grupos->get($parte);

            if (!$signers || $signers->isEmpty()) {
                return '';
            }

            return view('signature.pdf.signature-area', [
                'document' => $document,
                'signers' => $signers,
                'mode' => $mode,
                'signatureImages' => $imagens,
            ])->render();
        };

        $temGenerico = str_contains($corpo, $generico);

        $marcador = '(?:' . preg_quote($generico, '/') . '|\[\[assinatura:([a-z][a-z0-9_]*)\]\])';

        $corpo = (string) preg_replace_callback(
            '/(?:' . $marcador . '\s*){2,}/',
            function (array $achado) use ($marcador, $area): string {
                preg_match_all('/' . $marcador . '/', $achado[0], $marcadores);

                $celulas = array_values(array_filter(
                    array_map(fn(string $parte) => $area($parte), $marcadores[1]),
                    fn(string $html) => $html !== '',
                ));

                if (count($celulas) < 2) {
                    return $celulas[0] ?? '';
                }

                $linhas = '';

                foreach (array_chunk($celulas, 2) as $par) {
                    $linhas .= '<tr><td>' . $par[0] . '</td><td>' . ($par[1] ?? '') . '</td></tr>';
                }

                return '<table class="sig-grid">' . $linhas . '</table>';
            },
            $corpo,
        );

        $corpo = (string) preg_replace_callback(
            '/\[\[assinatura:([a-z][a-z0-9_]*)\]\]/',
            fn(array $achado) => $area($achado[1]),
            $corpo,
        );

        return $temGenerico
            ? str_replace($generico, $area(''), $corpo)
            : $corpo . $area('');
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

    /**
     * O relatório de validação do documento assinado pelo gov.br, em HTML.
     *
     * É o manifesto do gov.br, mas em PDF À PARTE: acrescentar páginas ao
     * arquivo assinado desfaria as assinaturas. Sai da conferência que fechou
     * o documento (`govbr_check_id`).
     *
     * @param  string  $finalSha256  hash do PDF assinado que vira o final
     */
    public function govbrReportHtml(SignatureDocument $document, string $finalSha256): string
    {
        $url = url('/validar/' . $document->validation_code);

        return view('signature.pdf.govbr-report', [
            'document' => $document,
            'check' => $document->govbrFinalCheck,
            'signers' => $document->signers()->with(['govbrCheck', 'govbrInvites'])->get(),
            'events' => $document->auditEvents()->get(),
            'finalSha256' => $finalSha256,
            'validationUrl' => $url,
            'qr' => $this->qrCodes->dataUri($url, 100),
        ])->render();
    }

    /** O relatório de validação do gov.br, em PDF. Ver govbrReportHtml(). */
    public function govbrReport(SignatureDocument $document, string $finalSha256): string
    {
        return $this->newPdf($this->govbrReportHtml($document, $finalSha256))->output();
    }

    /** Os bytes do PDF. */
    public function pdf(SignatureDocument $document, string $mode = self::MODE_ORIGINAL): string
    {
        /*
         | Documento PRONTO: as páginas são as do arquivo enviado, e o sistema
         | só carimba por cima (SignaturePdfStamper). O manifesto continua
         | saindo daqui, como PDF à parte, e é costurado ao fim.
         */
        if ($document->isUploaded()) {
            return $this->stamper->build(
                $document,
                $mode,
                $mode === self::MODE_FINAL ? $this->newPdf($this->html($document, $mode))->output() : null,
            );
        }

        $pdf = $this->newPdf($this->html($document, $mode));
        $geometry = $this->geometry($document);

        if (!$geometry->initials) {
            return $pdf->output();
        }

        /*
         | O visto vai nas páginas do DOCUMENTO, e não nas do manifesto: o
         | manifesto é anexo gerado depois, e ninguém o rubricou. Como ele
         | começa sempre em folha nova, basta saber quantas páginas o documento
         | tem sem ele — e a forma de saber é montá-lo sem ele.
         */
        $ultimaPagina = $mode === self::MODE_FINAL
            ? $this->pageCount($this->html($document, $mode, false))
            : null;

        $pdf->render();

        $temporarios = [];

        try {
            $this->stampInitials($pdf->getDomPDF(), $document, $mode, $geometry, $ultimaPagina, $temporarios);

            return $pdf->output();
        } finally {
            foreach ($temporarios as $arquivo) {
                @unlink($arquivo);
            }
        }
    }

    /**
     * Uma instância NOVA do gerador por PDF. A fachada `Pdf` guarda a mesma
     * instância entre chamadas, e aqui dois documentos são montados em
     * sequência (um só para contar páginas) — o desenho do visto num não pode
     * cair no canvas do outro.
     */
    private function newPdf(string $html): \Barryvdh\DomPDF\PDF
    {
        return app('dompdf.wrapper')->loadHTML($html)->setPaper('a4');
    }

    private function pageCount(string $html): int
    {
        $pdf = $this->newPdf($html);
        $pdf->render();

        return $pdf->getDomPDF()->getCanvas()->get_page_count();
    }

    /**
     * Desenha o visto de cada signatário no pé de cada página.
     *
     * No original são as caixas EM BRANCO — o documento que a pessoa lê mostra
     * onde o visto dela vai ficar. No final, cada caixa recebe a rubrica que
     * ela desenhou no tablet.
     *
     * É desenhado direto na página, depois de o PDF estar montado, e não como
     * bloco fixo do HTML: um bloco fixo se repetiria também nas páginas do
     * manifesto.
     *
     * @param  self::MODE_*  $mode
     * @param  array<int, string>  $temporarios  arquivos a apagar depois do output
     */
    private function stampInitials(
        \Dompdf\Dompdf $dompdf,
        SignatureDocument $document,
        string $mode,
        SignaturePageGeometry $geometry,
        ?int $ultimaPagina,
        array &$temporarios,
    ): void {
        $signers = $document->signers()->with('evidence')->get()->values();

        if ($signers->isEmpty()) {
            return;
        }

        $vistos = $mode === self::MODE_FINAL ? $this->initialsFiles($signers, $temporarios) : [];

        $pt = SignaturePageGeometry::PT_PER_PX;
        $largura = SignaturePageGeometry::VISTO_BOX_WIDTH;
        $altura = SignaturePageGeometry::VISTO_BOX_HEIGHT;
        $vao = SignaturePageGeometry::VISTO_GAP;

        $topo = $geometry->vistoTop();

        // Bloco alinhado à direita, na ordem em que as pessoas assinam.
        $inicio = SignaturePageGeometry::PAGE_WIDTH - SignaturePageGeometry::SIDE
            - $signers->count() * $largura - ($signers->count() - 1) * $vao;

        $dompdf->getCanvas()->page_script(function (
            int $pagina,
            int $total,
            $canvas,
            $fontMetrics,
        ) use ($signers, $vistos, $ultimaPagina, $pt, $largura, $altura, $vao, $topo, $inicio) {
            if ($ultimaPagina !== null && $pagina > $ultimaPagina) {
                return;
            }

            $fonte = $fontMetrics->getFont('DejaVu Sans', 'normal');

            foreach ($signers as $i => $signer) {
                $x = $inicio + $i * ($largura + $vao);

                $canvas->rectangle($x * $pt, $topo * $pt, $largura * $pt, $altura * $pt, [0.82, 0.78, 0.77], 0.5);

                $canvas->text(
                    $x * $pt,
                    ($topo + $altura + 3) * $pt,
                    'Visto — ' . \Illuminate\Support\Str::limit($signer->capacityLabel() === $signer->roleLabel()
                        ? $signer->name
                        : $signer->capacityLabel(), 22, '…'),
                    $fonte,
                    5.5,
                    [0.43, 0.38, 0.38],
                );

                if (!isset($vistos[$signer->id])) {
                    continue;
                }

                // A rubrica cabe na caixa com folga, sem deformar.
                [$arquivo, $w, $h] = $vistos[$signer->id];

                $escala = min(($largura - 10) / $w, ($altura - 6) / $h);
                $w *= $escala;
                $h *= $escala;

                $canvas->image(
                    $arquivo,
                    ($x + ($largura - $w) / 2) * $pt,
                    ($topo + ($altura - $h) / 2) * $pt,
                    $w * $pt,
                    $h * $pt,
                );
            }
        });
    }

    /**
     * Os vistos em arquivo temporário — o canvas do PDF só desenha imagem a
     * partir de caminho, e as rubricas moram no disco privado do módulo.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\SignatureSigner>  $signers
     * @param  array<int, string>  $temporarios
     * @return array<int, array{0: string, 1: int, 2: int}>  signatário => [arquivo, largura, altura]
     */
    private function initialsFiles($signers, array &$temporarios): array
    {
        $disk = Storage::disk(config('signature.disk'));
        $arquivos = [];

        foreach ($signers as $signer) {
            $caminho = $signer->evidence?->initials_path;

            if (!$caminho || !$disk->exists($caminho)) {
                continue;
            }

            $bytes = $disk->get($caminho);
            $info = @getimagesizefromstring($bytes);

            if (!$info) {
                continue;
            }

            $arquivo = tempnam(sys_get_temp_dir(), 'visto') . '.png';
            file_put_contents($arquivo, $bytes);

            // tempnam() cria o arquivo sem extensão; os dois saem no fim.
            $temporarios[] = $arquivo;
            $temporarios[] = substr($arquivo, 0, -4);

            $arquivos[$signer->id] = [$arquivo, $info[0], $info[1]];
        }

        return $arquivos;
    }

    /**
     * As imagens dos traços, em data URI, indexadas por signatário.
     *
     * Data URI porque o arquivo mora no disco PRIVADO e a assinatura de uma
     * pessoa não tem URL — a mesma decisão da assinatura do diretor nos
     * contratos de freelancer.
     *
     * O tablet manda a tela de desenho inteira, quase toda vazia: reduzida à
     * área de assinatura, ela virava um risquinho. A imagem é RECORTADA ao
     * traço (como no PDF enviado pronto, ver SignaturePdfStamper) e ganha o
     * tamanho que cabe na caixa SIGNATURE_BOX_*, sem distorcer. O arquivo
     * gravado (a evidência) não muda: o recorte é só para imprimir.
     *
     * @return array<int, array{src: string, width: int, height: int}>
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

            $bytes = PngTrimmer::trim((string) $disk->get($caminho));
            [$largura, $altura] = $this->fitSignature($bytes);

            $imagens[$signatario->id] = [
                'src' => 'data:image/png;base64,' . base64_encode($bytes),
                'width' => $largura,
                'height' => $altura,
            ];
        }

        return $imagens;
    }

    /** A caixa em que o traço é impresso, em px do PDF. A linha de assinatura tem 300px. */
    public const SIGNATURE_BOX_WIDTH = 280;

    public const SIGNATURE_BOX_HEIGHT = 100;

    /**
     * O maior tamanho que cabe na caixa mantendo a proporção do traço.
     * Sem ler a imagem, a caixa inteira de altura e largura proporcional
     * de uma assinatura comum (3:1).
     *
     * @return array{0: int, 1: int}
     */
    private function fitSignature(string $png): array
    {
        $info = @getimagesizefromstring($png);

        if (!$info || $info[0] < 1 || $info[1] < 1) {
            return [min(self::SIGNATURE_BOX_WIDTH, self::SIGNATURE_BOX_HEIGHT * 3), self::SIGNATURE_BOX_HEIGHT];
        }

        $escala = min(self::SIGNATURE_BOX_WIDTH / $info[0], self::SIGNATURE_BOX_HEIGHT / $info[1]);

        return [max(1, (int) round($info[0] * $escala)), max(1, (int) round($info[1] * $escala))];
    }
}
