<?php

namespace App\Http\Controllers\Signature;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSignatureTemplateRequest;
use App\Models\SignatureDocument;
use App\Models\SignatureTemplate;
use App\Services\Signature\SignatureDocumentRenderer;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Facades\DB;

/**
 * Modelos de documento: o texto do termo, da ficha ou do contrato.
 *
 * A tela mostra sempre a versão VIGENTE de cada modelo. Salvar uma alteração
 * não altera a linha em uso: cria a versão seguinte. As anteriores continuam
 * existindo porque é para elas que os documentos já criados apontam — é o que
 * faz um termo assinado em março continuar sendo o termo de março.
 *
 * Por isso não há "editar" que sobrescreva, e o `destroy` de um modelo já
 * usado desativa em vez de apagar.
 */
class TemplateController extends Controller
{
    public function index()
    {
        /*
         | Uma linha por modelo: a maior versão de cada `root_id`. A subconsulta
         | evita trazer o histórico inteiro só para descartá-lo na tela.
         */
        $vigentes = SignatureTemplate::whereIn('id', function ($query) {
            $query->selectRaw('MAX(id)')
                ->from('signature_templates')
                ->groupBy('root_id');
        })
            ->orderBy('name')
            ->get();

        $usos = SignatureDocument::selectRaw('signature_template_id, COUNT(*) as total')
            ->groupBy('signature_template_id')
            ->pluck('total', 'signature_template_id');

        return view('signature.templates.index', [
            'templates' => $vigentes,
            'usos' => $usos,
        ]);
    }

    public function create()
    {
        return view('signature.templates.create');
    }

    public function store(StoreSignatureTemplateRequest $request)
    {
        $template = SignatureTemplate::create($this->attributes($request));

        return redirect()->route('signature-templates.show', $template)
            ->with('success', 'Modelo "' . $template->name . '" criado.');
    }

    public function show(SignatureTemplate $signatureTemplate)
    {
        $versoes = $signatureTemplate->versions()->get();

        $usos = SignatureDocument::selectRaw('signature_template_id, COUNT(*) as total')
            ->whereIn('signature_template_id', $versoes->pluck('id'))
            ->groupBy('signature_template_id')
            ->pluck('total', 'signature_template_id');

        return view('signature.templates.show', [
            'template' => $signatureTemplate,
            'versoes' => $versoes,
            'usos' => $usos,
        ]);
    }

    public function edit(SignatureTemplate $signatureTemplate)
    {
        return view('signature.templates.edit', ['template' => $signatureTemplate]);
    }

    /**
     * Salva a revisão — que é uma versão NOVA, não uma alteração.
     */
    public function update(StoreSignatureTemplateRequest $request, SignatureTemplate $signatureTemplate)
    {
        $nova = DB::transaction(
            fn() => $signatureTemplate->newVersion($this->attributes($request), auth()->id()),
        );

        return redirect()->route('signature-templates.show', $nova)
            ->with('success', 'Modelo revisado: versão ' . $nova->version . ' criada e ativada. '
                . 'Os documentos já emitidos continuam na versão em que foram gerados.');
    }

    /**
     * Modelo nunca usado é apagado; modelo já usado é DESATIVADO.
     *
     * Apagar um modelo que gerou documento quebraria o vínculo do documento
     * com o texto que ele imprime — e é o vínculo que sustenta a versão.
     */
    public function destroy(SignatureTemplate $signatureTemplate)
    {
        $nome = $signatureTemplate->name;

        if ($signatureTemplate->inUse()) {
            SignatureTemplate::where('root_id', $signatureTemplate->root_id ?? $signatureTemplate->id)
                ->update(['active' => false]);

            return redirect()->route('signature-templates.index')
                ->with('success', 'Modelo "' . $nome . '" desativado. Ele não pode ser excluído porque '
                    . 'já gerou documentos, que continuam apontando para o texto que imprimiram.');
        }

        SignatureTemplate::where('root_id', $signatureTemplate->root_id ?? $signatureTemplate->id)->delete();

        return redirect()->route('signature-templates.index')
            ->with('success', 'Modelo "' . $nome . '" excluído.');
    }

    /**
     * Os campos gravados, com o HTML já saneado.
     *
     * O saneamento acontece na GRAVAÇÃO, e não na exibição: o corpo é
     * renderizado sem escapar no PDF e no tablet, e sanear na saída deixaria a
     * responsabilidade em cada um dos lugares que exibem.
     *
     * @return array<string, mixed>
     */
    private function attributes(StoreSignatureTemplateRequest $request): array
    {
        $dados = $request->validated();

        return [
            'name' => $dados['name'],
            'description' => $dados['description'] ?? null,
            'body_html' => HtmlSanitizer::clean(
                $dados['body_html'],
                SignatureDocumentRenderer::EXTRA_HTML_TAGS,
            ),
            'signature_placeholder' => $dados['signature_placeholder'] ?: '[[assinatura]]',
            'variables' => array_values($dados['variables'] ?? []),
            'requires_photo' => (bool) ($dados['requires_photo'] ?? false),
            'identity_check' => $dados['identity_check'],
            'retention_months' => $dados['retention_months'] ?? null,
            'created_by' => auth()->id(),
        ];
    }
}
