<?php

namespace App\Models;

use App\Services\Signature\SignatureFieldTypes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo de documento — o texto que o tablet exibe, com as variáveis que o
 * atendente preenche.
 *
 * Versionado por linha: `newVersion()` cria a versão seguinte e desativa a
 * anterior. Nunca se edita uma linha que já gerou documento — é isso que faz
 * um termo assinado em março continuar sendo o termo de março.
 *
 * @property int $id
 * @property int $root_id
 * @property int $version
 * @property string $body_html
 * @property bool $active
 */
class SignatureTemplate extends Model
{
    use HasFactory;

    public const IDENTITY_PARTIAL = 'partial';
    public const IDENTITY_FULL = 'full';
    public const IDENTITY_NONE = 'none';
    /** Código de 6 números enviado ao e-mail do signatário, digitado no tablet. */
    public const IDENTITY_EMAIL = 'email';

    public const IDENTITY_CHECKS = [
        self::IDENTITY_PARTIAL => 'Quatro primeiros dígitos do CPF',
        self::IDENTITY_FULL => 'CPF completo',
        self::IDENTITY_EMAIL => 'Código enviado por e-mail',
        self::IDENTITY_NONE => 'Sem conferência',
    ];

    protected $fillable = [
        'root_id',
        'version',
        'name',
        'description',
        'body_html',
        'variables',
        'parties',
        'attachments',
        'signature_placeholder',
        'signature_position',
        'requires_photo',
        'requires_initials',
        'identity_check',
        'retention_months',
        'active',
        'single_use',
        'created_by',
    ];

    /**
     * Defaults no MODEL, e não só no schema: o código lê `version` logo depois
     * de criar o modelo (para gravá-la no documento), e um default que só
     * existe no banco chega como null na instância recém-criada.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'version' => 1,
        'active' => true,
        'requires_photo' => true,
        'requires_initials' => false,
        'single_use' => false,
        'identity_check' => self::IDENTITY_PARTIAL,
        'signature_placeholder' => '[[assinatura]]',
    ];

    protected $casts = [
        'root_id' => 'integer',
        'version' => 'integer',
        'variables' => 'array',
        'parties' => 'array',
        'attachments' => 'array',
        'signature_position' => 'array',
        'requires_photo' => 'boolean',
        'requires_initials' => 'boolean',
        'retention_months' => 'integer',
        'active' => 'boolean',
        'single_use' => 'boolean',
        'created_by' => 'integer',
    ];

    /**
     * A primeira versão de um modelo aponta para si mesma. Feito aqui, e não
     * na migration, porque o id só existe depois da inserção — e um segundo
     * save() é barato num evento que acontece uma vez por modelo.
     */
    protected static function booted(): void
    {
        static::created(function (SignatureTemplate $template) {
            if ($template->root_id === null) {
                $template->forceFill(['root_id' => $template->id])->saveQuietly();
            }
        });
    }

    /**
     * @return HasMany<SignatureDocument>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(SignatureDocument::class);
    }

    /** Todas as versões deste modelo, da mais nova para a mais antiga. */
    public function versions()
    {
        return static::where('root_id', $this->root_id ?? $this->id)->orderByDesc('version');
    }

    /** A versão vigente do modelo — a que a tela do atendente oferece. */
    public function currentVersion(): self
    {
        return static::where('root_id', $this->root_id ?? $this->id)
            ->orderByDesc('version')
            ->first() ?? $this;
    }

    /**
     * Cria a versão seguinte com os campos alterados e desativa esta.
     *
     * Devolve a linha nova. A antiga continua existindo, inativa: é para onde
     * os documentos já criados apontam.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function newVersion(array $attributes, ?int $userId = null): self
    {
        $root = $this->root_id ?? $this->id;

        $next = static::where('root_id', $root)->max('version') + 1;

        $novo = static::create(array_merge(
            $this->only([
                'name',
                'description',
                'body_html',
                'variables',
                'parties',
                'attachments',
                'signature_placeholder',
                'signature_position',
                'requires_photo',
                'requires_initials',
                'identity_check',
                'retention_months',
            ]),
            $attributes,
            [
                'root_id' => $root,
                'version' => $next,
                'active' => true,
                'created_by' => $userId,
            ],
        ));

        static::where('root_id', $root)
            ->where('id', '!=', $novo->id)
            ->update(['active' => false]);

        return $novo;
    }

    /** Já gerou documento? Modelo usado não é apagado, é desativado. */
    public function inUse(): bool
    {
        return static::where('root_id', $this->root_id ?? $this->id)
            ->whereHas('documents')
            ->exists();
    }

    /**
     * As partes que assinam este modelo — "Contratante", "Contratado".
     *
     * Cada uma tem o seu lugar no texto (`[[assinatura:contratante]]`) e o
     * rótulo que sai impresso sob o nome de quem assina por ela. Vazio para o
     * modelo de sempre: um `[[assinatura]]` só, com todos os signatários.
     *
     * @return array<int, array{key: string, label: string}>
     */
    public function declaredParties(): array
    {
        return collect($this->parties ?? [])
            ->map(fn($p) => [
                'key' => (string) ($p['key'] ?? ''),
                'label' => (string) ($p['label'] ?? ($p['key'] ?? '')),
            ])
            ->filter(fn($p) => $p['key'] !== '')
            ->values()
            ->all();
    }

    /**
     * Os anexos que este modelo pede — identidade, comprovante. Cada item tem
     * chave, rótulo e se é obrigatório. Ver SignatureAttachmentService.
     *
     * @return array<int, array{key: string, label: string, required: bool}>
     */
    public function declaredAttachments(): array
    {
        return collect($this->attachments ?? [])
            ->filter(fn($a) => ($a['key'] ?? '') !== '' && ($a['label'] ?? '') !== '')
            ->map(fn($a) => [
                'key' => (string) $a['key'],
                'label' => (string) $a['label'],
                'required' => (bool) ($a['required'] ?? false),
            ])
            ->values()
            ->all();
    }

    /** O marcador de uma parte no texto. */
    public static function partyPlaceholder(string $key): string
    {
        return '[[assinatura:' . $key . ']]';
    }

    /**
     * As variáveis declaradas, normalizadas — os formulários do atendente e do
     * tablet são montados a partir disto.
     *
     * Um modelo gravado antes de os campos terem tipo só traz chave, rótulo e
     * obrigatoriedade: vale como texto — que é exatamente o que ele era.
     *
     * Quem responde cada campo (atendente ou quem assina) NÃO é do modelo: é
     * marcado no preenchimento de cada documento — ver
     * `SignatureDocument::fieldDefinitions()`. Um `ask_signer` antigo que
     * tenha ficado no JSON não é lido.
     *
     * @return array<int, array{
     *     key: string, label: string, required: bool, type: string,
     *     question: string, options: array<int, string>
     * }>
     */
    public function declaredVariables(): array
    {
        return collect($this->variables ?? [])
            ->map(function ($v) {
                $rotulo = (string) ($v['label'] ?? ($v['key'] ?? ''));
                $tipo = SignatureFieldTypes::exists($v['type'] ?? null) ? $v['type'] : SignatureFieldTypes::TEXT;

                return [
                    'key' => (string) ($v['key'] ?? ''),
                    'label' => $rotulo,
                    'required' => (bool) ($v['required'] ?? false),
                    'type' => $tipo,
                    // Como perguntar, se o atendente mandar o campo ao tablet.
                    // Sem pergunta escrita, a pergunta é o rótulo.
                    'question' => trim((string) ($v['question'] ?? '')) ?: $rotulo,
                    'options' => SignatureFieldTypes::hasOptions($tipo)
                        ? array_values(array_map('strval', (array) ($v['options'] ?? [])))
                        : [],
                ];
            })
            ->filter(fn($v) => $v['key'] !== '')
            ->values()
            ->all();
    }

    /**
     * Os campos que ALGUÉM preenche — o atendente, ou quem assina quando o
     * atendente os marca para perguntar no tablet. Só os automáticos ficam de
     * fora.
     *
     * @return array<int, array<string, mixed>>
     */
    public function manualFields(): array
    {
        return array_values(array_filter(
            $this->declaredVariables(),
            fn($v) => !SignatureFieldTypes::isAutomatic($v['type']),
        ));
    }

    /**
     * Os campos que o servidor resolve sozinho no ato da assinatura.
     *
     * @return array<int, array<string, mixed>>
     */
    public function automaticFields(): array
    {
        return array_values(array_filter(
            $this->declaredVariables(),
            fn($v) => SignatureFieldTypes::isAutomatic($v['type']),
        ));
    }
}
