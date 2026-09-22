<?php

namespace App\Models;

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

    public const IDENTITY_CHECKS = [
        self::IDENTITY_PARTIAL => 'Quatro primeiros dígitos do CPF',
        self::IDENTITY_FULL => 'CPF completo',
        self::IDENTITY_NONE => 'Sem conferência',
    ];

    protected $fillable = [
        'root_id',
        'version',
        'name',
        'description',
        'body_html',
        'variables',
        'signature_placeholder',
        'signature_position',
        'requires_photo',
        'identity_check',
        'retention_months',
        'active',
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
        'identity_check' => self::IDENTITY_PARTIAL,
        'signature_placeholder' => '[[assinatura]]',
    ];

    protected $casts = [
        'root_id' => 'integer',
        'version' => 'integer',
        'variables' => 'array',
        'signature_position' => 'array',
        'requires_photo' => 'boolean',
        'retention_months' => 'integer',
        'active' => 'boolean',
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
                'signature_placeholder',
                'signature_position',
                'requires_photo',
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
     * As variáveis declaradas, normalizadas — a tela do atendente monta o
     * formulário a partir disto.
     *
     * @return array<int, array{key: string, label: string, required: bool}>
     */
    public function declaredVariables(): array
    {
        return collect($this->variables ?? [])
            ->map(fn($v) => [
                'key' => (string) ($v['key'] ?? ''),
                'label' => (string) ($v['label'] ?? ($v['key'] ?? '')),
                'required' => (bool) ($v['required'] ?? false),
            ])
            ->filter(fn($v) => $v['key'] !== '')
            ->values()
            ->all();
    }
}
