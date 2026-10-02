<?php

namespace App\Http\Controllers\Signature;

use App\Exceptions\DocxImportException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSignatureTemplateRequest;
use App\Models\SignatureDocument;
use App\Models\SignatureTemplate;
use App\Services\Signature\DocxTemplateImporter;
use App\Services\Signature\SignatureDocumentRenderer;
use App\Services\Signature\SignatureFieldTypes;
use App\Support\HtmlSanitizer;
use Illuminate\Http\Request;
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
            // Os de uso único pertencem a UM documento enviado em PDF — não
            // são modelos de ninguém.
            ->where('single_use', false)
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

    /**
     * Converte um .docx no texto e nos campos do modelo — sem gravar nada.
     *
     * A resposta preenche o formulário que está aberto; quem grava continua
     * sendo o `store`/`update`, depois de a pessoa conferir a prévia. Por isso
     * o arquivo não é guardado: o modelo é o HTML convertido, e o Word é só o
     * jeito de escrevê-lo.
     */
    public function importDocx(Request $request, DocxTemplateImporter $importer)
    {
        $request->validate(
            ['arquivo' => ['required', 'file', 'max:10240']],
            [
                'arquivo.required' => 'Escolha o arquivo do Word (.docx).',
                'arquivo.file' => 'O envio do arquivo falhou. Tente de novo.',
                'arquivo.max' => 'O arquivo passa de 10 MB. Um modelo é só o texto — retire as imagens e envie de novo.',
            ],
        );

        try {
            return response()->json($importer->import($request->file('arquivo')->getRealPath()));
        } catch (DocxImportException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
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

        $corpo = HtmlSanitizer::clean($dados['body_html'], SignatureDocumentRenderer::EXTRA_HTML_TAGS);
        $marcador = ($dados['signature_placeholder'] ?? null) ?: '[[assinatura]]';

        return [
            'name' => $dados['name'],
            'description' => $dados['description'] ?? null,
            'body_html' => $corpo,
            'signature_placeholder' => $marcador,
            'variables' => $this->variables($corpo, $marcador, array_map(
                fn(array $variavel) => $this->variable($variavel),
                array_values($dados['variables'] ?? []),
            )),
            'parties' => $this->parties($corpo, array_values($dados['parties'] ?? [])),
            'requires_photo' => (bool) ($dados['requires_photo'] ?? false),
            'requires_initials' => (bool) ($dados['requires_initials'] ?? false),
            'identity_check' => $dados['identity_check'],
            'retention_months' => $dados['retention_months'] ?? null,
            'created_by' => auth()->id(),
        ];
    }

    /**
     * As partes declaradas, mais as que têm marcador no texto e ninguém
     * declarou — a mesma rede dos campos. Um `[[assinatura:contratado]]` sem
     * parte declarada não teria quem assinar por ele, e sumiria do documento.
     *
     * @param  array<int, array<string, mixed>>  $declaradas
     * @return array<int, array{key: string, label: string}>
     */
    private function parties(string $corpo, array $declaradas): array
    {
        $partes = [];

        foreach ($declaradas as $parte) {
            $partes[$parte['key']] = ['key' => $parte['key'], 'label' => $parte['label']];
        }

        preg_match_all('/\[\[assinatura:([a-z][a-z0-9_]{0,59})\]\]/', $corpo, $achados);

        foreach (array_unique($achados[1]) as $chave) {
            $partes[$chave] ??= ['key' => $chave, 'label' => DocxTemplateImporter::labelFor($chave)];
        }

        return array_values($partes);
    }

    /**
     * Um campo como é gravado: só o que o tipo dele usa.
     *
     * Opções de um campo que deixou de ser de escolha e pergunta de um campo
     * que voltou a ser do atendente não ficam guardadas — sobras assim
     * reapareceriam na próxima revisão como se alguém as tivesse escrito.
     *
     * @param  array<string, mixed>  $variavel
     * @return array<string, mixed>
     */
    private function variable(array $variavel): array
    {
        $tipo = $variavel['type'] ?? SignatureFieldTypes::TEXT;
        $pergunta = (bool) ($variavel['ask_signer'] ?? false) && !SignatureFieldTypes::isAutomatic($tipo);

        return [
            'key' => $variavel['key'],
            'label' => $variavel['label'],
            'type' => $tipo,
            // Campo automático nunca fica em branco: obrigatório não se aplica.
            'required' => (bool) ($variavel['required'] ?? false) && !SignatureFieldTypes::isAutomatic($tipo),
            'ask_signer' => $pergunta,
            'question' => $pergunta ? (trim((string) ($variavel['question'] ?? '')) ?: null) : null,
            'options' => SignatureFieldTypes::hasOptions($tipo) ? array_values($variavel['options'] ?? []) : [],
        ];
    }

    /**
     * As variáveis declaradas, mais as que estão no texto e ninguém declarou.
     *
     * Um `[[campo]]` sem declaração não vira campo no formulário do atendente
     * e sairia IMPRESSO no documento, com colchetes e tudo, para a pessoa
     * assinar. Declarar por conta própria é a rede: quem escreve o modelo não
     * precisa saber que existe uma segunda lista para manter em dia.
     *
     * @param  array<int, array<string, mixed>>  $declaradas
     * @return array<int, array<string, mixed>>
     */
    private function variables(string $corpo, string $marcador, array $declaradas): array
    {
        $conhecidas = array_column($declaradas, 'key');

        preg_match_all('/\[\[([a-z][a-z0-9_]{0,59})\]\]/', str_replace($marcador, '', $corpo), $achados);

        foreach (array_unique($achados[1]) as $chave) {
            if (!in_array($chave, $conhecidas, true)) {
                $declaradas[] = $this->variable([
                    'key' => $chave,
                    'label' => DocxTemplateImporter::labelFor($chave),
                    'required' => true,
                ]);
            }
        }

        return $declaradas;
    }
}
