<?php

namespace App\Http\Controllers\Signature;

use App\Exceptions\DocxImportException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSignatureTemplateRequest;
use App\Models\SignatureDirectorArea;
use App\Models\SignatureDocument;
use App\Models\SignatureTemplate;
use App\Services\Signature\DocxTemplateExporter;
use App\Services\Signature\DocxTemplateImporter;
use App\Services\Signature\LinkedTemplateComposer;
use App\Services\Signature\SignatureAttachmentService;
use App\Services\Signature\SignatureDocumentRenderer;
use App\Services\Signature\SignatureFieldTypes;
use App\Support\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
    public function index(Request $request)
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

        // Ativos e inativos em abas separadas (?aba=inativos): os desativados
        // ficam guardados, mas fora da lista do dia a dia.
        $aba = $request->query('aba') === 'inativos' ? 'inativos' : 'ativos';
        [$ativos, $inativos] = $vigentes->partition(fn(SignatureTemplate $t) => $t->active);

        return view('signature.templates.index', [
            'templates' => $aba === 'inativos' ? $inativos->values() : $ativos->values(),
            'aba' => $aba,
            'totalAtivos' => $ativos->count(),
            'totalInativos' => $inativos->count(),
            'usos' => $usos,
        ]);
    }

    public function create()
    {
        return view('signature.templates.create', [
            'areasDaDiretoria' => $this->directorAreas(),
            'modelosParaVincular' => $this->linkOptions(null),
            'vinculos' => $this->linksForForm(null),
        ]);
    }

    /**
     * Os campos e as partes de um modelo que se quer exigir junto — para a
     * tela montar a correspondência no ato de vincular. É o composto da
     * versão ativa: se ele exige outro, os campos desse vêm junto.
     */
    public function linkInfo(SignatureTemplate $signatureTemplate, LinkedTemplateComposer $composer)
    {
        $info = $this->linkPayload((int) ($signatureTemplate->root_id ?? $signatureTemplate->id), $composer);

        abort_if($info === null, 404);

        return response()->json($info);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function linkPayload(int $root, LinkedTemplateComposer $composer): ?array
    {
        $modelo = $composer->current($root);

        if (!$modelo) {
            return null;
        }

        $composto = $modelo->composed();

        return [
            'root_id' => $root,
            'name' => $modelo->name,
            'version' => $modelo->version,
            'includes' => array_column($composto->composedParts, 'name'),
            'variables' => array_map(fn(array $v) => [
                'key' => $v['key'],
                'label' => $v['label'],
                'type' => $v['type'],
                'type_label' => SignatureFieldTypes::LABELS[$v['type']] ?? $v['type'],
            ], $composto->declaredVariables()),
            'parties' => array_map(
                fn(array $p) => ['key' => $p['key'], 'label' => $p['label']],
                $composto->declaredParties(),
            ),
        ];
    }

    /**
     * Os modelos que podem ser exigidos por este: ativos, de uso comum, menos
     * ele mesmo. Ciclo é recusado na gravação (StoreSignatureTemplateRequest).
     */
    private function linkOptions(?SignatureTemplate $template)
    {
        $minhaRaiz = $template ? (int) ($template->root_id ?? $template->id) : null;

        return SignatureTemplate::whereIn('id', fn($q) => $q->selectRaw('MAX(id)')
            ->from('signature_templates')
            ->groupBy('root_id'))
            ->where('active', true)
            ->where('single_use', false)
            ->when($minhaRaiz, fn($q) => $q->where('root_id', '!=', $minhaRaiz))
            ->orderBy('name')
            ->get(['id', 'root_id', 'name', 'version']);
    }

    /**
     * Os vínculos gravados (ou do envio recusado), com os campos e as partes
     * de cada exigido para a tela.
     *
     * @return array<int, array<string, mixed>>
     */
    private function linksForForm(?SignatureTemplate $template): array
    {
        $composer = app(LinkedTemplateComposer::class);
        $vinculos = old('linked_templates', $template?->declaredLinks() ?? []);

        return collect($vinculos)
            ->map(function ($vinculo) use ($composer) {
                $info = $this->linkPayload((int) ($vinculo['root_id'] ?? 0), $composer);

                return $info ? $info + [
                    'fields' => (array) ($vinculo['fields'] ?? []),
                    'party_map' => (array) ($vinculo['parties'] ?? []),
                ] : null;
            })
            ->filter()
            ->values()
            ->all();
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

    /**
     * A base em Word do modelo, para editar no Word e enviar de novo em
     * "Revisar". Gerada do texto desta versão — o Word de origem não é
     * guardado (ver `importDocx`). Ver DocxTemplateExporter.
     */
    public function docx(SignatureTemplate $signatureTemplate, DocxTemplateExporter $exporter)
    {
        $nome = Str::slug('modelo ' . $signatureTemplate->name . ' v' . $signatureTemplate->version) . '.docx';

        return response($exporter->export($signatureTemplate), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="' . $nome . '"',
            'Cache-Control' => 'private, max-age=0, no-store',
        ]);
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
        return view('signature.templates.edit', [
            'template' => $signatureTemplate,
            'areasDaDiretoria' => $this->directorAreas(),
            'modelosParaVincular' => $this->linkOptions($signatureTemplate),
            'vinculos' => $this->linksForForm($signatureTemplate),
        ]);
    }

    /** As áreas da diretoria que uma parte do modelo pode apontar. */
    private function directorAreas()
    {
        return SignatureDirectorArea::where('active', true)->orderBy('name')->get(['id', 'name']);
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
        $raiz = (int) ($signatureTemplate->root_id ?? $signatureTemplate->id);

        // Modelos ativos que exigem este: sem ele, nenhum documento deles pode
        // ser criado até alguém tirar o vínculo.
        $dependentes = SignatureTemplate::where('active', true)
            ->whereNotNull('linked_templates')
            ->get()
            ->filter(fn(SignatureTemplate $t) => in_array($raiz, array_column($t->declaredLinks(), 'root_id'), true))
            ->pluck('name');
        $aviso = $dependentes->isNotEmpty()
            ? ' Atenção: ' . $dependentes->join(', ') . ' exige(m) este modelo e não gera(m) documento novo até o '
                . 'vínculo sair ou este modelo voltar.'
            : '';

        if ($signatureTemplate->inUse()) {
            SignatureTemplate::where('root_id', $raiz)->update(['active' => false]);

            return redirect()->route('signature-templates.index')
                ->with('success', 'Modelo "' . $nome . '" desativado. Ele não pode ser excluído porque '
                    . 'já gerou documentos, que continuam apontando para o texto que imprimiram.' . $aviso);
        }

        SignatureTemplate::where('root_id', $raiz)->delete();

        return redirect()->route('signature-templates.index')
            ->with('success', 'Modelo "' . $nome . '" excluído.' . $aviso);
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
            'attachments' => SignatureAttachmentService::normalize(array_values($dados['attachments'] ?? []), 'mod'),
            'review_items' => \App\Services\Signature\SignatureReviewService::normalize(array_values($dados['review_items'] ?? [])),
            // Modelos exigidos junto, já conferidos (ciclo, tipos, partes) no request.
            'linked_templates' => $request->linkedTemplates() ?: null,
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
            $partes[$parte['key']] = array_filter([
                'key' => $parte['key'],
                'label' => $parte['label'],
                // Parte assinada por uma área da diretoria: o documento já
                // nasce com o titular dela (Assinatura Diretoria).
                'director_area_id' => !empty($parte['director_area_id']) ? (int) $parte['director_area_id'] : null,
            ], fn($v) => $v !== null);
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
     * automático não ficam guardadas — sobras assim reapareceriam na próxima
     * revisão como se alguém as tivesse escrito.
     *
     * Quem responde o campo não é do modelo: o atendente marca, em cada
     * documento, o que vai ao tablet. A pergunta escrita aqui é só o texto que
     * o tablet mostra quando isso acontece.
     *
     * @param  array<string, mixed>  $variavel
     * @return array<string, mixed>
     */
    private function variable(array $variavel): array
    {
        $tipo = $variavel['type'] ?? SignatureFieldTypes::TEXT;
        $automatico = SignatureFieldTypes::isAutomatic($tipo);

        return [
            'key' => $variavel['key'],
            'label' => $variavel['label'],
            'type' => $tipo,
            // Campo automático nunca fica em branco: obrigatório não se aplica.
            'required' => (bool) ($variavel['required'] ?? false) && !$automatico,
            'question' => $automatico ? null : (trim((string) ($variavel['question'] ?? '')) ?: null),
            // Restrito ao atendente: o documento não pode mandá-lo ao tablet.
            'attendant_only' => !$automatico && (bool) ($variavel['attendant_only'] ?? false),
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
