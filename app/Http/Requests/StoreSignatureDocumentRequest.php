<?php

namespace App\Http\Requests;

use App\Models\SignatureSigner;
use App\Models\SignatureTemplate;
use App\Services\Signature\Directors\DirectorAreaService;
use App\Services\Signature\Directors\DirectorException;
use App\Services\Signature\SignatureFieldTypes;
use App\Support\Cpf;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Criação e correção de um documento pelo atendente.
 *
 * O CPF é normalizado ANTES de validar: ele chega com máscara do formulário e
 * sem máscara da busca de associado, e uma regra de tamanho sobre o texto cru
 * recusaria justamente o caminho mais comum.
 */
class StoreSignatureDocumentRequest extends FormRequest
{
    /**
     * Os dados do modelo, conferidos e na forma canônica de cada tipo.
     *
     * @var array<string, mixed>
     */
    private array $fieldData = [];

    /**
     * Os campos que o atendente marcou para perguntar a quem assina.
     *
     * @var array<int, string>
     */
    private array $signerFieldKeys = [];

    /**
     * Diretor escolhido que não pôde ser resolvido: índice da linha => motivo.
     *
     * @var array<int|string, string>
     */
    private array $directorErrors = [];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * O que vai para `data`: só os campos que o ATENDENTE preenche, já
     * conferidos. Os marcados para perguntar a quem assina e os automáticos
     * não saem daqui nem que o formulário os mande — eles têm outra porta
     * (SignatureSigningDataService).
     *
     * @return array<string, mixed>
     */
    public function fieldData(): array
    {
        return $this->fieldData;
    }

    /**
     * As chaves marcadas em "Perguntar ao signatário", só entre os campos do
     * modelo que alguém preenche: chave inventada ou de campo automático não
     * entra.
     *
     * @return array<int, string>
     */
    public function signerFieldKeys(): array
    {
        return $this->signerFieldKeys;
    }

    /**
     * Os anexos pedidos por este documento, na forma gravada.
     *
     * @return array<int, array{key: string, label: string, required: bool}>
     */
    public function attachmentRequirements(): array
    {
        return \App\Services\Signature\SignatureAttachmentService::normalize(
            array_values((array) $this->input('attachments', [])),
            'doc',
        );
    }

    /**
     * Confere cada campo pelo TIPO dele. Em branco passa — a obrigatoriedade é
     * do congelamento, para o rascunho poder ficar incompleto —, mas um CPF
     * preenchido errado é recusado já aqui.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->directorErrors as $i => $mensagem) {
                $validator->errors()->add('signers.' . $i . '.director', $mensagem);
            }

            // Na correção o modelo é o do documento: ele não muda depois de criado.
            $documento = $this->route('signatureDocument');

            $modelo = is_object($documento)
                ? $documento->template
                : SignatureTemplate::find($this->input('signature_template_id'));

            if (!$modelo) {
                return;
            }

            $bruto = (array) $this->input('data', []);
            $marcados = array_map('strval', array_filter((array) $this->input('ask_signer', []), 'is_scalar'));

            foreach ($modelo->manualFields() as $campo) {
                // Vai ao tablet: quem responde é quem assina, e o que o
                // atendente tenha digitado nele não é gravado.
                if (in_array($campo['key'], $marcados, true)) {
                    $this->signerFieldKeys[] = $campo['key'];

                    continue;
                }

                [$valor, $erro] = SignatureFieldTypes::parse($campo, $bruto[$campo['key']] ?? null);

                if ($erro !== null) {
                    $validator->errors()->add('data.' . $campo['key'], $erro);
                } elseif ($valor !== null) {
                    $this->fieldData[$campo['key']] = $valor;
                }
            }
        });
    }

    /**
     * Normaliza o CPF e resolve o DIRETOR escolhido.
     *
     * Diretor (Assinatura Diretoria) chega como `director` = "área:usuário".
     * Nome, CPF, e-mail e o vínculo com a área saem do cadastro do Lara, aqui
     * no servidor — o que o formulário mande nesses campos é descartado, e
     * `user_id`/`director_*` que chegarem soltos (sem escolha válida) também:
     * só o servidor diz quem é diretor.
     */
    protected function prepareForValidation(): void
    {
        $diretoria = app(DirectorAreaService::class);

        $signers = collect($this->input('signers', []))
            ->map(function ($signer, $i) use ($diretoria) {
                $signer = is_array($signer) ? $signer : [];

                unset($signer['user_id'], $signer['director_area_id'], $signer['director_area_name'], $signer['director_role']);

                $signer['cpf'] = Cpf::digits($signer['cpf'] ?? '');

                $escolha = trim((string) ($signer['director'] ?? ''));

                if ($escolha === '') {
                    return $signer;
                }

                [$area, $usuario] = array_pad(explode(':', $escolha, 2), 2, null);

                if (!ctype_digit((string) $area) || !ctype_digit((string) $usuario)) {
                    $this->directorErrors[$i] = 'Escolha o diretor de novo.';

                    return $signer;
                }

                try {
                    return array_merge($signer, $diretoria->signerData((int) $area, (int) $usuario));
                } catch (DirectorException $e) {
                    $this->directorErrors[$i] = $e->getMessage();

                    return $signer;
                }
            })
            ->all();

        $this->merge(['signers' => $signers]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'signature_template_id' => ['required', 'integer', 'exists:signature_templates,id'],
            'title' => ['nullable', 'string', 'max:200'],
            'ask_signer' => ['nullable', 'array'],

            // Valores das variáveis do modelo. A obrigatoriedade de cada uma é
            // do MODELO, e é conferida no congelamento — aqui o rascunho pode
            // ficar incompleto de propósito, para o atendente salvar e voltar.
            'data' => ['nullable', 'array'],

            // Anexos pedidos só por ESTE documento, além dos do modelo.
            'attachments' => ['nullable', 'array', 'max:20'],
            'attachments.*.label' => ['nullable', 'string', 'max:120'],
            'attachments.*.required' => ['nullable', 'boolean'],

            'signers' => ['required', 'array', 'min:1', 'max:5'],
            'signers.*.name' => ['required', 'string', 'max:150'],
            'signers.*.cpf' => ['required', 'digits:11'],
            'signers.*.member_id' => ['nullable', 'integer'],
            'signers.*.email' => ['nullable', 'email', 'max:150'],
            'signers.*.phone' => ['nullable', 'string', 'max:30'],
            'signers.*.role' => ['nullable', Rule::in(array_keys(SignatureSigner::ROLE_LABELS))],
            // A parte do modelo pela qual a pessoa assina ("contratante"). Se o
            // modelo não a declara, o serviço a descarta.
            'signers.*.party' => ['nullable', 'string', 'max:60'],
            // Diretor do clube: a escolha ("área:usuário") e o que o servidor
            // resolveu a partir dela em prepareForValidation.
            'signers.*.director' => ['nullable', 'string', 'max:40'],
            'signers.*.user_id' => ['nullable', 'integer'],
            'signers.*.director_area_id' => ['nullable', 'integer'],
            'signers.*.director_area_name' => ['nullable', 'string', 'max:120'],
            'signers.*.director_role' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'signers.required' => 'Informe quem vai assinar o documento.',
            'signers.*.cpf.digits' => 'O CPF do signatário deve ter 11 dígitos.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'signature_template_id' => 'modelo',
            'title' => 'título',
            'signers.*.name' => 'nome do signatário',
            'signers.*.cpf' => 'CPF do signatário',
            'signers.*.director' => 'diretor',
        ];
    }
}
