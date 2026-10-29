<?php

namespace App\Http\Requests;

use App\Models\SignatureTemplate;
use App\Services\Signature\LinkedTemplateComposer;
use App\Services\Signature\SignatureFieldTypes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Criação e revisão de um modelo de documento.
 *
 * Serve às duas operações porque são a mesma coisa: revisar um modelo não
 * altera a linha em uso, cria a versão seguinte com estes mesmos campos.
 */
class StoreSignatureTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * As opções de um campo de escolha chegam como texto, uma por linha — é
     * como a pessoa as digita na tela. Viram lista antes de validar.
     */
    protected function prepareForValidation(): void
    {
        $variaveis = collect($this->input('variables', []))
            ->map(function ($variavel) {
                if (is_array($variavel) && is_string($variavel['options'] ?? null)) {
                    $variavel['options'] = array_values(array_unique(array_filter(
                        array_map('trim', preg_split('/\R/u', $variavel['options']) ?: []),
                        fn(string $linha) => $linha !== '',
                    )));
                }

                return $variavel;
            })
            ->all();

        $this->merge(['variables' => $variaveis]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],

            // O corpo é saneado no controller antes de gravar; a validação
            // aqui é de tamanho, não de conteúdo.
            'body_html' => ['required', 'string', 'max:200000'],

            'signature_placeholder' => ['nullable', 'string', 'max:60'],

            // As variáveis que a tela do atendente vai pedir.
            'variables' => ['nullable', 'array', 'max:40'],
            'variables.*.key' => ['required', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_]*$/'],
            'variables.*.label' => ['required', 'string', 'max:120'],
            'variables.*.required' => ['nullable', 'boolean'],
            'variables.*.type' => ['nullable', Rule::in(array_keys(SignatureFieldTypes::LABELS))],
            // Respondido por quem assina, no tablet, em vez de pelo atendente.
            'variables.*.question' => ['nullable', 'string', 'max:200'],
            // Só o atendente responde: nenhum documento pode perguntar este campo a quem assina.
            'variables.*.attendant_only' => ['nullable', 'boolean'],
            'variables.*.options' => ['nullable', 'array', 'max:30'],
            'variables.*.options.*' => ['string', 'max:120'],

            // As partes que assinam ("Contratante", "Contratado"). A chave é
            // o que vai no marcador: [[assinatura:contratante]].
            'parties' => ['nullable', 'array', 'max:10'],
            'parties.*.key' => ['required', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_]*$/'],
            'parties.*.label' => ['required', 'string', 'max:120'],
            // Assinatura Diretoria: a área cujo titular já vem escolhido para a parte.
            'parties.*.director_area_id' => ['nullable', 'integer', 'exists:signature_director_areas,id'],

            // Anexos que todo documento deste modelo pede (identidade,
            // comprovante). A chave sai do rótulo — ver SignatureAttachmentService.
            'attachments' => ['nullable', 'array', 'max:20'],
            'attachments.*.label' => ['nullable', 'string', 'max:120'],
            'attachments.*.required' => ['nullable', 'boolean'],
            'review_items' => ['nullable', 'array', 'max:30'],
            'review_items.*.label' => ['nullable', 'string', 'max:120'],

            'requires_photo' => ['nullable', 'boolean'],
            'requires_initials' => ['nullable', 'boolean'],
            'identity_check' => ['required', Rule::in(array_keys(SignatureTemplate::IDENTITY_CHECKS))],

            'retention_months' => ['nullable', 'integer', 'min:1', 'max:1200'],

            // Modelos que todo documento deste exige junto, com a correspondência
            // de campos e partes (LinkedTemplateComposer).
            'linked_templates' => ['nullable', 'array', 'max:5'],
            'linked_templates.*.root_id' => ['required', 'integer'],
            'linked_templates.*.fields' => ['nullable', 'array'],
            'linked_templates.*.fields.*' => ['nullable', 'string', 'max:60'],
            'linked_templates.*.parties' => ['nullable', 'array'],
            'linked_templates.*.parties.*' => ['nullable', 'string', 'max:60'],
        ];
    }

    /**
     * Campo de escolha sem o que escolher não é campo: a pessoa chegaria a uma
     * pergunta sem resposta possível.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            foreach ((array) $this->input('variables', []) as $i => $variavel) {
                $tipo = $variavel['type'] ?? SignatureFieldTypes::TEXT;

                if (is_string($tipo)
                    && SignatureFieldTypes::hasOptions($tipo)
                    && count((array) ($variavel['options'] ?? [])) < 2) {
                    $validator->errors()->add(
                        "variables.{$i}.options",
                        'O campo "' . ($variavel['label'] ?? $variavel['key'] ?? '') . '" é de escolha: '
                            . 'informe ao menos duas opções, uma por linha.',
                    );
                }
            }

            $this->validateLinks($validator);
        });
    }

    /**
     * Os vínculos, conferidos e na forma gravada — ver `validateLinks`.
     *
     * @var array<int, array{root_id: int, fields: array<string, ?string>, parties: array<string, string>}>
     */
    private array $linkedTemplates = [];

    /** @return array<int, array{root_id: int, fields: array<string, ?string>, parties: array<string, string>}> */
    public function linkedTemplates(): array
    {
        return $this->linkedTemplates;
    }

    /**
     * Confere cada modelo exigido: existe e está ativo, não é este nem exige
     * este (ciclo), aparece uma vez, e cada correspondência aponta para um
     * campo deste modelo do MESMO tipo e para uma parte deste modelo (ou
     * "todos"/"nova").
     *
     * Correspondência de campo ou parte que o exigido não tem é descartada:
     * ele pode ter sido revisado entre abrir a tela e salvar.
     */
    private function validateLinks(\Illuminate\Validation\Validator $validator): void
    {
        $composer = app(LinkedTemplateComposer::class);
        $esteModelo = $this->route('signatureTemplate');
        $minhaRaiz = is_object($esteModelo) ? (int) ($esteModelo->root_id ?? $esteModelo->id) : null;

        // Os campos e as partes deste modelo como estão na tela, mais os
        // marcadores do texto que ninguém declarou (o controller os declara
        // como texto).
        $corpo = (string) $this->input('body_html', '');
        $tiposDeX = [];

        foreach ((array) $this->input('variables', []) as $v) {
            if (is_array($v) && is_string($v['key'] ?? null)) {
                $tiposDeX[$v['key']] = SignatureFieldTypes::exists($v['type'] ?? null) ? $v['type'] : SignatureFieldTypes::TEXT;
            }
        }

        preg_match_all('/\[\[([a-z][a-z0-9_]{0,59})\]\]/', $corpo, $achados);

        foreach ($achados[1] as $chave) {
            $tiposDeX[$chave] ??= SignatureFieldTypes::TEXT;
        }

        $partesDeX = array_filter(array_map(
            fn($p) => is_array($p) && is_string($p['key'] ?? null) ? $p['key'] : null,
            (array) $this->input('parties', []),
        ));
        preg_match_all('/\[\[assinatura:([a-z][a-z0-9_]{0,59})\]\]/', $corpo, $achados);
        $partesDeX = array_values(array_unique(array_merge($partesDeX, $achados[1])));

        $vistos = [];

        foreach (array_values((array) $this->input('linked_templates', [])) as $i => $vinculo) {
            $raiz = (int) (is_array($vinculo) ? ($vinculo['root_id'] ?? 0) : 0);
            $atributo = "linked_templates.{$i}.root_id";
            $y = $raiz > 0 ? $composer->current($raiz) : null;

            if (!$y) {
                $validator->errors()->add($atributo, 'Um dos modelos exigidos não existe ou está desativado.');

                continue;
            }

            if ($raiz === $minhaRaiz) {
                $validator->errors()->add($atributo, 'Um modelo não pode exigir a si mesmo.');

                continue;
            }

            if (isset($vistos[$raiz])) {
                $validator->errors()->add($atributo, 'O modelo "' . $y->name . '" foi vinculado duas vezes.');

                continue;
            }

            $vistos[$raiz] = true;

            if ($minhaRaiz !== null && in_array($minhaRaiz, $composer->reachableRoots($raiz), true)) {
                $validator->errors()->add($atributo, 'O modelo "' . $y->name . '" já exige este (direta ou '
                    . 'indiretamente): o vínculo é de um sentido só.');

                continue;
            }

            $composto = $y->composed();
            $camposDeY = collect($composto->declaredVariables())->keyBy('key');
            $partesDeY = collect($composto->declaredParties())->pluck('key')->push(LinkedTemplateComposer::GENERIC)->all();

            $campos = [];

            foreach ((array) ($vinculo['fields'] ?? []) as $chave => $alvo) {
                $campo = $camposDeY->get((string) $chave);

                if (!$campo) {
                    continue;
                }

                $alvo = is_string($alvo) && $alvo !== '' ? $alvo : null;

                if ($alvo !== null && ($tiposDeX[$alvo] ?? null) !== $campo['type']) {
                    $validator->errors()->add(
                        "linked_templates.{$i}.fields",
                        'O campo "' . $campo['label'] . '" de "' . $y->name . '" só pode ser ligado a um campo '
                            . 'deste modelo do mesmo tipo (' . (SignatureFieldTypes::LABELS[$campo['type']] ?? $campo['type']) . ').',
                    );

                    continue;
                }

                $campos[(string) $chave] = $alvo;
            }

            $partes = [];

            foreach ((array) ($vinculo['parties'] ?? []) as $chave => $alvo) {
                $chave = (string) $chave;

                if (!in_array($chave, $partesDeY, true) || !is_string($alvo) || $alvo === '') {
                    continue;
                }

                $valido = $alvo === LinkedTemplateComposer::GENERIC
                    || in_array($alvo, $partesDeX, true)
                    || ($alvo === LinkedTemplateComposer::NEW_PARTY && $chave !== LinkedTemplateComposer::GENERIC);

                if (!$valido) {
                    $validator->errors()->add(
                        "linked_templates.{$i}.parties",
                        'Escolha de novo onde assina, neste modelo, cada parte de "' . $y->name . '".',
                    );

                    continue;
                }

                $partes[$chave] = $alvo;
            }

            $this->linkedTemplates[] = ['root_id' => $raiz, 'fields' => $campos, 'parties' => $partes];
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'variables.*.key.regex' => 'A chave da variável deve começar por letra minúscula e conter '
                . 'apenas letras minúsculas, números e underline (ex.: nome_do_espaco).',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'body_html' => 'texto do documento',
            'identity_check' => 'conferência de identidade',
            'retention_months' => 'prazo de guarda',
        ];
    }
}
