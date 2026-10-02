<?php

namespace App\Http\Controllers\Signature;

use App\Http\Controllers\Controller;
use App\Models\SignatureDocument;
use App\Models\SignatureLayout;
use App\Services\Signature\SignaturePageGeometry;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * O papel timbrado dos documentos assinados: cabeçalho e rodapé da empresa.
 *
 * Um só para o módulo inteiro — é a identidade da empresa, e não uma escolha
 * de cada modelo. Salvar NÃO altera a linha em uso: cria a seguinte, e os
 * documentos já congelados continuam com a que tinham. Ver SignatureLayout.
 */
class LayoutController extends Controller
{
    public function edit()
    {
        $layout = SignatureLayout::current();

        return view('signature.layout.edit', [
            'layout' => $layout,
            'header' => $this->dataUri($layout, 'header'),
            'footer' => $this->dataUri($layout, 'footer'),
        ]);
    }

    public function update(Request $request)
    {
        $dados = $request->validate([
            'header_image' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:2048'],
            'footer_image' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:2048'],
            'remove_header' => ['nullable', 'boolean'],
            'remove_footer' => ['nullable', 'boolean'],
            'header_height_mm' => ['required', 'integer', 'min:8', 'max:60'],
            'footer_height_mm' => ['required', 'integer', 'min:6', 'max:50'],
            'footer_text' => ['nullable', 'string', 'max:500'],
            'full_width' => ['nullable', 'boolean'],
            'align' => ['required', Rule::in(array_keys(SignatureLayout::ALIGNMENTS))],
        ], [
            'header_image.mimes' => 'A imagem do cabeçalho deve ser PNG ou JPG.',
            'footer_image.mimes' => 'A imagem do rodapé deve ser PNG ou JPG.',
            'header_image.max' => 'A imagem do cabeçalho passa de 2 MB.',
            'footer_image.max' => 'A imagem do rodapé passa de 2 MB.',
        ], [
            'header_height_mm' => 'altura do cabeçalho',
            'footer_height_mm' => 'altura do rodapé',
            'footer_text' => 'texto do rodapé',
        ]);

        $atual = SignatureLayout::current();

        SignatureLayout::create([
            'header_path' => $this->path($request->file('header_image'), $dados['remove_header'] ?? false, $atual?->header_path),
            'footer_path' => $this->path($request->file('footer_image'), $dados['remove_footer'] ?? false, $atual?->footer_path),
            'header_height_mm' => $dados['header_height_mm'],
            'footer_height_mm' => $dados['footer_height_mm'],
            'footer_text' => $dados['footer_text'] ?? null,
            'full_width' => (bool) ($dados['full_width'] ?? false),
            'align' => $dados['align'],
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('signature-layout.edit')
            ->with('success', 'Papel timbrado salvo. Vale para os documentos congelados daqui em diante — '
                . 'os já congelados continuam com o que tinham.');
    }

    /**
     * Uma página de exemplo com o papel timbrado vigente, em PDF.
     *
     * Passa pela MESMA view dos documentos de verdade: uma prévia desenhada à
     * parte mostraria o que a prévia faz, e não o que o documento vai fazer.
     */
    public function preview()
    {
        $documento = new SignatureDocument(['title' => 'Documento de exemplo']);

        $paragrafo = '<p>Este é um documento de exemplo, para conferir o cabeçalho e o rodapé da empresa. '
            . 'O texto dos documentos de verdade vem do modelo e ocupa esta área, entre as duas margens. '
            . 'O rodapé traz sempre o código de validação do documento, logo acima da imagem da empresa.</p>';

        $html = view('signature.pdf.document', [
            'document' => $documento,
            'body' => str_repeat($paragrafo, 14),
            'mode' => 'original',
            'geometry' => new SignaturePageGeometry(SignatureLayout::current(), false),
            'manifest' => null,
        ])->render();

        return response(app('dompdf.wrapper')->loadHTML($html)->setPaper('a4')->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="papel-timbrado-exemplo.pdf"',
            'Cache-Control' => 'private, max-age=0, no-store',
        ]);
    }

    /**
     * O caminho da imagem na linha nova: a enviada agora, nenhuma (removida)
     * ou a mesma de antes.
     *
     * A enviada ganha SEMPRE um arquivo novo. Sobrescrever o anterior mudaria
     * o cabeçalho de todo documento já congelado que aponta para ele.
     */
    private function path(?UploadedFile $arquivo, bool $remover, ?string $atual): ?string
    {
        if ($arquivo) {
            return $arquivo->storeAs(
                config('signature.paths.layouts'),
                Str::uuid() . '.' . ($arquivo->extension() ?: 'png'),
                config('signature.disk'),
            ) ?: null;
        }

        return $remover ? null : $atual;
    }

    /** A imagem para mostrar na tela — o disco é privado e não tem URL. */
    private function dataUri(?SignatureLayout $layout, string $which): ?string
    {
        $bytes = $layout?->imageBytes($which);

        if ($bytes === null || !($info = @getimagesizefromstring($bytes))) {
            return null;
        }

        return 'data:' . $info['mime'] . ';base64,' . base64_encode($bytes);
    }
}
